<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (WoocommerceAddons::globkurier_meta_box_content(), UDIGroup_Admin_Ajax::globkurierCreateOrdersAsync() i szablon strony nadania), zmienne sa lokalne, nie globalne.

use udigroup_globkurier\GlobKurierCountries;
use udigroup_globkurier\UDIGroup_Helper;

if (! defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

/**
 * @var int $orderId
 **/

global $globKurier;
$statusIsOk = $globKurier->isUserLoggedIn(true);

if ($statusIsOk) {
	?>

	<input type='hidden' id='globkurier_create_order_order_id' value="<?php
	echo esc_attr($orderId); ?>">
	<input type='hidden' id='globkurier_create_order_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_create_order_nonce')); ?>">
	<input type='hidden' id='globkurier_get_products_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_products_nonce')); ?>">
	<input type='hidden' id='globkurier_get_product_addons_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_product_addons_nonce')); ?>">
	<input type='hidden' id='globkurier_get_custom_required_fields_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_custom_required_fields_nonce')); ?>">
	<input type='hidden' id='globkurier_get_first_pickup_day_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_first_pickup_day_nonce')); ?>">
	<input type='hidden' id='globkurier_get_product_addon_fields_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_product_addon_fields_nonce')); ?>">
	<input type='hidden' id='globkurier_get_pickup_time_ranges_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_pickup_time_ranges_nonce')); ?>">
	<input type='hidden' id='globkurier_get_price_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_price_nonce')); ?>">
	<input type='hidden' id='globkurier_get_payments_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_payments_nonce')); ?>">
	<input type='hidden' id='globkurier_order_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_order_nonce')); ?>">
	<input type='hidden' id='globkurier_get_current_status_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_current_status_nonce')); ?>">
	<input type='hidden' id='globkurier_save_inpost_points_session_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_save_inpost_points_session_nonce')); ?>">
	<input type='hidden' id='globkurier_save_ruch_points_session_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_save_ruch_points_session_nonce')); ?>">
	<input type='hidden' id='globkurier_get_inpost_points_select2_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_inpost_points_select2_nonce')); ?>">
	<input type='hidden' id='globkurier_get_ruch_points_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_ruch_points_nonce')); ?>">
	<input type='hidden' id='globkurier_get_person_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_person_nonce')); ?>">
	<input type='hidden' id='globkurier_get_inpost_points_select2_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_inpost_points_select2_nonce')); ?>">
	<input type='hidden' id='globkurier_get_ruch_points_select2_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_get_ruch_points_select2_nonce')); ?>">
	<input type='hidden' id='globkurier_add_person_to_address_book_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_add_person_to_address_book_nonce')); ?>">
	<input type='hidden' id='globkurier_update_person_to_address_book_nonce' value="<?php
	echo esc_attr(wp_create_nonce('globkurier_update_person_to_address_book_nonce')); ?>">


	<div class="globkurier-notices-container">
		<div class="globkurier-notices-error" style="background-color: #aa00009c; padding: 10px; color: #fdf2f2; display: none">
			<div class="globkurier-notices-error-header">
				<span class="dashicons dashicons-warning"></span>
				<?php
				echo esc_attr(__('Podczas wysyłania zamówienia wystąpił błąd', 'globkurier')) ?>:
				<hr>
			</div>
			<div class="globkurier-notices-error-body" style="padding: 0 15px;"></div>
		</div>
		<div class="globkurier-notices-success" style="background-color: rgba(49,128,41,0.61); padding: 10px; color: #fdf2f2; display: none">
			<div class="globkurier-notices-success-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php
				echo esc_attr(__('Zamówienie zostało przyjęte.', 'globkurier')) ?>:
				<hr>
			</div>
			<div class="globkurier-notices-success-body" style="padding: 0 15px;"></div>
		</div>

		<div class="globkurier-address-books-error" style="background-color: #aa00009c; padding: 10px; color: #fdf2f2; display: none">
			<div class="globkurier-notices-success-header">
				<span class="dashicons dashicons-warning"></span>
				<?php
				echo esc_attr(__('Podczas dodawania do książki adresowej wystąpił błąd', 'globkurier')) ?>:
				<hr>
			</div>
			<div class="globkurier-address-books-error-body" style="padding: 0 15px;"></div>
		</div>

		<div class="globkurier-address-books-success" style="background-color: rgba(49,128,41,0.61); padding: 10px; color: #fdf2f2; display: none">
			<div class="globkurier-notices-success-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php
				echo esc_attr(__('Pomyślnie dodano do książki adresowej', 'globkurier')) ?>:
				<hr>
			</div>
			<div class="globkurier-address-books-success-body" style="padding: 0 15px;"></div>
		</div>
	</div>
	
	<?php
	
	global $wpdb;

	// Post "ghost" dla nadan bez zamowienia WC tworzy (raz) GlobKurier::getGhostPostID() - tu nie jest potrzebny.

	$defaults         = $globKurier->settings('default');
	$defaultSenderData = apply_filters('globkurier_sender_defaults', $defaults[ 'send' ] ?? []);
	$defaultParcelData = apply_filters('globkurier_parcel_defaults', $defaults[ 'parcel' ] ?? []);
	
	if (isset($orderId)) {
		$order = wc_get_order($orderId);
	}
	
	$defaultWeight = $defaults[ 'parcel' ][ 'weight' ] ?? 1;
	$defaultLength = $defaults[ 'parcel' ][ 'length' ] ?? 1;
	$defaultWidth  = $defaults[ 'parcel' ][ 'width' ] ?? 1;
	$defaultHeight = $defaults[ 'parcel' ][ 'height' ] ?? 1;
	
	$maxWeight = 1;
	$maxLength = 1;
	$maxWidth  = 1;
	$maxHeight = 1;
	
	if (isset($order)) {
		$receiverAddress = $order->get_shipping_address_1();
		
//		preg_match('/^(.*?)(?:\s+([\d].*))?$/i', trim($receiverAddress), $receiverAddressParts);
//		$receiverAddressStreet = trim($receiverAddressParts[1] ?? '');
//		$receiverAddressHome = trim($receiverAddressParts[2] ?? '');
		
		
		$parsedAddress = UDIGroup_Helper::parseAddress($receiverAddress, $order->get_shipping_country());
		$receiverAddressStreet = $parsedAddress['street'] ?? $receiverAddress ?? '';
		$receiverAddressHome = $parsedAddress['number'] ?? '';
		
		
		$sumOrderWeight = $globKurier->settings('sum_order_weight') ?? 0;
		$totalWeight = 0;
		
		$productSKUs = [];
		$productEANs = [];
		
		foreach ($order->get_items() as $item) {
			$id      = $item->get_product_id();
			$product = wc_get_product($id);
			// Wymiary i waga z wariacji - WooCommerce sam uzupelnia puste pola danymi produktu nadrzednego.
			$sizeProduct = $item->get_variation_id() ? (wc_get_product($item->get_variation_id()) ?: $product) : $product;
			$quantity = $item->get_quantity();
			
			$weight = $defaultWeight;
			$length = $defaultLength;
			$width  = $defaultWidth;
			$height = $defaultHeight;
			
			if ($product && $sizeProduct) {
				$weight = ! empty($sizeProduct->get_weight()) ? $sizeProduct->get_weight() : $defaultWeight;
				$length = ! empty($sizeProduct->get_length()) ? $sizeProduct->get_length() : $defaultLength;
				$width  = ! empty($sizeProduct->get_width()) ? $sizeProduct->get_width() : $defaultWidth;
				$height = ! empty($sizeProduct->get_height()) ? $sizeProduct->get_height() : $defaultHeight;
				
				$productSKUs[] = apply_filters('globkurier_product_sku', $product->get_sku() ?? '', $product);
				$productEANs[] = apply_filters('globkurier_product_ean', $product->get_global_unique_id() ?? '', $product);
			}
			
			if ($sumOrderWeight == 1) {
				$totalWeight += (float)$weight * $quantity;
			} else {
				$maxWeight = max($maxWeight, ! empty($weight) ? $weight : 1);
			}
			
			$maxLength = max($maxLength, ! empty($length) ? $length : 1);
			$maxWidth  = max($maxWidth, ! empty($width) ? $width : 1);
			$maxHeight = max($maxHeight, ! empty($height) ? $height : 1);
		}
		
		if ($sumOrderWeight == 1 && $totalWeight > 0) {
			$maxWeight = $totalWeight;
		}
		
		$receiverData = apply_filters('globkurier_receiver_data', [
			'name'    => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
			'company' => $order->get_shipping_company() ?? '',
			'address' => $order->get_shipping_address_1(). ' ' .$order->get_shipping_address_2(),
			'street'  => $receiverAddressStreet ?? '',
			'home'    => $receiverAddressHome ?? '',
			'flat'    => $order->get_shipping_address_2() ?? '',
			'city'    => $order->get_shipping_city() ?? '',
			'state'   => $order->get_shipping_state() ?? '',
			'postal'  => $order->get_shipping_postcode() ?? '',
			'country' => $order->get_shipping_country() ?? '',
			'email'   => $order->get_billing_email() ?? '',
			'phone'   => $order->get_billing_phone() ?? '',
		]);
		
		$wcOrderId = $orderId ?? '';
	} else {
		$wcOrderId    = null;
		$receiverData = apply_filters('globkurier_receiver_data', [
			'name'    => '',
			'company' => '',
			'address' => '',
			'street'  => '',
			'flat'    => '',
			'city'    => '',
			'state'   => '',
			'postal'  => '',
			'country' => '',
			'email'   => '',
			'phone'   => '',
		]);
		
		$maxWeight = $defaultWeight;
		$maxLength = $defaultLength;
		$maxWidth  = $defaultWidth;
		$maxHeight = $defaultHeight;
	}
	
	$defaultInpostValue = $globKurier->settings('inpost_default_code');
	$defaultInpostId    = $globKurier->settings('inpost_default');
	
	$inpostId    = isset($order) ? $order->get_meta('globkurier_inpost_id', true) : null;
	$inpostValue = isset($order) ? $order->get_meta('globkurier_inpost_value', true) : null;
	
	$ruchId    = isset($order) ? $order->get_meta('globkurier_ruch_id', true) : null;
	$ruchValue = isset($order) ? $order->get_meta('globkurier_ruch_value', true) : null;
	
	$extraPickupCarrierId          = isset($order) ? $order->get_meta('globkurier_extra_pickup_carrier_id', true) : null;
	$extraPickupCarrierValue       = null;
	$extraPickupCarrierHiddenValue = null;
	if ($extraPickupCarrierId) {
		$extraPickupCarrierValue       = isset($order) ? $order->get_meta('globkurier_'.$extraPickupCarrierId.'_id', true) : null;
		$extraPickupCarrierHiddenValue = isset($order) ? $order->get_meta('globkurier_'.$extraPickupCarrierId.'_input_hidden_value', true) : null;
	}
	
	$pointType = '';
	$pointCode = '';
	$pointName = '';
	$pointText = '';
	
	if (! empty($inpostId) && ! empty($inpostValue)) {
		$pointType = 'inpost';
		$pointCode = $inpostValue;
		$pointName = $inpostId;
		$pointText = __('Paczkomat InPost', 'globkurier').': '.$inpostId;
	}
	
	if (! empty($ruchId) && ! empty($ruchValue)) {
		$pointType = 'ruch';
		$pointCode = $ruchValue;
		$pointName = $ruchId;
		$pointText = __('ORLEN Paczka', 'globkurier').': '.$ruchId;
	}
	
	if (! empty($extraPickupCarrierId) && ! empty($extraPickupCarrierValue)) {
		$pointType = $extraPickupCarrierId;
		$pointCode = $extraPickupCarrierValue;
		$pointName = $extraPickupCarrierId;
		$pointText = ucfirst((string) $extraPickupCarrierId).': '.$extraPickupCarrierValue;
	}
	
	$addSKUToContent = $globKurier->settings('content_add_sku') ?? 0;
	$addEANToContent = $globKurier->settings('content_add_ean') ?? 0;
	
	?>

	<input type="hidden" id="udi-wc-order-id" value="<?php
	echo esc_attr($wcOrderId) ?>">

	<div class="udi-order-confirm-container" style="display:none;">

		<div class="udi-loader-overlay" style="display:none; background-color: rgba(211, 211, 211, 0.59);width: 100%;height: 100%;left: 0;position: absolute;"></div>
		<div class="udi-loader" style="width: 100em; height: 100em; margin: -5em; border-width: 5em; position: absolute; top: calc(50% - 50em); left: calc(50% - 50em);"></div>

		<h1><?php
			echo esc_attr(__('Podsumowanie zamówienia', 'globkurier')) ?></h1>
		<p><?php
			echo esc_attr(__('Prosimy o wydruk listu przewozowego, który zostanie wysłany na adres e-mail Zleceniodawcy i Nadawcy. Numer listu umożliwi monitorowanie losów Twojej przesyłki. Przekazując kurierowi wydrukowany list przewozowy <strong>nie poniesiesz dodatkowych kosztów</strong>.',
				'globkurier')) ?></p>
		<p class="udi-info-p"><?php
			echo esc_attr(__('Przed zatwierdzeniem prosimy dokładnie zweryfikować dane.', 'globkurier')) ?></p>
		<div class="udi-order-confirm-address">
			<div class="udi-wpadmin-order-address-col">
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('NADAWCA', 'globkurier')) ?>:
				</div>
				<div>
					<div>
						<span id="udi-confirm-address-sender-name"></span>
					</div>
					<div>
						<span id="udi-confirm-address-sender-street"></span>
						<span id="udi-confirm-address-sender-homeNumber"></span>
						<span id="udi-confirm-address-sender-flatNumber"></span>
					</div>
					<div>
						<span id="udi-confirm-address-sender-city"></span>
						<span id="udi-confirm-address-sender-postal"></span>
					</div>
					<div>
						<span id="udi-confirm-address-sender-flnames"></span>
					</div>
					<div>
						<?php
						echo esc_attr(__('tel.', 'globkurier')) ?>
						<span id="udi-confirm-address-sender-phone"></span>
					</div>
					<div>
						<span id="udi-confirm-address-sender-email"></span>
					</div>
				</div>
			</div>
			<div class="udi-wpadmin-order-address-col">
				<?php
				do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('ODBIORCA', 'globkurier')) ?>:
				</div>

				<div>
					<div>
						<span id="udi-confirm-address-receiver-name"></span>
					</div>
					<div>
						<span id="udi-confirm-address-receiver-street"></span>
						<span id="udi-confirm-address-receiver-homeNumber"></span>
						<span id="udi-confirm-address-receiver-flatNumber"></span>
					</div>
					<div>
						<span id="udi-confirm-address-receiver-city"></span>
						<span id="udi-confirm-address-receiver-postal"></span>
					</div>
					<div>
						<span id="udi-confirm-address-receiver-flnames"></span>
					</div>
					<div>
						<?php
						echo esc_attr(__('tel.', 'globkurier')) ?>
						<span id="udi-confirm-address-receiver-phone"></span>
					</div>
					<div>
						<span id="udi-confirm-address-receiver-email"></span>
					</div>
				</div>
			</div>
		</div>
		<div class="udi-order-confirm-product">
			<div class="udi-wpadmin-order-address-col">
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('PRODUKT', 'globkurier')) ?>:
				</div>
				<div>
					<div><span id="udi-confirm-product-name"></span>
						<span id="udi-confirm-product-price"></span></div>
					<div id="udi-confirm-product-count"><?php
						echo esc_attr(__('Liczba paczek', 'globkurier')) ?>:
						<span class="udi-confirm-value"></span></div>
					<div id="udi-confirm-product-terminal"><?php
						echo esc_attr(__('Nadanie przesyłki w terminalu.', 'globkurier')) ?></div>
					<div id="udi-confirm-product-date"><?php
						echo esc_attr(__('Nadanie w dniu', 'globkurier')) ?>:
						<span class="udi-confirm-value"></span></div>
					<div id="udi-confirm-product-time"><?php
						echo esc_attr(__('Nadanie w godzinach', 'globkurier')) ?>:
						<span class="udi-confirm-value"></span></div>
					<div id="udi-confirm-product-content"><?php
						echo esc_attr(__('Zawartość przesyłki', 'globkurier')) ?>:
						<span class="udi-confirm-value"></span></div>
				</div>
			</div>
			<div class="udi-wpadmin-order-address-col">
				<?php
				do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('DODATKI', 'globkurier')) ?>:
				</div>

				<div class="udi-confirm-extras"></div>
			</div>
		</div>
		<div class="udi-order-confirm-payment">

			<div class="udi-wpadmin-order-address-col">
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('PŁATNOŚĆ', 'globkurier')) ?>:
				</div>

				<div>
					<div>
						<?php
						echo esc_attr(__('Forma płatności', 'globkurier')) ?>:
						<span id="udi-confirm-payment-method"></span>
					</div>
				</div>
			</div>
			<div class="udi-wpadmin-order-address-col">
				<?php
				do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('ŁĄCZNIE DO ZAPŁATY', 'globkurier')) ?>:
				</div>
				<div>
					<h1 id="udi-confirm-payment-total-price"></h1>
				</div>
			</div>
		</div>
		<div class="udi-order-confirm-container-buttons">
			<button type="button" class="button-secondary globkurier_confirm_correction"><?php
				echo esc_attr(__('Popraw dane', 'globkurier')) ?></button>
			<button type="button" class="button-primary globkurier_confirm_send"><?php
				echo esc_attr(__('Zatwierdź i zapłać', 'globkurier')) ?></button>
		</div>
	</div>

	<div class="udi-wpadmin-order-address">
		<div class="udi-wpadmin-order-address-col">
			<?php
			do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>

			<div class="udi-wpadmin-order-address-col" style="border: 0; ">
				<div style="display: flex; align-items: flex-end; flex-wrap: nowrap;">
					<span class="dashicons dashicons-search"></span>

					<select style="width: 100%" class="udi-is-select2" data-type="senders" id="globkurier-find-sender">
						<option disabled selected><?php
							echo esc_attr(__('Wyszukaj nadawcę zdefiniowanego w książce adresowej', 'globkurier')) ?></option>
					</select>
					<input type="hidden" class="globkurier-person-id" id="globkurier-sender-id">
				</div>
			</div>

			<br><br>

			<div class="udi-product-extras-header">
				<?php
				echo esc_attr(__('NADAWCA', 'globkurier')) ?>:
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-sender-name"><?php
					echo esc_attr(__('Nazwa', 'globkurier')) ?>:</label>
				<input name="globkurier-sender-name" type="text" id="globkurier-sender-name" maxlength="30" placeholder="<?php
				echo esc_attr(__('Nazwa', 'globkurier')) ?>" value="<?php
				echo esc_attr($defaultSenderData[ 'flnames' ] ?? '') ?>">
			</div>

			<div style="display: grid; grid-template-columns: auto auto 1fr">
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-sender-street"><?php
						echo esc_attr(__('Ulica', 'globkurier')) ?>:</label>
					<input name="globkurier-sender-street" id="globkurier-sender-street" minlength="3" maxlength="35" placeholder="<?php
					echo esc_attr(__('Ulica', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($defaultSenderData[ 'street' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-sender-home"><?php
						echo esc_attr(__('Nr domu', 'globkurier')) ?>:</label>
					<input name="globkurier-sender-home" id="globkurier-sender-home" maxlength="8" placeholder="<?php
					echo esc_attr(__('Nr domu', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($defaultSenderData[ 'homeNumber' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-sender-flat"><?php
						echo esc_attr(__('Nr lokalu', 'globkurier')) ?>:</label>
					<input name="globkurier-sender-flat" id="globkurier-sender-flat" maxlength="20" placeholder="<?php
					echo esc_attr(__('Nr lokalu', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($defaultSenderData[ 'flatNumber' ] ?? '') ?>">
				</div>
			</div>

			<div style="display: grid; grid-template-columns: auto auto 1fr">
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-sender-postal"><?php
						echo esc_attr(__('Kod Pocztowy', 'globkurier')) ?>:</label>
					<input name="globkurier-sender-postal" id="globkurier-sender-postal" placeholder="<?php
					echo esc_attr(__('Kod Pocztowy', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($defaultSenderData[ 'postal' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-sender-city"><?php
						echo esc_attr(__('Miasto', 'globkurier')) ?>:</label>
					<input name="globkurier-sender-city" id="globkurier-sender-city" placeholder="<?php
					echo esc_attr(__('Miasto', 'globkurier')) ?>" type="text" style="" value="<?php
					echo esc_attr($defaultSenderData[ 'city' ] ?? '') ?>">
				</div>
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-sender-country"><?php
					echo esc_attr(__('Kraj', 'globkurier')) ?>:</label>
				<select style="max-width: 100%" class="udi-is-select2" name="globkurier-sender-country" id="globkurier-sender-country" placeholder="Kraj nadawcy">
					<option disabled selected><?php
						echo esc_attr(__('Kraj', 'globkurier')) ?></option>
					<?php
					echo wp_kses($globKurier->countries()->getDropdown($defaultSenderData[ 'country' ] ?? null), GlobKurierCountries::DROPDOWN_ALLOWED_HTML); ?>
				</select>
			</div>

			<span style="font-weight: 500;"><?php
				echo esc_attr(__('Osoba kontaktowa', 'globkurier')) ?>:</span>
			<div class="udi-input-with-hidden-label">
				<label for="globkurier-sender-contact-"><?php
					echo esc_attr(__('Nazwa', 'globkurier')) ?>:</label>
				<input name="globkurier-sender-contact-name" id="globkurier-sender-contact-name" placeholder="<?php
				echo esc_attr(__('Nazwa', 'globkurier')) ?>" type="text" value="<?php
				echo esc_attr($defaultSenderData[ 'flnames' ] ?? '') ?>">
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-sender-contact-phone"><?php
					echo esc_attr(__('Telefon', 'globkurier')) ?>:</label>
				<input name="globkurier-sender-contact-phone" id="globkurier-sender-contact-phone" placeholder="<?php
				echo esc_attr(__('Telefon', 'globkurier')) ?>" type="text" value="<?php
				echo esc_attr($defaultSenderData[ 'phone' ] ?? '') ?>">
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-sender-email"><?php
					echo esc_attr(__('E-mail', 'globkurier')) ?>:</label>
				<input name="globkurier-sender-email" id="globkurier-sender-email" placeholder="<?php
				echo esc_attr(__('E-mail', 'globkurier')) ?>" type="email" value="<?php
				echo esc_attr($defaultSenderData[ 'email' ] ?? '') ?>">
			</div>

			<div style="display:flex; flex-wrap: wrap; margin-top: 20px">
				<button class="button-secondary globkurier-add-person-to-address-book" data-type="sender" id="globkurier-add-sender-to-address-book" style="display: none" type="button"><?php
					echo esc_attr(__('Dodaj nadawcę do książki adresowej', 'globkurier')) ?></button>
				<button class="button-secondary globkurier-update-person-to-address-book" data-type="sender" id="globkurier-update-sender-to-address-book" style="display: none" type="button"><?php
					echo esc_attr(__('Aktualizuj istniejącego nadawcę', 'globkurier')) ?></button>
			</div>
			
			<?php
			do_action('globkurier_after_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
		</div>

		<div class="udi-wpadmin-order-address-col">
			<?php
			do_action('globkurier_before_receiver_data', $order ?? '', $defaultSenderData, $receiverData) ?>

			<div class="udi-wpadmin-order-address-col" style="border: 0">
				<div style="display: flex; align-items: flex-end; flex-wrap: nowrap;">
					<span class="dashicons dashicons-search"></span>
					<select style="width: 100%" class="udi-is-select2" data-type="receivers" id="globkurier-find-receiver">
						<option disabled selected><?php
							echo esc_attr(__(' Wyszukaj odbiorcę zdefiniowanego w książce adresowej', 'globkurier')) ?></option>
					</select>
					<input type="hidden" class="globkurier-person-id" id="globkurier-receiver-id">
				</div>
			</div>

			<br><br>

			<div class="udi-product-extras-header">
				<?php
				echo esc_attr(__('ODBIORCA', 'globkurier')) ?>:
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-receiver-name"><?php
					echo esc_attr(__('Nazwa', 'globkurier')) ?>:</label>
				<input name="globkurier-receiver-name" type="text" id="globkurier-receiver-name" maxlength="30" placeholder="<?php
				echo esc_attr(__('Nazwa', 'globkurier')) ?>" value="<?php
				echo esc_attr($receiverData[ 'name' ] ?? '') ?>">
			</div>

			<div style="display: grid; grid-template-columns: auto auto 1fr">
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-receiver-street"><?php
						echo esc_attr(__('Ulica', 'globkurier')) ?>:</label>
					<input name="globkurier-receiver-street" maxlength="35" minlength="3" id="globkurier-receiver-street" placeholder="<?php
					echo esc_attr(__('Ulica', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($receiverData[ 'street' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-receiver-home"><?php
						echo esc_attr(__('Nr domu', 'globkurier')) ?>:</label>
					<input name="globkurier-receiver-home" maxlength="8" id="globkurier-receiver-home" placeholder="<?php
					echo esc_attr(__('Nr domu', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($receiverData[ 'home' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-receiver-flat"><?php
						echo esc_attr(__('Nr lokalu', 'globkurier')) ?>:</label>
					<input name="globkurier-receiver-flat" maxlength="20" id="globkurier-receiver-flat" placeholder="<?php
					echo esc_attr(__('Nr lokalu', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($receiverData[ 'flat' ] ?? '') ?>">
				</div>
			</div>

			<div style="display: grid; grid-template-columns: auto auto 1fr">
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-receiver-postal"><?php
						echo esc_attr(__('Kod Pocztowy', 'globkurier')) ?>:</label>
					<input name="globkurier-receiver-postal" id="globkurier-receiver-postal" placeholder="<?php
					echo esc_attr(__('Kod Pocztowy', 'globkurier')) ?>" type="text" value="<?php
					echo esc_attr($receiverData[ 'postal' ] ?? '') ?>">
				</div>
				<div class="udi-input-with-hidden-label">
					<label for="globkurier-receiver-city"><?php
						echo esc_attr(__('Miasto', 'globkurier')) ?>:</label>
					<input name="globkurier-receiver-city" id="globkurier-receiver-city" maxlength="35" placeholder="<?php
					echo esc_attr(__('Miasto', 'globkurier')) ?>" type="text" style="" value="<?php
					echo esc_attr($receiverData[ 'city' ] ?? '') ?>">
				</div>
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-receiver-country"><?php
					echo esc_attr(__('Kraj', 'globkurier')) ?>:</label>
				<select style="max-width: 100%" class="udi-is-select2" name="globkurier-receiver-country" id="globkurier-receiver-country" placeholder="<?php
				echo esc_attr(__('Kraj odbiorcy', 'globkurier')) ?>">
					<option disabled selected><?php
						echo esc_attr(__('Kraj', 'globkurier')) ?></option>
					<?php
					echo wp_kses($globKurier->countries()->getDropdown($receiverData[ 'country' ] ?? null, 'code'), GlobKurierCountries::DROPDOWN_ALLOWED_HTML); ?>
				</select>
			</div>

			<span style="font-weight: 500;"><?php
				echo esc_attr(__('Osoba kontaktowa', 'globkurier')) ?>:</span>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-receiver-contact-"><?php
					echo esc_attr(__('Nazwa', 'globkurier')) ?>:</label>
				<input name="globkurier-receiver-contact-name" id="globkurier-receiver-contact-name" placeholder="<?php
				echo esc_attr(__('Nazwa', 'globkurier')) ?>" type="text" value="<?php
				echo esc_attr($receiverData[ 'name' ] ?? '') ?>">
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-receiver-contact-phone"><?php
					echo esc_attr(__('Telefon', 'globkurier')) ?>:</label>
				<input name="globkurier-receiver-contact-phone" id="globkurier-receiver-contact-phone" placeholder="<?php
				echo esc_attr(__('Telefon', 'globkurier')) ?>" type="text" value="<?php
				echo esc_attr($receiverData[ 'phone' ] ?? '') ?>">
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-receiver-email"><?php
					echo esc_attr(__('E-mail', 'globkurier')) ?>:</label>
				<input name="globkurier-receiver-email" id="globkurier-receiver-email" placeholder="<?php
				echo esc_attr(__('E-mail', 'globkurier')) ?>" type="email" value="<?php
				echo esc_attr($receiverData[ 'email' ] ?? '') ?>">
			</div>

			<div style="display:flex; flex-wrap: wrap; margin-top: 20px">
				<button class="button-secondary globkurier-add-person-to-address-book" data-type="receiver" id="globkurier-add-receiver-to-address-book" type="button"><?php
					echo esc_attr(__('Dodaj nadawcę do książki adresowej', 'globkurier')) ?></button>
				<button class="button-secondary globkurier-update-person-to-address-book" data-type="receiver" id="globkurier-update-receiver-to-address-book" style="display: none" type="button"><?php
					echo esc_attr(__('Aktualizuj istniejącego odbiorcę', 'globkurier')) ?></button>
			</div>
			
			<?php
			do_action('globkurier_after_receiver_data', $order ?? '', $defaultSenderData, $receiverData) ?>
		</div>
	</div>

	<div class="udi-step-select-product">
		<h4><?php
			echo esc_attr(__('Podaj wymiary i wagę przesyłki', 'globkurier')) ?>:</h4>

		<div class="globkurier-parcel-details" style="display: flex; align-items: baseline; flex-wrap: wrap;">

			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-length"><?php
					echo esc_attr(__('Długość [cm]', 'globkurier')) ?>:</label>
				<input type="number" name="globkurier[length]" id="globkurier-length" class="onlyDecimal max10" placeholder="<?php
				echo esc_attr(__('Długość [cm]', 'globkurier')) ?>" value="<?php
				echo esc_attr($maxLength ?? 1) ?>" min="1" style="margin: 0 10px;">
			</div>

			<span>x</span>

			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-width"><?php
					echo esc_attr(__('Szerokość [cm]', 'globkurier')) ?>:</label>
				<input type="number" name="globkurier[ width ]" id="globkurier-width" class="onlyDecimal max10" placeholder=" <?php
				echo esc_attr(__('Szerokość [cm]', 'globkurier')) ?>" value="<?php
				echo esc_attr($maxWidth ?? 1) ?>" min="1" style="margin: 0 10px">
			</div>

			<span>x</span>

			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-height"><?php
					echo esc_attr(__('Wysokość [cm]', 'globkurier')) ?>:</label>
				<input type="number" name="globkurier[height]" id="globkurier-height" class="onlyDecimal max10" placeholder="<?php
				echo esc_attr(__('Wysokość [cm]', 'globkurier')) ?>" value="<?php
				echo esc_attr($maxHeight ?? 1) ?>" min="1" style="margin: 0 10px;">
			</div>

			<span>x</span>

			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-weight"><?php
					echo esc_attr(__('Waga [kg]', 'globkurier')) ?>:</label>
				<input type="number" name="globkurier[weight]" id="globkurier-weight" class="max10" placeholder="<?php
				echo esc_attr(__('Waga [kg]', 'globkurier')) ?>" value="<?php
				echo esc_attr($maxWeight ?? 1) ?>" min="1" style="margin: 0 10px;">
			</div>
		</div>

		<h4><?php
			echo esc_attr(__('Zawartość', 'globkurier')) ?>:</h4>
		<div class="globkurier-parcel-content" style="display: flex; align-items: baseline;flex-wrap: wrap;">
			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-content"><?php
					echo esc_attr(__('Zawartość', 'globkurier')) ?>:</label>

				<select style="margin: 0 10px" name="globkurier[content]" id="globkurier-content" placeholder="Zawartość">
					<?php
					
					foreach ($globKurier->contentsList() as $content) {
						$selected = '';
						if ($content == $defaultParcelData[ 'content' ]) {
							$selected = 'selected';
						}
						
						echo '<option value="'.esc_attr($content).'" '.esc_attr($selected).'>'.esc_attr($content).'</option>';
					}
					?>
				</select>
			</div>

			<div class="udi-input-with-hidden-label">
				<label for="globkurier-content"><?php
					echo esc_attr(__('Inna zawartość - Jaka?', 'globkurier')) ?>:</label>
				<input type="text" name="globkurier[otherContent]" id="globkurier-otherContent" placeholder="<?php
				echo esc_attr(__('Inna zawartość - Jaka?', 'globkurier')) ?>" minlength="3" maxlength="20" value="<?php
				echo esc_attr($defaultParcelData[ 'otherContent' ] ?? '') ?>"
					<?php
					if (($defaultParcelData[ 'content' ] ?? '') !== 'Inne' ?? false) {
						?>
						style="display: none"
						<?php
					}
					?>>
			</div>

			<span>:</span>
			<div class="udi-input-with-hidden-label">
				<label style="left: 15px;" for="globkurier-quantity"><?php
					echo esc_attr(__('Ilość', 'globkurier')) ?>:</label>
				<input type="number" name="globkurier[quantity]" id="globkurier-quantity" placeholder="<?php
				echo esc_attr(__('Ilość', 'globkurier')) ?>" value="<?php
				echo esc_attr($defaultParcelData[ 'quantity' ] ?? 1) ?>" min="1" max="10" maxvalue="10" oninput="this.value=this.value.replace(/[^0-9]/g,''); if( this.value > 10  ) this.value = 10;   "
				       style="margin: 0 10px">
			</div>
		</div>
	
		<?php
		$skuContent = '';
			
			if($addSKUToContent){
				$productSKUs = array_filter($productSKUs ?? []);
				if(! empty($productSKUs) ) {
					$skuContent .= implode(', ', $productSKUs);
				}
			}
		
			if($addEANToContent) {
				$productEANs = array_filter($productEANs ?? []);
				if (! empty($productEANs)) {
					if (! empty($skuContent)) {
						$skuContent .= ' ; ';
					}
					
					$skuContent .= implode(', ', $productEANs);
				}
			}
		
		$skuContent = apply_filters('globkurier_sku_content', $skuContent, $order ?? null);
		?>
		
		<input type="hidden" name="globkurier[sku_content]" id="sku_content" value="<?php echo esc_attr($skuContent) ?>"/>
		
		<br>
		
		<?php
		if (! empty($pointType)) {
			?>
			<div class="globkurier_pickup_container">
				<input type="hidden" id="globkurier_is_pickup_active" value="1">
				<input type="hidden" id="globkurier_pickup_type" value="<?php
				echo esc_attr($pointType) ?>">

				<input type='hidden' id='globkurier_extraPickupCarrierId' value="<?php
				echo esc_attr($extraPickupCarrierId) ?>">
				<input type='hidden' id='globkurier_extraPickupCarrierText' value="<?php
				echo esc_attr($extraPickupCarrierValue) ?>">
				<input type='hidden' id='globkurier_extraPickupCarrierValue' value="<?php
				echo esc_attr($extraPickupCarrierHiddenValue) ?>">

				<div class="globkurier_pickup_container-header">
					<span><?php
						echo esc_attr(__('ODBIÓR W PUNKCIE', 'globkurier')) ?></span>
				</div>
				<div class="globkurier_pickup_container-body">
					<p><?php
						echo esc_attr(__('Twój klient wybrał odbiór w punkcie', 'globkurier')) ?>:</p>
					<span class="globkurier_pickup_container-details"> <?php
						echo esc_attr($pointText) ?> </span>
					<p><?php
						echo esc_attr(__('W tym wypadku w wycenie będą zawarte tylko usługi pasujące do tego punktu Jeśli chcesz jednak, aby wyświetlane były wszystkie usługi zaznacz opcję poniżej', 'globkurier')) ?>
						:</p>
					<div class="globkurier_pickup_container-buttons">
						<input id="globkurier_show_all_providers" type="checkbox">
						<label for="globkurier_show_all_providers"><?php
							echo esc_attr(__('Pokaż wszystkich przewoźników', 'globkurier')) ?></label>
					</div>
				</div>
			</div>
			<?php
		} else {
			?>
			<input type="hidden" id="globkurier_is_pickup_active" value="0">
			<?php
		}
		?>

		<div class="globkurier_get_products-container">
			<button class="button-primary globkurier_get_products" type="button">
				<?php
				echo esc_attr(__('Wyceń', 'globkurier')) ?>
				<div class="udi-loader"></div>
			</button>
		</div>

		<div class="globkurier_edit_data-container" style="display: none">
			<button class="button-primary globkurier_edit_data" type="button">
				<?php
				echo esc_attr(__('Edytuj dane', 'globkurier')) ?>
			</button>
		</div>

		<div class="udi-best-price-products">
		</div>

		<div class="udi-all-products"></div>
	</div>

	<div class="udi-step-select-product-details" style="display: none">

		<div class="udi-step-select-product-details-left">
			<div class="udi-selected-product">
				<input type="hidden" id="udi-selected-product-is-inpost">
				<input type="hidden" id="udi-selected-product-inpost-delivery-to-point">
				<input type="hidden" id="udi-selected-product-is-ruch">
				<input type="hidden" id="udi-selected-product-is-crossborder">
				
				<input type="hidden" id="udi-selected-product-is-extra-pickup-point">

				<input type="hidden" id="udi-selected-product-id">

				<div class="udi-selected-product-header">
					<div>
						<?php
						echo esc_attr(__('WYBRANY PRZEWOŹNIK', 'globkurier')) ?>:
						<small class="udi-selected-product-body-name">Paczka w RUCHu</small>
					</div>
					<button type="button" class="button re-select-product"><?php
						echo esc_attr(__('Zmień', 'globkurier')) ?></button>
				</div>

				<div class="udi-selected-product-body">
					<div class="udi-selected-product-body-container">
						<div class="udi-selected-product-body-logo">
							<img alt="Globkurier" class="udi-product-img">
							<span class="udi-selected-product-body-name"> </span>
						</div>
						<div class="udi-selected-product-body-price"><?php
							echo esc_attr(__('Koszt', 'globkurier')) ?>:
							<span class="udi-product-price"></span>
							<span> / </span>
							<span class="udi-product-price-gross"></span>
							<input type="hidden" id="udi-carrierNetPriceWithAddons">
							<input type="hidden" id="udi-carrierGrossPriceWithAddons">
							<input type="hidden" id="udi-carrierNetPrice">
							<input type="hidden" id="udi-carrierGrossPrice">
						</div>
					</div>
					<div class="udi-selected-product-description"></div>
				</div>
				<div class="udi-selected-product-footer"></div>
			</div>

			<div class="udi-product-extras">
				<div class="udi-product-extras-header">
					<?php
					echo esc_attr(__('OPCJE DODATKOWE', 'globkurier')) ?>:
					<div class="udi-loader">...</div>
				</div>
				<input type="hidden" id="globkurier-requiredAlternativeAddonsGroups">
				<div class="udi-product-extras-body" style="display:none;">

					<div class="udi-product-extras-category" id="udi-extra-category-receiver">
						<div class="udi-product-extras-category-header">
							<?php
							echo esc_attr(__('Odbiorca', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-cod">
						<div class="udi-product-extras-category-header">
							<?php
							echo esc_attr(__('Pobranie COD', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-insurance">
						<div class="udi-product-extras-category-header">
							<?php
							echo esc_attr(__('Ubezpieczenie', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-other">
						<div class="udi-product-extras-category-header">
							<?php
							echo esc_attr(__('Inne', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

				</div>
			</div>
		</div>

		<div class="udi-step-select-product-details-right">
			<div class="udi-service-options">
				<div class="udi-service-options-header"><?php echo esc_attr(__('Opcje nadania', 'globkurier')) ?>:</div>
				<div class="udi-service-options-body">

					<div style="display: contents">
						<div class=udi-options-body-row" style="display: contents;">
							<label class="udi-options-body-label"><?php echo esc_attr(__('Nadanie przesyłki', 'globkurier')) ?>:</label>
							<div class="" style="margin-left: 10px;">
								<input type="radio" id="globkurier-pickup-type-PICKUP" data-pickuptype="PICKUP" name="globkurier-pickup-type" value="PICKUP">
								<label for="globkurier-pickup-type-PICKUP"><?php echo esc_attr(__('Przesyłkę odbierze kurier', 'globkurier')) ?></label><br>
								
								<input type="radio" id="globkurier-pickup-type-POINT" data-pickuptype="POINT" name="globkurier-pickup-type" value="POINT">
								<label for='globkurier-pickup-type-POINT'><?php echo esc_attr(__('Nadam przesyłkę w terminalu', 'globkurier')) ?></label><br>
								
								<input type="radio" id="globkurier-pickup-type-CROSSBORDER" data-pickuptype="CROSSBORDER" name="globkurier-pickup-type" value="CROSSBORDER">
								<label for="globkurier-pickup-type-POINT"><?php echo esc_attr(__('Nadam przesyłkę w terminalu', 'globkurier')) ?></label><br>
							</div>
						</div>

						<div class="globkurier-not-pickup udi-options-body-row" style="display: contents;">
							<label class="udi-options-body-label" for="globkurier-service-date-picker"><?php echo esc_attr(__('Data nadania', 'globkurier')) ?>:</label>
							<div class="udi-input-with-notice">
								<input type="text" style="flex-basis: 100%; background-color: inherit !important;" id="globkurier-service-date-picker" readonly>
								<small><?php echo esc_attr(__('W jakim dniu kurier ma przyjechać po paczkę?', 'globkurier')) ?></small>
							</div>
						</div>

						<div class="globkurier-not-pickup" style="display: contents;">
							<label for="globkurier-service-time-picker" class="udi-options-body-label"><?php
								echo esc_attr(__('Godzina nadania', 'globkurier')) ?>:</label>
							<div class="udi-input-with-notice">
								<select name="globkurier-service-time-picker" id="globkurier-service-time-picker"></select>
							</div>
						</div>

						<div class="globkurier-only-ruch">
							<label for="globkurier_ruch_input" class="udi-options-body-label"><?php
								echo esc_attr(__('ORLEN Paczka punkt odbioru', 'globkurier')) ?>:</label>

							<div class="udi-input-with-notice">
								<select style="width: 100%" class="udi-select2" id="udi-select-ruch" name="globkurier_ruch_input_value">
									<?php
									if (isset($ruchId)) {
										echo "<option value='".esc_attr($ruchValue)."' selected>".esc_attr($ruchId)."</option>";
									}
									?>
								</select>
								<input type="hidden" class="globkurier-is-required" name="globkurier_ruch_input" id="globkurier_ruch_input" value="<?php
								echo esc_attr($ruchId ?? '') ?>">
							</div>

						</div>

						<div class="globkurier-only-inpost">
							<label for="globkurier_inpost_input" class="udi-options-body-label"><?php
								echo esc_attr(__('InPost punkt nadania', 'globkurier')) ?>:</label>

							<div class="udi-input-with-notice">
								<select type="text" style="width: 100%" class=" udi-select2" id="udi-select-inpost-sender" name="globkurier_inpost_input_sender_value">
									<?php
									if (isset($defaultInpostId)) {
										echo "<option value='".esc_attr($defaultInpostValue)."' selected>".esc_attr($defaultInpostId)."</option>";
									}
									?>
								</select>
								<input type="hidden" name="globkurier_inpost_input" id="globkurier_inpost_input" value="<?php
								echo esc_attr($defaultInpostId ?? '') ?>">
							</div>
						</div>

						<div class="globkurier-only-inpost inpost-always">
							<label for="globkurier_inpost_input" class="udi-options-body-label"><?php
								echo esc_attr(__('InPost punkt odbioru', 'globkurier')) ?>:</label>

							<div class="udi-input-with-notice">

								<select type="text" style="width: 100%" class=" udi-select2" id="udi-select-inpost-pickup_value" name="globkurier_inpost_input_value">
									<?php
									if (isset($inpostId)) {
										echo "<option value='".esc_attr($inpostValue)."' selected>".esc_attr($inpostId)."</option>";
									}
									?>
								</select>

								<input type="hidden" name="globkurier_inpost_input-pickup" id="globkurier_inpost_input-pickup" value="<?php
								echo esc_attr($inpostId ?? '') ?>">
							</div>
						</div>

						<div class='globkurier-only-crossborder' style="display: contents">
							<label for='globkurier_inpost_input' class='udi-options-body-label'><?php
								echo esc_attr(__('Terminal nadawczy', 'globkurier')) ?>:</label>

							<div class='udi-input-with-notice'>
								<select type='text' style='width: 100%' class=' udi-select2' id='udi-select-crossborder_terminal_value' name='globkurier_crossborder_terminal_input_value'>
								
								</select>
							</div>
						</div>
						

					</div>

					<div id="globkurier-custom-fields-required" style="display: contents;"></div>


					<div id="globkurier-service-extra-fields-INSURANCE" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-CASH_ON_DELIVERY" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-RETURN_OF_DOCUMENTS" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-SENDER_WAYBILL_ADDRESS" style="display: contents;"></div>

					<div style="display: contents;">
						<label for="globkurier-service-payment-picker" class="udi-options-body-label""><?php
						echo esc_attr(__('Płatność', 'globkurier')) ?>:</label>
						<div class="udi-input-with-notice">
							<select style="width: 100%" name="globkurier-service-payment-picker" id="globkurier-service-payment-picker"></select>
						</div>
					</div>

					<div style="display: contents;">
						<label for="globkurier-contains-dangerous-goods" class="udi-options-body-label"><?php
						echo esc_attr(__('Towary niebezpieczne', 'globkurier')) ?>:</label>
						<div class="udi-input-with-notice">
							<label>
								<input type="checkbox" name="globkurier-contains-dangerous-goods" id="globkurier-contains-dangerous-goods" value="1">
								<?php
								echo esc_attr(__('Przesyłka zawiera towary niebezpieczne', 'globkurier')) ?>
							</label>
						</div>
					</div>

					<div style="display: contents;">
						<button style="width: max-content; justify-self: flex-end; grid-column-start: 2;" type="button" class="button button-primary udi-save-order">
							<?php
							echo esc_attr(__('Potwierdzam i składam zamówienie', 'globkurier')) ?>
							<div class="udi-loader"></div>
						</button>
					</div>

				</div>
			</div>
		</div>

	</div>
	
	<?php
} else {
	return false;
}