<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (UDIGroup_Admin::load_admin_page_template()), zmienne sa lokalne, nie globalne.

if (!defined('ABSPATH')) exit; // Exit if accessed directly

global $wpdb;
global $globKurier;


// Pobranie PDF etykiet/protokolow linkiem GET - nonce w adresie (GlobKurierApi::getOrder*PdfUrl, przyciski zbiorcze).
if (isset($_GET[ 'getPdfLabels' ]) || isset($_GET[ 'getPdfProtocols' ])) {
	check_admin_referer(\udigroup_globkurier\GlobKurierApi::PDF_NONCE);
}

if (sanitize_text_field(wp_unslash($_GET[ 'getPdfProtocols' ] ?? ''))) {
    $hashes = sanitize_text_field(wp_unslash($_GET[ 'hashes' ] ?? ''));

	$pdf = $globKurier->documents()->getOrderProtocolPdf($hashes);
	
	if(strpos($pdf, '%PDF') === false){
		echo esc_attr($pdf);
		die;
	}
	
	ob_clean();
	header('Content-type: application/pdf');
	header('Content-Disposition: inline; filename="protocol.pdf"');
	header('Content-Transfer-Encoding: binary');
	header('Accept-Ranges: bytes');
	
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binarny PDF z API GK wysylany z naglowkiem Content-Type: application/pdf (gdy odpowiedz nie jest PDF, wyzej esc_attr); escapowanie zepsuloby plik.
	die($pdf);
}


// Tabela laduje strony przez AJAX (globkurierGetProtocolOrders) - wczesniej szablon renderowal wszystkie przesylki naraz.

?>

<h1><?php echo esc_attr(__( 'Lista protokołów GlobKurier', 'globkurier' )) ?></h1>

