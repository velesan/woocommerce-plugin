<?php
/**
 * @wordpress-plugin
 * Plugin Name:       globkurier.pl – Integracja z WooCommerce
 * Description:       Integracja WooCommerce z globkurier.pl
 * Version:           2.7.1
 * Author:            WP OPIEKA
 * Author URI:        https://wp-opieka.pl/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP:      7.4
 * Text Domain: globkurier
 * Domain Path: /udigroup_globkurier_languages
 */

namespace udigroup_globkurier;

if( ! defined( 'WPINC' ) ){
	die;
}

class UDIGroup_GLOBKURIER_INIT{
	
	public static function getHelperDir(){
		
		return UDIGroup_HELPER_GLOBKURIER_DIR;
	}
	
	public static function getPluginName(){
		
		return UDIGroup_GLOBKURIER_NAME;
	}
	
	public function __construct(){
		$pluginConstPrefix    = 'UDIGLOBKURIER';
		$directoryConstPrefix = 'udigroup_globkurier_';
		
		define( 'UDIGroup_GLOBKURIER_DIRECTORY_PREFIX', $directoryConstPrefix );
		define( 'UDIGroup_GLOBKURIER_NAME', 'udigroup-globkurier' );
		define( 'UDIGroup_GLOBKURIER_TEXTDOMAIN', 'globkurier' );
		
		define( 'UDIGroup_GLOBKURIER_PLUGIN_DIR_URL', plugin_dir_url(__FILE__) );
		define( 'UDIGroup_GLOBKURIER_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__) );
		
		define( 'UDIGroup_HELPER_GLOBKURIER_DIR', plugin_dir_path( __FILE__ ) . UDIGroup_GLOBKURIER_DIRECTORY_PREFIX . 'includes/class-udi-helper.php' );
		
		define( 'UDIGroup_GLOBKURIER_VERSION', '2.7.1' );

		if ( ! defined( 'UDIGroup_GLOBKURIER_CUSTOMS_ENABLED' ) ) {
			define( 'UDIGroup_GLOBKURIER_CUSTOMS_ENABLED', false );
		}
		
		global $wpdb;
		define( 'UDIGroup_GLOBKURIER_DB_PREFIX', $wpdb->prefix . 'globkurier_' );
		
		// Wtyczka ma wlasna integracje z blokowym koszykiem/checkoutem (blocks/).
		// HPOS: meta zamowien idzie przez API WC_Order, historia przesylek czyta wp_postmeta i wc_orders_meta
		// (UDIGroup_ShipmentHistory), lista zamowien i akcje masowe sa podpiete pod oba ekrany.
		add_action( 'before_woocommerce_init', static function (){
			if( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ){
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );

		// Jednorazowo: duze listy punktow (gdy trzymane w bazie) nie musza sie ladowac przy kazdym zadaniu WP.
		add_action( 'admin_init', static function (){
			if( get_option( 'globkurier_points_autoload_off' ) || ! function_exists( 'wp_set_option_autoload_values' ) ){
				return;
			}
			wp_set_option_autoload_values( [ 'udigroup_gkInPostPoints' => false, 'udigroup_gkRuchPoints' => false ] );
			update_option( 'globkurier_points_autoload_off', 1, false );
		} );
		
		register_activation_hook( __FILE__, [ $this, 'activate_udi' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_udi' ] );
		
		require plugin_dir_path( __FILE__ ) . UDIGroup_GLOBKURIER_DIRECTORY_PREFIX . 'includes/class-udi.php';
		
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
		
		$timestamp = wp_next_scheduled( 'updateInpostPoints' );
		wp_unschedule_event( $timestamp, 'updateInpostPoints' );
	
		$timestamp2 = wp_next_scheduled( 'updateInpostPoints2' );
		wp_unschedule_event( $timestamp2, 'updateInpostPoints2' );
		
		add_filter( 'plugin_row_meta', [ $this, 'prefix_append_support_and_faq_links' ], 10, 4 );
		
		$plugin = new UDIGroup_plugin();
		$plugin->run();

		// Jednorazowe skopiowanie historii przesylek z wp_postmeta do wc_orders_meta (tylko przy HPOS, w tle).
		UDIGroup_ShipmentHistory::registerHooks();

		// Jednorazowe usuniecie pustych ghost postow tworzonych przez stare wersje (w tle).
		UDIGroup_GhostCleanup::registerHooks();

		if (is_admin()) {
			require_once plugin_dir_path(__FILE__) . 'bulk_send/BulkSend.php';

			if ( UDIGroup_GLOBKURIER_CUSTOMS_ENABLED ) {
				require_once plugin_dir_path(__FILE__) . 'udigroup_globkurier_admin/customs/Customs.php';
			}
		}
		
	}
	
	function prefix_append_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ){
		
		global $globKurier;
		
		if( strpos( $plugin_file_name, basename( __FILE__ ) ) ){
			$links_array[] = '<a href="' . $globKurier->getSettingsUrl() . '">' . __( 'Ustawienia', 'globkurier' ) . '</a>';
		}
		
		return $links_array;
	}
	
	public function activate_udi(){
		require_once UDIGroup_Helper::getInlcudesPath( 'class-udi-activator.php' );
		UDIGroup_Activator::activate();
	}
	
	public function deactivate_udi(){
		require_once UDIGroup_Helper::getInlcudesPath( 'class-udi-deactivator.php' );
		UDIGroup_Deactivator::deactivate();
	}
	
	public function load_plugin_textdomain(){
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- tlumaczenia dolaczone do wtyczki (udigroup_globkurier_languages), nie z translate.wordpress.org.
		load_plugin_textdomain( UDIGroup_GLOBKURIER_TEXTDOMAIN, false, UDIGroup_GLOBKURIER_NAME . '/' . UDIGroup_GLOBKURIER_DIRECTORY_PREFIX . 'languages' );
	}
}

new UDIGroup_GLOBKURIER_INIT();
