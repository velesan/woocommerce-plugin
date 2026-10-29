<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (WoocommerceAddons::globkurier_meta_old_orders_box_content() i UDIGroup_Admin_Ajax::globkurierGetOldOrdersAsync()), zmienne sa lokalne, nie globalne.

use udigroup_globkurier\UDIGroup_ShipmentHistory;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * @var int $orderId
 **/

// Najnowsze na gorze. Historia laczy wp_postmeta i wc_orders_meta (wersje do 2.5.4 zapisywaly tylko do postmeta).
$oldOrders = array_reverse( UDIGroup_ShipmentHistory::forOrder( $orderId ) );

?>

<div class="globkurier-old-orders-table-wrapper">
	<table class="globkurier-old-orders-table">
		<thead>
			<tr>
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
				<th style="min-width: 50px"><?php echo esc_attr(__( 'Opcja', 'globkurier' )) ?></th>
			</tr>
		</thead>
		
		<tbody>
		<?php
		global $globKurier;
		
		foreach( $oldOrders ?? [] as $order ){
			?>
			<tr>
				<td><?php echo esc_attr($order[ 'number' ] ?? '') ?></td>
				<td><?php echo esc_attr(wp_date( 'd.m.Y H:i', (int) ( $order[ 'date' ] ?? 0 ) ) ?: '' )?></td>
				<td><?php echo esc_attr($order[ 'data' ][ 'receiverAddress' ][ 'name' ] ?? '') ?></td>
				<td><?php echo esc_attr($order[ 'data' ][ 'content' ] ?? '') ?></td>
				<td><?php echo esc_attr($order[ 'data' ][ 'shipment' ][ 'weight' ] ?? '' )?></td>
				<td><?php echo esc_attr($order[ 'carrier' ][ 'name' ] ?? '' )?></td>
				<td><?php echo esc_attr($order[ 'cod' ][ 'value' ] ?? '-') ?></td>
				<td><?php echo esc_attr($order[ 'payment_name' ] ?? '' )?></td>
				<td><?php echo esc_attr($order[ 'price' ][ 'net' ] ?? '' )?>zł</td>
				<td>
					<button type="button" data-number="<?php echo esc_attr($order['number'] )?>" class="udi-get-current-status"><?php echo esc_attr(__( 'Pobierz', 'globkurier' ) )?></button>
					<div class="udi-loader"></div>
					<span class="udi-status-value"></span>
				</td>
				<td>
					<a style="text-decoration: none" href="<?php echo esc_attr($globKurier->api()->getOrderLabelPdfUrl( $order[ 'hash' ] ) )?>" target="_blank" title="<?php echo esc_attr(__( 'Pobierz list przewozowy', 'globkurier' ) )?>">
						<span class="dashicons dashicons-media-document"></span>
					</a>
					<a style="text-decoration: none" href="<?php echo esc_attr($globKurier->api()->getOrderTrackUrl( $order[ 'number' ] ) )?>" target="_blank" title="<?php echo esc_attr(__( 'Śledź przesyłkę', 'globkurier' ) )?>">
						<span class="dashicons dashicons-search"></span>
					</a>
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>

	</table>
</div>

<script>
	// Statusy przesylek pobierane od razu po otwarciu zamowienia (z cache serwera, 15 min); przycisk "Aktualizuj" pobiera swiezy.
	// Po "load" - handler klikniecia rejestruje udi-admin.js, ktory moze byc w stopce.
	jQuery( window ).on( 'load', function () {
		jQuery( '#globkurier_old_orders .udi-get-current-status' ).slice( 0, 10 ).trigger( 'click', [ { useCache: true } ] );
	} );
</script>
