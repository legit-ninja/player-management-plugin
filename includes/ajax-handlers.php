<?php
add_action('wp_ajax_intersoccer_add_player', 'intersoccer_add_player');
function intersoccer_add_player() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: AJAX request received for add_player, nonce: ' . $nonce . ', user_id: ' . $user_id);
    }

    if (!check_ajax_referer('intersoccer_player_nonce', 'nonce', false)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Nonce verification failed for nonce: ' . $nonce);
        }
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    if (!$user_id || (!current_user_can('edit_users') && !current_user_can('edit_user', $user_id))) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Permission check failed for user_id: ' . $user_id);
        }
        wp_send_json_error(['message' => 'Invalid user or insufficient permissions'], 403);
    }

    $first_name = isset($_POST['player_first_name']) ? sanitize_text_field(urldecode($_POST['player_first_name'])) : '';
    $last_name = isset($_POST['player_last_name']) ? sanitize_text_field(urldecode($_POST['player_last_name'])) : '';
    $dob = isset($_POST['player_dob']) ? sanitize_text_field($_POST['player_dob']) : '';
    $gender = isset($_POST['player_gender']) ? sanitize_text_field($_POST['player_gender']) : '';
    $avs_number = isset($_POST['player_avs_number']) ? sanitize_text_field($_POST['player_avs_number']) : '0000';
    $medical_conditions = isset($_POST['player_medical']) ? sanitize_textarea_field(urldecode($_POST['player_medical'])) : '';

    if (!$first_name || !$last_name || !$dob || !$gender) {
        wp_send_json_error(['message' => 'All required fields must be provided'], 400);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob)) {
        wp_send_json_error(['message' => 'Invalid date of birth format'], 400);
    }

    // Server-side age validation (3-13 years)
    $dob_time = strtotime($dob);
    $current_time = current_time('timestamp');
    $age = date('Y', $current_time) - date('Y', $dob_time) - ((date('m', $current_time) < date('m', $dob_time)) || (date('m', $current_time) == date('m', $dob_time) && date('d', $current_time) < date('d', $dob_time)) ? 1 : 0);
    if ($age < 3 || $age > 13) {
        wp_send_json_error(['message' => 'Player must be between 3 and 13 years old.'], 400);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Checking for duplicate player, user_id: ' . $user_id . ', existing players: ' . json_encode($players));
        global $wpdb;
        $order_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = 'intersoccer_player_index' AND meta_value != ''"
        ));
        error_log('InterSoccer: Order item metadata for intersoccer_player_index: ' . json_encode($order_meta));
    }

    foreach ($players as $index => $player) {
        if (
            strtolower($player['first_name']) === strtolower($first_name) &&
            strtolower($player['last_name']) === strtolower($last_name) &&
            $player['dob'] === $dob
        ) {
            wp_send_json_error(['message' => 'This player already exists. Please check the player list or edit an existing player.'], 409);
        }
    }

    $new_player = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'avs_number' => $avs_number,
        'medical_conditions' => $medical_conditions,
        'creation_timestamp' => current_time('timestamp'),
    ];

    $players[] = $new_player;
    $new_index = count($players) - 1;
    $updated = update_user_meta($user_id, 'intersoccer_players', $players);

    if ($updated) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Player added for user ' . $user_id . ': ' . json_encode($new_player));
        }
        wp_send_json_success([
            'message' => 'Player added successfully',
            'player' => array_merge($new_player, [
                'player_index' => $new_index,
                'user_id' => $user_id,
                'event_count' => 0, // New players have no events
            ]),
        ]);
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Failed to update user meta for user ' . $user_id);
        }
        wp_send_json_error(['message' => 'Failed to save player'], 500);
    }
}

