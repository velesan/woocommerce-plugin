<?php

namespace udigroup_globkurier;

class GlobKurierExtraPickupPoints extends GlobKurier
{
	/** Znacznik swiezosci listy w opcji globkurier_extra_pickup_points (wartosc = klucz konta i jezyka). */
	const FRESH_TRANSIENT = 'globkurier_extra_pickup_points_fresh';

	/**
	 * Przewoznicy z odbiorem w punkcie (do ustawien). Lista zmienia sie rzadko - z API najwyzej co 12 h
	 * (wczesniej pelna lista produktow przy kazdym wyswietleniu ustawien); $fresh wymusza pobranie (zapis ustawien).
	 */
	public function getAvailablePoints(bool $fresh = false)
	{
		$cacheKey = md5($this->getLanguage().'|'.$this->settings('username', ''));
		$stored   = get_option('globkurier_extra_pickup_points');

		if (! $fresh && is_array($stored) && get_transient(self::FRESH_TRANSIENT) === $cacheKey) {
			return $stored;
		}

		$allProducts = $this->product()->get([
			'globkurier_show_all_providers' => true,
			'globkurier_is_pickup_active'   => true,
			'globkurier_pickup_type'        => 'POINT',
			'senderCountryId'               => '1',
			'receiverCountryId'             => '1',
			'length'                        => '1',
			'width'                         => '1',
			'height'                        => '1',
			'weight'                        => '1',
			'quantity'                      => '1',
		]);
		
		$excludedCarrierNames = [
			'Orlen Paczka',
			'inPost-Paczkomaty',
			'inPost-Kurier',
			'Raben',
			'AmbroExpress',
			'Hellmann',
		];
		
		// Brak polaczenia z API konczy sie pusta lista produktow - zostaje ostatnia znana lista (wczesniej
		// nadpisywana pusta, co gubilo przewoznikow w ustawieniach).
		if (empty($allProducts[ 'results' ])) {
			return is_array($stored) ? $stored : [];
		}

		$pointCollectionOnly = [];

		foreach ($allProducts[ 'results' ] as $product) {
			if (\in_array($product[ 'carrierName' ], $excludedCarrierNames)) {
				continue;
			}
			
			if (in_array('POINT', array_column($product['deliveryTypeOptions'], 'key'))) {
				if (!isset($pointCollectionOnly[$product['carrierName']])) {
					$pointCollectionOnly[$product['carrierName']] = $product;
				}
			}
		}
		
		update_option('globkurier_extra_pickup_points', $pointCollectionOnly);
		set_transient(self::FRESH_TRANSIENT, $cacheKey, 12 * HOUR_IN_SECONDS);

		return $pointCollectionOnly;
	}
	
	public function getPointsSelect2()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- celowo publiczna wyszukiwarka punktow w checkoucie (goscie, strony z cache, bez nonce): dane z zadania sa tylko filtrem i nie sa zapisywane; 1-2 zapytania do API GK na wyszukiwanie, bez cache (timeout 15 s, bez ponawiania logowania).
		$function = 'points';
		$method   = 'GET';
		
		$params = [];

		$productId   = absint($_GET[ 'productId' ] ?? 0);
		$carrierName = $this->findCarrierNameForProductId($productId);

		// Filtrujemy po carrierName, gdy uda sie go zmapowac z productId;
		// w przeciwnym razie fallback do dotychczasowego filtra po productId.
		if ($carrierName !== null) {
			$params[ 'carrierName' ] = $carrierName;
		} else {
			$params[ 'productId' ] = $productId;
		}

		$params[ 'isCashOnDeliveryAddonSelected' ] = 'false';

		$countryId = sanitize_text_field(wp_unslash($_GET[ 'countryId' ] ?? ''));
		// Bez kraju API szuka w Polsce; nieznany kod kraju (spoza mapy GK) to nie Polska.
		$isPoland = $countryId === '' || $countryId === '1' || strtoupper($countryId) === 'PL';

		if ($countryId !== '' && ! is_numeric($countryId)) {
			$countriesMap = $this->inpost()->getGlobKurierCountriesMap();
			$countryId    = $countriesMap[ strtoupper($countryId) ] ?? null;
		}

		if ($countryId) {
			$params[ 'countryId' ] = (int)$countryId;
		}


