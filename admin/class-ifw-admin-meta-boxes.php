<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class IFW_Admin_Meta_Boxes {

	private static $meta_box_errors = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		include_once('post-types/meta-boxes/class-ifw-meta-box-refund.php');
		
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
	}

	/**
	 * Add an error message
	 * @param string $text
	 */
	public static function add_error( $text ) {
		self::$meta_box_errors[] = $text;
	}

	/**
	 * Show any stored error messages.
	 */
	public function output_errors() {
		$errors = maybe_unserialize( get_option( 'ifw_meta_box_refund_errors' ) );

		if ( ! empty( $errors ) ) {

			echo '<div id="woocommerce_errors" class="error fade">';
			foreach ( $errors as $error ) {
				echo '<p>' . esc_html( $error ) . '</p>';
			}
			echo '</div>';

			// Clear
			delete_option( 'ifw_meta_box_refund_errors' );
		}
	}

	/**
	 * Add WC Meta boxes
	 */
	public function add_meta_boxes() {
        add_meta_box(
            'ifw-order-refund-request',
            __( '결제내역', 'codem_inicis' ),
            'IFW_Meta_Box_Refund::output',
            'shop_order',
            'side',
            'default'
        );
	}

}

new IFW_Admin_Meta_Boxes();
