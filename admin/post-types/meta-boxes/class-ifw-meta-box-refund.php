<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class IFW_Meta_Box_Refund {
    /**
     * Output the metabox
     */
    public static function output( $post ) {
  		global $woocommerce, $inicis_payment;
		$woocommerce->payment_gateways();

	    $order = new WC_Order($post->ID);
		$payment_method = get_post_meta($order->id, '_payment_method', true);
		$tid = get_post_meta($post->ID, 'inicis_paymethod_tid', true);
		
        wp_register_script( 'ifw-admin-js', $inicis_payment->plugin_url() . '/assets/js/ifw_admin.js' );
        wp_enqueue_script( 'ifw-admin-js' );
		wp_localize_script( 'ifw-admin-js', '_ifw_admin', array(
            'action' =>  'refund_request_' . $payment_method ,
            'order_id' => $order->id,
            'nonce' => wp_create_nonce('refund_request'),
            'tid' => $tid
            ) );

        echo '<p class="order-info">';
		if( apply_filters( 'ifw_is_admin_refundable_' . $payment_method, false, $order ) ) {
	        echo '<input style="margin-right:10px" type="button" class="button button-primary tips" id="ifw-refund-request" name="refund-request" value="' . __('환불하기','codem_inicis') . '">';
		}
		if ( !empty($tid) ) {
			echo '<input type="button" class="button button-primary tips" id="ifw-check-receipt" name="refund-request-check-receipt" value="' . __('영수증 확인','codem_inicis') . '">';
		}
        echo '</p>';
    }

    /**
     * Save meta box data
     */
    public static function save( $post_id, $post ) {
    }
}