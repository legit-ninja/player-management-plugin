<?php
/**
 * Plugin Name: Player Management
 * Plugin URI: https://github.com/legit-ninja/player-management-plugin
 * Description: Manages players for InterSoccer events, integrating with WooCommerce My Account page and providing an admin dashboard.
 * Version: 1.3.130
 * Author: Jeremy Lee
 * Author URI: https://underdogunlimited.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: intersoccer-player-management
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * Network: false
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('PLAYER_MANAGEMENT_VERSION', '1.3.125');
define('PLAYER_MANAGEMENT_PATH', plugin_dir_path(__FILE__));
define('PLAYER_MANAGEMENT_URL', plugin_dir_url(__FILE__));

// Load translation
add_action('init', function () {
    load_plugin_textdomain('intersoccer-player-management', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Check if WooCommerce is active (required)
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Player Management requires WooCommerce to be installed and active.', 'intersoccer-player-management'); ?></p>
        </div>
        <?php
    });
    return;
}

// Include core files (check if they exist first)
$core_files = [
    'includes/player-management.php',
    'includes/ajax-handlers.php', 
    'includes/data-deletion.php'
];

foreach ($core_files as $file) {
    $file_path = PLAYER_MANAGEMENT_PATH . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Log missing file in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer Plugin: Missing core file: $file");
        }
    }
}

// Include admin files only in admin context
if (is_admin()) {
    $admin_files = [
        'includes/admin-players.php',
        'includes/admin-advanced.php',
        'includes/user-profile-players.php'
    ];
    
    foreach ($admin_files as $file) {
        $file_path = PLAYER_MANAGEMENT_PATH . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // Log missing file in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Plugin: Missing admin file: $file");
            }
        }
    }
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
    
    // Set version option
    add_option('intersoccer_player_management_version', PLAYER_MANAGEMENT_VERSION);
    
    // Create database tables if needed
    intersoccer_create_plugin_tables();
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
            $new_items['manage-players'] = __('Manage Players', 'intersoccer-player-management');
            $inserted = true;
        }
    }
    if (!$inserted) {
        $new_items['manage-players'] = __('Manage Players', 'intersoccer-player-management');
    }
    return $new_items;
}, 10);

// Add custom roles
add_action('init', function () {
    if (!get_role('coach')) {
        add_role('coach', __('Coach', 'intersoccer-player-management'), ['read' => true, 'edit_posts' => true]);
    }
    if (!get_role('organizer')) {
        add_role('organizer', __('Organizer', 'intersoccer-player-management'), ['read' => true, 'edit_posts' => true]);
    }
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

/**
 * Create database tables for future features
 */
function intersoccer_create_plugin_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Player events table for future gamification
    $table_name = $wpdb->prefix . 'intersoccer_player_events';
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        player_index int(11) NOT NULL,
        player_name varchar(255) NOT NULL,
        order_id bigint(20) NOT NULL,
        item_id bigint(20) NOT NULL,
        event_type varchar(50) NOT NULL,
        event_name varchar(255) NOT NULL,
        event_start date,
        event_end date,
        venue varchar(255),
        status varchar(50) DEFAULT 'registered',
        registration_date datetime DEFAULT CURRENT_TIMESTAMP,
        completion_date datetime,
        attendance_days int(11) DEFAULT 0,
        total_days int(11) DEFAULT 0,
        attendance_percentage decimal(5,2),
        points_earned int(11) DEFAULT 0,
        notes text,
        PRIMARY KEY (id),
        KEY user_player (user_id, player_index),
        KEY order_item (order_id, item_id),
        KEY event_type (event_type),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Helper function to get player event count (fallback if file missing)
 */
