<?php

namespace udigroup_globkurier;

class GlobKurierRuch extends GlobKurier
{
	/** Transient: trwa pelne pobieranie punktow ORLEN (jedno naraz). */
	const UPDATE_LOCK = 'globkurier_ruch_points_updating';

	/** Zdarzenie WP-Cron: odswiezenie listy punktow ORLEN w tle. */
	const REFRESH_HOOK = 'globkurier_refresh_ruch_points';

	protected $productId = 0;
	protected $label = 'PACZKA_W_RUCHU';

	private function _convert($content)
	{
		if (! mb_check_encoding($content, 'UTF-8')
			|| ! ($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))) {
			$content = mb_convert_encoding($content, 'UTF-8');
		}
		
		return $content;
	}
	
	public function getPoints($params)
	{
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'globkurier_get_ruch_points_nonce')) {
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
		
		$points = apply_filters('globkurier_ruch_points', $response[ 'data' ]);
		
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
		
		do_action('globkurier_after_get_ruch_points', $response, $points, $pointsResponse);
		
		return json_encode($pointsResponse);
	}
	
	public function getPointsSelect2($paramsIn)
	{
		$productId = absint($paramsIn[ 'product_id' ] ?? 0);

		if ($productId) {
			$filters = array_map(
				'trim',
				explode(' ', preg_replace('/[^[a-zA-Zą-źĄ-ŹŁł0-9 ]/', '', $paramsIn[ 'city' ] ?? ''))
			);
			$points  = $this->getPointsForProductId($productId, $filters);
			
			$pointsResponse = apply_filters('globkurier_ruch_product_points', $points, $productId, $filters, $paramsIn);
		} else {
			$allPoints = $this->getAllPoints();
			$search    = strtolower(trim($paramsIn[ 'city' ] ?? ''));
			
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
					
					if($point['latitude'] == 0 || $point['longitude'] == 0){
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
			
			$pointsResponse = apply_filters('globkurier_ruch_points', $points);
		}
		
		do_action('globkurier_after_get_ruch_points', $points, $pointsResponse);
		
		return json_encode($pointsResponse);
	}
	
	public function getAllPoints($update = false, bool $ignoreLock = false)
	{
		// Czas waznosci w sekundach (wczesniej omylkowo znacznik czasu ostatniego pobrania - odswiezanie nigdy nie ruszalo).
		$ruchPointsValidTime = apply_filters('globkurier_ruch_points_valid_time',
			get_option('gkStoreRuchPointsValidTime', DAY_IN_SECONDS)
		);
		
		$ruchPointsValidTimeChecker = apply_filters('globkurier_ruch_points_valid_time_checker_is_active',
			get_option('globkurier')[ 'ruch_points_valid_time_checker_is_active' ] ?? false
		);
		
		if ($ruchPointsValidTimeChecker && $ruchPointsValidTime < $this->getTimeSincePointsDownloaded()) {
			$update = true;
		}
		
		$storeRuchPointsInFile = apply_filters('globkurier_store_ruch_points_in_file',
			get_option('globkurier')[ 'storeRuchPointsInFile' ] ?? false
		);
		
		$oldPoints = null;
		if (! $update) {
			if ($storeRuchPointsInFile) {
				$oldPoints = $this->loadPointsFromFile();
			} else {
				$oldPoints = get_option('udigroup_gkRuchPoints', null);
			}
		}
		
		if (! $update && $oldPoints !== null) {
			return $oldPoints;
		}

		$stalePoints = function () use ($storeRuchPointsInFile){
			return ($storeRuchPointsInFile ? $this->loadPointsFromFile(false, true) : get_option('udigroup_gkRuchPoints', null)) ?? [];
		};

		// Odswiezenie istniejacej listy w tle (WP-Cron), jak w GlobKurierInpost::getAllPointsFromInpost().
		if (! $ignoreLock && ! wp_doing_cron()) {
			$cached = $stalePoints();
			if ($cached && UDIGroup_Helper::schedulePointsRefresh(self::REFRESH_HOOK)) {
				return $cached;
			}
		}

		// Pobieranie juz trwa albo niedawno sie nie udalo - zwracamy to, co jest w cache, zamiast pobierac rownolegle
		// lub w kolko. Reczne odswiezanie z panelu ($ignoreLock) dziala zawsze.
		if (! $ignoreLock && get_transient(self::UPDATE_LOCK)) {
			return $stalePoints();
		}
		set_transient(self::UPDATE_LOCK, 1, 5 * MINUTE_IN_SECONDS);

		$function = 'points';
		$method   = 'GET';

		$params = [];

		$params[ 'carrierName' ]                   = 'Orlen Paczka';
		$params[ 'isCashOnDeliveryAddonSelected' ] = 'false';

		$response = $this->api()->getResponse($function, null, $params, $method);
		
		$points = apply_filters('globkurier_ruch_points', $response[ 'data' ]);
		
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
		}
		
		if (count($pointsResponse) == 0) {
			// Blokada zostaje na 5 minut (przerwa dla prob automatycznych); panel dostaje blad jak dotad,
			// wyszukiwarka - dotychczasowy cache.
			if ($ignoreLock) {
				wp_send_json_error('Brak Punktów Orlen do zapisu');
			}

			return $stalePoints();
		}

		delete_transient(self::UPDATE_LOCK);
		
		if ($storeRuchPointsInFile) {
			$filePath = apply_filters('globkurier_store_ruch_points_file_path', UDIGroup_Helper::pointsCacheFile('orlen.json'));
			
			try{
				$ruchFileJsonContent = apply_filters('globkurier_ruch_file_json_content',
					json_encode($pointsResponse, JSON_THROW_ON_ERROR)
				);
				if (! UDIGroup_Helper::writePointsCacheFile($filePath, $ruchFileJsonContent)) {
					// Plik sie nie zapisal - nie kasujemy danych w bazie ani nie oznaczamy pobrania; wynik tylko dla tego zadania.
					return $pointsResponse;
				}
				delete_option('udigroup_gkRuchPoints');
			} catch (\JsonException $e){
				wp_send_json_error('Błąd podczas zapisu do pliku');
			}
		} else {
			update_option('udigroup_gkRuchPoints', $pointsResponse, false);
		}
		
		update_option('udigroup_gkRuchPointsDownloadedAt', time());
		
		return $pointsResponse;
	}
	
	public function getPointsForProductId($productId, $filters = [])
	{
		$function = 'points';
		$method   = 'GET';
		
		$params = [];
		
		$params[ 'productId' ]                     = $productId;
		$params[ 'filter' ]                        = implode(' ', $filters);
		$params[ 'isCashOnDeliveryAddonSelected' ] = 'false';
		
		$response = $this->api()->getResponse($function, null, $params, $method);
		
		$points = apply_filters('globkurier_ruch_points', $response[ 'data' ]);
		
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
		}
		
		// Tylko wynik wyszukiwania - bez zapisu do wspolnego cache punktow ORLEN (wczesniej kazde wyszukanie,
		// takze anonimowe z dowolnym product_id, podmienialo liste punktow w checkoucie).
		return $pointsResponse;
	}
	
	public function loadPointsFromFile($raw = false, $updated = false)
	{
		$filePath = apply_filters('globkurier_store_ruch_points_file_path', UDIGroup_Helper::pointsCacheFile('orlen.json', true));
		
		if (! file_exists($filePath)) {
			if ($updated) {
				return null;
			} else {
				// Brak pliku - pobranie z blokada (rownolegle zadania gosci nie pobieraja naraz).
				$this->getAllPoints(true);
				
				return $this->loadPointsFromFile($raw, true);
			}
		}
		
		$ruchFileJsonContent = apply_filters(
			'globkurier_store_ruch_points_json_content',
			file_get_contents($filePath)
		);
		
		return $raw
			? $ruchFileJsonContent
			: json_decode(mb_convert_encoding($ruchFileJsonContent, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
	}
	
	public function getTimeSincePointsDownloaded()
	{
		$downloadedAt = get_option('udigroup_gkRuchPointsDownloadedAt', 0);
		
		return absint(time() - $downloadedAt);
	}
	
	/** Reczne odswiezenie listy punktow (ustawienia, panel) - omija blokade pobierania. */
	public function update()
	{
		$this->getAllPoints(true, true);
	}
	
	public function getProductId()
	{
		global $globKurier;
		
		$products = $globKurier->product()->get([
			'height'                        => '1',
			'width'                         => '1',
			'length'                        => '1',
			'weight'                        => '1',
			'quantity'                      => '1',
			'receiverCountryId'             => '1',
			'receiverPostCode'              => '87-100',
			'senderCountryId'               => '1',
			'senderPostCode'                => '87-100',
			'globkurier_show_all_providers' => 'true',
			'globkurier_is_pickup_active'   => 'true',
			'globkurier_pickup_type'        => '',
		], false);
		
		foreach ($products[ 'results' ] as $product) {
			// API zwraca 'ORLEN Paczka' - porownanie bez wielkosci liter (wczesniej productId zawsze 0).
			if (strcasecmp((string) ($product[ 'carrierName' ] ?? ''), 'Orlen Paczka') === 0) {
				$this->productId = $product[ 'id' ];
			}
		}
	}
	
}