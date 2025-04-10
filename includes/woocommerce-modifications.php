<?php
/**
 * WooCommerce Modifications for InterSoccer Player Management
 * Contains customizations and overrides for WooCommerce functionality.
 *
 * @package InterSoccer_Player_Management
 */

// Prevent direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// Hook into WordPress init to apply WooCommerce modifications
add_action('init', 'intersoccer_woocommerce_modifications_init');
/**
 * Initializes WooCommerce modifications if WooCommerce is active.
 */
function intersoccer_woocommerce_modifications_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Increase the AJAX variation threshold to 500
    add_filter('woocommerce_ajax_variation_threshold', 'intersoccer_wc_ajax_variation_threshold', 10, 2);

    // Variation handling modifications
    add_action('wp_enqueue_scripts', 'intersoccer_enqueue_variation_details_script');
    add_filter('woocommerce_available_variation', 'intersoccer_add_variation_details_to_data', 10, 3);
    add_action('woocommerce_checkout_create_order_line_item', 'intersoccer_save_variation_details_to_order_item', 10, 4);
    add_filter('woocommerce_get_item_data', 'intersoccer_display_variation_details_in_cart', 10, 2);
    add_action('woocommerce_admin_order_item_values', 'intersoccer_display_full_variation_details_in_admin_order', 10, 3);
    add_action('woocommerce_order_item_meta_end', 'intersoccer_display_full_variation_details_in_customer_order', 10, 3);
}

/**
 * Increases the WooCommerce AJAX variation threshold to 500.
 *
 * @param int        $qty    The default variation threshold (typically 30).
 * @param WC_Product $product The product object.
 * @return int The new variation threshold.
 */
function intersoccer_wc_ajax_variation_threshold($qty, $product) {
    return 500;
}

/**
 * Enqueues the variation details script on product pages.
 */
function intersoccer_enqueue_variation_details_script() {
    if (is_product()) {
        wp_enqueue_script(
            'intersoccer-variation-details',
            plugin_dir_url(__FILE__) . '../assets/js/variation-details.js',
            array('jquery'),
            '1.0',
            true
        );
        wp_localize_script('intersoccer-variation-details', 'variationData', array('variations' => array()));
    }
}

/**
 * Adds variation details to the variation data for use in JavaScript.
 *
 * @param array      $data      The variation data.
 * @param WC_Product $product   The parent product.
 * @param WC_Product $variation The variation object.
 * @return array The modified variation data.
 */
function intersoccer_add_variation_details_to_data($data, $product, $variation) {
    $data['term'] = $variation->get_attribute('pa_term');
    $data['age_group'] = $variation->get_attribute('pa_age_group');
    $data['indoor_outdoor'] = $variation->get_attribute('pa_indoor_outdoor');
    $data['availability'] = $variation->get_attribute('pa_availability');
    $data['start_end_dates'] = get_post_meta($variation->get_id(), 'start_end_dates', true);
    $data['pro_rata_price'] = get_post_meta($variation->get_id(), 'pro_rata_price', true);
    return $data;
}

/**
 * Saves variation details to order items during checkout.
 *
 * @param WC_Order_Item_Product $item          The order item.
 * @param string                $cart_item_key The cart item key.
 * @param array                 $values        The cart item values.
 * @param WC_Order              $order         The order object.
 */
function intersoccer_save_variation_details_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['variation']['attribute_pa_schedule'])) {
        $item->add_meta_data('Schedule', $values['variation']['attribute_pa_schedule'], true);
    }
    if (isset($values['variation']['attribute_pa_term'])) {
        $item->add_meta_data('Term', $values['variation']['attribute_pa_term'], true);
    }
    if (isset($values['variation']['attribute_pa_age_group'])) {
        $item->add_meta_data('Age Group', $values['variation']['attribute_pa_age_group'], true);
    }
    if (isset($values['variation']['attribute_pa_indoor_outdoor'])) {
        $item->add_meta_data('Indoor/Outdoor', $values['variation']['attribute_pa_indoor_outdoor'], true);
    }
    if (isset($values['variation']['attribute_pa_availability'])) {
        $item->add_meta_data('Availability', $values['variation']['attribute_pa_availability'], true);
    }
    if (isset($values['variation']['attribute_pa_venue'])) {
        $item->add_meta_data('Venue', $values['variation']['attribute_pa_venue'], true);
    }
    $start_end_dates = get_post_meta($item->get_variation_id(), 'start_end_dates', true);
    if ($start_end_dates) {
        $item->add_meta_data('Start/End Dates', $start_end_dates, true);
    }
    $pro_rata_price = get_post_meta($item->get_variation_id(), 'pro_rata_price', true);
    if ($pro_rata_price) {
        $item->add_meta_data('Pro-Rata Price', $pro_rata_price, true);
    }
}

/**
 * Displays variation details in the cart.
 *
 * @param array $item_data The cart item data.
 * @param array $cart_item The cart item.
 * @return array The modified cart item data.
 */