if (!function_exists('intersoccer_get_player_event_count')) {
    function intersoccer_get_player_event_count($user_id, $player_index) {
        // Simple fallback - count from orders
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => -1
        ]);
        
        $count = 0;
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        
        if (!isset($players[$player_index])) {
            return 0;
        }
        
        $player_name = ($players[$player_index]['first_name'] ?? '') . ' ' . ($players[$player_index]['last_name'] ?? '');
        
        foreach ($orders as $order) {
            $order_players = $order->get_meta('intersoccer_players', true);
            if ($order_players && in_array($player_name, (array)$order_players)) {
                $count++;
            }
        }
        
        return $count;
    }
}

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
                ];
            }

            // Enqueue styles (check if files exist)
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'css/player-management.css')) {
                wp_enqueue_style(
                    'intersoccer-player-management',
                    PLAYER_MANAGEMENT_URL . 'css/player-management.css',
                    [],
                    PLAYER_MANAGEMENT_VERSION
                );
            }
            
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'css/loading.css')) {
                wp_enqueue_style(
                    'intersoccer-loading',
                    PLAYER_MANAGEMENT_URL . 'css/loading.css',
                    [],
                    PLAYER_MANAGEMENT_VERSION
                );
            }

            // Enqueue scripts (check if files exist)
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management-core.js')) {
                wp_enqueue_script(
                    'intersoccer-player-management-core',
                    PLAYER_MANAGEMENT_URL . 'js/player-management-core.js',
                    ['jquery'],
                    PLAYER_MANAGEMENT_VERSION,
                    true
                );
            }
            
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management-actions.js')) {
                wp_enqueue_script(
                    'intersoccer-player-management-actions',
                    PLAYER_MANAGEMENT_URL . 'js/player-management-actions.js',
                    ['intersoccer-player-management-core'],
                    PLAYER_MANAGEMENT_VERSION,
                    true
                );
            }

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
                'version' => PLAYER_MANAGEMENT_VERSION
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
        
        // Enqueue core.js and player-management.js (check if they exist)
        if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management-core.js')) {
            wp_enqueue_script('intersoccer-player-core-js', PLAYER_MANAGEMENT_URL . 'js/player-management-core.js', ['jquery', 'flatpickr'], PLAYER_MANAGEMENT_VERSION, true);
        }
        
        if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management.js')) {
            wp_enqueue_script('intersoccer-player-management-js', PLAYER_MANAGEMENT_URL . 'js/player-management.js', ['jquery', 'intersoccer-player-core-js'], PLAYER_MANAGEMENT_VERSION, true);
        }
        
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
            'server_time' => current_time('mysql'),
            'version' => PLAYER_MANAGEMENT_VERSION
        ];
        
        wp_localize_script('intersoccer-player-management-js', 'intersoccerPlayer', $localize_data);
        wp_localize_script('intersoccer-player-core-js', 'intersoccerPlayer', $localize_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Enqueued scripts on ' . $hook . ', user_id: ' . $user_id);
        }
    } elseif (strpos($hook, 'intersoccer-players') !== false) {
        // Enqueue for dashboard
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        
        if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/admin.js')) {
            wp_enqueue_script('intersoccer-admin-js', PLAYER_MANAGEMENT_URL . 'js/admin.js', ['jquery', 'flatpickr'], PLAYER_MANAGEMENT_VERSION, true);
            wp_localize_script('intersoccer-admin-js', 'intersoccerPlayer', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_player_nonce'),
                'version' => PLAYER_MANAGEMENT_VERSION
            ]);
        }
    }
});

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=intersoccer-players') . '">' . __('Settings', 'intersoccer-player-management') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Plugin status check for debugging
add_action('admin_notices', function() {
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
        $missing_files = [];
        
        // Check required files
        $required_files = [
            'includes/admin-players.php',
            'js/player-management-core.js'
        ];
        
        foreach ($required_files as $file) {
            if (!file_exists(PLAYER_MANAGEMENT_PATH . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (!empty($missing_files)) {
            ?>
            <div class="notice notice-warning">
                <p><strong>Player Management:</strong> Missing files detected: <?php echo implode(', ', $missing_files); ?></p>
            </div>
            <?php
        }
    }
});
?>