		// city[]=... z zewnatrz (endpoint nopriv): sanitize_text_field() zwraca '' dla tablicy (wczesniej TypeError w trim()).
		$city     = sanitize_text_field(wp_unslash($_GET['city'] ?? ''));
		$cityNoPl = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $city);
		
		$allPoints = [];

		// Z polskimi znakami i bez; gdy nazwa nie ma polskich znakow, drugie zapytanie byloby identyczne.
		$terms = array_unique([$city, (string) $cityNoPl]);

		foreach ($terms as $term) {
			$params['filter'] = implode(', ', explode(' ', $term));

			foreach ($this->searchPoints($function, $method, $params) as $point) {
				$allPoints[$point['id']] = $point;
			}
		}

		// Czesc przewoznikow (np. FedEX) API nie znajduje po carrierName, tylko po productId - jak w 2.5.4.
		// Tylko w Polsce: dla innych krajow wyszukiwanie po productId pomija countryId i zwraca punkty z Polski.
		if (empty($allPoints) && $carrierName !== null && $isPoland) {
			unset($params[ 'carrierName' ]);
			$params[ 'productId' ] = $productId;

			foreach ($terms as $term) {
				$params['filter'] = implode(', ', explode(' ', $term));

				foreach ($this->searchPoints($function, $method, $params) as $point) {
					$allPoints[$point['id']] = $point;
				}
			}
		}

		if(empty($allPoints)) {
			return json_encode([]);
		}
		
		$search = strtolower($city);
		$searchNoPl = strtolower($cityNoPl);
		
		$filteredPoints = $allPoints;
		$priorityPoints = [];
		
		foreach($allPoints as $key => $point) {
			if($point['latitude'] == 0 || $point['longitude'] == 0) {
				unset($filteredPoints[$key]);
				continue;
			}
			
			$found = false;
			foreach(explode(' ', $search) as $term) {
				if(mb_stripos($point['id'] ?? '', $term) !== false
				   || mb_stripos($point['city'] ?? '', $term) !== false
				   || mb_stripos($point['address'] ?? '', $term) !== false
				   || mb_stripos($point['value'] ?? '', $term) !== false) {
					$found = true;
					break;
				}
			}
			
			if(!$found) {
				foreach(explode(' ', $searchNoPl) as $term) {
					if(mb_stripos($point['id'] ?? '', $term) !== false
					   || mb_stripos($point['city'] ?? '', $term) !== false
					   || mb_stripos($point['address'] ?? '', $term) !== false
					   || mb_stripos($point['value'] ?? '', $term) !== false) {
						$found = true;
						break;
					}
				}
			}
			
			if(!$found) {
				unset($filteredPoints[$key]);
			}
		}
		
		foreach($filteredPoints as $key => $point) {
			if(mb_stripos($point['city'] ?? '', $search) !== false
			   || mb_stripos($point['city'] ?? '', $searchNoPl) !== false) {
				$priorityPoints[] = $point;
				unset($filteredPoints[$key]);
			}
		}
		
		array_multisort(
			array_column($filteredPoints, 'city'),
			SORT_ASC, $filteredPoints
		);
		
		return json_encode(array_values(array_merge($priorityPoints, $filteredPoints)));
		// phpcs:enable
	}
	
	/**
	 * Punkty z API dla frazy wyszukiwania. Bez cache: klucz zalezalby od frazy goscia, wiec warianty frazy tworzylyby
	 * w bazie dowolnie wiele duzych wpisow.
	 */
	private function searchPoints(string $function, string $method, array $params): array
	{
		$response = $this->api()->getResponse($function, null, $params, $method);

		return ($response[ 'code' ] == 200 && is_array($response[ 'data' ])) ? $response[ 'data' ] : [];
	}

	public function findProductIdForCarrierId(string $carrierId)
	{
		$extraPoints = get_option('globkurier_extra_pickup_points');
		
		// get_option zwraca false, gdy opcji jeszcze nie ma - ?? [] tego nie lapie.
		foreach ((is_array($extraPoints) ? $extraPoints : []) as $carrierName => $data) {
			if (sanitize_title($carrierName) == $carrierId) {
				return $data[ 'id' ];
			}
		}

		return null;
	}

	public function findCarrierNameForProductId(int $productId)
	{
		if (!$productId) {
			return null;
		}

		$extraPoints = get_option('globkurier_extra_pickup_points');

		// get_option zwraca false, gdy opcji jeszcze nie ma - ?? [] tego nie lapie.
		foreach ((is_array($extraPoints) ? $extraPoints : []) as $carrierName => $data) {
			if ((int)($data[ 'id' ] ?? 0) === $productId) {
				return $carrierName;
			}
		}

		return null;
	}

}
