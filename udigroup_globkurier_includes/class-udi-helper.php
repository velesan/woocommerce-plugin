<?php

namespace udigroup_globkurier;

class UDIGroup_Helper{
	
	public static function getGlobal( $var ){
		$prefix = 'UDIGroup_GLOBKURIER_';
		
		return constant( $prefix . $var );
	}
	
	public static function getBasePath(){
		
		return plugin_dir_path( __DIR__ );
	}
	
	public static function getBaseUrl(){
		
		return plugin_dir_url( __DIR__ );
	}
	
	public static function getInlcudesPath( $path = '' ): string{
		
		return self::getBasePath() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'includes/' . $path;
	}
	
	public static function getInlcudesUrl( $path = '' ): string{
		
		return self::getBaseUrl() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'includes/' . $path;
	}
	
	public static function getPublicPath( $path = '' ): string{
		
		return self::getBasePath() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'public/' . $path;
	}
	
	public static function getAjaxUrl(){
		
		return admin_url( 'admin-ajax.php' );
	}
	
	public static function getPublicUrl( $path = '' ): string{
		
		return self::getBaseUrl() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'public/' . $path;
	}
	
	public static function getAdminPath( $path = '' ): string{
		
		return self::getBasePath() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'admin/' . $path;
	}
	
	public static function getAdminUrl( $path = '' ): string{
		
		return self::getBaseUrl() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'admin/' . $path;
	}
	
	public static function getLanguagePath( $path = '' ): string{
		
		return self::getBasePath() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'language/' . $path;
	}
	
	public static function getLanguageUrl( $path = '' ): string{

		return self::getBaseUrl() . self::getGlobal( 'DIRECTORY_PREFIX' ) . 'language/' . $path;
	}

	/**
	 * Czy biezacy uzytkownik moze nadawac przesylki / korzystac z AJAX-ow panelu.
	 * Wystarczy dowolne z uprawnien sklepowych - ten sam krag osob, ktory widzi metabox zamowienia.
	 * Filtr pozwala dopuscic niestandardowe role u konkretnego klienta.
	 */
	public static function currentUserCanManageShipments(): bool{

		$allowed = current_user_can( 'edit_shop_orders' )
			|| current_user_can( 'edit_others_shop_orders' )
			|| current_user_can( 'manage_woocommerce' )
			|| current_user_can( 'manage_options' );

		return (bool) apply_filters( 'globkurier_user_can_manage_shipments', $allowed, get_current_user_id() );
	}

	/**
	 * Czy biezacy uzytkownik moze obslugiwac to zamowienie WooCommerce (nadanie, odprawa, historia przesylek).
	 * Najpierw uprawnienie do konkretnego zamowienia (meta-cap WC, dziala z HPOS i bez). Role z samym
	 * manage_woocommerce / manage_options przechodza jak dotad (zgodnosc wsteczna, ta sama regula co AJAX panelu).
	 */
	public static function canProcessOrder( int $orderId ): bool{

		if( $orderId <= 0 || ! wc_get_order( $orderId ) ){
			return false;
		}

		if( current_user_can( 'edit_shop_order', $orderId ) ){
			return true;
		}

		return (bool) apply_filters( 'globkurier_can_process_order', self::currentUserCanManageShipments(), $orderId );
	}

