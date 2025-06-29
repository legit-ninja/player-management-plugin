<?php

/**
 * File: ajax-handlers.php
 * Description: Handles AJAX requests for the InterSoccer Player Management plugin, including adding, editing, deleting players, refreshing nonces, fetching user roles, and retrieving player data. Supports all logged-in user roles and admin management. Includes event count and region for players.
 * Dependencies: WordPress, WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Helper function to count events for a player
if (!function_exists('intersoccer_get_player_event_count')) {
    function intersoccer_get_player_event_count($user_id, $player_index)
    {
        $count = 0;
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-completed', 'wc-processing'],
        ]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $player_index_meta = $item->get_meta('intersoccer_player_index');
                if ($player_index_meta == $player_index) {
                    $count++;
                }
            }
        }
        return $count;
    }
}

// Helper function to fetch past events for a player
function intersoccer_get_player_past_events($user_id, $player_index)
{
    $past_events = [];
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status' => ['wc-completed'],
    ]);
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $player_index_meta = $item->get_meta('intersoccer_player_index');
            if ($player_index_meta != $player_index) {
                continue;
            }
            $product_id = $item->get_product_id();
            $event_id = get_post_meta($product_id, '_tribe_wooticket_event', true);
            if (!$event_id) {
                continue;
            }
            $event = get_post($event_id);
            if (!$event || $event->post_type !== 'tribe_events') {
                continue;
            }
            $start_date = get_post_meta($event_id, '_EventStartDate', true);
            $venue_id = get_post_meta($event_id, '_EventVenueID', true);
            $venue = $venue_id ? get_the_title($venue_id) : 'N/A';
            if ($start_date && strtotime($start_date) < current_time('timestamp')) {
                $past_events[] = [
                    'name' => $event->post_title,
                    'date' => date('Y-m-d', strtotime($start_date)),
                    'venue' => $venue,
                ];
            }
        }
    }
    return $past_events;
}

// Helper function to fetch unique product attribute values for filters
function intersoccer_get_product_attribute_values($attribute_name)
{
    $values = [];
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_tribe_wooticket_event',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    $products = new WP_Query($args);
    while ($products->have_posts()) {
        $products->the_post();
        $product = wc_get_product(get_the_ID());
        $attribute = $product->get_attribute($attribute_name);
        if ($attribute) {
            $terms = array_map('trim', explode(',', $attribute));
            $values = array_merge($values, $terms);
        }
    }
    wp_reset_postdata();
    return array_unique($values);
}

// Add Player
add_action('wp_ajax_intersoccer_add_player', function () {
    error_log('InterSoccer: intersoccer_add_player called, nonce: ' . ($_POST['nonce'] ?? 'none') . ', user_id: ' . get_current_user_id());
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intersoccer_player_nonce')) {
        error_log('InterSoccer: Nonce verification failed for intersoccer_add_player, received: ' . ($_POST['nonce'] ?? 'none') . ', expected: ' . wp_create_nonce('intersoccer_player_nonce'));
        $new_nonce = wp_create_nonce('intersoccer_player_nonce');
        error_log('InterSoccer: Generated new nonce due to failure: ' . $new_nonce);
        wp_send_json_error(['message' => 'Invalid security token, please refresh and try again', 'new_nonce' => $new_nonce]);
        return;
    }

    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_add_player');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0 || !get_userdata($user_id)) {
        error_log('InterSoccer: Invalid user ID for intersoccer_add_player: ' . $user_id);
        wp_send_json_error(['message' => 'Invalid user ID']);
        return;
    }

    $first_name = sanitize_text_field($_POST['player_first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['player_last_name'] ?? '');
    $dob = sanitize_text_field($_POST['player_dob'] ?? '');
    $gender = sanitize_text_field($_POST['player_gender'] ?? '');
    $avs_number = sanitize_text_field($_POST['player_avs_number'] ?? '');
    $medical = sanitize_textarea_field($_POST['player_medical'] ?? '');

    error_log('InterSoccer: Add player input: ' . json_encode([
        'user_id' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'avs_number' => $avs_number,
        'medical' => $medical
    ]));

    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($avs_number)) {
        error_log('InterSoccer: Missing required fields for intersoccer_add_player');
        wp_send_json_error(['message' => 'All fields are required']);
        return;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        error_log('InterSoccer: Invalid DOB format for intersoccer_add_player: ' . $dob);
        wp_send_json_error(['message' => 'Invalid date of birth']);
        return;
    }
    $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
    $today = new DateTime('2025-06-23');
    if (!$dob_date || $dob_date > $today) {
        error_log('InterSoccer: Invalid DOB date for intersoccer_add_player: ' . $dob);
        wp_send_json_error(['message' => 'Invalid date of birth']);
        return;
    }
    $age = $today->diff($dob_date)->y;
    if ($age < 2 || $age > 13) {
        error_log('InterSoccer: Invalid age for intersoccer_add_player: ' . $age);
        wp_send_json_error(['message' => 'Player must be 2-13 years old']);
        return;
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];

    foreach ($players as $existing_player) {
        if (
            $existing_player['first_name'] === $first_name &&
            $existing_player['last_name'] === $last_name &&
            $existing_player['dob'] === $dob
        ) {
            error_log('InterSoccer: Duplicate player detected for user ' . $user_id . ': ' . $first_name . ' ' . $last_name);
            wp_send_json_error(['message' => 'This player already exists.']);
            return;
        }
    }

    $new_player = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'avs_number' => $avs_number,
        'medical_conditions' => $medical,
        'creation_timestamp' => current_time('timestamp'),
        'event_count' => 0
    ];
    $players[] = $new_player;
    $update_result = update_user_meta($user_id, 'intersoccer_players', $players);
    if ($update_result === false) {
        error_log('InterSoccer: Failed to update intersoccer_players meta for user ' . $user_id);
        wp_send_json_error(['message' => 'Failed to save player data']);
        return;
    }
    wp_cache_delete('intersoccer_players_' . $user_id, 'intersoccer');

    error_log('InterSoccer: Player added successfully for user ' . $user_id);
    wp_send_json_success([
        'message' => 'Player added successfully',
        'player' => $new_player,
    ]);
});

// Edit Player
add_action('wp_ajax_intersoccer_edit_player', function () {
    error_log('InterSoccer: intersoccer_edit_player called, nonce: ' . ($_POST['nonce'] ?? 'none') . ', user_id: ' . get_current_user_id());
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intersoccer_player_nonce')) {
        error_log('InterSoccer: Nonce verification failed for intersoccer_edit_player, received: ' . ($_POST['nonce'] ?? 'none'));
        wp_send_json_error(['message' => 'Invalid security token']);
        return;
    }

    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_edit_player');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $is_admin = current_user_can('manage_options') && !empty($_POST['is_admin']);
    $user_id = $is_admin ? intval($_POST['player_user_id'] ?? 0) : get_current_user_id();
    if ($user_id <= 0 || !get_userdata($user_id)) {
        error_log('InterSoccer: Invalid user ID for intersoccer_edit_player: ' . $user_id);
        wp_send_json_error(['message' => 'Invalid user ID']);
        return;
    }

    $index = intval($_POST['player_index'] ?? -1);
    $first_name = sanitize_text_field($_POST['player_first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['player_last_name'] ?? '');
    $dob = sanitize_text_field($_POST['player_dob'] ?? '');
    $gender = sanitize_text_field($_POST['player_gender'] ?? '');
    $avs_number = sanitize_text_field($_POST['player_avs_number'] ?? '');
    $medical = sanitize_textarea_field($_POST['player_medical'] ?? '');

    error_log('InterSoccer: Edit player input: ' . json_encode([
        'user_id' => $user_id,
        'index' => $index,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'avs_number' => $avs_number,
        'medical' => $medical
    ]));

    if ($index < 0 || empty($first_name) || empty($last_name) || empty($avs_number)) {
        error_log('InterSoccer: Invalid index or missing fields for intersoccer_edit_player');
        wp_send_json_error(['message' => 'Invalid data provided']);
        return;
    }

    if (!empty($dob)) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            error_log('InterSoccer: Invalid DOB format for intersoccer_edit_player: ' . $dob);
            wp_send_json_error(['message' => 'Invalid date of birth']);
            return;
        }
        $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
        $today = new DateTime('2025-06-29');
        if (!$dob_date || $dob_date > $today) {
            error_log('InterSoccer: Invalid DOB date for intersoccer_edit_player: ' . $dob);
            wp_send_json_error(['message' => 'Invalid date of birth']);
            return;
        }
        $age = $today->diff($dob_date)->y;
        if ($age < 2 || $age > 13) {
            error_log('InterSoccer: Invalid age for intersoccer_edit_player: ' . $age);
            wp_send_json_error(['message' => 'Player must be 2-13 years old']);
            return;
        }
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (!isset($players[$index])) {
        error_log('InterSoccer: Player index not found for intersoccer_edit_player: ' . $index);
        wp_send_json_error(['message' => 'Player not found']);
        return;
    }

    $original_player = $players[$index];
    $updated_player = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => !empty($dob) ? $dob : $original_player['dob'],
        'gender' => !empty($gender) ? $gender : $original_player['gender'],
        'avs_number' => $avs_number,
        'medical_conditions' => $medical,
        'creation_timestamp' => $original_player['creation_timestamp'] ?? current_time('timestamp'),
        'event_count' => intersoccer_get_player_event_count($user_id, $index),
        'region' => get_user_meta($user_id, 'intersoccer_region', true) ?: 'Unknown'
    ];
    $players[$index] = $updated_player;

    // Log comparison to validate unchanged data
    $is_unchanged = ($original_player['first_name'] === $first_name &&
                    $original_player['last_name'] === $last_name &&
                    $original_player['dob'] === $updated_player['dob'] &&
                    $original_player['gender'] === $updated_player['gender'] &&
                    $original_player['avs_number'] === $avs_number &&
                    $original_player['medical_conditions'] === $medical);
    error_log('InterSoccer: Original player: ' . json_encode($original_player));
    error_log('InterSoccer: Updated player: ' . json_encode($updated_player));
    error_log('InterSoccer: Data unchanged: ' . ($is_unchanged ? 'Yes' : 'No'));

    global $wpdb;
    error_log('InterSoccer: Pre-update players array: ' . json_encode($players));
    error_log('InterSoccer: Updating meta for user_id: ' . $user_id . ', meta_key: intersoccer_players');

    $update_result = update_user_meta($user_id, 'intersoccer_players', $players);
    error_log('InterSoccer: update_user_meta result: ' . var_export($update_result, true));
    error_log('InterSoccer: Last query: ' . $wpdb->last_query);
    error_log('InterSoccer: Last error: ' . $wpdb->last_error);

    if ($update_result === false) {
        error_log('InterSoccer: Update attempt failed for user ' . $user_id . ' with data: ' . json_encode($updated_player));
    }

    wp_cache_delete('intersoccer_players_' . $user_id, 'intersoccer');
    if ($is_unchanged && $update_result === false) {
        error_log('InterSoccer: No changes detected, update skipped but considered successful');
        wp_send_json_success(['message' => 'No changes detected, player data unchanged']);
    } else {
        error_log('InterSoccer: Player edited successfully for user ' . $user_id . ' with data: ' . json_encode($updated_player));
        wp_send_json_success(['message' => 'Player updated successfully', 'player' => $updated_player]);
    }
});

// Delete Player
add_action('wp_ajax_intersoccer_delete_player', function () {
    error_log('InterSoccer: intersoccer_delete_player called, nonce: ' . ($_POST['nonce'] ?? 'none') . ', user_id: ' . get_current_user_id());
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intersoccer_player_nonce')) {
        error_log('InterSoccer: Nonce verification failed for intersoccer_delete_player, received: ' . ($_POST['nonce'] ?? 'none'));
        wp_send_json_error(['message' => 'Invalid security token']);
        return;
    }

    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_delete_player');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $is_admin = current_user_can('manage_options') && !empty($_POST['is_admin']);
    $user_id = $is_admin ? intval($_POST['player_user_id'] ?? 0) : get_current_user_id();
    if ($user_id <= 0 || !get_userdata($user_id)) {
        error_log('InterSoccer: Invalid user ID for intersoccer_delete_player: ' . $user_id);
        wp_send_json_error(['message' => 'Invalid user ID']);
        return;
    }

    $index = intval($_POST['player_index'] ?? -1);
    if ($index < 0) {
        error_log('InterSoccer: Invalid player index for intersoccer_delete_player: ' . $index);
        wp_send_json_error(['message' => 'Invalid player index']);
        return;
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (!isset($players[$index])) {
        error_log('InterSoccer: Player index not found for intersoccer_delete_player: ' . $index);
        wp_send_json_error(['message' => 'Player not found']);
        return;
    }

    array_splice($players, $index, 1);
    $update_result = update_user_meta($user_id, 'intersoccer_players', $players);
    if ($update_result === false) {
        error_log('InterSoccer: Failed to update intersoccer_players meta for user ' . $user_id);
        wp_send_json_error(['message' => 'Failed to delete player']);
        return;
    }
    wp_cache_delete('intersoccer_players_' . $user_id, 'intersoccer');

    error_log('InterSoccer: Player deleted successfully for user ' . $user_id);
    wp_send_json_success(['message' => 'Player deleted successfully']);
});

// Refresh Nonce
add_action('wp_ajax_intersoccer_refresh_nonce', function () {
    error_log('InterSoccer: intersoccer_refresh_nonce called, user_id: ' . get_current_user_id());
    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_refresh_nonce');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $new_nonce = wp_create_nonce('intersoccer_player_nonce');
    error_log('InterSoccer: New nonce generated: ' . $new_nonce);
    wp_send_json_success(['nonce' => $new_nonce]);
});

// Get User Role
add_action('wp_ajax_intersoccer_get_user_role', function () {
    error_log('InterSoccer: intersoccer_get_user_role called, user_id: ' . get_current_user_id());
    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_get_user_role');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $user = wp_get_current_user();
    $roles = $user->roles;
    $role = !empty($roles) ? $roles[0] : 'none';
    error_log('InterSoccer: User role fetched: ' . $role);
    wp_send_json_success(['role' => $role]);
});

// Get Player (for fallback data retrieval)
add_action('wp_ajax_intersoccer_get_player', function () {
    error_log('InterSoccer: intersoccer_get_player called, nonce: ' . ($_POST['nonce'] ?? 'none') . ', user_id: ' . get_current_user_id());
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intersoccer_player_nonce')) {
        error_log('InterSoccer: Nonce verification failed for intersoccer_get_player, received: ' . ($_POST['nonce'] ?? 'none'));
        wp_send_json_error(['message' => 'Invalid security token']);
        return;
    }

    if (!is_user_logged_in()) {
        error_log('InterSoccer: User not logged in for intersoccer_get_player');
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $is_admin = current_user_can('manage_options') && !empty($_POST['is_admin']);
    $user_id = $is_admin ? intval($_POST['user_id'] ?? 0) : get_current_user_id();
    if ($user_id <= 0 || !get_userdata($user_id)) {
        error_log('InterSoccer: Invalid user ID for intersoccer_get_player: ' . $user_id);
        wp_send_json_error(['message' => 'Invalid user ID']);
        return;
    }

    $index = intval($_POST['player_index'] ?? -1);
    if ($index < 0) {
        error_log('InterSoccer: Invalid player index for intersoccer_get_player: ' . $index);
        wp_send_json_error(['message' => 'Invalid player index']);
        return;
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (!isset($players[$index])) {
        error_log('InterSoccer: Player index not found for intersoccer_get_player: ' . $index);
        wp_send_json_error(['message' => 'Player not found']);
        return;
    }

    $player = $players[$index];
    $player['event_count'] = intersoccer_get_player_event_count($user_id, $index);
    $player['user_id'] = $user_id;
    $player['region'] = get_user_meta($user_id, 'intersoccer_region', true) ?: 'Unknown';

    error_log('InterSoccer: Player fetched successfully for user ' . $user_id . ', index: ' . $index);
    wp_send_json_success([
        'message' => 'Player retrieved successfully',
        'player' => $player,
    ]);
});

// Hook to add player selection field to checkout for event products
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
    if (!$product || !is_checkout()) {
        return;
    }
    $event_id = get_post_meta($product->get_id(), '_tribe_wooticket_event', true);
    if (!$event_id) {
        return; // Not an event product
    }

    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (empty($players)) {
        return;
    }

    $selected_player_index = $item->get_meta('intersoccer_player_index');
?>
    <div class="intersoccer-player-assignment">
        <label for="player_select_<?php echo esc_attr($item_id); ?>"><?php esc_html_e('Assign Player to Event:', 'intersoccer-player-management'); ?></label>
        <select name="player_select[<?php echo esc_attr($item_id); ?>]" id="player_select_<?php echo esc_attr($item_id); ?>" class="player-select" required>
            <option value=""><?php esc_html_e('Select a Player', 'intersoccer-player-management'); ?></option>
            <?php foreach ($players as $index => $player) : ?>
                <option value="<?php echo esc_attr($index); ?>" <?php selected($selected_player_index, $index); ?>>
                    <?php echo esc_html($player['first_name'] . ' ' . $player['last_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php
}, 10, 3);

// Save player assignment during checkout
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($_POST['player_select'][$item->get_id()])) {
        $player_index = sanitize_text_field($_POST['player_select'][$item->get_id()]);
        $item->add_meta_data('intersoccer_player_index', $player_index, true);
    }
}, 10, 4);

?>
