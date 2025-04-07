<?php
/*
 * Plugin Name: InterSoccer Player Management
 * Description: Adds player management for Summer Football Camps to WooCommerce.
 * Version: 1.0.0
 * Author: Jeremy Lee
 * License: GPL-2.0+
 * Text Domain: intersoccer-player-management
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

// Include the player management functionality
require_once plugin_dir_path(__FILE__) . 'includes/player-management.php';

// Include the admin players management feature
require_once plugin_dir_path(__FILE__) . 'includes/admin-players.php';

// Include the checkout modifications
require_once plugin_dir_path(__FILE__) . 'includes/checkout.php';
?>