add_action('wp_ajax_intersoccer_edit_player', 'intersoccer_edit_player');
function intersoccer_edit_player() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $player_index = isset($_POST['player_index']) ? intval($_POST['player_index']) : -1;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: AJAX request received for edit_player, nonce: ' . $nonce . ', user_id: ' . $user_id . ', player_index: ' . $player_index);
    }

    if (!check_ajax_referer('intersoccer_player_nonce', 'nonce', false)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Nonce verification failed for nonce: ' . $nonce);
        }
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    if (!$user_id || $player_index < 0 || (!current_user_can('edit_users') && !current_user_can('edit_user', $user_id))) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Permission check failed for user_id: ' . $user_id . ', player_index: ' . $player_index);
        }
        wp_send_json_error(['message' => 'Invalid user, index, or insufficient permissions'], 403);
    }

    $first_name = isset($_POST['player_first_name']) ? sanitize_text_field(urldecode($_POST['player_first_name'])) : '';
    $last_name = isset($_POST['player_last_name']) ? sanitize_text_field(urldecode($_POST['player_last_name'])) : '';
    $dob = isset($_POST['player_dob']) ? sanitize_text_field($_POST['player_dob']) : '';
    $gender = isset($_POST['player_gender']) ? sanitize_text_field($_POST['player_gender']) : '';
    $avs_number = isset($_POST['player_avs_number']) ? sanitize_text_field($_POST['player_avs_number']) : '0000';
    $medical_conditions = isset($_POST['player_medical']) ? sanitize_textarea_field(urldecode($_POST['player_medical'])) : '';

    if (!$first_name || !$last_name || !$dob || !$gender) {
        wp_send_json_error(['message' => 'All required fields must be provided'], 400);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob)) {
        wp_send_json_error(['message' => 'Invalid date of birth format'], 400);
    }

    // Server-side age validation (3-13 years)
    $dob_time = strtotime($dob);
    $current_time = current_time('timestamp');
    $age = date('Y', $current_time) - date('Y', $dob_time) - ((date('m', $current_time) < date('m', $dob_time)) || (date('m', $current_time) == date('m', $dob_time) && date('d', $current_time) < date('d', $dob_time)) ? 1 : 0);
    if ($age < 3 || $age > 13) {
        wp_send_json_error(['message' => 'Player must be between 3 and 13 years old.'], 400);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];

    if (!isset($players[$player_index])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Player not found at index: ' . $player_index . ' for user: ' . $user_id);
        }
        wp_send_json_error(['message' => 'Player not found'], 404);
    }

    foreach ($players as $i => $player) {
        if ($i === $player_index) continue;
        if (
            strtolower($player['first_name']) === strtolower($first_name) &&
            strtolower($player['last_name']) === strtolower($last_name) &&
            $player['dob'] === $dob
        ) {
            wp_send_json_error(['message' => 'This player already exists. Please check the player list or edit an existing player.'], 409);
        }
    }

    $updated_player = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'avs_number' => $avs_number,
        'medical_conditions' => $medical_conditions,
        'creation_timestamp' => $players[$player_index]['creation_timestamp'] ?? current_time('timestamp'),
    ];

    $players[$player_index] = $updated_player;
    $updated = update_user_meta($user_id, 'intersoccer_players', $players);

    if ($updated) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Player edited for user ' . $user_id . ' at index ' . $player_index . ': ' . json_encode($updated_player));
        }
        wp_send_json_success([
            'message' => 'Player updated successfully',
            'player' => array_merge($updated_player, [
                'player_index' => $player_index,
                'user_id' => $user_id,
                'event_count' => intersoccer_get_player_event_count($user_id, $player_index),
            ]),
        ]);
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Failed to update user meta for user ' . $user_id . ' at index ' . $player_index);
        }
        wp_send_json_error(['message' => 'Failed to update player'], 500);
    }
}

// add_action('wp_ajax_intersoccer_refresh_nonce', 'intersoccer_refresh_nonce');
// function intersoccer_refresh_nonce() defined in main plugin file

// Add delete player handler
add_action('wp_ajax_intersoccer_delete_player', 'intersoccer_delete_player');
function intersoccer_delete_player() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $player_index = isset($_POST['player_index']) ? intval($_POST['player_index']) : -1;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: AJAX request received for delete_player, nonce: ' . $nonce . ', user_id: ' . $user_id . ', player_index: ' . $player_index);
    }

    if (!check_ajax_referer('intersoccer_player_nonce', 'nonce', false)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Nonce verification failed for nonce: ' . $nonce);
        }
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    if (!$user_id || $player_index < 0 || (!current_user_can('edit_users') && !current_user_can('edit_user', $user_id))) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Permission check failed for user_id: ' . $user_id . ', player_index: ' . $player_index);
        }
        wp_send_json_error(['message' => 'Invalid user, index, or insufficient permissions'], 403);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];

    if (!isset($players[$player_index])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Player not found at index: ' . $player_index . ' for user: ' . $user_id);
        }
        wp_send_json_error(['message' => 'Player not found'], 404);
    }

    unset($players[$player_index]);
    $players = array_values($players); // Reindex array to prevent gaps
    $updated = update_user_meta($user_id, 'intersoccer_players', $players);

    if ($updated) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Player deleted for user ' . $user_id . ' at index ' . $player_index);
        }
        wp_send_json_success(['message' => 'Player deleted successfully']);
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Failed to update user meta for user ' . $user_id . ' at index ' . $player_index);
        }
        wp_send_json_error(['message' => 'Failed to delete player'], 500);
    }
}
?>