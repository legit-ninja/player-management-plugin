<?php
/**
 * Plugin Name: Player Management
 * Description: Manages players for InterSoccer events, integrating with WooCommerce My Account page and providing an admin dashboard.
 * Version: 1.3.9
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

// Check if intersoccer-product-variations is active (optional)
if (!is_plugin_active('intersoccer-product-variations/intersoccer-product-variations.php')) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('InterSoccer Product Variations is not active. Some features of Player Management may be limited.', 'player-management'); ?></p>
        </div>
        <?php
    });
    // Continue loading the plugin even if intersoccer-product-variations is not active
}

// Include core files
require_once PLAYER_MANAGEMENT_PATH . 'includes/player-management.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/ajax-handlers.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/data-deletion.php';

// Include admin files only in admin context
if (is_admin()) {
    require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-players.php';
    require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-advanced.php';
}

// Include Elementor widget only if Elementor is active
add_action('plugins_loaded', function () {
    if (is_plugin_active('elementor/elementor.php')) {
        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-widgets.php';
    } else {
        add_action('admin_notices', function () {
            if (current_user_can('activate_plugins')) {
                ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Player Management: Elementor is not active. The Attendee Management widget will not be available. Please activate Elementor to use this feature.', 'player-management'); ?></p>
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
?>
