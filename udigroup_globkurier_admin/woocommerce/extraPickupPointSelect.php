<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (domkniecie w WoocommerceAddons::init() i WoocommerceAddons::afterShippingMethodSelected()), zmienne sa lokalne, nie globalne.
/**
 * @var string $key
 * @var string $method
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use udigroup_globkurier\UDIGroup_Helper;

global $globKurier;

$fields = [
	'idField'     => 'globkurier_'.$key.'_input',
	'valueField'  => 'globkurier_'.$key.'_input_hidden_value',
	'selectField' => 'globkurier_'.$key.'_input_value',
	
	'point_lat'          => 'globkurier_'.$key.'_input_point_lat',
	'point_long'         => 'globkurier_'.$key.'_input_point_long',
	'point_city'         => 'globkurier_'.$key.'_input_point_city',
	'point_address'      => 'globkurier_'.$key.'_input_point_address',
	'point_openingHours' => 'globkurier_'.$key.'_input_point_openingHours',
];

$actions = [
	'search'      => 'globkurierGetExtraPickupsPointsSelect2',
	'saveSession' => 'globkurierSaveExtraPickupsPointsSession',
];

$wrapperId = 'globkurier-pickup-wrapper_'.$key;

$carrierId = str_replace('extra_pickup_point_', '', $key);

$productId = $globKurier->extraPickupPoints()->findProductIdForCarrierId($carrierId);

$point_id           = WC()->session->get('globkurier_'.$carrierId.'_selected_point_id') ?? '';
$point_val          = WC()->session->get('globkurier_'.$carrierId.'_selected_point_value') ?? '';
$point_lat          = WC()->session->get('globkurier_'.$carrierId.'_selected_point_latitude') ?? '';
$point_long         = WC()->session->get('globkurier_'.$carrierId.'_selected_point_longitude') ?? '';
$point_city         = WC()->session->get('globkurier_'.$carrierId.'_selected_point_city') ?? '';
$point_address      = WC()->session->get('globkurier_'.$carrierId.'_selected_point_address') ?? '';
$point_openingHours = WC()->session->get('globkurier_'.$carrierId.'_selected_point_openingHours') ?? '';

$hasMap = !empty($googleMapsAPIKey);

if($hasMap){
	echo "<tr class='order-total'>";
	echo "<th colspan='2' id='udi-map-td'>";
}else{
	echo "<div class='globkurier-ruch-container'>";
}
?>

<input type='hidden'
       name='globkurier_method_id'
       id='globkurier_method_id'
       value="<?php echo esc_attr($method) ?>">

<input type='hidden'
       name='<?php echo esc_attr($fields[ 'idField' ]) ?>'
       id='<?php echo esc_attr($fields[ 'idField' ]) ?>'
       value="<?php
	   echo esc_attr($point_val) ?>"
       required>

<input type='hidden' name='<?php echo esc_attr($fields[ 'valueField' ]) ?>'
       id='<?php echo esc_attr($fields[ 'valueField' ]) ?>'
       value="<?php
	   echo esc_attr($point_id) ?>"
       required>

<select type='text'
		        style='width: 100%;'
		        class='udi-select2'
		        id='<?php echo esc_attr($fields[ 'selectField' ]) ?>'
		        name='<?php echo esc_attr($fields[ 'selectField' ] ?? '') ?>'>
			<?php
			if (isset($point_id)) {
				echo "<option value='".esc_attr($point_id)."' selected>".esc_attr($point_val).'</option>';
			}
			?>
		</select>

<?php
if($hasMap){
	echo '</th>';
	echo '</tr>';
}else{
	echo '</div>';
}
?>

<script>
	// Bez pustych linii w tym skrypcie: checkout w bloku Shortcode przepuszcza HTML przez wpautop, ktory w pustych liniach wstawia znaczniki akapitu i psuje JS.
	( function ( $ ) {
		$( function () {
			const params = {
				product: {
					id: '<?php echo esc_js($productId) ?>',
				},
				carrier: {
					id: '<?php echo esc_js($carrierId) ?>',
				},
				fields: {
					idField: '<?php echo esc_js($fields[ 'idField' ] ?? '') ?>',
					valueField: '<?php echo esc_js($fields[ 'valueField' ] ?? '') ?>',
					selectField: '<?php echo esc_js($fields[ 'selectField' ] ?? '') ?>',
				},
				actions: {
					search: '<?php echo esc_js($actions[ 'search' ] ?? '') ?>',
					saveSession: '<?php echo esc_js($actions[ 'saveSession' ] ?? '') ?>',
					getSessionData: '<?php echo esc_js($actions[ 'getSessionData' ] ?? '') ?>',
				},
				wrapperId: '<?php echo esc_js($wrapperId) ?>',
				ajax_url: data[ 'ajaxUrl' ],
			};
			const selectTarget = '#' + params.fields.selectField;
			const gkGetShippingCountry = () => {
				let country = '';
				if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
					country = $( '#shipping_country' ).val() || '';
				}
				if ( ! country ) {
					country = $( '#billing_country' ).val() || '';
				}
				return country;
			}
			const gkSaveSession = ( d ) => {
				$( document ).find( '#' + params.fields.idField, '#' + params.wrapperId ).val( d.value || d.text ).change();
				$( document ).find( '#' + params.fields.valueField, '#' + params.wrapperId ).val( d.id ).change();
				$( document ).find( '.select2-selection__rendered', '#' + params.wrapperId ).text( d.value || d.text ).change();
				$.post( {
					url: params.ajax_url,
					dataType: 'json',
					minLength: 3,
					data: {
						action: params.actions.saveSession,
						id: d.id,
						value: d.text,
						latitude: d.latitude,
						longitude: d.longitude,
						city: d.city,
						address: d.address,
						openingHours: d.openingHours,
						productId: params.product.id,
						carrierId: params.carrier.id,
						nonce: '<?php echo esc_js(wp_create_nonce('globkurier_save_extra_pickup_points_session_nonce')) ?>',
					},
				} );
			}
			$( document ).find( selectTarget ).select2( {
				placeholder: 'Znajdź punkt dostawy',
				language: {
					searching: function () {
						return 'Znajdź punkt dostawy';
					},
					inputTooShort: function () {
						return 'Wpisz miasto';
					},
					noResults: function () {
						return 'Brak wyników';
					},
					errorLoading: function () {
						return 'Szukanie...';
					},
					loadingMore: function () {
						return 'Szukanie...';
					},
				},
				ajax: {
					url: params.ajax_url,
					dataType: 'json',
					data: function ( search ) {
						return {
							city: search.term,
							action: params.actions.search,
							productId: params.product.id,
							countryId: gkGetShippingCountry()
						}
					},
					processResults: function ( data ) {
						let parsedData = JSON.parse( data );
						let options = [];
						if ( parsedData ) {
							$.each( parsedData, function ( index, data ) {
								options.push( {
									id: data.id,
									text: data.name + ', ' + data.address + ' [' + data.id + ']',
									latitude: data.latitude,
									longitude: data.longitude,
									city: data.city,
									address: data.address,
									openingHours: data.openingHours,
								} );
							} );
						}
						return {
							results: options
						};
					},
					cache: true
				},
				minimumInputLength: 3,
			} ).on( 'select2:select', function ( e ) {
				gkSaveSession(
					e.params.data,
				);
			} );
		} );
	} )( jQuery );
</script>