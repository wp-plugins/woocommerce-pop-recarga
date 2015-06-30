<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce POP Recarga Gateway class.
 *
 * @class   WC_Pop_Recarga_Gateway
 * @extends WC_Payment_Gateway
 * @version 2.0.0
 */
class WC_Pop_Recarga_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'pop_payments';
		$this->icon               = apply_filters( 'wc_pop_recarga_gateway_icon', plugins_url( 'assets/images/pop-recarga.png', plugin_dir_path( __FILE__ ) ) );
		$this->method_title       = __( 'POP Recarga', 'woocommerce-pop-recarga' );
		$this->method_description = __( 'POP Payments works by adding a mobile number entry field on the checkout and then sending the details to POP Payments for authorization.', 'woocommerce-pop-recarga' );
		$this->has_fields         = true;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Options.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->mode          = $this->get_option( 'mode' );
		$this->client_id     = $this->get_option( 'clientid' );
		$this->client_secret = $this->get_option( 'secret' );
		$this->debug         = $this->get_option( 'debug' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' == get_woocommerce_currency();
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$api = ! empty( $this->client_id ) && ! empty( $this->client_secret );

		$available = 'yes' == $this->get_option( 'enabled' ) && $api && $this->using_supported_currency();

		return $available;
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-pop-recarga' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-pop-recarga' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable POP Recarga', 'woocommerce-pop-recarga' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-pop-recarga' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-pop-recarga' ),
				'desc_tip'    => true,
				'default'     => __( 'POP Recarga', 'woocommerce-pop-recarga' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-pop-recarga' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-pop-recarga' ),
				'default'     => __( 'Pay by Mobile using POP Recarga.', 'woocommerce-pop-recarga' )
			),
			'integration' => array(
				'title'       => __( 'Integration Settings', 'woocommerce-pop-recarga' ),
				'type'        => 'title',
				'description' => ''
			),
			'mode' => array(
				'title'       => __( 'Gateway Mode', 'woocommerce-pop-recarga' ),
				'type'        => 'select',
				'description' => __( 'Sandbox option can be used to test the payments. Production option should be used to receive real payments.', 'woocommerce-pop-recarga' ),
				'desc_tip'    => true,
				'default'     => 'sandbox',
				'options'     => array(
					'sandbox'    => __( 'Sandbox', 'woocommerce-pop-recarga' ),
					'production' => __( 'Production', 'woocommerce-pop-recarga' )
				)
			),
			'clientid' => array(
				'title'             => __( 'Client ID', 'woocommerce-pop-recarga' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your Client ID. This is needed in order to take payment.', 'woocommerce-pop-recarga' ),
				'desc_tip'          => true,
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'secret' => array(
				'title'             => __( 'Client Secret', 'woocommerce-pop-recarga' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your Client Secret. This is needed in order to take payment.', 'woocommerce-pop-recarga' ),
				'desc_tip'          => true,
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'testing' => array(
				'title'       => __( 'Gateway Testing', 'woocommerce-pop-recarga' ),
				'type'        => 'title',
				'description' => ''
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-pop-recarga' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-pop-recarga' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log POP Recarga events, such as API requests, you can check this log in %s.', 'woocommerce-pop-recarga' ), $this->get_log_view() )
			)
		);
	}

	/**
	 * Load the checkout scripts.
	 */
	public function checkout_scripts() {
		if ( is_checkout() && 'yes' == $this->enabled ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script( 'wc-pop-recarga-checkout', plugins_url( 'assets/js/checkout' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_Pop_Recarga::VERSION, true );

			wp_localize_script(
				'wc-pop-recarga-checkout',
				'wc_pop_recarga_params',
				array(
					'ajax_url'            => admin_url( 'admin-ajax.php' ),
					'security'            => wp_create_nonce( 'wc_pop_recarga_request_token' ),
					'currency_code'       => get_woocommerce_currency(),
					'i18n_missing_number' => __( 'A valid mobile number is required.', 'woocommerce-pop-recarga' ),
					'i18n_missing_token'  => __( 'Password is required.', 'woocommerce-pop-recarga' ),
				)
			);
		}
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		wp_enqueue_script( 'wc-credit-card-form' );

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		// Get order total.
		if ( method_exists( $this, 'get_order_total' ) ) {
			$order_total = $this->get_order_total();
		} else {
			$order_total = WC_Pop_Recarga_API::get_order_total();
		}

		woocommerce_get_template(
			'payment-form.php',
			array(
				'order_total' => $order_total
			),
			'woocommerce/pop-recarga/',
			WC_Pop_Recarga::get_templates_path()
		);
	}

	/**
	 * Add error message in checkout.
	 *
	 * @param  string $message Error message.
	 *
	 * @return string          Displays the error message.
	 */
	protected function add_error( $message ) {
		global $woocommerce;

		$prefix  = '<strong>' . __( 'POP Recarga:', 'woocommerce-pop-recarga' ) . '</strong> ';
		$message = $prefix . $message;

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		} else {
			$woocommerce->add_error( $message );
		}
	}

	/**
	 * Empty card.
	 */
	protected function empty_card() {
		global $woocommerce;

		// Empty cart.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			WC()->cart->empty_cart();
		} else {
			$woocommerce->cart->empty_cart();
		}
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array         Redirect.
	 */
	public function process_payment( $order_id ) {
		$order         = new WC_Order( $order_id );
		$payment_id    = isset( $_REQUEST['pop_recarga_payment_id'] ) ? sanitize_text_field( $_REQUEST['pop_recarga_payment_id'] ) : '';
		$payment_token = isset( $_REQUEST['pop_recarga_token'] ) ? sanitize_text_field( $_REQUEST['pop_recarga_token'] ) : '';
		$valid         = true;

		if ( ! $payment_id || ! $payment_token ) {
			$this->add_error( __( 'You must fill your mobile number and token.', 'woocommerce-pop-recarga' ) );
			$valid = false;
		}

		if ( $valid ) {
			$api      = new WC_Pop_Recarga_API( $this->mode, $this->client_id, $this->client_secret, $this->debug );
			$response = $api->execute_payment( $payment_id, $payment_token );

			if ( $response['success'] ) {
				$this->empty_card();
				$order->add_order_note( __( 'POP Recarga: Payment approved.', 'woocommerce-pop-recarga' ) );
				add_post_meta( $order->id, '_transaction_id', (string) $payment_id, true );
				$order->payment_complete();
				$valid = true;
			} else {
				$this->add_error( $response['message'] );
				$valid = false;
			}
		}

		if ( $valid ) {
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		} else {
			return array(
				'result'   => 'fail',
				'redirect' => ''
			);
		}
	}
}
