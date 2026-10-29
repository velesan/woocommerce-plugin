<?php

namespace udigroup_globkurier;

class GlobKurierCustoms extends GlobKurier{

	const COUNTRIES_TRANSIENT = 'globkurier_customs_countries';
	const COUNTRIES_TTL       = 12 * HOUR_IN_SECONDS;

	const USER_ID_TRANSIENT = 'globkurier_customs_user_id';

	private $numericKeys = [
		'quantity',
		'unitValue',
		'unitWeightGross',
		'unitWeightNet',
		'subTotalValue',
		'weightGross',
		'weightNet',
		'postalCharges',
	];

	
	public function getConfig( $senderCountry, $receiverCountry, $carrierName ): array{

		if( $this->isCountryInEu( $receiverCountry ) ){
			return [ 'required' => FALSE ];
		}

		if( empty( $carrierName ) ){
			return [ 'required' => FALSE ];
		}

		$function = 'customs/config';
		$method   = 'GET';
		$params   = [
			'senderCountry'   => $senderCountry,
			'receiverCountry' => $receiverCountry,
			'name'            => $carrierName,
		];

		$token    = $this->api()->getToken();
		$response = $this->api()->getResponse( $function, $token, $params, $method );

		// Blad API (lub brak odpowiedzi) - nie wiemy, czy deklaracja jest wymagana; UI pokazuje blad z ponowieniem.
		if( (int) $response[ 'code' ] !== 200 ){
			return [ 'required' => FALSE, 'error' => TRUE ];
		}

		if( empty( $response[ 'data' ][ 'sections' ] ) ){
			return [ 'required' => FALSE ];
		}

		return apply_filters( 'globkurier_customs_config', [
			'required' => TRUE,
			'schema'   => $response[ 'data' ],
		], $senderCountry, $receiverCountry, $carrierName );
	}

	public function fetchHsCode( $receiverCountryId, $itemDescription ){

		if( $receiverCountryId === '' || $itemDescription === '' ){
			return NULL;
		}

		$function = 'order/customs/hsCode';
		$method   = 'GET';
		$params   = [
			'receiverCountryId' => $receiverCountryId,
			'itemDescription'   => $itemDescription,
		];

		$token    = $this->api()->getToken();
		$response = $this->api()->getResponse( $function, $token, $params, $method );

		if( $response[ 'code' ] != 200 || empty( $response[ 'data' ] ) ){
			return NULL;
		}

		$data = $response[ 'data' ];

		$first = ( is_array( $data ) && isset( $data[ 0 ] ) ) ? $data[ 0 ] : $data;

		if( empty( $first[ 'hsCode' ] ) ){
			return NULL;
		}

		$description = $first[ 'description' ] ?? '';
		if( is_array( $description ) ){
			$description = implode( ' ', array_filter( $description, 'is_scalar' ) );
		}

		return [
			'code'        => is_array( $first[ 'hsCode' ] ) ? ( $first[ 'hsCode' ][ 'code' ] ?? '' ) : (string) $first[ 'hsCode' ],
			'description' => (string) $description,
		];
	}

	public function translate( $text, $sourceLanguage = 'pl', $targetLanguage = 'en' ){

		$text = trim( (string) $text );

		if( $text === '' ){
			return NULL;
		}

		$token = $this->api()->getToken();

		$body = [
			'text'           => $text,
			'sourceLanguage' => $sourceLanguage,
			'targetLanguage' => $targetLanguage,
		];

		$userId = $this->getUserId( $token );

		if( $userId ){
			$body[ 'userId' ] = $userId;
		}

		$response = $this->api()->getResponse( 'translate', $token, $body, 'POST' );

		if( $response[ 'code' ] != 200 || empty( $response[ 'data' ] ) ){
			return NULL;
		}

		$data = $response[ 'data' ];

		if( is_string( $data ) ){
			return $data;
		}

		$translated = $data[ 'translatedText' ] ?? $data[ 'text' ] ?? $data[ 'translation' ] ?? NULL;

		return is_string( $translated ) && $translated !== '' ? $translated : NULL;
	}

