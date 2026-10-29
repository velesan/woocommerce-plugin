<?php

namespace udigroup_globkurier;

class GlobKurierOrder extends GlobKurier{
	
	public function pickupTimeRanges( $params ){
		
		$params = apply_filters( 'globkurier_get_pickup_time_ranges_params', $params );
		do_action( 'globkurier_before_get_pickup_time_ranges', $params );
		
		$requiredFields = [
			'productId',
			'senderCountryId',
			'receiverCountryId',
			'receiverPostCode',
			'senderPostCode',
			'date',
			'weight',
			'quantity',
		];
		
		foreach( $requiredFields as $requiredField ){
			if( ! isset( $params[ $requiredField ] ) ){
				$this->handleError( 'Brak Wymaganego Pola: ' . $requiredField );
			}
		}
		
		$function = 'order/pickupTimeRanges';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $params, $method );
		$pickupTimeRanges = apply_filters( 'globkurier_pickup_time_ranges', $response[ 'data' ] );
		
		if(!is_array($pickupTimeRanges)){
			$pickupTimeRanges = [];
		}
		
		$filteredPickupTimeRanges = array_values(array_filter($pickupTimeRanges, function($pickupTimeRange) use ($params) {
			return ($pickupTimeRange['date'] ?? null) == ($params['date'] ?? null);
		}));
		
		do_action( 'globkurier_after_get_pickup_time_ranges', $response, $filteredPickupTimeRanges );
		
