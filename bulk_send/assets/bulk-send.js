( function ( $ ) {
	// Escapowanie danych (API) przed wklejeniem do HTML lub atrybutu.
	function gkEsc( value ) {
		return $( '<div>' ).text( value === undefined || value === null ? '' : value ).html().replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}
	
	$( function () {
		
		let gkOpenModalRequest = null;
		let carriersData;
		
		$(document).on("click", "#doaction, #doaction2", function(e) {
			let $form = $(this).closest("form");
			let actionSelect = $(this).attr("id") === "doaction" ? "action" : "action2";
			let actionValue = $form.find("select[name=" + actionSelect + "]").val();
			
			if (actionValue === "nadaj_z_globkurier") {
				e.preventDefault();
				e.stopPropagation();
				
				if (gkOpenModalRequest) {
					gkOpenModalRequest.abort();
				}
				
				const selectedOrders = [];
				const shippingMethods = [];
				
				$form.find('input[name*=post], input[name*=id]').filter(':checked').each(function() {
					const orderId = $(this).val();
					selectedOrders.push(orderId);
					
					const $row = $(this).closest('tr');
					const $shippingCell = $row.find('td.column-shipping_method, td.shipping_method');
					const shippingMethod = $shippingCell.text().trim();
					
					if (shippingMethod) {
						shippingMethods.push(shippingMethod);
					}
				});
				
				if (selectedOrders.length === 0) {
					alert('Nie wybrano żadnych zamówień');
					return false;
				}
				
				const uniqueMethods = [...new Set(shippingMethods)];
				
				if (uniqueMethods.length > 1) {
					alert('Wybrane zamówienia mają różne metody wysyłki. Wybierz tylko zamówienia z tą samą metodą wysyłki.');
					return false;
				}
				
				if (uniqueMethods.length === 0 || uniqueMethods[0] === '') {
					alert('Niektóre zamówienia nie mają przypisanej metody wysyłki.');
					return false;
				}
				
				showModal(getLoaderContent());
				
				openModal(selectedOrders)
					.then(response => {
						gkOpenModalRequest = null;
						
						if (response.success) {
							loadModalContent(response.data.html);
							carriersData = Object.values(response.data.carrierData);
						} else {
							loadModalContent(getErrorContent(response.data.message));
						}
					})
					.catch(error => {
						loadModalContent(getErrorContent('Błąd komunikacji z serwerem'));
					});
			}
		});
		
		$( document ).on( "click", ".modal-close", function ( e ) {
			closeModal();
		} );
		
		$( document ).on( "click", ".modal-content", function ( e ) {
			e.stopPropagation();
		} );
		
		function openModal( orders ) {
			return new Promise( ( resolve, reject ) => {
				gkOpenModalRequest = $.ajax( {
					url: wpopieka_globkurier_bulk_send_data.ajax_url,
					type: 'POST',
					data: {
						action: wpopieka_globkurier_bulk_send_data.action,
						orders: orders,
						nonce: wpopieka_globkurier_bulk_send_data.nonce
					},
					dataType: 'json',
					success: function ( response ) {
						resolve( response );
					},
					error: function ( xhr, status, error ) {
						reject( xhr );
					}
				} );
			} );
		}
		
		function showModal( content ) {
			const $modal = $( '#globkurier-bulk-modal' );
			
			if ( $modal.length === 0 ) {
				createModal();
			}
			
			$( '#globkurier-bulk-modal' ).show();
			loadModalContent( content );
		}
		
		function createModal() {
			const modalHtml = `
				<div id='globkurier-bulk-modal'>
					<div class='modal-overlay'>
						<div class='modal-content'>
							<div class="modal-header">
								<h2 class="modal-title">Nadaj przez GlobKurier</h2>
								<button type="button" class="modal-close">&times;</button>
							</div>
							<div class="modal-body"></div>
						</div>
					</div>
				</div>
            `;
			
			$( 'body' ).append( modalHtml );
		}
		
		function loadModalContent( content ) {
			const $modalContent = $( '#globkurier-bulk-modal .modal-content .modal-body' );
			$modalContent.html( content );
			
			$( document ).find( '.udi-select2#udi-select-inpost-sender' ).select2( {
				placeholder: 'Znajdź punkt nadania',
				language: {
					searching: function () {
						return 'Szukaj Paczkomat InPost';
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
					url: wpopieka_globkurier_bulk_send_data.ajax_url,
					dataType: 'json',
					data: function ( params ) {
						return {
							city: params.term,
							nonce: $( '#globkurier_get_inpost_points_select2_nonce' ).val(),
							action: 'globkurierGetInpostPointsSelect2',
						};
					},
					processResults: function ( data ) {
						let parsedData = JSON.parse( data );
						
						var options = [];
						if ( parsedData ) {
							$.each( parsedData, function ( index, data ) {
								options.push( { id: data.id, text: data.label } );
							} );
						}
						return {
							results: options
						};
					},
					cache: true
				},
				minimumInputLength: 3,
			} );
			
			$( document ).find( '.udi-select2#udi-select-inpost-sender' ).on( 'select2:select', function ( e ) {
				$( '#globkurier_inpost_input' ).val( e.params.data.text );
			} );
			
			$( document ).find( '#globkurier-service-date-picker' ).datepicker( {
				minDate: 0,
				dateFormat: 'yy-mm-dd',
				beforeShowDay: $.datepicker.noWeekends
			} );
			
			$( '[name="globkurier-pickup-type"]' ).change();
			
			$( document ).on( 'change', '[name="globkurier-pickup-type"]', function () {
				let pickupType = $( this ).data( 'pickuptype' );
				
				if ( pickupType == 'POINT' ) {
					$( '.globkurier-not-pickup' ).hide();
				}
				
				if ( pickupType == 'PICKUP' ) {
					$( '.globkurier-not-pickup' ).show();
				}
				
			} );
			
			$( document ).find( '.udi-select2#udi-select-ruch' ).select2( {
				placeholder: 'Znajdź punkt dostawy',
				language: {
					searching: function () {
						return 'Szukaj Paczka w RUCHu';
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
					url: wpopieka_globkurier_bulk_send_data.ajax_url,
					dataType: 'json',
					data: function ( params ) {
						return {
							city: params.term,
							action: 'globkurierGetRuchPointsSelect2',
							product_id: $( '#udi-selected-product-id' ).val(),
							nonce: $( '#globkurier_get_ruch_points_select2_nonce' ).val(),
						};
					},
					processResults: function ( data ) {
						
						let parsedData = JSON.parse( data );
						
						var options = [];
						if ( parsedData ) {
							$.each( parsedData, function ( index, data ) {
								options.push( { id: data.id, text: data.label } );
							} );
						}
						return {
							results: options
						};
					},
					cache: true
				},
				minimumInputLength: 3,
			} );
			
			$( document ).find( '.udi-select2#udi-select-ruch' ).on( 'select2:select', function ( e ) {
				$( '#globkurier_ruch_input' ).val( e.params.data.text );
			} );
		}
		
		function closeModal() {
			$( '#globkurier-bulk-modal' ).hide();
			
			if ( gkOpenModalRequest ) {
				gkOpenModalRequest.abort();
				gkOpenModalRequest = null;
			}
		}
		
		function getLoaderContent() {
			return `
				<div class="loading">
					Pobieranie możliwych metod wysyłki...
				</div>
			`;
		}
		
		function getErrorContent( message ) {
			return `
				<div class="modal-header">
					<h2 class="modal-title">Nadaj przez GlobKurier</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<div class="modal-body">
					<div style="color: #dc2626; padding: 20px; text-align: center;">
						${ message }
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary modal-close">
						Zamknij
					</button>
				</div>
			`;
		}
		
		$( document ).on( 'click', '.udi-select-carrier-bulk', function () {
			$( '#gk-bulk-step-1' ).hide();
			
			let carierId = $( this ).data( 'carrierid' );
			
			bulkSelectCarrier( carierId, $( this ) );
		} );
		
		function bulkSelectCarrier( carrierId, _this ) {
			$('#gk-bulk-step-2').show()
			
			let collectionTypes = _this.data( 'collectiontypes' ).split( ',' );
			
			$.each( collectionTypes, function ( index, pickupType ) {
				let radio = $( '#globkurier-pickup-type-' + pickupType );
				
				if ( index === 0 ) {
					radio.prop( 'checked', true );
				}
				
				if ( collectionTypes.length > 1 ) {
					radio.attr( 'disabled', false );
				}
			} );
			
			$( '.udi-category-required' ).removeClass( 'udi-category-required' );
			
			$( '#udi-selected-product-id' ).val( carrierId );
			
			let _isRuch = isRuch( _this );
			let _isInpost = isInpost( _this );
			let _isCrossborder = isCrossborder( _this );

			// Czy usluga dostarcza do punktu odbioru (POINT) czy do drzwi (brak POINT w deliveryTypeOptions)
			let deliveryTypes = ( _this.data( 'deliverytypes' ) || '' ).toString().split( ',' );
			let inpostDeliveryToPoint = deliveryTypes.indexOf( 'POINT' ) !== -1 ? 1 : 0;

			if ( $( document ).find( '#udi-select-crossborder_terminal_value' ).data( 'select2' ) ) {
				$( document ).find( '#udi-select-crossborder_terminal_value' ).select2( 'destroy' );
			}

			$( '#udi-selected-product-is-inpost' ).val( _isInpost );
			$( '#udi-selected-product-inpost-delivery-to-point' ).val( inpostDeliveryToPoint );
			$( '#udi-selected-product-is-ruch' ).val( _isRuch );
			$( '#udi-selected-product-is-crossborder' ).val( _isCrossborder );
			
			let carriersDataId = carriersData.findIndex( function ( carrier ) {
				return carrier.id == carrierId;
			} );
			
			let carrierData = carriersData[ carriersDataId ];
			
			let selectProductContainer = $( '.udi-step-select-product' );
			let detailsContainer = $( '.udi-step-select-product-details' );
			
			$( '.globkurier-not-pickup' ).show();
			$( '.globkurier-only-ruch' ).css( 'display', 'none' );
			$( '.globkurier-only-inpost' ).css( 'display', 'none' );
			$( '.globkurier-only-crossborder' ).css( 'display', 'none' );
			
			$( '#globkurier_inpost_input' ).removeClass( 'globkurier-is-required' );
			$( '#globkurier_inpost_input-pickup' ).removeClass( 'globkurier-is-required' );
			$( '#globkurier_ruch_input' ).removeClass( 'globkurier-is-required' );
			
			if ( _isRuch == 1 ) {
				$( '.globkurier-not-pickup' ).hide();
				$( '.globkurier-only-inpost' ).hide();
				
				$( '#globkurier_inpost_input' ).removeClass( 'globkurier-is-required' );
				$( '#globkurier_inpost_input-pickup' ).removeClass( 'globkurier-is-required' );
				$( '#globkurier_ruch_input' ).addClass( 'globkurier-is-required' );
				
				$( '.globkurier-only-ruch' ).css( 'display', 'contents' );
			}
			
			if ( _isInpost == 1 ) {
				$( '.globkurier-not-pickup' ).hide();
				$( '.globkurier-only-ruch' ).hide();
				
				$( '#globkurier_inpost_input' ).addClass( 'globkurier-is-required' );
				$( '#globkurier_inpost_input-pickup' ).addClass( 'globkurier-is-required' );
				$( '#globkurier_ruch_input' ).removeClass( 'globkurier-is-required' );
				
				$( '.globkurier-only-inpost' ).css( 'display', 'contents' );
			}
			
			if ( _isCrossborder == 1 ) {
				$.post(
					wpopieka_globkurier_bulk_send_data.ajax_url,
					{
						action: 'globkurierGetCrossborderTerminals',
						nonce: wpopieka_globkurier_bulk_send_data.crossborder_terminals_nonce,
						productId: carrierId
					},
					function ( response ) {
						let options = [];
						
						if ( response.success ) {
							let terminals = typeof response.data === 'string' ? JSON.parse( response.data ) : response.data;
							
							terminals.forEach( function ( data ) {
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
						
						$( document ).find( '#udi-select-crossborder_terminal_value' ).select2( {
							placeholder: 'Znajdź terminal nadawczy',
							data: options,
							language: {
								searching: function () {
									return 'Znajdź terminal nadawczy';
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
						} );
					}
				);
				
				$( '.globkurier-only-crossborder' ).css( 'display', 'contents' );
			}
			
			detailsContainer.find( '.udi-selected-product-body-name, .udi-selected-product-body-name' ).text( carrierData.carrierName );
			detailsContainer.find( '.udi-product-img' ).attr( 'src', carrierData.carrierLogoLink );
			detailsContainer.find( '.udi-product-price' ).html( carrierData.netPrice.toFixed( 2 ) + 'zł' );
			
			$( '#udi-carrierNetPrice' ).val( carrierData.netPrice.toFixed( 2 ) );
			$( '#udi-carrierGrossPrice' ).val( carrierData.grossPrice.toFixed( 2 ) );
			
			detailsContainer.find( '.udi-selected-product-description' ).text( carrierData.name );
			
			selectProductContainer.hide();
			
			getCustomRequiredFields();
			
			detailsContainer.show();
			
			getFirstAvailablePickupDay();
			
			let orderData = getData( 0 );
			
			let ajaxData = {
				action: 'globkurierGetProductAddons',
				
				data: {
					productId: orderData.productId,
					'height': orderData.height,
					'width': orderData.width,
					'length': orderData.length,
					'weight': orderData.weight,
					'quantity': orderData.quantity,
					'receiverCountryId': orderData.receiverCountryId,
					'receiverPostCode': orderData.receiverPostal,
					'senderCountryId': orderData.senderCountryId,
					'senderPostCode': orderData.senderPostal,
					'nonce': $( '#globkurier_get_product_addons_nonce' ).val(),
				}
			};
			
			let loader = $( ' .udi-product-extras-header .udi-loader' );
			loader.show();
			
			getPrice( true );
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				let container = $( '.udi-product-extras-body' );
				
				$( '#udi-extra-category-cod' ).hide();
				$( '#udi-extra-category-insurance' ).hide();
				$( '#udi-extra-category-other' ).hide();
				$( '#udi-extra-category-receiver' ).hide();
				
				let cod = $( '#udi-extra-category-cod .udi-product-extras-cat-items' );
				let insurance = $( '#udi-extra-category-insurance  .udi-product-extras-cat-items' );
				let other = $( '#udi-extra-category-other .udi-product-extras-cat-items' );
				let receiver = $( '#udi-extra-category-receiver .udi-product-extras-cat-items' );
				
				cod.html( '' );
				insurance.html( '' );
				other.html( '' );
				receiver.html( '' );
				
				let addons = $.parseJSON( response );
				
				$( addons.addons ).each( function ( key, addon ) {
					let required = '';
					let requiredClass = '';
					if ( addon.isRequired ) {
						required = ' checked readonly title="Dodatek jest wymagany" ';
						requiredClass = 'udi-product-extras-input-required';
					}
					
					let priceDescription = '';
					
					if ( addon.priceDescription ) {
						priceDescription = '<br/><span style="font-size: x-small">(' + gkEsc( addon.priceDescription ) + ')</span>';
					}
					
					// addon.tooltip to celowo HTML: wc_help_tip() budowany i sanityzowany po stronie serwera (GlobKurierAddons::productAddons).
					let extra = '<div class="udi-product-extra udi-extra-' + gkEsc( addon.id ) + '">\n\t<div class="udi-product-extra-col1">\n\t\t<input ' + required + ' class="udi-product-extras-input ' + requiredClass + '" name="udi-product-extras[' + gkEsc( addon.category ) + '][' + gkEsc( addon.id ) + '][]"  data-minval="' + gkEsc( ( addon.minValue || '' ) ) + '" data-maxval="' + gkEsc( ( addon.maxValue || '' ) ) + '"  data-addonName="' + gkEsc( addon.addonName ) + '" data-price="' + addon.price + '" data-id="' + gkEsc( addon.id ) + '" data-category="' + gkEsc( addon.category ) + '" value="' + gkEsc( addon.id ) + '" id="udi-product-extra' + gkEsc( addon.id ) + '" type="checkbox"></div>\n\t<div class="udi-product-extra-col2"><label for="udi-product-extra' + gkEsc( addon.id ) + '">' + gkEsc( addon.addonName ) + '</label>' + priceDescription + '</div>\n\t<div class="udi-product-extra-col3">' + ( addon.tooltip || '' ) + '+' + addon.price.toFixed( 2 ) + 'zł</div>\n</div>';
					
					switch ( addon.category ) {
						case 'CASH_ON_DELIVERY':
							$( '#udi-extra-category-cod' ).show();
							cod.append( extra );
							break;
						case 'INSURANCE':
							$( '#udi-extra-category-insurance' ).show();
							insurance.append( extra );
							break;
						case 'RECEIVER_TYPE_PRIVATE_PERSON':
							$( '#udi-extra-category-receiver' ).show();
							receiver.append( extra );
							break;
						case 'RECEIVER_TYPE_COMPANY':
							$( '#udi-extra-category-receiver' ).show();
							receiver.append( extra );
							break;
						default:
							$( '#udi-extra-category-other' ).show();
							other.append( extra );
							break;
					}
					
					if ( addon.isRequired ) {
						$( '[name="udi-product-extras[' + addon.category + '][' + addon.id + '][]"]' ).change();
					}
					
				} );
				
				$( '#globkurier-requiredAlternativeAddonsGroups' ).val( addons.requiredAlternativeAddonsGroups.toString() );
				
				let i = 0;
				
				$( addons.requiredAlternativeAddonsGroups[ 0 ] ).each( function ( key, id ) {
					$( '.udi-extra-' + id + ' .udi-product-extra-col1 input, .udi-extra-' + id + ' .udi-product-extra-col2' ).addClass( 'udi-required-addon' );
					$( '.udi-extra-' + id + ' .udi-product-extra-col2' ).append( '<span style="cursor: help" title="Dodatek z grupy wymaganych dodatków">*</span>' );
					
					let categoryHeader = $( '.udi-extra-' + id ).parents( '.udi-product-extras-category' ).find( '.udi-product-extras-category-header' );
					
					if ( !categoryHeader.hasClass( 'udi-category-required' ) ) {
						categoryHeader.addClass( 'udi-category-required' );
					}
					
					if ( i == 0 ) {
						$( '.udi-product-extras-input[value=' + id + ']' ).prop( 'checked', true ).change();
					} else {
						$( '.udi-product-extras-input[value=' + id + ']' ).prop( 'readonly', true ).change();
					}
					
					i++;
				} );
				
				$( ".udi-product-extra-col3 .woocommerce-help-tip" ).tipTip( {
					attribute: "data-tip",
					fadeIn: 50,
					fadeOut: 50,
					delay: 200
				} );
				
				loader.hide();
				container.show();
			} );
		}
		
		function isInpost( product ) {
			return ((product.data('carriername') || '').toLowerCase() == 'inpost-paczkomaty') ? 1 : 0;
		}
		
		function isRuch(product) {
			return ((product.data('carriername') || '').toLowerCase() == 'orlen paczka') ? 1 : 0;
		}
		
		function isCrossborder(product) {
			return (product.data('collectiontypes')
				&& product.data('collectiontypes').toUpperCase().includes('CROSSBORDER')) ? 1 : 0;
		}
		
		function getCustomRequiredFields() {
			let orderData = getData( 0 );
			
			let ajaxData = {
				action: 'globkurierCustomRequiredFields',
				
				data: {
					'productId': orderData.productId,
					'senderCountryId': orderData.senderCountryId,
					'receiverCountryId': orderData.receiverCountryId,
					'collectionType': orderData.collectionType,
					'nonce': $( '#globkurier_get_custom_required_fields_nonce' ).val(),
				}
			};
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				
				let results = $.parseJSON( response );
				
				let container = $( '#globkurier-custom-fields-required' );
				
				container.html( '' );
				
				if ( results.length > 0 ) {
					
					$( results ).each( function () {
						
						let f = $( this )[ 0 ];
						
						let field = '';
						
						if(f.name == 'receiverAddressPointId'){
							return;
						}
						
						switch ( $( this )[ 0 ].type ) {
							case 'text':
								field = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-insurance-value" class="udi-options-body-label">' + gkEsc( f.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t<input type="text" value="" class="globkurier-is-required" style="flex-basis: 100%;" id="globkurier-custom-extra-required-' + gkEsc( f.name ) + '">\n\t</div>\n</div>';
								break;
							case 'number':
								let min = '';
								let max = '';
								
								if ( $( this )[ 0 ].min ) {
									min = 'min="' + gkEsc( $( this )[ 0 ].min ) + '"';
								}
								
								if ( $( this )[ 0 ].max ) {
									max = 'max="' + gkEsc( $( this )[ 0 ].max ) + '"';
								}
								
								field = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-insurance-value" class="udi-options-body-label">' + gkEsc( f.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t<input type="number" ' + min + ' ' + max + ' value="" class="globkurier-is-required" style="flex-basis: 100%;" id="globkurier-custom-extra-required-' + gkEsc( f.name ) + '">\n\t</div>\n</div>';
								break;
							case 'select':
								field = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-insurance-value" class="udi-options-body-label">' + gkEsc( f.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t\n\t\t<select style="width: 100%" name="" id="globkurier-custom-extra-required-' + gkEsc( f.name ) + '" class="globkurier-is-required ' + gkEsc( f.class || '' ) + '">';
								for ( const key in f.options ) {
									field += '<option value="' + gkEsc( ( parseInt( key ) || key.trim() || '' ) ) + '">' + gkEsc( f.options[ key ] ) + '</option>';
								}
								break;
							case 'select2':
								field = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-insurance-value" class="udi-options-body-label">' + gkEsc( f.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t\n\t\t<select style="width: 100%" name="" id="globkurier-custom-extra-required-' + gkEsc( f.name ) + '" class="globkurier-is-required ' + gkEsc( f.class || '' ) + '">';
								for ( const key in f.options ) {
									field += '<option value="' + gkEsc( ( parseInt( key ) || key.trim() || '' ) ) + '">' + gkEsc( f.options[ key ] ) + '</option>';
								}
								break;
							default:
								return;
						}
						
						container.append( field );
						
						if ( $( this )[ 0 ].type == 'select2' ) {
							$( document ).find( /^[A-Za-z0-9_-]+$/.test( f.class || '' ) ? '.' + f.class : [] ).select2( {
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
									url: wpopieka_globkurier_bulk_send_data.ajax_url,
									dataType: 'json',
									data: function ( search ) {
										return {
											city: search.term,
											action: 'globkurierGetExtraPickupsPointsSelect2',
											productId: orderData.productId,
											countryId: orderData.receiverCountryId
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
							} );
						}
					} );
					
				}
				
			} );
			
		}
		
		function getFirstAvailablePickupDay() {
			
			let orderData = getData( 0 );
			
			let productId = $( '#udi-selected-product-id' ).val();
			
			let ajaxData = {
				action: 'globkurierGetFirstPickupDay',
				
				data: {
					'productId': productId,
					'weight': orderData.weight,
					'quantity': orderData.quantity,
					'receiverCountryId': orderData.receiverCountryId,
					'receiverPostCode': orderData.receiverPostCode,
					'senderCountryId': orderData.senderCountryId,
					'senderPostCode': orderData.senderPostal,
					'date': orderData.date,
					'nonce': $( '#globkurier_get_first_pickup_day_nonce' ).val()
				}
			};
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				let results = $.parseJSON( response );
				if ( results.status == 'ok' ) {
					$( '#globkurier-service-date-picker' ).datepicker( "setDate", new Date( results.date ) );
					
					getPickupTimeRanges();
				}
				$( '#globkurier-service-date-picker' ).prop( 'readonly', false );
			} );
			
		}
		
		function getBulkOrderData( ) {
			
			let isRuch = $( '#udi-selected-product-is-ruch' ).val();
			let isInpost = $( '#udi-selected-product-is-inpost' ).val();
			let inpostDeliveryToPoint = $( '#udi-selected-product-inpost-delivery-to-point' ).val();

			let extraPickupCarrierId = $( '#globkurier_extraPickupCarrierId' ).val();
			let extraPickupCarrierValue = $( '#globkurier_extraPickupCarrierValue' ).val();
			let extraPickupCarrierText = $( '#globkurier_extraPickupCarrierText' ).val();

			let wcOrderID = $( '#udi-wc-order-id' ).val();
			let productId = $( '#udi-selected-product-id' ).val();
			let description = $( '#globkurier-content' ).val();

			if ( description == 'Inne' ) {
				description = $( '#globkurier-otherContent' ).val();
			}

			let skuContent = $( '#sku_content' ).val();


			let heights = {};
			$("input[name^='globkurier-height[']").each(function() {
				let name = $(this).attr('name');
				let match = name.match(/\[(.*?)\]/);
				if(match) {
					let orderId = match[1];
					heights[orderId] = $(this).val();
				}
			});
			
			let widths = {};
			$("input[name^='globkurier-width[']").each(function() {
				let name = $(this).attr('name');
				let match = name.match(/\[(.*?)\]/);
				if(match) {
					let orderId = match[1];
					widths[orderId] = $(this).val();
				}
			});
			
			
			let lengths = {};
			$("input[name^='globkurier-length[']").each(function() {
				let name = $(this).attr('name');
				let match = name.match(/\[(.*?)\]/);
				if(match) {
					let orderId = match[1];
					lengths[orderId] = $(this).val();
				}
			});
			
			let weights = {};
			$("input[name^='globkurier-weight[']").each(function() {
				let name = $(this).attr('name');
				let match = name.match(/\[(.*?)\]/);
				if(match) {
					let orderId = match[1];
					weights[orderId] = $(this).val();
				}
			});
			
			let quantities = {};
			$("input[name^='globkurier-quantity[']").each(function() {
				let name = $(this).attr('name');
				let match = name.match(/\[(.*?)\]/);
				if(match) {
					let orderId = match[1];
					quantities[orderId] = $(this).val();
				}
			});
			
			
			let paymentId = $( '#globkurier-service-payment-picker' ).val();
			
			let collectionType = $( '[name="globkurier-pickup-type"]:checked' ).val();
			
			let inpostSenderPointId = '';
			let inpostReceiverPointId = '';
			let ruchReceiverPointId = '';
			
			if ( parseInt( isInpost ) == 1 ) {
				inpostSenderPointId = $( '#udi-select-inpost-sender' ).val();
				inpostReceiverPointId = $( '#udi-select-inpost-pickup_value' ).val();
			} else if ( parseInt( isRuch ) == 1 ) {
				ruchReceiverPointId = $( '#udi-select-ruch' ).val();
			}
			
			let date = $( '#globkurier-service-date-picker' ).val();
			let time = $( '#globkurier-service-time-picker' ).val();
			
			let notices = '';
			
			let addons = $( '.udi-product-extras-input:checked' ).serialize();
			
			let insurance = $( '#globkurier-addon-extra-insurance-value' ).val();
			
			let cod_value = $( '#globkurier-addon-extra-cod-value' ).val();
			let cod_bankAccountNumber = $( '#globkurier-addon-extra-cod-bankAccountNumber' ).val();
			let cod_name = $( '#globkurier-addon-extra-cod-name' ).val();
			let cod_addressLine1 = $( '#globkurier-addon-extra-cod-addressLine1' ).val();
			let cod_addressLine2 = $( '#globkurier-addon-extra-cod-addressLine2' ).val();
			
			let rod_content = $( '#globkurier-addon-extra-rod-content' ).val();
			let rod_quantity = $( '#globkurier-addon-extra-rod-quantity' ).val();
			let rod_description = $( '#globkurier-addon-extra-rod-description' ).val();
			
			let swa_name = $( '#globkurier-addon-extra-swa-name' ).val();
			let swa_surname = $( '#globkurier-addon-extra-swa-surname' ).val();
			let swa_houseNumber = $( '#globkurier-addon-extra-swa-houseNumber' ).val();
			let swa_apartmentNumber = $( '#globkurier-addon-extra-swa-apartmentNumber' ).val();
			let swa_street = $( '#globkurier-addon-extra-swa-street' ).val();
			let swa_city = $( '#globkurier-addon-extra-swa-city' ).val();
			let swa_postCode = $( '#globkurier-addon-extra-swa-postCode' ).val();
			let swa_countryId = $( '#globkurier-addon-extra-swa-countryId' ).val();
			let swa_phone = $( '#globkurier-addon-extra-swa-phone' ).val();
			let swa_email = $( '#globkurier-addon-extra-swa-email' ).val();
			let swa_type = $( '#globkurier-addon-extra-swa-type' ).val();
			
			let carrier_name = $( '.udi-selected-product-body-name' ).html();
			
			let payment_name = $( '#globkurier-service-payment-picker option:selected' ).text();
			
			let declaredValue = $( '#globkurier-custom-extra-required-declaredValue' ).val();
			let purpose = $( '#globkurier-custom-extra-required-purpose' ).val();
			let senderStateId = $( '#globkurier-custom-extra-required-senderStateId' ).val();
			let receiverStateId = $( '#globkurier-custom-extra-required-receiverStateId' ).val();
			
			let containsDangerousGoods = $( '#globkurier-contains-dangerous-goods' ).is( ':checked' ) ? 1 : 0;
			
			let receiverAddressPointId = $( '#globkurier-custom-extra-required-receiverAddressPointId' ).val();
			let senderAddressPointId = $( '#globkurier-custom-extra-required-senderAddressPointId' ).val();
			
			let isCrossborderProduct = $( '#udi-selected-product-is-crossborder' ).val();
			let crossborderTerminal = $( '#udi-select-crossborder_terminal_value' ).val();
			
			let wcOrderIDs = $( '#gk-wc-order-ids' ).val();
			
			let ajaxData = {
				action: 'globkurierBulkOrder',
				data: {
					'wcOrderIDs': wcOrderIDs,
					
					'productId': productId,
					'isRuch': isRuch,
					'isInpost': isInpost,
					'inpostDeliveryToPoint': inpostDeliveryToPoint,

					'extraPickupCarrierId': extraPickupCarrierId,
					'extraPickupCarrierValue': extraPickupCarrierValue,
					'extraPickupCarrierText': extraPickupCarrierText,
					
					'lengths': lengths,
					'widths': widths,
					'heights': heights,
					'weights': weights,
					'quantities': quantities,
					
					'description': description,
					'sku_content': skuContent,
					
					'paymentId': paymentId,
					'collectionType': collectionType,
					
					'inpostSenderPointId': inpostSenderPointId || '',
					
					'date': date,
					'time': time,
					
					'addons': addons,
					
					'insurance': insurance,
					
					'cod_value': cod_value,
					'cod_bankAccountNumber': cod_bankAccountNumber,
					'cod_name': cod_name,
					'cod_addressLine1': cod_addressLine1,
					'cod_addressLine2': cod_addressLine2,
					
					'rod_content': rod_content,
					'rod_quantity': rod_quantity,
					'rod_description': rod_description,
					
					'swa_name': swa_name,
					'swa_surname': swa_surname,
					'swa_houseNumber': swa_houseNumber,
					'swa_apartmentNumber': swa_apartmentNumber,
					'swa_street': swa_street,
					'swa_city': swa_city,
					'swa_postCode': swa_postCode,
					'swa_countryId': swa_countryId,
					'swa_phone': swa_phone,
					'swa_email': swa_email,
					'swa_type': swa_type,
					
					'carrier_name': carrier_name,
					'notices': notices,
					'payment_name': payment_name,
					
					'declaredValue': declaredValue,
					'purpose': purpose,
					'senderStateId': senderStateId,
					'receiverStateId': receiverStateId,
					
					'containsDangerousGoods': containsDangerousGoods,
					
					'receiverAddressPointId': receiverAddressPointId,
					'senderAddressPointId': senderAddressPointId,
					
					'isCrossborder': isCrossborderProduct,
					'crossborderTerminal': crossborderTerminal,
					
					'nonce': $( '#globkurier_order_nonce' ).val(),
				}
			};
			
			return ajaxData;
		}
		
		function getData( orderNo ) {
			let isRuch = $( '#udi-selected-product-is-ruch' ).val();
			let isInpost = $( '#udi-selected-product-is-inpost' ).val();
			
			let extraPickupCarrierId = $( '#globkurier_extraPickupCarrierId' ).val();
			let extraPickupCarrierValue = $( '#globkurier_extraPickupCarrierValue' ).val();
			let extraPickupCarrierText = $( '#globkurier_extraPickupCarrierText' ).val();
			
			let wcOrderID = $( '#udi-wc-order-id' ).val();
			let productId = $( '#udi-selected-product-id' ).val();
			let description = $( '#globkurier-content' ).val();
			
			if ( description == 'Inne' ) {
				description = $( '#globkurier-otherContent' ).val();
			}
			
			let skuContent = $( '#sku_content' ).val();
			
			
			let height = $( `input[data-order-no="${ orderNo }"][name^="globkurier-height"]` ).val();
			let width = $( `input[data-order-no="${ orderNo }"][name^="globkurier-width"]` ).val();
			let length = $( `input[data-order-no="${ orderNo }"][name^="globkurier-length"]` ).val();
			let weight = $( `input[data-order-no="${ orderNo }"][name^="globkurier-weight"]` ).val();
			let quantity = $( `input[data-order-no="${ orderNo }"][name^="globkurier-quantity"]` ).val();
			
			let receiverName = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-name"]` ).val();
			let receiverCountryId = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-country"]` ).val();
			let receiverPostal = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-postal"]` ).val();
			let receiverCity = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-city"]` ).val();
			let receiverStreet = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-street"]` ).val();
			let receiverHome = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-home"]` ).val();
			let receiverFlat = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-flat"]` ).val();
			let receiverPhone = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-contact-phone"]` ).val();
			let receiverEmail = $( `input[data-order-no="${ orderNo }"][name^="globkurier-receiver-email"]` ).val();
			
			let senderName = $( '#globkurier-sender-name' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			let senderCity = $( '#globkurier-sender-city' ).val();
			let senderStreet = $( '#globkurier-sender-street' ).val();
			let senderHome = $( '#globkurier-sender-home' ).val();
			let senderFlat = $( '#globkurier-sender-flat' ).val();
			let senderPhone = $( '#globkurier-sender-contact-phone' ).val();
			let senderEmail = $( '#globkurier-sender-email' ).val();
			
			let paymentId = $( '#globkurier-service-payment-picker' ).val();
			
			let collectionType = $( '[name="globkurier-pickup-type"]:checked' ).val();
			
			let inpostSenderPointId = '';
			let inpostReceiverPointId = '';
			let ruchReceiverPointId = '';
			
			if ( parseInt( isInpost ) == 1 ) {
				inpostSenderPointId = $( '#udi-select-inpost-sender' ).val();
				inpostReceiverPointId = $( '#udi-select-inpost-pickup_value' ).val();
			} else if ( parseInt( isRuch ) == 1 ) {
				ruchReceiverPointId = $( '#udi-select-ruch' ).val();
			}
			
			let date = $( '#globkurier-service-date-picker' ).val();
			let time = $( '#globkurier-service-time-picker' ).val();
			
			let notices = '';
			
			let addons = $( '.udi-product-extras-input:checked' ).serialize();
			
			let insurance = $( '#globkurier-addon-extra-insurance-value' ).val();
			
			let cod_value = $( '#globkurier-addon-extra-cod-value' ).val();
			let cod_bankAccountNumber = $( '#globkurier-addon-extra-cod-bankAccountNumber' ).val();
			let cod_name = $( '#globkurier-addon-extra-cod-name' ).val();
			let cod_addressLine1 = $( '#globkurier-addon-extra-cod-addressLine1' ).val();
			let cod_addressLine2 = $( '#globkurier-addon-extra-cod-addressLine2' ).val();
			
			let rod_content = $( '#globkurier-addon-extra-rod-content' ).val();
			let rod_quantity = 1;//$( '#globkurier-addon-extra-rod-quantity' ).val();
			let rod_description = $( '#globkurier-addon-extra-rod-description' ).val();
			
			let swa_name = $( '#globkurier-addon-extra-swa-name' ).val();
			let swa_surname = $( '#globkurier-addon-extra-swa-surname' ).val();
			let swa_houseNumber = $( '#globkurier-addon-extra-swa-houseNumber' ).val();
			let swa_apartmentNumber = $( '#globkurier-addon-extra-swa-apartmentNumber' ).val();
			let swa_street = $( '#globkurier-addon-extra-swa-street' ).val();
			let swa_city = $( '#globkurier-addon-extra-swa-city' ).val();
			let swa_postCode = $( '#globkurier-addon-extra-swa-postCode' ).val();
			let swa_countryId = $( '#globkurier-addon-extra-swa-countryId' ).val();
			let swa_phone = $( '#globkurier-addon-extra-swa-phone' ).val();
			let swa_email = $( '#globkurier-addon-extra-swa-email' ).val();
			let swa_type = $( '#globkurier-addon-extra-swa-type' ).val();
			
			let carrier_name = $( '.udi-selected-product-body-name' ).html();
			
			let payment_name = $( '#globkurier-service-payment-picker option:selected' ).text();
			
			let declaredValue = $( '#globkurier-custom-extra-required-declaredValue' ).val();
			let purpose = $( '#globkurier-custom-extra-required-purpose' ).val();
			let senderStateId = $( '#globkurier-custom-extra-required-senderStateId' ).val();
			let receiverStateId = $( '#globkurier-custom-extra-required-receiverStateId' ).val();
			
			let receiverAddressPointId = $( '#globkurier-custom-extra-required-receiverAddressPointId' ).val();
			let senderAddressPointId = $( '#globkurier-custom-extra-required-senderAddressPointId' ).val();
			
			let isCrossborderProduct = $( '#udi-selected-product-is-crossborder' ).val();
			let crossborderTerminal = $( '#udi-select-crossborder_terminal_value' ).val();
			
			return {
				'productId': productId,
				
				'senderCountryId': senderCountryId,
				'senderPostal': senderPostal,
				
				'receiverCountryId': receiverCountryId,
				'receiverPostCode': receiverPostal,
				'receiverPostal': receiverPostal,
				
				'collectionType': collectionType,
				
				'date': date,
				'time': time,
				
				'height': height,
				'width': width,
				'length': length,
				'weight': weight,
				'quantity': quantity,
				
				
				'sender_name': senderName,
				'sender_street': senderStreet,
				'sender_home': senderHome,
				'sender_flat': senderFlat,
				'sender_city': senderCity,
				'sender_postCode': senderPostal,
				'sender_email': senderEmail,
				'sender_phone': senderPhone,
				
				'receiver_name': receiverName,
				'receiver_street': receiverStreet,
				'receiver_home': receiverHome,
				'receiver_flat': receiverFlat,
				'receiver_city': receiverCity,
				'receiver_postCode': receiverPostal,
				'receiver_email': receiverEmail,
				'receiver_phone': receiverPhone,
				
				'carrier_name': carrier_name,
				
				'description' : description,
				'payment_name' : payment_name,
			}
		}
		
		function getPrice( refreshPayments = true ) {
			let orderData = getData( 0 );
			
			let addonIds = [];
			$( '.udi-product-extras-input:checked' ).each( function () {
				addonIds.push( $( this ).val() );
			} );
			
			let insuranceValue = $( '#globkurier-addon-extra-insurance-value' ).val();
			let cashOnDeliveryValue = $( '#globkurier-addon-extra-cod-value' ).val();
			
			let ajaxData = {
				action: 'globkurierGetPrice',
				
				data: {
					'productId': orderData.productId,
					'height': orderData.height,
					'width': orderData.width,
					'length': orderData.length,
					'weight': orderData.weight,
					'quantity': orderData.quantity,
					'receiverCountryId': orderData.receiverCountryId,
					'receiverPostCode': orderData.receiverPostCode,
					'senderCountryId': orderData.senderCountryId,
					'senderPostCode': orderData.senderPostal,
					'addonIds': addonIds,
					'nonce': $( '#globkurier_get_price_nonce' ).val(),
				}
			}
			
			if ( parseFloat( insuranceValue ) > 0 ) {
				ajaxData.data.insuranceValue = insuranceValue;
			}
			if ( parseFloat( cashOnDeliveryValue ) > 0 ) {
				ajaxData.data.cashOnDeliveryValue = cashOnDeliveryValue;
			}
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				let price = $.parseJSON( response );
				
				let totalPriceContainerNet = $( '.udi-selected-product-body-price .udi-product-price' );
				let totalPriceContainerGross = $( '.udi-selected-product-body-price .udi-product-price-gross' );
				
				let netPrice = $( '.udi-selected-product-body-price #udi-carrierNetPrice' );
				let grossPrice = $( '.udi-selected-product-body-price #udi-carrierGrossPrice' );
				
				netPrice.val( price.totalNetPrice );
				grossPrice.val( price.totalGrossPrice );
				
				// Suma netto z API (produkt + dodatki + dopłata paliwowa - rabat); sama suma produktu i dodatków pomijała dopłatę paliwową.
				let netPriceWithAddons = parseFloat( price.totalNetPrice );
				if ( isNaN( netPriceWithAddons ) ) {
					netPriceWithAddons = parseFloat( price.productNetPrice ) + parseFloat( price.addonsNetPrice );
				}
				let totalGrossPrice = parseFloat( price.totalGrossPrice );
				
				$( '#udi-carrierNetPriceWithAddons' ).val( parseFloat( netPriceWithAddons ).toFixed( 2 ) );
				$( '#udi-carrierGrossPriceWithAddons' ).val( parseFloat( totalGrossPrice ).toFixed( 2 ) );
				
				totalPriceContainerNet.html( netPriceWithAddons.toFixed( 2 ) + 'zł (netto)' );
				totalPriceContainerGross.html( totalGrossPrice.toFixed( 2 ) + 'zł (brutto)' );
				
				if ( refreshPayments ) {
					getPayments( price );
				}
			} );
			
		}
		
		function getPayments( payment ) {
			let container = $( '#globkurier-service-payment-picker' );
			container.html( '' );
			
			let productId = $( '#udi-selected-product-id' ).val();
			
			let ajaxData = {
				action: 'globkurierGetPayments',
				
				data: {
					'productId': productId,
					'grossOrderPrice': payment.totalGrossPrice,
					'nonce': $( '#globkurier_get_payments_nonce' ).val(),
				}
			};
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				let payments = $.parseJSON( response );
				
				container.html( '' );
				
				if ( payments.length == 0 ) {
					let option = '<option disabled selected value="0">-- Brak dostępnych opcji płatności --</option>';
					container.append( option );
				} else {
					$( payments ).each( function ( key, value ) {
						if ( value.enabled == true ) {
							let selected = '';
							
							if ( value.default == true ) {
								selected = 'selected';
							}
							
							let option = '<option value="' + gkEsc( value.id ) + '" ' + selected + '>' + gkEsc( value.name ) + '</option>';
							container.append( option );
						}
					} )
				}
			} );
		}
		
		function getPickupTimeRanges() {
			
			let orderData = getData( 0 );
			
			let ajaxData = {
				action: 'globkurierGetPickupTimeRanges',
				
				data: {
					'productId': orderData.productId,
					'weight': orderData.weight,
					'quantity': orderData.quantity,
					'receiverCountryId': orderData.receiverCountryId,
					'receiverPostCode': orderData.receiverPostal,
					'senderCountryId': orderData.senderCountryId,
					'senderPostCode': orderData.senderPostal,
					'date': orderData.date,
					'nonce': $( '#globkurier_get_pickup_time_ranges_nonce' ).val()
				}
			};
			
			let container = $( '#globkurier-service-time-picker' );
			container.html( '' );
			
			$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
				let times = $.parseJSON( response );
				
				if ( times.length == 0 ) {
					let option = '<option disabled selected value="0">-- Brak opcji odbioru w wybranym dniu --</option>';
					container.append( option );
				} else {
					$( times ).each( function ( key, time ) {
						let option = '<option>' + gkEsc( time.timeFrom ) + ' - ' + gkEsc( time.timeTo ) + '</option>';
						container.append( option );
					} )
				}
			} );
		}
		
		$( document ).on( 'change', '.udi-product-extras-input', function () {
			if ( $( this ).hasClass( 'udi-product-extras-input-required' ) ) {
				$( this ).prop( 'checked', true );
				getAddonExtraFields( $( this ) );
				calculatePriceWithExtras();
				return false;
			}
			
			let category = $( this ).data( 'category' );
			
			if ( $( this ).is( '[readonly]' ) ) {
				let category_ = category;
				
				if ( category == 'RECEIVER_TYPE_PRIVATE_PERSON' ) {
					category_ = 'RECEIVER_TYPE_COMPANY';
				}
				if ( category == 'RECEIVER_TYPE_COMPANY' ) {
					category_ = 'RECEIVER_TYPE_PRIVATE_PERSON';
				}
				
				$( '[data-category="' + category_ + '"]' ).not( $( this ) ).prop( 'checked', false );
			}
			
			if ( category == 'CASH_ON_DELIVERY' ) {
				if ( $( '.udi-product-extras-input[data-category="INSURANCE"]:checked' ).length == 0 ) {
					let maxValue = $( this ).data( 'maxval' );
					
					let insuranceCheckbox = $( '.udi-product-extras-input[data-category="INSURANCE"][data-maxval=' + maxValue + ']' );
					
					if ( insuranceCheckbox.length > 0 ) {
						insuranceCheckbox.prop( 'checked', true ).change();
					} else {
						$( '.udi-product-extras-input[data-category="INSURANCE"]' ).each( function ( index ) {
							if ( $( this ).data( 'maxval' ) >= maxValue ) {
								$( this ).prop( 'checked', true ).change();
								return false;
							}
						} );
					}
				}
			}
			
			if ( $( this ).hasClass( 'udi-required-addon' ) ) {
				if ( $( this ).is( ':checked' ) ) {
					$( 'input.udi-required-addon:checked' ).prop( 'checked', false );
					$( $( this ).prop( 'checked', true ) );
				} else {
					$( 'input.udi-required-addon:checked' ).prop( 'checked', false );
					$( 'input.udi-required-addon' ).not( $( this ) ).first().prop( 'checked', true ).change();
				}
			}
			
			getAddonExtraFields( $( this ) );
			calculatePriceWithExtras();
		} );
		
		function getAddonExtraFields( addon ) {
			let category = addon.data( 'category' );
			let isActive = addon.is( ':checked' );
			
			let container = $( '#globkurier-service-extra-fields-' + category );
			container.html( '' );
			
			let fields = $( '.udi-wpadmin-order-address input, .udi-wpadmin-order-address select' );
			
			let addressData = fields.attr( 'disabled', false ).serialize();
			
			fields.attr( 'disabled', true );
			
			let minValue = addon.data( 'minval' );
			let maxValue = addon.data( 'maxval' );
			
			let ajaxData = {
				action: 'globkurierGetProductAddonFields',
				
				data: {
					'category': category,
					'address': addressData,
					'minValue': minValue,
					'maxValue': maxValue,
					'nonce': $( '#globkurier_get_product_addon_fields_nonce' ).val(),
				}
			};
			
			let categoryTitle;
			let requiredTitle;
			let deliveryTitle;
			
			if ( isActive ) {
				deliveryTitle = 'Tylko jeden dodatek opcji dostawy jest dozwolony';
				categoryTitle = 'Tylko jeden dodatek z tej grupy jest dozwolony';
				requiredTitle = 'Tylko jeden dodatek z grupy wymaganych jest dozwolony';
			} else {
				deliveryTitle = '';
				categoryTitle = '';
				requiredTitle = '';
			}
			
			let requiredAlternativeAddonsGroups = $( '#globkurier-requiredAlternativeAddonsGroups' ).val().split( ',' ).map( Number );
			
			if ( requiredAlternativeAddonsGroups.indexOf( addon.data( 'id' ) ) != -1 ) {
				$( requiredAlternativeAddonsGroups ).each( function ( key, val ) {
					$( '.udi-product-extras-input[data-id="' + val + '"]' ).not( addon ).attr( 'readonly', isActive ).attr( 'title', requiredTitle );
				} );
			}
			
			if ( [ 'SATURDAY_DELIVERY', 'ON_TIME_DELIVERY', 'ON_TIME_DELIVERY_EVENING' ].indexOf( category ) != -1 ) {
				$( '[data-category="SATURDAY_DELIVERY"], [data-category="ON_TIME_DELIVERY"], [data-category="ON_TIME_DELIVERY_EVENING"]' ).not( addon ).attr( 'disabled', isActive ).attr( 'title', deliveryTitle );
			} else {
				$( '[data-category="' + category + '"]' ).not( addon ).attr( 'readonly', isActive ).attr( 'title', categoryTitle ).attr( 'title', categoryTitle );
			}
			
			if ( isActive ) {
				$( '.udi-save-order' ).attr( 'disabled', true );
				
				addon.attr( 'disabled', true );
				
				$.post( wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function ( response ) {
					
					let fields = $.parseJSON( response );
					
					let container = $( '#globkurier-service-extra-fields-' + category );
					container.html( '' );
					
					if ( fields.length > 0 ) {
						$( fields ).each( function ( key, field ) {
							let input;
							
							let required = '';
							if ( field.required ) {
								required = ' class="globkurier-is-required"';
							}
							
							let value = '';
							if ( field.value ) {
								value = field.value;
							}
							
							let help = '';
							if ( field.help ) {
								help = field.help;
							}
							
							let type = 'text';
							if ( field.type ) {
								type = field.type;
							}
							
							let min, max;
							
							if ( field.min ) {
								min = 'data-minvalue="' + gkEsc( field.min ) + '"';
							}
							
							if ( field.max ) {
								max = 'data-maxvalue="' + gkEsc( field.max ) + '"';
							}
							
							if ( field.type === 'select' ) {
								input = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-' + gkEsc( field.name ) + '" class="udi-options-body-label">' + gkEsc( field.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t<select ' + required + ' style="flex-basis: 100%;" data-label="' + gkEsc( field.label ) + '" id="globkurier-addon-extra-' + gkEsc( field.name ) + '" type="text">\n\t\t\t' + field.options + '\n\t\t</select>\n\t\n<small>' + gkEsc( help ) + '</small></div></div>';
							} else {
								input = '<div style="display: contents;">\n\t<label for="globkurier-addon-extra-' + gkEsc( field.name ) + '" class="udi-options-body-label">' + gkEsc( field.label ) + ':</label>\n\t<div class="udi-input-with-notice">\n\t\t<input data-label="' + gkEsc( field.label ) + '" type="' + gkEsc( type ) + '" ' + min + ' ' + max + ' value="' + gkEsc( value ) + '"' + required + ' style="flex-basis: 100%;" id="globkurier-addon-extra-' + gkEsc( field.name ) + '" type="text"></input>\n\t\n<small>' + gkEsc( help ) + '</small></div></div>';
							}
							container.append( input );
						} );
					}
					
					addon.attr( 'disabled', false );
					
					$( '.udi-save-order' ).attr( 'disabled', false );
				} );
			}
		}
		
		function calculatePriceWithExtras() {
			getPrice( false );
		}
		
		function globkurier_add_error( msg, field ) {
			field.addClass( 'udi-error' );
		}
		
		function showNoticesError() {
			let container = $( '.globkurier-notices-error' ).show();
		}
		function addNoticeError( notice ) {
			let container = $( '.globkurier-notices-error-body' ).append( notice );
		}
		function hideNoticesError() {
			let container = $( '.globkurier-notices-error' ).hide();
		}
		function clearNoticesError() {
			let container = $( '.globkurier-notices-error-body' ).html( '' );
		}
		
		function clearNoticesSuccess() {
			let container = $( '.globkurier-notices-success-body' ).html( '' );
		}
		function hideNoticesSuccess() {
			let container = $( '.globkurier-notices-success' ).hide();
		}
		function addNoticeSuccess( notice ) {
			let container = $( '.globkurier-notices-success-body' ).append( notice );
		}
		function showNoticesSuccess() {
			let container = $( '.globkurier-notices-success' ).show();
		}
		
		function validateOrder() {
			clearNoticesError();
			
			let valueErrors = 0;
			
			$( '.globkurier-is-required' ).each( function () {
				
				let value = $( this ).val();
				
				if ( $( this ).data( 'minvalue' ) ) {
					if ( value < $( this ).data( 'minvalue' ) ) {
						globkurier_add_error( '', $( this ) );
						addNoticeError( '<p>Wartość minimalna pola <strong>' + $( this ).data( 'label' ) + '</strong> to ' + $( this ).data( 'minvalue' ) + 'zł</p>' );
						valueErrors++;
					}
				}
				
				if ( $( this ).data( 'maxvalue' ) ) {
					if ( value > $( this ).data( 'maxvalue' ) ) {
						globkurier_add_error( '', $( this ) );
						addNoticeError( '<p>Wartość maksymalna pola <strong>' + $( this ).data( 'label' ) + '</strong> to ' + $( this ).data( 'maxvalue' ) + 'zł</p>' );
						valueErrors++;
					}
				}
			} );
			
			// GlobKurier nie przyjmuje towarow niebezpiecznych - bez tego blad zwracalo dopiero API GK.
			if ( $( '#globkurier-contains-dangerous-goods' ).is( ':checked' ) ) {
				globkurier_add_error( '', $( '#globkurier-contains-dangerous-goods' ) );
				addNoticeError( '<p><strong>Towary niebezpieczne</strong>: GlobKurier nie przyjmuje towarów niebezpiecznych - usuń je z przesyłki i odznacz to pole.</p>' );
				valueErrors++;
			}
			
			let errors = 0;
			
			$( '.udi-service-options-body .globkurier-is-required' ).each( function () {
				if ( $( this ).val() == '' ) {
					globkurier_add_error( '', $( this ) );
					errors++;
				}
			} );
			
			if ( errors > 0 ) {
				addNoticeError( '<p>Uzupełnij wszystkie wymagane pola</p>' );
				showNoticesError();
				
				$( '.udi-save-order .udi-loader' ).hide();
				$( '.udi-save-order' ).attr( 'disabled', false );
				
				return false;
			} else {
				
				if ( valueErrors > 0 ) {
					showNoticesError();
					$( '.udi-save-order .udi-loader' ).hide();
					$( '.udi-save-order' ).attr( 'disabled', false );
					return false;
				} else {
					clearNoticesError();
					hideNoticesError();
				}
				
			}
			return true;
		}
		
		$( document ).on( 'click', '.udi-save-order', function () {
			if ( !validateOrder() ) {
				return;
			}
			
			$('#gk-bulk-step-3').show();
			
			let data = getData(0);
			
			$( '.udi-save-order' ).attr( 'disabled', true );
			$( '.udi-wpadmin-order-address' ).hide();
			$( '.udi-step-select-product-details' ).hide();
			
			$( '.udi-confirm-address-sender-name' ).text( data.sender_name );
			$( '.udi-confirm-address-sender-street' ).text( data.sender_street );
			$( '.udi-confirm-address-sender-homeNumber' ).text( data.sender_home );
			$( '.udi-confirm-address-sender-flatNumber' ).text( data.sender_flat );
			$( '.udi-confirm-address-sender-city' ).text( data.sender_city );
			$( '.udi-confirm-address-sender-postal' ).text( data.sender_postCode );
			$( '.udi-confirm-address-sender-flnames' ).text( data.sender_name );
			$( '.udi-confirm-address-sender-phone' ).text( data.sender_phone );
			$( '.udi-confirm-address-sender-email' ).text( data.sender_email );
			
			var count = parseInt( $( '[name="gk_orders_count"]' ).val() );
			for ( var orderId = 0; orderId < count; orderId++ ) {
				updateOrderAddress( orderId );
			}
			
			$( '.udi-confirm-product-name' ).text( data.carrier_name + ':' );
			$( '.udi-confirm-product-count .udi-confirm-value' ).text( data.quantity );
			$( '.udi-confirm-product-date .udi-confirm-value' ).text( data.date );
			$( '.udi-confirm-product-time .udi-confirm-value' ).text( data.time );
			$( '.udi-confirm-product-content .udi-confirm-value' ).text( data.description );
			$( '.udi-confirm-product-terminal' ).hide();
			$( '.udi-confirm-payment-product-name' ).text( data.carrier_name );
			$( '.udi-confirm-product-price' ).html( $( '#udi-carrierNetPrice' ).val() + 'zł' );
			$( '.udi-confirm-payment-method' ).text( data.payment_name );
			
			let totalNetPrice = $( '#udi-carrierNetPriceWithAddons' ).val() + 'zł (netto)';
			let totalGrossPrice = $( '#udi-carrierGrossPriceWithAddons' ).val() + 'zł (brutto)';
			$( '.udi-confirm-payment-total-price' ).html( totalNetPrice + ' / ' + totalGrossPrice );
			
			let extraContainer = $( '.udi-confirm-extras' );
			extraContainer.html( '' );
			let selectedExtras = $( '.udi-product-extras-input:checked' );
			
			$( selectedExtras ).each( function () {
				let name = $( this ).data( 'addonname' );
				let price = $( this ).data( 'price' );
				let category = $( this ).data( 'category' );
				
				let extraHtml = '<div class="udi-confirm-extra-name">' + $( '<div>' ).text( name ).html() + ': <span class="udi-confirm-extra-price">' + price.toFixed( 2 ) + 'zł</span></div>';
				
				switch ( category ) {
					case 'CASH_ON_DELIVERY':
						extraHtml += '<div class="udi-confirm-extra-name" style="margin-left: 15px">Kwota pobrania: <span class="udi-confirm-extra-price">' + parseFloat( $( '#globkurier-addon-extra-cod-value' ).val() ).toFixed( 2 ) + 'zł</span></div>';
						break;
					case 'INSURANCE':
						extraHtml += '<div class="udi-confirm-extra-name" style="margin-left: 15px">Kwota ubezpieczenia: <span class="udi-confirm-extra-price">' + parseFloat( $( '#globkurier-addon-extra-insurance-value' ).val() ).toFixed( 2 ) + 'zł</span></div>';
						break;
					default:
						break;
				}
				
				extraContainer.append( extraHtml );
			} );
			
			$( '.udi-order-confirm-container' ).show();
			$( '.udi-order-confirm-container-body' ).show();
		} );
		
		function updateOrderAddress( orderId ) {
			const data = getData( orderId );
			
			const mapping = {
				'udi-confirm-address-receiver-name': data.receiver_name,
				'udi-confirm-address-receiver-street': data.receiver_street,
				'udi-confirm-address-receiver-homeNumber': data.receiver_home,
				'udi-confirm-address-receiver-flatNumber': data.receiver_flat,
				'udi-confirm-address-receiver-city': data.receiver_city,
				'udi-confirm-address-receiver-postal': data.receiver_postCode,
				'udi-confirm-address-receiver-flnames': data.receiver_name,
				'udi-confirm-address-receiver-email': data.receiver_email,
				'udi-confirm-address-receiver-phone': data.receiver_phone
			};
			
			Object.entries( mapping ).forEach( function ( [ key, value ] ) {
				var selector = '[data-target="' + key + '[' + orderId + ']"]';
				$( selector ).text( value );
			} );
		}
		
		$( document ).on( 'click', '.globkurier_confirm_send', function () {
			order();
		} );
		
		function order() {
			$( '.globkurier_confirm_send' ).attr( 'disabled', true );
			
			$( '.udi-order-confirm-container .udi-loader-overlay' ).show();
			$( '.udi-order-confirm-container .udi-loader' ).show();
			
			if ( !validateOrder() ) {
				$( '.globkurier_confirm_send' ).attr( 'disabled', false );
				
				return;
			}
			
			$( '.udi-save-order .udi-loader' ).show();
			
			let ajaxData = getBulkOrderData();
			
			$.post(wpopieka_globkurier_bulk_send_data.ajax_url, ajaxData, function(response) {
				let result = $.parseJSON(response);
				
				clearNoticesError();
				clearNoticesSuccess();
				hideNoticesSuccess();
				hideNoticesError();
				
				const fieldMap = {
					'senderAddress[name]': 'Imię i nazwisko nadawcy',
					'senderAddress[postCode]': 'Kod pocztowy nadawcy',
					'senderAddress[city]': 'Miasto nadawcy',
					'senderAddress[street]': 'Ulica nadawcy',
					'senderAddress[houseNumber]': 'Nr mieszkania nadawcy',
					'senderAddress[apartmentNumber]': 'Nr lokalu nadawcy',
					'senderAddress[phone]': 'Numer telefonu nadawcy',
					'senderAddress[email]': 'Adres email nadawcy',
					'receiverAddress[name]': 'Imię i nazwisko odbiorcy',
					'receiverAddress[postCode]': 'Kod pocztowy odbiorcy',
					'receiverAddress[city]': 'Miasto odbiorcy',
					'receiverAddress[street]': 'Ulica odbiorcy',
					'receiverAddress[houseNumber]': 'Nr mieszkania odbiorcy',
					'receiverAddress[apartmentNumber]': 'Nr lokalu odbiorcy',
					'receiverAddress[phone]': 'Numer telefonu odbiorcy',
					'receiverAddress[email]': 'Adres email odbiorcy',
					'receiverAddress[pointId]': 'Punkt odbioru',
					'addons': 'Dodatki',
					'pickup[timeFrom]': 'Godzina nadania',
					'pickup[timeTo]': 'Godzina nadania',
					'paymentId': 'Płatność',
					'containsDangerousGoods': 'Towary niebezpieczne'
				};
				
				let hasErrors = false;
				let hasSuccess = false;
				
				if (result.success) {
					hasSuccess = true;
					$.each(result.success, function(orderId, orderNumber) {
						addNoticeSuccess('<p>Zamówienie ID: <strong>' + gkEsc( orderId ) + '</strong> - Numer przesyłki: <strong>' + gkEsc( orderNumber ) + '</strong></p>');
					});
				}
				
				if (result.errors) {
					hasErrors = true;
					$.each(result.errors, function(orderId, fields) {
						addNoticeError('<p><strong>Błąd dla zamówienia ID: ' + orderId + '</strong></p>');
						$.each(fields, function(field, error) {
							let fieldName = fieldMap[field] || field;
							addNoticeError('<p style="margin-left: 20px;">' + gkEsc( fieldName ) + ': <strong>' + gkEsc( error ) + '</strong></p>');
						});
					});
				}
				
				if (hasSuccess) {
					addNoticeSuccess('<p>Realizacja usługi nastąpi po odnotowaniu wpłaty na koncie Globkurier.pl (nie dotyczy płatności odroczonej i płatności przy doręczeniu).</p>');
					addNoticeSuccess('<p>Informacje związane ze statusem swojej przesyłki oraz list przewozowy otrzymasz na adres e-mail podany w procesie zamówienia.</p>');
					showNoticesSuccess();
					
					$('.modal-body').animate({
						scrollTop: $('.globkurier-notices-success').position().top
					}, 500);
				}
				
				if (hasErrors) {
					showNoticesError();
					
					$('.modal-body').animate({
						scrollTop: $('.globkurier-notices-error').position().top
					}, 500);
				}
				
				if(!hasErrors){
					$('.udi-order-confirm-container-buttons').hide();
				}
				
				$('.udi-order-confirm-container .udi-loader-overlay').hide();
				$('.udi-order-confirm-container .udi-loader').hide();
				
				$('.globkurier_confirm_send').attr('disabled', false);
				
				$('.udi-order-confirm-container-body').hide();
				$('.globkurier_confirm_send').hide();
				
				$('.globkurier-notices-container').show();
			});
		}
		
		function confirmCorrection() {
			$( '.udi-save-order' ).attr( 'disabled', false );
			$( '.udi-order-confirm-container' ).hide();
			$( '.udi-wpadmin-order-address' ).show();
			$( '.udi-step-select-product-details' ).show();
			$( '.globkurier_confirm_send' ).show();
			
			clearNoticesError();
			clearNoticesSuccess();
			
			$( '.globkurier-notices-container' ).hide();
		}
		
		$( document ).on( 'click', '.re-select-product', function () {
			$('#gk-bulk-step-2').hide()
			$('#gk-bulk-step-1').show()
		} );
		
		$( document ).on( 'click', '.globkurier_confirm_correction', function () {
			confirmCorrection();
		} );
		
	} );
} )( jQuery );