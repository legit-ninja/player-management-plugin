<?php
/**
 * File: order-item-functions.php
 * Description: Order item functions for player assignment
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Render assigned attendee dropdown in admin order item
 */
function intersoccer_render_order_item_player_dropdown($product, $item, $item_id) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Entered render_order_item_player_dropdown for item ID: ' . $item_id . 
                 ', product ID: ' . ($product ? $product->get_id() : 'none') . 
                 ', product type: ' . ($product ? $product->get_type() : 'none'));
    }

    if (!$product) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: No product for item ID: ' . $item_id . ' - skipping dropdown');
        }
        return;
    }

    // Get activity type from attribute (fall back to parent for variations)
    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
    $activity_type = wc_get_product($product_id)->get_attribute('pa_activity-type');
    $normalized_type = strtolower(trim($activity_type));

    if (!in_array($normalized_type, ['camp', 'course'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Skipping dropdown for item ID: ' . $item_id . 
                     ' - Activity Type not Camp or Course: "' . $activity_type . '"');
        }
        return;
    }

    $order = $item->get_order();
    $user_id = $order->get_user_id();
    
    if (!$user_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Skipping dropdown for item ID: ' . $item_id . ' - no user ID (guest order)');
        }
        echo '<p>' . esc_html__('Guest order - no attendees available.', 'player-management') . '</p>';
        return;
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    
    if (empty($players) || !is_array($players)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: No players for user ID: ' . $user_id . ' on item ID: ' . $item_id . 
                     '. Raw meta: ' . print_r(get_user_meta($user_id, 'intersoccer_players'), true));
        }
        echo '<p>' . esc_html__('No registered attendees for this customer.', 'player-management') . '</p>';
        return;
    }

    $selected_index = $item->get_meta('intersoccer_player_index');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Rendering dropdown for item ID: ' . $item_id . 
                 ', user ID: ' . $user_id . 
                 ', players count: ' . count($players) . 
                 ', selected index: ' . ($selected_index ?: 'none') . 
                 ', activity_type: "' . $activity_type . '"');
    }

    echo '<div class="wc-order-item-player">';
    echo '<label for="intersoccer_player_index_' . esc_attr($item_id) . '">' . 
         esc_html__('Assigned Attendee', 'player-management') . '</label>';
    echo '<select name="intersoccer_player_index[' . esc_attr($item_id) . ']" ' .
         'id="intersoccer_player_index_' . esc_attr($item_id) . '">';
    echo '<option value="">' . esc_html__('Select Attendee', 'player-management') . '</option>';
    
    foreach ($players as $index => $player) {
        if (!is_array($player)) continue;
        
        $name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
        $name = trim($name);
        
        if (empty($name)) {
            $name = 'Player ' . ($index + 1); // Fallback name
        }
        
        echo '<option value="' . esc_attr($index) . '" ' . 
             selected($selected_index, $index, false) . '>' . 
             esc_html($name) . '</option>';
    }
    
    echo '</select>';
    echo '</div>';
}
add_action('woocommerce_admin_order_item_values', 'intersoccer_render_order_item_player_dropdown', 10, 3);

/**
 * Save assigned attendee on order update
 */
function intersoccer_save_order_item_player($post_id, $post) {
    if (!isset($_POST['intersoccer_player_index']) || !is_array($_POST['intersoccer_player_index'])) {
        return;
    }
    
    $order = wc_get_order($post_id);
    if (!$order) {
        return;
    }
    
    foreach ($order->get_items() as $item_id => $item) {
        if (!isset($_POST['intersoccer_player_index'][$item_id])) {
            continue;
        }
        
        $index = sanitize_text_field($_POST['intersoccer_player_index'][$item_id]);
        $item->update_meta_data('intersoccer_player_index', $index);

        // Also save attendee name for display
        if ($index !== '') {
            $user_id = $order->get_user_id();
            if ($user_id) {
                $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
                
                if (is_array($players) && isset($players[$index]) && is_array($players[$index])) {
                    $player = $players[$index];
                    $name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
                    $name = trim($name);
                    
                    if (!empty($name)) {
                        $item->update_meta_data('Assigned Attendee', $name);
                    }
                }
            }
        }

        $item->save();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Saved assigned attendee for order item ID: ' . $item_id . ', index: ' . $index);
        }
    }
}
add_action('woocommerce_process_shop_order_meta', 'intersoccer_save_order_item_player', 10, 2);

// /**
//  * Display assigned attendee in order item meta (for frontend order view)
//  */
// function intersoccer_display_order_item_attendee($item_id, $item, $order, $plain_text = false) {
//     $attendee_name = $item->get_meta('Assigned Attendee');
    
//     if (!empty($attendee_name)) {
//         $label = __('Assigned Attendee', 'player-management');
        
//         if ($plain_text) {
//             echo "\n" . $label . ': ' . $attendee_name;
//         } else {
//             echo '<div class="assigned-attendee">';
//             echo '<strong>' . esc_html($label) . ':</strong> ' . esc_html($attendee_name);
//             echo '</div>';
//         }
//     }
// }
add_action('woocommerce_order_item_meta_end', 'intersoccer_display_order_item_attendee', 10, 4);

/**
 * Add attendee info to order emails
 */
function intersoccer_add_attendee_to_email($item, $sent_to_admin, $plain_text, $email) {
    $attendee_name = $item->get_meta('Assigned Attendee');
    
    if (!empty($attendee_name)) {
        $label = __('Assigned Attendee', 'player-management');
        
        if ($plain_text) {
            echo "\n" . $label . ': ' . $attendee_name;
        } else {
            echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($attendee_name) . '</p>';
        }
    }
}
add_action('woocommerce_order_item_meta_end', 'intersoccer_add_attendee_to_email', 20, 4);