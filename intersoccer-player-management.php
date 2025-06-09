<?php

/**
 * Plugin Name: InterSoccer Player Management
 * Description: Manages player profiles for InterSoccer Switzerland, integrated with WooCommerce My Account page.
 * Version: 1.0.14
 * Author: Jeremy Lee
 * Text Domain: intersoccer-player-management
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('INTERSOCCER_PLAYER_MANAGEMENT_VERSION', '1.0.14');
define('INTERSOCCER_PLAYER_MANAGEMENT_PATH', plugin_dir_path(__FILE__));
define('INTERSOCCER_PLAYER_MANAGEMENT_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'includes/player-management.php';
require_once INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'includes/ajax-handlers.php';

// Initialize plugin
add_action('plugins_loaded', function () {
    load_plugin_textdomain('intersoccer-player-management', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Register scripts
add_action('wp_enqueue_scripts', function () {
    if (is_account_page() && get_query_var('manage-players')) {
        // Enqueue player-management.js
        $script_path = INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'js/player-management.js';
        $script_url = INTERSOCCER_PLAYER_MANAGEMENT_URL . 'js/player-management.js';
        if (!file_exists($script_path)) {
            error_log('InterSoccer: player-management.js not found at ' . $script_path);
        } else {
            error_log('InterSoccer: Found player-management.js at ' . $script_path);
        }
        error_log('InterSoccer: Enqueuing player-management.js at ' . $script_url);
        wp_enqueue_script(
            'intersoccer-player-management',
            $script_url,
            ['jquery'],
            INTERSOCCER_PLAYER_MANAGEMENT_VERSION . '.' . time(),
            true
        );
        wp_localize_script(
            'intersoccer-player-management',
            'intersoccerPlayer',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_player_nonce'),
                'user_id' => get_current_user_id(),
                'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
            ]
        );
        // Dequeue variation-details.js
        wp_dequeue_script('intersoccer-variation-details');
        wp_deregister_script('intersoccer-variation-details');
        wp_dequeue_script('variation-details');
        wp_deregister_script('variation-details');
        wp_dequeue_script('intersoccer-variations');
        wp_deregister_script('intersoccer-variations');
        error_log('InterSoccer: Dequeued variation-details.js and variants on manage-players');
    }
}, 1000); // Very high priority to dequeue after other plugins

// Debug all enqueued scripts
add_action('wp_print_scripts', function () {
    global $wp_scripts;
    if (is_account_page() && get_query_var('manage-players')) {
        error_log('InterSoccer: Enqueued scripts on manage-players: ' . json_encode(array_keys($wp_scripts->queue)));
    }
}, 9999);

// Add manage-players endpoint
add_action('init', function () {
    add_rewrite_endpoint('manage-players', EP_ROOT | EP_PAGES);
});

// Add menu item to My Account
add_filter('woocommerce_account_menu_items', function ($items) {
    $new_items = [];
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        if ($key === 'dashboard') {
            $new_items['manage-players'] = __('Manage Players', 'intersoccer-player-management');
        }
    }
    return $new_items;
}, 10, 1);

// Ensure AJAX handlers are loaded
add_action('wp_ajax_intersoccer_get_user_role', function () {
    // Placeholder to ensure AJAX actions are registered
});