	private function getUserId( $token ){

		$transient = $this->userIdTransient();
		$cached    = get_transient( $transient );

		if( $cached ){
			return (int) $cached;
		}

		$response = $this->api()->getResponse( 'user/profile', $token, [], 'GET' );

		if( $response[ 'code' ] != 200 || ! is_array( $response[ 'data' ] ) ){
			return NULL;
		}

		$userId = $response[ 'data' ][ 'id' ] ?? $response[ 'data' ][ 'userId' ] ?? NULL;

		if( ! $userId ){
			return NULL;
		}

		set_transient( $transient, (int) $userId, self::COUNTRIES_TTL );

		return (int) $userId;
	}

	private function userIdTransient(): string{

		return self::USER_ID_TRANSIENT . '_' . md5( (string) $this->settings( 'username', '' ) );
	}

	public function importOrderItems( $orderId ): array{

		$order = wc_get_order( $orderId );

		if( ! $order ){
			return [];
		}

		$commodities = [];

		foreach( $order->get_items() as $item ){
			$product = $item->get_product();

			$include = apply_filters( 'globkurier_customs_include_item', $product && ! $product->is_virtual(), $item, $product, $order );

			if( ! $include ){
				continue;
			}

			// Ilosc po zwrotach (get_qty_refunded_for_item zwraca liczbe ujemna).
			$quantity = $item->get_quantity() + $order->get_qty_refunded_for_item( $item->get_id() );

			if( $quantity <= 0 ){
				continue;
			}

			$commodity = [
				'description' => $item->get_name(),
				'quantity'    => $quantity,
			];

			if( $product && $product->get_weight() !== '' && $product->get_weight() !== NULL ){
				$weightKg = (float) wc_get_weight( $product->get_weight(), 'kg' );

				if( $weightKg > 0 ){
					$commodity[ 'unitWeightGross' ] = $weightKg;
				}
			}

			// Cena jednostkowa z zamówienia (netto, po rabatach) - wartość transakcyjna do odprawy.
			$unitValue = (float) $order->get_item_total( $item, FALSE );

			if( $unitValue > 0 ){
				$commodity[ 'unitValue' ] = round( $unitValue, 2 );
			}

			$commodity = apply_filters( 'globkurier_customs_commodity_data', $commodity, $item, $product, $order );

			$commodities[] = $commodity;
		}

		return $commodities;
	}

	public function sanitizeCustoms( $value, $key = NULL ){

		if( is_array( $value ) ){
			$clean = [];
			foreach( $value as $k => $v ){
				$cleanKey = is_int( $k ) ? $k : preg_replace( '/[^A-Za-z0-9_]/', '', $k );
				$clean[ $cleanKey ] = $this->sanitizeCustoms( $v, $k );
			}
			return $clean;
		}

		if( in_array( $key, $this->numericKeys, TRUE ) ){
			return is_numeric( $value ) ? 0 + $value : NULL;
		}

		if( $key === 'hsCode' ){
			return preg_replace( '/[^0-9.]/', '', (string) $value );
		}

		// Opis dwujezyczny "pl ;; en" - sanitize_text_field przycialby spacje separatora przy pustej czesci.
		$separator = ' ;; ';
		if( strpos( (string) $value, trim( $separator ) ) !== FALSE ){
			$parts = array_map( 'sanitize_text_field', explode( trim( $separator ), (string) $value ) );

			return implode( $separator, $parts );
		}

		return sanitize_text_field( (string) $value );
	}

	public function isCountryInEu( $countryId ): bool{

		foreach( $this->getCountries() as $country ){
			if( (string) ( $country[ 'id' ] ?? '' ) === (string) $countryId ){
				return ! empty( $country[ 'isUEMember' ] );
			}
		}

		// Nie znaleziono kraju -> nie zakładamy UE (pozwól dalszej logice/API zdecydować).
		return FALSE;
	}

	private function getCountries(): array{

		$cached = get_transient( self::COUNTRIES_TRANSIENT );

		if( is_array( $cached ) ){
			return $cached;
		}

		$response = $this->api()->getResponse( 'countries', NULL, [], 'GET' );

		if( $response[ 'code' ] != 200 || ! is_array( $response[ 'data' ] ) ){
			return [];
		}

		set_transient( self::COUNTRIES_TRANSIENT, $response[ 'data' ], self::COUNTRIES_TTL );

		return $response[ 'data' ];
	}
}
