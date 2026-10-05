<?php
/**
 * Cart/checkout line player data — persistence only.
 *
 * The cart/checkout "assign a player" picker was removed: players are chosen on
 * the product page at add-to-cart (intersoccer-product-variations). This file
 * still reads the ATC cart keys and writes order-item meta for rosters.
 *
 * @package PlayerManagement
 */

defined('ABSPATH') or die('No script kiddies please!');

if (!function_exists('intersoccer_get_cart_item_player_index')) {
    /**
     * Extract player index from cart item data using fallback chain.
     *
     * PV (intersoccer-product-variations) ATC may write player data via different keys.
     *
     * Fallback order:
     * 1. assigned_player (PV ATC primary key)
     * 2. intersoccer_player_index (legacy PM cart key)
     * 3. Player Index (legacy)
     *
     * @param array $cart_item Cart item data array.
     * @return string|int Player index value, or empty string if not assigned.
     */
    function intersoccer_get_cart_item_player_index($cart_item) {
        $keys_to_check = ['assigned_player', 'intersoccer_player_index', 'Player Index'];

        foreach ($keys_to_check as $key) {
            if (isset($cart_item[$key]) && $cart_item[$key] !== '' && $cart_item[$key] !== null) {
                return $cart_item[$key];
            }
        }

        return '';
    }
}

/**
 * Save player assignment from cart line data onto the order item.
 *
 * Reads player index from any known cart item key (PV ATC) and writes meta
 * keys used by roster reports. Does not render a cart/checkout picker.
 *
 * @param WC_Order_Item_Product $item          Order item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         Order object.
 */
function intersoccer_save_order_item_player($item, $cart_item_key, $values, $order) {
    $player_index = intersoccer_get_cart_item_player_index($values);

    if ($player_index === '' || $player_index === null) {
        return;
    }

    $user_id = $order->get_customer_id();
    $player = null;

    if ($user_id && function_exists('intersoccer_get_player_by_index')) {
        $player = intersoccer_get_player_by_index($user_id, $player_index);
    } elseif ($user_id) {
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        $player = isset($players[$player_index]) ? $players[$player_index] : null;
    }

    if (!$player) {
        return;
    }

    $player_name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));

    $item->add_meta_data('Assigned Attendee', $player_name, true);
    $item->add_meta_data('intersoccer_player_index', $player_index, true);
    $item->add_meta_data('assigned_player', $player_index, true);
    $item->add_meta_data('Player Index', $player_index, true);

    if (!empty($player['dob'])) {
        $item->add_meta_data('Player DOB', $player['dob'], true);
    }
    if (!empty($player['medical_conditions'])) {
        $item->add_meta_data('Player Medical', $player['medical_conditions'], true);
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'InterSoccer: Saved player assignment order=%d item=%d player_index=%s name=%s',
            $order->get_id(),
            $item->get_id(),
            $player_index,
            $player_name
        ));
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'intersoccer_save_order_item_player', 10, 4);
