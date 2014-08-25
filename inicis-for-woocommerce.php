<?php
/*
Plugin Name: INICIS for WooCommerce
Plugin URI: http://www.codemshop.com
Description: 엠샵에서 개발한 KG 이니시스의 워드프레스 우커머스 이용을 위한 결제 시스템 플러그인 입니다. KG INICIS Payment Gateway Plugin for Wordpress WooCommerce that developed by MShop.
Version: 2.0.4
Author: CODEM(c)
Author URI: http://www.codemshop.com
*/

//소스에 URL로 직접 접근 방지
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'INICIS_Payment_Gateway' ) ) {

    class INICIS_Payment_Gateway {

        protected $slug;
                
        /**
         * @var string
         */
        public $version = '2.0.4';
    
        /**
         * @var string
         */
        public $plugin_url;
    
        /**
         * @var string
         */
        public $plugin_path;
    
        private $_body_classes = array();
        
        /**
         * MShop Constructor.
         *
         * @access public
         * @return void
         */
        public function __construct() {
            $this->load_plugin_textdomain();
            
            add_action( 'init', array( $this, 'init' ), 0 );
            add_action( 'wp_head', array( $this, 'inicis_mypage_cancel_order' ), 0 );           
            add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

            register_activation_hook( __FILE__, array( $this, 'activation_process' ) );
            register_deactivation_hook( __FILE__, array( $this, 'deactivation_process' ) );
        }
        
        function activation_process() {
            global $inicis_payment;
            WP_Filesystem();
             if ( !file_exists( WP_CONTENT_DIR . '/inicis' ) ) {
                $old = umask(0); 
                mkdir( WP_CONTENT_DIR . '/inicis', 0755, true );
                umask($old);

                if ( file_exists( plugin_dir_path(__FILE__) . '/lib' ) ) {
                    rename( plugin_dir_path(__FILE__) . '/lib/', WP_CONTENT_DIR . '/inicis' );
                }
            }
            update_option('ifw_ver', $this->version);
        }
        
        function deactivation_process() {
            delete_option( 'woocommerce_inicis_card_settings' );
            delete_option( 'woocommerce_inicis_bank_settings' );
            delete_option( '_codem_inicis_activate_key' );
            delete_option( 'ifw_ver' );
        }
        
        function plugins_loaded() {
            
            $payment_method_list = array( 'card', 'bank' );

            include_once( 'classes/class-wc-inicis-payment.php' );
            
            foreach( $payment_method_list as $type ) {
                include_once( 'classes/class-wc-inicis-payment-'.$type.'.php' );
            }
        }
        
        public function plugin_url() {
            if ( $this->plugin_url ) 
                return $this->plugin_url;
            
            return $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
        }
    
    
        public function plugin_path() {
            if ( $this->plugin_path ) 
                return $this->plugin_path;
    
            return $this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
        }
        
        function includes() {
            if ( is_admin() )
                $this->admin_includes();
    
            if ( defined('DOING_AJAX') )
                $this->ajax_includes();
    
            if ( ! is_admin() || defined('DOING_AJAX') )
                $this->frontend_includes();
            
        }
    
        public function admin_includes() {
        	global $inicis_payment, $page;
             
            include_once('admin/class-ifw-admin-meta-boxes.php');
        }
        
        public function ajax_includes() {
            
        }
        
        public function frontend_includes() {
            
        }
        
        public function frontend_scripts() {
            if( is_page( 'checkout' ) ) {
                if(wp_is_mobile()){
                    wp_register_script( 'ifw_payment-js', $this->plugin_url() . '/assets/js/ifw_payment.mobile.js' );
                }else{
                    wp_register_script( 'ifw_payment-js', $this->plugin_url() . '/assets/js/ifw_payment.js' );
                }
                wp_enqueue_script( 'ifw_payment-js' );
                wp_localize_script( 'ifw_payment-js', '_ifw_payment', array(
                    'ajax_loader_url' =>  $this->plugin_url() . '/assets/images/ajax_loader.gif'
                ) );

                wp_register_style( 'ifw-style', $this->plugin_url() . '/assets/css/style.min.css' );
                wp_enqueue_style( 'ifw-style' );
            }
        }
        
        public function init() {
            if ( ! is_admin() || defined('DOING_AJAX') ) {}
    
            $this->includes();
            
            add_action( 'wp_head', array( $this, 'inicis_ajaxurl') );
            add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
			add_filter( 'woocommerce_payment_gateways',  array( $this, 'woocommerce_payment_gateways' ) );
			add_filter( 'woocommerce_pay_order_button_html', array($this, 'woocommerce_pay_order_button_html' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'woocommerce_my_account_my_orders_actions' ), 1, 2 );
		}
		
		public function woocommerce_my_account_my_orders_actions($actions, $order){
			global $woocommerce;
			$woocommerce->payment_gateways();
			$payment_method = get_post_meta($order->id, '_payment_method', true);
			return apply_filters('woocommerce_my_account_my_orders_actions_' . $payment_method, $actions, $order);
		}
		
		function inicis_mypage_cancel_order(){
        	if( is_page( 'checkout' ) ) {
				$use_ssl = get_option('woocommerce_force_ssl_checkout');
				if ($use_ssl == 'yes') {
					$secunissl_cross = 'https://plugin.inicis.com/pay61_secunissl_cross.js';
				} else {
					$secunissl_cross = 'http://plugin.inicis.com/pay61_secuni_cross.js';
				}
				
				echo '<script type="text/javascript" src="' . $secunissl_cross . '"></script>';
        	}
			
			global $woocommerce;
			if ( isset( $_GET['inicis-cancel-order'] ) && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
				$woocommerce->payment_gateways();
				$payment_method = get_post_meta( $_GET['order_id'], '_payment_method', true );
				do_action( 'inicis_mypage_cancel_order_' . $payment_method, $_GET['order_id'] );
		        wp_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ));
				die();
			}
		}

		function woocommerce_pay_order_button_html() {
			$orderid = wc_get_order_id_by_order_key($_REQUEST['key']);
			
			if(wp_is_mobile()){
				wp_register_script( 'ifw-pay-for-order', $this->plugin_url() . '/assets/js/ifw_pay_for_order.mobile.js' );
        	}else{
				wp_register_script( 'ifw-pay-for-order', $this->plugin_url() . '/assets/js/ifw_pay_for_order.js' );
        	}
			
		    wp_enqueue_script( 'ifw-pay-for-order' );
			wp_localize_script( 'ifw-pay-for-order', '_ifw_pay_for_order', array(
				'ajax_loader_url' =>  $this->plugin_url() . '/assets/images/ajax_loader.gif',
	            'order_id' => $orderid,
	            'order_key' => $_REQUEST['key']
	            ) );

			$pay_order_button_text = apply_filters( 'woocommerce_pay_order_button_text', __( 'Pay for order', 'woocommerce' ) );
			return '<input type="button" class="button alt" id="place_order" value="' . esc_attr( $pay_order_button_text ) . '" data-value="' . esc_attr( $pay_order_button_text ) . '" />';
		}
		
		function inicis_ajaxurl() {
			?>
			<script type="text/javascript">
			<?php
                $use_ssl = get_option('woocommerce_force_ssl_checkout');
                if ($use_ssl == 'yes') {
                    $html_ajax_url = admin_url('admin-ajax.php', 'https');
                } else {
                    $html_ajax_url = admin_url('admin-ajax.php', 'http');
                }
            ?>

			var ajaxurl = '<?php echo $html_ajax_url; ?>';
			</script>
			<?php
		}
		
	    function woocommerce_payment_gateways( $methods ) {
			$payment_method_list = array( 'card', 'bank' );

			include_once( 'classes/class-wc-inicis-payment.php' );
			
			foreach( $payment_method_list as $type ) {
				include_once( 'classes/class-wc-inicis-payment-'.$type.'.php' );
		        $methods[] = 'WC_Gateway_Inicis_' . ucfirst( $type );
			}

	        return $methods;
	    }		
            
        public function add_body_class( $class ) {
            $this->_body_classes[] = sanitize_html_class( strtolower($class) );
        }
        
        public function output_body_class( $classes ) {
            return $classes;
        }

        public function load_plugin_textdomain() {
            load_plugin_textdomain( 'inicis_payment', false, dirname( plugin_basename(__FILE__) ) . "/languages/" );
        }
    }

    $inicis_payment = new INICIS_Payment_Gateway();
}
