<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce POP Recarga Ajax class.
 *
 * @class   WC_Pop_Recarga_Gateway
 * @version 2.0.0
 */
class WC_Pop_Recarga_Ajax {

	/**
	 * Initialize the ajax actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wc_pop_recarga_request_token', array( $this, 'request_token' ) );
		add_action( 'wp_ajax_nopriv_wc_pop_recarga_request_token', array( $this, 'request_token' ) );
	}

	/**
	 * Request token.
	 *
	 * @return string
	 */
	public function request_token() {
		ob_start();

		check_ajax_referer( 'wc_pop_recarga_request_token', 'security' );

		if ( empty( $_POST['order_total'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing order total.', 'woocommerce-pop-recarga' ) ) );
		}

		if ( empty( $_POST['number'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing mobile number.', 'woocommerce-pop-recarga' ) ) );
		}

		$mobile_number = preg_replace( '([^0-9])', '', sanitize_text_field( $_POST['number'] ) );
		if ( 12 != strlen( $mobile_number ) && 13 != strlen( $mobile_number ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid mobile number. Make sure the number is starting with the country calling code. For Brazil the number must start with 55.', 'woocommerce-pop-recarga' ) ) );
		}

		$options = get_option( 'woocommerce_pop_payments_settings', array() );

		if ( empty( $options['enabled'] ) && 'no' == $options['enabled'] ) {
			wp_send_json_error( array( 'message' => __( 'An error has occurred while processing your request, please try again or contact us for assistance.', 'woocommerce-pop-recarga' ) ) );
		}

		$api           = new WC_Pop_Recarga_API( $options['mode'], $options['clientid'], $options['secret'], $options['debug'] );
		$order_total   = absint( $_POST['order_total'] );
		$currency_code = isset( $_POST['currency_code'] ) ? sanitize_text_field( $_POST['currency_code'] ) : '';
		$payment       = $api->create_payment( $mobile_number, $order_total, $currency_code );

		if ( empty( $payment['id'] ) ) {
			wp_send_json_error( array( 'message' => $payment['message'] ) );
		} else {
			wp_send_json_success( array( 'payment_id' => $payment['id'] ) );
		}
	}
}

new WC_Pop_Recarga_Ajax();
