<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- szablon dolaczany wewnatrz funkcji (BulkSend::open_modal()), zmienne sa lokalne, nie globalne.
/**
 * @var array $orders
 * @var GlobKurier $globKurier
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use udigroup_globkurier\UDIGroup_Helper;

$ordersWithLinks = array_map(function ($order){
	return '<a href="'.esc_url($order->get_edit_order_url()).'" target="_blank">'
		   .'#'.esc_html($order->get_order_number().' ('.$order->get_shipping_method().')')
		   .'</a>';
}, $orders);

$defaults = $globKurier->settings('default');

$defaultSenderData = apply_filters('globkurier_sender_defaults', $defaults[ 'send' ] ?? []);
$defaultParcelData = apply_filters('globkurier_parcel_defaults', $defaults[ 'parcel' ] ?? []);

$defaultInpostValue = $globKurier->settings('inpost_default_code');
$defaultInpostId    = $globKurier->settings('inpost_default');

$defaultWeight   = $defaults[ 'parcel' ][ 'weight' ] ?? 1;
$defaultLength   = $defaults[ 'parcel' ][ 'length' ] ?? 1;
$defaultWidth    = $defaults[ 'parcel' ][ 'width' ] ?? 1;
$defaultHeight   = $defaults[ 'parcel' ][ 'height' ] ?? 1;
$defaultQuantity = $defaultParcelData[ 'quantity' ] ?? 1;

$sumOrderWeight = $globKurier->settings('sum_order_weight') ?? 0;
$addressPattern = '/(?=\d)/';

$products = [];

$heights    = [];
$widths     = [];
$lengths    = [];
$weights    = [];
$quantities = [];

foreach ($orders as $order) {
	$orderId = $order->get_id();
	
	$receiverAddress = $order->get_shipping_address_1();
	
	$parsedAddress = udigroup_globkurier\UDIGroup_Helper::parseAddress($receiverAddress, $order->get_shipping_country());
	$receiverAddressStreet = $parsedAddress['street'] ?? $receiverAddress ?? '';
	$receiverAddressHome = $parsedAddress['number'] ?? '';
	
	
	$productSKUs = [];
	$productEANs = [];
	
	$addSKUToContent = $globKurier->settings('content_add_sku') ?? 0;
	$addEANToContent = $globKurier->settings('content_add_ean') ?? 0;
	
	$totalWeight = 0;
	
	$maxWeight = 1;
	$maxLength = 1;
	$maxWidth  = 1;
	$maxHeight = 1;
	
	foreach ($order->get_items() as $item) {
		$id       = $item->get_product_id();
		$product  = wc_get_product($id);
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
	
	$inpostId    = $order->get_meta('globkurier_inpost_id', true);
	$inpostValue = $order->get_meta('globkurier_inpost_value', true);
	
	$ruchId    = $order->get_meta('globkurier_ruch_id', true);
	$ruchValue = $order->get_meta('globkurier_ruch_value', true);
	
	$extraPickupCarrierId          = $order->get_meta('globkurier_extra_pickup_carrier_id', true);
	$extraPickupCarrierValue       = null;
	$extraPickupCarrierHiddenValue = null;
	
	if ($extraPickupCarrierId) {
		$extraPickupCarrierValue       = $order->get_meta('globkurier_'.$extraPickupCarrierId.'_id', true);
		$extraPickupCarrierHiddenValue = $order->get_meta('globkurier_'.$extraPickupCarrierId.'_input_hidden_value', true);
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
		
		?>
		<input type="hidden" id="globkurier_extraPickupCarrierId" value="<?php echo esc_attr($extraPickupCarrierId) ?>">
		<?php
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
	
	$_products= $globKurier->product()->get($x = [
		'height'                        => $maxHeight,
		'width'                         => $maxWidth,
		'length'                        => $maxLength,
		'weight'                        => $maxWeight,
		'quantity'                      => $defaultQuantity,
		'receiverCountryId'             => $globKurier->countries()->getCountryIdByCode($receiverData[ 'country' ] ?? 'PL'),
		'receiverPostCode'              => $receiverData[ 'postal' ] ?? '',
		'senderCountryId'               => $defaultSenderData[ 'country' ] ?? 1,
		'senderPostCode'                => $defaultSenderData[ 'postal' ] ?? '',
		'globkurier_is_pickup_active'   => ! empty($pointType),
		'globkurier_pickup_type'        => $pointType,
		'globkurier_show_all_providers' => false,
	]);
	
	unset($_products['status']);
	
	$onlyPickupDelivery = empty($pointType);
	
	if($onlyPickupDelivery) {
		$filteredProducts[ 'results' ] = array_filter($_products[ 'results' ], function ($product){
			if (empty($product[ 'deliveryTypeOptions' ])) {
				return false;
			}

			foreach ($product[ 'deliveryTypeOptions' ] as $option) {
				if ($option[ 'key' ] === 'PICKUP' || $option[ 'key' ] === 'CROSSBORDER') {
					return true;
				}
			}
			
			return false;
		});
	}else{
		$filteredProducts = $_products;
	}

	$products[] = $filteredProducts;
	
	$heights[ $orderId ]    = $maxHeight;
	$widths [ $orderId ]    = $maxWidth;
	$lengths[ $orderId ]    = $maxLength;
	$weights[ $orderId ]    = $maxWeight;
	$quantities[ $orderId ] = $defaultQuantity;
}

$ids = [];
foreach ($products as $i => $product) {
	$ids[ $i ] = wp_list_pluck($product[ 'results' ], 'id');
}

if (count($ids) === 0) {
	$commonIds = [];
} elseif (count($ids) === 1) {
	$commonIds = array_values(reset($ids));
} else {
	$commonIds = array_values(array_intersect(...$ids));
}

$commonProducts = [];

foreach ($products as $product) {
	foreach ($product[ 'results' ] ?? [] as $result) {
		if (! in_array($result[ 'id' ], $commonIds)) {
			continue;
		}
		
		$productId = $result[ 'id' ];
		
		if (array_key_exists($productId, $commonProducts)) {
			continue;
		}
		
		$commonProducts[ $productId ] = $result;
	}
}
?>

<input type='hidden' name="gk_orders_count" value="<?php echo (int) count($orders) ?>">
<input type="hidden" id="gk-wc-order-ids" value="<?php echo esc_attr(json_encode(array_map(function ($order){ return $order->get_id(); }, $orders))) ?>">

<div>
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
<input type='hidden' id='globkurier_get_price_nonce' value="<?php echo esc_attr(wp_create_nonce('globkurier_get_price_nonce')); ?>">
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

<input type='hidden' id='globkurier-sender-name' value="<?php echo esc_attr($defaultSenderData[ 'flnames' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-country' value="<?php echo esc_attr($defaultSenderData[ 'country' ] ?? 1) ?>">
<input type='hidden' id='globkurier-sender-postal' value="<?php echo esc_attr($defaultSenderData[ 'postal' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-city' value="<?php echo esc_attr($defaultSenderData[ 'city' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-street' value="<?php echo esc_attr($defaultSenderData[ 'street' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-home' value="<?php echo esc_attr($defaultSenderData[ 'homeNumber' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-flat' value="<?php echo esc_attr($defaultSenderData[ 'flatNumber' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-contact-phone' value="<?php echo esc_attr($defaultSenderData[ 'phone' ] ?? '') ?>">
<input type='hidden' id='globkurier-sender-email' value="<?php echo esc_attr($defaultSenderData[ 'email' ] ?? '') ?>">

<input type='hidden' id='globkurier-content' value="<?php echo esc_attr($defaultParcelData[ 'content' ] ?? '') ?>">
<input type='hidden' id='globkurier-otherContent' value="<?php echo esc_attr($defaultParcelData[ 'otherContent' ] ?? '') ?>">

<?php
foreach ($orders as $i => $order) {
	
	$receiverAddress = $order->get_shipping_address_1();
	
	$parsedAddress = UDIGroup_Helper::parseAddress($receiverAddress, $order->get_shipping_country());
	$receiverAddressStreet = $parsedAddress['street'] ?? $receiverAddress ?? '';
	$receiverAddressHome = $parsedAddress['number'] ?? '';
	
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

	$orderID = $order->get_id();
	?>

	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-name[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'name' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-country[<?php echo esc_attr($orderID) ?>]'
	       value="<?php echo esc_attr($globKurier->countries()->getCountryIdByCode($receiverData[ 'country' ] ?? 'PL')) ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-postal[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'postal' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-city[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'city' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-street[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'street' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-home[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'home' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-flat[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'flat' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-contact-phone[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'phone' ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-receiver-email[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($receiverData[ 'email' ] ?? '') ?>">

	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-height[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($heights[ $orderID ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-width[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($widths[ $orderID ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-length[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($lengths[ $orderID ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-weight[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($weights[ $orderID ] ?? '') ?>">
	<input type='hidden' data-order-no="<?php echo esc_attr($i) ?>" data-order-id="<?php echo esc_attr($orderID) ?>" name='globkurier-quantity[<?php echo esc_attr($orderID) ?>]' value="<?php echo esc_attr($quantities[ $orderID ] ?? '') ?>">
	
	<?php
}
?>
</div>

<div class="bulk-order-header">
	<h3><?php echo esc_html__('Wybrane zamówienia', 'globkurier') ?>:</h3> <span><?php echo wp_kses(implode(', ', $ordersWithLinks), ['a' => ['href' => [], 'target' => []]]) ?></span>
</div>


<div class='globkurier-notices-container'>
	<div class='globkurier-notices-error' style='background-color: #aa00009c; padding: 10px; color: #fdf2f2; display: none'>
		<div class='globkurier-notices-error-header'>
			<span class='dashicons dashicons-warning'></span>
			<?php echo esc_attr(__('Podczas wysyłania zamówienia wystąpił błąd', 'globkurier')) ?>:
			<hr>
		</div>
		<div class="globkurier-notices-error-body" style="padding: 0 15px;"></div>
	</div>
	<div class="globkurier-notices-success" style="background-color: rgba(49,128,41,0.61); padding: 10px; color: #fdf2f2; display: none">
		<div class="globkurier-notices-success-header">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php echo esc_attr(__('Zamówienie zostało przyjęte.', 'globkurier')) ?>:
			<hr>
		</div>
		<div class="globkurier-notices-success-body" style="padding: 0 15px;"></div>
	</div>

	<div class="globkurier-address-books-error" style="background-color: #aa00009c; padding: 10px; color: #fdf2f2; display: none">
		<div class="globkurier-notices-success-header">
			<span class="dashicons dashicons-warning"></span>
			<?php echo esc_attr(__('Podczas dodawania do książki adresowej wystąpił błąd', 'globkurier')) ?>:
			<hr>
		</div>
		<div class="globkurier-address-books-error-body" style="padding: 0 15px;"></div>
	</div>

	<div class="globkurier-address-books-success" style="background-color: rgba(49,128,41,0.61); padding: 10px; color: #fdf2f2; display: none">
		<div class="globkurier-notices-success-header">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php echo esc_attr(__('Pomyślnie dodano do książki adresowej', 'globkurier')) ?>:
			<hr>
		</div>
		<div class="globkurier-address-books-success-body" style="padding: 0 15px;"></div>
	</div>
</div>


<div id="gk-bulk-step-1">
	<h3><?php echo esc_html__('Metody wysyłki pasujące do wszystkich wybranych zamówień', 'globkurier') ?>:</h3>
	<div class="udi-all-products">
		<?php
		foreach ($commonProducts as $productId => $product): ?>
			<?php
			$nameSuffix = '';
			if ($defaultQuantity > 1) {
				$nameSuffix = '(x'.$defaultQuantity.')';
			}
			
			$labels = $product[ 'labels' ] ?? [];
			if (is_array($labels)) {
				$labels = array_map(function ($label){
					return preg_replace('/[^a-zA-Z0-9 ]/', '', $label);
				}, $labels);
			} elseif (is_string($labels)) {
				$labels = [preg_replace('/[^a-zA-Z0-9 ]/', '', $labels)];
			} else {
				$labels = [];
			}
			
			$collectionTypesText = '';
			if (in_array('CROSSBORDER', $product[ 'collectionTypes' ] ?? [])) {
				$collectionTypesText = '<div><span><br><b>Crossborder</b></span></div>';
			}
			
			$netPrice   = number_format($product[ 'netPrice' ] ?? 0, 2, '.', '');
			$grossPrice = number_format($product[ 'grossPrice' ] ?? 0, 2, '.', '');
			?>

			<div class="udi-product">
				<div><img src="<?php echo esc_attr($product[ 'carrierLogoLink' ] ?? '') ?>"></div>
				<div><span><?php echo esc_html($product[ 'name' ] ?? '') ?> <?php echo esc_html($nameSuffix) ?></span></div>
				<?php echo wp_kses_post($collectionTypesText) ?>
				<div style="margin: 20px 0">
					<span class="udi-product-price"><?php echo esc_html($netPrice) ?>zł</span><br/>
					(<?php echo esc_html($grossPrice) ?>zł brutto)
				</div>
				<div style="margin: 10px 0">
					<button type="button"
					        class="button-secondary udi-select-carrier-bulk"
					        data-labels="<?php echo esc_attr(implode(',', $labels)) ?>"
					        data-carriername="<?php echo esc_attr($product[ 'carrierName' ] ?? '') ?>"
					        data-collectiontypes="<?php echo esc_attr(implode(',', $product[ 'collectionTypes' ] ?? [])) ?>"
					        data-deliverytypes="<?php echo esc_attr(implode(',', array_column($product[ 'deliveryTypeOptions' ] ?? [], 'key'))) ?>"
					        data-carrierid="<?php echo esc_attr($productId) ?>">
						<?php echo esc_html__('Wybieram', 'globkurier') ?>
					</button>
				</div>
			</div>
		<?php
		endforeach; ?>
	</div>
</div>

<div id="gk-bulk-step-2">

	<div class='udi-step-select-product-details' style='display: none'>

		<div class='udi-step-select-product-details-left'>
			<div class='udi-selected-product'>
				<input type='hidden' id='udi-selected-product-is-inpost'>
				<input type='hidden' id='udi-selected-product-inpost-delivery-to-point'>
				<input type='hidden' id='udi-selected-product-is-ruch'>
				<input type='hidden' id='udi-selected-product-id'>
				<div class='udi-selected-product-header'>
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
				</div>
				<input type="hidden" id="globkurier-requiredAlternativeAddonsGroups">
				<div class="udi-product-extras-body" style="display:none;">

					<div class="udi-product-extras-category" id="udi-extra-category-receiver">
						<div class="udi-product-extras-category-header">
							<?php echo esc_attr(__('Odbiorca', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-cod">
						<div class="udi-product-extras-category-header">
							<?php echo esc_attr(__('Pobranie COD', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-insurance">
						<div class="udi-product-extras-category-header">
							<?php echo esc_attr(__('Ubezpieczenie', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

					<div class="udi-product-extras-category" id="udi-extra-category-other">
						<div class="udi-product-extras-category-header">
							<?php echo esc_attr(__('Inne', 'globkurier')) ?>
						</div>
						<div class="udi-product-extras-cat-items"></div>
					</div>

				</div>
			</div>
		</div>

		<div class="udi-step-select-product-details-right">
			<div class="udi-service-options">
				<div class="udi-service-options-header"><?php echo esc_attr(__('Opcje nadania', 'globkurier')) ?>:
				</div>
				<div class="udi-service-options-body">

					<div style="display: contents">
						<div class=udi-options-body-row" style="display: contents;">
							<label class="udi-options-body-label" for=""><?php echo esc_attr(__('Nadanie przesyłki', 'globkurier')) ?>:</label>
							<div class="" style="margin-left: 10px;">
								<input type="radio" id="globkurier-pickup-type-PICKUP" data-pickuptype="PICKUP" name="globkurier-pickup-type" value="PICKUP">
								<label for="globkurier-pickup-type-PICKUP"><?php echo esc_attr(__('Przesyłkę odbierze kurier', 'globkurier')) ?></label><br>
								<input type="radio" id="globkurier-pickup-type-POINT" data-pickuptype="POINT" name="globkurier-pickup-type" value="POINT">
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
							<label for="globkurier-service-time-picker" class="udi-options-body-label"><?php echo esc_attr(__('Godzina nadania', 'globkurier')) ?>:</label>
							<div class="udi-input-with-notice">
								<select name="globkurier-service-time-picker" id="globkurier-service-time-picker"></select>
							</div>
						</div>

						<div class="globkurier-only-inpost">
							<label for="globkurier_inpost_input" class="udi-options-body-label"><?php echo esc_attr(__('InPost punkt nadania', 'globkurier')) ?>:</label>

							<div class="udi-input-with-notice">
								<select type="text" style="width: 100%" class=" udi-select2" id="udi-select-inpost-sender" name="globkurier_inpost_input_sender_value">
									<?php
									if (isset($defaultInpostId)) {
										echo "<option value='".esc_attr($defaultInpostValue)."' selected>".esc_attr($defaultInpostId).'</option>';
									}
									?>
								</select>
								<input type="hidden" name="globkurier_inpost_input" id="globkurier_inpost_input" value="<?php echo esc_attr($defaultInpostId ?? '') ?>">
							</div>
						</div>
					</div>

					<div id="globkurier-custom-fields-required" style="display: contents;"></div>

					<div id="globkurier-service-extra-fields-INSURANCE" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-CASH_ON_DELIVERY" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-RETURN_OF_DOCUMENTS" style="display: contents;"></div>
					<div id="globkurier-service-extra-fields-SENDER_WAYBILL_ADDRESS" style="display: contents;"></div>

					<div style="display: contents;">
						<label for="globkurier-service-payment-picker" class="udi-options-body-label""><?php echo esc_attr(__('Płatność', 'globkurier')) ?>:</label>
						<div class="udi-input-with-notice">
							<select style="width: 100%" name="globkurier-service-payment-picker" id="globkurier-service-payment-picker"></select>
						</div>
					</div>

					<div style="display: contents;">
						<label for="globkurier-contains-dangerous-goods" class="udi-options-body-label"><?php echo esc_attr(__('Towary niebezpieczne', 'globkurier')) ?>:</label>
						<div class="udi-input-with-notice">
							<label>
								<input type="checkbox" name="globkurier-contains-dangerous-goods" id="globkurier-contains-dangerous-goods" value="1">
								<?php echo esc_attr(__('Przesyłki zawierają towary niebezpieczne', 'globkurier')) ?>
							</label>
						</div>
					</div>

					<div style="display: contents;">
						<button style="width: max-content; justify-self: flex-end; grid-column-start: 2;" type="button" class="button button-primary udi-save-order">
							<?php echo esc_attr(__('Potwierdzam i składam zamówienie', 'globkurier')) ?>
						</button>
					</div>

				</div>
			</div>
		</div>

	</div>

</div>

<div id="gk-bulk-step-3" style="display:none;">
	<div class='udi-order-confirm-container'>

		<div class="udi-order-confirm-container-body">
		<h1><?php echo esc_attr(__('Podsumowanie zamówień', 'globkurier')) ?></h1>
		
		<p><?php echo esc_attr(__('Prosimy o wydruk listu przewozowego, który zostanie wysłany na adres e-mail Zleceniodawcy i Nadawcy. Numer listu umożliwi monitorowanie losów Twojej przesyłki. Przekazując kurierowi wydrukowany list przewozowy nie poniesiesz dodatkowych kosztów.',
				'globkurier')) ?></p>
	
		<p class="udi-info-p"><?php echo esc_attr(__('Przed zatwierdzeniem prosimy dokładnie zweryfikować dane.', 'globkurier')) ?></p>
		
		<?php
		  foreach ($orders as $i => $order) {
		?>
			<div style="margin-top: 40px">
				<h2>Zamówienie #<?php echo (int) $order->get_id() ?></h2>
				
				<div class="udi-order-confirm-address">
					<div class="udi-wpadmin-order-address-col">
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('NADAWCA', 'globkurier')) ?>:
						</div>
						<div>
							<div>
								<span class="udi-confirm-address-sender-name"></span>
							</div>
							<div>
								<span class="udi-confirm-address-sender-street"></span>
								<span class="udi-confirm-address-sender-homeNumber"></span>
								<span class="udi-confirm-address-sender-flatNumber"></span>
							</div>
							<div>
								<span class="udi-confirm-address-sender-city"></span>
								<span class="udi-confirm-address-sender-postal"></span>
							</div>
							<div>
								<span class="udi-confirm-address-sender-flnames"></span>
							</div>
							<div>
								<?php echo esc_attr(__('tel.', 'globkurier')) ?>
								<span class="udi-confirm-address-sender-phone"></span>
							</div>
							<div>
								<span class="udi-confirm-address-sender-email"></span>
							</div>
						</div>
					</div>
					<div class="udi-wpadmin-order-address-col">
						<?php
						do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('ODBIORCA', 'globkurier')) ?>:
						</div>
		
						<div>
							<div>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-name[<?php echo esc_attr($i) ?>]"></span>
							</div>
							<div>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-street[<?php echo esc_attr($i) ?>]"></span>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-homeNumber[<?php echo esc_attr($i) ?>]"></span>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-flatNumber[<?php echo esc_attr($i) ?>]"></span>
							</div>
							<div>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-city[<?php echo esc_attr($i) ?>]"></span>
								<span data-order-no="<?php echo esc_attr($i) ?>" data-target="udi-confirm-address-receiver-postal[<?php echo esc_attr($i) ?>]"></span>
							</div>
							<div>
								<span data-target="udi-confirm-address-receiver-flnames[<?php echo esc_attr($i) ?>]"></span>
							</div>
							<div>
								<?php echo esc_attr(__('tel.', 'globkurier')) ?>
								<span data-order-no="<?php echo esc_attr($i) ?>"  data-target="udi-confirm-address-receiver-phone[<?php echo esc_attr($i) ?>]"></span>
							</div>
							<div>
								<span data-order-no="<?php echo esc_attr($i) ?>"  data-target="udi-confirm-address-receiver-email[<?php echo esc_attr($i) ?>]"></span>
							</div>
						</div>
					</div>
				</div>
				
				<div class="udi-order-confirm-product">
					<div class="udi-wpadmin-order-address-col">
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('PRODUKT', 'globkurier')) ?>:
						</div>
						<div>
							<div>
								<span class="udi-confirm-product-name"></span>
								<strong><span class="udi-confirm-product-price"></span></strong>
							</div>
							
							<div class="udi-confirm-product-count">
								<?php echo esc_attr(__('Liczba paczek', 'globkurier')) ?>:
								<span class="udi-confirm-value"></span>
							</div>
							
							<div class="udi-confirm-product-terminal">
								<?php echo esc_attr(__('Nadanie przesyłki w terminalu.', 'globkurier')) ?>
							</div>
							
							<div class="udi-confirm-product-date">
								<?php echo esc_attr(__('Nadanie w dniu', 'globkurier')) ?>:
								<span class="udi-confirm-value"></span>
							</div>
							
							<div class="udi-confirm-product-time">
								<?php echo esc_attr(__('Nadanie w godzinach', 'globkurier')) ?>:
								<span class="udi-confirm-value"></span>
							</div>
							
							<div class="udi-confirm-product-content">
								<?php echo esc_attr(__('Zawartość przesyłki', 'globkurier')) ?>:
								<span class="udi-confirm-value"></span>
							</div>
						</div>
					</div>
					
					<div class="udi-wpadmin-order-address-col">
						<?php
						do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('DODATKI', 'globkurier')) ?>:
						</div>
		
						<div class="udi-confirm-extras"></div>
					</div>
				</div>
				
				<div class="udi-order-confirm-payment">
		
					<div class="udi-wpadmin-order-address-col">
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('PŁATNOŚĆ', 'globkurier')) ?>:
						</div>
		
						<div>
							<div>
								<?php echo esc_attr(__('Forma płatności', 'globkurier')) ?>:
								<span class="udi-confirm-payment-method"></span>
							</div>
						</div>
					</div>
					<div class="udi-wpadmin-order-address-col">
						<?php do_action('globkurier_before_sender_data', $order ?? '', $defaultSenderData, $receiverData) ?>
						<div class="udi-product-extras-header">
							<?php echo esc_attr(__('ŁĄCZNIE DO ZAPŁATY', 'globkurier')) ?>:
						</div>
						<div>
							<h1 class="udi-confirm-payment-total-price"></h1>
						</div>
					</div>
				</div>
			</div>
		<?php
		  }
		?>
		</div>
		<div class="udi-order-confirm-container-buttons">
			<button type="button" class="button-secondary globkurier_confirm_correction"><?php echo esc_attr(__('Popraw dane', 'globkurier')) ?></button>
			<button type="button" class="button-primary globkurier_confirm_send"><?php echo esc_attr(__('Zatwierdź i zapłać', 'globkurier')) ?></button>
		</div>
	</div>

</div>

<div id="gk-bulk-step-4">

</div>