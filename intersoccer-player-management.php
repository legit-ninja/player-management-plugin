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

// Include the player management functionality
require_once plugin_dir_path(__FILE__) . 'includes/player-management.php';

// Include the admin players management feature
require_once plugin_dir_path(__FILE__) . 'includes/admin-players.php';

// Optionally include other files if needed (e.g., checkout.php, admin.php)
// require_once plugin_dir_path(__FILE__) . 'includes/checkout.php';
?>

