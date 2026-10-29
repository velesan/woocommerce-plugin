<?php

namespace udigroup_globkurier;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once UDIGroup_Helper::getAdminPath('globkurier/GlobKurier.php');

class GlobKurierApi
{
	protected $globKurier;
	
	protected $apiVersion = 'v1';
	
	protected $globServerTest = 'https://test.api.globkurier.pl/';
	protected $globServerProd = 'https://api.globkurier.pl/';
	
	protected $trackingUrl = 'https://www.globkurier.pl/shipment-tracking/';
	
	protected $testMode = false;
	
	private $token;

	/** Trwa ponowienie zapytania po 401 (nowy token) - zeby nie ponawiac w kolko. */
	private $retryingAfterUnauthorized = false;

	public function __construct($globKurier)
	{
		$this->globKurier = $globKurier;
	}
	
	private function getServerUrl()
	{
		$base = $this->testMode ? $this->globServerTest : $this->globServerProd;
		
		return $base.$this->apiVersion;
	}
	
	public function testConnection($getCode = false)
	{
		$testFunction = 'countries';
		$method       = 'GET';
		
		$response = $this->getResponse($testFunction, null, [], $method);
		
		$code = $response[ 'code' ];
		
		if ($getCode) {
			return $code;
		}
		
		if ($code == 200) {
			$status = 'OK';
		} else {
			$status = 'BŁĄD '.$code.' ('.$response[ 'data' ][ 'general' ][ 0 ].')';
		}
		
		echo '<strong>Połączenie z serwerek GlobKurier:</strong> '.esc_attr($status);
		
		return $code;
	}
	
	private function prepareUrl($function, $params = [], $method = 'POST')
	{
		$baseUrl = $this->getServerUrl();
		
		if ($method == 'POST') {
			return $baseUrl.'/'.$function;
		}
		
		$vars = http_build_query($params, '', '&');
		
		return $baseUrl.'/'.$function.'?'.$vars;
	}
	
	public function getResponse($function, $token = null, $params = [], $method = 'POST', $onlyUrl = false)
	{
		$url = $this->prepareUrl($function, $params, $method);
		
		if ($onlyUrl) {
			return $url;
		}
		
		$args = [
			'method'    => $method,
			'headers'   => [
				'Accept-Language' => $this->globKurier->getLanguage(),
			],
			// Wczesniej cURL bez limitu czasu (ogranicza go i tak max_execution_time) - domyslne 5 s z WP
			// przerywaloby wolniejsze zapytania (nadanie, PDF, listy punktow). Filtr dostaje tez endpoint i metode.
			'timeout'   => (int) apply_filters('globkurier_api_timeout', self::defaultTimeout($function, $method), $function, $method),
			// Pakiet CA z WordPressa (wp-includes/certificates) - domyslny dla wp_remote_*.
			'sslverify' => $this->sslVerify(),
		];

		if ($method == 'POST' || $method == 'PUT') {
			$args[ 'headers' ][ 'Content-Type' ] = 'application/json';
			$args[ 'body' ]                      = json_encode($params);
		}

		if ($token) {
			$args[ 'headers' ][ 'x-auth-token' ] = $token;
		}

		$response = wp_remote_request($url, $args);

		// Blad polaczenia: ten sam ksztalt co wczesniej z cURL (kod 0, brak danych); rodzaj bledu dla komunikatu w panelu.
		if (is_wp_error($response)) {
			self::rememberConnectionProblem($response);

			return [
				'code' => 0,
				'data' => null,
			];
		}

		self::clearConnectionProblem();

		$code = (int) wp_remote_retrieve_response_code($response);

		// Token z cache moze wygasnac po stronie API wczesniej niz u nas (np. zmiana hasla na koncie GK):
		// jedno ponowienie z nowym tokenem zamiast bledu.
		if ($code === 401 && $token && $function !== 'auth/login' && ! $this->retryingAfterUnauthorized) {
			$this->forgetToken();

			if ($this->login() === 200) {
				$this->retryingAfterUnauthorized = true;

				try {
					return $this->getResponse($function, $this->getCurrentToken(), $params, $method);
				} finally {
					$this->retryingAfterUnauthorized = false;
				}
			}
		}

		return [
			'code' => $code,
			'data' => json_decode(wp_remote_retrieve_body($response), true),
		];
	}

	/** Limit czasu dla odczytow (formularze, ustawienia, ceny, statusy) - przy wolnym API strona nie wisi minutami. */
	const READ_TIMEOUT = 60;

	/** Nadanie przesylki, PDF i pelne listy punktow - moga trwac dlugo, a przerwanie nadania jest kosztowne. */
	const LONG_TIMEOUT = 300;

	public static function defaultTimeout(string $function, string $method): int
	{
		$long = ($function === 'order' && $method === 'POST')
			|| in_array($function, ['order/labels', 'order/protocol', 'points'], true);

		return $long ? self::LONG_TIMEOUT : self::READ_TIMEOUT;
	}

