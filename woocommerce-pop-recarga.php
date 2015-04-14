<?php
/**
 * Plugin Name: WooCommerce POP Recarga
 * Plugin URI: http://www.poprecarga.com.br
 * Description: POP Recarga Payments gateway plugin for WooCommerce.
 * Author: InPDV
 * Author URI: http://www.inpdv.com.br/
 * Version: 2.0.1
 * License: GPLv2 or later
 * Text Domain: woocommerce-pop-recarga
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Pop_Recarga' ) ) :

/**
 * WooCommerce POP Recarga main class.
 */
class WC_Pop_Recarga {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.0.1';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin actions.
	 */
	public function __construct() {
		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce is installed.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			$this->includes();

			// Hook to add POP Recarga Gateway to WooCommerce.
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'dependencies_notices' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get templates path.
	 *
	 * @return string
	 */
	public static function get_templates_path() {
		return plugin_dir_path( __FILE__ ) . 'templates/';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-pop-recarga', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Includes.
	 */
	private function includes() {
		include_once 'includes/class-wc-pop-recarga-api.php';
		include_once 'includes/class-wc-pop-recarga-ajax.php';
		include_once 'includes/class-wc-pop-recarga-gateway.php';
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 *
	 * @return array          Payment methods with POP Recarga.
	 */
	public function add_gateway( $methods ) {
		$methods[] = 'WC_Pop_Recarga_Gateway';

		return $methods;
	}

	/**
	 * Dependencies notices.
	 */
	public function dependencies_notices() {
		include_once 'includes/views/html-notice-woocommerce-missing.php';
	}

	/**
	 * Action links.
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array();

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_pop_recarga_gateway' );
		} else {
			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Pop_Recarga_Gateway' );
		}

		$plugin_links[] = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-pop-recarga' ) . '</a>';

		return array_merge( $plugin_links, $links );
	}
}

add_action( 'plugins_loaded', array( 'WC_Pop_Recarga', 'get_instance' ) );

endif;
