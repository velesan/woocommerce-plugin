<?php

namespace udigroup_globkurier;

class UDIGroup_Admin
{
	private $plugin_name;
	
	private $version;
	
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}
	
	/**
	 * Ekrany, na ktorych wtyczka czegos uzywa: zamowienia (lista i edycja, HPOS i klasyczne),
	 * ustawienia WooCommerce oraz wlasne strony GlobKuriera.
	 */
	private function isPluginScreen(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametr adresu ekranu panelu (wybor assetow/szablonu z listy dozwolonych), bez zmiany stanu.
		$page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
		
		if (strpos($page, 'globkurier') === 0 || in_array($page, ['wc-settings', 'wc-orders'], true)) {
			return true;
		}
		
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		
		if (! $screen) {
			return false;
		}
		
		if ($screen->post_type === 'shop_order') {
			return true;
		}
		
		if (in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-settings'], true)) {
			return true;
		}
		
		return strpos($screen->id, 'globkurier') !== false;
	}
	
	public function enqueue_styles()
	{
		// Na pozostalych ekranach tylko lekki CSS - zawiera m.in. styl ikony w menu admina.
		if (! $this->isPluginScreen()) {
			wp_enqueue_style($this->plugin_name, UDIGroup_Helper::getAdminUrl('css/udi-admin.css'), [], $this->version, 'all');

			return;
		}

		wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url().'/assets/css/admin.css', [], $this->version);

		wp_enqueue_style($this->plugin_name.'_datatables', UDIGroup_Helper::getAdminUrl('css/datatables/datatables.min.css'), [], $this->version, 'all');
		wp_enqueue_style($this->plugin_name, UDIGroup_Helper::getAdminUrl('css/udi-admin.css'), [], $this->version, 'all');
		wp_enqueue_style($this->plugin_name.'_select2', UDIGroup_Helper::getAdminUrl('css/select2/select2.min.css'), [], $this->version, 'all');
	}
	
	public function enqueue_scripts()
	{
		if (! $this->isPluginScreen()) {
			return;
		}
		
		wp_enqueue_script('jquery-ui-dialog');
		wp_enqueue_script('jquery-ui-datepicker');
		
		$data = [
			'ajaxUrl'                                        => UDIGroup_Helper::getAjaxUrl(),
			'globkurier_create_order_nonce'                  => wp_create_nonce('globkurier_create_order_nonce'),
			'globkurier_update_person_to_address_book_nonce' => wp_create_nonce('globkurier_update_person_to_address_book_nonce'),
			'globkurier_add_person_to_address_book_nonce'    => wp_create_nonce('globkurier_add_person_to_address_book_nonce'),
			'globkurier_get_person_nonce'                    => wp_create_nonce('globkurier_get_person_nonce'),
			'globkurier_get_current_status_nonce'            => wp_create_nonce('globkurier_get_current_status_nonce'),
			'globkurier_order_nonce'                         => wp_create_nonce('globkurier_order_nonce'),
			'globkurier_get_custom_required_fields_nonce'    => wp_create_nonce('globkurier_get_custom_required_fields_nonce'),
			'globkurier_get_payments_nonce'                  => wp_create_nonce('globkurier_get_payments_nonce'),
			'globkurier_get_price_nonce'                     => wp_create_nonce('globkurier_get_price_nonce'),
			'globkurier_get_first_pickup_day_nonce'          => wp_create_nonce('globkurier_get_first_pickup_day_nonce'),
			'globkurier_get_pickup_time_ranges_nonce'        => wp_create_nonce('globkurier_get_pickup_time_ranges_nonce'),
			'globkurier_get_product_addon_fields_nonce'      => wp_create_nonce('globkurier_get_product_addon_fields_nonce'),
			'globkurier_get_product_addons_nonce'            => wp_create_nonce('globkurier_get_product_addons_nonce'),
			'globkurier_get_products_nonce'                  => wp_create_nonce('globkurier_get_products_nonce'),
			'globkurier_get_inpost_points_select2_nonce'     => wp_create_nonce('globkurier_get_inpost_points_select2_nonce'),
			'globkurier_get_ruch_points_nonce'               => wp_create_nonce('globkurier_get_ruch_points_nonce'),
			'globkurier_get_ruch_points_select2_nonce'       => wp_create_nonce('globkurier_get_ruch_points_select2_nonce'),
			'globkurier_save_ruch_points_session_nonce'      => wp_create_nonce('globkurier_save_ruch_points_session_nonce'),
			'globkurier_save_inpost_points_session_nonce'    => wp_create_nonce('globkurier_save_inpost_points_session_nonce'),
			'globkurier_update_points_nonce'                 => wp_create_nonce('globkurier_update_points_nonce'),
			'globkurier_get_crossborder_terminals_nonce'     => wp_create_nonce('globkurier_get_crossborder_terminals_nonce'),
		];
		
		wp_enqueue_script($this->plugin_name.'_datatables', UDIGroup_Helper::getAdminUrl('js/datatables/datatables.min.js'), ['jquery'], $this->version, false);
		wp_enqueue_script($this->plugin_name.'_select2', UDIGroup_Helper::getAdminUrl('js/select2/select2.min.js'), ['jquery'], $this->version, false);
		
		
		$is_order_page = false;
		global $current_screen;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- parametry adresu ekranu panelu tylko do wyboru assetow, bez zmiany stanu.
		$page    = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
		$action  = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
		$section = sanitize_text_field(wp_unslash($_GET['section'] ?? ''));
		// phpcs:enable

		if ($current_screen && $current_screen->id === 'woocommerce_page_wc-orders' && $action === 'edit') {
			$is_order_page = true;
		}

		if ($current_screen && $current_screen->id === 'shop_order' &&
			$current_screen->base === 'post') {
			$is_order_page = true;
		}

		if (in_array($page, ['globkurier_ship_order', 'globkurier_all_orders', 'globkurier_protocols'], true)) {
			$is_order_page = true;
		}

		if ($page === 'wc-settings' && $section === 'globkurier') {
			$is_order_page = true;
		}
		
		if ($is_order_page) {
			wp_register_script($this->plugin_name.'_udi_admin_script', UDIGroup_Helper::getAdminUrl('js/udi-admin.js'), [], $this->version, true);
			
			wp_localize_script($this->plugin_name.'_udi_admin_script', 'udi_admin_script', [
				'datatables_lang_pl' => UDIGroup_GLOBKURIER_PLUGIN_DIR_URL.'udigroup_globkurier_admin/lang/datatables/pl.json',
			]);
		}
		
		wp_enqueue_script($this->plugin_name.'_udi_admin_script');
		
		wp_localize_script($this->plugin_name.'_udi_admin_script', 'data', $data);
	}
	
	public function load_admin_menu()
	{
		require_once UDIGroup_Helper::getAdminPath('partials/udi-admin-header.php');
		
		$this->load_admin_page_template();
		
		require_once UDIGroup_Helper::getAdminPath('partials/udi-admin-footer.php');
	}
	
	private function load_admin_page_template()
	{
		// Strona panelu => staly plik szablonu; sciezka nie jest budowana z danych zadania.
		$templates = [
			'globkurier_settings'   => 'partials/udi-admin-globkurier_settings.php',
			'globkurier_all_orders' => 'partials/udi-admin-globkurier_all_orders.php',
			'globkurier_ship_order' => 'partials/udi-admin-globkurier_ship_order.php',
			'globkurier_protocols'  => 'partials/udi-admin-globkurier_protocols.php',
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametr adresu ekranu panelu (wybor szablonu z listy dozwolonych), bez zmiany stanu.
		$page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));

		// Parametr tylko porownujemy - sciezka pochodzi zawsze ze stalej wartosci mapy, nie z indeksu z zadania.
		foreach ($templates as $slug => $template) {
			if ($slug === $page) {
				require_once UDIGroup_Helper::getAdminPath($template);
				break;
			}
		}
	}
}