	/**
	 * Argumenty pobrania PDF (etykiety, protokoly) wprost przez wp_remote_get - ten sam limit czasu i weryfikacja SSL
	 * co w getResponse(). Wczesniej domyslne 5 s z WordPressa przerywalo wolniej generowane PDF.
	 */
	public function pdfRequestArgs(string $function): array
	{
		return [
			'headers'   => ['x-auth-token' => $this->getToken()],
			'timeout'   => (int) apply_filters('globkurier_api_timeout', self::defaultTimeout($function, 'GET'), $function, 'GET'),
			'sslverify' => $this->sslVerify(),
		];
	}

	const CONNECTION_PROBLEM = 'globkurier_api_connection_problem';

	/** Nonce linkow GET do PDF etykiet i protokolow (strony panelu). */
	const PDF_NONCE = 'globkurier_pdf_documents';

	/**
	 * Zapamietuje bledy polaczenia, ktore wymagaja dzialania administratora strony: blokada ruchu wychodzacego
	 * w WordPressie (WP_HTTP_BLOCK_EXTERNAL) albo nieudana weryfikacja certyfikatu SSL. 2.5.4 laczyla sie przez
	 * cURL z pominieciem obu - komunikat w panelu mowi, co ustawic.
	 */
	private static function rememberConnectionProblem(\WP_Error $error): void
	{
		$message = $error->get_error_message();

		if ($error->get_error_code() === 'http_request_not_executed') {
			$type = 'blocked';
		} elseif (preg_match('/SSL|certificate|cURL error (35|51|58|60|77)\b/i', $message)) {
			$type = 'ssl';
		} else {
			return;
		}

		set_transient(self::CONNECTION_PROBLEM, ['type' => $type, 'message' => $message, 'time' => time()], DAY_IN_SECONDS);
	}

	/** Udane polaczenie konczy komunikat (sprawdzane raz na zadanie). */
	private static function clearConnectionProblem(): void
	{
		static $checked = false;

		if (! $checked) {
			$checked = true;
			if (get_transient(self::CONNECTION_PROBLEM)) {
				delete_transient(self::CONNECTION_PROBLEM);
			}
		}
	}

	/**
	 * Komunikat w panelu o problemie z polaczeniem z API GlobKurier (dla obslugi sklepu).
	 */
	public static function connectionNotice(): void
	{
		$problem = get_transient(self::CONNECTION_PROBLEM);

		if (! is_array($problem) || ! current_user_can('manage_woocommerce')) {
			return;
		}

		if ($problem[ 'type' ] === 'blocked') {
			$text = __("GlobKurier: WordPress blokuje połączenia z API GlobKurier (stała WP_HTTP_BLOCK_EXTERNAL). Dodaj w wp-config.php host api.globkurier.pl do WP_ACCESSIBLE_HOSTS, np. define( 'WP_ACCESSIBLE_HOSTS', 'api.globkurier.pl' ); - do tego czasu nadawanie przesyłek i wyszukiwanie punktów nie działa.", 'globkurier');
		} else {
			$text = __("GlobKurier: nie udało się zweryfikować certyfikatu SSL serwera API GlobKurier. Najczęściej to przestarzały OpenSSL na serwerze albo proxy przechwytujące ruch - skontaktuj się z hostingiem. W ostateczności weryfikację można wyłączyć filtrem: add_filter( 'globkurier_api_sslverify', '__return_false' );", 'globkurier');
		}

		echo '<div class="notice notice-error"><p>'.esc_html($text).'</p><p><small>'.esc_html($problem[ 'message' ]).'</small></p></div>';
	}

	/**
	 * Weryfikacja certyfikatu API. Domyslnie wlaczona; filtr pozwala ja wylaczyc na hostingu,
	 * na ktorym polaczenie z weryfikacja nie dziala (np. przechwytujacy proxy firmowy).
	 */
	private function sslVerify(): bool
	{
		return (bool) apply_filters('globkurier_api_sslverify', true);
	}
	
	public function getLoginCredentials()
	{
		$username = $this->globKurier->settings('username');
		$password = $this->globKurier->settings('password');
		if (! empty($password)) {
			$password = $this->globKurier->encrypter()->decrypt($password);
		}
		
		return [
			'username' => $username,
			'password' => $password,
		];
	}
	
	public function testToken()
	{
		return $this->login();
	}

	/**
	 * Logowanie do API. Przy sukcesie zapisuje token (wczesniej testToken() go wyrzucal), zwraca kod odpowiedzi
	 * (0 = brak polaczenia). Bez die - w odroznieniu od getToken() mozna to wolac przy renderowaniu stron panelu.
	 */
	public function login(): int
	{
		$credentials = $this->getLoginCredentials();

		$response = $this->getResponse('auth/login', null, [
			'email'    => $credentials[ 'username' ],
			'password' => $credentials[ 'password' ],
		], 'POST');

		$code = (int) $response[ 'code' ];

		if ($code === 200 && ! empty($response[ 'data' ][ 'token' ])) {
			$this->setCurrentToken($response[ 'data' ][ 'token' ]);
			$this->token = $response[ 'data' ][ 'token' ];
		}

		return $code;
	}

