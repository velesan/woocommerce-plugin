<?php

namespace udigroup_globkurier;

class UDIGroup_Public_Ajax
{
	public function __construct()
	{
	}
	
	public function init()
	{
		$this->priv();
		$this->noPriv();
	}
	
	private function priv()
	{
		$actionPrefix = 'wp_ajax_';
		add_action($actionPrefix.'globkurierGetInpostPoints', [$this, 'getInpostPoints']);
		add_action($actionPrefix.'globkurierGetInpostPointsSelect2', [$this, 'getInpostPointsSelect2']);
		
		add_action($actionPrefix.'globkurierGetRuchPoints', [$this, 'getRuchPoints']);
		add_action($actionPrefix.'globkurierGetRuchPointsSelect2', [$this, 'getRuchPointsSelect2']);
		
		add_action($actionPrefix.'globkurierSaveInpostPointsSession', [$this, 'saveInpostPointsSession']);
		add_action($actionPrefix.'globkurierSaveRuchPointsSession', [$this, 'saveRuchPointsSession']);
		
		add_action($actionPrefix.'globkurierGetPointsSession', [$this, 'globkurierGetPointsSession']);
		
		
		add_action($actionPrefix.'globkurierGetExtraPickupsPointsSelect2', [$this, 'getExtraPickupPointsSelect2']);
		add_action($actionPrefix.'globkurierSaveExtraPickupsPointsSession', [$this, 'saveExtraPickupsPointsSession']);
	}
	
	private function noPriv()
	{
		$actionPrefix = 'wp_ajax_nopriv_';
		add_action($actionPrefix.'globkurierGetInpostPoints', [$this, 'getInpostPoints']);
		add_action($actionPrefix.'globkurierGetInpostPointsSelect2', [$this, 'getInpostPointsSelect2']);
		
		add_action($actionPrefix.'globkurierGetRuchPoints', [$this, 'getRuchPoints']);
		add_action($actionPrefix.'globkurierGetRuchPointsSelect2', [$this, 'getRuchPointsSelect2']);
		
		add_action($actionPrefix.'globkurierSaveInpostPointsSession', [$this, 'saveInpostPointsSession']);
		add_action($actionPrefix.'globkurierSaveRuchPointsSession', [$this, 'saveRuchPointsSession']);
		
		add_action($actionPrefix.'globkurierGetPointsSession', [$this, 'globkurierGetPointsSession']);
		
		add_action($actionPrefix.'globkurierGetExtraPickupsPointsSelect2', [$this, 'getExtraPickupPointsSelect2']);
		add_action($actionPrefix.'globkurierSaveExtraPickupsPointsSession', [$this, 'saveExtraPickupsPointsSession']);
	}
	
	public function getInpostPoints()
	{
		$this->limitApiForPublicSearch();
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_get_inpost_points_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		global $globKurier;
		
		$data = array_map('sanitize_text_field', wp_unslash((array) ($_POST[ 'data' ] ?? [])));
		
		$points = $globKurier->inpost()->getPoints($data);
		
		die(json_encode($points));
	}
	
	public function getInpostPointsSelect2()
	{
		$this->limitApiForPublicSearch();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- celowo publiczna wyszukiwarka punktow w checkoucie (goscie, strony z cache, bez nonce): dane z zadania sa tylko filtrem i nie sa zapisywane; punkty z cache (dla innych krajow tylko kraje z mapy GK, cache per kraj), przy wygaslym cache odswiezenie w tle (WP-Cron), a gosc dostaje dotychczasowy cache.
		global $globKurier;
		
		$city = sanitize_text_field(wp_unslash($_GET[ 'city' ] ?? $_POST[ 'city' ] ?? ''));
		
		$data = [
			'city' => $city,
			'countryId' => $countryId = sanitize_text_field(wp_unslash($_GET[ 'countryId' ] ?? $_POST[ 'countryId' ] ?? '1')),
		];
		
		$points = $globKurier->inpost()->getPointsSelect2($data);
		
		die(json_encode($points));
		// phpcs:enable
	}
	
	public function getRuchPoints()
	{
		$this->limitApiForPublicSearch();
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_get_ruch_points_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		global $globKurier;
		
		$data = array_map('sanitize_text_field', wp_unslash((array) ($_POST[ 'data' ] ?? [])));
		
		$points = $globKurier->ruch()->getPoints($data);
		
		die(json_encode($points));
	}
	
