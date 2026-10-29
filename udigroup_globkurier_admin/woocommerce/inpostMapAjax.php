<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (domkniecie w WoocommerceAddons::init()), zmienne sa lokalne, nie globalne.

if (!defined('ABSPATH')) exit; // Exit if accessed directly

global $globKurier;

$point_id           = WC()->session->get( 'globkurier_inpost_selected_point_id' ) ?? '';
$point_val          = WC()->session->get( 'globkurier_inpost_selected_point_value' ) ?? '';
$point_lat          = WC()->session->get( 'globkurier_inpost_selected_point_latitude' ) ?? '';
$point_long         = WC()->session->get( 'globkurier_inpost_selected_point_longitude' ) ?? '';
$point_city         = WC()->session->get( 'globkurier_inpost_selected_point_city' ) ?? '';
$point_address      = WC()->session->get( 'globkurier_inpost_selected_point_address' ) ?? '';
$point_openingHours = WC()->session->get( 'globkurier_inpost_selected_point_openingHours' ) ?? '';

wp_enqueue_script( 'markerclusterer', udigroup_globkurier\UDIGroup_Helper::getAdminUrl( 'js/markers/markerclusterer_compiled.js' ), array(), '1.0', true );

?>

<tr class="order-total">
	<th colspan="2" id="udi-map-td">
		<input type="hidden" name="globkurier_method_id" id="globkurier_method_id" value="<?php echo esc_attr($globKurier->settings( 'inpost_method' ) ?? '') ?>">
		<input type="hidden" name="globkurier_inpost_input" id="globkurier_inpost_input" value="<?php echo esc_attr($point_val) ?>" required>
		<input type="hidden" name="globkurier_inpost_input_hidden_value" id="globkurier_inpost_input_hidden_value" value="<?php echo esc_attr($point_id) ?>" required>
		
		<div id="globkurier_map"></div>
		<select type="text" style="width: 100%;" class="udi-select2" id="udi-select-inpost" name="globkurier_inpost_input_value">
			<?php
			if( isset( $point_id ) ){
				echo "<option value='".esc_attr($point_id)."' selected>".esc_attr($point_val)."</option>";
			}
			?>
		</select>
	</th>
</tr>