	/**
	 * Kod 200, gdy jest wazny token z cache (bez zapytania do API); inaczej wynik logowania.
	 */
	public function ensureToken(): int
	{
		if ($this->getCurrentToken()) {
			return 200;
		}

		return $this->login();
	}

	/**
	 * Usuwa zapisany token (bez die, w odroznieniu od clearCurrentToken()) - np. po zmianie danych logowania.
	 */
	public function forgetToken(): void
	{
		update_option('globkurier_token', null);
		update_option('globkurier_token_expire_at', 0);
		$this->token = null;
	}
	
	public function clearCurrentToken()
	{
		update_option('globkurier_token', null);
		update_option('globkurier_token_expire_at', 0);
		die;
	}
	
	public function getCurrentToken()
	{
		$currentToken         = get_option('globkurier_token');
		$currentTokenExpireAt = get_option('globkurier_token_expire_at');
		
		if (empty($currentToken) || $currentTokenExpireAt < time()) {
			update_option('globkurier_token', null);
			update_option('globkurier_token_expire_at', null);
			
			return null;
		}

		return $currentToken;
	}
	public function setCurrentToken($token, $expireSeconds = 24 * 60 * 60, $expireSecondsThreshold = 4 * 60 * 60)
	{
		if (! $token) {
			return;
		}
		
		update_option('globkurier_token', $token);
		update_option('globkurier_token_expire_at', (time() + $expireSeconds - $expireSecondsThreshold));
	}
	
	public function getToken(int $tries = 0)
	{
		if ($currentToken = $this->getCurrentToken()) {
			return $this->token = $currentToken;
		}
		
		$credentials = $this->getLoginCredentials();
		
		if (! $credentials[ 'username' ] || ! $credentials[ 'password' ]) {
			$this->globKurier->handleError('Login and password must be set');
		}
		
		$function = 'auth/login';
		$method   = 'POST';
		
		$data = [
			'email'    => $credentials[ 'username' ],
			'password' => $credentials[ 'password' ],
		];
		
		$response = $this->getResponse($function, null, $data, $method);
		
		$code = $response[ 'code' ];
		
		if ($code == 200 && isset($response[ 'data' ][ 'token' ])) {
			$token = $response[ 'data' ][ 'token' ];
		} else {
			// Domyslnie 4 ponowienia (5 prob, jak dotad); publiczne wyszukiwarki punktow ustawiaja 0, zeby zadania gosci
			// przy blednych danych logowania nie mnozyly logowan na konto GK sklepu. Ponawiamy tylko bledy chwilowe
			// (brak polaczenia, 5xx) - zle dane logowania (4xx) nie zmienia sie przy kolejnej probie.
			$transient = $code === 0 || $code >= 500;

			if ($transient && $tries++ < (int) apply_filters('globkurier_api_login_retries', 4)) {
				return $this->getToken($tries);
			}
			
			$this->globKurier->handleError('Błąd podczas pobierania tokenu '.\json_encode($response));
		}
		
		$this->setCurrentToken($token ?? null);
		
		return $this->token = $token;
	}
	
	public function getOrderLabelUrl($hashes)
	{
		$function = 'order/labels';
		$method   = 'GET';
		
		$hashes = explode(',', $hashes);
		
		$params = [];
		foreach ($hashes as $i => $hash) {
			$params[ 'orderHashes['.$i.']' ] = $hash;
		}
		
		return $this->prepareUrl($function, $params, $method);
	}
	public function getOrderProtocolUrl($hashes)
	{
		$function = 'order/protocol';
		$method   = 'GET';
		
		$hashes = explode(',', $hashes);
		
		$params = [];
		foreach ($hashes as $i => $hash) {
			$params[ 'orderHashes['.$i.']' ] = $hash;
		}
		
		return $this->prepareUrl($function, $params, $method);
	}
	
	public function getOrderLabelPdfUrl($hashes)
	{
		return get_admin_url().'admin.php?page=globkurier_all_orders&getPdfLabels=1&hashes='.$hashes.'&_wpnonce='.wp_create_nonce(self::PDF_NONCE);
	}
	
	public function getOrderProtocolPdfUrl($hashes)
	{
		return get_admin_url().'admin.php?page=globkurier_all_orders&getPdfProtocols=1&hashes='.$hashes.'&_wpnonce='.wp_create_nonce(self::PDF_NONCE);
	}
	
	public function getOrderTrackUrl($number)
	{
		return $this->trackingUrl.$number;
	}
	
}