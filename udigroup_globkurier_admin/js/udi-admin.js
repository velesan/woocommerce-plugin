( function ( $ ) {
	// Escapowanie danych (API / dane klienta) przed wklejeniem do HTML lub atrybutu.
	function gkEsc( value ) {
		return $( '<div>' ).text( value === undefined || value === null ? '' : value ).html().replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}
	
	$( function () {
		'use strict';
		
		let _language = '';
		
		$.getJSON( udi_admin_script.datatables_lang_pl, function ( language ) {
			_language = language;
		} );
		var GKcreateOrderTimer;
		var GKcreateOrderXhr;
		
		
		$( document ).find( '.udi-is-select2' ).select2( {
			language: {
				inputTooShort: function () {
					return 'Wpisz co najmniej 3 znaki.';
				},
				searching: function () {
					return 'Szukanie..';
				},
				noResults: function () {
					return 'Brak wyników.';
				}
			},
		} );
		
		$( document ).find( '#globkurier-find-sender, #globkurier-find-receiver' ).select2( {
				language: {
					inputTooShort: function () {
						return 'Wpisz co najmniej 3 znaki.';
					},
					searching: function () {
						return 'Szukanie..';
					},
					noResults: function () {
						return 'Brak wyników.';
					}
				},
				delay: 250,
				minimumInputLength: 3,
				ajax: {
					url: ajaxurl,
					dataType: 'json',
					cache: false,
					data: function ( params ) {
						return {
							q: params.term,
							action: 'globkurierGetPerson',
							type: $( this ).data( 'type' ),
							nonce: $( '#globkurier_get_person_nonce' ).val(),
						};
					},
					processResults: function ( data ) {
						let options = [];
						if ( data ) {
							$.each( data, function ( index, person ) {
								options.push( { id: person.id, text: person.name, data: person } );
							} );
						}
						return {
							results: options
						};
					},
				},
			} )
			.on( 'select2:select', function ( e ) {
				populateFromAddressBook( e.params.data );
			} );
		
		$( document ).on( 'click', '[data-trigger="load-gk-order-box"]', function () {
			GKcreateOrderTrigger();
			$( this ).remove();
		} );
		
		function GKcreateOrderTrigger() {
			clearTimeout( GKcreateOrderTimer );
			if ( GKcreateOrderXhr ) {
				GKcreateOrderXhr.abort();
			}
			
			let wrapper = $( '.globkurier-create-order-table-wrapper' );
			
			GKcreateOrderTimer = setTimeout( function () {
				GKloadCreateOrder()
					.then( response => {
						wrapper.html( response.data );
						
						$( '.udi-is-datatable' ).DataTable( {
							'language': _language
						} );
						
						$( document ).find( '.udi-is-select2' ).select2( {
							language: {
								inputTooShort: function () {
									return 'Wpisz co najmniej 3 znaki.';
								},
								searching: function () {
									return 'Szukanie..';
								},
								noResults: function () {
									return 'Brak wyników.';
								}
							},
						} );
						
						$( document ).find( '#globkurier-find-sender, #globkurier-find-receiver' ).select2( {
								language: {
									inputTooShort: function () {
										return 'Wpisz co najmniej 3 znaki.';
									},
									searching: function () {
										return 'Szukanie..';
									},
									noResults: function () {
										return 'Brak wyników.';
									}
								},
								delay: 250,
								minimumInputLength: 3,
								ajax: {
									url: ajaxurl,
									dataType: 'json',
									cache: false,
									data: function ( params ) {
										return {
											q: params.term,
											action: 'globkurierGetPerson',
											type: $( this ).data( 'type' ),
											nonce: $( '#globkurier_get_person_nonce' ).val(),
										};
									},
									processResults: function ( data ) {
										let options = [];
										if ( data ) {
											$.each( data, function ( index, person ) {
												options.push( { id: person.id, text: person.name, data: person } );
											} );
										}
										return {
											results: options
										};
									},
								},
							} )
							.on( 'select2:select', function ( e ) {
								populateFromAddressBook( e.params.data );
							} );
						
						$( document ).find( '#globkurier-service-date-picker' ).datepicker( {
							minDate: 0,
							dateFormat: 'yy-mm-dd',
							beforeShowDay: $.datepicker.noWeekends
						} );
						
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
								url: data[ 'ajaxUrl' ],
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
						
						$( document ).find( '#globkurier-receiver-country' ).on( 'select2:select', function ( e ) {
							$( '#globkurier-receiver-country' ).select2().data( 'select2' ).$container.removeClass( 'udi-error' );
						} );
						
						$( document ).find( '#globkurier-sender-country' ).on( 'select2:select', function ( e ) {
							$( '#globkurier-sender-country' ).select2().data( 'select2' ).$container.removeClass( 'udi-error' );
						} );
						
						$( document ).find( '.udi-select2#udi-select-inpost-sender' ).on( 'select2:select', function ( e ) {
							$( '#globkurier_inpost_input' ).val( e.params.data.text );
						} );
						
						$( document ).find( '.udi-select2#udi-select-inpost-pickup_value' ).select2( {
							placeholder: 'Znajdź punkt odbioru',
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
								url: data[ 'ajaxUrl' ],
								dataType: 'json',
								data: function ( params ) {
									return {
										city: params.term,
										nonce: $( '#globkurier_get_inpost_points_select2_nonce' ).val(),
										countryId: $( '#globkurier-receiver-country' ).val(),
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
						
						$( document ).find( '.udi-select2#udi-select-inpost-pickup_value' ).on( 'select2:select', function ( e ) {
							$( '#globkurier_inpost_input-pickup' ).val( e.params.data.text );
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
								url: data[ 'ajaxUrl' ],
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
						
						$( document ).on( 'change', '.udi-error', function () {
							if ( $( '.udi-error' ).length === 0 ) {
								hideNoticesError();
								clearNoticesError();
							}
						} );
						
						$( document ).on( 'change', ':input[type="number"]', function () {
							let val = $( this ).val();
							let newVal = parseFloat( val.replace( /,/g, '.' ) );
							$( this ).val( newVal );
						} );
						
						$( document ).on( 'change', '.udi-service-options input:not("#globkurier-service-date-picker, #globkurier-service-time-picker"), .udi-service-options select:not("#globkurier-service-payment-picker")', function () {
							getPrice( false );
						} )
						
					} )
					.catch( error => {
					
					} );
			}, 500 );
		}
		
		function GKloadCreateOrder() {
			
			return new Promise( ( resolve, reject ) => {
				$.ajax( {
					url: ajaxurl, type: 'post', data: {
						action: 'globkurierCreateOrdersAsync',
						orderId: $( '#globkurier_create_order_order_id' ).val(),
						nonce: $( '#globkurier_create_order_nonce' ).val(),
					}, success: function ( response ) {
						resolve( response );
					}, error: function ( xhr, status, error ) {
						reject( error );
					}
				} );
			} );
		}
		
		
		let carriersData;
		
		
		$( document ).on( 'change', '.onlyDecimal', function () {
			$( this ).val( Math.round( parseFloat( $( this ).val() ) ) );
		} );
		
		
		$( document ).on( 'keyup', '.onlyDecimal', function () {
			if ( $( this ).val().length > 10 ) {
				$( this ).val( $( this ).val().substring( 0, 10 ) );
			}
		} );
		
		function populateFromAddressBook( data ) {
			let person = data.data;
			
			let id, name, email, street, home, flat, postal, city, country, contactName, phone;
			switch ( person.type ) {
				case 'senders':
					id = $( '#globkurier-sender-id' );
					name = $( '#globkurier-sender-name' );
					street = $( '#globkurier-sender-street' );
					home = $( '#globkurier-sender-home' );
					flat = $( '#globkurier-sender-flat' );
					postal = $( '#globkurier-sender-postal' );
					city = $( '#globkurier-sender-city' );
					country = $( '#globkurier-sender-country' );
					contactName = $( '#globkurier-sender-contact-name' );
					phone = $( '#globkurier-sender-contact-phone' );
					email = $( '#globkurier-sender-email' );
					
					$( '#globkurier-add-sender-to-address-book' ).hide();
					
					break;
				case 'receivers':
					id = $( '#globkurier-receiver-id' );
					name = $( '#globkurier-receiver-name' );
					street = $( '#globkurier-receiver-street' );
					home = $( '#globkurier-receiver-home' );
					flat = $( '#globkurier-receiver-flat' );
					postal = $( '#globkurier-receiver-postal' );
					city = $( '#globkurier-receiver-city' );
					country = $( '#globkurier-receiver-country' );
					contactName = $( '#globkurier-receiver-contact-name' );
					phone = $( '#globkurier-receiver-contact-phone' );
					email = $( '#globkurier-receiver-email' );
					
					$( '#globkurier-add-receiver-to-address-book' ).hide();
					
					break;
				default:
					return;
			}
			
			id.val( person.id );
			name.val( person.name );
			street.val( person.street );
			home.val( person.houseNumber );
			flat.val( person.apartmentNumber );
			postal.val( person.postCode );
			city.val( person.city );
			contactName.val( person.contactPerson );
			phone.val( person.phone );
			email.val( person.email );
			country.val( person.countryId ).change();
			country.trigger( 'select2:select' );
			
			name.removeClass( 'udi-error' );
			street.removeClass( 'udi-error' );
			home.removeClass( 'udi-error' );
			flat.removeClass( 'udi-error' );
			postal.removeClass( 'udi-error' );
			city.removeClass( 'udi-error' );
			contactName.removeClass( 'udi-error' );
			phone.removeClass( 'udi-error' );
			email.removeClass( 'udi-error' );
			country.removeClass( 'udi-error' );
		}
		
		$( document ).on( 'change', '.udi-wpadmin-order-address input, .udi-wpadmin-order-address select', function () {
			let personId = $( this ).parents( '.udi-wpadmin-order-address-col' ).find( 'input.globkurier-person-id' ).val();
			
			$( this ).parents( '.udi-wpadmin-order-address-col' ).find( '.globkurier-add-person-to-address-book' ).hide();
			$( this ).parents( '.udi-wpadmin-order-address-col' ).find( '.globkurier-update-person-to-address-book' ).hide();
			
			if ( personId != '' ) {
				$( this ).parents( '.udi-wpadmin-order-address-col' ).find( '.globkurier-update-person-to-address-book' ).show();
			}
			$( this ).parents( '.udi-wpadmin-order-address-col' ).find( '.globkurier-add-person-to-address-book' ).show();
			
		} );
		
		$( document ).on( 'click', '.globkurier-add-person-to-address-book', function () {
			let button = $( this );
			
			button.attr( 'disabled', true );
			
			let id, name, email, street, home, flat, postal, city, country, contactName, phone;
			
			let type = $( this ).data( 'type' );
			
			switch ( type ) {
				case 'sender':
					id = $( '#globkurier-sender-id' );
					name = $( '#globkurier-sender-name' );
					street = $( '#globkurier-sender-street' );
					home = $( '#globkurier-sender-home' );
					flat = $( '#globkurier-sender-flat' );
					postal = $( '#globkurier-sender-postal' );
					city = $( '#globkurier-sender-city' );
					country = $( '#globkurier-sender-country' );
					contactName = $( '#globkurier-sender-contact-name' );
					phone = $( '#globkurier-sender-contact-phone' );
					email = $( '#globkurier-sender-email' );
					
					break;
				case 'receiver':
					id = $( '#globkurier-receiver-id' );
					name = $( '#globkurier-receiver-name' );
					street = $( '#globkurier-receiver-street' );
					home = $( '#globkurier-receiver-home' );
					flat = $( '#globkurier-receiver-flat' );
					postal = $( '#globkurier-receiver-postal' );
					city = $( '#globkurier-receiver-city' );
					country = $( '#globkurier-receiver-country' );
					contactName = $( '#globkurier-receiver-contact-name' );
					phone = $( '#globkurier-receiver-contact-phone' );
					email = $( '#globkurier-receiver-email' );
					
					break;
				default:
					return;
			}
			
			$( '.globkurier-address-books-error-body' ).html( '' );
			$( '.globkurier-address-books-success-body' ).html( '' );
			$( '.globkurier-address-books-error' ).hide();
			$( '.globkurier-address-books-success' ).hide();
			
			let required = [ name, email, home, city, postal, phone ];
			let errors = 0;
			
			$( required ).each( function () {
				if ( $( this ).val() == '' ) {
					$( this ).addClass( 'udi-error' );
					$( '.globkurier-address-books-error-body' ).append( '<p>Pole <strong>' + $( this ).attr( 'placeholder' ) + '</strong> jest wymagane</p>' );
					errors++;
				}
			} );
			if ( errors > 0 ) {
				$( '.globkurier-address-books-error' ).show();
				button.attr( 'disabled', false );
				return;
			}
			
			let ajaxData = {
				action: 'globkurierAddPersonToAddressBook',
				data: {
					'type': type,
					'name': name.val(),
					'email': email.val(),
					'street': street.val(),
					'home': home.val(),
					'flat': flat.val(),
					'postal': postal.val(),
					'city': city.val(),
					'country': country.val(),
					'contactName': contactName.val(),
					'phone': phone.val(),
					'nonce': $( '#globkurier_add_person_to_address_book_nonce' ).val(),
				}
			};
			
			hideAddresBookNotices();
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				
				if ( response == 'ERROR' ) {
					$( '.globkurier-address-books-error-body' ).html( 'Uzupełnij prawidłowo wszystkie wymagane pola' );
					$( '.globkurier-address-books-error' ).show();
					
					button.attr( 'disabled', false );
					
					return;
				}
				
				let responesId = $.parseJSON( response );
				
				if ( responesId ) {
					
					id.val( responesId );
					
					button.hide();
					button.attr( 'disabled', false );
					
					$( '.globkurier-address-books-success-body' ).html( 'Pomyślnie dodano <strong>' + gkEsc( name.val() ) + '</strong> do książki adresowej' );
					$( '.globkurier-address-books-success' ).show();
				}
				
				button.attr( 'disabled', false );
			} );
			
		} );
		
		function hideAddresBookNotices() {
			$( '.globkurier-address-books-error-body' ).html( '' );
			$( '.globkurier-address-books-success-body' ).html( '' );
			$( '.globkurier-address-books-error' ).hide();
			$( '.globkurier-address-books-success' ).hide();
		}
		
		$( document ).on( 'click', '.globkurier-update-person-to-address-book', function () {
			let button = $( this );
			
			button.attr( 'disabled', true );
			
			let id, name, email, street, home, flat, postal, city, country, contactName, phone;
			
			let type = $( this ).data( 'type' );
			
			switch ( type ) {
				case 'sender':
					id = $( '#globkurier-sender-id' );
					name = $( '#globkurier-sender-name' );
					street = $( '#globkurier-sender-street' );
					home = $( '#globkurier-sender-home' );
					flat = $( '#globkurier-sender-flat' );
					postal = $( '#globkurier-sender-postal' );
					city = $( '#globkurier-sender-city' );
					country = $( '#globkurier-sender-country' );
					contactName = $( '#globkurier-sender-contact-name' );
					phone = $( '#globkurier-sender-contact-phone' );
					email = $( '#globkurier-sender-email' );
					
					break;
				case 'receiver':
					id = $( '#globkurier-receiver-id' );
					name = $( '#globkurier-receiver-name' );
					street = $( '#globkurier-receiver-street' );
					home = $( '#globkurier-receiver-home' );
					flat = $( '#globkurier-receiver-flat' );
					postal = $( '#globkurier-receiver-postal' );
					city = $( '#globkurier-receiver-city' );
					country = $( '#globkurier-receiver-country' );
					contactName = $( '#globkurier-receiver-contact-name' );
					phone = $( '#globkurier-receiver-contact-phone' );
					email = $( '#globkurier-receiver-email' );
					
					break;
				default:
					return;
			}
			
			$( '.globkurier-address-books-error-body' ).html( '' );
			$( '.globkurier-address-books-success-body' ).html( '' );
			$( '.globkurier-address-books-error' ).hide();
			$( '.globkurier-address-books-success' ).hide();
			
			let required = [ id, name, email, home, city, postal, phone ];
			let errors = 0;
			
			$( required ).each( function () {
				if ( $( this ).val() == '' ) {
					$( this ).addClass( 'udi-error' );
					$( '.globkurier-address-books-error-body' ).append( '<p>Pole <strong>' + $( this ).attr( 'placeholder' ) + '</strong> jest wymagane</p>' );
					errors++;
				}
			} );
			if ( errors > 0 ) {
				$( '.globkurier-address-books-error' ).show();
				button.attr( 'disabled', false );
				return;
			}
			
			let ajaxData = {
				action: 'globkurierUpdatePersonToAddressBook',
				data: {
					'type': type,
					'id': id.val(),
					'name': name.val(),
					'email': email.val(),
					'street': street.val(),
					'home': home.val(),
					'flat': flat.val(),
					'postal': postal.val(),
					'city': city.val(),
					'country': country.val(),
					'contactName': contactName.val(),
					'phone': phone.val(),
					'nonce': $( '#globkurier_update_person_to_address_book_nonce' ).val(),
				}
			};
			
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				
				if ( response == 'ERROR' ) {
					$( '.globkurier-address-books-error-body' ).html( 'Uzupełnij prawidłowo wszystkie wymagane pola' );
					$( '.globkurier-address-books-error' ).show();
					
					button.attr( 'disabled', false );
					
					return;
				}
				
				$( '.globkurier-address-books-success-body' ).html( 'Pomyślnie zapisano <strong>' + gkEsc( name.val() ) + '</strong> do książki adresowej' );
				$( '.globkurier-address-books-success' ).show();
				
				button.attr( 'disabled', false );
			} );
			
		} );
		
		$( document ).find( '[name="globkurier\\[default\\]\\[send\\]\\[country\\]' ).select2();
		
		
		function globkurier_add_notice( msg, type ) {
			
		}
		
		function globkurier_add_error( msg, field ) {
			field.addClass( 'udi-error' );
		}
		
		function globkurier_remove_error( msg, field ) {
			
		}
		
		$( document ).on( 'change', '.udi-error', function () {
			$( this ).removeClass( 'udi-error' );
		} );
		
		$( '#globkurier-sender-contact-phone, #globkurier-receiver-contact-phone' ).keydown( function ( event ) {
			let key = event.key;
			let keyCode = event.keyCode;
			
			if ( '!,@,#,$,%,^,&,*,(,),~'.split( ',' ).indexOf( key ) != -1 ) {
				event.preventDefault();
				return;
			}
			
			if ( keyCode == 46 || keyCode == 8 || keyCode == 32 || keyCode == 9 ) {
			} else {
				if ( ( keyCode < 48 || keyCode > 57 ) && ( keyCode < 96 || keyCode > 105 ) ) {
					event.preventDefault();
				}
			}
			
		} );
		
		function isEmail( email ) {
			var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
			return regex.test( email );
		}
		
		function isPhone( phone ) {
			return phone.replace( /\ /g, '' ).trim().length >= 9;
		}
		
		$( document ).on( 'click', '.globkurier_get_products', function () {
			hideAddresBookNotices();
			
			$( '.udi-best-price-products' ).hide();
			
			let button = $( this );
			
			let height = $( '#globkurier-height' ).val();
			let width = $( '#globkurier-width' ).val();
			let length = $( '#globkurier-length' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			let sku_content = $( '#sku_content' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			
			let validator = [
				$( '#globkurier-height' ), $( '#globkurier-width' ), $( '#globkurier-length' ), $( '#globkurier-weight' ), $( '#globkurier-quantity' ),
				$( '#globkurier-sender-country' ), $( '#globkurier-sender-postal' ), $( '#globkurier-sender-city' ), $( '#globkurier-sender-street' ),
				$( '#globkurier-sender-home' ), $( '#globkurier-sender-name' ), $( '#globkurier-sender-contact-name' ), $( '#globkurier-sender-contact-phone' ),
				$( '#globkurier-sender-email' ), $( '#globkurier-sender-postal' ),
				
				$( '#globkurier-receiver-country' ), $( '#globkurier-receiver-city' ), $( '#globkurier-receiver-street' ), $( '#globkurier-receiver-home' ),
				$( '#globkurier-receiver-name' ), $( '#globkurier-receiver-contact-name' ), $( '#globkurier-receiver-contact-phone' ),
				$( '#globkurier-receiver-email' ), $( '#globkurier-receiver-postal' )
			];
			
			let errors = 0;
			$.each( validator, function ( id, field ) {
				if ( field.val() == '' || field.val() == null ) {
					globkurier_add_error( 'Pole jest wymagane', field );
					errors++;
				}
			} );
			
			clearNoticesError();
			
			if ( $( '#globkurier-receiver-email' ).val() != '' && !isEmail( $( '#globkurier-receiver-email' ).val() ) ) {
				addNoticeError( '<p>Adres e-mail odbiorcy nie jest poprawny</p>' );
				globkurier_add_error( 'Adres e-mail odbiorcy nie jest poprawny', $( '#globkurier-receiver-email' ) );
				errors++;
			}
			
			if ( $( '#globkurier-sender-email' ).val() != '' && !isEmail( $( '#globkurier-sender-email' ).val() ) ) {
				addNoticeError( '<p>Adres e-mail nadawcy nie jest poprawny</p>' );
				globkurier_add_error( 'Adres e-mail nadawcy nie jest poprawny', $( '#globkurier-sender-email' ) );
				errors++;
			}
			
			if ( $( '#globkurier-sender-contact-phone' ).val() != '' && !isPhone( $( '#globkurier-sender-contact-phone' ).val() ) ) {
				addNoticeError( '<p>Telefon nadawcy musi zawierać co najmniej 9 cyfr</p>' );
				globkurier_add_error( 'Telefon nadawcy musi zawierać co najmniej 9 cyfr', $( '#globkurier-sender-contact-phone' ) );
				errors++;
			}
			
			if ( $( '#globkurier-receiver-contact-phone' ).val() != '' && !isPhone( $( '#globkurier-receiver-contact-phone' ).val() ) ) {
				addNoticeError( '<p>Telefon odbiorcy musi zawierać co najmniej 9 cyfr</p>' );
				globkurier_add_error( 'Telefon odbiorcy musi zawierać co najmniej 9 cyfr', $( '#globkurier-receiver-contact-phone' ) );
				errors++;
			}
			
			$.each( $( '.udi-wpadmin-order-address input, .udi-step-select-product input' ), function ( id, field ) {
				
				if ( $( field )[ 0 ].hasAttribute( 'maxlength' ) ) {
					if ( $( field ).val() != '' && $( field ).val().length > $( field ).attr( 'maxlength' ) ) {
						addNoticeError( '<p>' + $( field ).attr( 'placeholder' ) + ': Wprowadzona wartość jest za długa (maksymalnie ' + $( field ).attr( 'maxlength' ) + ' znaki)</ p>' );
						globkurier_add_error( 'Wprowadzona wartość jest za długa', $( field ) );
						errors++;
					}
				}
				
				if ( $( field )[ 0 ].hasAttribute( 'minlength' ) ) {
					if ( $( field ).val() != '' && $( field ).val().length < $( field ).attr( 'minlength' ) ) {
						addNoticeError( '<p>' + $( field ).attr( 'placeholder' ) + ': Wprowadzona wartość jest za krótka (minimalnie ' + $( field ).attr( 'minlength' ) + ' znaki)</ p>' );
						globkurier_add_error( 'Wprowadzona wartość jest za krótka', $( field ) );
						errors++;
					}
				}
				
				if ( $( field )[ 0 ].hasAttribute( 'minvalue' ) ) {
					if ( $( field ).val() != '' && parseFloat( $( field ).val() ) < parseFloat( $( field ).attr( 'minvalue' ) ) ) {
						globkurier_add_error( '', $( field ) );
						addNoticeError( '<p>Wartość minimalna pola <strong>' + $( field ).attr( 'placeholder' ) + '</strong> to ' + $( field ).attr( 'minvalue' ) + '</p>' );
						errors++;
					}
				}
				
				if ( $( field )[ 0 ].hasAttribute( 'maxvalue' ) ) {
					if ( $( field ).val() != '' && parseFloat( $( field ).val() ) > parseFloat( $( field ).attr( 'maxvalue' ) ) ) {
						globkurier_add_error( '', $( field ) );
						addNoticeError( '<p>Wartość maksymalna pola <strong>' + $( field ).attr( 'placeholder' ) + '</strong> to ' + $( field ).attr( 'maxvalue' ) + '</p>' );
						errors++;
					}
				}
				
			} );
			
			if ( $( '#globkurier-receiver-contact-phone' ).val() != '' && !isPhone( $( '#globkurier-receiver-contact-phone' ).val() ) ) {
				addNoticeError( '<p>Telefon odbiorcy musi zawierać co najmniej 9 cyfr</p>' );
				globkurier_add_error( 'Telefon odbiorcy musi zawierać co najmniej 9 cyfr', $( '#globkurier-receiver-contact-phone' ) );
				errors++;
			}
			
			if ( $( '#globkurier-receiver-country' ).val() == null ) {
				globkurier_add_error( 'Kraj odbiorcy jest wymagane', $( '#globkurier-receiver-country' ).select2().data( 'select2' ).$container );
				errors++;
			}
			
			if ( $( '#globkurier-sender-country' ).val() == null ) {
				globkurier_add_error( 'Kraj nadawcy jest wymagane', $( '#globkurier-sender-country' ).select2().data( 'select2' ).$container );
				errors++;
			}
			
			if ( $( '#globkurier-content' ).val() == '-- Wybierz --' ) {
				globkurier_add_error( 'Zawartość paczki jest wymagana', $( '#globkurier-content' ) );
				errors++;
			}
			
			if ( $( '#globkurier-content' ).val() == 'Inne' ) {
				if ( $( '#globkurier-otherContent' ).val().trim() == '' ) {
					
					addNoticeError( '<p>' + $( '#globkurier-otherContent' ).attr( 'placeholder' ) + ': jest wymagane jeśli wybrano Inną zawartość</ p>' );
					globkurier_add_error( '', $( '#globkurier-otherContent' ) );
					
					errors++;
				}
			}
			
			if ( errors > 0 ) {
				addNoticeError( '<p>Uzupełnij wszystkie wymagane pola</p>' );
			}
			
			if ( errors > 0 ) {
				showNoticesError();
				return;
			} else {
				clearNoticesError();
				hideNoticesError();
			}
			
			let globkurier_show_all_providers = $( '#globkurier_show_all_providers' ).is( ':checked' );
			let globkurier_is_pickup_active = $( '#globkurier_is_pickup_active' ).val() == 1;
			let globkurier_pickup_type = $( '#globkurier_pickup_type' ).val();
			
			let ajaxData = {
				action: 'globkurierGetProducts',
				data: {
					'height': height,
					'width': width,
					'length': length,
					'weight': weight,
					'quantity': quantity,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
					'globkurier_is_pickup_active': globkurier_is_pickup_active,
					'globkurier_pickup_type': globkurier_pickup_type,
					'globkurier_show_all_providers': globkurier_show_all_providers,
					'nonce': $( '#globkurier_get_products_nonce' ).val(),
				}
			};
			
			let loader = $( '.globkurier_get_products-container .udi-loader' );
			
			loader.show();
			
			button.prop( 'disabled', 'true' );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				
				if(response.success === false){
					alert(response.data.message || '');
					button.prop( 'disabled', '' );
					loader.hide();
					return;
				}
				
				let container = $( '.udi-best-price-products .products-list' );
				
				let results = $.parseJSON( response );
				
				clearNoticesError();
				hideNoticesError();
				
				if ( results.status == 'error' ) {
					
					$.each( results.msg, function ( key, msg ) {
						switch ( key ) {
							case 'senderPostCode':
								globkurier_add_error( 'Pole jest wymagene', $( '#globkurier-sender-postal' ) );
								key = 'Kod pocztowy nadawcy';
								break;
							case 'receiverPostCode':
								globkurier_add_error( 'Pole jest wymagene', $( '#globkurier-receiver-postal' ) );
								key = 'Kod pocztowy odbiorcy';
								break;
						}
						
						addNoticeError( '<p>' + gkEsc( key ) + ': <strong>' + gkEsc( msg ) + '</strong></p>' );
					} );
					showNoticesError();
					
					button.prop( 'disabled', '' );
					loader.hide();
					
					return;
				}
				
				let products = results.results;
				
				carriersData = products;
				container.html( '' );
				
				for ( let i = 0; i < 3; i++ ) {
					if ( products[ i ] ) {
						let product = '<div class="udi-best-price-product">\n\t<input type="radio" data-collectiontypes="' + gkEsc( products[ i ].collectionTypes ) + '" data-deliverytypes="' + gkEsc( ( products[ i ].deliveryTypeOptions || [] ).map( function ( o ) { return o.key; } ).join( ',' ) ) + '" name="globkurier-selected-product" id="globkurier-product-' + gkEsc( products[ i ].id ) + '" value="' + gkEsc( products[ i ].id ) + '">\n\t<label for="globkurier-product-' + gkEsc( products[ i ].id ) + '">\n\t\t<span class="udi-product-name">' + gkEsc( products[ i ].carrierName ) + '</span> - <span class="udi-product-price">' + products[ i ].netPrice.toFixed( 2 ) + 'zł</span>\n\t</label>\n</div>';
						container.append( product );
					}
				}
				
				container.parent().show();
				button.prop( 'disabled', '' );
				loader.hide();
				
				let allProductsContainer = $( '.udi-all-products' );
				allProductsContainer.html( '' );
				
				if ( products.length == 0 ) {
					allProductsContainer.append( '<span>Niestety nie ma pasujących ofert do podanych kryteriów. <a href="https://www.globkurier.pl/kontakt/">Skontaktuj się z nami</a></span>' );
				}
				
				$( products ).each( function ( k, value ) {
					let nameSuffix = '';
					if ( $( '#globkurier-quantity' ).val() > 1 ) {
						nameSuffix = '(x' + $( '#globkurier-quantity' ).val() + ')';
					}
					
					let labels = value.labels;
					labels = Array.isArray(labels)
						? labels.map(label => label.replace(/[^a-zA-Z0-9 ]/g, ''))
						: (typeof labels === 'string' ? [labels.replace(/[^a-zA-Z0-9 ]/g, '')] : []);
					
					
					let collectionTypesText = '';
					if(value.collectionTypes.includes( 'CROSSBORDER' )){
						collectionTypesText = '\t\t<div><span><br><b>Crossborder</b></span></div>\n'
					}
					
					let product = '\t<div class="udi-product">\n' +
						'\t\t<div><img src="' + gkEsc( value.carrierLogoLink ) + '"></div>\n' +
						'\t\t<div><span>' + gkEsc( value.name ) + ' ' + nameSuffix + '</span></div>\n' +
						collectionTypesText +
						'\t\t<div style="margin: 20px 0"><span class="udi-product-price">' + value.netPrice.toFixed( 2 ) + 'zł </span></span><br/>(' + value.grossPrice.toFixed( 2 ) + 'zł brutto' + ')</div>\n' +
						'\t\t<div style="margin: 10px 0"><button type="button" class="button-secondary udi-select-carrier"  data-labels="' + labels + '" data-carrierName="' + gkEsc( value.carrierName ) + '"  data-collectiontypes="' + gkEsc( value.collectionTypes ) + '"  data-deliverytypes="' + gkEsc( ( value.deliveryTypeOptions || [] ).map( function ( o ) { return o.key; } ).join( ',' ) ) + '"  data-carrierid="' + gkEsc( value.id ) + '">Wybieram</button></div>\n' +
						'\t</div>';
					
					allProductsContainer.append( product );
					
				} );
				
				$( '.udi-all-products' ).show();
				
				$( '.udi-wpadmin-order-address input, .udi-wpadmin-order-address select, .globkurier-parcel-details input, .globkurier-parcel-content input, .globkurier-parcel-content select' ).attr( 'disabled', true );
				
				$( '.globkurier_get_products-container' ).hide();
				$( '.globkurier_edit_data-container' ).show();
				
			} );
			
		} );
		
		$( document ).on( 'change', '#globkurier_show_all_providers', function () {
			if ( !$( '.globkurier_edit_data-container' ).is( ':visible' ) ) {
				return;
			}
			
			$( '.globkurier_get_products-container' ).show();
			$( '.globkurier_edit_data-container' ).hide();
			$( '.udi-best-price-products' ).hide();
			$( '.udi-all-products' ).hide();
			
			$( '.globkurier_get_products' ).click();
		} );
		
		$( document ).on( 'click', '.udi-wpadmin-order-address .udi-input-with-hidden-label', function ( e ) {
			$( '.re-select-product' ).click();
			$( '.globkurier_edit_data' ).click();
			$( e.target ).focus();
		} );
		
		$( document ).on( 'click', '.globkurier_edit_data', function () {
			$( '.udi-wpadmin-order-address input, .udi-wpadmin-order-address select, .globkurier-parcel-details input, .globkurier-parcel-content input, .globkurier-parcel-content select' ).attr( 'disabled', false );
			
			$( '.globkurier_get_products-container' ).show();
			$( '.globkurier_edit_data-container' ).hide();
			$( '.udi-best-price-products' ).hide();
			$( '.udi-all-products' ).hide();
		} );
		
		$( document ).on( 'click', '.udi-show-all-carriers', function () {
			let modalContainer = $( '.udi-best-price-products' );
		} );
		
		$( document ).on( 'change', 'input[name="globkurier-selected-product"]', function () {
			selectCarrier( $( this ).val(), $( this ) );
		} );
		
		$( document ).on( 'click', '.udi-select-carrier', function () {
			// Zmiana przewoznika czysci deklaracje celna - przy anulowaniu zostawiamy poprzedni wybor.
			if ( window.globkurierCustoms && window.globkurierCustoms.confirmCarrierChange
				&& !window.globkurierCustoms.confirmCarrierChange( $( this ).data( 'carriername' ) ) ) {
				return;
			}

			$( '.udi-all-products' ).hide();
			
			selectCarrier( $( this ).data( 'carrierid' ), $( this ) );
		} );
		
		function selectCarrier( carrierId, _this ) {
			
			let collectionTypes = _this.data( 'collectiontypes' ).split( ',' );
			
			$( '[name="globkurier-pickup-type"]' ).attr( 'disabled', true );
			
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

			if ($( document ).find( '#udi-select-crossborder_terminal_value').data('select2')) {
				$( document ).find( '#udi-select-crossborder_terminal_value').select2('destroy');
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
				if($('#globkurier-pickup-type-PICKUP').is(':disabled')){
					$( '.globkurier-not-pickup' ).hide();
				}
				$( '.globkurier-only-inpost' ).hide();
				
				$( '#globkurier_inpost_input' ).removeClass( 'globkurier-is-required' );
				$( '#globkurier_inpost_input-pickup' ).removeClass( 'globkurier-is-required' );
				$( '#globkurier_ruch_input' ).addClass( 'globkurier-is-required' );
				
				$( '.globkurier-only-ruch' ).css( 'display', 'contents' );
			}
			
			if ( _isInpost == 1 ) {
				if($('#globkurier-pickup-type-PICKUP').is(':disabled')){
					$( '.globkurier-not-pickup' ).hide();
				}

				$( '.globkurier-only-ruch' ).hide();

				$( '#globkurier_inpost_input' ).addClass( 'globkurier-is-required' );
				$( '#globkurier_ruch_input' ).removeClass( 'globkurier-is-required' );

				// Paczkomat odbioru wymagany tylko dla uslug z doreczeniem do punktu.
				// Dla "dostawy do drzwi" pole pozostaje ukryte i niewymagane.
				if ( inpostDeliveryToPoint == 1 ) {
					$( '.inpost-always' ).css( 'display', 'contents' );
					$( '#globkurier_inpost_input-pickup' ).addClass( 'globkurier-is-required' );
				} else {
					$( '.inpost-always' ).css( 'display', 'none' );
					$( '#globkurier_inpost_input-pickup' ).removeClass( 'globkurier-is-required' ).val( '' );
				}

				if(!$('#globkurier-pickup-type-PICKUP').is(':checked')){
					$( '.globkurier-only-inpost' ).css( 'display', 'contents' );

					// Po pokazaniu wspolnego kontenera ponownie ukryj pole odbioru dla dostawy do drzwi
					if ( inpostDeliveryToPoint != 1 ) {
						$( '.inpost-always' ).css( 'display', 'none' );
					}
				}
			}
			
			if ( _isCrossborder == 1 ) {
				$.post(
					data['ajaxUrl'],
					{
						action: 'globkurierGetCrossborderTerminals',
						nonce: data['globkurier_get_crossborder_terminals_nonce'],
						productId: carrierId
					},
					function (response) {
						let options = [];
						
						if (response.success) {
							let terminals = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
							
							terminals.forEach(function (data) {
								options.push({
									id: data.id,
									text: data.name + ', ' + data.address + ' [' + data.id + ']',
									latitude: data.latitude,
									longitude: data.longitude,
									city: data.city,
									address: data.address,
									openingHours: data.openingHours,
								});
							});
						}

						$( document ).find( '#udi-select-crossborder_terminal_value').select2({
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
						});
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

			if ( window.globkurierCustoms ) {
				window.globkurierCustoms.onCarrierSelected(
					carrierData,
					$( '#globkurier-sender-country' ).val(),
					$( '#globkurier-receiver-country' ).val()
				);
			}

			detailsContainer.show();
			
			getFirstAvailablePickupDay();
			
			let height = $( '#globkurier-height' ).val();
			let width = $( '#globkurier-width' ).val();
			let length = $( '#globkurier-length' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			let sku_content = $( '#globkurier-quantity' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			
			let ajaxData = {
				action: 'globkurierGetProductAddons',
				
				data: {
					productId: carrierId,
					'height': height,
					'width': width,
					'length': length,
					'weight': weight,
					'quantity': quantity,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
					'nonce': $( '#globkurier_get_product_addons_nonce' ).val(),
				}
			};
			
			let loader = $( ' .udi-product-extras-header .udi-loader' );
			loader.show();
			
			getPrice( true );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				let container = $( '.udi-product-extras-body' );
				
				$( '#udi-extra-category-cod' ).hide();
				$( '#udi-extra-category-insurance' ).hide();
				$( '#udi-extra-category-other' ).hide();
				$( '#udi-extra-category-receiver' ).hide();
				
				let cod = $( '#udi-extra-category-cod .udi-product-extras-cat-items' );
				let insurance = $( '#udi-extra-category-insurance  .udi-product-extras-cat-items' );
				let other = $( '#udi-extra-category-other  .udi-product-extras-cat-items' );
				let receiver = $( '#udi-extra-category-receiver  .udi-product-extras-cat-items' );
				
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
		
		$( document ).on( 'change', '[name="globkurier-pickup-type"]', function () {
			
			let pickupType = $( this ).data( 'pickuptype' );

			let _isInpost = $( '#udi-selected-product-is-inpost' ).val( );
			let _inpostDeliveryToPoint = $( '#udi-selected-product-inpost-delivery-to-point' ).val();

			if ( pickupType == 'POINT' ) {
				$( '.globkurier-not-pickup' ).hide();

				if ( _isInpost == 1 ) {
					$( '.globkurier-only-inpost' ).css( 'display', 'contents' );

					// Dla dostawy do drzwi pole odbioru pozostaje ukryte
					if ( parseInt( _inpostDeliveryToPoint ) != 1 ) {
						$( '.inpost-always' ).css( 'display', 'none' );
					}
				}
			}

			if ( pickupType == 'PICKUP' ) {
				$( '.globkurier-not-pickup' ).show();


				if ( _isInpost == 1 ) {
					$( '.globkurier-only-inpost' ).hide();

					// Paczkomat odbioru pokazujemy tylko dla uslug z doreczeniem do punktu
					if ( parseInt( _inpostDeliveryToPoint ) == 1 ) {
						$( '.inpost-always' ).css( 'display', 'contents' );
					} else {
						$( '.inpost-always' ).css( 'display', 'none' );
					}
				}

			}
			
		} );
		
		function getCustomRequiredFields() {
			let orderData = getOrderData().data;
			
			let ajaxData = {
				action: 'globkurierCustomRequiredFields',
				
				data: {
					'productId': orderData.productId,
					'senderCountryId': orderData.senderCountryId,
					'receiverCountryId': orderData.receiverCountryId,
					'collectionType': orderData.collectionType,
					'nonce': $( '#globkurier_get_custom_required_fields_nonce' ).val(),
					'orderData': orderData,
				}
			};
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				
				let results = $.parseJSON( response );
				
				let container = $( '#globkurier-custom-fields-required' );
				
				container.html( '' );
				
				if ( results.length > 0 ) {
					
					$( results ).each( function () {
						
						let f = $( this )[ 0 ];
						
						let field = '';
						
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
									url: data[ 'ajaxUrl' ],
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
												// console.log( {
												// 	id: data.id,
												// 	text: data.name,
												// 	latitude: data.latitude,
												// 	longitude: data.longitude,
												// 	city: data.city,
												// 	address: data.address,
												// 	openingHours: data.openingHours,
												// } );
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
			let productId = $( '#udi-selected-product-id' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			let date = $( '#globkurier-service-date-picker' ).val();
			
			let ajaxData = {
				action: 'globkurierGetFirstPickupDay',
				
				data: {
					'productId': productId,
					'weight': weight,
					'quantity': quantity,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
					'date': date,
					'nonce': $( '#globkurier_get_first_pickup_day_nonce' ).val()
				}
			};
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				let results = $.parseJSON( response );
				if ( results.status == 'ok' ) {
					$( '#globkurier-service-date-picker' ).datepicker( "setDate", new Date( results.date ) );
					
					getPickupTimeRanges();
				}
				$( '#globkurier-service-date-picker' ).prop( 'readonly', false );
			} );
			
		}
		
		function calculatePriceWithExtras() {
			getPrice( false );
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
				
				$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
					
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
		
		$( document ).on( 'change', '#globkurier-service-date-picker', function () {
			getPickupTimeRanges();
		} );
		
		function getPickupTimeRanges() {
			let productId = $( '#udi-selected-product-id' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			let date = $( '#globkurier-service-date-picker' ).val();
			
			let ajaxData = {
				action: 'globkurierGetPickupTimeRanges',
				
				data: {
					'productId': productId,
					'weight': weight,
					'quantity': quantity,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
					'date': date,
					'nonce': $( '#globkurier_get_pickup_time_ranges_nonce' ).val()
				}
			};
			
			let container = $( '#globkurier-service-time-picker' );
			container.html( '' );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
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
		
		$( document ).on( 'click', '.re-select-product', function () {
			$( '.udi-step-select-product' ).show();
			
			$( '.udi-all-products' ).show();
			
			let container = $( '.udi-product-extras-body' );
			
			$( '#udi-extra-category-cod' ).hide();
			$( '#udi-extra-category-insurance' ).hide();
			$( '#udi-extra-category-other' ).hide();
			$( '#udi-extra-category-receiver' ).hide();
			
			let cod = $( '#udi-extra-category-cod .udi-product-extras-cat-items' );
			let insurance = $( '#udi-extra-category-insurance  .udi-product-extras-cat-items' );
			let other = $( '#udi-extra-category-other  .udi-product-extras-cat-items' );
			let receiver = $( '#udi-extra-category-receiver  .udi-product-extras-cat-items' );
			
			cod.html( '' );
			insurance.html( '' );
			other.html( '' );
			receiver.html( '' );
			
			$( '#globkurier-requiredAlternativeAddonsGroups' ).val( '' );
			
			$( '#globkurier-service-extra-fields-INSURANCE' ).html( '' );
			$( '#globkurier-service-extra-fields-CASH_ON_DELIVERY' ).html( '' );
			$( '#globkurier-service-extra-fields-RETURN_OF_DOCUMENTS' ).html( '' );
			$( '#globkurier-service-extra-fields-SENDER_WAYBILL_ADDRESS' ).html( '' );
			
			$( 'input[name="globkurier-selected-product"]' ).prop( 'checked', false );
			
			$( '.udi-step-select-product-details' ).hide();
		} );
		
		function getPrice( refreshPayments = true ) {
			let productId = $( '#udi-selected-product-id' ).val();
			let height = $( '#globkurier-height' ).val();
			let width = $( '#globkurier-width' ).val();
			let length = $( '#globkurier-length' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let senderCountryId = $( '#globkurier-sender-country' ).val();
			let senderPostal = $( '#globkurier-sender-postal' ).val();
			
			let addonIds = [];
			$( '.udi-product-extras-input:checked' ).each( function () {
				addonIds.push( $( this ).val() );
			} );
			
			let insuranceValue = $( '#globkurier-addon-extra-insurance-value' ).val();
			let cashOnDeliveryValue = $( '#globkurier-addon-extra-cod-value' ).val();
			
			let ajaxData = {
				action: 'globkurierGetPrice',
				
				data: {
					'productId': productId,
					'height': height,
					'width': width,
					'length': length,
					'weight': weight,
					'quantity': quantity,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
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
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
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
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
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
		
		function order() {
			
			$( '.globkurier_confirm_send' ).attr( 'disabled', true );
			
			$( '.udi-order-confirm-container .udi-loader-overlay' ).show();
			$( '.udi-order-confirm-container .udi-loader' ).show();
			
			let ajaxData = getOrderData();
			
			if ( !validateOrder() ) {
				$( '.globkurier_confirm_send' ).attr( 'disabled', false );
				
				return;
			}
			
			$( '.udi-save-order .udi-loader' ).show();

			// Odprawa celna: dołącz sekcję customs tylko w ścieżce nadania (nie w wspólnym getOrderData).
			if ( window.globkurierCustoms ) {
				Object.assign( ajaxData.data, window.globkurierCustoms.getPayload() );
			}

			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				let order = $.parseJSON( response );
				
				clearNoticesError();
				clearNoticesSuccess();
				hideNoticesSuccess();
				hideNoticesError();
				
				if ( order.status == 'error' ) {
					
					$( '.udi-order-confirm-container .udi-loader-overlay' ).hide();
					$( '.udi-order-confirm-container .udi-loader' ).hide();
					
					$( '.globkurier_confirm_send' ).attr( 'disabled', false );
					
					$.each( order.fields, function ( key, item ) {
						
						switch ( key ) {
							
							case 'senderAddress[name]':
								key = 'Imię i nazwisko nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-name' ) );
								break;
							case 'senderAddress[postCode]':
								key = 'Kod pocztowy nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-postal' ) );
								break;
							case 'senderAddress[city]':
								key = 'Ulica nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-city' ) );
								break;
							case 'senderAddress[street]':
								key = 'Ulica nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-street' ) );
								break;
							case 'senderAddress[houseNumber]':
								key = 'Nr mieszkania nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-home' ) );
								break;
							case 'senderAddress[apartmentNumber]':
								key = 'Nr lokalu nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-flat' ) );
								break;
							case 'senderAddress[phone]':
								key = 'Numer telefonu nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-contact-phone' ) );
								break;
							case 'senderAddress[email]':
								key = 'Adres email nadawcy';
								globkurier_add_error( '', $( '#globkurier-sender-email' ) );
								break;
							case 'receiverAddress[name]':
								key = 'Imię i nazwisko odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-name' ) );
								break;
							case 'receiverAddress[postCode]':
								key = 'Kod pocztowy odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-postal' ) );
								break;
							case 'receiverAddress[city]':
								key = 'Miasto odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-city' ) );
								break;
							case 'receiverAddress[street]':
								key = 'Ulica odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-street' ) );
								break;
							case 'receiverAddress[houseNumber]':
								key = 'Nr mieszkania odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-home' ) );
								break;
							case 'receiverAddress[apartmentNumber]':
								key = 'Nr lokalu odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-flat' ) );
								break;
							case 'receiverAddress[phone]':
								key = 'Numer telefonu odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-contact-phone' ) );
								break;
							case 'receiverAddress[email]':
								key = 'Adres email odbiorcy';
								globkurier_add_error( '', $( '#globkurier-receiver-email' ) );
								break;
							case 'addons':
								key = 'Dodatki';
								break;
							case 'pickup[timeFrom]':
								key = 'Godzina nadania';
								globkurier_add_error( '', $( '#globkurier-service-time-picker' ) );
								break;
							case 'pickup[timeTo]':
								key = 'Godzina nadania';
								break;
							case 'paymentId':
								key = 'Płatność';
								globkurier_add_error( '', $( '#globkurier-service-payment-picker' ) );
								break;
							case 'containsDangerousGoods':
								key = 'Towary niebezpieczne';
								globkurier_add_error( '', $( '#globkurier-contains-dangerous-goods' ) );
								break;
						}
						
						addNoticeError( '<p>' + gkEsc( key ) + ': <strong>' + gkEsc( item ) + '</strong></p>' );
						
					} );
					showNoticesError();
					$( window ).scrollTop( $( '#globkurier_ship_new_order' ).offset().top );
					
					confirmCorrection();
					
				} else {
					$( '.udi-wpadmin-order-address' ).hide();
					$( '.udi-step-select-product-details' ).hide();
					$( '.udi-order-confirm-container' ).hide();
					
					$( '.globkurier_confirm_send' ).attr( 'disabled', false );
					
					$( '.udi-order-confirm-container .udi-loader-overlay' ).hide();
					$( '.udi-order-confirm-container .udi-loader' ).hide();
					
					addNoticeSuccess( '<p>Dziękujemy, zamówienie zostało złożone pomyślnie.</p>'
						+ '<p>Realizacja usługi nastąpi po odnotowaniu wpłaty na koncie Globkurier.pl (nie dotyczy płatności odroczonej i płatności przy doręczeniu).</p>'
						+ '<p>Informacje związane ze statusem swojej przesyłki oraz list przewozowy otrzymasz na adres e-mail podany w procesie zamówienia.</p>' );
					
					addNoticeSuccess( '<p>Numer zamówienia: <strong>' + gkEsc( order.number ) + '</strong></p>' );

					if ( window.globkurierCustoms && window.globkurierCustoms.getCustomsNotice ) {
						let customsNotice = window.globkurierCustoms.getCustomsNotice();
						if ( customsNotice ) {
							addNoticeSuccess( '<p>' + customsNotice + '</p>' );
						}
					}

					showNoticesSuccess();
					$( window ).scrollTop( $( '#globkurier_ship_new_order' ).offset().top );
				}
				
				$( '.udi-save-order .udi-loader' ).hide();
				$( '.udi-save-order' ).attr( 'disabled', false );
				
				
			} );
		}
		
		function confirmCorrection() {
			$( '.udi-save-order' ).attr( 'disabled', false );
			$( '.udi-order-confirm-container' ).hide();
			$( '.udi-wpadmin-order-address' ).show();
			$( '.udi-step-select-product-details' ).show();
		}
		
		$( document ).on( 'click', '.globkurier_confirm_correction', function () {
			confirmCorrection();
		} );
		
		function getOrderData() {
			
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
			
			let height = $( '#globkurier-height' ).val();
			let width = $( '#globkurier-width' ).val();
			let length = $( '#globkurier-length' ).val();
			let weight = $( '#globkurier-weight' ).val();
			let quantity = $( '#globkurier-quantity' ).val();
			
			let receiverName = $( '#globkurier-receiver-name' ).val();
			let receiverCountryId = $( '#globkurier-receiver-country' ).val();
			let receiverPostal = $( '#globkurier-receiver-postal' ).val();
			let receiverCity = $( '#globkurier-receiver-city' ).val();
			let receiverStreet = $( '#globkurier-receiver-street' ).val();
			let receiverHome = $( '#globkurier-receiver-home' ).val();
			let receiverFlat = $( '#globkurier-receiver-flat' ).val();
			let receiverPhone = $( '#globkurier-receiver-contact-phone' ).val();
			let receiverEmail = $( '#globkurier-receiver-email' ).val();
			
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
			
			let inpostDeliveryToPoint = $( '#udi-selected-product-inpost-delivery-to-point' ).val();

			let inpostSenderPointId = '';
			let inpostReceiverPointId = '';
			let ruchReceiverPointId = '';

			if ( parseInt( isInpost ) == 1 ) {
				inpostSenderPointId = $( '#udi-select-inpost-sender' ).val();

				// Punkt odbioru przekazujemy tylko dla uslug z doreczeniem do paczkomatu
				if ( parseInt( inpostDeliveryToPoint ) == 1 ) {
					inpostReceiverPointId = $( '#udi-select-inpost-pickup_value' ).val();
				}
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
			
			let receiverAddressPointId = $('#globkurier-custom-extra-required-receiverAddressPointId').val();
			let senderAddressPointId = $('#globkurier-custom-extra-required-senderAddressPointId').val();
			
			let isCrossborderProduct = $( '#udi-selected-product-is-crossborder' ).val();
			let crossborderTerminal = $( '#udi-select-crossborder_terminal_value' ).val();
			
			let ajaxData = {
				action: 'globkurierOrder',
				data: {
					'wcOrderID': wcOrderID,
					'receiverCountryId': receiverCountryId,
					'receiverPostCode': receiverPostal,
					'senderCountryId': senderCountryId,
					'senderPostCode': senderPostal,
					
					'productId': productId,
					'isRuch': isRuch,
					'isInpost': isInpost,
					'inpostDeliveryToPoint': inpostDeliveryToPoint,

					'extraPickupCarrierId': extraPickupCarrierId,
					'extraPickupCarrierValue': extraPickupCarrierValue,
					'extraPickupCarrierText': extraPickupCarrierText,
					
					'length': length,
					'width': width,
					'height': height,
					'weight': weight,
					'quantity': quantity,
					
					'sender_name': senderName,
					'sender_city': senderCity,
					'sender_street': senderStreet,
					'sender_home': senderHome,
					'sender_flat': senderFlat,
					'sender_postCode': senderPostal,
					'sender_countryId': senderCountryId,
					'sender_phone': senderPhone,
					'sender_email': senderEmail,
					
					'receiver_name': receiverName,
					'receiver_city': receiverCity,
					'receiver_street': receiverStreet,
					'receiver_home': receiverHome,
					'receiver_flat': receiverFlat,
					'receiver_postCode': receiverPostal,
					'receiver_countryId': receiverCountryId,
					'receiver_phone': receiverPhone,
					'receiver_email': receiverEmail,
					
					'description': description,
					'sku_content': skuContent,
					
					'paymentId': paymentId,
					'collectionType': collectionType,
					
					'inpostSenderPointId': inpostSenderPointId || '',
					'inpostReceiverPointId': inpostReceiverPointId || '',
					
					'ruchReceiverPointId': ruchReceiverPointId || '',
					
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
		
		function validateOrder() {
			clearNoticesError();
			
			let valueErrors = 0;
			
			$( '.globkurier-is-required' ).filter( function () {
				return $( this )
					.closest( '.globkurier-only-inpost, .globkurier-only-ruch, .globkurier-only-crossborder, .globkurier-not-pickup' )
					.css( 'display' ) !== 'none';
			} ).each( function () {
				
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
			
			
			$( '.udi-service-options-body .globkurier-is-required' ).filter( function () {
				return $( this )
					.closest( '.globkurier-only-inpost, .globkurier-only-ruch, .globkurier-only-crossborder, .globkurier-not-pickup' )
					.css( 'display' ) !== 'none';
			} ).each( function () {
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
			
			let data = getOrderData().data;
			
			$( '.udi-save-order' ).attr( 'disabled', true );
			$( '.udi-wpadmin-order-address' ).hide();
			$( '.udi-step-select-product-details' ).hide();
			
			$( '#udi-confirm-address-sender-name' ).text( data.sender_name );
			$( '#udi-confirm-address-sender-street' ).text( data.sender_street );
			$( '#udi-confirm-address-sender-homeNumber' ).text( data.sender_home );
			$( '#udi-confirm-address-sender-flatNumber' ).text( data.sender_flat );
			$( '#udi-confirm-address-sender-city' ).text( data.sender_city );
			$( '#udi-confirm-address-sender-postal' ).text( data.sender_postCode );
			$( '#udi-confirm-address-sender-flnames' ).text( data.sender_name );
			$( '#udi-confirm-address-sender-phone' ).text( data.sender_phone );
			$( '#udi-confirm-address-sender-email' ).text( data.sender_email );
			
			$( '#udi-confirm-address-receiver-name' ).text( data.receiver_name );
			$( '#udi-confirm-address-receiver-street' ).text( data.receiver_street );
			$( '#udi-confirm-address-receiver-homeNumber' ).text( data.receiver_home );
			$( '#udi-confirm-address-receiver-flatNumber' ).text( data.receiver_flat );
			$( '#udi-confirm-address-receiver-city' ).text( data.receiver_city );
			$( '#udi-confirm-address-receiver-postal' ).text( data.receiver_postCode );
			$( '#udi-confirm-address-receiver-flnames' ).text( data.receiver_name );
			$( '#udi-confirm-address-receiver-phone' ).text( data.receiver_phone );
			$( '#udi-confirm-address-receiver-email' ).text( data.receiver_email );
			
			$( '#udi-confirm-product-name' ).text( data.carrier_name + ':' );
			$( '#udi-confirm-product-price' ).html( $( '#udi-carrierNetPrice' ).val() + 'zł' );
			
			$( '#udi-confirm-product-count .udi-confirm-value' ).text( data.quantity );
			$( '#udi-confirm-product-date .udi-confirm-value' ).text( data.date );
			$( '#udi-confirm-product-time .udi-confirm-value' ).text( data.time );
			$( '#udi-confirm-product-content .udi-confirm-value' ).text( data.description );
			
			$( '#udi-confirm-product-terminal' ).hide();
			
			$( '#udi-confirm-payment-product-name' ).text( data.carrier_name );
			
			$( '#udi-confirm-payment-product-price' ).html( $( '#udi-carrierNetPrice' ).val() + 'zł' );
			
			$( '#udi-confirm-payment-method' ).text( data.payment_name );
			
			let totalNetPrice = $( '#udi-carrierNetPriceWithAddons' ).val() + 'zł (netto)';
			let totalGrossPrice = $( '#udi-carrierGrossPriceWithAddons' ).val() + 'zł (brutto)';
			$( '#udi-confirm-payment-total-price' ).html( totalNetPrice + ' / ' + totalGrossPrice );
			
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
		} );
		
		$( document ).on( 'click', '.globkurier_confirm_send', function () {
			order();
		} );
		
		function showNoticesError() {
			let container = $( '.globkurier-notices-error' ).show();
		}
		
		function hideNoticesError() {
			let container = $( '.globkurier-notices-error' ).hide();
		}
		
		function clearNoticesError() {
			let container = $( '.globkurier-notices-error-body' ).html( '' );
		}
		
		function addNoticeError( notice ) {
			let container = $( '.globkurier-notices-error-body' ).append( notice );
		}
		
		function showNoticesSuccess() {
			let container = $( '.globkurier-notices-success' ).show();
		}
		
		function hideNoticesSuccess() {
			let container = $( '.globkurier-notices-success' ).hide();
		}
		
		function clearNoticesSuccess() {
			let container = $( '.globkurier-notices-success-body' ).html( '' );
		}
		
		function addNoticeSuccess( notice ) {
			let container = $( '.globkurier-notices-success-body' ).append( notice );
		}
		
		$( '.udi-input-with-hidden-label input, .udi-input-with-hidden-label select' ).focusin( function () {
			$( this ).parent().find( 'input, select' ).css( 'padding', '15px 5px 0px 5px' );
			$( this ).parent().find( 'label' ).show();
		} );
		$( '.udi-input-with-hidden-label input, .udi-input-with-hidden-label select' ).focusout( function () {
			$( this ).parent().find( 'input, select' ).css( 'padding', '2px' );
			$( this ).parent().find( 'label' ).hide();
		} );
		
		// opts.useCache - automatyczne pobranie przy otwarciu zamowienia moze uzyc statusu z cache serwera.
		$( document ).on( 'click', '.udi-get-current-status', function ( e, opts ) {
			let number = $( this ).data( 'number' );

			let ajaxData = {
				action: 'globkurierGetCurrentStatus',

				data: {
					orderNumber: number,
					nonce: $( '#globkurier_get_current_status_nonce' ).val() || data[ 'globkurier_get_current_status_nonce' ],
					useCache: opts && opts.useCache ? 1 : 0,
				}
			};
			
			let button = $( this );
			
			button.hide();
			
			let loader = $( this ).parent().find( '.udi-loader' );
			
			loader.show();
			
			let statusText = $( this ).parent().find( '.udi-status-value' );
			statusText.text( '' );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				
				let status = $.parseJSON( response );
				
				if ( status.status == 'error' ) {
					statusText.text( 'Brak zamówienia w systemie' );
					button.text( 'Aktualizuj' );
					button.show();
				} else {
					let text = status.data.statuses[ status.data.statuses.length - 1 ].name;
					statusText.text( text );
					button.text( 'Aktualizuj' );
					button.show();
				}
				
				loader.hide();
			} );
			
		} );
		
		$( document ).on( 'input', '#globkurier_inpost_input', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier_inpost_input_value' ) );
			} else {
				$( '.ui-autocomplete' ).html( '' );
				$( '#globkurier_inpost_input_value' ).val( '' );
			}
		} );
		$( document ).on( 'change', '#globkurier_inpost_input', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier_inpost_input_value' ) );
			} else {
				let menuId = $( this ).data( 'data-menuid' );
				$( '.ui-autocomplete' + '#' + menuId ).html( '' );
				
				$( '#globkurier_inpost_input' ).val( '' );
				$( '#globkurier_inpost_input_value' ).val( '' );
				
				$.post( {
					url: data[ 'ajaxUrl' ],
					dataType: 'json',
					minLength: 3,
					data: {
						action: 'globkurierSaveInpostPointsSession',
						id: '',
						value: '',
						nonce: $( '#globkurier_save_inpost_points_session_nonce' ).val()
					},
					success: function ( data ) {
					},
				} );
			}
		} );
		$( document ).on( 'click', '#globkurier_inpost_input', function () {
			let menuId = $( this ).data( 'data-menuid' );
			$( '.ui-autocomplete' + '#' + menuId ).show();
		} );
		
		$( document ).on( 'input', '#globkurier_inpost_input-pickup', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier_inpost_input-pickup_value' ) );
			} else {
				$( '.ui-autocomplete' ).html( '' );
				$( '#globkurier_inpost_input-pickup_value' ).val( '' );
			}
		} );
		$( document ).on( 'change', '#globkurier_inpost_input-pickup', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier_inpost_input-pickup_value' ) );
			} else {
				// $( '.ui-autocomplete' ).html( '' );
				let menuId = $( this ).data( 'data-menuid' );
				$( '.ui-autocomplete' + '#' + menuId ).html( '' );
				
				$( '#globkurier_inpost_input-pickup' ).val( '' );
				$( '#globkurier_inpost_input-pickup_value' ).val( '' );
				
				$.post( {
					url: data[ 'ajaxUrl' ],
					dataType: 'json',
					minLength: 3,
					data: {
						action: 'globkurierSaveInpostPointsSession',
						id: '',
						value: '',
						nonce: $( '#globkurier_save_inpost_points_session_nonce' ).val()
					},
					success: function ( data ) {
					},
				} );
			}
		} );
		$( document ).on( 'click', '#globkurier_inpost_input-pickup', function () {
			let menuId = $( this ).data( 'data-menuid' );
			$( '.ui-autocomplete' + '#' + menuId ).show();
		} );
		
		$( document ).on( 'keyup', '#globkurier\\[inpost_default\\]', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier\\[inpost_default_code\\]' ) );
			} else {
				$( '.ui-autocomplete' ).html( '' );
				$( '#globkurier\\[inpost_default_code\\]' ).val( '' );
			}
		} );
		$( document ).on( 'change', '#globkurier\\[inpost_default\\]', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteInpost( search, $( this ), $( '#globkurier\\[inpost_default_code\\]' ) );
			} else {
				
				$( '.ui-autocomplete' ).html( '' );
				$( '#globkurier\\[inpost_default\\]' ).val( '' );
				$( '#globkurier\\[inpost_default_code\\]' ).val( '' );
			}
		} );
		$( document ).on( 'click', '#globkurier\\[inpost_default\\]', function () {
			let menuId = $( this ).data( 'data-menuid' );
			$( '.ui-autocomplete' + '#' + menuId ).show();
		} );
		
		$( document ).on( 'change ', '#globkurier\\[default\\]\\[parcel\\]\\[content\\]', function () {
			if ( $( this ).val() == 'Inne' ) {
				$( '.globkurier-content-other' ).show();
			} else {
				$( '.globkurier-content-other' ).hide();
			}
		} );
		
		$( document ).on( 'change', '#globkurier-content', function () {
			if ( $( this ).val() == 'Inne' ) {
				$( document ).find( '#globkurier-otherContent' ).show();
			} else {
				$( document ).find( '#globkurier-otherContent' ).hide();
			}
		} );
		
		$( document ).on( 'input', '#globkurier_ruch_input', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteRuch( search, $( this ), $( '#globkurier_ruch_input_value' ) );
			} else {
				$( '#globkurier_inpost_input_value' ).val( '' );
			}
		} );
		
		$( document ).on( 'change', '#globkurier_ruch_input', function () {
			let search = $( this ).val().trim();
			
			if ( search != '' ) {
				autocompleteRuch( search, $( this ), $( '#globkurier_ruch_input_value' ) );
			} else {
				
				let menuId = $( this ).data( 'data-menuid' );
				$( '.ui-autocomplete' + '#' + menuId ).html( '' );
				// $( '.ui-autocomplete' ).html( '' );
				
				$( '#globkurier_ruch_input' ).val( '' );
				$( '#globkurier_ruch_input_value' ).val( '' );
				
				$.post( {
					url: data[ 'ajaxUrl' ],
					dataType: 'json',
					minLength: 3,
					data: {
						action: 'globkurierSaveRuchPointsSession',
						id: '',
						value: '',
						nonce: $( '#globkurier_save_ruch_points_session_nonce' ).val()
					},
					success: function ( data ) {
					},
				} );
			}
		} );
		
		$( document ).on( 'click', '#globkurier_ruch_input', function () {
			let menuId = $( this ).data( 'data-menuid' );
			$( '.ui-autocomplete' + '#' + menuId ).show();
		} );
		
		function autocompleteInpost( search, input, code ) {
			if ( typeof code === 'undefined' ) {
				code = $( '#globkurier_inpost_input_value' );
			}
			
			input.autocomplete( {
				source: function ( request, response ) {
					$.post( {
						url: data[ 'ajaxUrl' ],
						dataType: 'json',
						minLength: 3,
						data: {
							action: 'globkurierGetInpostPointsSelect2',
							nonce: $( '#globkurier_get_inpost_points_select2_nonce' ).val(),
							city: search,
						},
						success: function ( data ) {
							let parsedData = JSON.parse( data );
							
							if ( parsedData.length == 0 ) {
								return response( [ {
									'label': 'Brak wyników',
									'value': search,
								} ] )
							}
							
							return response( parsedData );
						},
					} );
				},
				minLength: 3,
				open: function () {
					// let cityInput = $( '#globkurier_inpost_input' );
					let cityInput = input;
					let menuItems = $( '.ui-menu-item' );
					
					$( '.ui-autocomplete' ).css( 'width', 'max-content' );
					
					var scrollbarWidth = $( '.ui-autocomplete' )[ 0 ].offsetWidth - $( '.ui-autocomplete' )[ 0 ].clientWidth;
					
					menuItems.css( 'max-width', cityInput.width() - scrollbarWidth );
				},
				select: function ( event, item ) {
					code.val( item.item.id );
					$.post( {
						url: data[ 'ajaxUrl' ],
						dataType: 'json',
						minLength: 3,
						data: {
							action: 'globkurierSaveInpostPointsSession',
							id: item.item.id,
							value: item.item.value,
							nonce: $( '#globkurier_save_inpost_points_session_nonce' ).val(),
						},
						success: function ( data ) {
						},
					} );
				}
			} );
			
			input.data( 'data-menuid', input.autocomplete( "instance" ).menu.element.attr( 'id' ) );
		}
		
		function autocompleteRuch( search, input, code ) {
			
			if ( typeof code === 'undefined' ) {
				code = $( '#globkurier_inpost_input_value' );
			}
			
			// $( '#globkurier_ruch_input_value' ).val( '' );
			
			input.autocomplete( {
				source: function ( request, response ) {
					$.post( {
						url: data[ 'ajaxUrl' ],
						dataType: 'json',
						minLength: 3,
						data: {
							action: 'globkurierGetRuchPoints',
							city: search,
							nonce: $( '#globkurier_get_ruch_points_nonce' ).val()
						},
						success: function ( data ) {
							let parsedData = JSON.parse( data );
							
							if ( parsedData.length == 0 ) {
								return response( [ {
									'label': 'Brak wyników',
									'value': search,
								} ] )
							}
							
							return response( parsedData );
						},
						
						// error: function ( data ) {
						// 	console.log( 'error', data );
						// 	response( JSON.parse( data ) );
						// }
					} );
				},
				minLength: 3,
				open: function () {
					let cityInput = $( '#globkurier_ruch_input' );
					let menuItems = $( '.ui-menu-item' );
					
					$( '.ui-autocomplete' ).css( 'width', 'max-content' );
					
					var scrollbarWidth = $( '.ui-autocomplete' )[ 0 ].offsetWidth - $( '.ui-autocomplete' )[ 0 ].clientWidth;
					
					menuItems.css( 'max-width', cityInput.width() - scrollbarWidth );
				},
				select: function ( event, item ) {
					
					$( '#globkurier_ruch_input_value' ).val( item.item.id );
					
					$.post( {
						url: data[ 'ajaxUrl' ],
						dataType: 'json',
						minLength: 3,
						data: {
							action: 'globkurierSaveRuchPointsSession',
							id: item.item.id,
							value: item.item.value,
							nonce: $( '#globkurier_save_ruch_points_session_nonce' ).val(),
						},
						success: function ( data ) {
						},
					} );
					
				}
				
			} );
			
			input.data( 'data-menuid', input.autocomplete( "instance" ).menu.element.attr( 'id' ) );
		}
		
		
		function isInpost( product ) {
			return ( ( product.data( 'carriername' ) || '' ) == 'inPost-Paczkomaty' ) ? 1 : 0;
		}
	
		function isRuch( product ) {
			// API zwraca 'ORLEN Paczka' - bez wielkosci liter (jak w bulk-send.js), inaczej ORLEN nie mial pola punktu odbioru.
			return ( ( product.data( 'carriername' ) || '' ).toLowerCase() == 'orlen paczka' ) ? 1 : 0;
		}
		
		function isCrossborder( product ) {
			return (product.data( 'collectiontypes' ) && product.data( 'collectiontypes' ).includes( 'CROSSBORDER' ) )? 1 : 0;
		}
		
		function hasLabel( product, label ) {
			let labels = product.data( 'labels' ).split( ',' );
			return $.inArray( label, labels ) > -1;
		}
		
		$( document ).on( 'change', '#globkurier\\[inpost_active\\]', function ( e ) {
			if ( $( this ).val() == 1 ) {
				$( '.updateInpostContainer' ).show();
			} else {
				$( '.updateInpostContainer' ).hide();
			}
		} );
		
		$( document ).on( 'change', '#globkurier\\[ruch_active\\]', function ( e ) {
			if ( $( this ).val() == 1 ) {
				$( '.updateRuchContainer' ).show();
			} else {
				$( '.updateRuchContainer' ).hide();
			}
		} );
		$( document ).on( 'click', '.updateInpostButton', function ( e ) {
			let btn = $( this );
			let loader = btn.parent().find( '.udi-loader' );
			
			let ajaxData = {
				action: 'globkurierUpdateInpost',
				nonce: data[ 'globkurier_update_points_nonce' ],
			}
			
			loader.show();
			btn.attr( 'disabled', true );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				loader.hide();
				btn.attr( 'disabled', false );
			} );
		} );
		
		$( document ).on( 'click', '.updateRuchButton', function ( e ) {
			let btn = $( this );
			let loader = btn.parent().find( '.udi-loader' );
			
			let ajaxData = {
				action: 'globkurierUpdateRuch',
				nonce: data[ 'globkurier_update_points_nonce' ],
			}
			
			loader.show();
			btn.attr( 'disabled', true );
			
			$.post( data[ 'ajaxUrl' ], ajaxData, function ( response ) {
				loader.hide();
				btn.attr( 'disabled', false );
			} );
		} );
		
		$( document ).on( 'change', '[name="udi-product-extras[PAID_PICKUP][932][]"]', function () {
			if ( $( this ).is( ':checked' ) ) {
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "checked", false );
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "disabled", true );
			} else {
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "disabled", false );
			}
		} );
		
		$( document ).on( 'change', '[name="udi-product-extras[ORDERED_COURIER][933][]"]', function () {
			if ( $( this ).is( ':checked' ) ) {
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "checked", false );
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "disabled", true );
			} else {
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "disabled", false );
			}
		} );
		
		$( document ).on( 'change', '[name="globkurier-pickup-type"]', function () {
			
			if ( $( this ).val() === 'POINT' ) {
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "checked", false );
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "disabled", true );
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).attr( "title", 'Dodatek mam zamówionego kuriera/ podjazd kuriera dodatkowo płatny jest niemożliwy do wybrania przy typie nadania: nadam przesyłkę w terminalu.' );
				
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "checked", false );
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "disabled", true );
				$( '[name="udi-product-extras[ORDERED_COURIER][932][]"]' ).attr( "title", 'Dodatek mam zamówionego kuriera/ podjazd kuriera dodatkowo płatny jest niemożliwy do wybrania przy typie nadania: nadam przesyłkę w terminalu.' );
			} else {
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).prop( "disabled", false );
				$( '[name="udi-product-extras[PAID_PICKUP][932][]"]' ).attr( "title", '' );
				
				$( '[name="udi-product-extras[ORDERED_COURIER][933][]"]' ).prop( "disabled", false );
				$( '[name="udi-product-extras[ORDERED_COURIER][932][]"]' ).attr( "title", '' );
			}
		} );
		
		
	} );
} )( jQuery );