	/**
	 * Sanityzacja danych z $_POST przekazywanych do API GlobKuriera.
	 * Klucze z $rawKeys zawieraja zserializowane query stringi (parse_str) - sanitize_text_field
	 * usunalby z nich sekwencje %XX, wiec dla nich robimy tylko wp_unslash.
	 */
	public static function sanitizeRequestData( $value, $key = null ){

		$rawKeys = [ 'addons', 'address' ];

		if( is_array( $value ) ){
			$result = [];
			foreach( $value as $k => $v ){
				$result[ $k ] = self::sanitizeRequestData( $v, $k );
			}

			return $result;
		}

		if( ! is_scalar( $value ) ){
			return $value;
		}

		$value = wp_unslash( (string) $value );

		if( in_array( $key, $rawKeys, true ) ){
			return $value;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Plik cache punktow (inpost.json / orlen.json) w uploads/globkurier - katalog wtyczki jest nadpisywany
	 * przy kazdej aktualizacji. Do odczytu ($forRead), dopoki w uploads nie ma pliku, zwraca dotychczasowa
	 * lokalizacje w katalogu wtyczki (dane dolaczone do paczki / zapisane przez starsze wersje).
	 */
	public static function pointsCacheFile( string $name, bool $forRead = false ): string{

		$uploads = wp_upload_dir( null, false );
		$file    = trailingslashit( $uploads[ 'basedir' ] ) . 'globkurier/' . $name;

		if( $forRead && ! file_exists( $file ) ){
			return self::getAdminPath( 'globkurier/' . $name );
		}

		return $file;
	}

	/**
	 * Planuje odswiezenie listy punktow w tle (WP-Cron). Jedno zdarzenie na hook - kolejne wywolania, takze z zadan
	 * gosci, niczego nie dopisuja, dopoki zaplanowane czeka. false, gdy WP-Cron nie dziala (zdarzenie wisi ponad
	 * godzine, np. zablokowany loopback na hostingu) - wtedy wolajacy pobiera synchronicznie, jak przed 2.7.
	 */
	public static function schedulePointsRefresh( string $hook ): bool{

		$next = wp_next_scheduled( $hook );

		if( $next === false ){
			return (bool) wp_schedule_single_event( time(), $hook );
		}

		return $next > time() - HOUR_IN_SECONDS;
	}

	/** Bez limitu czasu dla pelnego pobierania punktow w tle (o ile hosting pozwala zmienic limit). */
	public static function extendTimeLimit(): void{

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		if( function_exists( 'set_time_limit' ) && ! in_array( 'set_time_limit', $disabled, true ) ){
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- tylko w zdarzeniu WP-Cron pelnego pobierania punktow (kilka stron API po do 60 s); na tanich hostingach max_execution_time 30 s przerywal pobieranie.
			set_time_limit( 0 );
		}
	}

	/**
	 * Zapis pliku cache punktow: najpierw plik tymczasowy, potem rename() - rownolegly odczyt nie trafi na
	 * polowe pliku. Katalog dostaje pusty index.php (bez listowania). true tylko przy udanym zapisie.
	 */
	public static function writePointsCacheFile( string $path, string $content ): bool{

		$dir = dirname( $path );
		if( ! wp_mkdir_p( $dir ) ){
			return false;
		}

		if( ! file_exists( $dir . '/index.php' ) ){
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- pusty index.php w katalogu cache w uploads.
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		$tmp = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache punktow w uploads, zapis atomowy przez rename().
		if( file_put_contents( $tmp, $content ) === false ){
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomowa podmiana pliku cache.
		if( ! rename( $tmp, $path ) ){
			wp_delete_file( $tmp );

			return false;
		}

		return true;
	}

	/**
	 * $_POST[$key] po sanitizeRequestData(). Jedyne miejsce odczytu surowego $_POST['data'] w handlerach AJAX
	 * panelu - wolajacy sprawdza nonce i uprawnienia przed wywolaniem.
	 */
	public static function postData( string $key = 'data' ){

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce i uprawnienia sprawdza handler AJAX przed wywolaniem; sanitizeRequestData() robi wp_unslash + sanitize_text_field rekurencyjnie (bez sanitize_text_field tylko 'addons'/'address' - zakodowane dane przekazywane 1:1 do API GK, nigdzie nie wypisywane).
		return self::sanitizeRequestData( $_POST[ $key ] ?? [] );
	}

	public static function parseAddress($address, $country = ''):array
	{
		$address = trim(preg_replace('/\s+/', ' ', (string)$address));
		
		if ($address === '') {
			return [
				'street' => null,
				'number' => null,
			];
		}
		
		$country = strtoupper((string)$country);
		$numberFirstCountries = ['US', 'CA', 'GB', 'IE', 'FR', 'AU', 'NZ'];
		
		if ($country === 'PL') {
			$address = preg_replace('/^(ulica|ul)\.?/iu', 'ul.', $address);
			$address = preg_replace('/^(aleja|al)\.?/iu', 'al.', $address);
			$address = preg_replace('/^(osiedle|os)\.?/iu', 'os.', $address);
		}
		
		if ($country === 'PL' && preg_match('/^(ul\.|al\.|os\.)?\s*\d+\s+[A-ZĄĆĘŁŃÓŚŹŻa-ząćęłńóśźż]/u', $address)) {
			if (preg_match('/^(?P<prefix>(ul\.|al\.|os\.)\s*)?(?P<street>.+?)\s+(?P<number>\d+[A-Z]?(?:[-\/]\d+[A-Z]?)?)$/u', $address, $plMatches)) {
				return [
					'street' => trim($plMatches['street']),
					'number' => trim($plMatches['number']),
				];
			}
			
			return [
				'street' => $address,
				'number' => null,
			];
		}
		
		$regexNumberFirst = '/^(?P<number>\d+[A-Z]?(?:[-\/]\d+[A-Z]?)?)\s+(?P<street>.+)$/u';
		$regexNumberLast  = '/^(?P<prefix>(ul\.|al\.|os\.)\s*)?(?P<street>.+?)\s+(?P<number>\d+[A-Z]?(?:[-\/]\d+[A-Z]?)?)(?:\s|$)/u';
		$regex             = in_array($country, $numberFirstCountries, true) ? $regexNumberFirst : $regexNumberLast;
		
		if (preg_match($regex, $address, $matches)) {
			return [
				'street' => trim($matches['street']),
				'number' => trim($matches['number']),
			];
		}
		
		if (preg_match('/^\d+[A-Z]?(?:[-\/]\d+[A-Z]?)?$/', $address)) {
			return [
				'street' => trim($address),
				'number' => null,
			];
		}
		
		return [
			'street' => trim($address),
			'number' => null,
		];
	}
	
}