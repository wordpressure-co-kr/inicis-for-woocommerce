<?php 
if( class_exists('WC_Payment_Gateway') ) { 

	if ( ! class_exists( 'WC_Gateway_Inicis_Bank' ) ) {
         
	    class WC_Gateway_Inicis_Bank extends WC_Gateway_Inicis{
	        public function __construct(){
	            $this->id = 'inicis_bank';
	            $this->has_fields = false;
	            
				parent::__construct();
	
	            $this->has_fields = false;
	            $this->countries = array('US', 'KR');
	            $this->method_title = __('이니시스 실시간 계좌이체', 'inicis_payment');
	            $this->method_description = __('이니시스 결제 대행 서비스를 사용하시는 분들을 위한 설정 페이지입니다. 실제 서비스를 하시려면 키파일을 이니시스에서 발급받아 설치하셔야 정상 사용이 가능합니다.', 'inicis_payment');
	    	
	            $this->init_settings();                                         
	            $this->settings['quotabase'] = '일시불';
	            $this->settings['nointerest'] = 'no';
	            $this->settings['gopaymethod'] = 'directbank';
	            $this->settings['paymethod'] = 'bank';
				
				if( empty($this->settings['title']) ){
					$this->title =  __('이니시스 실시간 계좌이체', 'inicis_payment');
					$this->description = __('이니시스 결제대행사를 통해 결제합니다.', 'inicis_payment');
				}else{
					$this->title = $this->settings['title'];
					$this->description = $this->settings['description'];
				}
				
	            $this->merchant_id = $this->settings['merchant_id'];
	            $this->merchant_pw = $this->settings['merchant_pw'];

	            $this->init_form_fields();
	            $this->init_action();
	        }
	
			function init_action() {
				add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_inicis_payment_response'));
               
				if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'), 20);
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_activate'), 10);
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_activate_check'), 10);
				} else {
					add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'), 20);
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_activate'), 10);
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_activate_check'), 10);
				}
                
				add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
				add_filter( 'woocommerce_payment_complete_order_status', array($this, 'woocommerce_payment_complete_order_status' ), 15, 2 );

				add_filter( 'ifw_is_admin_refundable_' . $this->id, array( $this, 'ifw_is_admin_refundable' ), 10, 2 );
				add_action( 'inicis_mypage_cancel_order_' . $this->id, array($this, 'inicis_mypage_cancel_order'), 20 );
				
				add_action( 'wp_ajax_payment_form_' . $this->id, array( &$this, 'wp_ajax_generate_payment_form' ) );
	        	add_action( 'wp_ajax_nopriv_payment_form_' . $this->id, array( &$this, 'wp_ajax_generate_payment_form' ) );
				add_action( 'wp_ajax_refund_request_' . $this->id, array( &$this, 'wp_ajax_refund_request' ) );							
			}

            function thankyou_page() {
                echo __('이니시스 실시간 계좌이체로 결제되었습니다. 감사합니다.', 'inicis_payment');
            }

		}

		if ( defined('DOING_AJAX') ) {
			$ajax_requests = array('payment_form_inicis_bank', 'refund_request_inicis_bank');
			if( in_array( $_REQUEST['action'], $ajax_requests ) ){
				new WC_Gateway_Inicis_Bank();
			}
		}	
	}
	   
} // class_exists function end