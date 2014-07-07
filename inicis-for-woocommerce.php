<?php
/*
Plugin Name: INICIS for WooCommerce
Plugin URI: http://www.codemshop.com
Description: 엠샵에서 개발한 KG 이니시스의 워드프레스 우커머스 이용을 위한 결제 시스템 플러그인 입니다. KG INICIS Payment Gateway Plugin for Wordpress WooCommerce that developed by MShop.
Version: 1.0.4
Author: CODEM(c)
Author URI: http://www.codemshop.com
*/

//소스에 URL로 직접 접근 방지
if ( ! defined( 'ABSPATH' ) ) exit;

date_default_timezone_set(get_option('timezone_string'));  //설정된 시간대에 맞는 타임존 설정

//WooCommerce 로드 이후에 사용하도록 처리
add_action('plugins_loaded', 'inicis_languages_load',0);
add_action('plugins_loaded', 'woocommerce_inicis_pg_init', 1);
add_action('woocommerce_view_order', 'mypage_refund_request', 6);
add_action('wp_head', 'add_header_meta_for_ie');	//IE10 결제 처리용 메타 태그 추가
add_action('add_meta_boxes', 'add_meta_boxes_inicis');
add_action( 'wp_ajax_codem_order_cancelled', 'ajax_codem_order_cancelled_callback' );
register_deactivation_hook( __FILE__, 'inicis_pg_deactivate' );

/**
 * Inicis Order Cancel Button View Function
 * 이니시스 주문 취소 버튼 정보 표시 함수
 * (이 함수를 통해서 마이페이지 또는 나의-계정 페이지에서 노출되는 주문 리스트에서 취소 버튼이 나타남)
 *
 * @return void
 * @author Alan
 */
function inicis_order_action($actions, $order){
	
	//사용자 환불 가능 상태 정보를 가져옴
 	$default_data = get_option('woocommerce_inicis_mypage_refund');
    $status_method_arr = explode( ':', $default_data );  //문자열 분리

    if( in_array($order->status, $status_method_arr) && (get_post_meta($order->id, '_payment_method', true) == 'inicis') ){	
		
		$cancel_endpoint = get_permalink( wc_get_page_id( 'cart' ) );
		$myaccount_endpoint = get_permalink( wc_get_page_id( 'myaccount' ) );
	
		$actions['cancel'] = array(
			'url'  => wp_nonce_url( add_query_arg( array( 'inicis-cancel-order' => 'true', 'order' => $order->order_key, 'order_id' => $order->id, 'redirect' => $myaccount_endpoint ), $cancel_endpoint ), 'mshop-cancel-order' ),
			'name' => __( 'Cancel', 'woocommerce' )
		);
		
		$actions['view'] = array(
			'url'  => $order->get_view_order_url(),
			'name' => __( 'View', 'woocommerce' )
		);
	}

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'inicis_order_action', 1, 2);

/**
 * Inicis Order Cancel Request Process on Mypage Order List Function
 * 이니시스 주문 취소 요청 처리(마이페이지 주문 리스트에서) 함수
 *
 * @return void
 * @author Alan
 */
function inicis_mypage_cancel_order(){
	
	if ( isset( $_GET['inicis-cancel-order'] ) && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
		$order = new WC_Order($_GET['order_id']);
        cancel_request_process($order->id);
        $order->add_order_note(__('사용자에 의해 주문이 취소 처리 되었습니다.','mshop'));
        update_post_meta($order->id, '_codem_inicis_order_cancelled', TRUE);
		
	    //마이페이지에서 주문 취소시 처리후 마이페이지로 이동 처리
        wp_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ));
		die();
	}
}
add_action('init', 'inicis_mypage_cancel_order');

/**
 * Ajax 관리자 주문 취소 요청 처리 함수
 * (관리자 페이지에서 주문상세에 나타나는 메타박스에서 취소시 처리하는 함수)
 *
 * @return void
 * @author Alan 
 */
function ajax_codem_order_cancelled_callback(){
    if ( isset($_POST['refund_request']) || wp_verify_nonce($_POST['refund_request'],'refund_request') )
    {
        //pg cancel request process action.    
        $rst = cancel_request_process($_POST['post_id']);
        if($rst == "true") {
            update_post_meta($_POST['post_id'], '_codem_inicis_order_cancelled', TRUE);
            echo TRUE;
            exit;
        } else {
            echo FALSE;
            exit;
        }
    } else {
        echo FALSE;
        exit;
    }
}

/**
 * 관리자 페이지 환불 요청 메타 박스 추가
 *
 * @return void
 * @author Alan 
 */
function add_meta_boxes_inicis(){
    remove_meta_box('postcustom', 'shop_order', 'normal'); //사용자 커스텀 필드 숨김 처리    

    if(!empty($_GET['post'])) {
        
        $orderinfo = new WC_Order($_GET['post']);
    
        $default_data = get_option('woocommerce_inicis_admin_refund');
        $status_method_arr = explode( ':', $default_data );  //문자열 분리
        if(count($status_method_arr) == 1 && empty($status_method_arr[0])) {
            $status_method_arr = array(__('refunded','codem_inicis'));
        }
    
        if( in_array($orderinfo->status, $status_method_arr) && (get_post_meta($_GET['post'], '_payment_method', true) == 'inicis') ){
            add_meta_box(
                'woocommerce-order-refund-request'
                ,__( '이니시스 결제', 'codem_inicis' )
                ,'metabox_refund_request'
                ,'shop_order'
                ,'side'
                ,'default'
            );
        }        
    }
}

/**
 * 관리자 페이지 환불 요청 메타 박스 출력 내용
 *
 * @param $post : Order id.
 * @return void
 * @author Alan 
 */
function metabox_refund_request($post){

    $orderinfo = new WC_Order($post->ID);
    
    $default_data = get_option('woocommerce_inicis_admin_refund');
    $status_method_arr = explode( ':', $default_data );  //문자열 분리

    if(count($status_method_arr) == 1 && empty($status_method_arr[0])) {
        $status_method_arr = array(__('refunded','codem_inicis'));
    }
    
    echo '
    <script type="text/javascript">
    
        function sleep(milliseconds) {
          var start = new Date().getTime();
          for (var i = 0; i < 1e7; i++) {
            if ((new Date().getTime() - start) > milliseconds){
              break;
            }
          }
        }    

        function onClickCancelRequest(){
            var data = {
                action: "codem_order_cancelled",
                post_id: '.$post->ID.',
                refund_request: "'.wp_create_nonce('refund_request').'",
            };
    
            jQuery("[name=\'refund-request\']").attr(\'disabled\',\'true\');
            jQuery("[name=\'refund-request\']").attr(\'value\', "'.__("처리중...","codem_inicis").'");
            
            jQuery.post(ajaxurl, data, function(response) {
                if( response == "1") {
                    alert("'.__('환불 처리가 완료되었습니다!','codem_inicis').'");
                    location.href="'.admin_url('post.php?post='.$post->ID.'&action=edit').'";    
                } else {
                    alert("'.__('환불 처리가 실패되었습니다!\n\n다시 시도해 주세요!\n\n계속 동일 증상 발생시 주문상태를 확인해주세요!','codem_inicis').'");
                    jQuery("[name=\'refund-request\']").removeAttr("disabled");
                    jQuery("[name=\'refund-request\']").attr(\'value\',"'.__('주문 환불하기','codem_inicis').'");
                }
            });
        }

        function checkReceipt(){
            window.open("https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid='.get_post_meta($post->ID, 'inicis_paymethod_tid', true).'");
        } 
    </script>';
    if( in_array($orderinfo->status, $status_method_arr) && (get_post_meta($post->ID, '_payment_method', true) == 'inicis') ){
        
        if(get_post_meta($post->ID, '_codem_inicis_order_cancelled')) {
            echo '<p class="order-info">
            <input type="button" class="button button-primary tips" name="refund-request" value="'.__('환불 완료','codem_inicis').'" disabled="true">
            <input type="button" class="button button-primary tips" name="refund-request-check-receipt" value="'.__('영수증 확인','codem_inicis').'" onClick="javascript:checkReceipt();">';
            wp_nonce_field('refund_request','refund_request');
            echo '</p>';
    
        } else {
            echo '<p class="order-info">
            <input type="button" class="button button-primary tips" name="refund-request" value="'.__('환불하기','codem_inicis').'" onClick="javascript:onClickCancelRequest();">
            <input type="button" class="button button-primary tips" name="refund-request-check-receipt" value="'.__('영수증 확인','codem_inicis').'" onClick="javascript:checkReceipt();">';
            wp_nonce_field('refund_request','refund_request');
            echo '</p>';
        }
    } 
}

/**
 * 마이페이지 환불 요청 페이지 & 처리 함수
 * MyPage Refund Request 'Page Content' & 'Process' Function
 *
 * @param $order : Order id.
 * @return void
 * @author Alan 
 */
function mypage_refund_request($order) {
	
    $orderinfo = new WC_Order($order);

    if (isset($_POST['refund_request']) || wp_verify_nonce($_POST['refund_request'],'refund_request') )
    {
        //pg cancel request process action.    
        cancel_request_process($order);
        update_post_meta($order, '_codem_inicis_order_cancelled', TRUE);
        echo "<script type='text/javascript'>
        location.reload();
        </script>";
    }
    
    $default_data = get_option('woocommerce_inicis_mypage_refund');
    $status_method_arr = explode( ':', $default_data );  //문자열 분리

    if( in_array($orderinfo->status, $status_method_arr) && (get_post_meta($order, '_payment_method', true) == 'inicis') ){
        if ( get_post_meta($order, '_codem_inicis_order_cancelled') == TRUE )
        {
            echo '<h2>'.__('주문취소','codem_inicis').'</h2>';
            echo '<p class="order-info">'.__('주문취소 처리가 완료되었습니다.','codem_inicis').'</p>
            <p class="order-info">
            <form name="refund_request" method="POST" action="">
            <input type="hidden" name="request_order" id="request_order" value="'.$order.'"/>';
        } else {
            echo '<h2>'.__('주문취소','codem_inicis').'</h2>';
            echo '<p class="order-info">주문한 상품을 취소합니다. 결제 방법 및 취소 기간 등에 따라 카드사 환불이 늦어질 수 있습니다.</p>
            <p class="order-info">
            <form name="refund_request" method="POST" action="">
            <input type="hidden" name="request_order" id="request_order" value="'.$order.'"/>';
            echo '<input type="submit" class="button" name="button_refund_request" value="'.__('주문 취소','codem_inicis').'"/>';
            wp_nonce_field('refund_request','refund_request'); 
        }    
        echo '</form></p>';        
    } 
}

/**
 * 플러그인 비활성화 처리
 * Plugin Deactive Process
 *
 * @return void
 * @author Alan 
 */
function inicis_pg_deactivate(){
	$key = get_option('_codem_inicis_activate_key');
	header("Content-Type: text/html;charset=utf-8"); 
    $http_url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2RlYWN0aXZhdGUtcmVnaXN0ZXI=");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $http_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ko"));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,  array('action'=>'deactivate-register', 'activate_key' => $key));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $httpResponse = curl_exec($ch);
	delete_option('_codem_inicis_activate_key');
	curl_close($ch);
		
	if($httpResponse == "1") {
		return true;	
	} else {
		return false;
	}
}

/**
 * 이니시스 IE10 호환 코드 추가
 * only page slug 'pay' page, use this code.
 * for IE10 Browser Payment.
 * 
 * @return void
 * @author Alan 
 */
function add_header_meta_for_ie() {
	global $post;
	
	//slug가 pay인 경우 메타 태그 추가
	if( $post->post_name == 'pay') {
		echo '
		<!--[if lte IE 9]>
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
		<![endif]-->
		<!--[if gte IE 10]>
		<meta http-equiv="X-UA-Compatible" content="requiresActiveX=true,IE=edge,chrome=1" />
		<![endif]-->';	
	}
}

/**
 * 주문 취소처리시 (주문취소는, pending, on-hold 인경우 가능)
 *
 * @param $post_id : Post ID Number
 * @return void
 * @author Alan 
 */
function cancel_request_process($post_id){
    global $woocommerce;
    $order = new WC_Order($post_id);
    
    //취소처리가 완료된 이후의 액션 시작 
    $paymethod = get_post_meta($post_id, "inicis_paymethod", true); //결제방법
    $paymethod = strtolower($paymethod); //소문자로 변경
    
    $paymethod_tid = get_post_meta($post_id, "inicis_paymethod_tid", true); //거래번호
    $paymethod_mid = get_post_meta($post_id, "inicis_paymethod_mid", true); //상정아이디

    $default_data = get_option('woocommerce_inicis_redirect_order_status_refunded');
    $order_status_arr = explode( ':', $default_data );  //문자열 분리
    
    //default value not found setting
    if(count($order_status_arr) == 1 && empty($order_status_arr[0])) {
        $order_status_arr = array(__('refunded','codem_inicis'));
    }
     
    $rst = "false";
    
    if($paymethod != ""){
        switch($paymethod){
            case "card": //카드 결제시
                $rst = send_pg_cancel_request($paymethod_mid, $paymethod_tid, "거래취소", "1");
                if($rst == "true"){
                    //우커머스 기존 내용에서 가져옴(취소처리)
                    if($_POST['refund_request']) {
                        unset($_POST['refund_request']);
                    }
                    $order->update_status( $order_status_arr[0] );
                    $order->add_order_note( sprintf( __('카드결제 거래취소 처리 완료! 영수증 및 이니시스 상점에서 최종 거래 취소 여부를 확인해주세요! <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>', 'codem_inicis'), $paymethod_tid) );  //주문노트에 메시지 작성
                } else {
                    //결제 취소 실패시
                    $order->add_order_note( sprintf(__('카드결제 거래취소 처리 실패. 에러코드 : %s', 'codem_inicis'), $rst));  //주문노트에 메시지 작성
                }
                break;
            case "vcard": //ISP 결제시
                $rst = send_pg_cancel_request($paymethod_mid, $paymethod_tid, "거래취소", "1");
                if($rst == "true"){
                    //우커머스 기존 내용에서 가져옴(취소처리)
                    if($_POST['refund_request']) {
                        unset($_POST['refund_request']);
                    }
                    $order->update_status( $order_status_arr[0] );
                    $order->add_order_note( sprintf( __('카드결제 거래취소 처리 완료! 영수증 및 이니시스 상점에서 최종 거래 취소 여부를 확인해주세요! <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>', 'codem_inicis'), $paymethod_tid) );  //주문노트에 메시지 작성                    $woocommerce->add_message( __( '주문이 취소 되었습니다.', 'codem_inicis' ) );    
                } else {
                    //결제 취소 실패시
                    $order->add_order_note( sprintf(__('카드결제 거래취소 처리 실패. 에러코드 : %s', 'codem_inicis'), $rst));  //주문노트에 메시지 작성
                }
                break;
            case "wcard": //ISP 결제시
                $rst = send_pg_cancel_request($paymethod_mid, $paymethod_tid, "거래취소", "1");
                if($rst == "true"){
                    //우커머스 기존 내용에서 가져옴(취소처리)
                    if($_POST['refund_request']) {
                        unset($_POST['refund_request']);
                    }
                    $order->update_status( $order_status_arr[0] );
                    $order->add_order_note( sprintf( __('카드결제 거래취소 처리 완료! 영수증 및 이니시스 상점에서 최종 거래 취소 여부를 확인해주세요! <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>', 'codem_inicis'), $paymethod_tid) );  //주문노트에 메시지 작성                    $woocommerce->add_message( __( '주문이 취소 되었습니다.', 'codem_inicis' ) );    
                } else {
                    //결제 취소 실패시
                    $order->add_order_note( sprintf(__('카드결제 거래취소 처리 실패. 에러코드 : %s', 'codem_inicis'), $rst));  //주문노트에 메시지 작성
                }
                break;
			default:	//기타 결제 방법 취소시 처리 방법 추가 기재
                $rst = send_pg_cancel_request($paymethod_mid, $paymethod_tid, "거래취소", "1");
                if($rst == "true"){
                    //우커머스 기존 내용에서 가져옴(취소처리)
                    if($_POST['refund_request']) {
                        unset($_POST['refund_request']);
                    }
                    $order->update_status( $order_status_arr[0] );
                    $order->add_order_note( sprintf( __('기타 거래취소 처리 완료! 영수증 및 이니시스 상점에서 최종 거래 취소 여부를 확인해주세요! <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>', 'codem_inicis'), $paymethod_tid) );  //주문노트에 메시지 작성                    $woocommerce->add_message( __( '주문이 취소 되었습니다.', 'codem_inicis' ) );    
                } else {
                    //결제 취소 실패시
                    $order->add_order_note( sprintf(__('기타 거래취소 처리 실패. 에러코드 : %s', 'codem_inicis'), $rst));  //주문노트에 메시지 작성
                }
                break;
		}
    } else {
        $order->cancel_order( __('고객이 주문을 취소하였습니다.', 'codem_inicis' ) ); 
        $woocommerce->add_message( __( '주문이 취소 되었습니다.', 'codem_inicis' ) );    
    }
    
    return $rst;
}

