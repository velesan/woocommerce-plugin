<?php

namespace udigroup_globkurier;

class GlobKurierInpost extends GlobKurier
{
	/** Transient: trwa pelne pobieranie punktow InPost (jedno naraz). */
	const UPDATE_LOCK = 'globkurier_inpost_points_updating';

	/** Zdarzenie WP-Cron: odswiezenie listy punktow InPost w tle. */
	const REFRESH_HOOK = 'globkurier_refresh_inpost_points';

	/** Ile sekund pamietac, ze API nie ma punktow dla danego kraju (bez tego kazde zadanie to nowe zapytania). */
	const EMPTY_COUNTRY_TTL = 600;

	protected $productId = 0;
	protected $label = 'PACZKOMAT';
	
	private function _convert($content)
	{
		if (! mb_check_encoding($content, 'UTF-8')
			|| ! ($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))) {
			$content = mb_convert_encoding($content, 'UTF-8');
		}
		
		return $content;
	}
	
	public function getPoints($paramsIn)
	{
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_get_inpost_points_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		$function = 'points';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		$this->getProductId();
		
		$params[ 'id' ]        = strtoupper($this->_convert(sanitize_text_field(wp_unslash($_POST[ 'city' ] ?? '')) ?? ''));
		$params[ 'productId' ] = $this->productId;
		
		$response = $this->api()->getResponse($function, $token, $params, $method);
		
		if ($response[ 'code' ] != 200 || empty($response[ 'data' ])) {
			$params = [];
			
			$params[ 'productId' ]                     = $this->productId;
			$params[ 'isCashOnDeliveryAddonSelected' ] = 'false';
			$params[ 'city' ]                          = $this->_convert(sanitize_text_field(wp_unslash($_POST[ 'city' ] ?? '')) ?? '');
			
			$response = $this->api()->getResponse($function, $token, $params, $method);
		}
		
		$points = apply_filters('globkurier_inpost_points', $response[ 'data' ]);
		
		$pointsResponse = [];
		
		if ($response[ 'code' ] == 200 && count($response[ 'data' ]) > 0) {
			foreach ($points as $point) {
				$pointsResponse[] = [
					'id'           => $point[ 'id' ],
					'label'        => $point[ 'city' ].', '.$point[ 'address' ].' ['.$point[ 'id' ].']',
					'value'        => $point[ 'city' ].', '.$point[ 'address' ].' ['.$point[ 'id' ].']',
					'latitude'     => $point[ 'latitude' ],
					'longitude'    => $point[ 'longitude' ],
					'city'         => $point [ 'city' ],
					'address'      => $point [ 'address' ],
					'openingHours' => $point [ 'openingHours' ],
				];
			}
		} else {
			$pointsResponse[] = [
				'id'           => ' ',
				'value'        => ' ',
				'label'        => __('Brak wyników', 'globkurier'),
				'latitude'     => 0,
				'longitude'    => 0,
				'city'         => ' ',
				'address'      => ' ',
				'openingHours' => ' ',
			];
		}
		
		return json_encode($pointsResponse);
		
		do_action('globkurier_after_get_inpost_points', $response, $points, $pointsResponse);
		
		return $points;
	}
	
	public function getAllPoints()
	{
		return $this->getAllPointsFromInpost();
	}
	
	public function getPointsSelect2($paramsIn)
	{
		$function = 'points';
		$method   = 'GET';
		
		$countryId = $paramsIn['countryId'] ?? 'PL';
		
		if($countryId == 'PL' || $countryId == 1){
			$allPoints = $this->getAllPointsFromInpost();
		} else {
			$allPoints = $this->getAllInpostForCountry($countryId);
		}
		
		$search = strtolower(trim($paramsIn[ 'city' ] ?? ''));
		
		$filteredPoints = $allPoints;
		$priorityPoints = [];
		// Maks. 5 slow - kazde slowo to przejscie po calej liscie punktow (publiczna wyszukiwarka).
		foreach (array_slice(explode(' ', $search), 0, 5) as $term) {
			foreach ($allPoints as $key => $point) {
				if (
					mb_stripos($point[ 'id' ] ?? '', $term) === false
					&& mb_stripos($point[ 'city' ] ?? '', $term) === false
					&& mb_stripos($point[ 'address' ] ?? '', $term) === false
					&& mb_stripos($point[ 'value' ] ?? '', $term) === false
				) {
					unset($filteredPoints[ $key ]);
				}
				
				if(!isset($point['latitude']) || !isset($point['longitude']) || $point['latitude'] == 0 || $point['longitude'] == 0){
					unset($filteredPoints[ $key ]);
				}
			}
		}
		
		foreach ($filteredPoints as $key => $point) {
			if (mb_stripos($point[ 'city' ] ?? '', $search) !== false) {
				$priorityPoints[] = $point;
				unset($filteredPoints[ $key ]);
			}
		}
		
		array_multisort(
			array_column($filteredPoints, 'city'),
			SORT_ASC, $filteredPoints
		);
		
		$points = $priorityPoints + $filteredPoints;;
		
		$pointsResponse = apply_filters('globkurier_inpost_points', $points);
		
		do_action('globkurier_after_get_inpost_points', $points, $pointsResponse);
		
		return json_encode($pointsResponse);
	}
	
	/**
	 * @deprecated 2.7 Uzyj getAllPointsFromInpost(). Pobierala liste z API GK i zapisywala cache bez blokady
	 * pobierania; nieuzywana we wtyczce, zostaje dla kodu zewnetrznego (ten sam format punktow).
	 */
	public function getAllPointsGK($update = false)
	{
		_deprecated_function(__METHOD__, '2.7', __CLASS__.'::getAllPointsFromInpost()');
		
		return $this->getAllPointsFromInpost($update);
	}
	
	public function getAllPointsFromInpost($update = false, bool $ignoreLock = false)
	{
		$cacheTTL = get_option('globkurier')[ 'inpost' ][ 'cache_ttl' ] ?? 3;
		
		$inpostPointsValidTime = apply_filters('globkurier_inpost_points_valid_time',
			get_option('gkStoreInpostPointsValidTime', $cacheTTL * 24 * 60 * 60)
		);
		
		$inpostPointsValidTimeChecker = apply_filters('globkurier_inpost_points_valid_time_checker_is_active',
			get_option('globkurier')[ 'inpost_points_valid_time_checker_is_active' ] ?? false
		);
		
		if ($cacheTTL != -1 && $inpostPointsValidTimeChecker && $inpostPointsValidTime < $this->getTimeSincePointsDownloaded()) {
			$update = true;
		}
		
		$storeInpostPointsInFile = apply_filters('globkurier_store_inpost_points_in_file',
			get_option('globkurier')[ 'storeInpostPointsInFile' ] ?? false
		);
		
		$oldPoints = null;
		if (! $update) {
			if ($storeInpostPointsInFile) {
				$oldPoints = $this->loadPointsFromFile();
			} else {
				$oldPoints = get_option('udigroup_gkInPostPoints', null);
			}
		}
		
		if (! $update && $oldPoints !== null) {
			return $oldPoints;
		}

		$stalePoints = function () use ($storeInpostPointsInFile){
			return ($storeInpostPointsInFile ? $this->loadPointsFromFile(false, true) : get_option('udigroup_gkInPostPoints', null)) ?? [];
		};

		// Odswiezenie istniejacej listy idzie do WP-Cron: zadanie goscia (publiczna wyszukiwarka) nie czeka na pelne
		// pobieranie i nie trafia na limit czasu/pamieci hostingu - dostaje dotychczasowy cache. Synchronicznie tylko:
		// reczne odswiezanie ($ignoreLock), samo zdarzenie crona, brak jakichkolwiek danych albo niedzialajacy WP-Cron.
		if (! $ignoreLock && ! wp_doing_cron()) {
			$cached = $stalePoints();
			if ($cached && UDIGroup_Helper::schedulePointsRefresh(self::REFRESH_HOOK)) {
				return $cached;
			}
		}

		// Pelne pobieranie juz trwa albo niedawno sie nie udalo (inne zadanie, np. publiczna wyszukiwarka przy
		// wygaslym cache) - nie pobieramy rownolegle ani w kolko, zwracamy to, co jest w cache. Reczne odswiezanie
		// z panelu ($ignoreLock) dziala zawsze, takze gdy blokada zostala po przerwanym zadaniu.
		if (! $ignoreLock && get_transient(self::UPDATE_LOCK)) {
			return $stalePoints();
		}
		set_transient(self::UPDATE_LOCK, 1, 10 * MINUTE_IN_SECONDS);
		
		$pointsResponse = null;
		try{
			$pointsResponse = $this->downloadAllPointsFromInpost();
		} finally{
			if ($pointsResponse) {
				delete_transient(self::UPDATE_LOCK);
			} else {
				// Po nieudanym pobraniu 5 minut przerwy dla prob automatycznych.
				set_transient(self::UPDATE_LOCK, 1, 5 * MINUTE_IN_SECONDS);
			}
		}
		
		if (! $pointsResponse) {
			return $stalePoints();
		}
		
		if ($storeInpostPointsInFile) {
			$filePath = apply_filters('globkurier_store_inpost_points_file_path', UDIGroup_Helper::pointsCacheFile('inpost.json'));
			
			try{
				$inpostFileJsonContent = apply_filters('globkurier_inpost_file_json_content',
					json_encode($pointsResponse, JSON_THROW_ON_ERROR)
				);
				
				if (! UDIGroup_Helper::writePointsCacheFile($filePath, $inpostFileJsonContent)) {
					// Plik sie nie zapisal - nie kasujemy danych w bazie ani nie oznaczamy pobrania; wynik tylko dla tego zadania.
					return $pointsResponse;
				}
				delete_option('udigroup_gkInPostPoints');
			} catch (\JsonException $e){
				return;
			}
		} else {
			update_option('udigroup_gkInPostPoints', $pointsResponse, false);
		}
		
		update_option('udigroup_gkInPostPointsDownloadedAt', time());
		
		return $pointsResponse;
	}
	
	public function getAllInpostForCountry($countryId)
	{
		
		$countriesMap = $this->getGlobKurierCountriesMap() ?: [];

		if(! is_numeric($countryId)){
			$countryId = $countriesMap[ strtoupper((string) $countryId) ] ?? null;
		}

		// Liczba calkowita w kluczu transientu: "01", "1.0" itp. z publicznej wyszukiwarki nie tworza osobnych wpisow.
		$countryId = (int) $countryId;

		// Tylko kraje znane API GK: dowolna wartosc z publicznej wyszukiwarki nie wywoluje zapytan do API ani nie tworzy
		// wpisow w cache, a nieznany kraj nie dostaje juz punktow z Polski. Bez mapy krajow (API niedostepne) - pusto.
		if (! $countryId || ! in_array($countryId, array_map('intval', $countriesMap), true)) {
			return [];
		}

		$cacheKey     = 'globkurier_inpost_international_points_'.$countryId;
		$cachedPoints = get_transient($cacheKey);

		// Pusta tablica w transiencie = API nie ma punktow dla tego kraju (pamietane krotko).
		if($cachedPoints !== false){
			return $cachedPoints;
		}
		
		$function = 'points';
		$method   = 'GET';
		
		$this->getProductId($countryId);
		
		$params = [];
		
		$params[ 'productId' ]                     = $this->productId;
		$params[ 'isCashOnDeliveryAddonSelected' ] = 'false';
		
		if($countryId){
			$params[ 'countryId' ] =  $countryId;
		}
		
		$response = $this->api()->getResponse($function, null, $params, $method);
		
		$points = apply_filters('globkurier_inpost_points', $response[ 'data' ]);
		
		$pointsResponse = [];
		
		if ($response[ 'code' ] == 200 && count($response[ 'data' ]) > 0) {
			foreach ($points as $point) {
				$pointsResponse[] = [
					'id'           => $point[ 'id' ],
					'label'        => $point[ 'city' ].', '.$point[ 'address' ].' ['.$point[ 'id' ].']',
					'value'        => $point[ 'city' ].', '.$point[ 'address' ].' ['.$point[ 'id' ].']',
					'latitude'     => $point[ 'latitude' ],
					'longitude'    => $point[ 'longitude' ],
					'city'         => $point[ 'city' ],
					'address'      => $point[ 'address' ],
					'openingHours' => $point[ 'openingHours' ],
				];
			}
		}

		if (count($pointsResponse) == 0) {
			// Bez punktow tez zapamietujemy (krocej przy bledzie API), inaczej kazde zadanie to nowe zapytania do API.
			set_transient($cacheKey, [], $response[ 'code' ] == 200 ? self::EMPTY_COUNTRY_TTL : MINUTE_IN_SECONDS);

			return [];
		}

		set_transient($cacheKey, $pointsResponse, 24 * HOUR_IN_SECONDS);

		return $pointsResponse;
	}
	
	public function getGlobKurierCountriesMap()
	{
		$currentCountries = get_transient('globkurier_countries_map');
		
		// Pusta tablica w transiencie = API nie zwrocilo krajow (pamietane krotko, bez zapytania przy kazdym zadaniu).
		if (is_array($currentCountries)) {
			return $currentCountries;
		}
		
		$countries = array_reduce(
			$this->api()->getResponse('countries', null, [], 'GET')['data'] ?? [],
			function ($result, $country) {
				$result[$country['isoCode']] = $country['id'];
				return $result;
			}
		) ?: [];
		
		set_transient('globkurier_countries_map', $countries, $countries ? 24 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
		
		return $countries;
	}
	
	public function loadPointsFromFile($raw = false, $updated = false)
	{
		$filePath = apply_filters('globkurier_store_inpost_points_file_path', UDIGroup_Helper::pointsCacheFile('inpost.json', true));
		
		if (! file_exists($filePath)) {
			if ($updated) {
				return null;
			} else {
				// Brak pliku (np. pierwsze uzycie) - pobranie z blokada, zeby rownolegle zadania gosci nie pobieraly naraz.
				$this->getAllPointsFromInpost(true);
				
				return $this->loadPointsFromFile($raw, true);
			}
		}
		
		$inpostFileJsonContent = apply_filters(
			'globkurier_store_inpost_points_json_content',
			file_get_contents($filePath)
		);
		
		return $raw
			? $inpostFileJsonContent
			: json_decode($inpostFileJsonContent, true, 512, JSON_THROW_ON_ERROR);
	}
	
	public function getTimeSincePointsDownloaded()
	{
		$downloadedAt = get_option('udigroup_gkInPostPointsDownloadedAt', 0);
		
		return absint(time() - $downloadedAt);
	}
	
	/** Reczne odswiezenie listy punktow (ustawienia, panel) - omija blokade pobierania. */
	public function update()
	{
		$this->getAllPointsFromInpost(true, true);
	}
	
	public function getProductId($receiverCountryId = null)
	{
		global $globKurier;
		
		$products = $globKurier->product()->get([
			'height'                        => '1',
			'width'                         => '1',
			'length'                        => '1',
			'weight'                        => '1',
			'quantity'                      => '1',
			'receiverCountryId'             => $receiverCountryId ?? '1',
			'receiverPostCode'              => '87-100',
			'senderCountryId'               => '1',
			'senderPostCode'                => '87-100',
			'globkurier_show_all_providers' => 'true',
			'globkurier_is_pickup_active'   => 'true',
			'globkurier_pickup_type'        => '',
		], false);
		
		foreach ($products[ 'results' ] as $product) {
			if ($product[ 'carrierName' ] === 'inPost-Paczkomaty') {
				$this->productId = $product[ 'id' ];
			}
		}
	}
	
	/**
	 * Wszystkie strony punktow z API InPost. null, gdy ktorakolwiek strona sie nie pobrala - ucieta lista
	 * nie moze zastapic pelnej w cache.
	 */
	private function downloadAllPointsFromInpost(): ?array
	{
		$baseUrl = 'https://api-pl-points.easypack24.net/v1/points';
		$perPage = (int) (get_option('globkurier')[ 'inpost' ][ 'api_per_page' ] ?? 7000);
		$fields  = 'name,address_details,address,location,opening_hours';

		$points = [];
		// Limit stron na wypadek, gdyby API ignorowalo parametr page (ok. 34 tys. punktow = 5 stron po 7000).
		for ($page = 1; $page <= 100; $page++) {
			$data = $this->fetchDataFromApi("{$baseUrl}?per_page={$perPage}&page={$page}&fields={$fields}");

			if ($data === null) {
				return null;
			}

			if (empty($data[ 'items' ])) {
				break;
			}

			$points = array_merge($points, $this->parseInpostData($data[ 'items' ]));
		}

		return $points;
	}

	/**
	 * Jedna strona z API InPost jako tablica; null przy bledzie polaczenia, kodzie innym niz 200 albo zlym JSON.
	 */
	private function fetchDataFromApi($url): ?array
	{
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- pelna lista punktow InPost (ok. 34 tys.) w pamieci przy odswiezaniu cache: reczne z ustawien albo w tle z WP-Cron (z zadania goscia tylko, gdy nie ma zadnych danych albo WP-Cron nie dziala). Tanie hostingi maja bardzo niski memory_limit, a WP_MAX_MEMORY_LIMIT (256 MB) przez wp_raise_memory_limit() moglby nie wystarczyc tam, gdzie dzis dziala. Rownolegle pobieranie i ponawianie po bledzie blokuje UPDATE_LOCK.
		\ini_set('memory_limit', -1);

		$response = wp_remote_get($url, ['timeout' => (int) apply_filters('globkurier_inpost_points_timeout', 60)]);

		if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		return is_array($data) ? $data : null;
	}
	
	private function parseInpostData($data): array
	{
		$parsed = [];
		
		foreach ($data as $point) {
			$name     = $point[ 'name' ] ?? '';
			$city     = $point[ 'address_details' ][ 'city' ] ?? '';
			$address1 = $point[ 'address' ][ 'line1' ] ?? '';
			
			$parsed[] = [
				'id'           => $name,
				'label'        => $city.' '.$address1.' ['.$name.']',
				'value'        => $city.' '.$address1.' ['.$name.']',
				'latitude'     => $point[ 'location' ][ 'latitude' ] ?? '',
				'longitude'    => $point[ 'location' ][ 'longitude' ] ?? '',
				'city'         => $city,
				'address'      => $address1,
				'openingHours' => $point[ 'opening_hours' ] ?? '',
			];
		}
		
		return $parsed;
	}
	
}