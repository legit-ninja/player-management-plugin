<?php

/**
 * File: ajax-handlers.php
 * Description: Handles AJAX requests for player management operations in the InterSoccer Player Management plugin, including adding, editing, deleting, retrieving player profiles, product types, days of the week, and course metadata.
 * Dependencies: None
 * Changes:
 * - Standardized nonce to intersoccer_nonce (2025-05-16).
 * - Added intersoccer_get_course_metadata handler (2025-05-16).
 * - Enhanced course metadata validation and removed alternative days attribute check (2025-05-16).
 * - Updated intersoccer_get_course_metadata to fetch metadata from variation ID (2025-05-16).
 * Testing:
 * - Verify player management AJAX actions (add, edit, delete, get players) work without errors.
 * - Test product type retrieval for camps and courses.
 * - Test days of the week retrieval for single-day camps.
 * - Test course metadata retrieval for a course variation (e.g., ID 28965), confirm correct _course_start_date, _course_total_weeks, _course_weekly_discount.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debugging to confirm handler is loaded
error_log('InterSoccer: ajax-handlers.php loaded');

// Get user players
add_action('wp_ajax_intersoccer_get_user_players', 'intersoccer_get_user_players');
add_action('wp_ajax_nopriv_intersoccer_get_user_players', 'intersoccer_get_user_players');
function intersoccer_get_user_players()
{
    if (ob_get_length()) {
        ob_clean();
    }

    error_log('InterSoccer: intersoccer_get_user_players called');
    error_log('InterSoccer: POST data: ' . print_r($_POST, true));

    check_ajax_referer('intersoccer_nonce', 'nonce', false);
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => __('You must be logged in.', 'intersoccer-player-management')], 403);
    }

    if (!isset($_POST['user_id']) || absint($_POST['user_id']) !== $user_id) {
        wp_send_json_error(['message' => __('Unauthorized.', 'intersoccer-player-management')], 403);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    wp_send_json_success(['players' => $players]);
}

// Add player
add_action('wp_ajax_intersoccer_add_player', 'intersoccer_add_player');
function intersoccer_add_player()
{
    if (ob_get_length()) {
        ob_clean();
    }

    check_ajax_referer('intersoccer_nonce', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => __('You must be logged in.', 'intersoccer-player-management')], 403);
    }

    $first_name = sanitize_text_field($_POST['player_first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['player_last_name'] ?? '');
    $dob = sanitize_text_field($_POST['player_dob'] ?? '');
    $gender = sanitize_text_field($_POST['player_gender'] ?? '');
    $medical = sanitize_textarea_field($_POST['player_medical'] ?? '');
    $region = sanitize_text_field($_POST['player_region'] ?? '');

    if (!$first_name || !$last_name) {
        wp_send_json_error(['message' => __('First and last names are required.', 'intersoccer-player-management')], 400);
    }

    if ($dob && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob))) {
        wp_send_json_error(['message' => __('Invalid date of birth format. Use YYYY-MM-DD.', 'intersoccer-player-management')], 400);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    foreach ($players as $player) {
        if (strtolower($player['first_name']) === strtolower($first_name) && strtolower($player['last_name']) === strtolower($last_name)) {
            wp_send_json_error(['message' => __('Player already exists.', 'intersoccer-player-management')], 400);
        }
    }

    $player = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'medical_conditions' => $medical,
        'region' => $region,
    ];

    $players[] = $player;
    update_user_meta($user_id, 'intersoccer_players', $players);
    wp_send_json_success(['message' => __('Player added successfully.', 'intersoccer-player-management'), 'player' => $player]);
}

// Edit player
add_action('wp_ajax_intersoccer_edit_player', 'intersoccer_edit_player');
function intersoccer_edit_player()
{
    if (ob_get_length()) {
        ob_clean();
    }

    check_ajax_referer('intersoccer_nonce', 'nonce');
    $user_id = get_current_user_id();
    $index = absint($_POST['player_index'] ?? -1);
    if (!$user_id) {
        wp_send_json_error(['message' => __('You must be logged in.', 'intersoccer-player-management')], 403);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (!isset($players[$index])) {
        wp_send_json_error(['message' => __('Invalid player index.', 'intersoccer-player-management')], 400);
    }

    $first_name = sanitize_text_field($_POST['player_first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['player_last_name'] ?? '');
    $dob = sanitize_text_field($_POST['player_dob'] ?? '');
    $gender = sanitize_text_field($_POST['player_gender'] ?? '');
    $medical = sanitize_textarea_field($_POST['player_medical'] ?? '');
    $region = sanitize_text_field($_POST['player_region'] ?? '');

    if (!$first_name || !$last_name) {
        wp_send_json_error(['message' => __('First and last names are required.', 'intersoccer-player-management')], 400);
    }

    if ($dob && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob))) {
        wp_send_json_error(['message' => __('Invalid date of birth format. Use YYYY-MM-DD.', 'intersoccer-player-management')], 400);
    }

    $other_players = array_filter($players, function ($p, $i) use ($index) {
        return $i !== $index;
    }, ARRAY_FILTER_USE_BOTH);
    foreach ($other_players as $player) {
        if (strtolower($player['first_name']) === strtolower($first_name) && strtolower($player['last_name']) === strtolower($last_name)) {
            wp_send_json_error(['message' => __('Player already exists.', 'intersoccer-player-management')], 400);
        }
    }

    $players[$index] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'dob' => $dob,
        'gender' => $gender,
        'medical_conditions' => $medical,
        'region' => $region,
    ];

    update_user_meta($user_id, 'intersoccer_players', $players);
    wp_send_json_success(['message' => __('Player updated successfully.', 'intersoccer-player-management'), 'player' => $players[$index]]);
}

// Delete player
add_action('wp_ajax_intersoccer_delete_player', 'intersoccer_delete_player');
function intersoccer_delete_player()
{
    if (ob_get_length()) {
        ob_clean();
    }

    check_ajax_referer('intersoccer_nonce', 'nonce');
    $user_id = get_current_user_id();
    $index = absint($_POST['player_index'] ?? -1);
    if (!$user_id) {
        wp_send_json_error(['message' => __('You must be logged in.', 'intersoccer-player-management')], 403);
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    if (!isset($players[$index])) {
        wp_send_json_error(['message' => __('Invalid player index.', 'intersoccer-player-management')], 400);
    }

    unset($players[$index]);
    $players = array_values($players);
    update_user_meta($user_id, 'intersoccer_players', $players);
    wp_send_json_success(['message' => __('Player deleted successfully.', 'intersoccer-player-management')]);
}

// Get product type
add_action('wp_ajax_intersoccer_get_product_type', 'intersoccer_get_product_type');
add_action('wp_ajax_nopriv_intersoccer_get_product_type', 'intersoccer_get_product_type');
function intersoccer_get_product_type()
{
    if (ob_get_length()) {
        ob_clean();
    }

    error_log('InterSoccer: intersoccer_get_product_type called');
    error_log('InterSoccer: POST data: ' . print_r($_POST, true));

    check_ajax_referer('intersoccer_nonce', 'nonce', false);
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        wp_send_json_error(['message' => __('Invalid product ID.', 'intersoccer-player-management')], 400);
    }

    $product_id = absint($_POST['product_id']);
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => __('Product not found.', 'intersoccer-player-management')], 404);
    }

    $activity_type = wc_get_product_terms($product_id, 'pa_activity-type', ['fields' => 'names']);
    $product_type = !empty($activity_type) ? strtolower($activity_type[0]) : '';
    $is_camp = $product_type === 'camp';

    error_log('InterSoccer: Product ID ' . $product_id . ' activity type: ' . $product_type);

    wp_send_json_success(['product_type' => $is_camp ? 'camp' : 'course']);
}

// Get days of the week for a product
add_action('wp_ajax_intersoccer_get_days_of_week', 'intersoccer_get_days_of_week');
add_action('wp_ajax_nopriv_intersoccer_get_days_of_week', 'intersoccer_get_days_of_week');
function intersoccer_get_days_of_week()
{
    if (ob_get_length()) {
        ob_clean();
    }

    error_log('InterSoccer: intersoccer_get_days_of_week called');
    error_log('InterSoccer: POST data: ' . print_r($_POST, true));
    error_log('InterSoccer: Current user ID: ' . get_current_user_id());

    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'intersoccer_nonce')) {
        error_log('InterSoccer: Nonce verification failed for intersoccer_get_days_of_week. Provided nonce: ' . $nonce);
        wp_send_json_error(['message' => __('Invalid nonce.', 'intersoccer-player-management')], 403);
    }

    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        error_log('InterSoccer: Invalid product ID in intersoccer_get_days_of_week');
        wp_send_json_error(['message' => __('Invalid product ID.', 'intersoccer-player-management')], 400);
    }

    $product_id = absint($_POST['product_id']);
    $product = wc_get_product($product_id);
    if (!$product) {
        error_log('InterSoccer: Product not found for ID: ' . $product_id);
        wp_send_json_error(['message' => __('Product not found.', 'intersoccer-player-management')], 404);
    }

    $parent_id = $product->get_type() === 'variation' ? $product->get_parent_id() : $product_id;
    error_log('InterSoccer: Using parent product ID: ' . $parent_id);

    $attribute_name = 'pa_days-of-week';
    $days = wc_get_product_terms($parent_id, $attribute_name, ['fields' => 'names']);
    error_log('InterSoccer: Fetched ' . $attribute_name . ' for parent product ID: ' . $parent_id . ': ' . print_r($days, true));

    if (empty($days)) {
        error_log('InterSoccer: No days of the week found for parent product ID: ' . $parent_id);
        wp_send_json_error(['message' => __('No days of the week found for this product. Please ensure the Days of Week attribute is set.', 'intersoccer-player-management')], 404);
    }

    $day_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    usort($days, function ($a, $b) use ($day_order) {
        $pos_a = array_search($a, $day_order);
        $pos_b = array_search($b, $day_order);
        if ($pos_a === false) $pos_a = count($day_order);
        if ($pos_b === false) $pos_b = count($day_order);
        return $pos_a - $pos_b;
    });

    error_log('InterSoccer: Days fetched and sorted for product ' . $parent_id . ': ' . print_r($days, true));
    wp_send_json_success(['days' => $days]);
}

// Get course metadata
add_action('wp_ajax_intersoccer_get_course_metadata', 'intersoccer_get_course_metadata');
add_action('wp_ajax_nopriv_intersoccer_get_course_metadata', 'intersoccer_get_course_metadata');
function intersoccer_get_course_metadata()
{
    if (ob_get_length()) {
        ob_clean();
    }

    error_log('InterSoccer: intersoccer_get_course_metadata called');
    error_log('InterSoccer: POST data: ' . print_r($_POST, true));

    check_ajax_referer('intersoccer_nonce', 'nonce', false);
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id']) || !isset($_POST['variation_id']) || !is_numeric($_POST['variation_id'])) {
        wp_send_json_error(['message' => __('Invalid product or variation ID.', 'intersoccer-player-management')], 400);
    }

    $product_id = absint($_POST['product_id']);
    $variation_id = absint($_POST['variation_id']);
    $product = wc_get_product($variation_id);
    if (!$product) {
        wp_send_json_error(['message' => __('Product not found.', 'intersoccer-player-management')], 404);
    }

    $start_date = get_post_meta($variation_id, '_course_start_date', true);
    $weekly_discount = get_post_meta($variation_id, '_course_weekly_discount', true);
    $total_weeks = get_post_meta($variation_id, '_course_total_weeks', true);

    if (!$start_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        error_log('InterSoccer: Invalid or missing _course_start_date for variation ' . $variation_id);
        $start_date = '2025-01-01';
    }
    $weekly_discount = floatval($weekly_discount ?: 0);
    $total_weeks = intval($total_weeks ?: 1);
    if ($total_weeks < 1) {
        error_log('InterSoccer: Invalid _course_total_weeks for variation ' . $variation_id);
        $total_weeks = 1;
    }

    $metadata = [
        'start_date' => $start_date,
        'weekly_discount' => $weekly_discount,
        'total_weeks' => $total_weeks,
    ];

    error_log('InterSoccer: Course metadata for variation ' . $variation_id . ': ' . print_r($metadata, true));
    wp_send_json_success($metadata);
}

