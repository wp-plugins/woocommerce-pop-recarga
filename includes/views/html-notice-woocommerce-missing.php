<?php
/**
 * Admin View: Notice - WooCommerce missing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_slug = 'woocommerce';

if ( current_user_can( 'install_plugins' ) ) {
	$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $plugin_slug ), 'install-plugin_' . $plugin_slug );
} else {
	$url = 'http://wordpress.org/plugins/' . $plugin_slug;
}
?>

<div class="error">
	<p><strong><?php _e( 'WooCommerce POP Recarga Disabled', 'woocommerce-pop-recarga' ); ?></strong>: <?php printf( __( 'This depends on the last version of %s to work!', 'woocommerce-pop-recarga' ), '<a href="' . esc_url( $url ) . '">' . __( 'WooCommerce', 'woocommerce-pop-recarga' ) . '</a>' ); ?></p>
</div>