/**
 * 주문 취소처리시 (주문취소는, pending, on-hold 인경우 가능)
 *
 * @param $mid : 상점코드 (테스트 결제시에는 'INIpayTest' 사용)
 * @param $tid : 거래번호 TID (PG에서 생성한 번호)
 * @param $msg : 취소 사유 내용(텍스트)
 * @param $code : 취소코드(현금영수증), 1: 거래취소, 2:오류, 3: 기타사항
 * @example send_pg_cancel_request('INIpayTest','INIPay_CardCancel_139856239862','사용자 변심 취소 요청','1');
 * @return void
 * @author Alan 
 */
function send_pg_cancel_request($mid, $tid, $msg, $code="1"){
	//자동 결제 취소 요청 처리	
	//header("Content-Type: text/html;charset=utf-8"); 
    $http_url = home_url().base64_decode("L3djLWFwaS9XQ19HYXRld2F5X0luaWNpcz90eXBlPWNhbmNlbGxlZA=="); 
    
    //옵션에서 PG 라이브러리 경로 정보를 가져옴
	$inicis_settings = get_option('woocommerce_inicis_settings');
	if(!empty($inicis_settings['libfolder'])){
		$home = $inicis_settings['libfolder'];	
	} else {
		return;
	}
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $http_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ko"));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,  array('mid' => $mid, 'tid' => $tid, 'msg' => $msg, 'code' => $code, 'home' => $home ) );
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    $httpResponse = trim(curl_exec($ch));
	if($httpResponse == "success") {
		return "true";	
	} else {
        return trim($httpResponse);
	}
    curl_close($ch);
}

/**
 * 플러그인 언어팩 로드
 * Plugin Language Pack Load
 * (default WP_LANG : ko_KR)
 *
 * @return void
 * @author Alan 
 */
