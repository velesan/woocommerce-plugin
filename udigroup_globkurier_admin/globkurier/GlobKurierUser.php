<?php

namespace udigroup_globkurier;

class GlobKurierUser extends GlobKurier{
	
	public function profile(){
		
		$function = 'user/profile';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, [], $method );
		
		if( $response[ 'code' ] == 200 ){
			return $response[ 'data' ];
		} else{
			die( 'ERROR' );
		}
		
	}
	
	public function addressBook( $params ){
		
		switch( $params[ 'type' ] ){
			case 'senders':
				$function = 'user/addressBook/senders';
				break;
			case 'receivers':
				$function = 'user/addressBook/receivers';
				break;
			default:
				return [];
		}
		
		$method = 'GET';
		
		$token = $this->api()->getToken();
		
		$filters = [
			'filters' => [
				'phrase' => htmlentities( $params[ 'filter' ] ?? '' ),
			]
		];
		
		$response = $this->api()->getResponse( $function, $token, $filters, $method );
		
		if( $response[ 'code' ] == 200 ){
			return $this->parsePersons( $response[ 'data' ][ 'results' ], $params[ 'type' ] );
		} else{
			die( 'ERROR' );
		}
		
	}
	
	public function addToAddresBook( $params ){
		
		switch( $params[ 'type' ] ){
			case 'sender':
				$function = 'user/addressBook/sender';
				break;
			case 'receiver':
				$function = 'user/addressBook/receiver';
				break;
			default:
				return [];
		}
		
		$contact = [
			'name'            => $params[ 'name' ] ?? '',
			'email'           => $params[ 'email' ] ?? '',
			'street'          => $params[ 'street' ] ?? '',
			'houseNumber'     => $params[ 'home' ] ?? '',
			'apartmentNumber' => $params[ 'flat' ] ?? '',
			'postCode'        => $params[ 'postal' ] ?? '',
			'city'            => $params[ 'city' ] ?? '',
			'countryId'       => $params[ 'country' ] ?? '',
			'contactPerson'   => $params[ 'contactName' ] ?? '',
			'phone'           => preg_replace( '/[^0-9]/', '', $params[ 'phone' ] ),
		];
		
		$method = 'POST';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, [ 'contact' => $contact ], $method );
		
		if( $response[ 'code' ] == 200 || $response[ 'code' ] == 201 ){
			return $response[ 'data' ][ 'id' ];
		}

		// API tworzy kontakt z kodem 204 bez tresci (wczesniej traktowane jak blad - kontakt powstawal, a panel
		// pokazywal "Uzupelnij prawidlowo pola"). ID odczytujemy z ksiazki adresowej.
		if( $response[ 'code' ] == 204 && ( $id = $this->findContactId( $params[ 'type' ], $contact, $token ) ) ){
			return $id;
		}

		die( 'ERROR' );
		
	}
	
	public function updateToAddresBook( $params ){
		
		switch( $params[ 'type' ] ){
			case 'sender':
				$function = 'user/addressBook/sender';
				break;
			case 'receiver':
				$function = 'user/addressBook/receiver';
				break;
			default:
				return [];
		}
		
		$contact = [
			'id'              => $params[ 'id' ] ?? '',
			'name'            => $params[ 'name' ] ?? '',
			'email'           => $params[ 'email' ] ?? '',
			'street'          => $params[ 'street' ] ?? '',
			'houseNumber'     => $params[ 'home' ] ?? '',
			'apartmentNumber' => $params[ 'flat' ] ?? '',
			'postCode'        => $params[ 'postal' ] ?? '',
			'city'            => $params[ 'city' ] ?? '',
			'countryId'       => $params[ 'country' ] ?? '',
			'contactPerson'   => $params[ 'contactName' ] ?? '',
			'phone'           => preg_replace( '/[^0-9]/', '', $params[ 'phone' ] ),
		];
		
		$method = 'PUT';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, [ 'contact' => $contact ], $method );
		
		if( $response[ 'code' ] == 200 || $response[ 'code' ] == 201 || $response[ 'code' ] == 204 ){
			// Przy 204 API nie zwraca tresci - ID jest to, ktore wyslalismy.
			return $response[ 'data' ][ 'contact' ][ 'id' ] ?? $contact[ 'id' ];
		} else{
			die( 'ERROR' );
		}
		
	}
	
	private function parsePersons( $response, $type ){
		$persons = [];
		
		foreach( $response as $data ){
			$persons[] = [
				'id'              => $data[ 'id' ] ?? '',
				'name'            => $data[ 'name' ] ?? '',
				'email'           => $data[ 'email' ] ?? '',
				'street'          => $data[ 'street' ] ?? '',
				'houseNumber'     => $data[ 'houseNumber' ] ?? '',
				'apartmentNumber' => $data[ 'apartmentNumber' ] ?? '',
				'postCode'        => $data[ 'postCode' ] ?? '',
				'city'            => $data[ 'city' ] ?? '',
				'countryId'       => $data[ 'countryId' ] ?? 1,
				'phone'           => $data[ 'phone' ] ?? '',
				'contactPerson'   => $data[ 'contactPerson' ] ?? '',
				'tin'             => $data[ 'tin' ] ?? '',
				'type'            => $type,
			];
		}
		
		return $persons;
	}
	
	/** Metody platnosci konta (pole w ustawieniach): ['key' => konto i jezyk, 'methods' => [id => nazwa]]. */
	const PAYMENT_METHODS_TRANSIENT = 'globkurier_payment_methods';

	/** Ostatnia udana odpowiedz (bez autoload) - na wypadek awarii API. */
	const PAYMENT_METHODS_LAST = 'globkurier_payment_methods_last';

	/**
	 * Metody platnosci do pola w ustawieniach. Z API najwyzej co 12 h (zmieniaja sie rzadko); zapis ustawien
	 * czysci cache. Przy awarii API: ostatnia znana lista, a gdy jej brak - zapisana metoda. Wczesniej die('ERROR')
	 * konczyl cala strone ustawien, a pusta lista skasowalaby zapisana metode przy zapisie formularza.
	 */
	public function paymentMethods(){

		$cacheKey = md5( $this->getLanguage() . '|' . $this->settings( 'username', '' ) );

		$cached = get_transient( self::PAYMENT_METHODS_TRANSIENT );
		if( is_array( $cached ) && ( $cached[ 'key' ] ?? '' ) === $cacheKey ){
			return $cached[ 'methods' ];
		}

		$function = 'user/payment/methods';
		$method   = 'GET';

		$token = $this->api()->getToken();

		$response = $this->api()->getResponse( $function, $token, [], $method );

		if( $response[ 'code' ] == 200 && is_array( $response[ 'data' ] ) ){
			$methods = [];

			foreach( $response[ 'data' ] as $method ){
				if( $method[ 'enabled' ] == 1 ){
					$methods[ $method[ 'id' ] ] = $method[ 'name' ];
				}
			}

			$entry = [ 'key' => $cacheKey, 'methods' => $methods ];
			set_transient( self::PAYMENT_METHODS_TRANSIENT, $entry, 12 * HOUR_IN_SECONDS );
			update_option( self::PAYMENT_METHODS_LAST, $entry, false );

			return $methods;
		}

		$last = get_option( self::PAYMENT_METHODS_LAST );
		if( is_array( $last ) && ( $last[ 'key' ] ?? '' ) === $cacheKey ){
			return $last[ 'methods' ];
		}

		$saved = (string) $this->settings( 'payment', '' );

		return $saved !== '' ? [ $saved => $saved ] : [];
	}

	public static function forgetPaymentMethodsCache(): void{
		delete_transient( self::PAYMENT_METHODS_TRANSIENT );
	}

	/**
	 * ID kontaktu dodanego przed chwila (API po utworzeniu odpowiada 204 bez tresci): szukamy po e-mailu,
	 * a gdy go nie ma - po nazwie; dopasowanie po nazwie, e-mailu i kodzie pocztowym, najnowszy wpis.
	 */
	private function findContactId( string $type, array $contact, $token ): ?int{
		
		$function = $type === 'sender' ? 'user/addressBook/senders' : 'user/addressBook/receivers';
		$phrase   = $contact[ 'email' ] !== '' ? $contact[ 'email' ] : $contact[ 'name' ];
		
		$response = $this->api()->getResponse( $function, $token, [ 'filters' => [ 'phrase' => $phrase ] ], 'GET' );
		
		$ids = [];
		foreach( $response[ 'data' ][ 'results' ] ?? [] as $found ){
			if( strcasecmp( (string) ( $found[ 'email' ] ?? '' ), (string) $contact[ 'email' ] ) === 0
				&& (string) ( $found[ 'name' ] ?? '' ) === (string) $contact[ 'name' ]
				&& (string) ( $found[ 'postCode' ] ?? '' ) === (string) $contact[ 'postCode' ] ){
				$ids[] = (int) $found[ 'id' ];
			}
		}
		
		return $ids ? max( $ids ) : null;
	}

}
