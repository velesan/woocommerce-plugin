<?php

namespace udigroup_globkurier;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierCountries.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierEncrypter.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierUser.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierProduct.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierAddons.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierOrder.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierInpost.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierRuch.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierCustomRequiredFields.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierDocuments.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierExtraPickupPoints.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierCrossborderTerminals.php' );
require_once UDIGroup_Helper::getAdminPath( 'globkurier/GlobKurierCustoms.php' );

require_once UDIGroup_Helper::getAdminPath( 'woocommerce/ShippingMethods.php' );

class GlobKurier{

    public $settings;

    public function __construct(){
        $this->settings = $this->settings();
    }

    public function api(): GlobKurierApi{
        return new GlobKurierApi( $this );
    }

    public function getSettingsUrl(){
        $adminUrl = get_admin_url();
        $suffix   = 'admin.php?page=wc-settings&tab=shipping&section=globkurier';

        return apply_filters( 'globkurier_settings_url', $adminUrl . $suffix );
    }

    public function testConnection( $getCode = FALSE ){

        return $this->api()->testConnection( $getCode );
    }

    public function getConnectionCode(){

        return $this->testConnection( TRUE );
    }

    public function isUserLoggedIn( $die = FALSE ){
        $username = $this->settings( 'username' );
        $password = $this->settings( 'password' );

        if( empty( $username ) || empty( $password ) ){
            $this->addErrorAdminNotice( __( 'Proszę skonfigurować połączenie z API GlobKurier', 'globkurier' ) . "<br><a href='{$this->getSettingsUrl()}'>" . __( 'Konfiguracja', 'globkurier' ) .'</a>', 'error', $die );
            return FALSE;
        }

        // Wazny token z cache (ok. 20 h) = zalogowany, bez zapytan do API. Wczesniej kazde wywolanie robilo GET countries
        // i POST auth/login. Zmiana loginu lub hasla czysci token (WoocommerceAddons::forgetApiSessionOnCredentialsChange),
        // wiec nowe dane sa sprawdzane logowaniem przy pierwszym wyswietleniu strony.
        $code = $this->api()->ensureToken();

        if( $code === 0 || $code >= 500 ){
            $this->addErrorAdminNotice( "Błąd połączenia z serwerem GLOBKURIER<br>(Kod odpowiedzi = {$code})", 'error', $die );
            return FALSE;
        }

        if( $code !== 200 ){
            $this->addErrorAdminNotice( __( 'Błędny login lub hasło połączenia API GlobKurier', 'globkurier' ) . "<br>(Kod odpowiedzi = {$code})", 'error', $die );
            return FALSE;
        }

        return TRUE;
    }

    public function settings( $setting = NULL, $default = [] ){
        $optionsKey = 'globkurier';

        $options = get_option( $optionsKey, $default );

        if( $setting ){
            return $options[ $setting ] ?? $default;
        }

        return $options;
    }

    public function getLanguage(){
        return $this->settings[ 'language' ] ?? 'pl';
    }

    public function encrypter(): GlobKurierEncrypter{
        return new GlobKurierEncrypter();
    }

    public function countries(): GlobKurierCountries{
        return new GlobKurierCountries();
    }

    public function user(): GlobKurierUser{
        return new GlobKurierUser();
    }

    public function order(): GlobKurierOrder{
        return new GlobKurierOrder();
    }

    public function addons(): GlobKurierAddons{
        return new GlobKurierAddons();
    }

    public function product(): GlobKurierProduct{

        return new GlobKurierProduct();
    }

    public function contentsList(){

        return [
            ''                               => '-- Wybierz --',
            'Akcesoria telefoniczne'         => 'Akcesoria telefoniczne',
            'Artykuły medyczne (nie leki)'   => 'Artykuły medyczne (nie leki)',
            'Artykuły i narzędzia budowlane' => 'Artykuły i narzędzia budowlane',
            'Artykuły i urządzenia sportowe' => 'Artykuły i urządzenia sportowe',
            'Części samochodowe'             => 'Części samochodowe',
            'Dokumenty'                      => 'Dokumenty',
            'Fotografie'                     => 'Fotografie',
            'Książki i czasopisma'           => 'Książki i czasopisma',
            'Materiały firmowe'              => 'Materiały firmowe',
            'Meble'                          => 'Meble',
            'Odzież'                         => 'Odzież',
            'Sprzęt AGD i RTV'               => 'Sprzęt AGD i RTV',
            'Sprzęt komputerowy'             => 'Sprzęt komputerowy',
            'Wózki'                          => 'Wózki',
            'Zabawki i modele'               => 'Zabawki i modele',
            'Inne'                           => 'Inne'
        ];

    }

    public function inpost(): GlobKurierInpost{

        return new GlobKurierInpost();
    }

    public function ruch(): GlobKurierRuch{

        return new GlobKurierRuch();
    }

    public function customRequiredFields(){
        return new GlobKurierCustomRequiredFields();
    }

    public function documents(){
        return new GlobKurierDocuments();
    }
	
	public function extraPickupPoints()
	{
		return new GlobKurierExtraPickupPoints();
	}
	
	public function crossborderTerminals(): GlobKurierCrossborderTerminals
	{
		return new GlobKurierCrossborderTerminals();
	}

	public function customs(): GlobKurierCustoms
	{
		return new GlobKurierCustoms();
	}

    public function wcShippingMethods(){
        return new ShippingMethods();
    }

    private function postExist( $postId ){
        return get_post_status( $postId ) !== FALSE;
    }

    // Used to store not Woocommerce order globkurier assigned shipping datails
    public function getGhostPostID(){
        $currentGhostPostID = get_option( 'udigroup_globkurier_ghost_post_id', NULL );

        if( $currentGhostPostID && $this->postExist( $currentGhostPostID ) ){
            //ghost post already exist skip creating
            return $currentGhostPostID;
        }

        $ghostPostID = wp_insert_post( [
            'post_title' => 'globkurier_ghost_post_do_not_delete',
            'post_type'  => 'globkurier_ghost',
        ] );

        update_option( 'udigroup_globkurier_ghost_post_id', $ghostPostID );

        return $ghostPostID;
    }

    public function handleError( $code, $response = NULL ){

        echo wp_json_encode( $response[ 'fields' ] ?? $code );
        die;
    }

    public function addErrorAdminNotice( $msg, $type = 'error', $die = TRUE ){
        $arr = array( 'br' => array(), 'p' => array(), 'strong' => array() , 'a' => array( 'href' => array() ));
        echo "<div class=\"notice notice-".esc_attr($type)."\">";
        echo "<p>".wp_kses($msg,$arr)."</p>";
        echo '</div>';

        if( $die ){
            die;
        }
    }
	
	public function isAnyPickupPointActive():bool
	{
		$settings = $this->settings();
	
		if( ($settings['inpost_active'] ?? 0) == 1 || ($settings['ruch_active'] ?? 0) == 1 ){
			return true;
		}
		
		foreach( $settings as $key => $value ){
			if( strpos( $key, 'extra_pickup_point_' ) === 0 && $value != 0 ){
				return true;
			}
		}
		
		return false;
	}
}