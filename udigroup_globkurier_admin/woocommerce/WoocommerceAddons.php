<?php

namespace udigroup_globkurier;

use WC_Order_Item_Shipping;

use function Sodium\add;

class WoocommerceAddons
{

    private $globKurier;

    public function __construct()
    {
        global $globKurier;
        $this->globKurier = $globKurier;
    }

    public function init()
    {
        add_filter('woocommerce_screen_ids', [$this, 'set_wc_screen_ids']);

        add_filter('woocommerce_get_sections_shipping', static function ($section){
            $section[ 'globkurier' ] = __('GlobKurier', 'globkurier');

            return $section;
        });

        add_filter('woocommerce_get_settings_shipping', [$this, 'globkurier_settings'], 10, 2);

        add_action('woocommerce_admin_field_udihidden', static function ($value){
            echo '<tr valign="top" class="'.esc_attr($value[ 'class' ]).'" '.esc_attr($value[ 'style' ]).'>';
            echo '	<th scope="row" class="titledesc">';
            echo '		<label for="'.esc_attr($value[ 'id' ]).'">'.esc_attr($value[ 'title' ]).'</label>';
            echo '	</th>';
            echo '	<td class="forminp forminp-text">';
            echo "		<input name='".esc_attr($value[ 'name' ])."' id ='".esc_attr($value[ 'id' ])."' type ='text' value='".esc_attr($value[ 'value' ])."'>";
            echo '  </td>';
            echo '</tr>';
        }, 10, 1);

        add_action('woocommerce_admin_field_udiHiddenUpdateInpostBtn', static function ($value){
            echo '<tr valign="top" class="'.esc_attr($value[ 'class' ]).'" '.esc_attr($value[ 'style' ]).'>';
            echo '	<th scope="row" class="titledesc" style="padding: 0;">';
            echo '		<label for="'.esc_attr($value[ 'id' ]).'">'.esc_attr($value[ 'title' ]).'</label>';
            echo '	</th>';
            echo '	<td class="forminp forminp-text" style="padding-top: 0;padding-bottom: 0; display: flex">';
            echo "		<button class='button-primary updateInpostButton' type='button'>".esc_attr(__('Wgraj aktualne punkty Inpost', 'globkurier'))."</button>";
            echo "		<div class='udi-loader'></div>";
            echo '  </td>';
            echo '</tr>';
        }, 10, 1);
		
		add_action('woocommerce_admin_field_udideletedata', static function ($value){
			echo '<tr valign="top" class="'.esc_attr($value[ 'class' ] ?? '').'" '.esc_attr($value[ 'style' ] ?? '').'>';
			echo '	<th scope="row" class="titledesc">';
			echo "		<a href='".esc_url(wp_nonce_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=globkurier&delete_globkurier_data=1'),
					'delete_globkurier_data_nonce'))."' class='button button-secondary' onclick='return confirm(\"".esc_js(__('Czy na pewno chcesz usunąć wszystkie dane wtyczki?',
					'globkurier'))."\")'>".esc_attr(__('Usuń dane wtyczki', 'globkurier')).'</a>';
			echo '  </th>';
			echo '</tr>';
		}, 10, 1);
		
		
		
		add_action('woocommerce_admin_field_udiHiddenUpdateRuchBtn', static function ($value){
            echo '<tr valign="top" class="'.esc_attr($value[ 'class' ]).'" '.esc_attr($value[ 'style' ]).'>';
            echo '	<th scope="row" class="titledesc" style="padding: 0;">';
            echo '		<label for="'.esc_attr($value[ 'id' ]).'">'.esc_attr($value[ 'title' ]).'</label>';
            echo '	</th>';
            echo '	<td class="forminp forminp-text" style="padding-top: 0;padding-bottom: 0; display: flex">';
            echo "		<button class='button-primary updateRuchButton' type='button'>".esc_attr(__('Wgraj aktualne punkty ORLEN Paczka', 'globkurier'))."</button>";
            echo "		<div class='udi-loader'></div>";
            echo '  </td>';
            echo '</tr>';
        }, 10, 1);

        add_action('woocommerce_admin_field_udireadonly', static function ($value){
            echo '<tr valign="top">';
            echo '	<th scope="row" class="titledesc">';
            echo '		<label for="'.esc_attr($value[ 'id' ]).'">'.esc_attr($value[ 'title' ]).'</label>';
            echo '	</th>';
            echo '	<td class="forminp forminp-text">';
            echo '		<input readonly  name="'.esc_attr($value[ 'name' ]).'" id="'.esc_attr($value[ 'id' ]).'" type="'.esc_attr($value[ '_type' ]).'" style="" value="'.esc_attr($value[ 'value' ]).'">';
            echo '  </td>';
            echo '</tr>';
        }, 10, 1);

        add_action('woocommerce_admin_field_udisectionstart', static function ($value){
            echo '<div class="udi-section-start">';
        }, 10, 1);

        add_action('woocommerce_admin_field_udisectionend', static function ($value){
            echo '</div>';
        }, 10, 1);

        add_filter('pre_update_option_globkurier', [$this, 'hashPasswordValue'], 10, 2);

		add_filter('pre_update_option_globkurier', [$this, 'cleanupRemovedCarriers'], 5, 2);

		add_action('update_option_globkurier', [$this, 'forgetApiSessionOnCredentialsChange'], 10, 2);
		add_action('add_option_globkurier', [$this, 'forgetApiSessionOnCredentialsChange'], 10, 2);

		add_action('admin_footer', [$this, 'renderReturnsBanner']);

        add_action('add_meta_boxes', [$this, 'globkurier_wc_order_meta_box']);

        add_action('add_meta_boxes', [$this, 'globkurier_wc_old_orders_meta_box']);

        add_action('woocommerce_after_shipping_rate', [$this, 'afterShippingMethodSelected'], 100, 2);

        add_action('woocommerce_review_order_before_order_total', function (){
            $chosenShippingMethods = WC()->session->get('chosen_shipping_methods');

            global $globKurier;

            $googleMapsAPIKey = $globKurier->settings('googleMapApiKey');

            if (empty($googleMapsAPIKey)) {
                return;
            }

            $globkurierInpost = [
                'active'   => $globKurier->settings('inpost_active') ?? '',
                'methodId' => $globKurier->settings('inpost_method') ?? '',
            ];

            $globkurierRuch = [
                'active'   => $globKurier->settings('ruch_active') ?? '',
                'methodId' => $globKurier->settings('ruch_method') ?? '',
            ];

            foreach ($globKurier->settings() as $key => $method) {
                if (strpos($key, 'extra_pickup_point_') === 0) {
                    if ($method == $chosenShippingMethods[ 0 ]) {
                        require('extraPickupPointSelect.php');
                    }
                }
            }
			
            if ($chosenShippingMethods[ 0 ] == $globkurierInpost[ 'methodId' ] && $globkurierInpost[ 'active' ]) {
                require_once('inpostMapAjax.php');
            }

            if ($chosenShippingMethods[ 0 ] == $globkurierRuch[ 'methodId' ] && $globkurierRuch[ 'active' ]) {
                require_once('ruchMapWithAjaxSearch.php');
            }
        });