<script>
	// Bez pustych linii w tym skrypcie: checkout w bloku Shortcode przepuszcza HTML przez wpautop, ktory w pustych liniach wstawia znaczniki akapitu i psuje JS.
	( function ( $ ) {
		function gkMapEsc( v ) { return String( v === undefined || v === null ? '' : v ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }
		$( function () {
			const ajaxurl = data[ 'ajaxUrl' ];
			const globkurierInput = '#globkurier_inpost_input';
			const udiSelect = '.udi-select2#udi-select-inpost';
			const globkurierInputHiddenValue = '#globkurier_inpost_input_hidden_value';
			const saveSessionAction = 'globkurierSaveInpostPointsSession';
			const getPointsAction = 'globkurierGetInpostPointsSelect2';
			const clusterIcon = 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m';
			const map_container = 'globkurier_map';
			const poland = { lat: 52.476329, lng: 18.995734 };
			let markerCluster;
			let globkurier_map;
			globkurierMapInit();
			function globkurierMapInit() {
				globkurier_map = new google.maps.Map( document.getElementById( map_container ), {
					center: poland,
					zoom: 6,
					disableDefaultUI: true
				} );
			}
			function gkSaveSession( d ) {
				$( document ).find( globkurierInput ).val( d.value || d.text );
				$( document ).find( globkurierInputHiddenValue ).val( d.id );
				$( document ).find( '#udi-map-td .select2-selection__rendered' ).text( d.value || d.text);
				$.post( {
					url: ajaxurl,
					dataType: 'json',
					minLength: 3,
					data: {
						action: saveSessionAction,
						id: d.id,
						value: d.value || d.text,
						latitude: d.latitude,
						longitude: d.longitude,
						city: d.city,
						address: d.address,
						openingHours: d.openingHours,
                        nonce: '<?php echo esc_attr(wp_create_nonce( 'globkurier_save_inpost_points_session_nonce' )) ?>',
					},
				} );
			}
			$( document ).on( 'click', '.globkurier_marker_select_point', function ( e ) {
				let eventData = $( e.target ).data( 'eventdata' );
				gkSaveSession( eventData );
				$( e.target ).parent().parent().parent().parent().find('button.gm-ui-hover-effect').click()
			} );
			<?php
			if ( $point_val && $point_id && $point_lat && $point_long && $point_city && $point_address ) {  ?>
			let lat = <?php echo wp_json_encode( (float) $point_lat ) ?>;
			let lng = <?php echo wp_json_encode( (float) $point_long ) ?>;
			let pos = { lat, lng };
			let contentString = `<p> Miasto: ${ <?php echo wp_json_encode( esc_html( $point_city ) ) ?> } </p>` + `<p> Adres: ${ <?php echo wp_json_encode( esc_html( $point_address ) ) ?> } </p>`;
			<?php if ($point_openingHours != ''){?>
			contentString += `<p> Godziny otwarcia: ${ <?php echo wp_json_encode( esc_html( $point_openingHours ) ) ?> } </p>`;
			<?php }?>
			let tooltip = new google.maps.InfoWindow( {
				content: contentString
			} );
			let marker = new google.maps.Marker( {
				position: pos,
			} );
			marker.addListener( 'click', () => {
				tooltip.open( globkurier_map, marker );
				$( globkurierInput ).val( <?php echo wp_json_encode( $point_val, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ?> );
				$( globkurierInputHiddenValue ).val( <?php echo wp_json_encode( $point_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ?> );
			} );
			globkurier_map.setZoom( 15 );
			globkurier_map.setCenter( marker.getPosition() );
			marker.setMap( globkurier_map );
			<?php } ?>
			$( document ).find( udiSelect ).select2( {
				placeholder: 'Znajdź punkt dostawy',
				language: {
					searching: function () {
						return 'Szukaj Paczomatu Inpost';
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
					url: data[ 'ajaxUrl' ],
					dataType: 'json',
					data: function ( params ) {
						return {
							city: params.term,
							countryId: ($('#ship-to-different-address-checkbox').is(':checked') ? $('#shipping_country').val() : $('#billing_country').val()) || 'PL',
							action: getPointsAction,
						};
					},
					processResults: function ( data ) {
						let parsedData = JSON.parse( data );
						let options = [];
						if ( parsedData ) {
							$.each( parsedData, function ( index, data ) {
								options.push( {
									id: data.id,
									text: data.label,
									latitude: data.latitude,
									longitude: data.longitude,
									city: data.city,
									address: data.address,
									openingHours: data.openingHours,
								} );
							} );
							globkurierMapInit();
							let markers = [];
							parsedData.forEach( ( d ) => {
								let lat = d.latitude;
								let lng = d.longitude;
								let pos = { lat, lng };
								let contentString =
									`<div class="gkInfowindow-inside">` +
									`<p> Miasto: ${ gkMapEsc( d.city ) } </p>` +
									`<p> Adres: ${ gkMapEsc( d.address ) } </p>`;
								if ( d.openingHours !== '' ) {
									contentString += `<p> Godziny otwarcia: ${ gkMapEsc( d.openingHours ) } </p>`;
								}
								let tooltip = new google.maps.InfoWindow( {
								} );
								contentString += `<button class="globkurier_marker_select_point"`;
								let eventData = JSON.stringify( d )
								let tooltipData = JSON.stringify( d )
								contentString += " data-eventdata='" + gkMapEsc( eventData ) + "'";
								contentString += " data-tooltip='" + gkMapEsc( tooltipData ) + "'";
								contentString += ` type="button">WYBIERZ</button>`;
								contentString += `</div>`;
								tooltip.setContent( contentString ) ;
								let marker = new google.maps.Marker( {
									position: pos,
									maxWidth: 300,
								} );
								marker.addListener( 'click', () => {
									tooltip.open( globkurier_map, marker );
									globkurier_map.setCenter( new google.maps.LatLng( d.latitude, d.longitude ) );
									globkurier_map.setZoom( 15 );
								} );
								markers.push( marker );
							} );
							markerCluster = new MarkerClusterer( globkurier_map, markers, { imagePath: clusterIcon } );
							markerCluster.fitMapToMarkers();
						}
						return {
							results: options
						};
					},
					cache: true
				},
				minimumInputLength: 3,
			} );
			$( document ).find( udiSelect ).on( 'select2:select', function ( e ) {
				$( globkurierInput ).val( e.params.data.text );
				$( globkurierInputHiddenValue ).val( e.params.data.id );
				let lat = e.params.data.latitude;
				let lng = e.params.data.longitude;
				let pos = { lat, lng };
				let markers = [];
				let contentString =
					`<p> Miasto: ${ gkMapEsc( e.params.data.city ) } </p>` +
					`<p> Adres: ${ gkMapEsc( e.params.data.address ) } </p>`;
				contentString += `<p> Godziny otwarcia: ${ gkMapEsc( e.params.data.openingHours ) } </p>`;
				let tooltip = new google.maps.InfoWindow( {
					content: contentString
				} );
				let marker = new google.maps.Marker( {
					position: pos,
				} );
				marker.addListener( 'click', () => {
					tooltip.open( globkurier_map, marker );
				} );
				markers.push( marker );
				globkurierMapInit();
				markerCluster = new MarkerClusterer( globkurier_map, markers, { imagePath: clusterIcon } );
				globkurier_map.setCenter( marker.getPosition() );
				globkurier_map.setZoom( 15 );
				gkSaveSession( e.params.data )
			} );
			$( document ).find( udiSelect ).data( 'select2' ).on( 'results:message', function ( params ) {
				this.dropdown._resizeDropdown();
				this.dropdown._positionDropdown();
			} );
		} );
	} )( jQuery );
</script>