function intersoccer_display_variation_details_in_cart($item_data, $cart_item) {
    if (isset($cart_item['variation']['attribute_pa_schedule'])) {
        $item_data[] = array(
            'key' => 'Schedule',
            'value' => $cart_item['variation']['attribute_pa_schedule'],
        );
    }
    if (isset($cart_item['variation']['attribute_pa_term'])) {
        $item_data[] = array(
            'key' => 'Term',
            'value' => $cart_item['variation']['attribute_pa_term'],
        );
    }
    if (isset($cart_item['variation']['attribute_pa_age_group'])) {
        $item_data[] = array(
            'key' => 'Age Group',
            'value' => $cart_item['variation']['attribute_pa_age_group'],
        );
    }
    if (isset($cart_item['variation']['attribute_pa_indoor_outdoor'])) {
        $item_data[] = array(
            'key' => 'Indoor/Outdoor',
            'value' => $cart_item['variation']['attribute_pa_indoor_outdoor'],
        );
    }
    if (isset($cart_item['variation']['attribute_pa_availability'])) {
        $item_data[] = array(
            'key' => 'Availability',
            'value' => $cart_item['variation']['attribute_pa_availability'],
        );
    }
    $start_end_dates = get_post_meta($cart_item['variation_id'], 'start_end_dates', true);
    if ($start_end_dates) {
        $item_data[] = array(
            'key' => 'Start/End Dates',
            'value' => $start_end_dates,
        );
    }
    $pro_rata_price = get_post_meta($cart_item['variation_id'], 'pro_rata_price', true);
    if ($pro_rata_price) {
        $item_data[] = array(
            'key' => 'Pro-Rata Price',
            'value' => $pro_rata_price,
        );
    }
    return $item_data;
}

/**
 * Displays variation details in admin order details.
 *
 * @param WC_Product $product The product object.
 * @param WC_Order_Item $item The order item.
 * @param int $item_id The order item ID.
 */
function intersoccer_display_full_variation_details_in_admin_order($product, $item, $item_id) {
    if ($schedule = $item->get_meta('Schedule')) {
        echo '<div><strong>Schedule:</strong> ' . esc_html($schedule) . '</div>';
    }
    if ($term = $item->get_meta('Term')) {
        echo '<div><strong>Term:</strong> ' . esc_html($term) . '</div>';
    }
    if ($age_group = $item->get_meta('Age Group')) {
        echo '<div><strong>Age Group:</strong> ' . esc_html($age_group) . '</div>';
    }
    if ($indoor_outdoor = $item->get_meta('Indoor/Outdoor')) {
        echo '<div><strong>Indoor/Outdoor:</strong> ' . esc_html($indoor_outdoor) . '</div>';
    }
    if ($availability = $item->get_meta('Availability')) {
        echo '<div><strong>Availability:</strong> ' . esc_html($availability) . '</div>';
    }
    if ($start_end_dates = $item->get_meta('Start/End Dates')) {
        echo '<div><strong>Start/End Dates:</strong> ' . esc_html($start_end_dates) . '</div>';
    }
    if ($pro_rata_price = $item->get_meta('Pro-Rata Price')) {
        echo '<div><strong>Pro-Rata Price:</strong> ' . esc_html($pro_rata_price) . '</div>';
    }
    if ($player = $item->get_meta('Player 1')) {
        echo '<div><strong>Player:</strong> ' . esc_html($player) . '</div>';
    }
    if ($medical_conditions = $item->get_meta('Medical Conditions 1')) {
        echo '<div><strong>Medical Conditions:</strong> ' . esc_html($medical_conditions) . '</div>';
    }
    if ($venue = $item->get_meta('Venue')) {
        echo '<div><strong>Venue:</strong> ' . esc_html($venue) . '</div>';
    }
}

/**
 * Displays variation details in customer order details.
 *
 * @param int $item_id The order item ID.
 * @param WC_Order_Item $item The order item.
 * @param WC_Order $order The order object.
 */
function intersoccer_display_full_variation_details_in_customer_order($item_id, $item, $order) {
    if ($schedule = $item->get_meta('Schedule')) {
        echo '<div><strong>Schedule:</strong> ' . esc_html($schedule) . '</div>';
    }
    if ($term = $item->get_meta('Term')) {
        echo '<div><strong>Term:</strong> ' . esc_html($term) . '</div>';
    }
    if ($age_group = $item->get_meta('Age Group')) {
        echo '<div><strong>Age Group:</strong> ' . esc_html($age_group) . '</div>';
    }
    if ($indoor_outdoor = $item->get_meta('Indoor/Outdoor')) {
        echo '<div><strong>Indoor/Outdoor:</strong> ' . esc_html($indoor_outdoor) . '</div>';
    }
    if ($availability = $item->get_meta('Availability')) {
        echo '<div><strong>Availability:</strong> ' . esc_html($availability) . '</div>';
    }
    if ($start_end_dates = $item->get_meta('Start/End Dates')) {
        echo '<div><strong>Start/End Dates:</strong> ' . esc_html($start_end_dates) . '</div>';
    }
    if ($pro_rata_price = $item->get_meta('Pro-Rata Price')) {
        echo '<div><strong>Pro-Rata Price:</strong> ' . esc_html($pro_rata_price) . '</div>';
    }
    if ($player = $item->get_meta('Player 1')) {
        echo '<div><strong>Player:</strong> ' . esc_html($player) . '</div>';
    }
    if ($medical_conditions = $item->get_meta('Medical Conditions 1')) {
        echo '<div><strong>Medical Conditions:</strong> ' . esc_html($medical_conditions) . '</div>';
    }
    if ($venue = $item->get_meta('Venue')) {
        echo '<div><strong>Venue:</strong> ' . esc_html($venue) . '</div>';
    }
}
?>

