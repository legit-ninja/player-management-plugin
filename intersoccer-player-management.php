<?php
/*
 * Plugin Name: InterSoccer Player Management
 * Description: Adds player management for Summer Football Camps to WooCommerce.
 * Version: 1.0.0
 * Author: Jeremy Lee
 * License: GPL-2.0+
 * Text Domain: intersoccer-player-management
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Load the plugin text domain for translations
add_action('init', 'load_intersoccer_textdomain');
function load_intersoccer_textdomain() {
    load_plugin_textdomain('intersoccer-player-management', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Register the pa_course-start-and-end-dates taxonomy
add_action('init', 'register_course_start_end_dates_taxonomy');
function register_course_start_end_dates_taxonomy() {
    $labels = array(
        'name' => _x('Course Start and End Dates', 'taxonomy general name', 'intersoccer-player-management'),
        'singular_name' => _x('Course Start and End Date', 'taxonomy singular name', 'intersoccer-player-management'),
        'search_items' => __('Search Course Start and End Dates', 'intersoccer-player-management'),
        'all_items' => __('All Course Start and End Dates', 'intersoccer-player-management'),
        'edit_item' => __('Edit Course Start and End Date', 'intersoccer-player-management'),
        'update_item' => __('Update Course Start and End Date', 'intersoccer-player-management'),
        'add_new_item' => __('Add New Course Start and End Date', 'intersoccer-player-management'),
        'new_item_name' => __('New Course Start and End Date Name', 'intersoccer-player-management'),
        'menu_name' => __('Course Start and End Dates', 'intersoccer-player-management'),
    );

    $args = array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'course-start-end-dates'),
        'show_in_rest' => true,
    );

    register_taxonomy('pa_course-start-and-end-dates', array('product'), $args);

    // Register the taxonomy as a WooCommerce attribute
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    $attribute_exists = false;
    foreach ($attribute_taxonomies as $tax) {
        if ($tax->attribute_name === 'course-start-and-end-dates') {
            $attribute_exists = true;
            break;
        }
    }

    if (!$attribute_exists) {
        wc_create_attribute(array(
            'name' => __('Course Start and End Dates', 'intersoccer-player-management'),
            'slug' => 'course-start-and-end-dates',
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false,
        ));
    }
}

// Include the player management functionality
require_once plugin_dir_path(__FILE__) . 'includes/player-management.php';

// Include the admin players management feature
require_once plugin_dir_path(__FILE__) . 'includes/admin-players.php';

// Include the checkout modifications
require_once plugin_dir_path(__FILE__) . 'includes/checkout.php';

// Include the Event Tickets integration
require_once plugin_dir_path(__FILE__) . 'includes/event-tickets-integration.php';

// Include the Player Trading Cards widget
require_once plugin_dir_path(__FILE__) . 'includes/player-trading-cards.php';

// Include the Player Count widget
require_once plugin_dir_path(__FILE__) . 'includes/player-count.php';

// Include the Upcoming Events widget
require_once plugin_dir_path(__FILE__) . 'includes/upcoming-events.php';

// Include the Data Deletion feature
require_once plugin_dir_path(__FILE__) . 'includes/data-deletion.php';

// Include the Mobile Check-In feature
require_once plugin_dir_path(__FILE__) . 'includes/mobile-checkin.php';
?>

