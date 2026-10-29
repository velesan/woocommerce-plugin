<?php

namespace udigroup_globkurier;

class GlobKurierDocuments extends GlobKurier
{
	
	public function tracking($params)
	{
		$function = 'order/tracking';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse($function, $token, $params, $method);
		
		if ($response[ 'code' ] == 200) {
			return $response[ 'data' ];
		} else {
			die('ERROR');
		}
	}
	
	public function getOrderLabelPdf($hash)
	{
		$api_url = $this->api()->getOrderLabelUrl($hash);
		
		$response = wp_remote_get($api_url, $this->api()->pdfRequestArgs('order/labels'));
		
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			
			return $error_message;
		}
		
		$response_body = wp_remote_retrieve_body($response);
		
		return $response_body;
	}
	
	public function getOrderProtocolPdf($hash)
	{
		$api_url = $this->api()->getOrderProtocolUrl($hash);
		
		$response = wp_remote_get($api_url, $this->api()->pdfRequestArgs('order/protocol'));
		
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			
			return $error_message;
		}
		
		$response_body = wp_remote_retrieve_body($response);
		
		return $response_body;
	}
	
	public function labels($params)
	{
		$function = 'order/labels';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$url = $this->api()->getResponse($function, $token, $params, $method, $onlyUrl = true);
		
		return ($url);
	}
	
	public function hasLabels($params)
	{
		$function = 'order/labels';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse($function, $token, $params, $method);
		
		return $response;
	}
	
	/**
	 * Dostepnosc etykiet per hash przesylki. Zapytanie do API trwa kilka sekund, wiec wynik zapamietujemy:
	 * dostepna etykieta nie znika (sprawdzamy raz na dobe), brak etykiety (np. przed oplaceniem) - po 10 minutach.
	 */
	public function labelsAvailability(array $hashes)
	{
		$hashes = array_values(array_unique(array_filter(array_map('strval', $hashes))));
		$now    = time();

		$cache = get_option('globkurier_labels_cache', []);
		$cache = is_array($cache) ? $cache : [];

		$result  = [];
		$unknown = [];
		foreach ($hashes as $hash) {
			if (isset($cache[ $hash ][ 'expires' ]) && $cache[ $hash ][ 'expires' ] > $now) {
				$result[ $hash ] = ! empty($cache[ $hash ][ 'available' ]);
			} else {
				$unknown[] = $hash;
			}
		}

		if (! $unknown) {
			return $result;
		}

		$response = $this->hasLabels([
			'orderHashes' => $unknown,
		]);

		if (($response[ 'code' ] ?? 0) == 200) {
			$missing = [];
		} elseif (isset($response[ 'data' ][ 'fields' ]) && is_array($response[ 'data' ][ 'fields' ])) {
			// API zwraca bledy pod kluczami orderHashes[i] - indeksy dotycza wyslanej listy $unknown.
			$missing = $response[ 'data' ][ 'fields' ];
		} else {
			// Blad API - jak wczesniej pokazujemy brak etykiety i niczego nie zapamietujemy.
			return $result + array_fill_keys($unknown, false);
		}

		foreach ($unknown as $key => $hash) {
			$available = ! array_key_exists("orderHashes[{$key}]", $missing);

			$result[ $hash ] = $available;
			$cache[ $hash ]  = [
				'available' => $available,
				'expires'   => $now + ($available ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS),
			];
		}

		foreach ($cache as $hash => $entry) {
			if (! isset($entry[ 'expires' ]) || $entry[ 'expires' ] <= $now) {
				unset($cache[ $hash ]);
			}
		}

		update_option('globkurier_labels_cache', $cache, false);

		return $result;
	}

	public function protocols($params)
	{
		$function = 'order/protocol';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse($function, $token, $params, $method);
		
		if ($response[ 'code' ] == 200) {
			wp_send_json_success($response[ 'data' ]);
		} else {
			$errors = [];
			foreach ($response[ 'data' ][ 'fields' ] ?? [] as $key => $error) {
				$errors [] = $error;
			}
			
			wp_send_json_error($errors);
		}
	}
	
	/**
	 * Strona listy "Historia przesylek" (DataTables serverSide) z dostepnoscia etykiet.
	 */
	public function get($params)
	{
		$page   = $this->historyPage($params, 'd.m.Y');
		$orders = $page[ 'rows' ];
		$hashes = $page[ 'hashes' ];

		$labels = $this->labelsAvailability($hashes);

		foreach ($orders as $i => &$order) {
			$order[ 'hasLabel' ] = ! empty($labels[ $hashes[ $i ] ]);
		}
		unset($order);

		$json_data = [
			'draw'            => $params[ 'draw' ],
			'recordsTotal'    => $page[ 'total' ],
			'recordsFiltered' => $page[ 'total' ],
			'data'            => $orders,
		];

		echo wp_json_encode($json_data);
		die;
	}

	/**
	 * Strona listy "Protokoly" (DataTables serverSide). Wczesniej szablon renderowal wszystkie przesylki naraz.
	 * Bez sprawdzania etykiet w API - protokol nie zalezy od etykiety.
	 */
	public function getProtocolsList($params)
	{
		global $globKurier;

		$page   = $this->historyPage($params, 'd.m.Y H:i');
		$orders = $page[ 'rows' ];

		foreach ($orders as $i => &$order) {
			$order[ 'order_protocol_url' ] = esc_url($globKurier->api()->getOrderProtocolPdfUrl(rawurlencode($page[ 'hashes' ][ $i ])));
		}
		unset($order);

		echo wp_json_encode([
			'draw'            => $params[ 'draw' ],
			'recordsTotal'    => $page[ 'total' ],
			'recordsFiltered' => $page[ 'total' ],
			'data'            => $orders,
		]);
		die;
	}

	/**
	 * Wiersze jednej strony historii (wspolne dla historii i protokolow).
	 *
	 * @return array{total:int, rows:array<int, array>, hashes:string[]} hashes - surowe, w kolejnosci wierszy
	 */
	private function historyPage($params, string $dateFormat): array
	{
		global $globKurier;

		$start  = (int) ($params[ 'start' ] ?? 0);
		$length = (int) ($params[ 'length' ] ?? 10);

		// Historia z wp_postmeta i wc_orders_meta (HPOS), bez duplikatow, najnowsze na gorze. Data nadania siedzi
		// w zserializowanym wpisie, wiec sortowanie i stronicowanie robimy w PHP na calej (lekkiej) liscie.
		$page = UDIGroup_ShipmentHistory::query((string) ($params[ 'search' ][ 'value' ] ?? ''), $start, $length);

		// Zamowienia WC dla calej strony jednym zapytaniem (wczesniej wc_get_order dla kazdego wiersza).
		$wcOrders = UDIGroup_ShipmentHistory::ordersById(array_column($page[ 'items' ], 'owner_id'));

		$orders = [];
		$hashes = [];

		foreach ($page[ 'items' ] as $item) {
			$data = $item[ 'entry' ];

			$wcOrderId = $item[ 'owner_id' ];
			$wcOrder   = $wcOrders[ $wcOrderId ] ?? null;

			// Kolumny tekstowe sa renderowane przez DataTables jako HTML - escapujemy je tutaj.
			$order = [];

			$order[ 'check_btn' ]             = true;
			$order[ 'number' ]                = esc_html($data[ 'number' ] ?? '');
			$order[ 'timestamp' ]             = (int) ($data[ 'date' ] ?? 0);
			// Czas sklepu (Ustawienia > Ogolne), a nie UTC - przy gmdate() nadania po polnocy mialy date poprzedniego dnia.
			$order[ 'date' ]                  = wp_date($dateFormat, $order[ 'timestamp' ]);
			$order[ 'receiver_address_name' ] = esc_html($data[ 'data' ][ 'receiverAddress' ][ 'name' ] ?? '');
			$order[ 'content' ]               = esc_html($data[ 'data' ][ 'content' ] ?? '');
			$order[ 'shipment_weight' ]       = esc_html($data[ 'data' ][ 'shipment' ][ 'weight' ] ?? '');
			$order[ 'carrier_name' ]          = esc_html($data[ 'carrier' ][ 'name' ] ?? '');
			$order[ 'cod_value' ]             = esc_html($data[ 'cod' ][ 'value' ] ?? '');
			$order[ 'payment_name' ]          = esc_html($data[ 'payment_name' ] ?? '');
			$order[ 'price_net' ]             = number_format(round((float) ($data[ 'price' ][ 'net' ] ?? 0), 2), 2);
			$order[ 'get_status_btn' ]        = '';
			// hash i adresy tez trafiaja do atrybutow HTML; surowy hash zostaje tylko do zapytania o etykiety w API.
			$hash = (string) ($data[ 'hash' ] ?? '');

			$order[ 'wc_order_link' ]         = $wcOrder ? esc_url($wcOrder->get_edit_order_url()) : null;
			$order[ 'wc_order_id' ]           = (int) $wcOrderId;
			// Numer widoczny dla obslugi sklepu (wtyczki numeracji zamowien) - ID zostaje tylko w linku do edycji.
			$order[ 'wc_order_number' ]       = $wcOrder ? esc_html($wcOrder->get_order_number()) : '';
			$order[ 'hash' ]                  = esc_attr($hash);
			$order[ 'order_label_url' ]       = esc_url($globKurier->api()->getOrderLabelPdfUrl(rawurlencode($hash)));
			$order[ 'order_track_url' ]       = esc_url($globKurier->api()->getOrderTrackUrl(rawurlencode((string) ($data[ 'number' ] ?? ''))));
			$order[ 'hasLabel' ]              = false;

			$orders[] = $order;
			$hashes[] = $hash;
		}

		return [
			'total'  => $page[ 'total' ],
			'rows'   => $orders,
			'hashes' => $hashes,
		];
	}
	
	public function getOrder($params)
	{
		$function = 'order';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse($function, $token, $params, $method);
		
		return $response;
	}
	
}