<?php
/**
 * InterSoccer Player Management Elementor Bootstrap
 *
 * @package InterSoccer_Player_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads Elementor dependencies and registers widgets.
 */
class InterSoccer_Player_Elementor_Bootstrap {

    /**
     * Initialize Elementor integration when Elementor is available.
     */
    public static function init() {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        self::define_legacy_constants();
        self::load_dependencies();

        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-elementor-integration.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-elementor-manager.php';

        InterSoccer_Player_Management_Elementor_Integration::get_instance();
        new InterSoccer_Player_Elementor_Manager();
    }

    /**
     * Map legacy INTERSOCCER_PLAYER_* constants to PLAYER_MANAGEMENT_*.
     */
    private static function define_legacy_constants() {
        if (!defined('INTERSOCCER_PLAYER_PATH')) {
            define('INTERSOCCER_PLAYER_PATH', PLAYER_MANAGEMENT_PATH);
        }
        if (!defined('INTERSOCCER_PLAYER_URL')) {
            define('INTERSOCCER_PLAYER_URL', PLAYER_MANAGEMENT_URL);
        }
        if (!defined('INTERSOCCER_PLAYER_VERSION')) {
            define('INTERSOCCER_PLAYER_VERSION', PLAYER_MANAGEMENT_VERSION);
        }
        if (!defined('INTERSOCCER_PLAYER_TEXT_DOMAIN')) {
            define('INTERSOCCER_PLAYER_TEXT_DOMAIN', 'player-management');
        }
    }

    /**
     * Load classes required by Elementor widgets.
     */
    private static function load_dependencies() {
        $deps = array(
            'includes/class-logger.php',
            'includes/class-validator.php',
            'includes/class-player-management-database.php',
            'includes/class-elementor-widget-base.php',
        );

        foreach ($deps as $file) {
            $path = PLAYER_MANAGEMENT_PATH . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