	public function getRuchPointsSelect2()
	{
		$this->limitApiForPublicSearch();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- celowo publiczna wyszukiwarka punktow w checkoucie (goscie, strony z cache, bez nonce): dane z zadania sa tylko filtrem i nie sa zapisywane; bez product_id - lista z cache (wygasly odswiezany w tle, WP-Cron), z product_id (panel) - jedno zapytanie do API GK na wyszukiwanie, bez zapisu.
		global $globKurier;
		
		$city = sanitize_text_field(wp_unslash($_GET[ 'city' ] ?? $_POST[ 'city' ] ?? ''));
		
		$product_id = sanitize_text_field(wp_unslash($_GET[ 'product_id' ] ?? $_POST[ 'product_id' ] ?? ''));
		
		$data = ['city' => $city, 'product_id' => $product_id];
		
		$points = $globKurier->ruch()->getPointsSelect2($data);
		
		die(json_encode($points));
		// phpcs:enable
	}
			
	public function saveRuchPointsSession()
	{
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_save_ruch_points_session_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		WC()->session->set('globkurier_ruch_selected_point_id', sanitize_text_field(wp_unslash($_POST[ 'id' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_value', sanitize_text_field(wp_unslash($_POST[ 'value' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_latitude', sanitize_text_field(wp_unslash($_POST[ 'latitude' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_longitude', sanitize_text_field(wp_unslash($_POST[ 'longitude' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_city', sanitize_text_field(wp_unslash($_POST[ 'city' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_address', sanitize_text_field(wp_unslash($_POST[ 'address' ] ?? '')));
		WC()->session->set('globkurier_ruch_selected_point_openingHours', sanitize_text_field(wp_unslash($_POST[ 'openingHours' ] ?? '')));
	}
	
	public function saveInpostPointsSession()
	{
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_save_inpost_points_session_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		WC()->session->set('globkurier_inpost_selected_point_id', sanitize_text_field(wp_unslash($_POST[ 'id' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_value', sanitize_text_field(wp_unslash($_POST[ 'value' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_latitude', sanitize_text_field(wp_unslash($_POST[ 'latitude' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_longitude', sanitize_text_field(wp_unslash($_POST[ 'longitude' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_city', sanitize_text_field(wp_unslash($_POST[ 'city' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_address', sanitize_text_field(wp_unslash($_POST[ 'address' ] ?? '')));
		WC()->session->set('globkurier_inpost_selected_point_openingHours', sanitize_text_field(wp_unslash($_POST[ 'openingHours' ] ?? '')));
	}
			
	public function globkurierGetPointsSession()
	{
		wp_send_json_success([]);
	}
	
	public function getExtraPickupPointsSelect2()
	{
		$this->limitApiForPublicSearch();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- celowo publiczna wyszukiwarka punktow w checkoucie (goscie, strony z cache, bez nonce): dane z zadania sa tylko filtrem i nie sa zapisywane; 1-2 zapytania do API GK na wyszukiwanie, bez cache (timeout 15 s, bez ponawiania logowania).
		global $globKurier;
		
		$city = sanitize_text_field(wp_unslash($_GET[ 'city' ] ?? $_POST[ 'city' ] ?? ''));
		
		$data = ['city' => $city];
		
		$points = $globKurier->extraPickupPoints()->getPointsSelect2($data);
		
		die(json_encode($points));
		// phpcs:enable
	}
	
	public function saveExtraPickupsPointsSession()
	{
		$nonce = sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? ''));
		if (! wp_verify_nonce($nonce, 'globkurier_save_extra_pickup_points_session_nonce')) {
			wp_send_json_error('Invalid nonce');
		}
		
		global $globKurier;
		
		// Slug przewoznika (jak w kluczu ustawien extra_pickup_point_<slug> i przy odczycie w extraPickupPointSelect.php);
		// wczesniej absint() ze sluga dawal zawsze 0, wiec wybor punktu nie wracal po przeladowaniu.
		$carrierId = sanitize_title(wp_unslash($_POST[ 'carrierId' ] ?? ''));
		
		if ($carrierId === '' || ! array_key_exists('extra_pickup_point_'.$carrierId, (array) $globKurier->settings())) {
			wp_send_json_error('Unknown carrier');
		}
		
		foreach (['id', 'value', 'latitude', 'longitude', 'city', 'address', 'openingHours'] as $field) {
			WC()->session->set('globkurier_'.$carrierId.'_selected_point_'.$field, sanitize_text_field(wp_unslash($_POST[ $field ] ?? '')));
		}
	}

	/**
	 * Wyszukiwarki punktow (takze dla gosci): krotszy limit czasu zapytan do API GK i bez ponawiania logowania,
	 * zeby publiczne zadania nie trzymaly workerow PHP przy wolnym API ani nie mnozyly logowan na konto sklepu.
	 */
	private function limitApiForPublicSearch(): void
	{
		add_filter('globkurier_api_timeout', static function (){
			return 15;
		});
		add_filter('globkurier_api_login_retries', '__return_zero');
	}
	
}
