<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce POP Recarga API class.
 *
 * @class   WC_Pop_Recarga_API
 * @version 2.0.0
 */
class WC_Pop_Recarga_API {

	/**
	 * Gateway mode.
	 *
	 * @var string
	 */
	protected $mode = '';

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * Client Secret.
	 *
	 * @var string
	 */
	protected $client_secret = '';

	/**
	 * Debug mode.
	 *
	 * @var string
	 */
	protected $debug = 'no';

	/**
	 * Logger.
	 *
	 * @var WC_Logger|null
	 */
	protected $log = null;

	/**
	 * Constructor.
	 *
	 * @param WC_Pop_Recarga_Gateway $gateway
	 */
	public function __construct( $mode, $client_id, $client_secret, $debug = 'no' ) {
		$this->mode          = $mode;
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->debug         = $debug;

		// Active logs.
		if ( 'yes' == $debug ) {
			$this->log = $this->logger();
		}
	}

	/**
	 * Logger.
	 *
	 * @return WC_Logger
	 */
	public function logger() {
		global $woocommerce;

		if ( class_exists( 'WC_Logger' ) ) {
			return new WC_Logger();
		} else {
			return $woocommerce->logger();
		}
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		$mode = 'sandbox' === $this->mode ? 'sandbox.' : '';

		return 'https://api.' . $mode . 'inpdv.com.br/';
	}

	/**
	 * Get order total.
	 *
	 * @return float
	 */
	public static function get_order_total() {
		global $woocommerce;

		$order_total = 0;
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
		} else {
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		}

		// Gets order total from "pay for order" page.
		if ( 0 < $order_id ) {
			$order      = new WC_Order( $order_id );
			$order_total = (float) $order->get_total();

		// Gets order total from cart/checkout.
		} elseif ( 0 < $woocommerce->cart->total ) {
			$order_total = (float) $woocommerce->cart->total;
		}