		return $filteredPickupTimeRanges;
	}
	
	public function price( $params ){
		
		$params = apply_filters( 'globkurier_get_price_params', $params );
		do_action( 'globkurier_before_get_price', $params );
		
		$requiredFields = [
			'productId',
			'senderCountryId',
			'receiverCountryId',
			'receiverPostCode',
			'senderPostCode',
			'width',
			'height',
			'length',
			'weight',
			'quantity',
		];
		
		foreach( $requiredFields as $requiredField ){
			if( ! isset( $params[ $requiredField ] ) ){
				$this->handleError( 'Brak Wymaganego Pola: ' . $requiredField );
			}
		}
		
		$function = 'order/price';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $params, $method );
		
		$price = apply_filters( 'globkurier_price', $response[ 'data' ] );
		
		do_action( 'globkurier_after_get_price', $response, $price );
		
		return $price;
	}
	
	public function payments( $params ){
		
		$params = apply_filters( 'globkurier_get_payments_params', $params );
		do_action( 'globkurier_before_get_payments', $params );
		
		$requiredFields = [
			'productId',
			'grossOrderPrice',
		];
		
		foreach( $requiredFields as $requiredField ){
			if( ! isset( $params[ $requiredField ] ) ){
				$this->handleError( 'Brak Wymaganego Pola: ' . $requiredField );
			}
		}
		
		$function = 'order/payments';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $params, $method );
		
		$payments = apply_filters( 'globkurier_payments', $response[ 'data' ] );
		
		if( isset( get_option( 'globkurier' )[ 'payment' ] ) ){
			$default = get_option( 'globkurier' )[ 'payment' ];
			foreach( $payments as &$payment ){
				$payment[ 'default' ] = ( $payment[ 'id' ] == $default ) ? '1' : '';
			}
		}
		
		do_action( 'globkurier_after_get_payments', $response, $payments );
		
		return $payments;
	}
	
	public function orderShipment( $params, $returnOnError = false ){
		
		$params = apply_filters( 'globkurier_get_order_params', $params );
		
		do_action( 'globkurier_before_order', $params );
		do_action( 'globkurier_before_order_params_parse', $params );
		
		$wcOrderID = htmlentities( $params[ 'wcOrderID' ] );
		
		$parsedParams = $this->parseOrderParams( $params );

		do_action( 'globkurier_after_order_params_parse', $params, $parsedParams );

		// GlobKurier nie przyjmuje towarow niebezpiecznych - odrzucamy przed zapytaniem do API (formularz blokuje to
		// juz w JS); blad w formacie odpowiedzi API, zeby metabox i wysylka zbiorcza pokazaly go jak dotad.
		if( ! empty( $parsedParams[ 'containsDangerousGoods' ] ) ){
			$order = [
				'fields' => [ 'containsDangerousGoods' => __( 'GlobKurier nie przyjmuje towarów niebezpiecznych - usuń je z przesyłki i odznacz to pole.', 'globkurier' ) ],
				'status' => 'error',
			];

			if( $returnOnError ){
				return [ 'errors' => $order ];
			}

			echo wp_json_encode( $order );
			die;
		}

		$function = 'order';
		$method   = 'POST';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $parsedParams, $method );
		
		$order = apply_filters( 'globkurier_order', $response[ 'data' ] );
		
		if( $response[ 'code' ] > 204 ){
			if($returnOnError){
				return ['errors' => $order];
			}else{
				$order[ 'status' ] = 'error';
				echo wp_json_encode( $order );
				die;
			}
		} else{
			$order[ 'status' ] = 'success';
			
			$extras = [
				'cod_value'    => $params[ 'cod_value' ] ?? null,
				'carrier_name' => $params[ 'carrier_name' ] ?? '',
				'payment_name' => $params[ 'payment_name' ] ?? '',
			];
			
			$this->saveOrderToWc( $order, $wcOrderID, $parsedParams, $extras );
		}
		
		do_action( 'globkurier_after_order', $response, $order );
		
		return $order;
	}
	
	private function parseOrderParams( $params ): array{
		
		$isInpost = $params[ 'isInpost' ];
		$isRuch   = $params[ 'isRuch' ];
		
		unset( $params[ 'isInpost' ], $params[ 'isRuch' ] );
		
		$shipment = [
			'productId' => $params[ 'productId' ],
			'length'    => $params[ 'length' ],
			'width'     => $params[ 'width' ],
			'height'    => $params[ 'height' ],
			'weight'    => $params[ 'weight' ],
			'quantity'  => $params[ 'quantity' ],
		];
		
		$senderAddress = [
			'name'            => $params[ 'sender_name' ],
			'city'            => $params[ 'sender_city' ],
			'street'          => $params[ 'sender_street' ],
			'houseNumber'     => $params[ 'sender_home' ],
			'apartmentNumber' => $params[ 'sender_flat' ],
			'postCode'        => $params[ 'sender_postCode' ],
			'countryId'       => $params[ 'sender_countryId' ],
			'phone'           => preg_replace( '/[^0-9]/', '', $params[ 'sender_phone' ] ),
			'email'           => $params[ 'sender_email' ],
			
			'stateId' => htmlentities( $params[ 'senderStateId' ] ?? '' ),
		];
		
		if(isset($params['senderAddressPointId']) && !empty($params['senderAddressPointId'])){
			$senderAddress[ 'pointId' ] = $params[ 'senderAddressPointId' ] ?? '';
		}
		
		if($params['collectionType'] == 'CROSSBORDER' && isset($params[ 'crossborderTerminal' ])){
			$senderAddress[ 'pointId' ] = $params[ 'crossborderTerminal' ] ?? '';
		}
		
		
		if( $params[ 'collectionType' ] == 'POINT' && $isInpost == 1 ){
			$senderAddress[ 'pointId' ] = $params[ 'inpostSenderPointId' ] ?? '';
		}
		
		if( $senderAddress[ 'apartmentNumber' ] == '' ){
			unset( $senderAddress[ 'apartmentNumber' ] );
		}
		
		if( empty( $senderAddress[ 'stateId' ] ) ){
			unset( $senderAddress[ 'stateId' ] );
		}
		
		$receiverAddress = [
			'name'            => $params[ 'receiver_name' ],
			'city'            => $params[ 'receiver_city' ],
			'street'          => $params[ 'receiver_street' ],
			'houseNumber'     => $params[ 'receiver_home' ],
			'apartmentNumber' => $params[ 'receiver_flat' ],
			'postCode'        => $params[ 'receiver_postCode' ],
			'countryId'       => $params[ 'receiver_countryId' ],
			'phone'           => preg_replace( '/[^0-9]/', '', $params[ 'receiver_phone' ] ),
			'email'           => $params[ 'receiver_email' ],
			'stateId'         => htmlentities( $params[ 'receiverStateId' ] ?? '' ),
		];
		
		if(isset($params['receiverAddressPointId']) && !empty($params['receiverAddressPointId'])){
			$receiverAddress[ 'pointId' ] = $params[ 'receiverAddressPointId' ] ?? '';
		}
		
		if( $isInpost == 1 ){
			// Dla InPost paczkomat ustawiamy punkt odbioru tylko gdy usluga dostarcza do punktu.
			// Uslugi "dostawa do drzwi" (deliveryTypeOptions bez POINT) nie wymagaja paczkomatu odbiorcy.
			$inpostDeliveryToPoint = !isset( $params[ 'inpostDeliveryToPoint' ] ) || $params[ 'inpostDeliveryToPoint' ] == 1;

			if( $inpostDeliveryToPoint ){
				$receiverAddress[ 'pointId' ] = $params[ 'inpostReceiverPointId' ] ?? '';
			}
		} else if( $isRuch == 1 ){
			// Punkt ORLEN odbiorcy niezaleznie od sposobu nadania - przy odbiorze kurierem (PICKUP) API tez go wymaga.
			$receiverAddress[ 'pointId' ] = $params[ 'ruchReceiverPointId' ] ?? '';
		}
		
		if( empty( $receiverAddress[ 'stateId' ] ) ){
			unset( $receiverAddress[ 'stateId' ] );
		}
		
		if( $receiverAddress[ 'apartmentNumber' ] == '' ){
			unset( $receiverAddress[ 'apartmentNumber' ] );
		}
		
		$time = mb_split( ' - ', $params[ 'time' ] );
		
		$pickup = [];
		if( $params[ 'collectionType' ] == 'PICKUP' ){
			$pickup = [
				'date'     => $params[ 'date' ],
				'timeFrom' => $time[ 0 ] ?? '',
				'timeTo'   => $time[ 1 ] ?? '',
			];
		}
		
		parse_str( $params[ 'addons' ], $rawAddons );
		
		$addons = [];
		foreach( $rawAddons[ 'udi-product-extras' ] ?? [] as $category => $id ){
			
			if(\is_array($id)){
				foreach ($id as $iKey => $iItem) {
					$addons[ $category ][] = $iItem;
				}
			}else{
				$addons[ $category ] = key( $id );
			}
			
		}
		
		$extras = [];
		foreach( $addons as $category => $id ){
			switch( $category ){
				
				case 'INSURANCE':
					$extras[] = [
						'id'   => $id[0][0],
						'value' => $params[ 'insurance' ] ?? 0,
					];
					break;
				
				case 'CASH_ON_DELIVERY':
					$extra = [
						'id'                => $id[0][0],
						'value'             => $params[ 'cod_value' ] ?? '',
						'bankAccountNumber' => $params[ 'cod_bankAccountNumber' ] ?? '',
						'name'              => $params[ 'cod_name' ] ?? '',
						'addressLine1'      => $params[ 'cod_addressLine1' ] ?? '',
					];
					
					$codAddressLine2 = $params[ 'cod_addressLine2' ];
					if( ! empty( $codAddressLine2 ) ){
						$extra[ 'addressLine2' ] = $codAddressLine2;
					}
					
					$extras[] = $extra;
					break;
				
				case 'RETURN_OF_DOCUMENTS':
					$extra = [
						'id' => $id[0][0],
					];
					
					$rodContent = $params[ 'rod_content' ];
					if( ! empty( $rodContent ) ){
						$extra[ 'type' ] = $rodContent;
					}
					
					$rodQuantity = $params[ 'rod_quantity' ];
					if( ! empty( $rodQuantity ) ){
						$extra[ 'quantity' ] = $rodQuantity;
					}
					
					$rodDescription = $params[ 'rod_description' ];
					if( ! empty( $rodDescription ) ){
						$extra[ 'description' ] = $rodDescription;
					}
					
					$extras[] = $extra;
					break;
				
				case 'SENDER_WAYBILL_ADDRESS':
					$extra = [
						'id'          => $id[0][0],
						'name'        => $params[ 'swa_name' ] ?? '',
						'surname'     => $params[ 'swa_surname' ] ?? '',
						'houseNumber' => $params[ 'swa_houseNumber' ] ?? '',
						'street'      => $params[ 'swa_street' ] ?? '',
						'city'        => $params[ 'swa_city' ] ?? '',
						'postCode'    => $params[ 'swa_postCode' ] ?? '',
						'countryId'   => $params[ 'swa_countryId' ] ?? '',
						'email'       => $params[ 'swa_email' ] ?? '',
						'type'        => $params[ 'swa_type' ] ?? '',
						'phone'       => preg_replace( '/[^0-9]/', '', $params[ 'swa_phone' ] ?? '' ),
					];
					
					$swaApartmentNumber = $params[ 'swa_apartmentNumber' ];
					if( ! empty( $swaApartmentNumber ) ){
						$extra[ 'apartmentNumber' ] = $swaApartmentNumber;
					}
					
					$extras[] = $extra;
					
					break;
				
				default:
					if(is_array($id)){
						foreach ($id as $item) {
							$extras[] = [
								'id' => $item[0],
							];
						}
					}else{
						$extras[] = [
							'id' => $id,
						];
					}
					
					break;
			}
			
		}
		
		
		$description = htmlentities($params['description'] ?? '');
		$sku_content = !empty($params['sku_content']) ? htmlentities($params['sku_content']) : '';
		
		$content = $description;

		if( !empty( $sku_content ) ){
			$content .= ': ' .$sku_content;
		}
		
		$maxContentLength = 30;
		if(strlen($content) > $maxContentLength){
			
			if(!empty( $sku_content )) {
				$content = $sku_content;
			}
			
			if(strlen($content) > $maxContentLength){
				$content = mb_substr($content, 0, $maxContentLength-2) . '..';
			}
		}
		
		$content = apply_filters( 'globkurier_order_content', $content, $params );
		
		$parsed = apply_filters('globkurier_order_params',[
			'shipment'        => $shipment,
			'senderAddress'   => $senderAddress,
			'receiverAddress' => $receiverAddress,
			'pickup'          => $pickup,
			'paymentId'       => htmlentities( $params[ 'paymentId' ] ),
			'content'         => $content,
			'collectionType'  => htmlentities( $params[ 'collectionType' ] ),
			'addons'          => $extras,
			'purpose'         => htmlentities( $params[ 'purpose' ] ?? '' ),
			'declaredValue'   => htmlentities( $params[ 'declaredValue' ] ?? '' ),
			// Oświadczenie o towarach niebezpiecznych - zawsze bool; czy wymagane, waliduje API.
			'containsDangerousGoods' => filter_var( $params[ 'containsDangerousGoods' ] ?? false, FILTER_VALIDATE_BOOLEAN ),
			'originId'        => apply_filters( 'globkurier_wc_order_origin_id', 'WOOCOMMERCE_API' ),
		], $this);
		
		if( empty( $parsed[ 'purpose' ] ) ){
			unset( $parsed[ 'purpose' ] );
		}

		// Odprawa celna: dołącz sekcję customs (zsanityzowaną per typ - wspólny sanitizer to atrapa).
		if( defined( 'UDIGroup_GLOBKURIER_CUSTOMS_ENABLED' ) && UDIGroup_GLOBKURIER_CUSTOMS_ENABLED
			&& ! empty( $params[ 'customs' ] ) && is_array( $params[ 'customs' ] ) ){
			$parsed[ 'customs' ] = $this->customs()->sanitizeCustoms( $params[ 'customs' ] );
		}

		return $parsed;
	}
	
	private function saveOrderToWc( $order, $wcOrderId, $parsedParams, $extras ){
		do_action( 'globkurier_before_save_order_to_wc', $order, $wcOrderId, $parsedParams );

		$wcOrder = $wcOrderId ? wc_get_order( (int)$wcOrderId ) : false;

		if( $wcOrderId && ! $wcOrder ){
			// Przesylka jest juz utworzona i oplacona - nie gubimy jej, zapisujemy na ghost poscie.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (przesylka juz oplacona / migracja), nie debug.
			error_log( '[globkurier] Nie znaleziono zamowienia WC #' . (int)$wcOrderId . ' przy zapisie przesylki ' . ( $order[ 'number' ] ?? '' ) . ' - zapis na ghost poscie.' );
		}

		if( ! $wcOrder ){
			$wcOrderId = $this->getGhostPostID();
		}

		$mataName = apply_filters( 'globkurier_wc_order_meta_name', 'globkurier_orders' );
		
		$orderDetails = [];
		
		$orderDetails[ 'date' ]   = time();
		$orderDetails[ 'number' ] = $order[ 'number' ];
		$orderDetails[ 'hash' ]   = $order[ 'hash' ];
		
		$orderDetails[ 'price' ] = [
			'net'   => $order[ 'price' ][ 'totalNetPrice' ],
			'gross' => $order[ 'price' ][ 'totalGrossPrice' ],
		];
		
		$orderDetails[ 'data' ] = $parsedParams;
		
		$orderDetails[ 'payment_name' ] = $extras[ 'payment_name' ] ?? '';
		
		$orderDetails[ 'cod' ] = [
			'value' => $extras[ 'cod_value' ] ?? '-',
		];
		
		$orderDetails[ 'carrier' ] = [
			'name' => $extras[ 'carrier_name' ] ?? '',
		];
		
		$metaValue = maybe_serialize( apply_filters( 'globkurier_order_meta_value', $orderDetails ) );
		
		$saved = false;

		if( $wcOrder ){
			// Przez API zamowien WC: przy HPOS trafia do wc_orders_meta, bez HPOS do wp_postmeta (jak dotad).
			try{
				$wcOrder->add_meta_data( $mataName, $metaValue, false );
				$wcOrder->save();
				$saved = true;
			} catch( \Throwable $e ){
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (przesylka juz oplacona / migracja), nie debug.
				error_log( '[globkurier] Zapis przesylki ' . ( $order[ 'number' ] ?? '' ) . ' w zamowieniu #' . (int)$wcOrderId . ' nieudany: ' . $e->getMessage() );
			}
		}

		if( ! $saved ){
			// Przesylki bez zamowienia (ghost post) i awaryjnie, gdy zapis zamowienia sie nie powiodl.
			add_post_meta( (int)$wcOrderId, $mataName, $metaValue, FALSE );
		}

		// Indeks listy historii (wlasna tabela); bledy indeksu sa logowane, nie przerywaja nadania.
		UDIGroup_ShipmentHistory::indexShipment( (int)$wcOrderId, $metaValue );

		do_action( 'globkurier_after_save_order_to_wc', $metaValue, $order, $wcOrderId, $parsedParams );
	}
	
	/** Czas zycia statusu przesylki w cache (automatyczne pobieranie w metaboxie zamowienia). */
	const STATUS_CACHE_TTL = 15 * 60;

	/**
	 * Status przesylki; $useCache - moze byc z cache (15 min). Kazde udane pobranie odswieza cache,
	 * wiec reczne "Aktualizuj" (bez cache) poprawia tez to, co zobacza inni przy otwarciu zamowienia.
	 */
	public function getCurrentStatusCached( $params, bool $useCache ){

		$cacheKey = 'globkurier_status_' . md5( (string) ( $params[ 'orderNumber' ] ?? '' ) );

		if( $useCache ){
			$cached = get_transient( $cacheKey );
			if( is_array( $cached ) ){
				return $cached;
			}
		}

		// Przy bledzie API getCurrentStatus() konczy zadanie (die) - do cache trafiaja tylko udane odpowiedzi.
		$status = $this->getCurrentStatus( $params );
		set_transient( $cacheKey, $status, self::STATUS_CACHE_TTL );

		return $status;
	}

	public function getCurrentStatus( $params ){
		
		$params = apply_filters( 'globkurier_get_order_status_params', $params );
		do_action( 'globkurier_before_get_order_status', $params );
		
		$function = 'order/tracking';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $params, $method );
		
		$status = [];
		
		if( $response[ 'code' ] > 204 ){
			$status[ 'status' ] = 'error';
			echo wp_json_encode( $status );
			die;
		} else{
			$status[ 'status' ] = 'success';
			$status[ 'data' ]   = $response[ 'data' ];
		}
		
		do_action( 'globkurier_after_get_order_status', $response );
		
		return $status;
	}
	
	public function getFirstPickupDay( $params ){
		
		$date = ! empty( $params[ 'date' ] ) ? $params[ 'date' ] : gmdate( 'Y-m-d' );
		
		$i = 0;
		
		do{
			$params[ 'date' ] = $date;
			$times            = $this->pickupTimeRanges( $params );
			if( empty( $times ) ){
				$date = gmdate( 'Y-m-d', strtotime( $date . ' + 1 days' ) );
			} else{
				return [ 'date' => $date, 'status' => 'ok' ];
			}
			$i ++;
		} while( 1 && $i < 11 );
		
		return [ 'date' => $date, 'status' => 'error' ];
	}
	
}
