<?php

namespace udigroup_globkurier;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Customs
{
	const NONCE_CONFIG    = 'globkurier_customs_config_nonce';
	const NONCE_HSCODE    = 'globkurier_customs_hscode_nonce';
	const NONCE_IMPORT    = 'globkurier_customs_import_nonce';
	const NONCE_TRANSLATE = 'globkurier_customs_translate_nonce';

	const CAPABILITY = 'edit_shop_orders';

	public function init(): void
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

		add_action('wp_ajax_globkurierGetCustomsConfig', [$this, 'ajaxGetConfig']);
		add_action('wp_ajax_globkurierFetchHsCode', [$this, 'ajaxFetchHsCode']);
		add_action('wp_ajax_globkurierImportCustomsItems', [$this, 'ajaxImportItems']);
		add_action('wp_ajax_globkurierTranslateCustoms', [$this, 'ajaxTranslate']);

		add_action('globkurier_after_receiver_data', [$this, 'renderSection'], 10, 3);
		add_action('admin_footer', [$this, 'renderModal']);
	}

	private function isOrderPage(): bool
	{
		$screen = get_current_screen();

		if (! $screen) {
			return false;
		}

		// HPOS: woocommerce_page_wc-orders?action=edit
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametr adresu ekranu panelu tylko do wyboru assetow, bez zmiany stanu.
		$action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));

		if ($screen->id === 'woocommerce_page_wc-orders' && $action === 'edit') {
			return true;
		}

		// Klasyczny: shop_order (edycja posta)
		if ($screen->id === 'shop_order' && $screen->base === 'post') {
			return true;
		}

		return false;
	}

	public function enqueue_scripts(): void
	{
		if (! $this->isOrderPage() || ! current_user_can(self::CAPABILITY)) {
			return;
		}

		$jsPath = plugin_dir_path(__FILE__) . 'assets/customs.js';
		$cssPath = plugin_dir_path(__FILE__) . 'assets/customs.css';

		$jsVer = file_exists($jsPath) ? filemtime($jsPath) : '1.0';
		$cssVer = file_exists($cssPath) ? filemtime($cssPath) : '1.0';

		wp_enqueue_script(
			'globkurier_customs_script',
			plugin_dir_url(__FILE__) . 'assets/customs.js',
			['jquery'],
			$jsVer,
			true
		);

		wp_enqueue_style(
			'globkurier_customs_style',
			plugin_dir_url(__FILE__) . 'assets/customs.css',
			[],
			$cssVer
		);

		wp_localize_script('globkurier_customs_script', 'globkurierCustomsCfg', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonces'  => [
				'config'    => wp_create_nonce(self::NONCE_CONFIG),
				'hsCode'    => wp_create_nonce(self::NONCE_HSCODE),
				'import'    => wp_create_nonce(self::NONCE_IMPORT),
				'translate' => wp_create_nonce(self::NONCE_TRANSLATE),
			],
			'i18n'    => $this->i18nStrings(),
		]);
	}

	private function i18nStrings(): array
	{
		return [
			'sectionTitle'   => __('Odprawa Celna', 'globkurier'),
			'editButton'     => __('Edytuj deklarację celną', 'globkurier'),
			'required'       => __('Pole jest wymagane', 'globkurier'),
			'save'           => __('Zapisz i zamknij', 'globkurier'),
			'addRow'         => __('Dodaj pozycję', 'globkurier'),
			'importItems'    => __('Pobierz listę produktów z zamówienia', 'globkurier'),
			'removeRow'      => __('Usuń pozycję', 'globkurier'),
			'resetConfirm'   => __('Zmiana przewoźnika wyczyści wypełnioną deklarację celną. Kontynuować?', 'globkurier'),
			'requestError'   => __('Wystąpił błąd komunikacji. Spróbuj ponownie.', 'globkurier'),
			'fixFields'      => __('Popraw zaznaczone pola deklaracji celnej.', 'globkurier'),
			/* translators: %d: liczba znakow (podstawiane w JS) */
			'valMinLength'   => __('Min. %d znaków', 'globkurier'),
			/* translators: %d: liczba znakow (podstawiane w JS) */
			'valMaxLength'   => __('Maks. %d znaków', 'globkurier'),
			/* translators: %s: wartosc graniczna (podstawiane w JS) */
			'valGreater'     => __('Wartość musi być większa niż %s', 'globkurier'),
			/* translators: %s: wartosc graniczna (podstawiane w JS) */
			'valGreaterEq'   => __('Wartość nie może być mniejsza niż %s', 'globkurier'),
			/* translators: %s: wartosc graniczna (podstawiane w JS) */
			'valLessEq'      => __('Wartość nie może być większa niż %s', 'globkurier'),
			'valDate'        => __('Nieprawidłowa data (RRRR-MM-DD)', 'globkurier'),
			'valNumber'      => __('Wartość musi być liczbą', 'globkurier'),
			/* translators: %d: liczba miejsc po przecinku (podstawiane w JS) */
			'valPrecision'   => __('Maks. %d miejsc po przecinku', 'globkurier'),
			'weightExceeds'  => __('Waga całkowita w deklaracji celnej nie może być większa niż waga podana w zamówieniu.', 'globkurier'),
			'hsHint'         => __('Wpisz opis towaru, aby wyszukać kod HS...', 'globkurier'),
			'translate'      => __('Przetłumacz na angielski', 'globkurier'),
			'translateError' => __('Tłumaczenie nie powiodło się. Spróbuj ponownie.', 'globkurier'),
			'afterOrderInfo' => __('Pamiętaj, aby przesłać dokumenty celne/transportowe elektronicznie przez panel klienta na globkurier.pl.', 'globkurier'),
		];
	}

	public function renderSection($order = null, $defaultSenderData = null, $receiverData = null): void
	{
		if (! current_user_can(self::CAPABILITY)) {
			return;
		}
		?>
		<div id="globkurier-customs-section" class="globkurier-customs-section" style="display:none;">
			<h3 class="globkurier-customs-section__title"><?php echo esc_html__('Odprawa Celna', 'globkurier'); ?></h3>
			<div class="globkurier-customs-section__row">
				<label class="globkurier-customs-section__label"><?php echo esc_html__('Uzupełnij deklarację celną', 'globkurier'); ?></label>
				<div class="globkurier-customs-section__control">
					<button type="button" class="button globkurier-customs-edit-btn" id="globkurier-customs-edit-btn">
						<?php echo esc_html__('Edytuj deklarację celną', 'globkurier'); ?>
					</button>
					<p class="globkurier-customs-required-msg" id="globkurier-customs-required-msg" style="display:none;">
						<?php echo esc_html__('Pole jest wymagane', 'globkurier'); ?>
					</p>
				</div>
			</div>
			<div class="globkurier-customs-config-error" id="globkurier-customs-config-error" style="display:none;">
				<p><?php echo esc_html__('Nie udało się sprawdzić, czy dla tej przesyłki wymagana jest deklaracja celna.', 'globkurier'); ?></p>
				<button type="button" class="button" id="globkurier-customs-config-retry"><?php echo esc_html__('Spróbuj ponownie', 'globkurier'); ?></button>
			</div>
			<input type="hidden" id="globkurier-customs-data" value="">
		</div>
		<?php
	}

	public function renderModal(): void
	{
		if (! $this->isOrderPage() || ! current_user_can(self::CAPABILITY)) {
			return;
		}

		include __DIR__ . '/templates/customs-modal.php';
	}

	public function ajaxGetConfig(): void
	{
		$this->verify(self::NONCE_CONFIG);

		$data            = $this->requestData();
		$senderCountry   = sanitize_text_field($data['senderCountry'] ?? '');
		$receiverCountry = sanitize_text_field($data['receiverCountry'] ?? '');
		$carrierName     = sanitize_text_field($data['carrierName'] ?? '');

		global $globKurier;
		$result = $globKurier->customs()->getConfig($senderCountry, $receiverCountry, $carrierName);

		wp_send_json_success($result);
	}

	public function ajaxFetchHsCode(): void
	{
		$this->verify(self::NONCE_HSCODE);

		$data              = $this->requestData();
		$receiverCountryId = sanitize_text_field($data['receiverCountryId'] ?? '');
		$itemDescription   = sanitize_text_field($data['itemDescription'] ?? '');

		global $globKurier;
		$hsCode = $globKurier->customs()->fetchHsCode($receiverCountryId, $itemDescription);

		wp_send_json_success($hsCode);
	}

	public function ajaxImportItems(): void
	{
		$this->verify(self::NONCE_IMPORT);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce i uprawnienia sprawdza $this->verify() linie wyzej.
		$orderId = absint($_POST['data']['orderId'] ?? 0);

		if (! $orderId) {
			wp_send_json_error(['message' => __('Brak identyfikatora zamówienia.', 'globkurier')]);
		}

		if (! UDIGroup_Helper::canProcessOrder($orderId)) {
			wp_send_json_error(['message' => 'Forbidden']);
		}

		global $globKurier;
		$commodities = $globKurier->customs()->importOrderItems($orderId);

		wp_send_json_success(['commodities' => $commodities]);
	}

	public function ajaxTranslate(): void
	{
		$this->verify(self::NONCE_TRANSLATE);

		$data   = $this->requestData();
		$text   = sanitize_text_field($data['text'] ?? '');
		$source = sanitize_text_field($data['sourceLanguage'] ?? 'pl');
		$target = sanitize_text_field($data['targetLanguage'] ?? 'en');

		$allowed = ['pl', 'en'];

		if ($text === '' || ! in_array($source, $allowed, true) || ! in_array($target, $allowed, true) || $source === $target) {
			wp_send_json_error(['message' => __('Nieprawidłowe dane tłumaczenia.', 'globkurier')]);
		}

		global $globKurier;
		$translated = $globKurier->customs()->translate($text, $source, $target);

		if ($translated === null) {
			wp_send_json_error(['message' => __('Tłumaczenie nie powiodło się. Spróbuj ponownie.', 'globkurier')]);
		}

		wp_send_json_success(['text' => $translated]);
	}

	/**
	 * $_POST['data'] bez ukosnikow WP (inaczej "Men's" trafia do API jako "Men\'s"), po sanitize_text_field; zawsze tablica.
	 */
	private function requestData(): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce i uprawnienia sprawdza $this->verify() na poczatku kazdego handlera.
		$data = map_deep(wp_unslash($_POST['data'] ?? []), 'sanitize_text_field');

		return is_array($data) ? $data : [];
	}

	private function verify(string $nonceAction): void
	{
		$nonce = sanitize_text_field(wp_unslash($_POST['data']['nonce'] ?? $_POST['nonce'] ?? ''));

		if (! wp_verify_nonce($nonce, $nonceAction)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
		}

		if (! current_user_can(self::CAPABILITY)) {
			wp_send_json_error(['message' => 'Forbidden']);
		}
	}
}

(new Customs())->init();
