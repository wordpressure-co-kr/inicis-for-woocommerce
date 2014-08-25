<?php 
if( class_exists('WC_Payment_Gateway') ) { 

    include_once('class-encrypt.php');
    
    class WC_Gateway_Inicis extends WC_Payment_Gateway{
        public function __construct(){
            add_filter( 'woocommerce_my_account_my_orders_actions',  array($this, 'woocommerce_my_account_my_orders_actions'), 10, 2 );
        }
        
        function get_payment_description( $paymethod ) {
            switch($paymethod){
                case "card": 
                    return __( '신용카드(안심클릭)', 'inicis_payment' );
                    break;
                case "vcard":
                    return __( '신용카드(ISP)', 'inicis_payment' );
                    break;
                case "directbank":
                    return __( '무통장입금(실시간계좌이체)', 'inicis_payment' );
                    break;
                case "wcard": 
                    return __( '신용카드(모바일)', 'inicis_payment' );
                    break;
                case "vbank": 
                    return __( '가상계좌(모바일)', 'inicis_payment' );
                    break;
                case "bank":
                    return __( '실시간계좌이체(모바일)', 'inicis_payment' );
                    break;
                default:
                    return $paymethod;
                    break;
            }
        }
        
        public function wp_ajax_refund_request() {
            global $woocommerce;
            $valid_order_status = $this->settings['possible_refund_status_for_admin'];
            $order_id = $_REQUEST['order_id'];
            $order = new WC_Order( $order_id );

        
            if( !in_array($order->status, $valid_order_status) ){
                wp_send_json_error( __('주문을 취소할 수 없는 상태입니다.', 'inicis_payment' ) );
            }
        
            $paymethod = get_post_meta($order_id, "inicis_paymethod", true);
            $paymethod = strtolower($paymethod); 
            $paymethod_tid = get_post_meta($order_id, "inicis_paymethod_tid", true); 

            if( empty($paymethod) || empty($paymethod_tid) ) {
                wp_send_json_error( __( '주문 정보에 오류가 있습니다.', 'inicis_payment' ) );
            }
            
            $rst = $this->cancel_request($paymethod_tid, __( '관리자 주문취소', 'inicis_payment' ), __( 'CM_CANCEL_002', 'inicis_payment' ) );
            if($rst == "success"){
                if($_POST['refund_request']) {
                    unset($_POST['refund_request']);
                }
                
                $order->update_status( 'cancelled' );
                $order->add_order_note( sprintf( __('관리자의 요청으로 주문(%s)이 취소 처리 되었습니다.', 'inicis_payment'), $this->get_payment_description($paymethod)) );
                update_post_meta($order->id, '_codem_inicis_order_cancelled', TRUE);
                wp_send_json_success( __( '주문이 정상적으로 취소되었습니다.', 'inicis_payment' ) );
            } else {
                wp_send_json_error( __( '주문 취소 시도중 오류가 발생했습니다.', 'inicis_payment' ) );
                wc_add_notice( __( '주문 취소 시도중 오류가 발생했습니다. 관리자에게 문의해주세요.', 'inicis_payment' ), 'error' );
            }
        }

        public function woocommerce_payment_complete_order_status($new_order_status, $id) {
            $order_status = $this->settings['order_status_after_payemnt'];
            if ( !empty($order_status) ) {
                return $order_status;
            } else {
                return $new_order_status;
            }
        }

        public function inicis_mypage_cancel_order($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $valid_order_status = $this->settings['possible_refund_status_for_mypage'];
        
            if( !in_array($order->status, $valid_order_status) ){
                wc_add_notice( __( '주문을 취소할 수 없는 상태입니다. 관리자에게 문의해 주세요.', 'inicis_payment' ), 'error' );
                return;
            }
            
            $paymethod = get_post_meta($order_id, "inicis_paymethod", true);
            $paymethod = strtolower($paymethod); 
            $paymethod_tid = get_post_meta($order_id, "inicis_paymethod_tid", true); 

            if( empty($paymethod) || empty($paymethod_tid) ) {
                wc_add_notice( __( '주문 정보에 오류가 있습니다.관리자에게 문의해 주세요.', 'inicis_payment' ), 'error' );
                return;
            }

            $rst = $this->cancel_request($paymethod_tid, __( '사용자 주문취소', 'inicis_payment' ), __( 'CM_CANCEL_001', 'inicis_payment' ) );
            if($rst == "success"){
                if($_POST['refund_request']) {
                    unset($_POST['refund_request']);
                }
                $order->update_status( 'cancelled' );
                wc_add_notice( __( '주문이 정상적으로 취소되었습니다.', 'inicis_payment' ), 'success' );
                $order->add_order_note( sprintf( __('사용자의 요청으로 주문(%s)이 취소 처리 되었습니다.', 'inicis_payment'), $this->get_payment_description($paymethod)) );
                update_post_meta($order->id, '_codem_inicis_order_cancelled', TRUE);
            } else {
                wc_add_notice( __( '주문 취소 시도중 오류가 발생했습니다. 관리자에게 문의해주세요.', 'inicis_payment' ), 'error' );
                $order->add_order_note( sprintf( __('사용자 주문취소 시도 실패 (에러메세지 : %s)', 'inicis_payment'), $rst) );
            }
        }
        
        public function ifw_is_admin_refundable($refundable, $order) {
            $valid_order_status = $this->settings['possible_refund_status_for_admin'];
        
            if( !empty($valid_order_status) && in_array($order->status, $valid_order_status) ){
                return true;
            }else{
                return false;
            }
        }
        
        public function woocommerce_my_account_my_orders_actions($actions, $order){
            $payment_method = get_post_meta($order->id, '_payment_method', true);

            if($payment_method == $this->id) {
                $valid_order_status = $this->settings['possible_refund_status_for_mypage'];
            
                if( !empty($valid_order_status) && in_array($order->status, $valid_order_status) ){ 
                    
                    $cancel_endpoint = get_permalink( wc_get_page_id( 'cart' ) );
                    $myaccount_endpoint = get_permalink( wc_get_page_id( 'myaccount' ) );
                
                    $actions['cancel'] = array(
                        'url'  => wp_nonce_url( add_query_arg( array( 'inicis-cancel-order' => 'true', 'order' => $order->order_key, 'order_id' => $order->id, 'redirect' => $myaccount_endpoint ), $cancel_endpoint ), 'mshop-cancel-order' ),
                        'name' => __( 'Cancel', 'woocommerce' )
                    );
                }else{
                    unset($actions['cancel']);
                }
            } 
        
            return $actions;
        }
    
        public function validate_ifw_order_status_field($key) {
            $option_key = $this->id . '_' . $key;
            return $_POST[$option_key];
        }
        
        public function validate_ifw_logo_upload_field($key) {
            return $_POST[$key];
        }     
        
        public function validate_ifw_keyfile_upload_field($key) {
            if( empty($_FILES['upload_keyfile']) && !isset($_FILES['upload_keyfile']) ) {
                return; 
            }    
            if ( !file_exists( WP_CONTENT_DIR . '/inicis/upload' )) {
                $old = umask(0); 
                mkdir( WP_CONTENT_DIR . '/inicis/upload', 0777, true );
                umask($old);
            }
            
            if( $_FILES['upload_keyfile']['size'] > 4086 ) {
                return false;
            }

            if( !class_exists('ZipArchive') ) {
                return false;
            } 
            
            $zip = new ZipArchive();
            if(isset($_FILES['upload_keyfile']['tmp_name']) && !empty($_FILES['upload_keyfile']['tmp_name'])) {
                if($zip->open($_FILES['upload_keyfile']['tmp_name']) == TRUE) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if( !in_array( $filename, array('readme.txt', 'keypass.enc', 'mpriv.pem', 'mcert.pem') ) ) {
                            return false;
                        }
                    }
                }
                
                $movefile = move_uploaded_file($_FILES['upload_keyfile']['tmp_name'], WP_CONTENT_DIR . '/inicis/upload/' . $_FILES['upload_keyfile']['name'] );
                if ( $movefile ) {
                    WP_Filesystem();
                    $filepath = pathinfo( WP_CONTENT_DIR . '/inicis/upload/' . $_FILES['upload_keyfile']['name'] );
                    $unzipfile = unzip_file( WP_CONTENT_DIR . '/inicis/upload/' . $_FILES['upload_keyfile']['name'], WP_CONTENT_DIR . '/inicis/key/' . $filepath['filename'] );
    
                    $this->init_form_fields();
    
                    if ( !is_wp_error($unzipfile) ) {
                        if ( !$unzipfile )  {
                            return false;    
                        }
                        return true;
                    }
                } else {
                    return false;
                }   
            }
        }      
        
        public function generate_ifw_order_status_html($key, $value) {
            $option_key = $this->id . '_' . $key;
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));
            $selections = $this->settings[$key];
            
            if( empty($selections) ){
                $selections = $value['default'];
            }
            
            ob_start();
            ?><tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                    <?php echo $this->get_tooltip_html($value); ?>
                </th>
                <td class="forminp">
                    <select multiple="multiple" name="<?php echo esc_attr( $option_key ); ?>[]" style="width:350px" data-placeholder="<?php _e( '주문 상태를 선택하세요.', 'inicis_payment' ); ?>" title="<?php _e( 'Order Status', 'inicis_payment' ); ?>" class="chosen_select">
                        <?php
                            if ( $shop_order_status ) {
                                foreach ( $shop_order_status as $status ) {
                                    $selected = selected( in_array( $status->slug, $selections ), true, false );
                                    echo '<option value="' . esc_attr( $status->slug ) . '" ' . $selected .'>' . $status->name . '</option>';
                                }
                            }
                        ?>
                    </select><br>
                    <a class="select_all button" href="#"><?php _e( 'Select all', 'inicis_payment' ); ?></a> <a class="select_none button" href="#"><?php _e( 'Select none', 'inicis_payment' ); ?></a>
                </td>
            </tr><?php
            return ob_get_clean();
        }

        public function generate_ifw_keyfile_upload_html($key, $value) {
            
            ob_start();
            ?><tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                    <?php echo $this->get_tooltip_html($value); ?>
                </th>
                <td class="forminp">
                    <input id="upload_keyfile" type="file" size="36" name="upload_keyfile" />
                </td>
            </tr><?php
            return ob_get_clean();
        }
        
        public function generate_ifw_logo_upload_html($key, $value) {
            $imgsrc = $this->settings[$key];
            
            if( empty($imgsrc) ){
                $imgsrc = $value['default'];
            }
            
            ob_start();
            ?><tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                    <?php echo $this->get_tooltip_html($value); ?>
                </th>
                <td class="forminp">
                    <img src="<?php echo $imgsrc; ?>" id="upload_logo_preview" style="border: solid 1px #666;"><br>
                    <input id="upload_logo" type="text" size="36" name="<?php echo $key; ?>" value="<?php echo $imgsrc; ?>" />
                    <input class="button" id="upload_logo_button" type="button" value="<?php _e( 'Upload/Select Logo', 'inicis_payment' ); ?>" />
                    <br>                    
                </td>
            </tr><?php
            return ob_get_clean();
        }        

        function process_activate(){
            $url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLXJlZ2lzdGVy");
            if(empty($_POST['p_activate_key'])) { return false; }
            if(empty($_POST['p_email'])) { return false; }

            $response = wp_remote_post( $url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'action' => 'activate-register', 'activate_key' => $_POST['p_activate_key'], 'email' => $_POST['p_email'], 'homeurl' => home_url() ),
                )
            );
            
            if ( is_wp_error( $response ) ) {
               $error_message = $response->get_error_message();
               echo '<div id="message" class="error fade"><p><strong>' . __( '플러그인 인증 체크 오류 : ', 'inicis_payment' ) . $error_message . '</strong></p></div>';
            } else {
               if( $response['body'] == '1' ) {
                    update_option('_codem_inicis_activate_key', $_POST['p_activate_key']);
                    return true;    
               } else {
                    return false;
               }  
            }
        }

        function process_activate_check(){
            $url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLXJlZ2lzdGVy");
            $mid = '';
            
            if(empty($_POST['woocommerce_'.$this->id.'_merchant_id'])) {
                return false; 
            } else {
                $mid = trim($_POST['woocommerce_'.$this->id.'_merchant_id']);
                if(!$this->check_mid($mid)) {
                    return false;
                } 
            }
            $tmp_key = get_option('_codem_inicis_activate_key');
            if(empty($tmp_key)) { return false; }

            $response = wp_remote_post( $url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'action' => 'activate-register-mid', 'activate_key' => $tmp_key, 'mid' => $mid, 'homeurl' => home_url() ),
                )
            );
            
            if ( is_wp_error( $response ) ) {
               $error_message = $response->get_error_message();
               echo '<div id="message" class="error fade"><p><strong>' . __( '플러그인 인증 체크 오류 : ', 'inicis_payment' ) . $error_message . '</strong></p></div>';
            } else {
               if( $response['body'] == '1' ) {
                   return true;
               } else {
                   return false;
               }  
            }
        }

        function check_mid($mid){
            if(!empty($mid)) {
                $tmpmid = substr($mid, 0, 3);
                if( !($tmpmid == base64_decode("SU5J") || $tmpmid == base64_decode("Q09E") || $tmpmid == base64_decode("Y29k") || $mid == base64_decode("Y29kZW1zdG9yeQ==") ) )  {
                    $tmparr = get_option('woocommerce_'.$this->id.'_settings');    
                    $tmparr['merchant_id'] = base64_decode('SU5JcGF5VGVzdA==');
                    $this->settings['merchant_id'] = base64_decode('SU5JcGF5VGVzdA==');
                    update_option( 'woocommerce_'.$this->id.'_settings', $tmparr );
                    return false; 
                }
                return true;
            }
            return false;   
        }
    
        function is_valid_for_key(){
            $key = get_option('_codem_inicis_activate_key');
            if(empty($key)) {
                return false;
            }       
            $url = base64_decode("aHR0cDovL3d3dy53b3JkcHJlc3NzaG9wLmNvLmtyL2FjdGl2YXRlLWNoZWNr"); 
            $response = wp_remote_post( $url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'action' => 'activate-check', 'activate_key' => $key ),
                )
            );
            
            if ( is_wp_error( $response ) ) {
               $error_message = $response->get_error_message();
               echo '<div id="message" class="error fade"><p><strong>' . __( '플러그인 인증 체크 오류 : ', 'inicis_payment' ) . $error_message . '</strong></p></div>';
            } else {
               if( $response['body'] == '1' ) {
                   return true;
               } else {
                   return false;
               }  
            }
        }

        public function admin_options() {
            global $woocommerce, $inicis_payment;
            
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'thickbox' );
            wp_register_script( 'ifw-upload', $inicis_payment->plugin_url() . '/assets/js/ifw_admin_upload.js', array( 'jquery', 'media-upload', 'thickbox' ) );
            wp_enqueue_script( 'ifw-upload' );
            wp_enqueue_style( 'thickbox' ); 
            
            if ( isset( $this->method_description ) && $this->method_description != '' ) {
                $tip = '<img class="help_tip" data-tip="' . esc_attr( $this->method_description ) . '" src="' . $woocommerce->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
            } else { 
                $tip = '';
            }   
            
            if(!empty($_POST['woocommerce_'.$this->id.'_merchant_id'])) {
                $mid = trim($_POST['woocommerce_'.$this->id.'_merchant_id']);
                if(!$this->check_mid($mid)) {
                    echo '<div id="message" class="error fade"><p><strong>' . __( '상점 아이디가 정확하지 않습니다. 상점 아이디를 확인하여 주세요. 문제가 계속 된다면 메뉴얼 또는 <a href="http://www.wordpressshop.co.kr" target="_blank">http://www.wordpressshop.co.kr</a> 사이트에 문의하여 주세요.', 'inicis_payment' ) . '</strong></p></div>';
                }
            }
            ?>
            <h3><?php echo $this->method_title; echo $tip;?></h3>

            <?php
            if( !$this->is_valid_for_key() ) {
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
                            msg = msg + '<?php _e( '- 이니시스 사이트 가입시 인증하셨던 이메일을 정확하게 입력해주세요.', 'inicis_payment' ); ?>\n'; 
                        }
                        if(jQuery("#p_activate_key").val() != "" ) { 
                            pflag = true; 
                        } else { 
                            pflag = false; 
                            msg = msg + '<?php _e( '- 이니시스 사이트 가입후에 프로필에서 확인할수 있는 플러그인 인증키를 입력해주세요.', 'inicis_payment' ); ?>\n';
                        }
                        if(pflag) {
                            return true;
                        } else {
                            alert("<?php _e( '다음 사항을 확인해주세요!', 'inicis_payment' ); ?>\n\n" + msg);
                            return false; 
                        }
                        return false;
                    }
                </script>
                </form>
                <div class="inline error">
                    <p><strong><?php _e( '플러그인 인증 필요', 'inicis_payment' ); ?></strong><br/><?php _e( 'http://wordpressshop.co.kr 사이트에서 인증키를 발급받아 등록하여 주세요.<br/>플러그인을 비활성화 할경우 다시 재인증 받으셔야 합니다.', 'inicis_payment' ); ?></p>
                    <p>
                        <form name="frm_activate" id="frm_activate" method="post" action="" enctype="multipart/form-data" onsubmit="return frmcheck()">
                            <label style="width: 120px;display: inline-block;"><?php _e( '회원 이메일 주소', 'inicis_payment' ); ?></label><input type="text" name="p_email" id="p_email" value=""><br/>
                            <label style="width: 120px;display: inline-block;"><?php _e( '플러그인 인증키', 'inicis_payment' ); ?></label><input type="text" name="p_activate_key" id="p_activate_key" value=""><br/>
                            <input type="hidden" name="siteurl" value="<?php echo home_url(); ?>"><br/>
                            <?php wp_nonce_field('woocommerce-settings') ?>
                            <input type="submit" class="button-primary" value="<?php _e( '인증하기', 'inicis_payment' ); ?>">
                        </form>
                    </p>
                </div>

            <?php } else {
                if( $this->is_valid_for_use() ) {
                    $this->generate_pg_notice(); ?>
                <table class="form-table">
                <?php $this->generate_settings_html(); ?>
                    <tr>
                        <th scope="row"><label for="activate-key"><?php _e( '플러그인 개인 인증키', 'inicis_payment' ); ?></label></th>
                        <td><span style="font-weight: bold;font-size:16px;"><?php echo get_option('_codem_inicis_activate_key'); ?></span><br/>
                            <span class="description"><?php _e('인증상태 : ', 'inicis_payment'); ?><?php if($this->is_valid_for_key()) { echo __( '정상', 'inicis_payment' ); } else { echo __( '비인증상태', 'inicis_payment' ); } ?></span></td>
                    </tr>
                </table>
                <?php                            
                }
                
                if( !$this->is_valid_for_use() ) { ?>  
                <div class="inline error"><p><strong><?php _e( '해당 결제 방법 비활성화', 'inicis_payment' ); ?></strong>: <?php _e( '이니시스 결제는 KRW, USD 이외의 통화로는 결제가 불가능합니다. 상점의 통화(Currency) 설정을 확인해주세요.', 'inicis_payment' ); ?></p></div>
                <?php    
                }
            }
        }   

        function generate_pg_notice(){
            if(isset($_GET['noti_close'])) {
                if($_GET['noti_close'] == '1') {
                    update_option('inicis_notice_close', '1');
                } else if($_GET['noti_close'] == '0') {
                    update_option('inicis_notice_close', '0');
                }   
            }    
        
            $css = '';
            if(get_option('inicis_notice_close') == '1') {
                $css = 'display:none;';
                $admin_noti_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_'.$this->id.'&noti_close=0');
                $admin_noti_txt = __('열기', 'inicis_payment');
            }else{
                $css = '';
                $admin_noti_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_'.$this->id.'&noti_close=1');
                $admin_noti_txt = __('닫기', 'inicis_payment');
            }
            ?>
            <div id="welcome-panel" class="welcome-panel" style="padding-top:15px;">
                <div class="welcome-panel-content">
                    <h3 style="font-size:16px;font-weight:bold;margin-bottom: 15px;"><?php _e('공지사항', 'inicis_payment'); ?></h3>
                    <a class="welcome-panel-close" style="padding-top:15px;" href="<?php echo $admin_noti_url; ?>"><?php echo $admin_noti_txt; ?></a>
                    <div class="tab_contents" style="line-height:16px;<?php echo $css; ?>">
                        <ul>
            <?php
                $url = "http://www.wordpressshop.co.kr/category/pg_notice/feed";
                $response = wp_remote_get($url);
                $xmldata = new SimpleXMLElement($response['body']);
                $limit = 5;
                $maxitem = count($xmldata->channel->item);
                if($maxitem <= 0) {
                    echo '
                    <li style="font-size:12px;">
                        <span>' . __( '아직 공지사항이 없거나 데이터를 가져오지 못했습니다. 페이지를 새로고침 하여 주시기 바랍니다.', 'inicis_payment') . '</span>
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

        public function init_form_fields() {
            global $inicis_payment;
            
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('사용', 'inicis_payment'), 
                    'type' => 'checkbox', 
                    'label' => $this->title, 
                    'default' => 'no'
                    ), 
                'title' => array(
                    'title' => __('결제모듈 이름', 'inicis_payment'), 
                    'type' => 'text', 
                    'description' => __('사용자들이 체크아웃(결제진행)시에 나타나는 이름으로 사용자들에게 보여지는 이름입니다.', 'inicis_payment'), 
                    'default' => $this->title, 
                    'desc_tip' => true, 
                    ), 
                'description' => array(
                    'title' => __('결제모듈 설명', 'inicis_payment'), 
                    'type' => 'textarea', 
                    'description' => __('사용자들이 체크아웃(결제진행)시에 나타나는 설명글로 사용자들에게 보여지는 내용입니다.', 'inicis_payment'), 
                    'default' => $this->description, 
                    'desc_tip' => true, 
                    ), 
                'libfolder' => array(
                    'title' => __('이니페이 설치 경로', 'inicis_payment'), 
                    'type' => 'text', 
                    'description' => __('이니페이 설치 경로 안에 key 폴더(키파일)와 log 폴더(로그)가 위치한 경로를 입력해주세요. 키파일 폴더와 로그 폴더의 권한 설정은 가이드를 참고해주세요. <br><br><span style="color:red;font-weight:bold;">주의! 사용하시는 호스팅이나 서버 상태에 따라서 웹상에서 접근 불가능한 경로에 업로드 하시고 절대경로 주소를 입력해주세요. 웹상에서 접근 가능한 경로에 폴더가 위치한 경우 키파일 및 로그 파일 노출로 인한 보안사고가 발생할 수 있으며 이 경우 발생하는 문제는 상점의 책임입니다.</span>', 'inicis_payment'), 
                    'default' => WP_CONTENT_DIR . '/inicis/', 
                    'desc_tip' => true, 
                    ), 
                'merchant_id' => array(
                    'title' => __('상점 아이디', 'inicis_payment'), 
                    'class' => 'chosen_select',
                    'type' => 'select',
                    'options' => $this->get_keyfile_list(),
                    'description' => __('이니시스 상점 아이디(MID)를 선택하세요.', 'inicis_payment'), 
                    'default' => __('INIpayTest', 'inicis_payment'), 
                    'desc_tip' => true, 
                    ), 
                'merchant_pw' => array(
                    'title' => __('키파일 비밀번호', 'inicis_payment'), 
                    'type' => 'password', 
                    'description' => __('키파일 비밀번호를 입력해주세요. 기본값은 1111 입니다. ', 'inicis_payment'), 
                    'default' => __('1111', 'inicis_payment'), 
                    'desc_tip' => true, 
                    ), 
                'possible_refund_status_for_mypage' => array(
                    'title' => __('사용자 주문취소 가능상태', 'inicis_payment'), 
                    'type' => 'ifw_order_status',
                    'description' => __('이니시스 결제건에 한해서, 사용자가 My-Account 메뉴에서 주문취소 요청을 할 수 있는 주문 상태를 지정합니다.', 'inicis_payment'),
                    'default' => array('processing'), 
                    'desc_tip' => true, 
                    ),  
                'possible_refund_status_for_admin' => array(
                    'title' => __('관리자 주문취소 가능상태', 'inicis_payment'), 
                    'type' => 'ifw_order_status',
                    'description' => __('이니시스 결제건에 한해서, 관리자가 관리자 페이지 주문 상세 페이지에서 환불 처리를 할 수 있는 주문 상태를 지정합니다.', 'inicis_payment'),
                    'default' => array('processing'), 
                    'desc_tip' => true, 
                    ), 
                'order_status_after_payemnt' => array(
                    'title' => __('결제완료시 변경될 주문상태', 'inicis_payment'), 
                    'class' => 'chosen_select',
                    'type' => 'select',
                    'options' => $this->get_order_status_list( array( 'cancelled', 'completed', 'failed', 'on-hold', 'refunded' ) ),
                    'default' => 'processing',
                    'desc' => __('이니시스 플러그인을 통한 결제건에 한해서, 결제후 주문접수가 완료된 경우 해당 주문의 상태를 지정하는 옵션입니다.', 'inicis_payment'), 
                    ),
                'order_status_after_refund' => array(
                    'title' => __('환불처리시 변경될 주문상태', 'inicis_payment'), 
                    'class' => 'chosen_select',
                    'type' => 'select',
                    'options' => $this->get_order_status_list( array('completed','on-hold','pending','processing') ),
                    'default' => 'cancelled',
                    'desc' => __('이니시스 플러그인을 통한 결제건에 한해서, 사용자의 환불처리가 승인된 경우 해당 주문의 상태를 지정하는 옵션입니다.','inicis_payment'),
                    ),                    
                'logo_upload' => array(
                    'title' => __('결제 PG 로고', 'inicis_payment'), 
                    'type' => 'ifw_logo_upload', 
                    'description' => __('로고를 업로드 및 선택해 주세요. 128 x 40 pixels 사이즈로 지정해주셔야 하며, gif/jpg/png 확장자가 지원됩니다. 투명배경은 허용되지 않습니다. ', 'inicis_payment'),
                    'default' => WP_CONTENT_URL . '/inicis/img/codemshop_logo_pg.jpg', 
                    'desc_tip' => true, 
                    ), 
                'keyfile_upload' => array(
                    'title' => __('키파일 업로드', 'inicis_payment'), 
                    'type' => 'ifw_keyfile_upload', 
                    'description' => __('상점 키파일을 업로드 해주세요.', 'inicis_payment'), 
                    'desc_tip' => true, 
                    ), 
            );
        }

        function get_keyfile_list() {

            if( empty( $this->settings['libfolder'] ) ) {
                $library_path = WP_CONTENT_DIR . '/inicis';
            } else {
                $library_path = $this->settings['libfolder'];
            }

            $dirs = glob( $library_path . '/key/*', GLOB_ONLYDIR);
            if( count($dirs) > 0 ) {
                $result = array();
                foreach ($dirs as $val) {
                    $tmpmid = substr( basename($val), 0, 3 );
                    if( ($tmpmid == base64_decode("SU5J") || $tmpmid == base64_decode("Q09E") || $mid == base64_decode("Y29kZW1zdG9yeQ==") ) )  {
                        if ( file_exists( $val . '/keypass.enc' )  && file_exists( $val . '/mcert.pem' ) && file_exists( $val . '/mpriv.pem' ) && file_exists( $val . '/readme.txt' )) {
                            $result[basename($val)] = basename($val);    
                        }
                    }
                }
                return $result;         
            } else {
                return array( -1 => __( '=== 키파일을 업로드 해주세요 ===', 'inicis_payment' ) );
            }
        }       

        function get_order_status_list($except_list) {
            $shop_order_status = get_terms(array('shop_order_status'), array('hide_empty' => false));

            $reorder = array();
            foreach ($shop_order_status as $key => $value) {
                $reorder[$value->slug] = $value->name;
            }

            foreach ($except_list as $val) {
                unset($reorder[$val]);
            }
            
            return $reorder;
        }
        
        function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_inicis_supported_currencies', array( 'USD', 'KRW' ) ) ) ) 
                return false;
    
            return true;
        }
        
        function cancel_request($tid, $msg, $code="1"){
            global $woocommerce;
    
            require($this->settings['libfolder']."/libs/INILib.php");
            $inipay = new INIpay50();
            
            $inipay->SetField("inipayhome", $this->settings['libfolder']);
            $inipay->SetField("type", "cancel");
            $inipay->SetField("debug", "true");
            $inipay->SetField("mid", $this->merchant_id);
            $inipay->SetField("admin", $this->merchant_pw);                            
            $inipay->SetField("tid", $tid);
            $inipay->SetField("cancelmsg", $_REQUEST['msg']);
        
            if($code != ""){
                $inipay->SetField("cancelcode", $code);
            }
    
            $inipay->startAction();
            
            if($inipay->getResult('ResultCode') == "00"){
                return "success";
            }else{
                return $inipay->getResult('ResultMsg');
            }
        }
   
        function successful_request_pc( $posted ) {
            global $woocommerce;
            
            if( !file_exists($this->settings['libfolder'] . "/libs/INILib.php" ) ) {
                die('<span style="color:red;font-weight:bold;">' . __( '에러 : 상점 키파일 설정에 문제가 있습니다. 사이트 관리자에게 문의하여 주십시오.', 'inicis_payment' ) . '</span>');
                wc_add_notice( __( '상점 키파일 설정에 문제가 있습니다. 사이트 관리자에게 문의하여 주십시오.', 'inicis_payment' ), 'error' );
            }
            require ($this->settings['libfolder'] . "/libs/INILib.php");
            
            
            if(isset($_REQUEST['txnid']))
            {
                $txnid = $_REQUEST['txnid'];
                $userid = get_current_user_id();
                $orderid = explode('_', $_REQUEST['txnid']);
                $orderid = (int)$orderid[0];
                $order = new WC_Order($orderid);
                
                if( $order->get_order($orderid) == false ){
                    wc_add_notice( __( '유효하지않은 주문입니다.', 'inicis_payment'), 'error' );
                    return;
                }
                
                $productinfo = $this->make_product_info($order);
                $order_total = $order->get_order_total();
            
                if($order->status != 'on-hold' && $order->status != 'pending' && $order->status != 'failed'){
                    wc_add_notice( __('주문에 따른 결제대기 시간 초과로 결제가 완료되지 않았습니다. 다시 주문을 시도 해 주세요.', 'inicis_payment'), 'error' );
                    $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 상태(%s)가 유효하지 않습니다.</font>', 'inicis_payment' ), $txnid, __($order->status, 'woocommerce') ) );  
                    return;
                }
                
                if($this->validate_txnid($order, $txnid) == false){
                    wc_add_notice( sprintf( __( '유효하지 않은 주문번호(%s) 입니다.', 'inicis_payment' ), $txnid ), 'error' );
                    $order->add_order_note( sprintf( __( '<font color="red">유효하지 않은 주문번호(%s) 입니다.</font>', 'inicis_payment' ), $txnid ) );  
                    return;
                }
                
                $checkhash = hash('sha512', "$this->merchant_id|$txnid|$userid|$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||");
                
                if($_REQUEST['hash'] != $checkhash){
                    wc_add_notice( sprintf( __('주문요청(%s)에 대한 위변조 검사 오류입니다.', 'inicis_payment' ), $txnid ), 'error' );
                    $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 위변조 검사 오류입니다.</font>', 'inicis_payment' ), $txnid ) );  
                    return;
                }
                
                $ini_rn = get_post_meta($order->id, "ini_rn", true);
                $ini_enctype = get_post_meta($order->id, "ini_enctype", true);    
                
                $inipay = new INIpay50();
                $inipay->SetField("inipayhome", $this->settings['libfolder']);
                $inipay->SetField("type", "securepay");
                $inipay->SetField("pgid", "INIphp". $pgid);
                $inipay->SetField("subpgip","203.238.3.10");
                $inipay->SetField("admin", $this->merchant_pw);
                $inipay->SetField("debug", "true");
                $inipay->SetField("uid", $uid);
                $inipay->SetField("goodname", iconv("UTF-8", "EUC-KR", $goodname));
                $inipay->SetField("currency", $currency);
                $inipay->SetField("mid", $this->merchant_id);
                $inipay->SetField("price", $order->get_order_total());
                $inipay->SetField("rn", $ini_rn);
                $inipay->SetField("enctype", $ini_enctype);
                $inipay->SetField("buyername", iconv("UTF-8", "EUC-KR", $buyername)); 
                $inipay->SetField("buyertel",  $buyertel);
                $inipay->SetField("buyeremail",$buyeremail);
                $inipay->SetField("paymethod", $paymethod);
                $inipay->SetField("encrypted", $encrypted);
                $inipay->SetField("sessionkey",$sessionkey);
                $inipay->SetField("url", home_url());
                $inipay->SetField("cardcode", $cardcode);
                $inipay->SetField("parentemail", $parentemail);
                $inipay->SetField("recvname",$recvname);
                $inipay->SetField("recvtel",$recvtel);
                $inipay->SetField("recvaddr",$recvaddr);
                $inipay->SetField("recvpostnum",$recvpostnum);
                $inipay->SetField("recvmsg",$recvmsg);
                $inipay->SetField("joincard",$joincard);
                $inipay->SetField("joinexpire",$joinexpire);
                $inipay->SetField("id_customer",$id_customer);
        
                $inipay->startAction();

                try
                {
                    if($inipay->GetResult('ResultCode') != "00"){
                        wc_add_notice( sprintf( __( '결제 승인 요청 과정에서 오류가 발생했습니다. 관리자에게 문의해주세요. 오류코드(%s), 오류메시지(%s)', 'inicis_payment' ), $inipay->GetResult('ResultCode'), $inipay->GetResult('ResultMsg') ), 'error');
                        $order->add_order_note( sprintf( __('<font color="red">결제 승인 요청 과정에서 오류가 발생했습니다. 오류코드(%s), 오류메시지(%s)</font>', 'inicis_payment' ),  $inipay->GetResult('ResultCode'), $inipay->GetResult('ResultMsg') ) );  
                        return;
                    }

                    $order->payment_complete();
                                                                
                    add_post_meta($orderid, "inicis_paymethod", $paymethod);
                    add_post_meta($orderid, "inicis_paymethod_tid", $inipay->GetResult('TID'));

                    wc_add_notice( __( '결제가 정상적으로 완료되었습니다.', 'inicis_payment'), 'success' );
                    $order->add_order_note( sprintf( __( '주문이 완료되었습니다. 결제방법 : %s, 이니시스 거래번호(TID) : <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>, 몰 고유 주문번호 : %s', 'inicis_payment'), $paymethod,$inipay->GetResult('TID'), $inipay->GetResult('MOID') ) );  

                    $woocommerce->cart->empty_cart();
                }
                catch(Exception $e)
                {
                    $msg = "Error";
                }
                
                delete_post_meta($orderid, "ini_rn");
                delete_post_meta($orderid, "ini_enctype");
                delete_post_meta($orderid, 'txnid');
            }else{
                wc_add_notice( __( 'Invalid Request. (ERROR: 0xF54D)', 'inicis_payment' ), 'error' );
            }
        }
          
        function successful_request_mobile_next( $posted ) {
            global $woocommerce;
            
            if (!file_exists($this->settings['libfolder'] . "/libs/INImx.php")) {
                die( __('<span style="color:red;font-weight:bold;">에러 : 상점 키파일 설정에 문제가 있습니다. 사이트 관리자에게 문의하여 주십시오.</span>', 'inicis_payment') );
                wc_add_notice( __( '상점 키파일 설정에 문제가 있습니다. 사이트 관리자에게 문의하여 주십시오.', 'inicis_payment' ), 'error' );
            }
            require ($this->settings['libfolder'] . "/libs/INImx.php");
            
            $notification = $this->decrypt_notification($_POST['P_NOTI']);
            if( empty($notification) ){
                wc_add_notice( __( '유효하지않은 주문입니다.', 'inicis_payment' ), 'error' );
                return;
            }
                
            $txnid = $notification->txnid;
            $hash = $notification->hash;
            
            if( $_REQUEST['P_STATUS'] == '00' && !empty($txnid) )
            {
                $userid = get_current_user_id();
                $orderid = explode('_', $txnid);
                $orderid = (int)$orderid[0];
                $order = new WC_Order($orderid);
                
                if( empty($order) || !is_numeric($orderid) || $order->get_order($orderid) == false ){
                    wc_add_notice( __( '유효하지않은 주문입니다.', 'inicis_payment' ), 'error' );
                    return;
                }
                
                $productinfo = $this->make_product_info($order);
                $order_total = $order->get_order_total();

                if($order->status != 'on-hold' && $order->status != 'pending' && $order->status != 'failed'){
                    wc_add_notice( __( '주문에 따른 결제대기 시간 초과로 결제가 완료되지 않았습니다. 다시 주문을 시도 해 주세요.', 'inicis_payment' ), 'error' );
                    $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 상태(%s)가 유효하지 않습니다.</font>', 'inicis_payment' ), $txnid, __($order->status, 'woocommerce') ) );  
                    return;
                }
                
                if($this->validate_txnid($order, $txnid) == false){
                    wc_add_notice( sprintf( __('유효하지 않은 주문번호(%s) 입니다.', 'inicis_payment' ), $txnid), 'error' );
                    $order->add_order_note( sprintf( __('<font color="red">유효하지 않은 주문번호(%s) 입니다.</font>', 'inicis_payment' ), $txnid) );  
                    return;
                }
                
                $checkhash = hash('sha512', "$this->merchant_id|$txnid||$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||");
                
                if($hash != $checkhash){
                    wc_add_notice( sprintf( __( '주문요청(%s)에 대한 위변조 검사 오류입니다.', 'inicis_payment' ), $txnid ), 'error' );
                    $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 위변조 검사 오류입니다.</font>', 'inicis_payment' ), $txnid) );  
                    return;
                }
                                
                $inimx = new INImx();
                $inimx->reqtype             = "PAY";
                $inimx->inipayhome          = $this->settings['libfolder'];
                $inimx->id_merchant         = $this->merchant_id;
                $inimx->status              = $P_STATUS;
                $inimx->rmesg1              = $P_RMESG1;
                $inimx->tid                 = $P_TID;
                $inimx->req_url             = $P_REQ_URL;
                $inimx->noti                = $P_NOTI;
                $inimx->startAction();
                $inimx->getResult();
                
                try
                {
                    if($inimx->m_resultCode != "00"){
                        wc_add_notice( sprintf( __( '결제 승인 요청 과정에서 오류가 발생했습니다. 관리자에게 문의해주세요. 오류코드(%s), 오류메시지(%s)', 'inicis_payment' ), $inimx->m_resultCode, $inimx->m_resultMsg), 'error' );
                        $order->add_order_note( sprintf( __('<font color="red">결제 승인 요청 과정에서 오류가 발생했습니다. 오류코드(%s), 오류메시지(%s)</font>', 'inicis_payment' ), $inimx->m_resultCode, $inimx->m_resultMsg) );  
                        return;
                    }
                    
                    $inimx_txnid = $inimx->m_moid;
                    $inimx_orderid = explode('_', $inimx_txnid);
                    $inimx_orderid = (int)$inimx_orderid[0];
                
                    if( $txnid != $inimx_txnid || $orderid != $inimx_orderid ){
                        wc_add_notice( __( '주문요청에 대한 위변조 검사 오류입니다. 관리자에게 문의해주세요.', 'inicis_payment' ), 'error' );
                        $order->add_order_note( sprintf( __( '<font color="red">주문요청(%s, %s, %s, %s)에 대한 위변조 검사 오류입니다. 결재는 처리되었으나, 결재요청에 오류가 있습니다. 이니시스 결재내역을 확인하신 후, 고객에게 연락을 해주시기 바랍니다.</font>', 'inicis_payment' ), $txnid, $inimx_txnid, $orderid, $inimx_orderid ) );  
                        return;
                    }

                    $order->payment_complete(); 

                    add_post_meta($orderid, "inicis_paymethod", $inimx->m_payMethod);
                    add_post_meta($orderid, "inicis_paymethod_tid",  $inimx->m_tid);

                    $order->add_order_note( sprintf( __( '주문이 완료되었습니다. 결제방법 : [모바일] %s, 이니시스 거래번호(TID) : <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>, 몰 고유 주문번호 : %s', 'inicis_payment'), $inimx->m_payMethod, $inimx->m_tid, $inimx->m_moid ) );
                    
                    $woocommerce->cart->empty_cart();
                }
                catch(Exception $e)
                {
                    $msg = "Error";
                }
                
                delete_post_meta($orderid, "ini_rn");
                delete_post_meta($orderid, "ini_enctype");
                delete_post_meta($orderid, 'txnid');
            }else{
                wc_add_notice( __('Invalid Request. (ERROR: 0xF54D)', 'inicis_payment' ), 'error' );
            }
        }
        
        function successful_request_mobile_noti( $posted ) {
            global $woocommerce;
            
            $this->inicis_noti_print_log(print_r($_REQUEST, TRUE));
            
            $PGIP = $_SERVER['REMOTE_ADDR'];
            if($PGIP == "211.219.96.165" || $PGIP == "118.129.210.25")
            {
                $P_TID;                 
                $P_MID;                 
                $P_AUTH_DT;             
                $P_STATUS;              
                $P_TYPE;                
                $P_OID;                 
                $P_FN_CD1;              
                $P_FN_CD2;              
                $P_FN_NM;               
                $P_AMT;                 
                $P_UNAME;               
                $P_RMESG1;              
                $P_RMESG2;              
                $P_NOTI;                
                $P_AUTH_NO;             
    
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
    
                if($P_TYPE == "VBANK")
                {
                    if($P_STATUS != "02")
                    {
                        echo "OK";
                        return;
                    }
                }
    
                $notification = $this->decrypt_notification($_POST['P_NOTI']);
                if( empty($notification) ){
                    $this->inicis_noti_print_log( __( '유효하지않은 주문입니다. (invalid notification)', 'inicis_payment' ) );
                    echo "FAIL";
                    exit();
                }
                    
                $txnid = $notification->txnid;
                $hash = $notification->hash;
                
                if( $_REQUEST['P_STATUS'] == '00' && !empty($txnid) )
                {
                    $userid = get_current_user_id();
                    $orderid = explode('_', $txnid);
                    $orderid = (int)$orderid[0];
                    $order = new WC_Order($orderid);

                    if( empty($order) || !is_numeric($orderid) || $order->get_order($orderid) == false ){
                        $this->inicis_noti_print_log( __( '유효하지않은 주문입니다. (invalid orderid)', 'inicis_payment' ) );
                        echo "FAIL";
                        exit();
                    }
                    
                    $productinfo = $this->make_product_info($order);
                    $order_total = $order->get_order_total();

                    if($order->status != 'on-hold' && $order->status != 'pending' && $order->status != 'failed'){
                        $this->inicis_noti_print_log( sprintf( __('주문요청(%s)에 대한 상태(%s)가 유효하지 않습니다.', 'inicis_payment' ), $txnid, __($order->status, 'woocommerce')));
                        $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 상태(%s)가 유효하지 않습니다.</font>', 'inicis_payment' ), $txnid, __($order->status, 'woocommerce')));
                        $rst = $this->cancel_request($_REQUEST['P_TID'], __('주문시간 초과오류 : 자동결재취소', 'inicis_payment'), __('CM_CANCEL_100', 'inicis_payment') );  
                        if($rst == "success"){
                            $order->add_order_note( sprintf( __('<font color="red">[결재알림]</font>주문시간 초과오류건(%s)에 대한 자동 결제취소가 진행되었습니다.', 'inicis_payment'), $_REQUEST['P_TYPE']) );
                            update_post_meta($order->id, '_codem_inicis_order_cancelled', TRUE);
                        } else {
                            $order->add_order_note( sprintf( __('<font color="red">주문시간 초과오류건(%s)에 대한 자동 결제취소가 실패했습니다.</font>', 'inicis_payment'), $_REQUEST['P_TYPE']) );
                        }
                        echo "FAIL";
                        exit();
                    }
                    
                    if($this->validate_txnid($order, $txnid) == false){
                        $this->inicis_noti_print_log( sprintf( __( '유효하지 않은 주문번호(%s) 입니다', 'inicis_payment'), $txnid) );
                        $order->add_order_note( sprintf( __('<font color="red">유효하지 않은 주문번호(%s) 입니다.</font>', 'inicis_payment'), $txnid) );  
                        echo "FAIL";
                        exit();
                    }
                    
                    $checkhash = hash('sha512', "$this->merchant_id|$txnid||$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||");
                    
                    if($hash != $checkhash){                
                        $this->inicis_noti_print_log("$this->merchant_id|$txnid||$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||");
                        $this->inicis_noti_print_log( sprintf( __( '주문요청(%s)에 대한 위변조 검사 오류입니다.', 'inicis_payment'), $txnid) );  
                        $order->add_order_note( sprintf( __('<font color="red">주문요청(%s)에 대한 위변조 검사 오류입니다.</font>', 'inicis_payment'), $txnid) );  
                        echo "FAIL";
                        exit();
                    }
                                    
                    $inimx_txnid = $_REQUEST['P_OID'];
                    $inimx_orderid = explode('_', $inimx_txnid);
                    $inimx_orderid = (int)$inimx_orderid[0];
                    
                    if( $txnid != $inimx_txnid || $orderid != $inimx_orderid ){
                        $this->inicis_noti_print_log( sprintf( __( '주문요청(%s, %s, %s, %s)에 대한 위변조 검사 오류입니다. 결재는 처리되었으나, 결재요청에 오류가 있습니다. 이니시스 결재내역을 확인하신 후, 고객에게 연락을 해주시기 바랍니다.', 'inicis_payment' ), $txnid, $inimx_txnid, $orderid, $inimx_orderid) );
                        $order->add_order_note( sprintf( __('<font color="red">주문요청(%s, %s, %s, %s)에 대한 위변조 검사 오류입니다. 결재는 처리되었으나, 결재요청에 오류가 있습니다. 이니시스 결재내역을 확인하신 후, 고객에게 연락을 해주시기 바랍니다.</font>', 'inicis_payment' ), $txnid, $inimx_txnid, $orderid, $inimx_orderid) );  
                        echo "FAIL";
                        exit();
                    }
    
                    $order->payment_complete(); 

                    add_post_meta($orderid, "inicis_paymethod", $_REQUEST['P_TYPE']);
                    add_post_meta($orderid, "inicis_paymethod_tid",  $_REQUEST['P_TID']);

                    $this->inicis_noti_print_log( sprintf( __( '주문이 완료되었습니다. 결제방법 : [모바일] %s, 이니시스 거래번호(TID) : %s, 몰 고유 주문번호 : %s', 'inicis_payment'), $_REQUEST['P_TYPE'], $_REQUEST['P_TID'], $_REQUEST['P_OID'] ) );
                    $order->add_order_note( sprintf( __( '주문이 완료되었습니다. 결제방법 : [모바일] %s, 이니시스 거래번호(TID) : <a href="https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=%s" target=_blank>[영수증 확인]</a>, 몰 고유 주문번호 : %s', 'inicis_payment'),$_REQUEST['P_TYPE'], $_REQUEST['P_TID'], $_REQUEST['P_OID'] ) );
                    
                    $woocommerce->cart->empty_cart();
                    
                    delete_post_meta($orderid, "ini_rn");
                    delete_post_meta($orderid, "ini_enctype");
                    delete_post_meta($orderid, 'txnid');
                    
                    echo "OK";
                    exit();
                }else{
                    $this->inicis_noti_print_log( __( '유효하지않은 주문입니다. (invalid status or txnid)', 'inicis_payment') );
                    echo "FAIL";
                    exit();
                }   
            }
        }        
        
        function successful_request_mobile_return( $posted ) {
        }
       
        
        function process_payment($orderid){
    
            global $woocommerce;
    
            $order = new WC_Order($orderid);
            
            //WooCommerce Version Check
            if(version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' )) { 
                return array(
                    'result'    => 'success',
                    'redirect'  => $order->get_checkout_payment_url( true ),
                    'order_id'  => $order->id,
                    'order_key' => $order->order_key
                );
            } else { 
                return array(
                    'result' => 'success', 
                    'redirect' => add_query_arg('order',$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id')))),
                    'order_id'  => $order->id,
                    'order_key' => $order->order_key
                );
            }
        }
        
        function receipt_page( $order ) {
        }
        
        function encrypt_notification($data, $hash) {
            $param = array(
                'txnid' => $data,
                'hash' => $hash 
            );
            
            return aes256_cbc_encrypt("inicis-for-woocommerce", json_encode($param), "codemshop" );
        }

        function decrypt_notification($data) {
            return json_decode(aes256_cbc_decrypt("inicis-for-woocommerce", $data, "codemshop" ));
        }
        
        function make_txnid($order) {
            $txnid = $order->id . '_' . date("ymd") . '_' . date("his");
            update_post_meta($order->id, 'txnid', $txnid);
            return $txnid;
        }
        
        function validate_txnid($order, $txnid) {
            $org_txnid = get_post_meta($order->id, 'txnid', true);
            return $org_txnid == $txnid;
        }
        
        function make_product_info($order) {
            $items = $order->get_items();
            
            if(count($items) == 1){
                $keys = array_keys($items);
                return $items[$keys[0]]['name'];
            }else{
                $keys = array_keys($items);
                return sprintf( __('%s 외 %d건', 'inicis_payment'), $items[$keys[0]]['name'], count($items)-1);
            }
        }
        
        function wp_ajax_generate_payment_form() {
            global $woocommerce, $inicis_payment;
            
            $orderid = $_REQUEST['orderid'];
            
            if (!file_exists($this->settings['libfolder'] . "/libs/INILib.php")) {
                wp_send_json_error( __( '결제오류 : 상점 키파일 설정에 문제가 있습니다. 사이트 관리자에게 문의하여 주십시오.', 'inicis_payment' ) );
            }
            
            try{
                require ($this->settings['libfolder'] . "/libs/INILib.php");
            }catch (Exception $e) {
                wp_send_json_error( __( '결제오류 : 결제 모듈을 불러올 수 없습니다. 사이트 관리자에게 문의하여 주십시오.', 'inicis_payment') . ' [' . $e->getMessage() . ']');
            }
            
            $use_ssl = get_option('woocommerce_force_ssl_checkout');
            
            if( $this->id == 'inicis_card' && !empty( $this->settings ) ) {
                $arr_accept_method = array();    
                if ( $this->settings['cardpoint'] == 'yes' ) {
                    $arr_accept_method[] = 'cardpoint';
                }
                if ( $this->settings['skincolor'] != '' ) {
                    $arr_accept_method[] = $this->settings['skincolor'];
                }
                $acceptmethod = implode( ":", $arr_accept_method );
            } else {
                $acceptmethod = '';
            } 
            
            $userid = get_current_user_id();
            $order = new WC_Order($orderid);
            $txnid = $this->make_txnid($order);
            $productinfo = $this->make_product_info($order);
            $order_total = $order->get_order_total();

            $inipay = new INIpay50();
            $inipay->SetField("inipayhome", $this->settings['libfolder']);
            $inipay->SetField("type", "chkfake");
            $inipay->SetField("debug", "true");
            $inipay->SetField("enctype", "asym");
            $inipay->SetField("admin", $this->merchant_pw);
            $inipay->SetField("checkopt", "false");
            $inipay->SetField("mid", $this->merchant_id);
            $inipay->SetField("price", $order->get_order_total());
            $inipay->SetField("nointerest", $this->settings['nointerest']);
            $inipay->SetField("quotabase", iconv("UTF-8", "EUC-KR", $this->settings['quotabase']));

            $inipay->startAction();

            if ($inipay->GetResult("ResultCode") != "00") {
                wp_send_json_error($inipay->GetResult("ResultMsg"));
            }
            
            update_post_meta($orderid, "ini_rn", $inipay->GetResult("rn"));
            update_post_meta($orderid, "ini_enctype", $inipay->GetResult("enctype"));
            
            if (wp_is_mobile()) {
                $str = "$this->merchant_id|$txnid||$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||";
                $hash = hash('sha512', $str);
                $notification = $this->encrypt_notification($txnid, $hash);
                ob_start();
                include($inicis_payment->plugin_path() . '/templates/payment_form_mobile.php');
                $form_tag = ob_get_clean();
            } else {
                $str = "$this->merchant_id|$txnid|$userid|$order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||";
                $hash = hash('sha512', $str);
                ob_start();
                include($inicis_payment->plugin_path() . '/templates/payment_form_pc.php');
                $form_tag = ob_get_clean();
            }

            wp_send_json_success('<div data-id="mshop-payment-form" style="display:none">' . $form_tag . '</div>');
        }
        
        function successful_request_cancelled( $posted ) {
            global $woocommerce;
    
            require($this->settings['libfolder']."/libs/INILib.php");
            $inipay = new INIpay50();
            
            $inipay->SetField("inipayhome", $_REQUEST['home']);
            $inipay->SetField("type", "cancel");
            $inipay->SetField("debug", "true");
            $inipay->SetField("mid", $_REQUEST['mid']);
            $inipay->SetField("admin", "1111");
            $inipay->SetField("tid", $_REQUEST['tid']);
            $inipay->SetField("cancelmsg", $_REQUEST['msg']);
        
            if($code != ""){
                $inipay->SetField("cancelcode", $_REQUEST['code']);
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

        function check_inicis_payment_response() {
            if (!empty($_REQUEST)) {
                header('HTTP/1.1 200 OK');

                if (!empty($_REQUEST['type'])) {
                    
                    $return_type = explode(',', $_REQUEST['type']);

                    if( $_REQUEST['txnid'] ) {
                        $orderid = explode('_', $_REQUEST['txnid']);
                    } else if( $_POST['P_NOTI'] ) {
                        $notification = $this->decrypt_notification($_POST['P_NOTI']);
                        $orderid = explode('_', $notification->txnid);
                    } else if( $_REQUEST['P_OID'] ) {
                        $orderid = explode('_', $_REQUEST['P_OID']);
                    } else if( $_GET['oid'] ) {
                        $orderid = explode('_', $_GET['oid']);
                    } else if ( $return_type[1] ) {
                        $temp_oid = explode('=', $return_type[1]);
                        $orderid = explode('_', $temp_oid[1]);
                    }
                    
                    if( !empty( $orderid ) ) {
                        $orderid = (int)$orderid[0];
                        $order = new WC_Order($orderid);
                    }
                    
                    switch($return_type[0]) {
                        case "cancelled" :
                            $this->successful_request_cancelled($_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        case "pc" :
                            $this->successful_request_pc($_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        case "mobile_next" :
                            $this->successful_request_mobile_next($_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        case "mobile_noti" :
                            $this->successful_request_mobile_noti($_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        case "mobile_return" :
                            $this->successful_request_mobile_return($_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        case "cancel_payment" :
                            do_action("valid-inicis-request_cancel_payment", $_POST);
                            $this->inicis_redirect_page($order);
                            break;
                        default :
                            wp_die( __( '결제 요청 실패 : 관리자에게 문의하세요!', 'inicis_payment' ) );
                            break;
                    }
                } else {
                    wp_die( __( '결제 요청 실패 : 관리자에게 문의하세요!', 'inicis_payment' ) );
                }
            } else {
                wp_die( __( '결제 요청 실패 : 관리자에게 문의하세요!', 'inicis_payment' ) );
            }
        }
        
        function inicis_redirect_page($order) {
            if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=') ) {
                if( isset( $order ) && !empty( $order ) ) {
                    wp_redirect( $order->get_checkout_order_received_url() );
                } else {
                    $tmp_myaccount_pid = get_option( 'woocommerce_myaccount_page_id', true );
                    if ( empty( $tmp_myaccount_pid ) ) {
                        $myaccount_page = home_url();
                    } else {
                        $myaccount_page = get_permalink( get_option( 'woocommerce_myaccount_page_id', true ) );
                    }
                    wp_redirect( $myaccount_page );
                }
            } else {
                $tmp_myaccount_pid = get_option( 'woocommerce_myaccount_page_id', true );
                if ( empty( $tmp_myaccount_pid ) ) {
                    $myaccount_page = home_url();
                } else {
                    $myaccount_page = get_permalink( get_option( 'woocommerce_myaccount_page_id', true ) );
                }
                wp_redirect( $myaccount_page );
            }            
        }
        
        function inicis_noti_print_log($msg)
        {
            $path = $this->settings['libfolder']."/log/";
            $file = "ININoti" . $this->merchant_id ."_".date("Ymd").".log";
            
            if(!is_dir($path)) 
            {
                mkdir($path, 0755);
            }
            if(!($fp = fopen($path.$file, "a+"))) return 0;
    
            if(fwrite($fp, " ".$msg."\n") === FALSE)
            {
                fclose($fp);
                return 0;
            }
            fclose($fp);
            return 1;
        }       
    }
}