<?php
if (class_exists('WC_Payment_Gateway')) {

	if ( ! class_exists( 'WC_Gateway_Inicis_Card' ) ) {
    
		class WC_Gateway_Inicis_Card extends WC_Gateway_Inicis {
			public function __construct() {
				parent::__construct();
				
				$this->id = 'inicis_card';
				$this->method_title = __('이니시스 카드', 'inicis_payment');
				$this->has_fields = false;
	            $this->method_description = __('이니시스 결제 대행 서비스를 사용하시는 분들을 위한 설정 페이지입니다. 실제 서비스를 하시려면 키파일을 이니시스에서 발급받아 설치하셔야 정상 사용이 가능합니다.', 'inicis_payment');
				
				$this->init_settings();
	            $this->settings['gopaymethod'] = 'card';		
	            $this->settings['paymethod'] = 'wcard';
				
				if( empty($this->settings['title']) ){
					$this->title =  __('이니시스 카드', 'inicis_payment');
					$this->description = __('이니시스 결제대행사를 통해 결제합니다.', 'inicis_payment');
				}else{
					$this->title = $this->settings['title'];
					$this->description = $this->settings['description'];
				}
				
				$this->merchant_id = $this->settings['merchant_id'];
				$this->merchant_pw = $this->settings['merchant_pw'];
				$this->redirect_page_id = $this->settings['redirect_page_id'];

				$this->init_form_fields();
				$this->init_action();			
			}
	
			function init_action() {

				add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_inicis_card_response'));
	
				if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'), 20);
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_activate'), 10);
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_activate_check'), 10);
				} else {
					add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'), 20);
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_activate'), 10);
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_activate_check'), 10);
				}
				
				add_filter( 'woocommerce_payment_complete_order_status', array($this, 'woocommerce_payment_complete_order_status' ), 15, 2 );
				add_filter( 'ifw_is_admin_refundable_' . $this->id, array( $this, 'ifw_is_admin_refundable' ), 10, 2 );
				add_action( 'inicis_mypage_cancel_order_' . $this->id, array($this, 'inicis_mypage_cancel_order'), 20 );
				add_action( 'wp_ajax_payment_form_' . $this->id, array( &$this, 'wp_ajax_generate_payment_form' ) );
	        	add_action( 'wp_ajax_nopriv_payment_form_' . $this->id, array( &$this, 'wp_ajax_generate_payment_form' ) );				
				add_action( 'wp_ajax_refund_request_' . $this->id, array( &$this, 'wp_ajax_refund_request' ) );
			}

			function init_form_fields() {
				parent::init_form_fields();
				$this->form_fields = array_merge($this->form_fields, array(
					'quotabase' => array(
						'title' => __('할부 구매 개월수 설정', 'inicis_payment'), 
						'type' => 'text', 
						'description' => __('할부 구매를 허용할 개월수를 설정하세요.<span style="color:red;">(무이자 할부 개월수가 아닙니다)</span><br/>예) 선택:일시불:2개월:3개월:6개월<br/>단, 최소 결제금액이 5만원 이상인 경우에만 할부 결제가 허용됩니다. 지정한 할부 개월수와 상관없이 할부 결제 최소 금액이 아닌 경우 할부 거래가 허용되지 않습니다.', 'inicis_payment'), 
						'default' => __('선택:일시불:3개월:6개월:9개월:12개월', 'inicis_payment'), 
						'desc_tip' => true, 
						), 
					'nointerest' => array(
						'title' => __('무이자 할부 설정', 'inicis_payment'), 
						'type' => 'checkbox', 
						'label' => __('무이자 할부 허용(수수료 상점 부담) ', 'inicis_payment'), 
						'default' => 'no', 
						'description' => __('무이자 할부 허용하시면 무이자 할부에 따른 수수료는 상점에서 부담하게 됩니다. 수수료는 이니시스에 문의하여 주십시오. (단, 이니시스에서 모든 가맹점을 대상으로 하는 무이자 이벤트인 경우는 제외입니다)', 'inicis_payment'), 
						'desc_tip' => true, 
						), 
                    'cardpoint' => array(
                        'title' => __('카드 포인트 결제 허용', 'inicis_payment'), 
                        'type' => 'checkbox', 
                        'label' => __('카드 포인트 결제 허용 여부 ', 'inicis_payment'), 
                        'default' => 'no', 
                        'description' => __('카드 포인트를 결제시에 사용할 수 있도록 허용할 것인지 여부를 지정합니다.', 'inicis_payment'), 
                        'desc_tip' => true, 
                        ), 
                    'skincolor' => array(
                        'title' => __('PG결제 스킨 색상', 'inicis_payment'), 
                        'class' => 'chosen_select',
                        'type' => 'select', 
                        'label' => __('PG결제 스킨 색상 지정 ', 'inicis_payment'),
                        'default' => 'SKIN(BLUE)', 
                        'options' => array( 'SKIN(BLUE)' => __('파랑색', 'inicis_payment'), 'SKIN(GREEN)' => __('초록색', 'inicis_payment'), 'SKIN(PURPLE)' => __('보라색', 'inicis_payment'), 'SKIN(RED)' => __('빨강색', 'inicis_payment'), 'SKIN(YELLOW)' => __('노랑색', 'inicis_payment') ),
                        'description' => __('PG결제 창의 스킨 색상을 지정합니다.', 'inicis_payment'), 
                        'desc_tip' => true, 
                        ), 
				));
			}
		}
		
		if ( defined('DOING_AJAX') ) {
			$ajax_requests = array('payment_form_inicis_card', 'refund_request_inicis_card');
			if( in_array( $_REQUEST['action'], $ajax_requests ) ){
				new WC_Gateway_Inicis_Card();
			}
		}	
	}

} // class_exists function end