function inicis_languages_load() {
	//플러그인 서브 폴더에서 언어팩 로드
	load_plugin_textdomain( 'codem_inicis', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * 이니시스PG 설정 초기화 및 이니시스 결제 플러그인 클래스 선언
 * Inicis PG Setting Init & Define Inicis PG Class
 *
 * @return void
 * @author Alan 
 */
function woocommerce_inicis_pg_init(){
	
    //확장 클래스 존재 체크	
    if(!class_exists('WC_Payment_Gateway')) { return; }
    
    /**
     * 'WC_Gateway_Inicis' class
	 * 우커머스 이니시스 결제 플러그인용 클래스
     *
     * @package default
     * @author Alan
     */
    class WC_Gateway_Inicis extends WC_Payment_Gateway{
    	public $activate_check;
		
        public function __construct(){
            $this->id = 'inicis'; 											//결제 메소드 아이디 값
            $this->has_fields = false;    									//체크아웃 페이지에서 지불관련 필드를 노출하려면 True, 아니면 false.(체크아웃 페이지에서 바로 결제할수 있도록 하려는 경우 사용)
            $this->countries = array('KR'); 								//현재 결제 플러그인 사용가능 국가 지정
            $this->init_form_fields();    									//배열에 있는 필드 정보들을 결제 설정 화면에 표시되도록 추가 처리 및 초기화
            $this->init_settings();   										//DB에 저장되어 있는 결제 관련 셋팅값을 가져와서 각 필드에 초기화 
            $this->title = $this->settings['title'];  						//결제시에 보여지는 결제 방법 제목. 예:) '이니시스 카드 결제'
        	$this->description = $this->settings['description'];  			//결제 메소드에 따른 간단한 안내 설명 문구. 예:) '이니시스 결제를 통해서 결제를 진행합니다'
            $this->merchant_id = $this->settings['merchant_id'];  			//상점 아이디 셋팅
            $this->merchant_pw = $this->settings['merchant_pw'];  			//키파일 비밀번호 셋팅
            $this->redirect_page_id = $this->settings['redirect_page_id'];  //결제 처리 이후에 이동할 페이지 지정
            $this->msg['message'] = "";										//메시지 내용 초기화
            $this->msg['class'] = "";										//메시지 클래스 초기화
            $this->err_mid = false;
            $this->method_title = __('이니시스 결제', 'codem_inicis');			//결제 메소드 제목(title)
			$this->method_description = __('이니시스 결제 대행 서비스를 사용하시는 분들을 위한 설정 페이지입니다. 실제 서비스를 하시려면 키파일을 이니시스에서 발급받아 설치하셔야 정상 사용이 가능합니다.', 'codem_inicis');  //결제 메소드 간단한 설명
			$this->activate_check = $this->is_valid_for_key();
			//사용하는 스크립트 및 스타일 등록
			add_action( 'wp_head', array($this, 'codem_assets'));
			add_action( 'admin_head', array($this, 'codem_assets'));			
            add_action( 'woocommerce_api_wc_gateway_inicis', array( $this, 'check_inicis_response' ) );				//WC Api을 통해 들어온 요청 처리
            add_action( 'valid-inicis-request_mobile_next', array( $this, 'successful_request_mobile_next' ) );		//모바일 결제 처리시 실행할 함수 지정     
            add_action( 'valid-inicis-request_mobile_noti', array( $this, 'successful_request_mobile_noti' ) );     
            add_action( 'valid-inicis-request_mobile_return', array( $this, 'successful_request_mobile_return' ) );        
            add_action( 'valid-inicis-request_pc', array( $this, 'successful_request_pc' ) );						//PC결제 처리시 실행할 함수 지정
            add_action( 'valid-inicis-request_cancelled', array( $this, 'successful_request_cancelled' ) );						//PC결제 처리시 실행할 함수 지정

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {          								//우커머스 버전 확인 (2.0.0 이상인지 확인)            
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_gopaymethod' ) );		//결제허용 수단 설정값 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_gopaymethod_mobile' ) );		//결제허용 수단 설정값 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_acceptmethod' ) );		//기타 옵션 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_mypage_refund' ) );       //마이페이지 환불표시 옵션 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_admin_refund' ) );       //관리자 환불표시 옵션 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_redirect_order_status' ) );       //결제 완료시 주문 상태 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_inicis_redirect_order_status_refunded' ) );       //결제 완료시 주문 상태 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_activate' ) );		//기타 옵션 저장
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_activate_check' ), 10 );		//기타 옵션 저장
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
    			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_gopaymethod' ) );		//결제허용 수단 설정값 저장
    			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_gopaymethod_mobile' ) );		//결제허용 수단 설정값 저장
    			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_acceptmethod' ) );		//기타 옵션 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_mypage_refund' ) );        //마이페이지 환불표시 옵션 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_admin_refund' ) );        //관리자 환불표시 옵션 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_redirect_order_status' ) );        //결제 완료시 주문 상태 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_inicis_redirect_order_status_refunded' ) );        //결제 완료시 주문 상태 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_activate' ) );		//기타 옵션 저장
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_activate_check' ), 10 );		//기타 옵션 저장
	        }
            add_action('woocommerce_receipt_inicis', array($this, 'receipt_page'));			//지불 처리 페이지 액션ocess 처리시
            add_filter('woocommerce_payment_complete_order_status', array($this, 'set_payment_complete_order_status'), 15, 2);      //주문 완료 상태 변경 필터
        }

        /**
         * Change Order Complete Status. (ex: complete -> on-hold)
         * 우커머스 payment_complete() 함수를 사용해서 최종 결제처리를 할때,
         * 주문 상태가 커스터마이징되어 여러개 있는 경우, 원하는 주문 완료 상태를 설정 가능.  
         *
         * @param $new_order_status : New Order Status from WC Order Class Function.
         * @param $id : Order Id from Apply_filter.
         * @author Alan
         * @return bool
         */
        function set_payment_complete_order_status($new_order_status, $id){
            $order_status = get_option('woocommerce_inicis_redirect_order_status');
            if(!empty($order_status) && isset($order_status)) {
                return $order_status;
            } else {
                return $new_order_status;
            }
        }

	    /**
	     * 코드엠 js,css 추가
	     *
	     * @access public
		 * @author Alan
	     * @return bool
	     */
		function codem_assets(){
			 wp_register_script( 'codem_inicis_script', plugins_url( '/assets/js/script.min.js', __FILE__ ) );
			 wp_register_style( 'codem_inicis_style', plugins_url( '/assets/css/style.min.css', __FILE__ ) );
			 wp_enqueue_script( 'codem_inicis_script' );
			 wp_enqueue_style( 'codem_inicis_style' );
		}

	    /**
	     * 플러그인 인증 정보 확인 및 처리
	     *
	     * @access public
		 * @author Alan
	     * @return bool
	     */
		function process_activate(){
			$http_url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLXJlZ2lzdGVy");
			if(empty($_POST['p_activate_key'])) { return false; }
			if(empty($_POST['p_email'])) { return false; }
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $http_url);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ko"));
		    curl_setopt($ch, CURLOPT_POST, 1);
		    curl_setopt($ch, CURLOPT_POSTFIELDS,  array('action'=>'activate-register', 'activate_key' => $_POST['p_activate_key'], 'email' => $_POST['p_email'], 'homeurl' => home_url()));
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		    
		    $httpResponse = curl_exec($ch);
			if($httpResponse == "1") {
				curl_close($ch);	
				$this->activate_check = true;
				update_option('_codem_inicis_activate_key', $_POST['p_activate_key']);
				return true;	
			} else {
				curl_close($ch);
				$this->activate_check = false;
				return false;
			}	
		}

	    /**
	     * 상점 아이디 체크
	     *
		 * @author Alan
	     * @return bool
	     */
		function check_mid($mid){
			$this->err_mid = false;	
			if(!empty($mid)) {
				$tmpmid = substr($mid, 0, 3);
				if(!($tmpmid == base64_decode("SU5J") || $tmpmid == base64_decode("Q09E"))) {
					$tmparr = get_option('woocommerce_inicis_settings');	
					$tmparr['merchant_id'] = base64_decode('SU5JcGF5VGVzdA==');
					update_option('woocommerce_inicis_settings', $tmparr);
					$this->err_mid = true;

					$_SESSION['err_mid'] = true;
					return false; 
				}
				$_SESSION['err_mid'] = false;
				return true;
			}
			$this->err_mid = true;
			return false;	
		}

	    /**
	     * 플러그인 사용자 정보 갱신 처리
	     *
	     * @access public
		 * @author Alan
	     * @return bool
	     */
		function process_activate_check(){
			$http_url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLXJlZ2lzdGVy");
			$mid = '';
			if(empty($_POST['woocommerce_inicis_merchant_id'])) {
				return false; 
			} else {
				$mid = trim($_POST['woocommerce_inicis_merchant_id']);
				if(!$this->check_mid($mid)) {
					return false;
				} 
			}
			$tmp_key = get_option('_codem_inicis_activate_key');
			if(empty($tmp_key)) { return false; }
			
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $http_url);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ko"));
		    curl_setopt($ch, CURLOPT_POST, 1);
		    curl_setopt($ch, CURLOPT_POSTFIELDS,  array('action'=>'activate-register-mid', 'activate_key' => $tmp_key, 'mid' => $mid, 'homeurl' => home_url()));
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		    
		    $httpResponse = curl_exec($ch);
			if($httpResponse == "1") {
				curl_close($ch);	
				$this->activate_check = true;
			} else {
				curl_close($ch);
				$this->activate_check = false;
			}
		}

	    /**
	     * 이니시스 설정 패널
	     *
	     * @access public
	     * @return bool
	     */
		function admin_options() {
			global $woocommerce;
			if ( isset( $this->method_description ) && $this->method_description != '' ) {
				$tip = '<img class="help_tip" data-tip="' . esc_attr( $this->method_description ) . '" src="' . $woocommerce->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
			} else { 
				$tip = '';
			}	

			if( isset($_SESSION['err_mid']) && $_SESSION['err_mid'] == true ) {
				echo '<div id="message" class="error fade"><p><strong>상점 아이디가 정확하지 않습니다. 상점 아이디를 확인하여 주세요. 문제가 계속 된다면 메뉴얼 또는 <a href="http://www.wordpressshop.co.kr" target="_blank">http://www.wordpressshop.co.kr</a> 사이트에 문의하여 주세요. </strong></p></div>';
			}

			?>
			<h3><?php echo $this->method_title; echo $tip;?></h3>

			<?php
			if( !$this->activate_check ) {
			?>	
	            <style type="text/css" >
	            	.submit input.button-primary { 
	            		display:none !important;
	            	}
	            </style>
	            <script type="text/javascript">
	            	function isValidEmail(email_address)  
					{
					    var format = /^([0-9a-zA-Z_-]+)@([0-9a-zA-Z_-]+)(\.[0-9a-zA-Z_-]+){1,2}$/;  
					    if (email_address.search(format) != -1) {  
					        return true;  
					    } else {
					        return false;  
					    }
					}
					
					function checkBizID(bizID) 
					{ 
					    var checkID = new Array(1, 3, 7, 1, 3, 7, 1, 3, 5, 1); 
					    var tmpBizID, i, chkSum=0, c2, remander; 
					     bizID = bizID.replace(/-/gi,''); 
					
					     for (i=0; i<=7; i++) chkSum += checkID[i] * bizID.charAt(i); 
					     c2 = "0" + (checkID[8] * bizID.charAt(8)); 
					     c2 = c2.substring(c2.length - 2, c2.length); 
					     chkSum += Math.floor(c2.charAt(0)) + Math.floor(c2.charAt(1)); 
					     remander = (10 - (chkSum % 10)) % 10 ; 
					
					    if (Math.floor(bizID.charAt(9)) == remander) return true ; 
					      return false; 
					}
					
					function frmcheck(){
						var pflag = false;
						var msg = "";
						if(jQuery("#p_email").val() != "" && isValidEmail(jQuery("#p_email").val() )) { 
							pflag = true; 
						} else { 
							pflag = false; 
							msg = msg + '- 이니시스 사이트 가입시 인증하셨던 이메일을 정확하게 입력해주세요. \n'; 
						}
						if(jQuery("#p_activate_key").val() != "" ) { 
							pflag = true; 
						} else { 
							pflag = false; 
							msg = msg + '- 이니시스 사이트 가입후에 프로필에서 확인할수 있는 플러그인 인증키를 입력해주세요. \n';
						}
						
						if(pflag) {
							return true;
						} else {
							alert("다음 사항을 확인해주세요!\n\n" + msg);
							return false; 
						}
						return false;
					}
	            </script>
	            <div class="inline error">
	            	<p><strong><?php _e( '플러그인 인증 필요', 'codem_inicis' ); ?></strong><br/><?php _e( 'http://wordpressshop.co.kr 사이트에서 인증키를 발급받아 등록하여 주세요.<br/>플러그인을 비활성화 할경우 다시 재인증 받으셔야 합니다.', 'codem_inicis' ); ?></p>
	            	<p>
	            		<form name="frm_activate" id="frm_activate" method="post" action="" enctype="multipart/form-data" onsubmit="return frmcheck()">
		            		<label style="width: 120px;display: inline-block;">회원 이메일 주소</label><input type="text" name="p_email" id="p_email" value=""><br/>
		            		<label style="width: 120px;display: inline-block;">플러그인 인증키</label><input type="text" name="p_activate_key" id="p_activate_key" value=""><br/>
		            		<input type="hidden" name="siteurl" value="<?php echo home_url(); ?>"><br/>
		            		<input type="submit" class="button-primary" value="인증하기">
		            		<?php wp_nonce_field('woocommerce-settings') ?>
	            		</form>
	            	</p>
	            </div>

	        <?php    
			} else {
				if( $this->is_valid_for_use() && $this->check_php_extension_load()) {
			?>
				<?php $this->generate_pg_notice(); ?>
				<table class="form-table">
				<?php $this->generate_settings_html(); ?>
					<tr>
						<th scope="row"><label for="activate-key">플러그인 개인 인증키</label></th>
						<td><span style="font-weight: bold;font-size:16px;"><?php echo get_option('_codem_inicis_activate_key'); ?></span><br/>
							<span class="description">인증상태 : <?php if($this->is_valid_for_key()) { echo "정상"; } else { echo "비인증상태"; } ?></span></td>
					</tr>
				</table>
			<?php							 
				}
				
				if(!$this->check_php_extension_load() ) {
			?>
				<div class="inline error"><p><strong><?php _e( 'PHP 확장 설치 필요', 'codem_inicis' ); ?></strong>: <?php _e( '이니시스 결제에서 사용하는 PHP 확장이 설치가 안되어 있습니다. 이니시스 PG 결제를 하기 위해서는 PHP 확장 설치가 필요합니다. 가이드를 참고해주세요. ', 'codem_inicis' ); ?></p></div>			
			<?php
				}
				
				if( !$this->is_valid_for_use() ) {
			?>	
	            <div class="inline error"><p><strong><?php _e( '해당 결제 방법 비활성화', 'codem_inicis' ); ?></strong>: <?php _e( '이니시스 결제는 KRW, USD 이외의 통화로는 결제가 불가능합니다. 상점의 통화(Currency) 설정을 확인해주세요.', 'codem_inicis' ); ?></p></div>
	        <?php    
				}
				
			}
		}

        /**
         * 이니시스 PG 공지사항 페이지 생성
		 * Generate Inicis PG Notice. 
		 *
         * @return bool
         * @author Alan
         */
		function generate_pg_notice(){
			
			if($_GET['noti_close'] == '1') {
				update_option('inicis_notice_close', '1');
			} else if($_GET['noti_close'] == '0') {
				update_option('inicis_notice_close', '0');
			}	
		
			$css = '';
			if(get_option('inicis_notice_close') == '1') {
				$css = 'display:none;';
				$admin_noti_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_inicis&noti_close=0');
				$admin_noti_txt = "열기";
			}else{
				$css = '';
				$admin_noti_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_inicis&noti_close=1');
				$admin_noti_txt = "닫기";
			}
			
			?>
			<div id="welcome-panel" class="welcome-panel" style="padding-top:15px;">
				<div class="welcome-panel-content">
					<h3 style="font-size:16px;font-weight:bold;margin-bottom: 15px;">공지사항</h3>
					<a class="welcome-panel-close" style="padding-top:15px;" href="<?php echo $admin_noti_url; ?>"><?php echo $admin_noti_txt; ?></a>
			        <div class="tab_contents" style="line-height:16px;<?php echo $css; ?>">
			            <ul>
		    <?php
		        
		        //XML 형태로 넘어오는 Feed 값 가져오기
		        $url = "http://www.wordpressshop.co.kr/category/pg_notice/feed";
				//$url = "http://www.codemshop.com/category/notice/feed";  
		            
		        $curl = curl_init();  
		        curl_setopt($curl, CURLOPT_URL, $url);  
		        curl_setopt($curl, CURLOPT_HEADER, 0);  
		        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);  
		        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);  
		        $xml = curl_exec($curl);  
		        curl_close($curl); 
		        
		        $xmldata = new SimpleXMLElement($xml);
		        
		        //Feed XML 데이터 출력
		        $limit = 5; //가져올 갯수 지정
		        $maxitem = count($xmldata->channel->item);
		        
				if($maxitem <= 0) {
					echo '
					<li style="font-size:12px;">
						<span>아직 공지사항이 없거나 데이터를 가져오지 못했습니다. 페이지를 새로고침 하여 주시기 바랍니다.</span>
					</li>';
				}
				
		        for($i=0;$i<$maxitem;$i++)
		        {
		            if($i < $limit){
		                $item = $xmldata->channel->item[$i];
		                echo '<li style="font-size: 13px;font-weight: bold;">
		                        <span class="label blue"><i class="icon-bullhorn"></i></span>
		                        <span class="text_gray italic">'.date("Y-m-d", strtotime($item->pubDate)).'</span> | 
		                        <a href="'.$item->link.'" target="_blank">'.$item->title.'</a> 
		                      </li>';    
		            }
		        }
		    ?>
			            </ul>
			        </div>
				</div>
			</div>
			<?php	
		}

        /**
         * 이니시스 Key Check
         *
         * @return bool
         * @author Alan
         */
		function is_valid_for_key(){
			$key = get_option('_codem_inicis_activate_key');
			if(empty($key)) {
				return false;
			}		
		    $http_url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLWNoZWNr"); 
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $http_url);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ko"));
		    curl_setopt($ch, CURLOPT_POST, 1);
		    curl_setopt($ch, CURLOPT_POSTFIELDS,  array('action'=>'activate-check', 'activate_key' => $key ) );
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		    
		    $httpResponse = curl_exec($ch);
			if($httpResponse == "1") {
				curl_close($ch);	
				$this->activate_check = true;
				return true;	
			} else {
				curl_close($ch);
				$this->activate_check = false;
				return false;
			}
		}

	    /**
	     * 상점에서 사용하는 통화를 지원하는 지 여부 체크
		 * (기본적으로 이니시스는 달러와 원화만 사용가능합니다)
	     *
	     * @access public
	     * @return bool
	     */
	    function is_valid_for_use() {
	        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'USD', 'KRW' ) ) ) ) return false;
	
	        return true;
	    }
		
		/**
		 * PHP 확장 로드 확인
		 * check php extension load check.
		 * 
		 * @return void
		 * @author Alan 
		 */
		function check_php_extension_load(){
				
			if (!function_exists('xml_set_element_handler')) {
				//libxml
				return false;
			}
			if (!function_exists('openssl_get_publickey')) {
				//openssl
				return false;
			}
			if (!function_exists('socket_create')) {
				//socket
				return false;
			}
			if (!function_exists('mcrypt_cbc')) {
				//mcrypt
				return false;
			}
			if(!is_callable('curl_init')) {
				//curl
				return false;
			}
			
			return true;
		}
		
	
        /**
		 * 체크 여부 확인하여 체크박스 선택여부 리턴
		 * 
		 * @param $arr
		 * @param $str
		 * @return String
		 * @author Alan
		 */
		function check_paymethod_array($arr, $str){
			if (in_array($str, $arr)) {
				return "checked";
			} else {
				return;
			}
		}		

        /**
         * 체크 여부 확인하여 체크박스 선택여부 리턴
         * 
         * @param $arr
         * @param $str
         * @return String
         * @author Alan
         */
        function check_order_status_array($arr, $str, $opt = ''){
            if (in_array($str, $arr)) {
                    
                if(!empty($opt)) {
                    return $opt;
                }     
                return "checked";
            } else {
                return;
            }
        }       

        /**
		 * 배열에 해당 텍스트가 들어 있는지 확인하여 여부 리턴
		 * 
		 * @param Array $arr
		 * @param String $str
		 * @return String
		 * @author Alan
		 */
		function check_use_method_array($arr, $str){
			if (in_array($str, $arr)) {
				return true;
			} else {
				return false;
			}
		}		

        /**
		 * 배열에 해당 텍스트가 들어 있는지 확인하여 여부 리턴
		 * 
		 * @param Array $arr
		 * @param String $str
		 * @return String
		 * @author Alan
		 */
		function check_array($arr, $str, $rtn_str){
			if (in_array($str, $arr)) {
				return $rtn_str;
			} else {
				return false;
			}
		}	
		
        /**
		 * 이니시스 결제수단 지정 타입 추가
		 * (form_fields에 지정된 타입에 대한 정의 추가)
		 * 
		 * @param $key
		 * @param $data
		 * @return String
		 * @author Alan
		 */
        function generate_inicis_gopaymethod_html($key, $data){
	        	
			$default_data = get_option('woocommerce_inicis_gopaymethod');
			$paymethod_arr = explode( ':', $default_data );  //문자열 분리
			
	        return '
		        <tr valign="top">
		            <th scope="row" class="titledesc">
		            	<label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
		            	<img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
	            	</th>
		            <td class="forminp">
		                <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>
		                
			                <input type="checkbox" name="chk_card" id="chk_card" value="Card"'.$this->check_paymethod_array($paymethod_arr, "Card").'>신용카드
			                <input type="checkbox" name="chk_directbank" id="chk_directbank" value="DirectBank"'.$this->check_paymethod_array($paymethod_arr, "DirectBank").'>은행계좌이체
			                
						<p class="description">'.$data['description'].'</p>
						</fieldset>
		            </td>
		        </tr>';
        }

        /**
         * 사용자 마이페이지에서의 환불 처리 타입 추가
         * (form_fields에 지정된 타입에 대한 정의 추가)
         * 
         * @param $key
         * @param $data
         * @return String
         * @author Alan
         */
        function generate_inicis_mypage_refund_html($key, $data){
                
            $default_data = get_option('woocommerce_inicis_mypage_refund');
            $status_method_arr = explode( ':', $default_data );  //문자열 분리

            if(count($status_method_arr) == 1 && empty($status_method_arr[0])) {
                //$status_method_arr = array(__('refunded','codem_inicis'));
            }
            
            $return_msg = '
                <tr valign="top">
                    <th scope="row" class="titledesc">
                    <label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
                    <img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
                    </th>
                    <td class="forminp">
                        <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>';
            
            //get order status
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));
            $reorder = array();

            //reorder
            $reorder = array();
            foreach($shop_order_status as $key => $value) {
                $reorder[$value->slug] = $value->name; 
            }            
            
            //remove array order status
            $remove_arr = array('cancelled','completed','failed','processing','refunded');
            foreach($remove_arr as $val) {
                unset($reorder[$val]);
            }

            foreach ($reorder as $key => $value) {
                $return_msg .= '<input type="checkbox" name="chk_mypage_refund_'.$key.'" id="chk_mypage_refund_'.$key.'" value="'.$key.'" '.$this->check_order_status_array($status_method_arr, $key, 'checked').'>'.__(__($value,'codem_inicis'),'woocommerce').'&nbsp;&nbsp;';
            }
                            
            $return_msg .= '<p class="description">'.$data['description'].'</p>
                        </fieldset>
                    </td>
                </tr>';
                
           return $return_msg;
        }

        /**
         * 관리자 페이지에서의 환불 처리 타입 추가
         * (form_fields에 지정된 타입에 대한 정의 추가)
         * 
         * @param $key
         * @param $data
         * @return String
         * @author Alan
         */
        function generate_inicis_admin_refund_html($key, $data){
                
            $default_data = get_option('woocommerce_inicis_admin_refund');
            $status_method_arr = explode( ':', $default_data );  //문자열 분리

            if(count($status_method_arr) == 1 && empty($status_method_arr[0])) {
                //$status_method_arr = array(__('refunded','codem_inicis'));
            }
                        
            $return_msg = '
                <tr valign="top">
                    <th scope="row" class="titledesc">
	                    <label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
	                    <img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
                    </th>
                    <td class="forminp">
                        <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>';
            
            //get order status
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));

            //reorder
            $reorder = array();
            foreach($shop_order_status as $key => $value) {
                $reorder[$value->slug] = $value->name; 
            }            
            
            //remove array order status
            $remove_arr = array('cancelled','completed','failed');
            foreach($remove_arr as $val) {
                unset($reorder[$val]);
            }

            foreach ($reorder as $key => $value) {
                $return_msg .= '<input type="checkbox" name="chk_admin_refund_'.$key.'" id="chk_admin_refund_'.$key.'" value="'.$key.'" '.$this->check_order_status_array($status_method_arr, $key, 'checked').'>'.__(__($value,'codem_inicis'),'woocommerce').'&nbsp;&nbsp;';
            }

            $return_msg .= '<p class="description">'.$data['description'].'</p>
                        </fieldset>
                    </td>
                </tr>';
                
           return $return_msg;
        }

        /**
         * 결제 완료시 이동할 주문 상태 지정 타입 추가
         * (form_fields에 지정된 타입에 대한 정의 추가)
         * 
         * @param $key
         * @param $data
         * @return String
         * @author Alan
         */
        function generate_inicis_redirect_order_status_html($key, $data){
                
            $default_data = get_option('woocommerce_inicis_redirect_order_status',__('processing','codem_inicis'));
            $status_method_arr = array($default_data);
            
            $return_msg = '
                <tr valign="top">
                    <th scope="row" class="titledesc">
                    	<label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
                    	<img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
                    </th>
                    <td class="forminp">
                        <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>';
            
            //get order status
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));
            
            //reorder
            $reorder = array();
            foreach($shop_order_status as $key => $value) {
                $reorder[$value->slug] = $value->name; 
            }            
            
            //remove array order status
            $remove_arr = array('cancelled','completed','failed','on-hold','refunded');
            foreach($remove_arr as $val) {
                unset($reorder[$val]);
            }
            
            $return_msg .= '<select class="select" name="select_complete_order_status">';
            foreach ($reorder as $key => $value) {
                $return_msg .= '<option value="'.$key.'"'.$this->check_order_status_array($status_method_arr, $key, 'selected').'>'.__(__($value,'codem_inicis'),'woocommerce').'</option>';
            }
            $return_msg .= '</select>';
                            
            $return_msg .= '<p class="description">'.$data['description'].'</p>
                        </fieldset>
                    </td>
                </tr>';
                
           return $return_msg;
        }

        /**
         * 환불 완료시 이동할 주문 상태 지정 타입 추가
         * (form_fields에 지정된 타입에 대한 정의 추가)
         * 
         * @param $key
         * @param $data
         * @return String
         * @author Alan
         */                
        function generate_inicis_redirect_order_status_refunded_html($key, $data){
                
            $default_data = get_option('woocommerce_inicis_redirect_order_status_refunded',__('refunded','codem_inicis'));
            $status_method_arr = array($default_data);
            
            $return_msg = '
                <tr valign="top">
                    <th scope="row" class="titledesc">
                    	<label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
                    	<img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
                	</th>
                    <td class="forminp">
                        <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>';
            
            //get order status
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));

            //reorder
            $reorder = array();
            foreach($shop_order_status as $key => $value) {
                $reorder[$value->slug] = $value->name; 
            }            
            
            //remove array order status
            $remove_arr = array('cancelled','completed','failed','on-hold','pending','processing');
            foreach($remove_arr as $val) {
                unset($reorder[$val]);
            }
            
            $return_msg .= '<select class="select" name="select_complete_order_status_refunded">';
            foreach ($reorder as $key => $value) {
                $return_msg .= '<option value="'.$key.'"'.$this->check_order_status_array($status_method_arr, $key, 'selected').'>'.__(__($value,'codem_inicis'),'woocommerce').'</option>';
            }
            $return_msg .= '</select>';
                            
            $return_msg .= '<p class="description">'.$data['description'].'</p>
                        </fieldset>
                    </td>
                </tr>';
                
           return $return_msg;
        }                

        /**
		 * 이니시스 결제수단 지정 타입 추가
		 * (form_fields에 지정된 타입에 대한 정의 추가)
		 * 
		 * @param $key
		 * @param $data
		 * @return String
		 * @author Alan
		 */
        function generate_inicis_gopaymethod_mobile_html($key, $data){
	        	
			$default_data = get_option('woocommerce_inicis_gopaymethod_mobile');
			$paymethod_arr = explode( ':', $default_data );  //문자열 분리
			
	        return '
		        <tr valign="top">
		            <th scope="row" class="titledesc">
		            	<label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
		            	<img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
	            	</th>
		            <td class="forminp">
		                <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>
		                
			                <input type="checkbox" name="chk_mobile_card" id="chk_mobile_card" value="wcard"'.$this->check_paymethod_array($paymethod_arr, "wcard").'>신용카드(안심클릭)
			                
						<p class="description">'.$data['description'].'</p>
						</fieldset>
		            </td>
		        </tr>';
        }
		
        /**
		 * PG 관련 기타 옵션 타입
		 * 
		 * @return void
		 * @author Alan
		 */ 
		function generate_inicis_acceptmethod_html($key, $data) {
	        	
			$str_setting = get_option('woocommerce_inicis_acceptmethod');
			$arr_setting = explode( ':', $str_setting );  //문자열 분리
			
			$tmp_skin = "";	//임시 변수
			//배열에 해당 배열안의 내용이 들어 있는지 확인(스킨 옵션용) 
			if( $this->check_array_arr(array('SKIN(BLUE)','SKIN(GREEN)','SKIN(PURPLE)','SKIN(RED)','SKIN(YELLOW)'),$arr_setting) ) {
				$tmp_skin = 'checked';
			}
							
	        return '
		        <tr valign="top">
		            <th scope="row" class="titledesc">
		            	<label for="woocommerce_inicis_merchant_id">'.$data['title'].'</label>
		            	<img class="help_tip" data-tip="' . esc_attr( $data['desc'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />
	            	</th>
		            <td class="forminp">
		                <fieldset><legend class="screen-reader-text"><span>'.$data['title'].'</span></legend>
		                
						<input type="checkbox" name="chk_accept_cardpoint" id="chk_accept_cardpoint" value="yes" '.$this->check_array($arr_setting, 'CardPoint', 'checked').'>카드 포인트 사용 허용
						<input type="checkbox" name="chk_accept_skin" id="chk_accept_skin" value="yes" '.$tmp_skin.'>PG사 스킨색상 (미선택시 기본색상 파란색)
						<select name="chk_accept_skin_value" id="chk_accept_skin_value">
							<option value="SKIN(BLUE)" '.$this->check_array($arr_setting, 'SKIN(BLUE)', 'selected').'>파란색</option>
							<option value="SKIN(GREEN)" '.$this->check_array($arr_setting, 'SKIN(GREEN)', 'selected').'>녹색</option>
							<option value="SKIN(PURPLE)" '.$this->check_array($arr_setting, 'SKIN(PURPLE)', 'selected').'>보라색</option>
							<option value="SKIN(RED)" '.$this->check_array($arr_setting, 'SKIN(RED)', 'selected').'>빨강색</option>
							<option value="SKIN(YELLOW)" '.$this->check_array($arr_setting, 'SKIN(YELLOW)', 'selected').'>노랑색</option>
						</select>
							                
						<p class="description">'.$data['description'].'</p>
						</fieldset>
		            </td>
		        </tr>';
		}
		
        /**
         * 배열에 해당 값이 있는지 확인처리 함수
         * Check Array has item.
         * 
         * @return void
         * @author Alan
         */         
		function check_array_arr($arr, $str){
			for($i=0;$i<count($arr);$i++){
				if( in_array($arr[$i], $str) ) {
					return true;
				}
			}
			return false;
		}
		
        /**
		 * 결제 취소 처리
		 * (정상 경로로 WC Api를 타고 들어온 경우 처리)
		 * 
		 * @return void
		 * @author Alan
		 */        
        function successful_request_cancelled( $posted ) {
			global $woocommerce;
			 /**************************
		     * 1. 라이브러리 인클루드 *
		     **************************/
		    require($this->settings['libfolder']."/libs/INILib.php");
		    
		    /***************************************
		     * 2. INIpay41 클래스의 인스턴스 생성 *
		     ***************************************/
		    $inipay = new INIpay50();
		    
		    /*********************
		     * 3. 취소 정보 설정 *
		     *********************/
		    $inipay->SetField("inipayhome", $_REQUEST['home']); // 이니페이 홈디렉터리(상점수정 필요)
		    $inipay->SetField("type", "cancel");                            // 고정 (절대 수정 불가)
		    $inipay->SetField("debug", "true");                             // 로그모드("true"로 설정하면 상세로그가 생성됨.)
		    $inipay->SetField("mid", $_REQUEST['mid']);                                 // 상점아이디
		    $inipay->SetField("admin", "1111");                            
		    $inipay->SetField("tid", $_REQUEST['tid']);                                 // 취소할 거래의 거래아이디
		    $inipay->SetField("cancelmsg", $_REQUEST['msg']);                           // 취소사유
		
		    if($code != ""){
		        $inipay->SetField("cancelcode", $_REQUEST['code']);         //취소사유코드
		    }

			$inipay->startAction();
		    
			if($inipay->getResult('ResultCode') == "00"){
				echo "success";
				exit();
			}else{
                echo $inipay->getResult('ResultMsg');
				exit();
			}
		}
		
        /**
		 * PG 결제 지불 처리(PC 결제)
		 * (정상 경로로 WC Api를 타고 들어온 경우 처리)
		 * 
		 * @return void
		 * @author Alan
		 */        
        function successful_request_pc( $posted ) {
            global $woocommerce;
			
			$order_id_time = $_REQUEST['txnid'];
        	$order_id = explode('_', $_REQUEST['txnid']);
            $order_id = (int)$order_id[0];
            $order = new WC_Order($order_id);
            
            $ini_mid = get_post_meta($order->id, "ini_mid", true);
            $ini_admin = get_post_meta($order->id, "ini_admin", true);
            $ini_price = get_post_meta($order->id, "ini_price", true);
            $ini_rn = get_post_meta($order->id, "ini_rn", true);
            $ini_enctype = get_post_meta($order->id, "ini_enctype", true);    

            /*************** [ 이니시스 결제 라이브러리 관련 코드 시작 ] ***************/
            require($this->settings['libfolder']."/libs/INILib.php");
            
            $inipay = new INIpay50();
            $inipay->SetField("inipayhome", $this->settings['libfolder']); 	//이니페이 홈디렉터리(상점수정 필요)
            $inipay->SetField("type", "securepay");                        					//고정 (절대 수정 불가)
            $inipay->SetField("pgid", "INIphp".$pgid);                      				//고정 (절대 수정 불가)
            $inipay->SetField("subpgip","203.238.3.10");                    				//고정 (절대 수정 불가)
            $inipay->SetField("admin", $ini_admin);    										//키패스워드(상점아이디에 따라 변경)
            $inipay->SetField("debug", "true");                             				//로그모드("true"로 설정하면 상세로그가 생성됨.)
            $inipay->SetField("uid", $uid);                                 				//INIpay User ID (절대 수정 불가)
            $inipay->SetField("goodname", iconv("UTF-8", "EUC-KR", $goodname));             //상품명 
            $inipay->SetField("currency", $currency);                       				//화폐단위

    		$inipay->SetField("mid", $ini_mid);        										//상점아이디
            $inipay->SetField("rn", $ini_rn);          										//웹페이지 위변조용 RN값
            $inipay->SetField("price", $ini_price);     									//가격
            $inipay->SetField("enctype", $ini_enctype);										//고정 (절대 수정 불가)

            $inipay->SetField("buyername", iconv("UTF-8", "EUC-KR", $buyername));       	//구매자 명
            $inipay->SetField("buyertel",  $buyertel);       								//구매자 연락처(휴대폰 번호 또는 유선전화번호)
            $inipay->SetField("buyeremail",$buyeremail);      								//구매자 이메일 주소
            $inipay->SetField("paymethod", $paymethod);       								//지불방법 (절대 수정 불가)
            $inipay->SetField("encrypted", $encrypted);       								//암호문
            $inipay->SetField("sessionkey",$sessionkey);      								//암호문
            $inipay->SetField("url", home_url()); 							//실제 서비스되는 상점 SITE URL로 변경할것
            $inipay->SetField("cardcode", $cardcode);         								//카드코드 리턴
            $inipay->SetField("parentemail", $parentemail);   								//보호자 이메일 주소(핸드폰 , 전화결제시에 14세 미만의 고객이 결제하면  부모 이메일로 결제 내용통보 의무, 다른결제 수단 사용시에 삭제 가능)
            $inipay->SetField("recvname",$recvname);    									//수취인 명
            $inipay->SetField("recvtel",$recvtel);      									//수취인 연락처
            $inipay->SetField("recvaddr",$recvaddr);    									//수취인 주소
            $inipay->SetField("recvpostnum",$recvpostnum);  								//수취인 우편번호
            $inipay->SetField("recvmsg",$recvmsg);      									//전달 메세지
            $inipay->SetField("joincard",$joincard);  										//제휴카드코드
            $inipay->SetField("joinexpire",$joinexpire);    								//제휴카드유효기간
            $inipay->SetField("id_customer",$id_customer);    								//user_id

			$inipay->startAction();   //지불 처리
            /*************** [ 이니시스 결제 라이브러리 관련 코드 끝 ] ***************/
            
            if(isset($_REQUEST['txnid']))
            {
                $order_id_time = $_REQUEST['txnid'];
                $order_id = explode('_', $_REQUEST['txnid']);
                $order_id = (int)$order_id[0];

                if($order_id != '')
                {
                    try
                    {
                        $order = new WC_Order($order_id);
                        $merchant_id = $_REQUEST['key'];
                        $amount = $_REQUEST['Amount'];
                        $hash = $_REQUEST['hash'];

                        if($inipay->GetResult('ResultCode') == "00")  //결과코드에 따른 상태 지정 처리
                        {  
                            $status = "success";
                        }
                        else
                        {
                            $status = "error";
                        }

                        $data = $order->get_items();
                        $product_name = "";
                        foreach($data as $item) {
                            $product_name = $item['name'];
                        }
                        $productinfo = $product_name;
                        
                        //위변조 방지 검증용 해시 생성 & 비교
                        $checkhash = hash('sha512', "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||");
                        $transauthorised = false;
                        
                        if($order -> status !=='completed'){  //완료상태가 아닌 경우
                            if($hash == $checkhash)  //해시값 비교
                            {
                              $status = strtolower($status);  //소문자로 변경
                                if($status=="success"){  //성공 상태
                                    $transauthorised = true; //트랜잭션 상태값
                                    $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                    $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                    
                                    if($order -> status == 'processing'){  //프로세싱중인 경우
                                    }else{ //프로세싱 중이 아닌 경우 처리
                                        
                                        $order -> payment_complete();   //주문 지불 완료 처리
	                                                                                
                                        //구매 완료처리시 결제방법에 대한 기록 남김
                                        add_post_meta($order_id, "inicis_paymethod", $paymethod);  //카드결제시 카드결제로 기록
                                        add_post_meta($order_id, "inicis_paymethod_tid", $inipay->GetResult('TID'));  //결제시 사용된 TID 값 기록
                                        add_post_meta($order_id, "inicis_paymethod_mid", $ini_mid);  //결제시 사용된 MID 값 기록
                                                                                
                                        //주문노트 작성, TID는 이니시스에서 생성하는 주문번호, MOID는 몰에서 생성한 주문번호
                                        $order -> add_order_note( sprintf(__('주문이 완료되었습니다. 결제방법 : %s, 이니시스 거래번호(TID) : <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>, 몰 고유 주문번호 : %s', 'codem_inicis'), $paymethod,$inipay->GetResult('TID'), $inipay->GetResult('MOID') ) );  
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                        $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                    }
                                }
                                //펜딩상태 처리 주석
                                /*else if($status=="pending"){   //펜딩 상태
                                }*/
                                else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                    $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                    $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); 
                                    $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과 메시지 : %s, 에러코드 : %s', 'codem_inicis'),$paymethod,$inipay->GetResult('ResultMsg'),$inipay->GetResult('ResultCode') ) ); //결제 거절 메시지 노트 추가
                                    //경우에 따라서 사용자에게 이메일을 보내거나 관리자에게 알림을 남길수 있도록 이곳에 추가 처리
                                }
                            }else{   //해쉬값이 안맞을때 (보안문제)
                                $this -> msg['class'] = 'error';   //메시지 클래스 지정
                                $this -> msg['message'] = __('보안에러, 허용되지 않은 접근이 확인되었습니다! 다시 시도해 주세요!', 'codem_inicis');  //에러메시지
                            }
                            
                            if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                $order -> update_status('failed');  //실패상태로 업데이트
                                $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과 메시지 : %s, 에러코드 : %s', 'codem_inicis'),$paymethod,$inipay->GetResult('ResultMsg'), $inipay->GetResult('ResultCode') ) ); //결제 거절 메시지 노트 추가
                                $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                            }
                            add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                        }
                    }
                    catch(Exception $e)
                    {
                            $msg = "Error";
                    }
                }
            }   
        }

        /**
		 * PG 결제 지불 처리(모바일 결제)
		 * (p_next_url로 들어온 경우 처리)
		 * 
		 * @return void
		 * @author Alan
		 */        
        function successful_request_mobile_next( $posted ) {
            global $woocommerce;
			
			/*************** [ 이니시스 결제 라이브러리 관련 코드 시작 ] ***************/
            require($this->settings['libfolder']."/libs/INImx.php");
            $inimx = new INImx();
            $inimx->reqtype         	= "PAY";  //결제요청방식
            $inimx->inipayhome  		= $this->settings['libfolder']; //로그기록 경로 (이 위치의 하위폴더에 log폴더 생성 후 log폴더에 대해 777 권한 설정)
            $inimx->id_merchant 		= substr($P_TID,'10','10');  //TID에서 상점 아이디만 가져옴(?)
            $inimx->status         		= $P_STATUS;
            $inimx->rmesg1          	= $P_RMESG1;
            $inimx->tid     			= $P_TID;
            $inimx->req_url     		= $P_REQ_URL;
            $inimx->noti        		= $P_NOTI;
    
            if($inimx->status =="00")   // 모바일 인증이 성공시
            {
                $inimx->startAction();  // 승인요청
                $inimx->getResult();  //승인결과 파싱
                switch($inimx->m_payMethod)
                {   
                    case "CARD":  //신용카드 안심클릭
						//여기서 부터는 자체 처리 기능
						$transauthorised = false;
                        if($inimx->m_moid != "")
                        {
                            $order_id_time = $inimx->m_moid;
                            $order_id = explode('_', $inimx->m_moid);
                            $order_id = (int)$order_id[0];

                            if($order_id != '')
                            {
                                try
                                {
                                    //주문번호를 가져와서 주문정보를 가져옴
                                    $order = new WC_Order($order_id);
                                    
                                    $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                    $product_name = ""; 
                                    
                                    foreach($data as $item) {
                                        $product_name = $item['name'];
                                    }
                                    $productinfo = $product_name;
                                    if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                        $transauthorised = true; //트랜잭션 상태값
                                        $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                        $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                        
                                        $order -> payment_complete();   //주문 지불 완료 처리
                                                                                
                                        //구매 완료처리시 결제방법에 대한 기록 남김
                                        add_post_meta($order_id, "inicis_paymethod", $inimx->m_payMethod);  //카드결제시 카드결제로 기록
                                        add_post_meta($order_id, "inicis_paymethod_tid", $inimx->m_tid);  //결제시 사용된 TID 값 기록
                                        add_post_meta($order_id, "inicis_paymethod_mid", $inimx->m_mid);  //결제시 사용된 MID 값 기록
                                        
                                        $order -> add_order_note( sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis' ), $inimx->m_payMethod, $inimx->m_tid) );
                                        $order -> add_order_note( sprintf( __("몰 고유 주문번호 : %s", 'codem_inicis'), $inimx->m_moid ) );
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                        $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                        //지불완료
                                    }
                                    //펜딩상태 처리 주석
                                    /*else if($status=="pending"){   //펜딩 상태
                                    }*/
                                    else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                        $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                        $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note(sprintf( __('결제방법 : %s, 주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $inimx->m_payMethod, $inimx->m_resultMsg, $inimx->m_resultCode ) ); //결제 거절 메시지 노트 추가
                                    }
                                    
                                    if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                        $order -> update_status('failed');  //실패상태로 업데이트
                                        $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                        $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $inimx->m_payMethod, $inimx->m_resultMsg, $inimx->m_resultCode) ); //결제 거절 메시지 노트 추가
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                    }
                                    add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                }
                            
                                catch(Exception $e)
                                {
                                        $msg = "Error";
                                }
                            }
                        }
                        break;
                    case "MOBILE":  //휴대폰결제
                        if($inimx->m_moid != "")
                        {
                            $order_id_time = $inimx->m_moid;
                            $order_id = explode('_', $inimx->m_moid);
                            $order_id = (int)$order_id[0];

                            if($order_id != '')
                            {
                                try
                                {
                                    //주문번호를 가져와서 주문정보를 가져옴
                                    $order = new WC_Order($order_id);
                                    
                                    $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                    $product_name = ""; 
                                    
                                    foreach($data as $item) {
                                        $product_name = $item['name'];
                                    }
                                    
                                    $productinfo = $product_name;
                                    
                                    if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                        $transauthorised = true; //트랜잭션 상태값
                                        $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                        $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                        
                                        $order -> payment_complete();   //주문 지불 완료 처리

                                        //구매 완료처리시 결제방법에 대한 기록 남김
                                        add_post_meta($order_id, "inicis_paymethod", $inimx->m_payMethod);  //카드결제시 카드결제로 기록
                                        add_post_meta($order_id, "inicis_paymethod_tid", $inimx->m_tid);  //결제시 사용된 TID 값 기록
                                        add_post_meta($order_id, "inicis_paymethod_mid", $inimx->m_mid);  //결제시 사용된 MID 값 기록
                                        
                                        //$order -> add_order_note("이니시스 모바일 카드결제로 결제처리 되었습니다.");
                                        $order -> add_order_note( sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $inimx->m_payMethod, $inimx->m_tid ) );
                                        $order -> add_order_note( sprintf( __("몰 고유 주문번호 : %s", 'codem_inicis'), $inimx->m_moid ) );
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                        $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                        //지불완료
                                    }
                                    //펜딩상태 처리 주석
                                    /*else if($status=="pending"){   //펜딩 상태
                                    }*/
                                    else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                        $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                        $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $inimx->m_payMethod, $inimx->m_resultMsg, $inimx->m_resultCode ) ); //결제 거절 메시지 노트 추가
                                    }
                                    
                                    if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                        $order -> update_status('failed');  //실패상태로 업데이트
                                        $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                        $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('결제방법 : '.$inimx->m_payMethod.' 주문처리 결과메시지 : '.$inimx->m_resultMsg.', 에러코드 : '.$inimx->m_resultCode,'codem_inicis' )) ); //결제 거절 메시지 노트 추가
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                    }
                                    add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                }
                            
                                catch(Exception $e)
                                {
                                        $msg = "Error";
                                }
                            }
                        }
                        break;
                    case "VBANK":  //가상계좌
                        if($inimx->m_moid != "")
                        {
                            $order_id_time = $inimx->m_moid;
                            $order_id = explode('_', $inimx->m_moid);
                            $order_id = (int)$order_id[0];

                            if($order_id != '')
                            {
                                try
                                {
                                    //주문번호를 가져와서 주문정보를 가져옴
                                    $order = new WC_Order($order_id);
                                    
                                    $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                    $product_name = ""; 
                                    
                                    foreach($data as $item) {
                                        $product_name = $item['name'];
                                    }
                                    
                                    $productinfo = $product_name;
                                    
                                    if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                        $transauthorised = true; //트랜잭션 상태값
                                        $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                        $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                        
                                        $order -> payment_complete();   //주문 지불 완료 처리
	                                                                                
                                        //구매 완료처리시 결제방법에 대한 기록 남김
                                        add_post_meta($order_id, "inicis_paymethod", $inimx->m_payMethod);  //카드결제시 카드결제로 기록
                                        add_post_meta($order_id, "inicis_paymethod_tid", $inimx->m_tid);  //결제시 사용된 TID 값 기록
                                        add_post_meta($order_id, "inicis_paymethod_mid", $inimx->m_mid);  //결제시 사용된 MID 값 기록
                                        
                                        //$order -> add_order_note("이니시스 모바일 가상계좌로 결제처리 되었습니다.");
                                        $order -> add_order_note( sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $inimx->m_payMethod, $inimx->m_tid ) );
                                        $order -> add_order_note( sprintf( __('몰 고유 주문번호 : %s', 'codem_inicis'), $inimx->m_moid));
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                        $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                        //지불완료
                                    }
                                    //펜딩상태 처리 주석
                                    /*else if($status=="pending"){   //펜딩 상태
                                    }*/
                                    else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                        $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                        $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $inimx->m_payMethod, $inimx->m_resultMsg, $inimx->m_resultCode) ); //결제 거절 메시지 노트 추가
                                    }
                                    
                                    if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                        $order -> update_status('failed');  //실패상태로 업데이트
                                        $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                        $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('결제방법 : %s, 주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $inimx->m_payMethod, $inimx->m_resultMsg, $inimx->m_resultCode) ); //결제 거절 메시지 노트 추가
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                    }
                                    add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                }
                            
                                catch(Exception $e)
                                {
                                        $msg = "Error";
                                }
                            }
                        }
                        break;
                    default: //문화상품권,해피머니
                        if($inimx->m_moid != "")
                        {
                            $order_id_time = $inimx->m_moid;
                            $order_id = explode('_', $inimx->m_moid);
                            $order_id = (int)$order_id[0];

                            if($order_id != '')
                            {
                                try
                                {
                                    //주문번호를 가져와서 주문정보를 가져옴
                                    $order = new WC_Order($order_id);
                                    
                                    $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                    $product_name = ""; 
                                    
                                    foreach($data as $item) {
                                        $product_name = $item['name'];
                                    }
                                    
                                    $productinfo = $product_name;
                                    
                                    if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                        $transauthorised = true; //트랜잭션 상태값
                                        $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                        $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                        
                                        $order -> payment_complete();   //주문 지불 완료 처리
	                                                                                
                                        //구매 완료처리시 결제방법에 대한 기록 남김
                                        add_post_meta($order_id, "inicis_paymethod", $inimx->m_payMethod);  //카드결제시 카드결제로 기록
                                        add_post_meta($order_id, "inicis_paymethod_tid", $inimx->m_tid);  //결제시 사용된 TID 값 기록
                                        add_post_meta($order_id, "inicis_paymethod_mid", $inimx->m_mid);  //결제시 사용된 MID 값 기록
                                        
                                        $order -> add_order_note( __('이니시스 모바일 문화상품권,해피머니 등 수단으로 결제처리 되었습니다.', 'codem_inicis') );
                                        $order -> add_order_note( sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $inimx->m_payMethod, $inimx->m_tid ) );
                                        $order -> add_order_note( sprintf( __('몰 고유 주문번호 : %s', 'codem_inicis'), $inimx->m_moid));
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                        $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                        //지불완료
                                    }
                                    //펜딩상태 처리 주석
                                    /*else if($status=="pending"){   //펜딩 상태
                                    }*/
                                    else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                        $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                        $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s','codem_inicis'), $inimx->m_resultMsg, $inimx->m_resultCode));
                                    }
                                    
                                    if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                        $order -> update_status('failed');  //실패상태로 업데이트
                                        $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                        $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                        $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s','codem_inicis'), $inimx->m_resultMsg, $inimx->m_resultCode));
                                        $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                    }
                                    add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                }
                            
                                catch(Exception $e)
                                {
                                        $msg = "Error";
                                }
                            }
						}
                }
            }
        }
        
        /**
		 * PG 결제 지불 처리(모바일 결제)
		 * (p_noti_url로 들어온 경우 처리)
		 * 
		 * @return void
		 * @author Alan
		 */                
        function successful_request_mobile_noti( $posted ) {
            global $woocommerce;
            
			$PGIP = $_SERVER['REMOTE_ADDR'];
            if($PGIP == "211.219.96.165" || $PGIP == "118.129.210.25")    //PG에서 보냈는지 IP로 체크
            {
                $P_TID;					// 거래번호
                $P_MID;                	// 상점아이디
                $P_AUTH_DT;            	// 승인일자
                $P_STATUS;            	// 거래상태 (00:성공, 01:실패)
                $P_TYPE;            	// 지불수단
                $P_OID;                	// 상점주문번호
                $P_FN_CD1;            	// 금융사코드1
                $P_FN_CD2;            	// 금융사코드2
                $P_FN_NM;            	// 금융사명 (은행명, 카드사명, 이통사명)
                $P_AMT;                	// 거래금액
                $P_UNAME;            	// 결제고객성명
                $P_RMESG1;            	// 결과코드
                $P_RMESG2;            	// 결과메시지
                $P_NOTI;            	// 노티메시지(상점에서 올린 메시지)
                $P_AUTH_NO;            	// 승인번호

                $P_TID = $_REQUEST['P_TID'];
                $P_MID = $_REQUEST['P_MID'];
                $P_AUTH_DT = $_REQUEST['P_AUTH_DT'];
                $P_STATUS = $_REQUEST['P_STATUS'];
                $P_TYPE = $_REQUEST['P_TYPE'];
                $P_OID = $_REQUEST['P_OID'];
                $P_FN_CD1 = $_REQUEST['P_FN_CD1'];
                $P_FN_CD2 = $_REQUEST['P_FN_CD2'];
                $P_FN_NM = $_REQUEST['P_FN_NM'];
                $P_AMT = $_REQUEST['P_AMT'];
                $P_UNAME = $_REQUEST['P_UNAME'];
                $P_RMESG1 = $_REQUEST['P_RMESG1'];
                $P_RMESG2 = $_REQUEST['P_RMESG2'];
                $P_NOTI = $_REQUEST['P_NOTI'];
                $P_AUTH_NO = $_REQUEST['P_AUTH_NO'];

                //WEB 방식의 경우 가상계좌 채번 결과 무시 처리 (APP 방식의 경우 해당 내용을 삭제 또는 주석 처리 하시기 바랍니다.)
                if($P_TYPE == "VBANK")    //결제수단이 가상계좌이며
                {
					if($P_STATUS != "02") //입금통보 "02" 가 아니면(가상계좌 채번 : 00 또는 01 경우)
					{
					    echo "OK";
					    return;
					}
                }

                $PageCall_time = date("H:i:s");

                $value = array(
                        "PageCall time" 	=> $PageCall_time,
                        "P_TID"            	=> $P_TID,  
                        "P_MID"     		=> $P_MID,  
                        "P_AUTH_DT" 		=> $P_AUTH_DT,      
                        "P_STATUS"  		=> $P_STATUS,
                        "P_TYPE"    		=> $P_TYPE,     
                        "P_OID"     		=> $P_OID,  
                        "P_FN_CD1"  		=> $P_FN_CD1,
                        "P_FN_CD2"  		=> $P_FN_CD2,
                        "P_FN_NM"   		=> $P_FN_NM,  
                        "P_AMT"     		=> $P_AMT,  
                        "P_UNAME"   		=> $P_UNAME,  
                        "P_RMESG1"  		=> $P_RMESG1,  
                        "P_RMESG2"  		=> $P_RMESG2,
                        "P_NOTI"    		=> $P_NOTI,  
                        "P_AUTH_NO" 		=> $P_AUTH_NO );

                $flag_rst = "FAIL";
                //우커머스 지불관련 처리 시작
                switch($P_TYPE){
                	
                    case "CARD":  //카드결제
                        if(!strcmp($P_STATUS,"00")) {
                            //결제 성공시
                            if(isset($P_OID))
                            {
                                $order_id_time = $P_OID;
                                $order_id = explode('_', $P_OID);
                                $order_id = (int)$order_id[0];

                                if($order_id != '')
                                {
                                    try
                                    {
                                        //주문번호를 가져와서 주문정보를 가져옴
                                        $order = new WC_Order($order_id);
                                        
                                        $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                        $product_name = ""; 
                                        
                                        foreach($data as $item) {
                                            $product_name = $item['name'];
                                        }
                                        
                                        $productinfo = $product_name;
                                        
                                        $transauthorised = false;
                                        if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                            
                                            $transauthorised = true; //트랜잭션 상태값
                                            $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                            $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
	                                            
	                                        $order -> payment_complete();   //주문 지불 완료 처리

                                            //구매 완료처리시 결제방법에 대한 기록 남김
                                            add_post_meta($order_id, "inicis_paymethod", $P_TYPE);  //결제방법 기록
                                            add_post_meta($order_id, "inicis_paymethod_tid", $P_TID);  //결제시 사용된 TID 값 기록
                                            add_post_meta($order_id, "inicis_paymethod_mid", $P_MID);  //결제시 사용된 MID 값 기록
                                                                                        
                                            $order -> add_order_note( __('이니시스 모바일 카드결제로 결제처리 되었습니다.', 'codem_inicis') );
                                            $order -> add_order_note( sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $P_TYPE, $P_TID ) );
                                            $order -> add_order_note( sprintf( __("몰 고유 주문번호 : %s", 'codem_inicis'), $P_OID ));
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                            $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                            //지불완료
                                        
                                        //펜딩상태 처리 주석
                                        /*else if($status=="pending"){   //펜딩 상태
                                        }*/
                                        }else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                            $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                            $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                            $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                        }
                                        
                                        if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                            $order -> update_status('failed');  //실패상태로 업데이트
                                            $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                            $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                            $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                        }
                                        add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                    }
                                    catch(Exception $e)
                                    {
                                            $msg = "Error";
                                    }
                                }
                            }
                            
                            $flag_rst = "OK";
                        } else {
                            //결제 실패시
                        }
                        break;
                    case "BANK":  //계좌이체
                        if(!strcmp($P_STATUS,"00")) {
                            //결제 성공시
                            if(isset($P_OID))
                            {
                                $order_id_time = $P_OID;
                                $order_id = explode('_', $P_OID);
                                $order_id = (int)$order_id[0];

                                if($order_id != '')
                                {
                                    try
                                    {
                                        //주문번호를 가져와서 주문정보를 가져옴
                                        $order = new WC_Order($order_id);
                                        
                                        $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                        $product_name = ""; 
                                        
                                        foreach($data as $item) {
                                            $product_name = $item['name'];
                                        }
                                        
                                        $productinfo = $product_name;
                                        
                                        $transauthorised = false;
                                        if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                            
                                            $transauthorised = true; //트랜잭션 상태값
                                            $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                            $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                            
	                                        $order -> payment_complete();   //주문 지불 완료 처리
		                                                                                
                                            //구매 완료처리시 결제방법에 대한 기록 남김
                                            add_post_meta($order_id, "inicis_paymethod", $P_TYPE);  //결제방법 기록
                                            add_post_meta($order_id, "inicis_paymethod_tid", $P_TID);  //결제시 사용된 TID 값 기록
                                            add_post_meta($order_id, "inicis_paymethod_mid", $P_MID);  //결제시 사용된 MID 값 기록
                                            
                                            $order -> add_order_note(__('이니시스 모바일 계좌이체로 결제처리 되었습니다.', 'codem_inicis'));
                                            $order -> add_order_note(sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $P_TYPE, $P_TID));
                                            $order -> add_order_note(sprintf(__("몰 고유 주문번호 : %s", 'codem_inicis'), $P_OID));
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                            $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                            //지불완료
                                        
                                        //펜딩상태 처리 주석
                                        /*else if($status=="pending"){   //펜딩 상태
                                        }*/
                                        }else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                            $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                            $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                            $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                        }
                                        
                                        if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                            $order -> update_status('failed');  //실패상태로 업데이트
                                            $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                            $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
           									$order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                        }
                                        add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                    }
                                    catch(Exception $e)
                                    {
                                            $msg = "Error";
                                    }
                                }
                            }
                            $flag_rst = "OK";
                        } else {
                            //결제 실패시
                        }
                        break;
                    case "ISP":  //ISP
                        if(!strcmp($P_STATUS,"00")) {
                            //결제 성공시
                            if(isset($P_OID))
                            {
                                $order_id_time = $P_OID;
                                $order_id = explode('_', $P_OID);
                                $order_id = (int)$order_id[0];

                                if($order_id != '')
                                {
                                    try
                                    {
                                        //주문번호를 가져와서 주문정보를 가져옴
                                        $order = new WC_Order($order_id);
                                        
                                        $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                        $product_name = ""; 
                                        
                                        foreach($data as $item) {
                                            $product_name = $item['name'];
                                        }
                                        
                                        $productinfo = $product_name;
                                        
                                        $transauthorised = false;
                                        if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                            
                                            $transauthorised = true; //트랜잭션 상태값
                                            $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                            $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                            
	                                        $order -> payment_complete();   //주문 지불 완료 처리
		                                                                                
                                            //구매 완료처리시 결제방법에 대한 기록 남김
                                            add_post_meta($order_id, "inicis_paymethod", $P_TYPE);  //결제방법 기록
                                            add_post_meta($order_id, "inicis_paymethod_tid", $P_TID);  //결제시 사용된 TID 값 기록
                                            add_post_meta($order_id, "inicis_paymethod_mid", $P_MID);  //결제시 사용된 MID 값 기록
                                            
                                            $order -> add_order_note(__('이니시스 모바일 ISP로 결제처리 되었습니다.', 'codem_inicis'));
                                            $order -> add_order_note(sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $P_TYPE, $P_TID));
                                            $order -> add_order_note(sprintf(__("몰 고유 주문번호 : %s", 'codem_inicis'), $P_OID));
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                            $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                            //지불완료
                                        
                                        //펜딩상태 처리 주석
                                        /*else if($status=="pending"){   //펜딩 상태
                                        }*/
                                        }else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                            $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                            $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                            $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                        }
                                        
                                        if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                            $order -> update_status('failed');  //실패상태로 업데이트
                                            $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                            $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
           									$order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                        }
                                        add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                    }
                                    catch(Exception $e)
                                    {
                                            $msg = "Error";
                                    }
                                }
                            }
                            $flag_rst = "OK";
                        } else {
                            //결제 실패시
                        }
                        break;
					default:  //계좌이체
                        if(!strcmp($P_STATUS,"00")) {
                            //결제 성공시
                            if(isset($P_OID))
                            {
                                $order_id_time = $P_OID;
                                $order_id = explode('_', $P_OID);
                                $order_id = (int)$order_id[0];

                                if($order_id != '')
                                {
                                    try
                                    {
                                        //주문번호를 가져와서 주문정보를 가져옴
                                        $order = new WC_Order($order_id);
                                        
                                        $data = $order->get_items();  //해당 주문의 아이템 항목을 가져옴
                                        $product_name = ""; 
                                        
                                        foreach($data as $item) {
                                            $product_name = $item['name'];
                                        }
                                        
                                        $productinfo = $product_name;
                                        
                                        $transauthorised = false;
                                        if($order -> status !=='completed'){  //완료상태가 아닌 경우
                                            
                                            $transauthorised = true; //트랜잭션 상태값
                                            $this -> msg['message'] = __('주문해 주셔서 감사합니다. 곧 상품을 준비하여 배송(또는 사용 가능하도록) 처리될 예정입니다.', 'codem_inicis');  //성공 메시지
                                            $this -> msg['class'] = 'woocommerce_message';  //메시지 클래스 지정
                                            
	                                        $order -> payment_complete();   //주문 지불 완료 처리
		                                                                                
                                            //구매 완료처리시 결제방법에 대한 기록 남김
                                            add_post_meta($order_id, "inicis_paymethod", $P_TYPE);  //결제방법 기록
                                            add_post_meta($order_id, "inicis_paymethod_tid", $P_TID);  //결제시 사용된 TID 값 기록
                                            add_post_meta($order_id, "inicis_paymethod_mid", $P_MID);  //결제시 사용된 MID 값 기록
                                            
                                            $order -> add_order_note(__('이니시스 모바일로 결제처리 되었습니다.', 'codem_inicis'));
                                            $order -> add_order_note(sprintf( __("결제방법 : %s, 이니시스 거래번호(TID) : <a href='https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s' target=_blank>[영수증 확인]</a>", 'codem_inicis'), $P_TYPE, $P_TID));
                                            $order -> add_order_note(sprintf(__("몰 고유 주문번호 : %s", 'codem_inicis'), $P_OID));
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 메시지 작성
                                            $woocommerce -> cart -> empty_cart();   //장바구니 비움
                                            //지불완료
                                        
                                        //펜딩상태 처리 주석
                                        /*else if($status=="pending"){   //펜딩 상태
                                        }*/
                                        }else{  //success,pending 상태가 아닌 경우 (에러 상황)
                                            $this -> msg['class'] = 'woocommerce_error'; //에러메시지 클래스 지정
                                            $this -> msg['message'] = __('주문을 해주셔서 감사합니다만, 주문이 정상적으로 처리되지 않았습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
                                            $order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                        }
                                        
                                        if($transauthorised==false){   //트랜잭션 상태가 false 일경우(문제가 있는 상태)
                                            $order -> update_status('failed');  //실패상태로 업데이트
                                            $order -> add_order_note('Failed');  //실패관련 주문노트 추가
                                            $this -> msg['message'] = __('주문요청을 해주셔서 감사합니다만, 주문처리에 실패했습니다. 관리자에게 문의해주세요!', 'codem_inicis'); //에러메시지
           									$order -> add_order_note( sprintf( __('주문처리 결과메시지 : %s, 에러코드 : %s', 'codem_inicis'), $P_RMESG2, $P_RMESG1)); //결제 거절 메시지 노트 추가
                                            $order -> add_order_note($this->msg['message']);  //주문노트에 해당 내용 추가
                                        }
                                        add_action('the_content', array(&$this, 'showMessage'));   //메시지 보여주는 액션 추가
                                    }
                                    catch(Exception $e)
                                    {
                                            $msg = "Error";
                                    }
                                }
                            }
                            $flag_rst = "OK";
                        } else {
                            //결제 실패시
                        }
                        break;
				/*------------------ end -----------------*/
				}
                echo "OK";
				exit();
            }
        }        
        
        /**
		 * PG 결제 지불 처리(모바일 결제)
		 * (p_return_url로 들어온 경우 처리)
		 * 
		 * @return void
		 * @author Alan
		 */       
        function successful_request_mobile_return( $posted ) {
            //p_return_url은 결과페이지만 보여주는 URL이므로 처리 안함.
            //알아서 결제 이후 페이지로 이동할 것임(결제는 P_NOTI_URL에 연결된 곳에서 처리를 완료함)
            //단, P_NOTI_URL의 처리가 늦어질수 있으므로 P_RETURN_URL 페이지에서 처리는 조심해야함.
            //특별한 동작이 없기 때문에 결제 이후 이동하는 페이지로 이동 처리 됨.
        }
		
        /**
		 * 우커머스 주문 상태 정보를 가져옴
		 * 
		 * @return Array
		 * @author Alan
		 */   	
		function get_order_status(){
        	//주문 상태 정보 가져옴
			$tmp_shop_order_status = get_terms('shop_order_status', array('orderby' => 'name', 'order'=> 'ASC','hide_empty' => false));
			$arr_shop_order_status;
			foreach ($tmp_shop_order_status as $key => $value) {
				$arr_shop_order_status[$value->slug] = __($value->slug, 'codem_inicis').' ('.$value->slug.')';
			}
			return $arr_shop_order_status;
		}
		
        /**
		 * 결제 플러그인 환경설정 폼 필드 초기화
		 * 
		 * @return void
		 * @author Alan
		 */   		
        function init_form_fields(){
			
			//초기 셋팅 필드 배열
            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('사용', 'codem_inicis'),
                    'type' => 'checkbox',
                    'label' => __('이니시스 결제모듈', 'codem_inicis'),
                    'default' => 'no'
                    ),
                'title' => array(
                    'title' => __('결제모듈 이름', 'codem_inicis'),
                    'type'=> 'text',
                    'description' => __('사용자들이 체크아웃(결제진행)시에 나타나는 이름으로 사용자들에게 보여지는 이름입니다.', 'codem_inicis'),
                    'default' => __('신용카드/실시간 계좌이체', 'codem_inicis'),
                    'desc_tip' =>true,					
                    ),
                'description' => array(
                    'title' => __('결제모듈 설명', 'codem_inicis'),
                    'type' => 'textarea',
                    'description' => __('사용자들이 체크아웃(결제진행)시에 나타나는 설명글로 사용자들에게 보여지는 내용입니다.', 'codem_inicis'),
                    'default' => __('이니시스 결제대행사를 통해 결제합니다.', 'codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'libfolder' => array(
                    'title' => __('이니페이 설치 경로', 'codem_inicis'),
                    'type' => 'text',
                    'description' =>  __('이니페이 설치 경로 안에 key 폴더(키파일)와 log 폴더(로그)가 위치한 경로를 입력해주세요. 키파일 폴더와 로그 폴더의 권한 설정은 가이드를 참고해주세요. <br><br><span style="color:red;font-weight:bold;">주의! 사용하시는 호스팅이나 서버 상태에 따라서 웹상에서 접근 불가능한 경로에 업로드 하시고 절대경로 주소를 입력해주세요. 웹상에서 접근 가능한 경로에 폴더가 위치한 경우 키파일 및 로그 파일 노출로 인한 보안사고가 발생할 수 있으며 이 경우 발생하는 문제는 상점의 책임입니다.</span>', 'codem_inicis'),
                    'default'	=> dirname(  __FILE__  ) . '/pg/inicis/' ,
                    'desc_tip' =>true,	
                    ),
                'merchant_id' => array(
                    'title' => __('상점 아이디', 'codem_inicis'),
                    'type' => 'text',
                    'description' => __('이니시스 상점 아이디(MID)를 입력하세요.','codem_inicis'),
                    'default' => __('INIpayTest', 'codem_inicis'),
					'desc_tip' =>true,
                    ),
                'merchant_pw' => array(
                    'title' => __('키파일 비밀번호', 'codem_inicis'),
                    'type' => 'password',
                    'description' =>  __('키파일 비밀번호를 입력해주세요. 기본값은 1111 입니다. ', 'codem_inicis'),
                    'default' => __('1111', 'codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'possible_mypage_refund' => array(
                    'title' => __('사용자 주문취소 가능상태', 'codem_inicis'),
                    'type' => 'inicis_mypage_refund',
                    'desc' => __('이니시스 결제건에 한해서, 사용자가 My-Account 메뉴에서 주문취소 요청을 할 수 있는 주문 상태를 지정합니다.','codem_inicis'),
                    ),
                'possible_admin_refund' => array(
                    'title' => __('관리자 환불 가능상태', 'codem_inicis'),
                    'type' => 'inicis_admin_refund',
                    'desc' => __('이니시스 결제건에 한해서, 관리자가 관리자 페이지 주문 상세 페이지에서 환불 처리를 할 수 있는 주문 상태를 지정합니다.','codem_inicis'),
                    ),
                'redirect_order_status' => array(
                    'title' => __('결제완료시 변경될 주문상태', 'codem_inicis'),
                    'type' => 'inicis_redirect_order_status',
                    'desc' => __('이니시스 플러그인을 통한 결제건에 한해서, 결제후 주문접수가 완료된 경우 해당 주문의 상태를 지정하는 옵션입니다.','codem_inicis'),
                    ),
                'redirect_order_status_refunded' => array(
                    'title' => __('환불처리시 변경될 주문상태', 'codem_inicis'),
                    'type' => 'inicis_redirect_order_status_refunded',
                    'desc' => __('이니시스 플러그인을 통한 결제건에 한해서, 사용자의 환불처리가 승인된 경우 해당 주문의 상태를 지정하는 옵션입니다.','codem_inicis'),
                    ),
                'redirect_page_id' => array(
                    'title' => __('결제완료시 이동 페이지', 'codem_inicis'),
                    'type' => 'select',
                    'options' => $this -> get_pages(__('=== 선택하세요 ===','codem_inicis')),
                    'description' => __('결제 완료시 이동할 페이지를 지정해주세요.','codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'use_currency' => array(
                    'title' => __('결제 통화 선택', 'codem_inicis'),
                    'type' => 'select',
                    'options' => array('WON'=>'대한민국 원화(KRW)', 'USD'=>'미국 달러화(USD)'),
                    'default' => 'WON',
                    'description' => __('이니시스 결제시에 지정되는 통화로 각 상점에서 사용하는 통화에 맞춰서 설정해주세요.','codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'quotabase' => array(
                    'title' => __('할부 구매 개월수 설정', 'codem_inicis'),
                    'type' => 'text',
                    'description' => __('할부 구매를 허용할 개월수를 설정하세요.<span style="color:red;">(무이자 할부 개월수가 아닙니다)</span><br/>예) 선택:일시불:2개월:3개월:6개월<br/>단, 최소 결제금액이 5만원 이상인 경우에만 할부 결제가 허용됩니다. 지정한 할부 개월수와 상관없이 할부 결제 최소 금액이 아닌 경우 할부 거래가 허용되지 않습니다.','codem_inicis'),
                    'default' => __('선택:일시불:1개월:2개월:3개월:4개월:5개월:6개월:7개월:8개월:9개월:10개월:11개월:12개월', 'codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'nointerest' => array(
                    'title' => __('무이자 할부 설정', 'codem_inicis'),
                    'type' => 'checkbox',
                    'label' => __('무이자 할부 허용(수수료 상점 부담) ', 'codem_inicis'),
                    'default' => 'no',
                    'description' => __('무이자 할부 허용하시면 무이자 할부에 따른 수수료는 상점에서 부담하게 됩니다. 수수료는 이니시스에 문의하여 주십시오. (단, 이니시스에서 모든 가맹점을 대상으로 하는 무이자 이벤트인 경우는 제외입니다)','codem_inicis'),
                    'desc_tip' =>true,
                    ),
                'gopaymethod' => array(
                    'title' => __('PC결제수단 사용메뉴 선택','codem_inicis'),
                    'type' => 'inicis_gopaymethod',
                    'desc' => __('PC에서 결제시 보여주실 결제수단을 선택하세요. 체크를 하더라도 이니시스와 계약이 되어 있지 않은 항목의 경우 정상 동작하지 않을 수 있습니다. 계약내용을 확인해주세요.','codem_inicis'),
                    ),
                'gopaymethod_mobile' => array(
                    'title' => __('모바일 결제수단 사용메뉴 선택','codem_inicis'),
                    'type' => 'inicis_gopaymethod_mobile',
                    'desc' => __('모바일에서 결제시 보여주실 결제수단을 선택하세요. 체크를 하더라도 이니시스와 계약이 되어 있지 않은 항목의 경우 정상 동작하지 않을 수 있습니다. 계약내용을 확인해주세요.','codem_inicis'),
                    ),
                'acceptmethod' => array(
                    'title' => __('기타 옵션 선택','codem_inicis'),
                    'type' => 'inicis_acceptmethod',
                    'desc' => __('사용을 원하시는 내용에 체크 또는 선택을 해주세요. 상점 계약 내용에 따라 지원이 안될 수도 있습니다.','codem_inicis'),
                    ),
            );
        }

        /**
         * 'process_inicis_redirect_order_status' 타입의 요청 처리
         *
         * @access public
         * @return void
         */
        function process_inicis_redirect_order_status() {
            if ( isset( $_POST['select_complete_order_status'] ) )   $order_complete_status   = woocommerce_clean( $_POST['select_complete_order_status'] );    
            update_option('woocommerce_inicis_redirect_order_status', $order_complete_status );
        }

        /**
         * 'process_inicis_redirect_order_status_refunded' 타입의 요청 처리
         *
         * @access public
         * @return void
         */
        function process_inicis_redirect_order_status_refunded() {
            if ( isset( $_POST['select_complete_order_status_refunded'] ) )   $order_complete_status_refunded   = woocommerce_clean( $_POST['select_complete_order_status_refunded'] );    
            update_option('woocommerce_inicis_redirect_order_status_refunded', $order_complete_status_refunded );
        }
                
        /**
         * 'process_inicis_mypage_refund' 타입의 요청 처리
         *
         * @access public
         * @return void
         */
        function process_inicis_mypage_refund() {
            $gopaymethod_arr = array();

            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));
            $reorder = array();

            foreach ($shop_order_status as $key => $value) {
                if ( isset( $_POST['chk_mypage_refund_'.$value->slug] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_mypage_refund_'.$value->slug] );    
            }

            $result_enc = '';
            for($i=0;$i<count($gopaymethod_arr);$i++){
                $result_enc .= $gopaymethod_arr[$i].":";
            }
            
            if(strlen($result_enc) != 1) {
                $result_enc = substr($result_enc, 0,strlen($result_enc)-1); 
            }

            update_option('woocommerce_inicis_mypage_refund', $result_enc );
        }

        /**
         * 'process_inicis_admin_refund' 타입의 요청 처리
         *
         * @access public
         * @return void
         */
        function process_inicis_admin_refund() {
            $gopaymethod_arr = array();

            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));
            $reorder = array();

            foreach ($shop_order_status as $key => $value) {
                if ( isset( $_POST['chk_admin_refund_'.$value->slug] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_admin_refund_'.$value->slug] );    
            }

            $result_enc = '';
            for($i=0;$i<count($gopaymethod_arr);$i++){
                $result_enc .= $gopaymethod_arr[$i].":";
            }
            
            if(strlen($result_enc) != 1) {
                $result_enc = substr($result_enc, 0,strlen($result_enc)-1); 
            }

            update_option('woocommerce_inicis_admin_refund', $result_enc );
        }
	
	    /**
	     * 'inicis_gopaymethod' 타입의 요청 처리
	     *
	     * @access public
	     * @return void
	     */
	    function process_inicis_gopaymethod() {
			$gopaymethod_arr = array();
	
			if ( isset( $_POST['chk_card'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_card'] );
			if ( isset( $_POST['chk_directbank'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_directbank'] );
			if ( isset( $_POST['chk_hpp'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_hpp'] );
			if ( isset( $_POST['chk_vbank'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_vbank'] );
			if ( isset( $_POST['chk_ocbpoint'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_ocbpoint'] );
			if ( isset( $_POST['chk_phonebill'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_phonebill'] );
			if ( isset( $_POST['chk_culture'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_culture'] );
			if ( isset( $_POST['chk_dgcl'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_dgcl'] );
			if ( isset( $_POST['chk_teencash'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_teencash'] );
			if ( isset( $_POST['chk_bcsh'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_bcsh'] );
			if ( isset( $_POST['chk_hpmn'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_hpmn'] );
			if ( isset( $_POST['chk_mmlg'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_mmlg'] );
			if ( isset( $_POST['chk_ypay'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_ypay'] );

			$result_enc = '';
			for($i=0;$i<count($gopaymethod_arr);$i++){
				$result_enc .= $gopaymethod_arr[$i].":";
			}
			
			if(strlen($result_enc) != 1) {
				$result_enc = substr($result_enc, 0,strlen($result_enc)-1);	
			}

			update_option('woocommerce_inicis_gopaymethod', $result_enc );
	    }

	    /**
	     * 'inicis_gopaymethod' 타입의 요청 처리 
	     *
	     * @return void
	     * @return void
	     */
	    function process_inicis_gopaymethod_mobile() {
			$gopaymethod_arr = array();
	
			if ( isset( $_POST['chk_mobile_card'] ) )   $gopaymethod_arr[]   = woocommerce_clean( $_POST['chk_mobile_card'] );

			$result_enc = '';
			for($i=0;$i<count($gopaymethod_arr);$i++){
				$result_enc .= $gopaymethod_arr[$i].":";
			}
			
			if(strlen($result_enc) != 1) {
				$result_enc = substr($result_enc, 0,strlen($result_enc)-1);	
			}

			update_option('woocommerce_inicis_gopaymethod_mobile', $result_enc );
	    }

	    /**
	     * 'inicis_acceptmethod' 타입의 요청 처리 
	     *
	     * @return void
	     * @return void
	     */
	    function process_inicis_acceptmethod() {
			$tmp_option = '';
	
			if ( isset( $_POST['chk_accept_cardpoint'] ) )  	$accept_cardpoint   = woocommerce_clean( $_POST['chk_accept_cardpoint'] );
			if ( isset( $_POST['chk_accept_skin'] ) )   		$accept_skin   		= woocommerce_clean( $_POST['chk_accept_skin'] );
			if ( isset( $_POST['chk_accept_skin_value'] ) )   	$accept_skin_value  = woocommerce_clean( $_POST['chk_accept_skin_value'] );

			//카드 결제 여부 확인
			$default_data = get_option('woocommerce_inicis_gopaymethod');
			$method_arr = explode( ':', $default_data );  //문자열 분리

			//모바일 카드 결제 여부 확인
			$default_data = get_option('woocommerce_inicis_gopaymethod_mobile');
			$method_mobile_arr = explode( ':', $default_data );  //문자열 분리
			
			//카드 결제 사용 여부 확인
			if($this->check_use_method_array($method_arr, "Card")){
				$tmp_option .= 'Card(0):';
			} else if($this->check_use_method_array($method_mobile_arr, "wcard")){
				$tmp_option .= 'Card(0):';
			}
			
			//신용카드 포인트 결제 허용 (계약된 경우)
			if($accept_cardpoint == 'yes') {
				$tmp_option .= 'CardPoint:';
			}
			
			//플러그인 스킨 컬러 변경 옵션
			if($accept_skin == 'yes') {
				$tmp_option .= $accept_skin_value.':';
			}

			if(strlen($tmp_option) != 1) {
				$tmp_option = substr($tmp_option, 0,strlen($tmp_option)-1);	
			}

			update_option('woocommerce_inicis_acceptmethod', $tmp_option );
	    }

        /**
		 * PC결제 허용 수단 체크 함수 
		 * (결제 허용 수단 체크 함수)
		 * 
		 * @return Bool
		 * @author Alan
		 */
	    function validate_inicis_gopaymethod_field( $key ) {
			return false;
	    }

        /**
         * 사용자 환불 가능 주문상태 체크 함수 
         * (결제 허용 수단 체크 함수)
         * 
         * @return Bool
         * @author Alan
         */
        function validate_inicis_mypage_refund_field( $key ) {
            return false;
        }

		
        /**
		 * 모바일 결제 허용 수단 체크 함수 
		 * (결제 허용 수단 체크 함수)
		 * 
		 * @return Bool
		 * @author Alan
		 */
	    function validate_inicis_gopaymethod_mobile_field( $key ) {
			return false;
	    }		

        /**
		 * 기타 옵션 체크 함수 
		 * (카드 포인트 결제 등 처리)
		 * 
		 * @return Bool
		 * @author Alan
		 */
	    function validate_inicis_acceptmethod_field( $key ) {
			return false;
	    }		

        /**
		 * 결제 필드 구성 함수
		 * (여기서는 결제 플러그인 설명만 노출하도록 되어 있으나, 필요에 따라서 폼을 구성하여 직접 결제가 가능하도록 구성 가능한 부분)
		 * 
		 * @return void
		 * @author Alan
		 */   		
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
		
        /**
		 * 지불 페이지 상단에 나타나는 안내 문구 및 결제 폼 생성 함수
		 * 
		 * @return void
		 * @author Alan
		 */   		
        function receipt_page($order){
            echo '<p>'.__('주문해주셔서 감사합니다. 결제 버튼을 눌러 결제처리를 진행하여 주시기 바랍니다. ', 'codem_inicis').'</p>';
            echo $this->generate_inicis_form($order);
        }
        
        /**
		 * 서버 콜백이 이상이 없는지 체크하고 해당 함수로 처리를 넘기는 함수
		 * 
		 * @return void
		 * @author Alan
		 */  
        function check_inicis_response(){
            if(!empty($_REQUEST)) { 
                //결제처리시 필요한 데이터가 넘어온 경우
                header( 'HTTP/1.1 200 OK' );
                
                //결제처리 타입에 따른 분기처리
                if(!empty($_REQUEST['type'])){
                    //타입에 따라서 결제 처리 함수를 다르게 호출 함
                    switch($_REQUEST['type']){
						case "cancelled":
							//결제 취소 처리
							do_action("valid-inicis-request_cancelled", $_POST );
							
							//결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
							break;
                        case "pc":
                            //PC결제 처리
                            do_action( "valid-inicis-request_pc", $_POST );  // WC_Api를 통해서 넘어온 값을 이용하여 최종 결제 처리 함수 실행

                            //결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
                            break;
                        case "mobile_next":
                            //모바일 결제 처리
                            //P_NEXT_URL 처리 부분, 결과화면 URL 
                            //ISP(안절결제) 인증 결제를 제외한 VISA3D, 기타 지불 수단(ISP, 계좌이체는 사용안함)
                            do_action( "valid-inicis-request_mobile_next", $_POST );  // WC_Api를 통해서 넘어온 값을 이용하여 최종 결제 처리 함수 실행

                            //결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
                            break;
                        case "mobile_noti":
                            //모바일 결제 처리
                            //P_NOTI_URL 처리 부분, 결과처리 URL
                            //ISP, 가상계좌, 계좌이체, 삼성월렛만 사용
                            do_action( "valid-inicis-request_mobile_noti", $_POST );  // WC_Api를 통해서 넘어온 값을 이용하여 최종 결제 처리 함수 실행

                            //결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
                            break;
                        case "mobile_return":
                            //모바일 결제 처리
                            //P_RETURN_URL 처리 부분, 결과화면 URL
                            //ISP, 계좌이체, 삼성월렛만 사용
                            do_action( "valid-inicis-request_mobile_return", $_POST );  // WC_Api를 통해서 넘어온 값을 이용하여 최종 결제 처리 함수 실행

                            //결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
                            break;
                        case "cancel_payment":
                            //거래취소 처리
                            do_action( "valid-inicis-request_cancel_payment", $_POST );  // WC_Api를 통해서 넘어온 값을 이용하여 최종 결제 처리 함수 실행
                            //결제처리 이후에 미리 지정된 페이지로 리다이렉트 처리
                            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                            wp_redirect($redirect_url);  //WooCommerce 결제 완료후 이동 페이지가 지정되어 있으면 해당 하는 곳으로 이동, 없으면 웹사이트 기본 페이지로 이동.
                            break;                                                    
                        default:
                            wp_die(__('결제 요청 실패 : 관리자에게 문의하세요!', 'codem_inicis'));
                            break;
                    }
                } else {
                    wp_die(__('결제 요청 실패 : 관리자에게 문의하세요!', 'codem_inicis'));
                }
            } else {
                wp_die(__('결제 요청 실패 : 관리자에게 문의하세요!', 'codem_inicis'));
            }
        }

        /**
		 * 결제 방식 이름 반환 함수
		 * (영문으로된 결제 방식을 전달 받으면 해당 되는 한글명으로 반환)
		 * 
		 * @return String
		 * @author Alan
		 */  
		function getMethodName($str){
			$str = woocommerce_clean($str);
			switch($str){
				/********************[PC결제]*****************/	
				case "Card":
					return "신용카드";
				case "DirectBank":
					return "계좌이체";
				/******************[모바일결제]***************/
				case "wcard":
					return "신용카드(안심클릭)";
				case "vbank":
					return "가상계좌";
				case "mobile":
					return "휴대폰";
				case "culture":
					return "문화상품권";
				case "hpmn":
					return "해피머니상품권";
				case "bank":
					return "계좌이체";
				default:
					return "기타";
			}
		}	
        
        /**
		 * 이니시스 결제 폼 생성 함수
		 * (이니시스 지불 페이지에서 결제폼과 함께 결제 버튼을 생성한다, 
		 * 모바일인 경우에는 관련해서 구분하여 결제가능하도록 내용을 출력한다.)
		 * 
		 * @return void
		 * @author Alan
		 */  
        function generate_inicis_form($order_id){
            global $woocommerce;

            $order = new WC_Order($order_id);
            $txnid = $order_id.'_'.date("ymds");
            $data = $order->get_items();
            $product_name = "";
            foreach($data as $item) {
                $product_name = $item['name'];
            }
                        
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
            $productinfo = $product_name;

            //검증용 해시값 생성(SHA512)
            $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||";
            $hash = hash('sha512', $str);
            $price_all = $order -> order_total;
            $price_all = floor($price_all);
            
            //모바일 결제 지원시 결제 폼이 달라져야 하므로, 그에 따른 모바일 브라우저 검사 및 노출되는 폼 정보 변경처리
            $return_value = "";
            $useragent=$_SERVER['HTTP_USER_AGENT'];
            $is_mobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|iPad|iPod|SHW-M480W|Nexus 7|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4));
			
        	$str_tmp = "";	//임시 문자열 변수
			
			//PC결제 허용 방법 리스트 생성
        	$paymethod_list = get_option('woocommerce_inicis_gopaymethod');
			if(!empty($paymethod_list)){
	            $paymethod_arr = explode( ':', $paymethod_list );  //문자열 분리
			} else {
				$paymethod_arr = null;
			}
			
			//MOBILE결제 허용 방법 리스트 생성
	        $paymethod_mobile_list = get_option('woocommerce_inicis_gopaymethod_mobile');
			if(!empty($paymethod_mobile_list)){
	            $paymethod_mobile_arr = explode( ':', $paymethod_mobile_list );  //문자열 분리
			} else {
				$paymethod_mobile_arr = null;
			}
			
            if($is_mobile){		//모바일 브라우저 인 경우(Mobile)

				//MOBILE인 경우 결제 방법 가져오기
	            for($i=0;$i<count($paymethod_mobile_arr);$i++){
	            	if($i==0) {
	            		if(count($paymethod_mobile_arr) == 0) {
							$str_tmp .= '';	            			
	            		} else {
							$str_tmp .= '<p class="mobile_paymethod_p"><input type="radio" name="paymethodtype" value="'.$paymethod_mobile_arr[$i].'" checked/>'.$this->getMethodName($paymethod_mobile_arr[$i]).'</p>';
	            		}
	            	} else {
						$str_tmp .= '<p class="mobile_paymethod_p"><input type="radio" name="paymethodtype" value="'.$paymethod_mobile_arr[$i].'"/>'.$this->getMethodName($paymethod_mobile_arr[$i]).'</p>';
	            	}
	            }            
            
                $return_value = '
                <br/><br/>* 주의 : 스마트폰은 팝업차단을 옵션을 꺼주셔야 결제를 진행할수 있습니다!<br/><br/>';
				
				//결제 방법을 하나라도 선택 안한 경우 결제 방법 메시지를 감추고 결제방법을 설정하라는 메시지를 출력
				if(count($paymethod_mobile_arr) == 0) {
					$return_value .= '';
				} else {
					$return_value .= '
	                <p class="mobile_paymethod_p">모바일 결제방법 : </p>'.$str_tmp.'
					';
				}
				
				$return_value .= '                
                <form id="form1" name="ini" method="post" action="" accept-charset="EUC-KR">
                    <input type="hidden" name="inipaymobile_type" id="select" value="web"/>
                    <input type="hidden" name="P_OID" value="'.$order_id.'_'.date("ymds").'"/>
                    <input type="hidden" name="P_GOODS" value="'.$productinfo.'"/>
                    <input type="hidden" name="P_AMT" value="'.$price_all.'"/>
                    <input type="hidden" name="P_UNAME" value="'.$order->billing_last_name.' '.$order->billing_first_name.'"/>
                    <input type="hidden" name="P_MNAME" value="'.get_bloginfo('name').'"/>
                    <input type="hidden" name="P_MOBILE" value="'.$order->billing_phone.'"/>
                    <input type="hidden" name="P_EMAIL" value="'.$order->billing_email.'" />
                    <input type="hidden" name="P_MID" value="'.$this->merchant_id.'">
                    <input type="hidden" name="P_NEXT_URL" value="'.home_url().'/wc-api/WC_Gateway_Inicis?type=mobile_next">
                    <input type="hidden" name="P_RETURN_URL" value="'.home_url().'/wc-api/WC_Gateway_Inicis?type=mobile_return&oid='.$order_id.'_'.date("ymds").'">
                    <input type="hidden" name="P_NOTI_URL" value="'.home_url().'/wc-api/WC_Gateway_Inicis?type=mobile_noti">
                    <input type="hidden" name="P_CANCEL_URL" value="'.home_url().'/wc-api/WC_Gateway_Inicis?type=mobile_return&oid='.$order_id.'_'.date("ymds").'">
                    <input type="hidden" name="P_HPP_METHOD" value="1">
                    <input type="hidden" name="P_APP_BASE" value="">
                    <input type="hidden" name="P_RESERVED" value="ismart_use_sign=Y">';
					
				//결제 방법을 하나라도 선택 안한 경우 결제 방법 메시지를 감추고 결제방법을 설정하라는 메시지를 출력
				if(count($paymethod_mobile_arr) == 0) {
					$return_value .= '<p class="mobile_paymethod_p_blue">결제 방법이 설정되지 않았습니다. 상점 관리자에게 문의하여 주십시오.</p>';
				} else {
					$return_value .= '
                    <input type="hidden" name="paymethod" size=20 value="'.$paymethod_mobile_arr[0].'" />
					<img id="inicis_image_btn" src="'.plugins_url( "assets/images/button_03.gif", __FILE__ ).'" width="63" height="25" style="width:63px;height:25px;border:none;padding:0px;margin:8px 0px;" onclick="javascript:onSubmit();"/>';
				} 
				$return_value .= '</form>';
            } else {		//일반 브라우저 인 경우(PC)
				//PC인 경우 결제 방법 가져오기
	            for($i=0;$i<count($paymethod_arr);$i++){
	            	if($i==0) {
						$str_tmp .= '<p class="mobile_paymethod_p"><input type="radio" name="paymethodtype" value="'.$paymethod_arr[$i].'" checked/>'.$this->getMethodName($paymethod_arr[$i]).'</p>';	            			
	            	} else {
	            		$str_tmp .= '<p class="mobile_paymethod_p"><input type="radio" name="paymethodtype" value="'.$paymethod_arr[$i].'"/>'.$this->getMethodName($paymethod_arr[$i]).'</p>';
	            	}
	            }
            
				//결제 폼  내용 반환
                $return_value = '
                    <script language=javascript src="http://plugin.inicis.com/pay61_secuni_cross.js"></script>
                    <body bgcolor="#FFFFFF" text="#242424" leftmargin=0 topmargin=15 marginwidth=0 marginheight=0 bottommargin=0 rightmargin=0 onload="javascript:enable_click()" onFocus="javascript:focus_control()">';
					
				//결제 방법을 하나라도 선택 안한 경우 결제 방법 메시지를 감추고 결제방법을 설정하라는 메시지를 출력
				if(count($paymethod_arr) == 0) {
					$return_value .= '';
				} else {
					$return_value .= '
	                <p class="mobile_paymethod_p">결제방법 : </p>'.$str_tmp.'
					';
				}
					
				$return_value .= '					
                    <form name=ini method=post action="'.home_url().'/wc-api/WC_Gateway_Inicis?type=pc" onSubmit="return pay(this)"> 
                    <input type="hidden" name="goodname" size=20 value="'.$productinfo.'" />
                    <input type="hidden" name="oid" size=40 value="'.$order_id.'_'.date("ymds").'" />
                    <input type="hidden" name="buyername" size=20 value="'.$order->billing_last_name.' '.$order->billing_first_name.'" />
                    <input type="hidden" name="buyeremail" size=20 value="'.$order->billing_email.'" />
                    <input type="hidden" name="buyertel" size=20 value="'.$order->billing_phone.'" />';
				
				//결제 방법을 하나라도 선택 안한 경우 결제 방법 메시지를 감추고 결제방법을 설정하라는 메시지를 출력
				if(count($paymethod_arr) == 0) {
					$return_value .= '<p class="mobile_paymethod_p_blue">결제 방법이 설정되지 않았습니다. 상점 관리자에게 문의하여 주십시오.</p>';
				} else {
					$return_value .= '
                    <input type="hidden" name="gopaymethod" size=20 value="'.$paymethod_arr[0].'" />
					<p><input id="inicis_image_btn" type="image" src="'.plugins_url( 'assets/images/button_03.gif', __FILE__ ).'" width="63" height="25" style="width:63px;height:25px;border:none;padding:0px;margin:8px 0px;" /> </p>';
				} 

				$acceptmethod = get_option('woocommerce_inicis_acceptmethod');

				$return_value .= ' 
                    <input type="hidden" name="currency" size=20 value="WON" />
                    <input type="hidden" name="acceptmethod" size=20 value="'.$acceptmethod.'" />
                    <input type="hidden" name="ini_logoimage_url" value="'.plugins_url( 'assets/images/codemshop_logo_pg.jpg', __FILE__ ).'" />
                    <input type=hidden name=quotainterest value="">
                    <input type=hidden name=paymethod value="">
                    <input type=hidden name=cardcode value="">
                    <input type=hidden name=cardquota value="">
                    <input type=hidden name=rbankcode value="">
                    <input type=hidden name=reqsign value="DONE">
                    <input type=hidden name=encrypted value="">
                    <input type=hidden name=sessionkey value="">
                    <input type=hidden name=uid value=""> 
                    <input type=hidden name=sid value="">
                    <input type=hidden name=version value=4000>
                    <input type=hidden name=clickcontrol value="">
                    <input type=hidden name=hash value="'.$hash.'">
                    <input type=hidden name=txnid value="'.$txnid.'">
                    <input type=hidden name=Amount value="'.$price_all.'">
                ';
            }

			/********************** 이니시스 라이브러리 처리 시작 **********************/
            require($this->settings['libfolder']."/libs/INILib.php");

            $inipay = new INIpay50();
            
            $inipay->SetField("inipayhome", $this->settings['libfolder']);       // 이니페이 홈디렉터리(상점수정 필요)
            $inipay->SetField("type", "chkfake");      // 고정 (절대 수정 불가)
            $inipay->SetField("debug", "true");        // 로그모드("true"로 설정하면 상세로그가 생성됨.)
            $inipay->SetField("enctype","asym");            //asym:비대칭, symm:대칭(현재 asym으로 고정)
            $inipay->SetField("admin", $this->merchant_pw);               // 키패스워드(키발급시 생성, 상점관리자 패스워드와 상관없음)
            $inipay->SetField("checkopt", "false");         //base64함:false, base64안함:true(현재 false로 고정)
            $inipay->SetField("mid", $this->merchant_id);            // 상점아이디
            $inipay->SetField("price", $price_all);                // 가격
            $inipay->SetField("nointerest", $this->settings['nointerest']);             //무이자여부(no:일반, yes:무이자)
            $inipay->SetField("quotabase", iconv("UTF-8", "EUC-KR", $this->settings['quotabase']));//할부기간

            $inipay->startAction();
			/********************** 이니시스 라이브러리 처리 끝 **********************/

            if( $inipay->GetResult("ResultCode") != "00" ) 
            {
                echo $inipay->GetResult("ResultMsg");
                exit(0);
            }

            //주문정보에 결제 정보 저장
            update_post_meta($order_id, "ini_mid", $this -> merchant_id);
            update_post_meta($order_id, "ini_admin", $this -> merchant_pw);
            update_post_meta($order_id, "ini_price", $price_all);
            update_post_meta($order_id, "ini_rn", $inipay->GetResult("rn"));
            update_post_meta($order_id, "ini_enctype", $inipay->GetResult("enctype"));
            
            //PC결제인 경우 화면에 PC결제시 사용하는 코드 노출
            if(!$is_mobile){
                $return_value = $return_value.'<input type=hidden name=ini_encfield value="'.$inipay->GetResult("encfield").'">
                        <input type=hidden name=ini_certid value="'.$inipay->GetResult("certid").'">
                    </form>
                    </body>';
            }

            return $return_value;
        }

        /**
		 * 결제를 처리하고 결과를 리턴하는 함수
		 * 
		 * @return Array
		 * @author Alan
		 */           
        function process_payment($order_id){

            global $woocommerce;

            $order = new WC_Order($order_id);
            
            //WooCommerce Version Check
            if(version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' )) { 
                return array(
                    'result'    => 'success',
                    'redirect'  => $order->get_checkout_payment_url( true )
                );
            } else { 
                return array(
                    'result' => 'success', 
                    'redirect' => add_query_arg('order',$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
                );
            }
        }  

        /**
		 * 메시지 노출 함수
		 * 
		 * @return String
		 * @author Alan
		 */          
        function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
        
        /**
		 * 페이지 가져오기 함수
		 * 
		 * @return Array List
		 * @author Alan
		 */          
		function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
   		}
	}
}

/**
 * WooCommerce Payment Gateway 등록
 * (PG 리스트에 이니시스 클래스명을 추가하여 준다. 여기에 등록이 되어 있어야 자동으로 로드가 된다)
 * 
 * @return Array
 * @author Alan
 */    
function woocommerce_add_inicis_gateway($methods) {
    $methods[] = 'WC_Gateway_Inicis';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_add_inicis_gateway');

/**
 * 액션 링크에 링크 추가
 *
 * @param mixed $links
 * @return void
 */
function action_links( $links ) {
	unset($links['edit']);		//플러그인 페이지에서 플러그인 편집 못하도록 제거
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'action_links');		//플러그인 리스트에서 액션 리스트 필터링
