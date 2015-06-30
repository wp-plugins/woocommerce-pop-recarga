<?php
/**
 * Credit Card - Checkout form.
 *
 * @author  InPDV
 * @package WC_Pop_Recarga/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<fieldset id="pop-recarga-fields">
	<p class="form-row form-row-first">
		<label for="pop-recarga-code"><?php _e( 'Mobile Number', 'woocommerce-pop-recarga' ); ?> <span class="required">*</span></label>
		<input id="pop-recarga-code" class="input-text" type="tel" autocomplete="off" maxlength="2" style="font-size: 1.5em; padding: 8px; width: 20%;" required="required" /> <input id="pop-recarga-number" class="input-text" type="tel" autocomplete="off" maxlength="9" pattern=".{9,10}" title="<?php esc_attr_e( 'Please enter with a valid phone number with 8 or 9 digits', 'woocommerce-pop-recarga' ); ?>" style="font-size: 1.5em; padding: 8px; width: 75%;" required="required" />
	</p>
	<p class="form-row form-row-last">
		<br />
		<button type="button" id="pop-recarga-send"><?php _e( 'Receive Pop Recarga Password', 'woocommerce-pop-recarga' ); ?></button>
	</p>
	<div class="clear"></div>
	<p id="pop-recarga-token-wrap" class="form-row form-row-wide" style="display: none;">
		<label for="pop-recarga-token"><?php _e( 'Password', 'woocommerce-pop-recarga' ); ?> <span class="required">*</span></label>
		<input id="pop-recarga-token" name="pop_recarga_token" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" required="required" />
	</p>

	<input type="hidden" id="pop-recarga-order-total" value="<?php echo esc_attr( $order_total ); ?>">
	<div class="clear"></div>
</fieldset>