		return $order_total;
	}

	/**
	 * Error messages.
	 *
	 * @param  string $code
	 *
	 * @return string
	 */
	protected function i18n_error_message( $code = '' ) {
		$code = strtolower( $code );

		switch ( $code ) {
			case 'notfound' :
				$message = __( 'Your phone number is not registered on POP Recarga.', 'woocommerce-pop-recarga' );
				break;
			case 'lockedcustomer' :
				$message = __( 'Your POP Recarga account is blocked, do the unlocking process in order to make this payment.', 'woocommerce-pop-recarga' );
				break;

			default:
				$message = __( 'An error has occurred while processing your payment, please try again or contact us for assistance.', 'woocommerce-pop-recarga' );
				break;
		}

		return $message;
	}

	/**
	 * Do requests in the Iugu API.
	 *
	 * @param  string $endpoint API Endpoint.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $endpoint, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'    => $method,
			'sslverify' => false,
			'timeout'   => 60
		);

		if ( ! empty( $data ) ) {
			$params['body'] = $data;
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		}

		return wp_remote_post( $this->get_api_url() . $endpoint, $params );
	}

	/**
	 * Get access token.
	 *
	 * @return string
	 */
	public function get_access_token() {
		// Check if have some api token saved.
		if ( false !== ( $access_token = get_transient( 'pop_recarga_access_token' ) ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Access token recovered from transients!' );
			}

			return $access_token;
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( 'pop_payments', 'Requesting new access token...' );
		}

		$data = build_query( array(
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
		) );
		$headers = array(
			'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8'
		);

		$response = $this->do_request( 'token', 'POST', $data, $headers );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'WP_Error while requesting access token: ' . $response->get_error_message() );
			}
		} else if ( 200 === $response['response']['code'] ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Access token created successfully!' );
			}

			$body         = json_decode( $response['body'], true );
			$access_token = sanitize_text_field( $body['access_token'] );
			$expires_in   = absint( $body['expires_in'] );

			set_transient( 'pop_recarga_access_token', $access_token, $expires_in );

			return $access_token;
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Error while requesting access token: ' . print_r( $response, true ) );
			}
		}

		return '';
	}

	/**
	 * Create payments.
	 *
	 * @param  int    $mobile_number
	 * @param  float  $order_total
	 * @param  string $currency_code
	 *
	 * @return array
	 */
	public function create_payment( $mobile_number, $order_total, $currency_code ) {
		$error_code   = 'none';
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return '';
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( 'pop_payments', 'Creating payment for: +' . $mobile_number . '. Total: ' . $order_total . ' ' . $currency_code . '...' );
		}

		$data = json_encode( array(
			'identifier'  => '+' . $mobile_number,
			'transaction' => array(
				'amount'       => $order_total,
				'currencyCode' => $currency_code,
				'description'  => sprintf( __( '%s - Payment of %s', 'woocommerce-pop-recarga' ), sanitize_text_field( get_bloginfo( 'name', 'display' ) ), woocommerce_price( $order_total, array( 'currency' => $currency_code ) ) )
			)
		) );

		$headers = array(
			'Content-Type'  => 'application/json;charset=UTF-8',
			'Authorization' => 'Bearer ' . $access_token,
		);

		$response = $this->do_request( 'payments', 'POST', $data, $headers );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'WP_Error while creating payment: ' . $response->get_error_message() );
			}
		} else if ( 201 === $response['response']['code'] ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Payment created successfully!' );
			}

			$body = json_decode( $response['body'], true );

			return array(
				'payment_id' => sanitize_text_field( $body['id'] ),
				'message'    => ''
			);
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Error while creating the payment: ' . print_r( $response, true ) );
			}

			if ( isset( $response['body'] ) ) {
				$body       = json_decode( $response['body'], true );
				$error_code = sanitize_text_field( $body['codeDescription'] );
			}
		}

		return array(
			'payment_id' => '',
			'message'    => $this->i18n_error_message( $error_code )
		);
	}

	/**
	 * Authorize payment.
	 *
	 * @param  string $payment_id
	 * @param  string $payment_token
	 *
	 * @return bool
	 */
	protected function authorize_payment( $payment_id, $payment_token ) {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return '';
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( 'pop_payments', 'Authorizing payment for ' . $payment_id . '...' );
		}

		$headers = array(
			'Content-Type'  => 'application/json;charset=UTF-8',
			'Authorization' => 'Bearer ' . $access_token,
		);

		$response = $this->do_request( 'payments/' . $payment_id . '/token/' . $payment_token, 'PUT', array(), $headers );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'WP_Error while authorizing payment: ' . $response->get_error_message() );
			}
		} else if ( 200 === $response['response']['code'] ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Payment authorized successfully!' );
			}

			return true;
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Error while authorizing the payment: ' . print_r( $response, true ) );
			}
		}

		return false;
	}

	/**
	 * Execute payments.
	 *
	 * @param  string $payment_id
	 * @param  string $payment_token
	 *
	 * @return array
	 */
	public function execute_payment( $payment_id, $payment_token ) {
		$authorized = $this->authorize_payment( $payment_id, $payment_token );
		if ( ! $authorized ) {
			return array(
				'success' => false,
				'message' => __( 'An error occurred while authorize your payment, make sure your token is correct.', 'woocommerce-pop-recarga' )
			);
		}

		$error_code   = 'none';
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return '';
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( 'pop_payments', 'Executing payment for ' . $payment_id . '...' );
		}

		$headers = array(
			'Content-Type'  => 'application/json;charset=UTF-8',
			'Authorization' => 'Bearer ' . $access_token,
		);

		$response = $this->do_request( 'payments/' . $payment_id . '/execute', 'PUT', array(), $headers );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'WP_Error while executing payment: ' . $response->get_error_message() );
			}
		} else if ( 200 === $response['response']['code'] ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Payment executed successfully!' );
			}

			return array(
				'success' => true,
				'message' => ''
			);
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'pop_payments', 'Error while executing the payment: ' . print_r( $response, true ) );
			}

			if ( isset( $response['body'] ) ) {
				$body       = json_decode( $response['body'], true );
				$error_code = sanitize_text_field( $body['codeDescription'] );
			}
		}

		return array(
			'success' => false,
			'message' => $this->i18n_error_message( $error_code )
		);
	}
}
