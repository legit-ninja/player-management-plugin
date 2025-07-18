<?php
/**
 * Plugin Name: Player Management
 * Description: Manages players for InterSoccer events, integrating with WooCommerce My Account page and providing an admin dashboard.
 * Version: 1.3.95
 * Author: Jeremy Lee
 * Text Domain: player-management
 * Dependencies: WooCommerce, Elementor (optional for widgets), intersoccer-product-variations (optional)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('PLAYER_MANAGEMENT_PATH', plugin_dir_path(__FILE__));

// Load translation
add_action('init', function () {
    load_plugin_textdomain('player-management', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Check if WooCommerce is active (required)
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Player Management requires WooCommerce to be installed and active.', 'player-management'); ?></p>
        </div>
        <?php
    });
    return;
}

// Include core files
require_once PLAYER_MANAGEMENT_PATH . 'includes/player-management.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/ajax-handlers.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/data-deletion.php';

// Include admin files only in admin context
if (is_admin()) {
    require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-players.php';
    require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-advanced.php';
    require_once PLAYER_MANAGEMENT_PATH . 'includes/user-profile-players.php';
}

// Add endpoint for manage-players
add_action('init', function () {
    add_rewrite_endpoint('manage-players', EP_ROOT | EP_PAGES);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Registered manage-players endpoint');
    }
});

// Flush rewrite rules on activation
register_activation_hook(__FILE__, function () {
    add_rewrite_endpoint('manage-players', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
});

// Flush rewrite rules on deactivation
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// Add manage-players to My Account menu
add_filter('woocommerce_account_menu_items', function ($items) {
    $new_items = [];
    $inserted = false;
    foreach ($items as $key => $label) {
        $new_items[$key] = $label;
        if ($key === 'dashboard' && !$inserted) {
            $new_items['manage-players'] = __('Manage Players', 'player-management');
            $inserted = true;
        }
    }
    if (!$inserted) {
        $new_items['manage-players'] = __('Manage Players', 'player-management');
    }
    return $new_items;
}, 10);

// Add custom roles
add_action('init', function () {
    add_role('coach', __('Coach', 'player-management'), ['read' => true, 'edit_posts' => true]);
    add_role('organizer', __('Organizer', 'player-management'), ['read' => true, 'edit_posts' => true]);
});

// Ensure endpoint is recognized by Elementor
add_filter('woocommerce_get_query_vars', function ($query_vars) {
    $query_vars['manage-players'] = 'manage-players';
    return $query_vars;
});

// Flush permalinks on activation
add_action('init', function () {
    if (get_option('player_management_flush_permalinks')) {
        flush_rewrite_rules();
        delete_option('player_management_flush_permalinks');
    }
});

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function () {
    if (is_account_page() && (isset($_GET['manage-players']) || strpos($_SERVER['REQUEST_URI'], 'manage-players') !== false)) {
        $user_id = get_current_user_id();
        if ($user_id) {
            // Fetch player data for preloading
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
            $preload_players = [];
            foreach ($players as $index => $player) {
                $preload_players[$index] = [
                    'first_name' => $player['first_name'] ?? 'N/A',
                    'last_name' => $player['last_name'] ?? 'N/A',
                    'dob' => $player['dob'] ?? 'N/A',
                    'gender' => $player['gender'] ?? 'N/A',
                    'avs_number' => $player['avs_number'] ?? 'N/A',
                    'event_count' => intersoccer_get_player_event_count($user_id, $index) ?? 0,
                    'medical_conditions' => $player['medical_conditions'] ?? '',
                    'creation_timestamp' => $player['creation_timestamp'] ?? '',
                    'user_id' => $user_id,
                    'canton' => get_user_meta($user_id, 'billing_state', true) ?: '',
                    'city' => get_user_meta($user_id, 'billing_city', true) ?: '',
                    // 'past_events' => intersoccer_get_player_past_events($user_id, $index) ?? []
                ];
            }

            // Enqueue styles
            wp_enqueue_style(
                'intersoccer-player-management',
                plugin_dir_url(__FILE__) . 'css/player-management.css',
                [],
                '1.0.' . time()
            );
            wp_enqueue_style(
                'intersoccer-loading',
                plugin_dir_url(__FILE__) . 'css/loading.css',
                [],
                '1.0.' . time()
            );

            // Enqueue scripts
            wp_enqueue_script(
                'intersoccer-player-management-core',
                plugin_dir_url(__FILE__) . 'js/player-management-core.js',
                ['jquery'],
                '1.0.' . time(),
                true
            );
            wp_enqueue_script(
                'intersoccer-player-management-actions',
                plugin_dir_url(__FILE__) . 'js/player-management-actions.js',
                ['intersoccer-player-management-core'],
                '1.0.' . time(),
                true
            );

            // Localize script
            $localize_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_player_nonce'),
                'user_id' => $user_id,
                'is_admin' => current_user_can('manage_options') ? '1' : '0',
                'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
                'preload_players' => $preload_players,
                'server_time' => current_time('mysql'),
            ];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Localizing intersoccerPlayer data: ' . json_encode($localize_data));
            }
            wp_localize_script(
                'intersoccer-player-management-core',
                'intersoccerPlayer',
                $localize_data
            );
        }
    }
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'user-edit.php' || $hook === 'profile.php') {
        // Enqueue Flatpickr
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        
        // Enqueue core.js and player-management.js
        wp_enqueue_script('intersoccer-player-core-js', plugins_url('js/player-management-core.js', __FILE__), ['jquery', 'flatpickr'], '1.0', true);
        wp_enqueue_script('intersoccer-player-management-js', plugins_url('js/player-management.js', __FILE__), ['jquery', 'intersoccer-player-core-js'], '1.0', true);
        
        // Localize
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_player_nonce'),
            'user_id' => $user_id,
            'is_admin' => '1',
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
            'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
            'preload_players' => get_user_meta($user_id, 'intersoccer_players', true) ?: [],
            'server_time' => current_time('mysql')
        ];
        wp_localize_script('intersoccer-player-management-js', 'intersoccerPlayer', $localize_data);
        wp_localize_script('intersoccer-player-core-js', 'intersoccerPlayer', $localize_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Enqueued flatpickr, core.js, and player-management.js on ' . $hook . ', user_id: ' . $user_id);
        }
    } elseif (strpos($hook, 'intersoccer-players') !== false) {
        // Enqueue for dashboard
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('intersoccer-admin-js', plugins_url('js/admin.js', __FILE__), ['jquery', 'intersoccer-player-core-js', 'flatpickr'], '1.0', true);
        wp_localize_script('intersoccer-admin-js', 'intersoccerPlayer', $localize_data);
    }
});
?>