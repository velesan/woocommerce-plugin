<?php

namespace udigroup_globkurier;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class BulkSend
{
	/** @var bool|null kolumna "Wysylka" ukryta w Opcjach ekranu biezacego uzytkownika (liczone raz na zadanie) */
	private $shippingColumnHidden = null;

	public function init(): void
	{
		add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_action']);
		add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_action']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		
		add_action('wp_ajax_wpopieka_globkurier_bulk_send_open_modal', [$this, 'open_modal']);

		// Kolumna "Wysylka" mozna wylaczyc w ustawieniach (domyslnie wlaczona, jak dotad).
		$settings = get_option('globkurier', []);
		if (is_array($settings) && isset($settings[ 'order_list_shipping_column' ]) && ! $settings[ 'order_list_shipping_column' ]) {
			return;
		}

		add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_shipping_method_column_hpos']);
		add_filter('manage_edit-shop_order_columns', [$this, 'add_shipping_method_column_legacy']);
		
		add_action('woocommerce_shop_order_list_table_custom_column',  [$this, 'populate_shipping_method_column_hpos'], 10, 2);
		add_action('manage_shop_order_posts_custom_column',  [$this, 'populate_shipping_method_column_legacy'], 10, 2);
		
	}
	
	public function add_bulk_action($actions)
	{
		$actions[ 'nadaj_z_globkurier' ] = 'Nadaj z GlobKurier';
		
		return $actions;
	}
	
	public function enqueue_scripts(): void
	{
		$screen = get_current_screen();
		if (! $screen || ! in_array($screen->id, ['edit-shop_order', 'woocommerce_page_wc-orders'])) {
			return;
		}
		
		$is_order_page = false;
		global $current_screen;
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametr adresu ekranu panelu tylko do wyboru assetow, bez zmiany stanu.
		$action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));

		// HPOS
		if ($current_screen && $current_screen->id === 'woocommerce_page_wc-orders' && $action === 'edit') {
			$is_order_page = true;
		}
		
		// Classic orders
		if ($current_screen && $current_screen->id === 'shop_order' &&
			$current_screen->base === 'post') {
			$is_order_page = true;
		}
		
		
		if($is_order_page){
			return;
		}
		
		$bulkSendScriptPath = plugin_dir_path(__FILE__).'assets/bulk-send.js';
		$bulkSendScriptVersion = file_exists($bulkSendScriptPath) ? filemtime($bulkSendScriptPath) : '1.0';
		wp_enqueue_script('wpopieka_globkurier_bulk_send_script', plugin_dir_url(__FILE__).'/assets/bulk-send.js', ['jquery'], $bulkSendScriptVersion, false);
		wp_localize_script('wpopieka_globkurier_bulk_send_script', 'wpopieka_globkurier_bulk_send_data', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('wpopieka_globkurier_bulk_send'),
			'crossborder_terminals_nonce' => wp_create_nonce('globkurier_get_crossborder_terminals_nonce'),
			'action'   => 'wpopieka_globkurier_bulk_send_open_modal',
		]);
		
		$bulkSendStylePath = plugin_dir_path(__FILE__).'assets/bulk-send.css';
		wp_enqueue_style('wpopieka_globkurier_bulk_send_style', plugin_dir_url(__FILE__).'/assets/bulk-send.css', [], file_exists($bulkSendStylePath) ? filemtime($bulkSendStylePath) : '1.0');
	}
	
	public function open_modal()
	{
		$nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
		
		if ( ! wp_verify_nonce($nonce, 'wpopieka_globkurier_bulk_send')) {
			wp_send_json_error(['message' => 'Invalid nonce']);
		}
		
		if ( ! UDIGroup_Helper::currentUserCanManageShipments()) {
			wp_send_json_error(['message' => 'Brak uprawnień']);
		}
		
		$orderIds = array_filter(array_map('intval', (array)($_POST['orders'] ?? [])));
		
		if(empty($orderIds)){
			wp_send_json_error(['message' => 'Brak wybranych zamówień']);
		}

		// Uprawnienie do kazdego zamowienia partii (nieistniejace sa pomijane nizej).
		foreach ($orderIds as $orderId) {
			if (wc_get_order($orderId) && ! UDIGroup_Helper::canProcessOrder($orderId)) {
				wp_send_json_error(['message' => 'Brak uprawnień do zamówienia #'.$orderId]);
			}
		}

		$orders = array_values(array_filter(array_map(function ($order) {
			return wc_get_order($order);
		}, $orderIds)));
		
		if(empty($orders)){
			wp_send_json_error(['message' => 'Brak wybranych zamówień']);
		}
		
		global $globKurier;
		
		ob_start();
		
		include __DIR__.'/templates/bulk_order_template.php';
		
		$html = ob_get_clean();
		
		wp_send_json_success(['message' => 'success', 'html' => $html, 'carrierData'=>$commonProducts??[]]);
	}
	
	
	
	
	public function add_shipping_method_column_hpos($columns) {
		$new_columns = array();
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ($key === 'order_total') {
				$new_columns['shipping_method'] = __('Wysyłka', 'globkurier');
			}
		}
		return $new_columns;
	}
	
	public function add_shipping_method_column_legacy($columns) {
		$new_columns = array();
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ($key === 'order_total') {
				$new_columns['shipping_method'] = __('Wysyłka', 'globkurier');
			}
		}
		return $new_columns;
	}

	/**
	 * WordPress renderuje tez kolumny ukryte w "Opcjach ekranu" (tylko z klasa hidden) - bez tego ukrycie
	 * kolumny nie oszczedzaloby odczytu metod wysylki (z WPML: zapytanie na kazda pozycje wysylki).
	 */
	private function isShippingColumnHidden(): bool
	{
		if ($this->shippingColumnHidden === null) {
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;

			$this->shippingColumnHidden = $screen && function_exists('get_hidden_columns') && in_array('shipping_method', (array) get_hidden_columns($screen), true);
		}

		return $this->shippingColumnHidden;
	}

	public function populate_shipping_method_column_hpos($column, $order) {
		if ($column === 'shipping_method' && ! $this->isShippingColumnHidden()) {
			if (!is_object($order)) {
				$order = wc_get_order($order);
			}
			
			$shipping_methods = $order->get_shipping_methods();
			if (!empty($shipping_methods)) {
				$method = reset($shipping_methods);
				echo esc_html($method->get_name());
			}
		}
	}
	
	public function populate_shipping_method_column_legacy($column, $post_id) {
		if ($column === 'shipping_method' && ! $this->isShippingColumnHidden()) {
			$order = wc_get_order($post_id);
			
			$shipping_methods = $order->get_shipping_methods();
			if (!empty($shipping_methods)) {
				$method = reset($shipping_methods);
				echo esc_html($method->get_name());
			}
		}
	}
	
	
}

(new BulkSend())->init();