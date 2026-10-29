<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * @var int $orderId
 **/

?>

<input type="hidden" id="globkurier_create_order_order_id" value="<?php echo esc_attr($orderId); ?>">
<input type="hidden" id="globkurier_create_order_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_create_order_nonce')); ?>">
<input type="hidden" id="globkurier_get_products_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_products_nonce')); ?>">
<input type="hidden" id="globkurier_get_product_addons_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_product_addons_nonce')); ?>">
<input type="hidden" id="globkurier_get_custom_required_fields_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_custom_required_fields_nonce')); ?>">
<input type="hidden" id="globkurier_get_first_pickup_day_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_first_pickup_day_nonce')); ?>">
<input type="hidden" id="globkurier_get_product_addon_fields_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_product_addon_fields_nonce')); ?>">
<input type="hidden" id="globkurier_get_pickup_time_ranges_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_pickup_time_ranges_nonce')); ?>">
<input type="hidden" id="globkurier_get_price_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_price_nonce')); ?>">
<input type="hidden" id="globkurier_get_payments_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_payments_nonce')); ?>">
<input type="hidden" id="globkurier_order_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_order_nonce')); ?>">
<input type="hidden" id="globkurier_get_current_status_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_current_status_nonce')); ?>">
<input type="hidden" id="globkurier_save_inpost_points_session_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_save_inpost_points_session_nonce')); ?>">
<input type="hidden" id="globkurier_save_ruch_points_session_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_save_ruch_points_session_nonce')); ?>">
<input type="hidden" id="globkurier_get_inpost_points_select2_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_inpost_points_select2_nonce')); ?>">
<input type="hidden" id="globkurier_get_ruch_points_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_ruch_points_nonce')); ?>">
<input type="hidden" id="globkurier_get_person_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_person_nonce')); ?>">
<input type="hidden" id="globkurier_get_inpost_points_select2_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_inpost_points_select2_nonce')); ?>">
<input type="hidden" id="globkurier_get_ruch_points_select2_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_get_ruch_points_select2_nonce')); ?>">
<input type="hidden" id="globkurier_add_person_to_address_book_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_add_person_to_address_book_nonce')); ?>">
<input type="hidden" id="globkurier_update_person_to_address_book_nonce" value="<?php echo esc_attr(wp_create_nonce('globkurier_update_person_to_address_book_nonce')); ?>">

<div class="globkurier-create-order-table-wrapper">

	<button class="button" data-trigger="load-gk-order-box">Nadaj przesyłkę</button>
	
</div>
