<?php
/**
 * Admin Feature: Manage Players, Products, and Orders
 */

// Add admin menu page
add_action('admin_menu', 'intersoccer_admin_menu');
function intersoccer_admin_menu() {
    add_menu_page(
        __('Player Management', 'woocommerce'),
        __('Players & Orders', 'woocommerce'),
        'manage_options',
        'intersoccer-players',
        'intersoccer_players_admin_page',
        'dashicons-groups',
        56
    );
}

// Render the admin page
function intersoccer_players_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Player Management - All Players & Orders', 'woocommerce'); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('User', 'woocommerce'); ?></th>
                    <th><?php _e('Player Name', 'woocommerce'); ?></th>
                    <th><?php _e('DOB', 'woocommerce'); ?></th>
                    <th><?php _e('Medical Conditions', 'woocommerce'); ?></th>
                    <th><?php _e('Consent File', 'woocommerce'); ?></th>
                    <th><?php _e('Order ID', 'woocommerce'); ?></th>
                    <th><?php _e('Product', 'woocommerce'); ?></th>
                    <th><?php _e('Categories', 'woocommerce'); ?></th>
                    <th><?php _e('Variations & Attributes', 'woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get all users
                $users = get_users();
                foreach ($users as $user) {
                    $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: array();
                    if (empty($players)) {
                        continue;
                    }

                    // Get orders for this user
                    $orders = wc_get_orders(array(
                        'customer_id' => $user->ID,
                        'status' => array('wc-completed', 'wc-processing'),
                    ));

                    foreach ($players as $player) {
                        $player_row = array(
                            'user' => esc_html($user->display_name . ' (' . $user->user_email . ')'),
                            'player_name' => esc_html($player['name']),
                            'dob' => esc_html($player['dob']),
                            'medical_conditions' => esc_html($player['medical_conditions']),
                            'consent_file' => !empty($player['consent_url']) ? '<a href="' . esc_url($player['consent_url']) . '" target="_blank">View Consent</a>' : 'None',
                            'order_id' => 'N/A',
                            'product' => 'N/A',
                            'categories' => 'N/A',
                            'variations_attributes' => 'N/A',
                        );

                        // Find orders associated with this player
                        $found_in_order = false;
                        foreach ($orders as $order) {
                            foreach ($order->get_items() as $item) {
                                $order_player = $item->get_meta('Player');
                                if ($order_player === $player['name']) {
                                    $found_in_order = true;
                                    $product = $item->get_product();
                                    $product_id = $item->get_product_id();
                                    $variation_id = $item->get_variation_id();

                                    // Product categories
                                    $categories = get_the_terms($product_id, 'product_cat');
                                    $category_names = $categories ? wp_list_pluck($categories, 'name') : array();
                                    $category_list = !empty($category_names) ? implode(', ', $category_names) : 'N/A';

                                    // Product variations and attributes
                                    $variation_attributes = array();
                                    if ($variation_id) {
                                        $variation = wc_get_product($variation_id);
                                        $variation_attributes = $variation->get_attributes();
                                    }

                                    // Specified attributes
                                    $attributes_to_display = array(
                                        'pa_event-terms' => 'Event Terms',
                                        'pa_intersoccer-venues' => 'InterSoccer Venues',
                                        'pa_summer-camp-terms-2025' => 'Summer Camp Terms - 2025',
                                        'pa_hot-lunch' => 'Hot Lunch',
                                        'pa_camp-times' => 'Camp Times',
                                        'pa_camp-holidays' => 'Camp Holidays',
                                        'pa_booking-type' => 'Booking Type',
                                        'pa_age-open' => 'Age Open',
                                    );

                                    $attribute_list = array();
                                    foreach ($attributes_to_display as $attribute_key => $attribute_label) {
                                        $term = '';
                                        if ($variation_id && isset($variation_attributes[$attribute_key])) {
                                            $term = $variation_attributes[$attribute_key];
                                        } else {
                                            $terms = wc_get_product_terms($product_id, $attribute_key, array('fields' => 'names'));
                                            $term = !empty($terms) ? $terms[0] : '';
                                        }
                                        if ($term) {
                                            $attribute_list[] = "$attribute_label: $term";
                                        }
                                    }
                                    $attributes_display = !empty($attribute_list) ? implode('<br>', $attribute_list) : 'N/A';

                                    // Output row with order details
                                    $player_row['order_id'] = $order->get_id();
                                    $player_row['product'] = esc_html($product->get_name());
                                    $player_row['categories'] = esc_html($category_list);
                                    $player_row['variations_attributes'] = $attributes_display;

                                    echo '<tr>';
                                    echo '<td>' . $player_row['user'] . '</td>';
                                    echo '<td>' . $player_row['player_name'] . '</td>';
                                    echo '<td>' . $player_row['dob'] . '</td>';
                                    echo '<td>' . $player_row['medical_conditions'] . '</td>';
                                    echo '<td>' . $player_row['consent_file'] . '</td>';
                                    echo '<td>' . $player_row['order_id'] . '</td>';
                                    echo '<td>' . $player_row['product'] . '</td>';
                                    echo '<td>' . $player_row['categories'] . '</td>';
                                    echo '<td>' . $player_row['variations_attributes'] . '</td>';
                                    echo '</tr>';
                                }
                            }
                        }

                        // If player is not associated with any order, display a row without order details
                        if (!$found_in_order) {
                            echo '<tr>';
                            echo '<td>' . $player_row['user'] . '</td>';
                            echo '<td>' . $player_row['player_name'] . '</td>';
                            echo '<td>' . $player_row['dob'] . '</td>';
                            echo '<td>' . $player_row['medical_conditions'] . '</td>';
                            echo '<td>' . $player_row['consent_file'] . '</td>';
                            echo '<td>' . $player_row['order_id'] . '</td>';
                            echo '<td>' . $player_row['product'] . '</td>';
                            echo '<td>' . $player_row['categories'] . '</td>';
                            echo '<td>' . $player_row['variations_attributes'] . '</td>';
                            echo '</tr>';
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

