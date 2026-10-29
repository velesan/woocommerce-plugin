<?php

if (! defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

/**
 * @var int $orderId
 **/

?>

<button type="button" class="button globkurier_get_old_orders"><?php echo esc_html__('Pobierz listę nadanych paczek', 'globkurier') ?></button>

<input type="hidden" id="globkurier_old_orders_order_id" value="<?php
echo esc_attr($orderId); ?> ">
<div class="globkurier-old-orders-table-wrapper"></div>

<script>
	( function ( $ ) {
		$( function () {
			
			let GKloadOldOrdersTimer;
			let GKloadOldOrdersXhr;
			
			$( document ).on( 'click', '.globkurier_get_old_orders', function () {
				GKloadOldOrdersTrigger();
			} );
			
			function GKloadOldOrdersTrigger() {
				clearTimeout( GKloadOldOrdersTimer );
				if ( GKloadOldOrdersXhr ) {
					GKloadOldOrdersXhr.abort();
				}
				
				let wrapper = $( '.globkurier-old-orders-table-wrapper' );
				
				wrapper.css( 'min-height', '50px' );
				wrapper.block( {
					message: '',
					css: {
						backgroundColor: '#e0e0e0',
					}
				} );
				
				GKloadOldOrdersTimer = setTimeout( function () {
					GKloadOldOrders()
						.then( response => {
							wrapper.html( response.data );
							$( '.globkurier_get_old_orders' ).hide();
						} )
						.catch( error => {
						
						} );
				}, 500 );
			}
			
			function GKloadOldOrders() {
				return new Promise( ( resolve, reject ) => {
					GKloadOldOrdersXhr = $.ajax( {
						url: ajaxurl, type: 'post', data: {
							action: 'globkurierGetOldOrdersAsync',
							orderId: $( '#globkurier_old_orders_order_id' ).val(),
							nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_get_order_nonce')); ?>'
						}, success: function ( response ) {
							resolve( response );
						}, error: function ( xhr, status, error ) {
							reject( error );
						}
					} );
				} );
			}
			
		} );
	} )( jQuery );
</script>
