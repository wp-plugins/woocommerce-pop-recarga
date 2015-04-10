<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Update the main file.
$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/woocommerce-gateway-pop-paymentswoocommerce-gateway-pop-payments.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/woocommerce-gateway-pop-paymentswoocommerce-gateway-pop-payments.php', '/woocommerce-pop-recarga.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
