<?php
/**
 * InterSoccer Player Management Autoloader
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class for InterSoccer Player Management
 */
class InterSoccer_Player_Autoloader {

    /**
     * Namespace prefix
     */
    private $namespace_prefix = 'InterSoccer_Player_';

    /**
     * Base directory for the namespace prefix
     */
    private $base_dir;

    /**
     * Class map for performance
     */
    private $class_map = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->base_dir = INTERSOCCER_PLAYER_PATH . 'includes/';
        $this->register();
        $this->load_class_map();
    }

    /**
     * Register the autoloader
     */
    public function register() {
        spl_autoload_register(array($this, 'load_class'));
    }

    /**
     * Load class map for better performance
     */
    private function load_class_map() {
        $this->class_map = array(
            'InterSoccer_Player_Admin' => 'admin/class-admin.php',
            'InterSoccer_Player_Admin_Dashboard' => 'admin/class-dashboard.php',
            'InterSoccer_Player_Admin_Settings' => 'admin/class-settings.php',
            'InterSoccer_Player_Admin_Export' => 'admin/class-export.php',
            'InterSoccer_Player_Frontend' => 'frontend/class-frontend.php',
            'InterSoccer_Player_Frontend_Account' => 'frontend/class-account.php',
            'InterSoccer_Player_Ajax' => 'ajax/class-ajax.php',
            'InterSoccer_Player_Ajax_Handlers' => 'ajax/class-handlers.php',
            'InterSoccer_Player_Database' => 'class-database.php',
            'InterSoccer_Player_Logger' => 'class-logger.php',
            'InterSoccer_Player_Validator' => 'class-validator.php',
            'InterSoccer_Player_Loader' => 'class-loader.php',
            'InterSoccer_Player_Player' => 'models/class-player.php',
            'InterSoccer_Player_Event' => 'models/class-event.php',
            'InterSoccer_Player_Roster' => 'models/class-roster.php',
            'InterSoccer_Player_WooCommerce_Integration' => 'integrations/class-woocommerce.php',
            'InterSoccer_Player_Elementor_Manager' => 'elementor/class-elementor-manager.php',
            'InterSoccer_Player_Elementor_Widget_Base' => 'elementor/widgets/class-widget-base.php',
            'InterSoccer_Player_Elementor_Player_List' => 'elementor/widgets/class-player-list.php',
            'InterSoccer_Player_Elementor_Player_Stats' => 'elementor/widgets/class-player-stats.php',
            'InterSoccer_Player_Elementor_Event_Roster' => 'elementor/widgets/class-event-roster.php',
            'InterSoccer_Player_Utils' => 'class-utils.php',
            'InterSoccer_Player_Cache' => 'class-cache.php',
            'InterSoccer_Player_API' => 'api/class-api.php',
            'InterSoccer_Player_Shortcodes' => 'class-shortcodes.php',
        );
    }

    /**
     * Load class file
     *
     * @param string $class_name The class name
     */
    public function load_class($class_name) {
        // Check if it's our namespace
        if (0 !== strpos($class_name, $this->namespace_prefix)) {
            return;
        }

        // Check class map first for performance
        if (isset($this->class_map[$class_name])) {
            $file = $this->base_dir . $this->class_map[$class_name];
            $this->require_file($file);
            return;
        }

        // Convert class name to file path
        $relative_class = substr($class_name, strlen($this->namespace_prefix));
        $file = $this->base_dir . $this->get_file_path($relative_class);
        
        $this->require_file($file);
    }

    /**
     * Convert class name to file path
     *
     * @param string $class_name
     * @return string
     */
    private function get_file_path($class_name) {
        // Convert CamelCase to snake_case and add class- prefix
        $file_name = 'class-' . strtolower(preg_replace('/([A-Z])/', '-$1', $class_name));
        $file_name = ltrim($file_name, '-');
        
        // Determine subdirectory based on class name
        if (strpos($class_name, 'Admin') === 0) {
            return 'admin/' . $file_name . '.php';
        } elseif (strpos($class_name, 'Frontend') === 0) {
            return 'frontend/' . $file_name . '.php';
        } elseif (strpos($class_name, 'Ajax') === 0) {
            return 'ajax/' . $file_name . '.php';
        } elseif (strpos($class_name, 'Elementor') === 0) {
            if (strpos($class_name, 'Widget') !== false) {
                return 'elementor/widgets/' . $file_name . '.php';
            }
            return 'elementor/' . $file_name . '.php';
        } elseif (in_array($class_name, array('Player', 'Event', 'Roster'))) {
            return 'models/' . $file_name . '.php';
        } elseif (strpos($class_name, 'Integration') !== false) {
            return 'integrations/' . $file_name . '.php';
        } elseif (strpos($class_name, 'API') === 0) {
            return 'api/' . $file_name . '.php';
        }
        
        return $file_name . '.php';
    }

    /**
     * Require file if it exists
     *
     * @param string $file
     */
    private function require_file($file) {
        if (file_exists($file)) {
            require_once $file;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Player Management: Loaded class file: {$file}");
            }
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer Player Management: Class file not found: {$file}");
        }
    }

    /**
     * Add class to map
     *
     * @param string $class_name
     * @param string $file_path
     */
    public function add_class($class_name, $file_path) {
        $this->class_map[$class_name] = $file_path;
    }

    /**
     * Get all registered classes
     *
     * @return array
     */
    public function get_registered_classes() {
        return array_keys($this->class_map);
    }
}