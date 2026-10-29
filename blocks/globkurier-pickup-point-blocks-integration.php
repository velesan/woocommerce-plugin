<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define('GlobkurierPickupPoint_VERSION', '0.1.0');

class GlobkurierPickupPoint_Blocks_Integration implements IntegrationInterface
{
	
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name()
	{
		return 'globkurier-pickup-point';
	}
	
	public function initialize()
	{
		$this->register_newsletter_block_frontend_scripts();
		$this->register_newsletter_block_editor_scripts();
		$this->register_newsletter_block_editor_styles();
		$this->register_main_integration();
	}
	
	public function register_main_integration()
	{
		$script_path = '/build/index.js';
		$style_path  = '/build/style-globkurier-pickup-point-block.css';
		
		$script_url = plugins_url($script_path, __FILE__);
		$style_url  = plugins_url($style_path, __FILE__);
		
		$script_asset_path = dirname(__FILE__).'/build/index.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => $this->get_file_version($script_path),
			];
		
		global $globKurier;
		if($globKurier->isAnyPickupPointActive()){
			wp_enqueue_style(
				'globkurier-pickup-point-blocks-integration',
				$style_url,
				[],
				$this->get_file_version($style_path)
			);
		}
		
		wp_register_script(
			'globkurier-pickup-point-blocks-integration',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);
		wp_set_script_translations(
			'globkurier-pickup-point-blocks-integration',
			'globkurier-pickup-point',
			dirname(__FILE__).'/languages'
		);
	}
	
	public function get_script_handles()
	{
		return ['globkurier-pickup-point-blocks-integration', 'globkurier-pickup-point-block-frontend'];
	}
	
	public function get_editor_script_handles()
	{
		return ['globkurier-pickup-point-blocks-integration', 'globkurier-pickup-point-block-editor'];
	}
	
	public function get_script_data()
	{
		$data = [];
		
		return $data;
	}
	
	public function register_newsletter_block_editor_styles()
	{
		global $globKurier;
		if(!$globKurier->isAnyPickupPointActive()){
			return;
		}
		
		$style_path = '/build/style-globkurier-pickup-point-block.css';
		
		$style_url = plugins_url($style_path, __FILE__);
		wp_enqueue_style(
			'globkurier-pickup-point-block',
			$style_url,
			[],
			$this->get_file_version($style_path)
		);
	}
	
	public function register_newsletter_block_editor_scripts()
	{
		$script_path       = '/build/globkurier-pickup-point-block.js';
		$script_url        = plugins_url($script_path, __FILE__);
		$script_asset_path = dirname(__FILE__).'/build/globkurier-pickup-point-block.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => $this->get_file_version($script_asset_path),
			];
		
		wp_register_script(
			'globkurier-pickup-point-block-editor',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);
		
		wp_set_script_translations(
			'globkurier-pickup-point-newsletter-block-editor',
			'globkurier-pickup-point',
			dirname(__FILE__).'/languages'
		);
	}
	
	public function register_newsletter_block_frontend_scripts()
	{
		$script_path       = '/build/globkurier-pickup-point-block-frontend.js';
		$script_url        = plugins_url($script_path, __FILE__);
		$script_asset_path = dirname(__FILE__).'/build/globkurier-pickup-point-block-frontend.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => $this->get_file_version($script_asset_path),
			];
		
		
		$dependencies    = $script_asset[ 'dependencies' ];
		
		global $globKurier;
		$globKurierSettings = $globKurier->settings();
		
		if(!empty($googleMapApiKey) && $globKurier->isAnyPickupPointActive()){
			wp_enqueue_script('markerclusterer', udigroup_globkurier\UDIGroup_Helper::getAdminUrl('js/markers/markerclusterer_compiled.js'), [], '1.0', true);
			$dependencies [] = 'markerclusterer';
		}
		
		wp_register_script(
			'globkurier-pickup-point-block-frontend',
			$script_url,
			$dependencies,
			$script_asset[ 'version' ],
			true
		);
		wp_set_script_translations(
			'globkurier-pickup-point-block-frontend',
			'globkurier',
			dirname(__FILE__).'/languages'
		);
		
		$WcSession = WC() ? WC()->session : null;
		
		$gkSettingsLocalized = [
			'googleMapApiKey' => $globKurierSettings[ 'googleMapApiKey' ] ?? '',
			'ajax_url'        => admin_url('admin-ajax.php'),
			'providers'       => [
				'inpost' => [
					'label'           => 'Inpost',
					'active'          => (bool)($globKurierSettings[ 'inpost_active' ] ?? false),
					'method'          => $globKurierSettings[ 'inpost_method' ] ?? '',
					'renderFunction'  => 'inpostMapBlock',
					'actions'         => [
						'search'         => 'globkurierGetInpostPointsSelect2',
						'saveSession'    => 'globkurierSaveInpostPointsSession',
						'getSessionData' => 'globkurierGetPointsSession',
					],
					'nonce'           => [
						'saveSession' => esc_attr(wp_create_nonce('globkurier_save_inpost_points_session_nonce')),
					],
					'fields'          => [
						'idField'     => 'globkurier_inpost_input',
						'valueField'  => 'globkurier_inpost_input_hidden_value',
						'selectField' => 'globkurier_inpost_input_value',
						
						'point_lat'          => 'globkurier_inpost_input_point_lat',
						'point_long'         => 'globkurier_inpost_input_point_long',
						'point_city'         => 'globkurier_inpost_input_point_city',
						'point_address'      => 'globkurier_inpost_input_point_address',
						'point_openingHours' => 'globkurier_inpost_input_point_openingHours',
					],
					'session'         => [
						'point_id'           => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_id') ?? '') : '',
						'point_val'          => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_value') ?? '') : '',
						'point_lat'          => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_latitude') ?? '') : '',
						'point_long'         => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_longitude') ?? '') : '',
						'point_city'         => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_city') ?? '') : '',
						'point_address'      => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_address') ?? '') : '',
						'point_openingHours' => $WcSession ? ($WcSession->get('globkurier_inpost_selected_point_openingHours') ?? '') : '',
					],
					'wrapper_preffix' => 'globkurier-pickup-wrapper_',
					'map'             => [
						'hasMap' => true,
					],
				],
				'ruch'   => [
					'label'           => 'Ruch Orlen',
					'active'          => (bool)($globKurierSettings[ 'ruch_active' ] ?? false),
					'method'          => $globKurierSettings[ 'ruch_method' ] ?? '',
					'renderFunction'  => 'ruchMapBlock',
					'fields'          => [
						'idField'     => 'globkurier_ruch_input',
						'valueField'  => 'globkurier_ruch_input_hidden_value',
						'selectField' => 'globkurier_ruch_input_value',
						
						'point_lat'          => 'globkurier_ruch_input_point_lat',
						'point_long'         => 'globkurier_ruch_input_point_long',
						'point_city'         => 'globkurier_ruch_input_point_city',
						'point_address'      => 'globkurier_ruch_input_point_address',
						'point_openingHours' => 'globkurier_ruch_input_point_openingHours',
					],
					'actions'         => [
						'search'         => 'globkurierGetRuchPointsSelect2',
						'saveSession'    => 'globkurierSaveRuchPointsSession',
						'getSessionData' => 'globkurierGetPointsSession',
					],
					'nonce'           => [
						'saveSession' => esc_attr(wp_create_nonce('globkurier_save_ruch_points_session_nonce')),
					],
					'session'         => [
						'point_id'           => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_id') ?? '') : '',
						'point_val'          => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_value') ?? '') : '',
						'point_lat'          => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_latitude') ?? '') : '',
						'point_long'         => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_longitude') ?? '') : '',
						'point_city'         => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_city') ?? '') : '',
						'point_address'      => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_address') ?? '') : '',
						'point_openingHours' => $WcSession ? ($WcSession->get('globkurier_ruch_selected_point_openingHours') ?? '') : '',
					],
					'wrapper_preffix' => 'globkurier-pickup-wrapper_',
					'map'             => [
						'hasMap' => true,
					],
				],
			],
			
			'languages' => apply_filters('globkurier_block_languages', [
				'findPointButtonText'   => __('Znajdź punkt odbioru', 'globkurier'),
				'selectPointMarkerText' => __('WYBIERZ', 'globkurier'),
				'select2SearchText'     => __('Wpisz miasto', 'globkurier'),
			]),
		];
		
		foreach ($globKurierSettings as $key => $_method) {
			if (strpos($key, 'extra_pickup_point_') === 0) {
				$carrierId = \str_replace('extra_pickup_point_', '', $key);
				
				$gkSettingsLocalized[ 'providers' ][ $carrierId ] = [
					'label'           => $carrierId,
					'carrierId'       => $carrierId,
					'active'          => true,
					'method'          => $_method,
					'productId'       => $globKurier->extraPickupPoints()->findProductIdForCarrierId($carrierId),
					'actions'         => [
						'search'         => 'globkurierGetExtraPickupsPointsSelect2',
						'saveSession'    => 'globkurierSaveExtraPickupsPointsSession',
						'getSessionData' => 'globkurierGetPointsSession',
					],
					'nonce'           => [
						'saveSession' => esc_attr(wp_create_nonce('globkurier_save_extra_pickup_points_session_nonce')),
					],
					'fields'          => [
						'idField'     => 'globkurier_'.$key.'_input',
						'valueField'  => 'globkurier_'.$key.'_input_hidden_value',
						'selectField' => 'globkurier_'.$key.'_input_value',
						
						'point_lat'          => 'globkurier_'.$key.'_input_point_lat',
						'point_long'         => 'globkurier_'.$key.'_input_point_long',
						'point_city'         => 'globkurier_'.$key.'_input_point_city',
						'point_address'      => 'globkurier_'.$key.'_input_point_address',
						'point_openingHours' => 'globkurier_'.$key.'_input_point_openingHours',
					],
					'session'         => [
						'point_id'           => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_id') ?? '') : '',
						'point_val'          => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_value') ?? '') : '',
						'point_lat'          => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_latitude') ?? '') : '',
						'point_long'         => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_longitude') ?? '') : '',
						'point_city'         => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_city') ?? '') : '',
						'point_address'      => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_address') ?? '') : '',
						'point_openingHours' => $WcSession ? ($WcSession->get('globkurier_'.$carrierId.'_selected_point_openingHours') ?? '') : '',
					],
					'wrapper_preffix' => 'globkurier-pickup-wrapper_',
					'map'             => [
						'hasMap' => true,
					],
				];
			}
		}
		
		wp_localize_script(
			'globkurier-pickup-point-block-frontend',
			'globKurier_settings',
			$gkSettingsLocalized
		);
	}
	
	protected function get_file_version($file)
	{
		if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
			return filemtime($file);
		}
		
		return GlobkurierPickupPoint_VERSION;
	}
}
