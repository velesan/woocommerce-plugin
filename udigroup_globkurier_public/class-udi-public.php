<?php

namespace udigroup_globkurier;

class UDIGroup_Public{
	
	private $plugin_name;
	
	private $version;
	
	public function __construct( $plugin_name, $version ){
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}
	
	/**
	 * Bufor wyjscia tylko na stronach panelu, ktore po wyrenderowaniu naglowka admina wysylaja naglowki
	 * (PDF etykiet/protokolow z ob_clean(), przekierowania w ustawieniach). Wczesniej dla kazdego zadania, takze sklepu.
	 */
	public function do_output_buffer(){
		if( ! is_admin() || wp_doing_ajax() ){
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametr adresu ekranu panelu, tylko decyzja o buforze.
		$page = sanitize_text_field( wp_unslash( $_GET[ 'page' ] ?? '' ) );

		if( strpos( $page, 'globkurier' ) === 0 || $page === 'wc-settings' ){
			ob_start();
		}
	}
	
	public function enqueue_styles(){
		
		global $globKurier;

		if(!$globKurier->isAnyPickupPointActive()){
			return;
		}
		
		wp_enqueue_style( $this->plugin_name, UDIGroup_Helper::getPublicUrl( 'css/udi-public.css' ), [], $this->version, 'all' );
	}
	
	public function register_styles(){
		wp_register_style( $this->plugin_name, UDIGroup_Helper::getPublicUrl( 'css/udi-public.css' ), [], $this->version, 'all' );
	}
	
	public function enqueue_scripts(){
		global $globKurier;
		
		if(!$globKurier->isAnyPickupPointActive()){
			return;
		}
		
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		
		$googleMapApiKey = $globKurier->settings('googleMapApiKey');
		
		if(!empty($googleMapApiKey)){
			wp_enqueue_script( $this->plugin_name.'_map', 'https://maps.googleapis.com/maps/api/js?key='.$googleMapApiKey, [], $this->version, 'all');

			// Mapa punktow w checkoutcie klasycznym renderuje sie we fragmencie odswiezanym AJAX-em (update_order_review),
			// gdzie wp_enqueue_script juz nie dziala - biblioteka musi byc na stronie od poczatku.
			// Ten sam uchwyt i plik co w inpostMapAjax.php / ruchMapWithAjaxSearch.php, wiec WP doda go tylko raz.
			if( function_exists( 'is_checkout' ) && is_checkout() ){
				wp_enqueue_script( 'markerclusterer', UDIGroup_Helper::getAdminUrl( 'js/markers/markerclusterer_compiled.js' ), [], '1.0', true );
			}
		}

		$data = [
			'ajaxUrl' => UDIGroup_Helper::getAjaxUrl(),
            'globkurier_get_inpost_points_nonce'   => wp_create_nonce( 'globkurier_get_inpost_points_nonce' ),
            'globkurier_get_inpost_points_select2_nonce'   => wp_create_nonce( 'globkurier_get_inpost_points_select2_nonce' ),
            'globkurier_get_ruch_points_nonce'   => wp_create_nonce( 'globkurier_get_ruch_points_nonce' ),
            'globkurier_save_ruch_points_session_nonce'   => wp_create_nonce( 'globkurier_save_ruch_points_session_nonce' ),
            'globkurier_save_inpost_points_session_nonce'   => wp_create_nonce( 'globkurier_save_inpost_points_session_nonce' ),
		];
		
		wp_enqueue_script( $this->plugin_name, UDIGroup_Helper::getPublicUrl( 'js/udi-public.js' ), [], $this->version, 'all' );
		wp_localize_script( $this->plugin_name, 'data', $data );
	}
	
	public function register_scripts(){
		wp_register_script( $this->plugin_name, UDIGroup_Helper::getPublicUrl( 'js/udi-public.js' ), [], $this->version, 'all' );
	}
	
	public function flush_rules(){
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
	
	public function insert_rewrite_rules( $rules ){
		$newrules = [];
		return $newrules + $rules;
	}
	
	public function insert_query_vars( $vars ){
		return $vars;
	}
	
}