        add_action('woocommerce_after_order_itemmeta', [
            $this,
            'globkurier_woocommerce_admin_order_items_after_line_items',
        ], 10, 3);

        global $globKurier;
        if ($globKurier && $globKurier->isAnyPickupPointActive()) {
            add_action('woocommerce_after_checkout_validation', [
                $this,
                'globkurier_woocommerce_after_checkout_validation',
            ], 10, 2);

            add_action('woocommerce_checkout_update_order_meta', [
                $this,
                'globkurier_woocommerce_checkout_create_order',
            ], 20, 3);
        }
	}

    public function set_wc_screen_ids($screen)
    {
        $screen[] = 'globkurier_page_globkurier_ship_order';

        return $screen;
    }

    public function globkurier_wc_order_meta_box()
    {
        add_meta_box('globkurier_ship_new_order', __('NADAJ PRZESYŁKĘ Z GLOBKURIER', 'globkurier'), [
            $this,
            'globkurier_meta_box_content_async',
        ], ['shop_order', 'woocommerce_page_wc-orders'], 'advanced', 'high');
    }

    public function globkurier_meta_box_content()
    {
        require_once 'metaBox/order.php';
    }
    public function globkurier_meta_box_content_async()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID zamowienia z adresu ekranu edycji (HPOS), tylko odczyt do metaboxa.
        $orderId = get_the_ID() ?: (isset($_GET['id']) ? absint($_GET['id'] ?? '') : null);

        require_once __DIR__.'/metaBox/orderAsync.php';
    }
    public function globkurier_wc_old_orders_meta_box()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID zamowienia z adresu ekranu edycji (HPOS), tylko odczyt do metaboxa.
        $orderId = get_the_ID() ?: (isset($_GET['id']) ? absint($_GET['id'] ?? '') : null);

        $oldOrders = UDIGroup_ShipmentHistory::forOrder($orderId);

        if (count($oldOrders) == 0) {
            return;
        }

        // Lista od razu, bez przycisku "Pobierz liste" - dane sa lokalne (historia w bazie), bez zapytan do API.
        add_meta_box('globkurier_old_orders', __('PACZKI NADANE PRZEZ GLOBKURIER', 'globkurier').' ('.count($oldOrders).')', [
            $this,
            'globkurier_meta_old_orders_box_content',
        ], ['shop_order', 'woocommerce_page_wc-orders'], 'advanced', 'core');
    }

    public function globkurier_meta_old_orders_box_content()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID zamowienia z adresu ekranu edycji (HPOS), tylko odczyt do metaboxa.
        $orderId = get_the_ID() ?: absint($_GET[ 'id' ] ?? '');

        require_once 'metaBox/oldOrders.php';
    }

    public function globkurier_meta_old_orders_box_content_async()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID zamowienia z adresu ekranu edycji (HPOS), tylko odczyt do metaboxa.
        $orderId = get_the_ID() ?: absint($_GET[ 'id' ] ?? '');

        require_once 'metaBox/oldOrdersAsync.php';
    }

    /**
     * Zapis formularza ustawien GlobKurier (a nie update_option('globkurier') z WP-CLI, importu czy innej wtyczki).
     */
    private function isSettingsFormSubmit(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- tylko sprawdzenie, czy to wyslanie formularza; nonce weryfikuja wywolujacy.
        return isset($_POST[ 'globkurier' ]);
    }

    private function settingsNonceValid(): bool
    {
        return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[ 'nonce' ] ?? '')), 'globkurier_hash_password_nonce');
    }

    public function hashPasswordValue($options)
    {
        // Zapis spoza formularza ustawien przechodzi bez zmian (wczesniej konczyl kazde zadanie bledem "Invalid nonce").
        if (! $this->isSettingsFormSubmit()) {
            return $options;
        }

        if (! $this->settingsNonceValid()) {
            wp_send_json_error('Invalid nonce');
        }

        global $globKurier;

        $oldPassword = $globKurier->settings('password');
        // Haslo bez sanitize_text_field (zmienial hasla z "<", sekwencjami %XX i wielokrotnymi spacjami) - nigdzie nie jest
        // wypisywane, trafia tylko do encrypt().
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- haslo do szyfrowania (patrz wyzej); nonce sprawdza settingsNonceValid() powyzej.
        $newPassword = (string) wp_unslash($_POST[ 'globkurier' ][ 'password' ] ?? '');

        if ($newPassword !== $oldPassword) {
            $options[ 'password' ] = $globKurier->encrypter()->encrypt($newPassword);
        }

        return $options;
    }

    public function globkurier_settings($settings, $current_section)
    {
		if (isset($_GET[ 'delete_globkurier_data' ])
			&& wp_verify_nonce(sanitize_text_field(wp_unslash($_GET[ '_wpnonce' ] ?? '')), 'delete_globkurier_data_nonce')
			&& current_user_can('manage_woocommerce')) {
			delete_option('globkurier');
			
			wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=globkurier&deleted=1'));
			exit;
		}
		
		if (isset($_GET[ 'deleted' ])) {
			echo "<div class=\"notice notice-success\">";
			echo '<p>'.esc_attr(__('Dane wtyczki zostały usunięte', 'globkurier')).'</p>';
			echo '</div>';
		}
		
        if ($current_section == 'globkurier') {
            global $globKurier;

            $settings_globkurier   = [];
            $settings_globkurier[] = [
                'name' => __('GlobKurier - konfiguracja', 'globkurier'),
                'id'   => 'globkurier-info',
                'desc' => '<div>
								<div class="udi-container-column udi-globkurier-how-to-start-container">
									<h3 class="udi-globkurier-how-to-start-header">'.esc_html__('Jak rozpocząć', 'globkurier').'</h3>
										<ol class="udi-globkurier-how-to-start-list">
											<li>'.esc_html__('Uzupełnij dane Użytkownik GlobKurier', 'globkurier').'</li>
											<li>'.esc_html__('Uzupełnij dane domyślne', 'globkurier').'</li>
											<li>'.esc_html__('Gotowe', 'globkurier').'!</li>
										</ol>
								</div>'
                    .esc_html__('Nie masz konta?', 'globkurier').' <a href="http://globkurier.pl/register" target="_blank">'.esc_html__('Zarejestruj się', 'globkurier').'</a>'
                    .'</div>',
                'type' => 'title',
            ];

            echo "<input type='hidden' name='nonce' value='".esc_attr(wp_create_nonce('globkurier_hash_password_nonce'))."'>";

            $user     = get_option('globkurier-username');
            $password = get_option('globkurier-password');

            $settings_globkurier[] = [
                'title'   => __('Język API', 'globkurier'),
                'name'    => 'globkurier[language]',
                'id'      => 'globkurier[language]',
                'value'   => get_option('globkurier')[ 'language' ] ?? '',
                'type'    => 'select',
                'options' => ['pl' => __('Polski', 'globkurier'), 'en' => __('Angielski', 'globkurier')],
            ];

            $status = $globKurier->isUserLoggedIn(false);

            if (! $status) {
                $settings_globkurier = array_merge($settings_globkurier, $this->getSettingsSection_login());
            } else {
                $settings_globkurier = array_merge($settings_globkurier, $this->getSettingsSection_login());
                $settings_globkurier = array_merge($settings_globkurier, $this->getSettingsSection_defaults());
                $settings_globkurier = array_merge($settings_globkurier, $this->getSettingsSection_orderList());

                // Odswiezanie punktow linkiem (GET) tylko z nonce'em i dla obslugi sklepu - inaczej bylby to CSRF.
                $canUpdatePoints = (current_user_can('manage_woocommerce') || current_user_can('manage_options'))
                    && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET[ '_wpnonce' ] ?? '')), 'globkurier_update_points_nonce');

                if ($canUpdatePoints && sanitize_text_field(wp_unslash($_GET[ 'updateInpostPoints' ] ?? ''))) {
                    $globKurier->inpost()->update();
                    wp_safe_redirect($globKurier->getSettingsUrl().'&updateInpostSuccess');
                    wp_die();
                }

                if ($canUpdatePoints && sanitize_text_field(wp_unslash($_GET[ 'updateRuchPoints' ] ?? ''))) {
                    $globKurier->ruch()->update();
                    wp_safe_redirect($globKurier->getSettingsUrl().'&updateRuchSuccess');
                    wp_die();
                }

                if (sanitize_text_field(wp_unslash($_GET[ 'updateInpostSuccess' ] ?? ''))) {
                    echo "<div class=\"notice notice-info\">";
                    echo "<p>".esc_attr(__('Paczkomaty zostały zaktualizowane', 'globkurier'))."</p>";
                    echo '</div>';
                }

                if (sanitize_text_field(wp_unslash($_GET[ 'updateRuchSuccess' ] ?? ''))) {
                    echo "<div class=\"notice notice-info\">";
                    echo "<p>".esc_attr(__('Punkt RUCHu zostały zaktualizowane', 'globkurier'))."</p>";
                    echo '</div>';
                }
            }

            return $settings_globkurier;
        }

        return $settings;
    }

    public function getSettingsSection_login()
    {
        $settings_inputs = [];

        $settings_inputs[] = [
            'title' => __('Użytkownik GlobKurier', 'globkurier'),
            'type'  => 'title',
        ];

        $settings_inputs[] = [
            'title' => __('Użytkownik', 'globkurier'),
            'name'  => 'globkurier[username]',
            'id'    => 'globkurier[username]',
            'value' => get_option('globkurier')[ 'username' ] ?? '',
            'type'  => 'text',
            'desc'  => __('Twój login z serwisu GlobKurier', 'globkurier'),
        ];

        $settings_inputs[] = [
            'title' => __('Hasło', 'globkurier'),
            'id'    => 'globkurier[password]',
            'name'  => 'globkurier[password]',
            'value' => get_option('globkurier')[ 'password' ] ?? '',
            'desc'  => __('Twoje hasło z serwisu GlobKurier', 'globkurier'),
            'type'  => 'password',
        ];

        $settings_inputs[] = [
            'title' => __('Google Maps API KEY', 'globkurier'),
            'name'  => 'globkurier[googleMapApiKey]',
            'id'    => 'globkurier[googleMapApiKey]',
            'value' => get_option('globkurier')[ 'googleMapApiKey' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-login-end'];

        return $settings_inputs;
    }

    /**
     * Lista zamowien WooCommerce: dodatkowa kolumna "Wysylka" (BulkSend). Domyslnie wlaczona - jak dotad.
     */
    public function getSettingsSection_orderList()
    {
        return [
            ['type' => 'udisectionstart'],
            [
                'title' => __('Lista zamówień', 'globkurier'),
                'type'  => 'title',
            ],
            [
                'title'   => __('Kolumna "Wysyłka" na liście zamówień', 'globkurier'),
                'name'    => 'globkurier[order_list_shipping_column]',
                'id'      => 'globkurier[order_list_shipping_column]',
                'value'   => get_option('globkurier')[ 'order_list_shipping_column' ] ?? 1,
                'type'    => 'select',
                'options' => [
                    1 => __('TAK', 'globkurier'),
                    0 => __('NIE', 'globkurier'),
                ],
                'desc'    => __('WooCommerce pokazuje metodę wysyłki także w kolumnie "Wysyłka do". Wyłączenie kolumny przyspiesza listę zamówień przy wielu zamówieniach i wtyczkach tłumaczeń (WPML). Kolumnę można też ukryć tylko dla siebie w "Opcjach ekranu".', 'globkurier'),
            ],
            ['type' => 'sectionend', 'id' => 'globkurier-order-list-end'],
            ['type' => 'udisectionend'],
        ];
    }

    public function getSettingsSection_defaults()
    {
        $settings_inputs = [];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs = array_merge($settings_inputs, $this->getSettingsSection_defaults_send());
        $settings_inputs = array_merge($settings_inputs, $this->getSettingsSection_defaults_parcel());

        $settings_inputs[] = ['type' => 'udisectionstart'];

        return $settings_inputs;
    }

    public function getSettingsSection_defaults_send()
    {
        $settings_inputs = [];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs[] = [
            'title' => __('Domyślny adres nadania', 'globkurier'),
            'type'  => 'title',
        ];

        $settings_inputs[] = [
            'title' => __('Imię i nazwisko', 'globkurier'),
            'name'  => 'globkurier[default][send][flnames]',
            'id'    => 'globkurier[default][send][flnames]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'flnames' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Ulica', 'globkurier'),
            'name'  => 'globkurier[default][send][street]',
            'id'    => 'globkurier[default][send][street]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'street' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Numer domu', 'globkurier'),
            'name'  => 'globkurier[default][send][homeNumber]',
            'id'    => 'globkurier[default][send][homeNumber]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'homeNumber' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Numer lokalu', 'globkurier'),
            'name'  => 'globkurier[default][send][flatNumber]',
            'id'    => 'globkurier[default][send][flatNumber]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'flatNumber' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Miasto', 'globkurier'),
            'name'  => 'globkurier[default][send][city]',
            'id'    => 'globkurier[default][send][city]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'city' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Kod pocztowy', 'globkurier'),
            'name'  => 'globkurier[default][send][postal]',
            'id'    => 'globkurier[default][send][postal]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'postal' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title'   => __('Państwo', 'globkurier'),
            'name'    => 'globkurier[default][send][country]',
            'id'      => 'globkurier[default][send][country]',
            'value'   => get_option('globkurier')[ 'default' ][ 'send' ][ 'country' ] ?? '',
            'type'    => 'select',
            'options' => $this->globKurier->countries()->getArray(),
        ];

        $settings_inputs[] = [
            'title' => __('Email', 'globkurier'),
            'name'  => 'globkurier[default][send][email]',
            'id'    => 'globkurier[default][send][email]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'email' ] ?? '',
            'type'  => 'email',
        ];

        $settings_inputs[] = [
            'title' => __('Numer telefonu', 'globkurier'),
            'name'  => 'globkurier[default][send][phone]',
            'id'    => 'globkurier[default][send][phone]',
            'value' => get_option('globkurier')[ 'default' ][ 'send' ][ 'phone' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-default-send-end'];

        $settings_inputs[] = ['type' => 'udisectionend'];

        return $settings_inputs;
    }

    public function getSettingsSection_defaults_parcel()
    {
        $settings_inputs = [];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs[] = [
            'title' => __('Domyślne parametry przesyłki', 'globkurier'),
            'type'  => 'title',
        ];

        $settings_inputs[] = [
            'title' => __('Długość [cm]', 'globkurier'),
            'name'  => 'globkurier[default][parcel][length]',
            'id'    => 'globkurier[default][parcel][length]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'length' ] ?? '',
            'type'  => 'number',
        ];
        $settings_inputs[] = [
            'title' => __('Szerokość [cm]', 'globkurier'),
            'name'  => 'globkurier[default][parcel][width]',
            'id'    => 'globkurier[default][parcel][width]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'width' ] ?? '',
            'type'  => 'number',
        ];

        $settings_inputs[] = [
            'title' => __('Wysokość [cm]', 'globkurier'),
            'name'  => 'globkurier[default][parcel][height]',
            'id'    => 'globkurier[default][parcel][height]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'height' ] ?? '',
            'type'  => 'number',
        ];

        $settings_inputs[] = [
            'title' => __('Waga (w kg)', 'globkurier'),
            'name'  => 'globkurier[default][parcel][weight]',
            'id'    => 'globkurier[default][parcel][weight]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'weight' ] ?? '',
            'type'  => 'number',
        ];

        $settings_inputs[] = [
            'title'   => __('Sumuj wagę towarów w zamówieniu', 'globkurier'),
            'name'    => 'globkurier[sum_order_weight]',
            'id'      => 'globkurier[sum_order_weight]',
            'value'   => get_option('globkurier')[ 'sum_order_weight' ] ?? 0,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
            'desc' => __('Automatycznie sumuje wagę wszystkich produktów w zamówieniu', 'globkurier'),
        ];

        $settings_inputs[] = [
            'title'   => __('Zawartość', 'globkurier'),
            'name'    => 'globkurier[default][parcel][content]',
            'id'      => 'globkurier[default][parcel][content]',
            'value'   => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'content' ] ?? '',
            'type'    => 'select',
            'options' => $this->globKurier->contentsList(),
        ];

        if ((get_option('globkurier')[ 'default' ][ 'parcel' ][ 'content' ] ?? '') == 'Inne') {
            $style = '';
        } else {
            $style = 'style="display:none"';
        }

        $settings_inputs[] = [
            'title' => __('Inna zawartość - jaka?', 'globkurier'),
            'name'  => 'globkurier[default][parcel][otherContent]',
            'id'    => 'globkurier[default][parcel][otherContent]',
            'class' => 'globkurier-content-other',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'otherContent' ] ?? '',
            'style' => $style,
            'type'  => 'udihidden',
			'desc'  => __('Limit 30 znaków. Tekst zostanie automatycznie ucięty.', 'globkurier'),
		];

		$settings_inputs[] = [
			'title'   => __('Dodaj SKU do zawartości', 'globkurier'),
			'name'    => 'globkurier[content_add_sku]',
			'id'      => 'globkurier[content_add_sku]',
			'value'   => get_option('globkurier')[ 'content_add_sku' ] ?? 0,
			'type'    => 'select',
			'options' => [
				0 => __('NIE', 'globkurier'),
				1 => __('TAK', 'globkurier'),
			],
			'desc'  => __('Limit 30 znaków na treść zawartości. SKU zostanie automatycznie ucięty jeśli przekroczy długość. Kody SKU mają pierwszeństwo przed zawartością, jeśli łączny tekst przekroczy 30 znaków w treści pojawia się tylko kody SKU.', 'globkurier'),
		];
		
		$settings_inputs[] = [
			'title'   => __('Dodaj EAN do zawartości', 'globkurier'),
			'name'    => 'globkurier[content_add_ean]',
			'id'      => 'globkurier[content_add_ean]',
			'value'   => get_option('globkurier')[ 'content_add_ean' ] ?? 0,
			'type'    => 'select',
			'options' => [
				0 => __('NIE', 'globkurier'),
				1 => __('TAK', 'globkurier'),
			],
			'desc'  => __('Limit 30 znaków na treść zawartości. EAN zostanie automatycznie ucięty jeśli przekroczy długość. Kody EAN mają pierwszeństwo przed zawartością, jeśli łączny tekst przekroczy 30 znaków w treści pojawia się tylko kody EAN.', 'globkurier'),
		];
		
        $settings_inputs[] = [
            'title' => __('Numer konta bankowego do pobrań', 'globkurier'),
            'desc'  => 'Numer konta w formacie IBAN ( PL29109015199335376470438408 )',
            'name'  => 'globkurier[default][parcel][cod][account][number]',
            'id'    => 'globkurier[default][parcel][cod][account][number]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'cod' ][ 'account' ][ 'number' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Nazwa właściciela rachunku', 'globkurier'),
            'name'  => 'globkurier[default][parcel][cod][account][name]',
            'id'    => 'globkurier[default][parcel][cod][account][name]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'cod' ][ 'account' ][ 'name' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Ulica właściciela rachunku', 'globkurier'),
            'name'  => 'globkurier[default][parcel][cod][account][owner]',
            'id'    => 'globkurier[default][parcel][cod][account][owner]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'cod' ][ 'account' ][ 'owner' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Kod pocztowy i miejscowość właściciela rachunku', 'globkurier'),
            'name'  => 'globkurier[default][parcel][cod][account][address]',
            'id'    => 'globkurier[default][parcel][cod][account][address]',
            'value' => get_option('globkurier')[ 'default' ][ 'parcel' ][ 'cod' ][ 'account' ][ 'address' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title'   => __('Domyślna metoda płatności', 'globkurier'),
            'name'    => 'globkurier[payment]',
            'id'      => 'globkurier[payment]',
            'type'    => 'select',
            'value'   => get_option('globkurier')[ 'payment' ] ?? '',
            'options' => $this->globKurier->user()->paymentMethods(),
        ];

        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-default-parcel-end'];
        $settings_inputs[] = ['type' => 'udisectionend'];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs[] = [
            'title' => __('InPost paczkomaty', 'globkurier'),
            'type'  => 'title',
        ];

        $inpostValue = get_option('globkurier')[ 'inpost_active' ] ?? '';

        $settings_inputs[] = [
            'title'   => __('Włącz obsługę paczkomatów InPost', 'globkurier'),
            'name'    => 'globkurier[inpost_active]',
            'id'      => 'globkurier[inpost_active]',
            'value'   => $inpostValue,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title' => '',
            'name'  => 'updateInpost',
            'id'    => 'updateInpost',
            'class' => 'updateInpostContainer',
            'value' => '',
            'style' => ($inpostValue == 0) ? 'style="display:none"' : '',
            'type'  => 'udiHiddenUpdateInpostBtn',
        ];

        $settings_inputs[] = [
            'title'   => __('Wymuś aktualizację punktów Inpost jeśli starsze niż 3 dni', 'globkurier'),
            'name'    => 'globkurier[inpost_points_valid_time_checker_is_active]',
            'id'      => 'globkurier[inpost_points_valid_time_checker_is_active]',
            'value'   => get_option('globkurier')[ 'inpost_points_valid_time_checker_is_active' ] ?? 0,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title'   => __('Zapisz punkty Inpost w pliku json', 'globkurier'),
            'name'    => 'globkurier[storeInpostPointsInFile]',
            'id'      => 'globkurier[storeInpostPointsInFile]',
            'value'   => get_option('globkurier')[ 'storeInpostPointsInFile' ] ?? 1,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title' => __('Co ile dni pobierać aktualizacje listy paczkomatów Inpost', 'globkurier'),
            'name'  => 'globkurier[inpost][cache_ttl]',
            'id'    => 'globkurier[inpost][cache_ttl]',
            'value' => get_option('globkurier')[ 'inpost' ][ 'cache_ttl' ] ?? 3,
            'type'  => 'number',
            'desc'  => 'Wartość -1 wyłączy automatyczne aktualizacje listy paczkomatów, użyj tej wartości jeśli występują problemy z pobieraniem listy paczkomatów.',
        ];

        $settings_inputs[] = [
            'title' => __('Ile paczkomatów pobierać za 1 zapytaniem do API Inpost', 'globkurier'),
            'name'  => 'globkurier[inpost][api_per_page]',
            'id'    => 'globkurier[inpost][api_per_page]',
            'value' => get_option('globkurier')[ 'inpost' ][ 'api_per_page' ] ?? 7000,
            'type'  => 'number',
            'desc'  => 'Zmniejsz tę liczbę jeśli występują problemy z automatyczną aktualizacją listy paczkomatów. Zwiększenie wartości przyspieszy proces aktualizacji podczas wyszukiwania paczkomatu',
        ];

        $settings_inputs[] = [
            'title'   => __('Metoda wysyłki do obłsugi paczkomatów InPost', 'globkurier'),
            'name'    => 'globkurier[inpost_method]',
            'id'      => 'globkurier[inpost_method]',
            'value'   => get_option('globkurier')[ 'inpost_method' ] ?? '',
            'type'    => 'select',
            'options' => $this->globKurier->wcShippingMethods()->getArray(),
        ];

        $settings_inputs[] = [
            'title' => __('Wpisz miasto domyślnego paczkomatu nadawczego', 'globkurier'),
            'name'  => 'globkurier[inpost_default]',
            'id'    => 'globkurier[inpost_default]',
            'value' => get_option('globkurier')[ 'inpost_default' ] ?? '',
            'type'  => 'text',
        ];

        $settings_inputs[] = [
            'title' => __('Kod paczkomatu nadawczego', 'globkurier'),
            'name'  => 'globkurier[inpost_default_code]',
            'id'    => 'globkurier[inpost_default_code]',
            'value' => get_option('globkurier')[ 'inpost_default_code' ] ?? '',
            'type'  => 'udireadonly',
            '_type' => 'text',
        ];

        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-inpost-end'];
        $settings_inputs[] = ['type' => 'udisectionend'];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs[] = [
            'title' => __('ORLEN Paczka', 'globkurier'),
            'type'  => 'title',
        ];

        $ruchValue         = get_option('globkurier')[ 'ruch_active' ] ?? '';
        $settings_inputs[] = [
            'title'   => __('Włącz obsługę ORLEN Paczka', 'globkurier'),
            'name'    => 'globkurier[ruch_active]',
            'id'      => 'globkurier[ruch_active]',
            'value'   => $ruchValue,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title' => '',
            'name'  => 'updateRuch',
            'id'    => 'updateRuch',
            'class' => 'updateRuchContainer',
            'value' => '',
            'style' => ($ruchValue == 0) ? 'style="display:none"' : '',
            'type'  => 'udiHiddenUpdateRuchBtn',
        ];

        $settings_inputs[] = [
            'title'   => __('Wymuś aktualizację punktów Orlen jeśli starsze niż 1 dzień', 'globkurier'),
            'name'    => 'globkurier[ruch_points_valid_time_checker_is_active]',
            'id'      => 'globkurier[ruch_points_valid_time_checker_is_active]',
            'value'   => get_option('globkurier')[ 'ruch_points_valid_time_checker_is_active' ] ?? 0,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title'   => __('Zapisz punkty Orlen w pliku json', 'globkurier'),
            'name'    => 'globkurier[storeRuchPointsInFile]',
            'id'      => 'globkurier[storeRuchPointsInFile]',
            'value'   => get_option('globkurier')[ 'storeRuchPointsInFile' ] ?? 1,
            'type'    => 'select',
            'options' => [
                0 => __('NIE', 'globkurier'),
                1 => __('TAK', 'globkurier'),
            ],
        ];

        $settings_inputs[] = [
            'title'   => __('Wybierz przewoźnika do obsługi ORLEN Paczka', 'globkurier'),
            'name'    => 'globkurier[ruch_method]',
            'id'      => 'globkurier[ruch_method]',
            'value'   => get_option('globkurier')[ 'ruch_method' ] ?? '',
            'type'    => 'select',
            'options' => $this->globKurier->wcShippingMethods()->getArray(),
        ];

        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-ruch-end'];
        $settings_inputs[] = ['type' => 'udisectionend'];

        $settings_inputs[] = ['type' => 'udisectionstart'];

        $settings_inputs[] = [
            'title' => __('Dodatkowe metody wysyłki', 'globkurier'),
            'type'  => 'title',
        ];

        $shippingMethods = $this->globKurier->wcShippingMethods()->getArray();

        $productsWithPoint = $this->globKurier->extraPickupPoints()->getAvailablePoints();
		
		$shippingMethods = $this->globKurier->wcShippingMethods()->getArray();
		
        foreach ($productsWithPoint as $product) {
            $carrierId         = sanitize_title($product[ 'carrierName' ]);
			
			$optionKey = 'extra_pickup_point_'.$carrierId;
			
            $settings_inputs[] = [
                /* translators: %s: nazwa przewoznika */
                'title' => sprintf(__('Wybierz przewoźnika do obsługi %s', 'globkurier'), $product[ 'carrierName' ]),

                'name' => 'globkurier['.$optionKey.']',
                'id'   => 'globkurier['.$optionKey.']',

                'value'   => get_option('globkurier')[ $optionKey ] ?? '',
                'type'    => 'select',
                'options' => $shippingMethods,
            ];
        }
		
		$settings_inputs[] = [
			'title' => '',
			'name'  => 'udideletedata',
			'id'    => 'udideletedata',
			'class' => 'udideletedataContainer',
			'value' => '',
			'type'  => 'udideletedata',
		];
		
        $settings_inputs[] = ['type' => 'sectionend', 'id' => 'globkurier-extra_point-end'];

        return $settings_inputs;
    }
	
	
	public function cleanupRemovedCarriers($value, $old_value)
	{
		// Logowanie do API i pobranie produktow tylko przy poprawnym zapisie formularza ustawien.
		if (! $this->isSettingsFormSubmit() || ! $this->settingsNonceValid()) {
			return $value;
		}

		// Zapis ustawien = odswiezenie list z API trzymanych w cache (metody platnosci, przewoznicy z punktami).
		GlobKurierUser::forgetPaymentMethodsCache();

		if($this->globKurier->api()->testToken() !== 200){
			return $value;
		}

		$productsWithPoint = $this->globKurier->extraPickupPoints()->getAvailablePoints(true);
		
		$validCarrierIds = [];
		foreach ($productsWithPoint as $product) {
			$carrierId = sanitize_title($product['carrierName']);
			$validCarrierIds[] = 'extra_pickup_point_'.$carrierId;
		}
		
		
		foreach ($value as $key => $val) {
			if (strpos($key, 'extra_pickup_point_') === 0) {
		
				if (!in_array($key, $validCarrierIds)) {
					unset($value[$key]);
				}
			}
		}
		
		return $value;
	}

	/**
	 * Zmiana loginu lub hasla (takze spoza formularza, np. WP-CLI) usuwa token poprzedniego konta - isUserLoggedIn()
	 * ufa tokenowi z cache, wiec bez tego nowe dane nie bylyby sprawdzane do jego wygasniecia (ok. 20 h).
	 * Dla add_option pierwszy argument to nazwa opcji, drugi - nowa wartosc.
	 */
	public function forgetApiSessionOnCredentialsChange($oldValue, $newValue)
	{
		$old = is_array($oldValue) ? $oldValue : [];
		$new = is_array($newValue) ? $newValue : [];

		if (($old[ 'username' ] ?? '') === ($new[ 'username' ] ?? '') && ($old[ 'password' ] ?? '') === ($new[ 'password' ] ?? '')) {
			return;
		}

		$this->globKurier->api()->forgetToken();
		GlobKurierUser::forgetPaymentMethodsCache();
	}


    public function afterShippingMethodSelected($method, $index)
    {
        global $globKurier;

        if (is_cart()) {
            return;
        }

        $googleMapsAPIKey = $globKurier->settings('googleMapApiKey');

        if (! empty($googleMapsAPIKey)) {
            return;
        }

        $chosenShippingMethods = WC()->session->get('chosen_shipping_methods');

        $methodId = $method->get_id();


        if ($chosenShippingMethods[ 0 ] == $methodId) {
            $globkurierInpost = [
                'active'   => $globKurier->settings('inpost_active') ?? '',
                'methodId' => $globKurier->settings('inpost_method') ?? '',
            ];

            $globkurierRuch = [
                'active'   => $globKurier->settings('ruch_active') ?? '',
                'methodId' => $globKurier->settings('ruch_method') ?? '',
            ];

            if ($globkurierInpost[ 'active' ] && $globkurierInpost[ 'methodId' ] === $methodId) {
                $inpost_id  = WC()->session->get('globkurier_inpost_selected_point_id') ?? '';
                $inpost_val = WC()->session->get('globkurier_inpost_selected_point_value') ?? '';


                ?>
                </li>
                <div class="globkurier-inpost-container">
                    <input type="hidden" name="globkurier_method_id" id="globkurier_method_id" value="<?php
                    echo esc_attr($methodId) ?>">

                    <input type="hidden" name="globkurier_inpost_input" id="globkurier_inpost_input" value="<?php
                    echo esc_attr($inpost_val) ?>" required>
                    <input type="hidden" name="globkurier_inpost_input_hidden_value" id="globkurier_inpost_input_hidden_value" value="<?php
                    echo esc_attr($inpost_id) ?>" required>

                    <input type="hidden" name="nonce" value="<?php
                    echo esc_attr(wp_create_nonce('globkurier_woocommerce_checkout_nonce')) ?>">

                    <select style="width: 100%; display: none" class="udi-select2" id="udi-select-inpost" name="globkurier_inpost_input_value">
                    </select>

                    <script>
                        // Bez pustych linii w tym skrypcie: checkout w bloku Shortcode przepuszcza HTML przez wpautop, ktory w pustych liniach wstawia znaczniki akapitu i psuje JS.
                        ( function ( $ ) {
                            $( function () {
                                const globkurierInput = '#globkurier_inpost_input';
                                const globkurierInputHiddenValue = '#globkurier_inpost_input_hidden_value';
                                const ajaxurl = data[ 'ajaxUrl' ];
                                const saveSessionAction = 'globkurierSaveInpostPointsSession';
                                function gkSaveSession( d ) {
                                    $.post( {
                                        url: ajaxurl,
                                        dataType: 'json',
                                        minLength: 3,
                                        data: {
                                            action: saveSessionAction,
                                            id: d.id,
                                            value: d.value || d.text,
                                            nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_save_inpost_points_session_nonce')) ?>',
                                        },
                                    } );
                                }
                                function matchStart( params, data ) {
                                    params.term = params.term || '';
                                    if ( data.text.toUpperCase().indexOf( params.term.toUpperCase() ) == 0 ) {
                                        return data;
                                    }
                                    return null;
                                }
                                $( document ).find( '.udi-select2#udi-select-inpost' ).select2( {
                                    placeholder: 'Znajdź punkt dostawy',
                                    language: {
                                        searching: function () {
                                            return 'Szukaj Paczkomat InPost';
                                        },
                                        inputTooShort: function () {
                                            return 'Wpisz miasto';
                                        },
                                        noResults: function () {
                                            return 'Brak wyników';
                                        },
                                        errorLoading: function () {
                                            return 'Szukanie...';
                                        },
                                        loadingMore: function () {
                                            return 'Szukanie...';
                                        },
                                    },
                                    ajax: {
                                        url: data[ 'ajaxUrl' ],
                                        dataType: 'json',
                                        data: function ( params ) {
                                            return {
                                                city: params.term,
                                                action: 'globkurierGetInpostPointsSelect2',
	                                            countryId: ($('#ship-to-different-address-checkbox').is(':checked') ? $('#shipping_country').val() : $('#billing_country').val()) || 'PL',
                                                nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_get_inpost_points_select2_nonce')) ?>'
                                            };
                                        },
                                        processResults: function ( data ) {
                                            let parsedData = JSON.parse( data );
                                            let options = [];
                                            if ( parsedData ) {
                                                $.each( parsedData, function ( index, data ) {
                                                    options.push( {
                                                        id: data.id,
                                                        text: data.label,
                                                        latitude: data.latitude,
                                                        longitude: data.longitude,
                                                        city: data.city,
                                                        address: data.address,
                                                        openingHours: data.openingHours,
                                                    } );
                                                } );
                                            }
                                            return {
                                                results: options
                                            };
                                        },
                                        cache: true
                                    },
                                    minimumInputLength: 3,
                                } ).on( 'select2:select', function ( e ) {
                                    let option = $( e.params.data )[ 0 ];
                                    $( globkurierInput ).val( option.text );
                                    $( globkurierInputHiddenValue ).val( option.id );
                                    gkSaveSession( {
                                        id: option.id,
                                        value: option.text,
                                    } );
                                } );
                                <?php if( isset($inpost_id) && $inpost_id != '' ){?>
                                $( document ).find( '.globkurier-inpost-container .select2-selection__rendered' ).text( <?php echo wp_json_encode( $inpost_val, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ?> );
                                <?php } ?>
                            } );
                        } )( jQuery );
                    </script>
                </div>
                <li>
                <?php
            }

            if ($globkurierRuch[ 'active' ] && $globkurierRuch[ 'methodId' ] === $methodId) {
                $r_id  = WC()->session->get('globkurier_ruch_selected_point_id') ?? '';
                $r_val = WC()->session->get('globkurier_ruch_selected_point_value') ?? '';
                ?>
                </li>

                <div class="globkurier-ruch-container">
                    <input type="hidden" name="globkurier_method_id" id="globkurier_method_id" value="<?php
                    echo esc_attr($methodId) ?>">
                    <input type="hidden" name="globkurier_ruch_input" id="globkurier_ruch_input" value="<?php
                    echo esc_attr($r_val) ?>" required>
                    <input type="hidden" name="globkurier_ruch_input_hidden_value" id="globkurier_ruch_input_hidden_value" value="<?php
                    echo esc_attr($r_id) ?>" required>
                    <input type="hidden" name="nonce" value="<?php
                    echo esc_attr(wp_create_nonce('globkurier_woocommerce_checkout_nonce')) ?>">

                    <select style="width: 100%; display: none" class="udi-select2" id="udi-select-ruch" name="globkurier_ruch_input_value">
                        <option></option>
                    </select>
                    <script>
                        // Bez pustych linii w tym skrypcie: checkout w bloku Shortcode przepuszcza HTML przez wpautop, ktory w pustych liniach wstawia znaczniki akapitu i psuje JS.
                        ( function ( $ ) {
                            $( function () {
                                const ajaxurl = data[ 'ajaxUrl' ];
                                const globkurierInput = '#globkurier_ruch_input';
                                const globkurierInputHiddenValue = '#globkurier_ruch_input_hidden_value';
                                const saveSessionAction = 'globkurierSaveRuchPointsSession';
                                function matchStart( params, data ) {
                                    params.term = params.term || '';
                                    if ( data.text.toUpperCase().indexOf( params.term.toUpperCase() ) == 0 ) {
                                        return data;
                                    }
                                    return null;
                                }
                                function gkSaveSession( d ) {
                                    $.post( {
                                        url: ajaxurl,
                                        dataType: 'json',
                                        minLength: 3,
                                        data: {
                                            action: saveSessionAction,
                                            id: d.id,
                                            value: d.value || d.text,
                                            nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_save_ruch_points_session_nonce')) ?>',
                                        },
                                    } );
                                }
                                $( document ).find( '.udi-select2#udi-select-ruch' ).select2( {
                                    placeholder: 'Znajdź punkt dostawy',
                                    language: {
                                        searching: function () {
                                            return 'Szukaj Paczka w RUCHu';
                                        },
                                        inputTooShort: function () {
                                            return 'Wpisz miasto';
                                        },
                                        noResults: function () {
                                            return 'Brak wyników';
                                        },
                                        errorLoading: function () {
                                            return 'Szukanie...';
                                        },
                                        loadingMore: function () {
                                            return 'Szukanie...';
                                        },
                                    },
                                    minimumInputLength: 3,
                                    ajax: {
                                        url: data[ 'ajaxUrl' ],
                                        dataType: 'json',
                                        data: function ( params ) {
                                            return {
                                                city: params.term,
                                                action: 'globkurierGetRuchPointsSelect2',
                                                nonce: '<?php echo esc_attr(wp_create_nonce('globkurier_get_ruch_points_select2_nonce')) ?>'
                                            };
                                        },
                                        processResults: function ( data ) {
                                            let parsedData = JSON.parse( data );
                                            let options = [];
                                            if ( parsedData ) {
                                                $.each( parsedData, function ( index, data ) {
                                                    options.push( {
                                                        id: data.id,
                                                        text: data.label,
                                                        latitude: data.latitude,
                                                        longitude: data.longitude,
                                                        city: data.city,
                                                        address: data.address,
                                                        openingHours: data.openingHours,
                                                    } );
                                                } );
                                            }
                                            return {
                                                results: options
                                            };
                                        },
                                        cache: true
                                    },
                                } ).on( 'select2:select', function ( e ) {
                                    let option = $( e.params.data );
                                    $( globkurierInput ).val( option[ 0 ].text );
                                    $( globkurierInputHiddenValue ).val( option[ 0 ].id );
                                    gkSaveSession( {
                                        id: option[ 0 ].id,
                                        value: option[ 0 ].text,
                                    } );
                                } );
                                <?php if( isset($r_val) && $r_val != '' ){?>
                                $( document ).find( '.globkurier-ruch-container .select2-selection__rendered' ).text( <?php echo wp_json_encode( $r_val, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ?> );
                                <?php } ?>
                            } );
                        } )( jQuery );
                    </script>
                </div>
                <li>
                <?php
            }


            foreach ($globKurier->settings() as $key => $method) {
                if (strpos($key, 'extra_pickup_point_') === 0) {
                    if ($method == $methodId) {
                        echo '</li><div class="globkurier-ruch-container">';
                        require 'extraPickupPointSelect.php';
                        echo '</div><li>';
                    }
                }
            }
        }
    }

    public function globkurier_woocommerce_after_checkout_validation($fields, $errors)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- walidacja checkoutu: WooCommerce weryfikuje nonce (woocommerce-process-checkout-nonce) przed tym hookiem.
        global $globKurier;

        // Metoda wybrana w checkoucie (dane WC, to samo zrodlo co przy zapisie meta), a nie ukryte pole
        // globkurier_method_id - po jego podmianie walidacja punktu byla pomijana.
        $methodId = sanitize_text_field((string) ($fields[ 'shipping_method' ][ 0 ] ?? ''));

        $globkurierInpost = [
            'active'   => $globKurier->settings('inpost_active') ?? '',
            'methodId' => $globKurier->settings('inpost_method') ?? '',
        ];

        $globkurierRuch = [
            'active'   => $globKurier->settings('ruch_active') ?? '',
            'methodId' => $globKurier->settings('ruch_method') ?? '',
        ];

        if ($globkurierInpost[ 'active' ] && $globkurierInpost[ 'methodId' ] === $methodId) {
            if (empty(sanitize_text_field(wp_unslash($_POST[ 'globkurier_inpost_input' ] ?? ''))) || empty(sanitize_text_field(wp_unslash($_POST[ 'globkurier_inpost_input_hidden_value' ] ?? '')))) {
                $errors->add('required-field', __('<strong>Paczkomat InPost</strong> jest niepoprawny', 'globkurier'));
            }
        }

        if ($globkurierRuch[ 'active' ] && $globkurierRuch[ 'methodId' ] === $methodId) {
            if (empty(sanitize_text_field(wp_unslash($_POST[ 'globkurier_ruch_input' ] ?? ''))) || empty(sanitize_text_field(wp_unslash($_POST[ 'globkurier_ruch_input_hidden_value' ] ?? '')))) {
                $errors->add('required-field', __('<strong>Punkt Orlen Paczki</strong> jest niepoprawny', 'globkurier'));
            }
        }
        // phpcs:enable
    }

    public function globkurier_woocommerce_admin_order_items_after_line_items($item_id, $item)
    {
        if ($item instanceof WC_Order_Item_Shipping) {
            global $globKurier;

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID zamowienia z adresu ekranu edycji (HPOS), tylko odczyt.
            $order_id = get_the_ID() ?: (absint($_GET[ 'id' ] ?? null) ?: null);

			if( !$order_id ){
				return;
			}
			
            $order = wc_get_order($order_id);

            $methodId = $item->get_method_id().':'.$item->get_instance_id();

            $globkurierInpost = [
                'active'   => $globKurier->settings('inpost_active') ?? '',
                'methodId' => $globKurier->settings('inpost_method') ?? '',
            ];

            $globkurierRuch = [
                'active'   => $globKurier->settings('ruch_active') ?? '',
                'methodId' => $globKurier->settings('ruch_method') ?? '',
            ];

            if ($globkurierInpost[ 'active' ] && $globkurierInpost[ 'methodId' ] === $methodId) {
                $id = $order->get_meta('globkurier_inpost_id');
                ?>
                <div class="view">
                    <table cellspacing="0" class="display_meta">
                        <tbody>
                        <tr>
                            <th><?php
                                echo esc_attr(__('Paczkomat', 'globkurier')) ?>:
                            </th>
                            <td>
                                <p><?php
                                    echo esc_attr($id) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <?php
            }

            if ($globkurierRuch[ 'active' ] && $globkurierRuch[ 'methodId' ] === $methodId) {
                $id = $order->get_meta('globkurier_ruch_id');
                ?>
                <div class="view">
                    <table cellspacing="0" class="display_meta">
                        <tbody>
                        <tr>
                            <th><?php
                                echo esc_attr(__('Punkt Paczka Orlen', 'globkurier')) ?>:
                            </th>
                            <td><p><?php
                                    echo esc_attr($id) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <?php
            }

            foreach ($globKurier->settings() as $key => $_method) {
                if (strpos($key, 'extra_pickup_point_') === 0) {
                    if ($_method == $methodId) {
                        $carrierId = \str_replace('extra_pickup_point_', '', $key);
                        $id        = $order->get_meta('globkurier_'.$carrierId.'_id') ?? '';
                        ?>
                        <div class="view">
                            <table cellspacing="0" class="display_meta">
                                <tbody>
                                <tr>
                                    <th><?php
                                        /* translators: %s: identyfikator przewoznika */
                                        echo esc_attr(sprintf(__('Punkt odbioru %s', 'globkurier'), $carrierId)) ?>:
                                    </th>
                                    <td>
                                        <p><?php
                                            echo esc_attr($id) ?></p>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    public function globkurier_woocommerce_checkout_create_order($order_id, $data)
    {
        $shipping_method = sanitize_text_field($data[ 'shipping_method' ][ 0 ] ?? '');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce weryfikuje nonce checkoutu (woocommerce-process-checkout-nonce) przed tym hookiem.
        $this->setOrderMeta($order_id, $shipping_method, map_deep(wp_unslash($_POST), 'sanitize_text_field'));
    }

    public function setOrderMeta($order_id, $methodId, $data)
    {
        global $globKurier;

        $order = wc_get_order($order_id);

        $globkurierInpost = [
            'active'   => $globKurier->settings('inpost_active') ?? '',
            'methodId' => $globKurier->settings('inpost_method') ?? '',
        ];

        $globkurierRuch = [
            'active'   => $globKurier->settings('ruch_active') ?? '',
            'methodId' => $globKurier->settings('ruch_method') ?? '',
        ];

        if ($globkurierInpost[ 'active' ] && $globkurierInpost[ 'methodId' ] === $methodId) {
            $order->update_meta_data('globkurier_inpost_id', sanitize_text_field($data[ 'globkurier_inpost_input' ] ?? ''));
            $order->update_meta_data('globkurier_inpost_value', sanitize_text_field($data[ 'globkurier_inpost_input_hidden_value' ] ?? ''));
        }

        if ($globkurierRuch[ 'active' ] && $globkurierRuch[ 'methodId' ] === $methodId) {
            $order->update_meta_data('globkurier_ruch_id', sanitize_text_field($data[ 'globkurier_ruch_input' ] ?? ''));
            $order->update_meta_data('globkurier_ruch_value', sanitize_text_field($data[ 'globkurier_ruch_input_hidden_value' ] ?? ''));
        }

        foreach ($globKurier->settings() as $key => $_method) {
            if (strpos($key, 'extra_pickup_point_') === 0) {
                if ($_method == $methodId) {
                    $carrierId = \str_replace('extra_pickup_point_', '', $key);

                    $order->update_meta_data('globkurier_'.$carrierId.'_id', sanitize_text_field($data[ 'globkurier_extra_pickup_point_'.$carrierId.'_input' ] ?? ''));
                    $order->update_meta_data('globkurier_'.$carrierId.'_input_hidden_value', sanitize_text_field($data[ 'globkurier_extra_pickup_point_'.$carrierId.'_input_hidden_value' ] ?? ''));
                    $order->update_meta_data('globkurier_extra_pickup_carrier_id', sanitize_text_field($carrierId));
                }
            }
        }

        $order->save();
    }

	public function renderReturnsBanner()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- parametry adresu ekranu ustawien tylko do decyzji o wyswietleniu banera.
		$page    = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
		$tab     = sanitize_text_field(wp_unslash($_GET['tab'] ?? ''));
		$section = sanitize_text_field(wp_unslash($_GET['section'] ?? ''));
		// phpcs:enable

		if ($page !== 'wc-settings' || $tab !== 'shipping' || $section !== 'globkurier') {
			return;
		}
		?>
		<div id="globkurier-returns-banner">
			<button type="button" class="globkurier-returns-banner__close" aria-label="<?php esc_attr_e('Zamknij', 'globkurier'); ?>">&times;</button>
			<div class="globkurier-returns-banner__icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M9 14l-4-4 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M5 10h11a4 4 0 0 1 0 8h-1" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>
			<h3 class="globkurier-returns-banner__title">
				<?php esc_html_e('Szybkie i tanie zwroty na jedno kliknięcie', 'globkurier'); ?>
				<br>
				<?php esc_html_e('w Twoim sklepie', 'globkurier'); ?>
			</h3>
			<ul class="globkurier-returns-banner__list">
				<li><?php esc_html_e('Darmowe uruchomienie i korzystanie z narzędzia', 'globkurier'); ?></li>
				<li><?php esc_html_e('Szybki i prosty proces aktywacji', 'globkurier'); ?></li>
				<li><?php esc_html_e('Lepsza kontrola nad procesami i raportowaniem', 'globkurier'); ?></li>
			</ul>
			<a href="https://zwroty.globkurier.pl/pl/zwroty-dla-sklepow-internetowych" target="_blank" rel="noopener noreferrer" class="globkurier-returns-banner__btn">
				<?php esc_html_e('DOWIEDZ SIĘ WIĘCEJ', 'globkurier'); ?>
			</a>
		</div>
		<script>
			// Bez pustych linii w tym skrypcie: checkout w bloku Shortcode przepuszcza HTML przez wpautop, ktory w pustych liniach wstawia znaczniki akapitu i psuje JS.
			document.querySelector('.globkurier-returns-banner__close')?.addEventListener('click', function() {
				document.getElementById('globkurier-returns-banner').style.display = 'none';
			});
		</script>
		<?php
	}

}