<div class="globkurier-old-orders-table-wrapper">
	<table class="globkurier-old-orders-table stripe udi-is-datatable udi-is-ajax-datatable">
		<thead>
		<tr>
			<th></th>
			<th style="min-width: 150px"><?php echo esc_attr(__( 'Nr zamówienia GK', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Data nadania', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Odbiorca', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Zawartość', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Waga [kg]', 'globkurier' )) ?></th>
			<th style="min-width: 150px"><?php echo esc_attr(__( 'Przewoźnik', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Pobranie [zł]', 'globkurier' )) ?></th>
			<th style="min-width: 150px"><?php echo esc_attr(__( 'Płatność', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Cena [zł]', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_attr(__( 'Status', 'globkurier' )) ?></th>
			<th style="min-width: 100px"><?php echo esc_html(__( 'Nr Zamówienia Woocommerce', 'globkurier' )) ?></th>
			<th style="min-width: 50px"><?php echo esc_attr(__( 'Opcja', 'globkurier' )) ?></th>
		</tr>
		</thead>
		<tbody></tbody>
		<tfoot>
		<tr>
			<td colspan="13">
				
				<input id="udi-check-all" type="checkbox">
				<label for="udi-check-all"><?php echo esc_attr(__( 'Zaznacz wszystkie', 'globkurier' )) ?></label>
				<br>
				<div class="glogburier-orders-bulk-actions-container">
					
					<p class="glogburier-orders-bulk-actions-header"><?php echo esc_attr(__( 'Masowe działania dla', 'globkurier' )) ?>
						<span id="bulk-actions-counter">0</span> <?php echo esc_attr(__( 'zaznaczonych pozycji', 'globkurier' )) ?>
					</p>
					
					<div class="globkurier-orders-bulk-actions">
						<select id="bulk-print-format">
							<option value="1"><?php echo esc_attr(__( 'Pobranie protokołu', 'globkurier' )) ?></option>
						</select>
						
						<button type="button" class="button globkurier-bulk-action"><?php echo esc_attr(__( 'Wykonaj', 'globkurier' )) ?></button>
					
					</div>
				
				</div>
			
			</td>
		</tr>
		</tfoot>
	</table>
</div>

<script>
	
	( function ( $ ) {
		$( function () {

			// Wartosci w JSON sa juz escapowane po stronie serwera (GlobKurierDocuments::historyPage()).
			function checkItem( data, type, full ) {
				return '<input type="checkbox" class="globkurier-order-bulk-check" value="' + full.hash + '" title="<?php echo esc_attr(__( 'Zaznacz', 'globkurier' )) ?>">';
			}

			function getStatus( data, type, full ) {
				return '<button type="button" data-number="' + full.number + '" title="<?php echo esc_attr(__( 'Pobierz status zamówienia', 'globkurier' )) ?>" class="udi-get-current-status"><?php echo esc_attr(__( 'Pobierz', 'globkurier' )) ?></button>'
					+ '<div class="udi-loader"></div><span class="udi-status-value"></span>';
			}

			function getOrderLink( data, type, full ) {
				if ( full.wc_order_link ) {
					return '<a style="text-decoration: none" href="' + full.wc_order_link + '" target="_blank">#' + ( full.wc_order_number || full.wc_order_id ) + '</a>';
				}

				return '-';
			}

			function actionLinks( data, type, full ) {
				return '<a style="text-decoration: none" href="' + full.order_protocol_url + '" target="_blank" title="<?php echo esc_attr(__( 'Pobierz list przewozowy', 'globkurier' )) ?>"><span class="dashicons dashicons-media-document"></span></a>'
					+ '<a style="text-decoration: none" href="' + full.order_track_url + '" target="_blank" title="<?php echo esc_attr(__( 'Śledź przesyłkę', 'globkurier' )) ?>"><span class="dashicons dashicons-search"></span></a>';
			}

			$( '.udi-is-ajax-datatable' )
				.DataTable( {
					'language': { 'url': '<?php echo esc_url(UDIGroup_GLOBKURIER_PLUGIN_DIR_URL.'udigroup_globkurier_admin/lang/datatables/pl.json') ?>' },
					'stateSave': true,
					'processing': true,
					'serverSide': true,
					'ajax': ajaxurl + "?action=globkurierGetProtocolOrders&nonce=<?php echo esc_attr(wp_create_nonce('globkurier_get_protocol_orders_nonce')); ?>",
					'bSort': false,
					'columns': [
						{ 'render': checkItem },
						{ 'data': 'number' },
						{ 'data': 'date' },
						{ 'data': 'receiver_address_name' },
						{ 'data': 'content' },
						{ 'data': 'shipment_weight' },
						{ 'data': 'carrier_name' },
						{ 'data': 'cod_value' },
						{ 'data': 'payment_name' },
						{ 'data': 'price_net' },
						{ 'render': getStatus },
						{ 'render': getOrderLink },
						{ 'render': actionLinks },
					],
				} )
				.on( 'draw.dt', function () {
					// Zaznaczenia dotycza tylko widocznej strony.
					$( '#udi-check-all' ).prop( 'checked', false );
					$( '#bulk-actions-counter' ).html( 0 );
				} );
			
			$( '#udi-check-all' ).change( function () {
				if ( $( this ).is( ':checked' ) ) {
					$( '.globkurier-order-bulk-check' ).prop( 'checked', true ).change();
				}
			} );
			
			$( document ).on( 'change', '.globkurier-order-bulk-check', function () {
				let counter = $( '.globkurier-order-bulk-check:checked' ).length;
				$( '#bulk-actions-counter' ).html( counter );
			} );
			
			$( '.globkurier-bulk-action' ).click( function () {
				let counter = $( '.globkurier-order-bulk-check:checked' ).length;
				
				if ( counter == 0 ) {
					return;
				}
				let button = $( this );
				button.attr( 'disabled', true );
				
				let orderHashes = $( '.globkurier-order-bulk-check:checked' ).map( function ( idx, elem ) {
					return $( this ).val();
				} ).get();
				
				window.open( '?page=globkurier_protocols&getPdfProtocols=1&hashes=' + orderHashes + '&_wpnonce=<?php echo esc_js(wp_create_nonce(\udigroup_globkurier\GlobKurierApi::PDF_NONCE)) ?>', '_blank' );
				button.attr( 'disabled', false );

				return;
				
				let ajaxData = {
					action: 'globkurierGetProtocols',
					data: {
						orderHashes: orderHashes,
                        nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_get_protocols_nonce')); ?>'
					}
				};
				
				$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
					if ( response.success == false ) {
						alert( response.data.toString() );
						button.show();
						button.attr( 'disabled', false );
					} else {
						let text = response.data.pop().name;
						statusText.text( text );
					}
					
					loader.hide();
				} ).fail( function () {
					alert( 'error' );
				} );
				
			} );
		} );
	} )( jQuery );

</script>
