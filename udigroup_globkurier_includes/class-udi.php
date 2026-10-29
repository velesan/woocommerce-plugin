<?php

namespace udigroup_globkurier;

if (! defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class UDIGroup_plugin
{
	protected $loader;
	
	protected $plugin_name;
	
	protected $version;
	
	public function __construct()
	{
		$this->plugin_name = UDIGroup_GLOBKURIER_INIT::getPluginName();
		
		if (defined('UDIGroup_GLOBKURIER_VERSION')) {
			$this->version = UDIGroup_GLOBKURIER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		
		$this->load_dependencies();
		
		global $globKurier;
		$globKurier = new GlobKurier();
		
		(new Blocks())->register();
		
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/** Odswiezanie list punktow w tle (WP-Cron) - planowane przy wygaslym cache, zob. getAllPointsFromInpost(). */
	private function define_cron_hooks()
	{
		add_action(GlobKurierInpost::REFRESH_HOOK, static function (){
			global $globKurier;
			UDIGroup_Helper::extendTimeLimit();
			$globKurier->inpost()->getAllPointsFromInpost(true);
		});

		add_action(GlobKurierRuch::REFRESH_HOOK, static function (){
			global $globKurier;
			UDIGroup_Helper::extendTimeLimit();
			$globKurier->ruch()->getAllPoints(true);
		});
	}
	
	private function load_dependencies()
	{
		require_once UDIGroup_GLOBKURIER_INIT::getHelperDir();

		require_once UDIGroup_Helper::getInlcudesPath('class-udi-shipment-history.php');
		require_once UDIGroup_Helper::getInlcudesPath('class-udi-ghost-cleanup.php');

		require_once UDIGroup_Helper::getInlcudesPath('class-udi-blocks.php');
		require_once UDIGroup_Helper::getInlcudesPath('class-udi-loader.php');
		
		require_once UDIGroup_Helper::getInlcudesPath('class-udi-i18n.php');
		
		require_once UDIGroup_Helper::getAdminPath('class-udi-admin.php');
		
		require_once UDIGroup_Helper::getAdminPath('ajax/class-admin-ajax.php');
		
		require_once UDIGroup_Helper::getPublicPath('ajax/class-public-ajax.php');
		
		require_once UDIGroup_Helper::getPublicPath('class-udi-public.php');
		
		require_once UDIGroup_Helper::getPublicPath('class-udi-shortcodes.php');
		
		require_once UDIGroup_Helper::getAdminPath('woocommerce/WoocommerceAddons.php');
		
		require_once UDIGroup_Helper::getAdminPath('globkurier/GlobKurierApi.php');
		
		require_once UDIGroup_Helper::getAdminPath('globkurier/GlobKurier.php');
		
		$this->loader = new UDIGroup_Loader();
	}
	
	private function set_locale()
	{
		$plugin_i18n = new UDIGroup_i18n();
		$this->loader->add_action('init', $plugin_i18n, 'load_plugin_textdomain');
	}
	
	private function define_admin_hooks()
	{
		$plugin_admin = new UDIGroup_Admin($this->get_plugin_name(), $this->get_version());
		$ajax_admin   = new UDIGroup_Admin_Ajax();
		$ajax_public  = new UDIGroup_Public_Ajax();
		
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		
		$this->loader->add_menu_page($component = $plugin_admin, $pageTitle = fn() => __('GlobKurier', 'globkurier'), $menuTitle = fn() => __('GlobKurier', 'globkurier'), $menuSlug = 'globkurier',
			$iconUrl = UDIGroup_Helper::getAdminUrl('img/icon-new.png'), $position = 5, $capability = 'manage_options_ghost');

		$this->loader->add_submenu_page($component = $plugin_admin, $parentSlug = 'globkurier', $pageTitle = fn() => __('GlobKurier - Historia przesyłek', 'globkurier'), $menuTitle = fn() => __('Historia przesyłek', 'globkurier'),
			$capability = 'manage_options', $menuSlug = 'globkurier_all_orders', $position = 0);
		$this->loader->add_submenu_page($component = $plugin_admin, $parentSlug = 'globkurier', $pageTitle = fn() => __('GlobKurier - Protokoły', 'globkurier'), $menuTitle = fn() => __('Protokoły', 'globkurier'),
			$capability = 'manage_options', $menuSlug = 'globkurier_protocols', $position = 1);
		$this->loader->add_submenu_page($component = $plugin_admin, $parentSlug = 'globkurier', $pageTitle = fn() => __('GlobKurier - Nadaj przez GlobKuriera', 'globkurier'),
			$menuTitle = fn() => __('Nadaj przez GlobKuriera', 'globkurier'), $capability = 'manage_options', $menuSlug = 'globkurier_ship_order', $position = 2);
		$this->loader->add_submenu_page($component = $plugin_admin, $parentSlug = 'globkurier', $pageTitle = fn() => __('GlobKurier - Konfiguracja', 'globkurier'), $menuTitle = fn() => __('Konfiguracja', 'globkurier'),
			$capability = 'manage_options', $menuSlug = 'globkurier_settings', $position = 3);
		
		$wc_addons = new WoocommerceAddons();
		$this->loader->add_action('init', $wc_addons, 'init');
		
		$this->loader->add_action('init', $ajax_admin, 'init');
		add_action('admin_notices', [GlobKurierApi::class, 'connectionNotice']);
		$this->loader->add_action('init', $ajax_public, 'init');
	}
	
	private function define_public_hooks()
	{
		$plugin_public = new UDIGroup_Public($this->get_plugin_name(), $this->get_version());
		
		$this->loader->add_action('init', $plugin_public, 'do_output_buffer');
		
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'register_scripts');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'register_styles');
	}
	
	public function run()
	{
		$this->loader->run();
	}
	
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}
	
	public function get_loader()
	{
		return $this->loader;
	}
	
	public function get_version()
	{
		return $this->version;
	}
	
}