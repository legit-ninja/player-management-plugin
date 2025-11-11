<?php
/**
 * PHPUnit bootstrap file for InterSoccer Player Management Plugin
 * Uses WP_Mock for WordPress function mocking
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Provide lightweight WordPress function shims for the test environment
require_once __DIR__ . '/helpers/wp-stubs.php';

// Initialize WP_Mock
WP_Mock::bootstrap();

// Define plugin constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('PLAYER_MANAGEMENT_VERSION')) {
    define('PLAYER_MANAGEMENT_VERSION', '1.11.11');
}

if (!defined('PLAYER_MANAGEMENT_PATH')) {
    define('PLAYER_MANAGEMENT_PATH', dirname(__DIR__) . '/');
}

if (!defined('PLAYER_MANAGEMENT_URL')) {
    define('PLAYER_MANAGEMENT_URL', 'http://example.com/wp-content/plugins/player-management/');
}

if (!defined('INTERSOCCER_PLAYER_TEXT_DOMAIN')) {
    define('INTERSOCCER_PLAYER_TEXT_DOMAIN', 'player-management');
}

// Load plugin classes that don't depend on WordPress initialization
// These will be loaded individually in test files as needed
