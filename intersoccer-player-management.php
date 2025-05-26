<?php

/**
 * Plugin Name: InterSoccer Player Management
 * Description: Manages players for InterSoccer events, integrating with WooCommerce My Account page and providing an admin dashboard.
 * Version: 1.0.0
 * Author: Jeremy Lee
 * Text Domain: intersoccer-player-management
 * Dependencies: WooCommerce, Elementor (optional for widgets)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('INTERSOCCER_PLAYER_MANAGEMENT_PATH', plugin_dir_path(__FILE__));

// Load translation
add_action('init', function () {
    load_plugin_textdomain('intersoccer-player-management', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Check if WooCommerce is active (required dependency)
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', function () {
?>
        <div class="notice notice-error">
            <p><?php esc_html_e('InterSoccer Player Management requires WooCommerce to be installed and active.', 'intersoccer-player-management'); ?></p>
        </div>
        <?php
    });
    return; // Exit if WooCommerce is not active
}

// Include core files
require_once INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'includes/player-management.php';
require_once INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'includes/ajax-handlers.php';

// Include Elementor widget only if Elementor is active
add_action('plugins_loaded', function () {
    if (is_plugin_active('elementor/elementor.php')) {
        require_once INTERSOCCER_PLAYER_MANAGEMENT_PATH . 'includes/elementor-widgets.php';
    } else {
        add_action('admin_notices', function () {
            if (current_user_can('activate_plugins')) {
        ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('InterSoccer Player Management: Elementor is not active. The Attendee Management widget will not be available. Please activate Elementor to use this feature.', 'intersoccer-player-management'); ?></p>
                </div>
<?php
            }
        });
    }
});

// Add endpoint for manage-players
add_action('init', function () {
    add_rewrite_endpoint('manage-players', EP_ROOT | EP_PAGES);
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
    $items['manage-players'] = __('Manage Attendees', 'intersoccer-player-management');
    return $items;
});

// Add custom roles
add_action('init', function () {
    add_role('coach', __('Coach', 'intersoccer-player-management'), array('read' => true, 'edit_posts' => true));
    add_role('organizer', __('Organizer', 'intersoccer-player-management'), array('read' => true, 'edit_posts' => true));
});

// Register endpoint
add_action('init', function () {
    add_rewrite_endpoint('manage-players', EP_ROOT | EP_PAGES);
});

// Add menu item with high priority (after Dashboard)
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

// Ensure endpoint is recognized by Elementor
add_filter('woocommerce_get_query_vars', function ($query_vars) {
    $query_vars['manage-players'] = 'manage-players';
    return $query_vars;
});

// Flush permalinks on activation
add_action('init', function () {
    if (get_option('intersoccer_flush_permalinks')) {
        flush_rewrite_rules();
        delete_option('intersoccer_flush_permalinks');
    }
});
?>