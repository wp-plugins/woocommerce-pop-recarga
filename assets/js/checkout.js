/* global wc_pop_recarga_params */
/*jshint devel: true */
(function( $ ) {
	'use strict';

	$( function() {
		$( document.body ).on( 'click', '#pop-recarga-send', function( event ) {
			event.preventDefault();

			var $form      = $( 'form.checkout, form#order_review' ),
				wrapper    = $( '#pop-recarga-fields', $form ),
				numberVal  = $( '#pop-recarga-number', wrapper ).val(),
				orderTotal = $( '#pop-recarga-order-total', wrapper ).val();

			$( '.woocommerce-error', wrapper ).remove();

			if ( 0 === numberVal.length ) {
				wrapper.prepend( '<div class="woocommerce-error">' + wc_pop_recarga_params.i18n_missing_number + '</div>' );
				return false;
			}

			requestToken( orderTotal, numberVal, $form );
		});

		/**
		 * Generate token.
		 *
		 * @param {float} orderTotal
		 * @param {string} mobileNumber
		 * @param {object} form
		 */
		function requestToken( orderTotal, mobileNumber, form ) {
			var wrapper = $( '#pop-recarga-fields', form );

			form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				url: wc_pop_recarga_params.ajax_url,
				data: {
					action: 'wc_pop_recarga_request_token',
					security: wc_pop_recarga_params.security,
					order_total: orderTotal,
					number: mobileNumber,
					currency_code: wc_pop_recarga_params.currency_code
				},
				type: 'POST',
				dataType: 'json',
				success: function( response ) {
					$( '.woocommerce-error, .pop-recarga-payment-id', wrapper ).remove();
					form.unblock();

					if ( response.success ) {
						$( '#pop-recarga-token-wrap', wrapper ).show();
						wrapper.prepend( '<input type="hidden" class="pop-recarga-payment-id" value="' + response.data.payment_id + '" name="pop_recarga_payment_id" />' );
					} else {
						$( '#pop-recarga-token-wrap', wrapper ).hide();
						wrapper.prepend( '<div class="woocommerce-error">' + response.data.message + '</div>' );
					}
				}
			});
		}
	});

}( jQuery ));
