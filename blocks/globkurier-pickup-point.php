<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

add_action('woocommerce_blocks_loaded', function (){
	require_once __DIR__.'/globkurier-pickup-point-blocks-integration.php';

	add_action(
		'woocommerce_blocks_checkout_block_registration',
		function ($integration_registry){
			$integration_registry->register(new GlobkurierPickupPoint_Blocks_Integration());
		}
	);
});

function register_GlobkurierPickupPoint_block_category($categories)
{
	return array_merge(
		$categories,
		[
			[
				'slug'  => 'globkurier-pickup-point',
				'title' => __('GlobkurierPickupPoint Blocks', 'globkurier'),
			],
		]
	);
}

add_action('block_categories_all', 'register_GlobkurierPickupPoint_block_category', 10, 2);

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use udigroup_globkurier\WoocommerceAddons;

add_action('woocommerce_blocks_loaded', function (){
	woocommerce_store_api_register_endpoint_data(
		[
			'endpoint'        => CheckoutSchema::IDENTIFIER,
			'namespace'       => 'globkurier-pickup-point',
			'data_callback'   => 'globkurier_cb_data_callback',
			'schema_callback' => 'globkurier_cb_schema_callback',
			'schema_type'     => ARRAY_A,
		]
	);
});

function globkurier_cb_data_callback()
{
	return [
		'globkurier_inpost_input' => '',
	];
}

function globkurier_cb_schema_callback()
{
	$return = [
		
		'globkurier_method_id' => [
			'description' => __('globkurier_method_id', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		
		'globkurier_inpost_input'              => [
			'description' => __('globkurier_inpost_input', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_inpost_input_hidden_value' => [
			'description' => __('globkurier_inpost_input_hidden_value', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		
		'globkurier_inpost_input_point_lat'          => [
			'description' => __('globkurier_inpost_input_point_lat', 'globkurier'),
			'type'        => ['float', 'integer', 'string', 'null'],
			'readonly'    => true,
		],
		'globkurier_inpost_input_point_long'         => [
			'description' => __('globkurier_inpost_input_point_long', 'globkurier'),
			'type'        => ['float', 'integer', 'string', 'null'],
			'readonly'    => true,
		],
		'globkurier_inpost_input_point_city'         => [
			'description' => __('globkurier_inpost_input_point_city', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_inpost_input_point_address'      => [
			'description' => __('globkurier_inpost_input_point_address', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_inpost_input_point_openingHours' => [
			'description' => __('globkurier_inpost_input_point_openingHours', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		
		'globkurier_ruch_input'              => [
			'description' => __('globkurier_ruch_input', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_ruch_input_hidden_value' => [
			'description' => __('globkurier_ruch_input_hidden_value', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		
		'globkurier_ruch_input_point_lat'          => [
			'description' => __('globkurier_ruch_input_point_lat', 'globkurier'),
			'type'        => ['float', 'integer', 'string', 'null'],
			'readonly'    => true,
		],
		'globkurier_ruch_input_point_long'         => [
			'description' => __('globkurier_ruch_input_point_long', 'globkurier'),
			'type'        => ['float', 'integer', 'string', 'null'],
			'readonly'    => true,
		],
		'globkurier_ruch_input_point_city'         => [
			'description' => __('globkurier_ruch_input_point_city', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_ruch_input_point_address'      => [
			'description' => __('globkurier_ruch_input_point_address', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
		'globkurier_ruch_input_point_openingHours' => [
			'description' => __('globkurier_ruch_input_point_openingHours', 'globkurier'),
			'type'        => ['string', 'null'],
			'readonly'    => true,
		],
	
	];
	
	global $globKurier;
	
	foreach ($globKurier->settings() as $key => $_method) {
		if (strpos($key, 'extra_pickup_point_') === 0) {
			$carrierId = \str_replace('extra_pickup_point_', '', $key);
			
			$return = array_merge($return, [
				'globkurier_'.$key.'_input'              => [
					'description' => 'globkurier_'.$key.'_input',
					'type'        => ['string', 'null'],
					'readonly'    => true,
				],
				'globkurier_'.$key.'_input_hidden_value' => [
					'description' => 'globkurier_'.$key.'_input_hidden_value',
					'type'        => ['string', 'null'],
					'readonly'    => true,
				],
				
				'globkurier_'.$key.'_input_point_lat'          => [
					'description' => 'globkurier_'.$key.'_input_point_lat',
					'type'        => ['float', 'integer', 'string', 'null'],
					'readonly'    => true,
				],
				'globkurier_'.$key.'_input_point_long'         => [
					'description' => 'globkurier_'.$key.'_input_point_long',
					'type'        => ['float', 'integer', 'string', 'null'],
					'readonly'    => true,
				],
				'globkurier_'.$key.'_input_point_city'         => [
					'description' => 'globkurier_'.$key.'_input_point_city',
					'type'        => ['string', 'null'],
					'readonly'    => true,
				],
				'globkurier_'.$key.'_input_point_address'      => [
					'description' => 'globkurier_'.$key.'_input_point_address',
					'type'        => ['string', 'null'],
					'readonly'    => true,
				],
				'globkurier_'.$key.'_input_point_openingHours' => [
					'description' => 'globkurier_'.$key.'_input_point_openingHours',
					'type'        => ['string', 'null'],
					'readonly'    => true,
				],
			]);
		}
	}
	
	return $return;
}

add_action('woocommerce_store_api_checkout_update_order_from_request', function ($order, $request){
	global $globKurier;
	if (!$globKurier || !$globKurier->isAnyPickupPointActive()) {
		return;
	}

	$data = isset($request[ 'extensions' ][ 'globkurier-pickup-point' ]) ? $request[ 'extensions' ][ 'globkurier-pickup-point' ] : [];

	if (empty($data[ 'globkurier_method_id' ])) {
		return;
	}

	// Metoda dostawy faktycznie wybrana w zamowieniu (linie wysylki sa juz utworzone z koszyka), a nie podana
	// przez klienta w danych rozszerzenia - inaczej dalo sie dopisac meta punktu do zamowienia z inna metoda.
	// Wartosc od klienta przyjmujemy tylko, gdy odpowiada linii wysylki zamowienia - takze dla stawek z dodatkowym
	// sufiksem w ID (np. table_rate:3:1), ktore moga byc zapisane w ustawieniach wtyczki.
	$shippingItems = $order->get_shipping_methods();
	$shippingItem  = reset($shippingItems);
	$orderMethodId = $shippingItem ? $shippingItem->get_method_id().':'.$shippingItem->get_instance_id() : '';
	$clientMethod  = sanitize_text_field((string) $data[ 'globkurier_method_id' ]);
	$methodId      = ($orderMethodId !== '' && ($clientMethod === $orderMethodId || strpos($clientMethod, $orderMethodId.':') === 0))
		? $clientMethod
		: $orderMethodId;

	(new WoocommerceAddons())->setOrderMeta($order->get_id(), $methodId, $data);
}, 10, 2);

function globkurier_enqueue_select2_jquery()
{
	global $globKurier;
	if (!$globKurier || !$globKurier->isAnyPickupPointActive()) {
		return;
	}

	wp_register_style('gk_select2css', plugins_url().'/woocommerce/assets/css/select2.css', false, '1.0', 'all');
	wp_enqueue_style('gk_select2css');
}
add_action('wp_enqueue_scripts', 'globkurier_enqueue_select2_jquery');