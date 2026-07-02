<?php
/**
 * File: includes/class-elementor-integration.php
 * InterSoccer Player Management Elementor Integration
 * 
 * Handles registration of Elementor widgets for the Player Management plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Player_Management_Elementor_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Log constructor call
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: Elementor Integration class instantiated');
        }
        
        // Register hooks only if Elementor is available
        if (class_exists('Elementor\Widget_Base')) {
            add_action('elementor/widgets/register', [$this, 'register_widgets'], 10);
            add_action('elementor/elements/categories_registered', [$this, 'add_widget_categories'], 10);
            
            // Register and enqueue styles for Elementor editor
            add_action('elementor/editor/before_enqueue_scripts', [$this, 'enqueue_editor_styles']);
            add_action('wp_enqueue_scripts', [$this, 'register_styles']);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer Player Management: Elementor Widgets have been successfully loaded');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer Player Management: Elementor\Widget_Base not found, skipping widget hooks');
            }
        }
    }
    
    /**
     * Register widget styles
     */
    public function register_styles() {
        wp_register_style(
            'intersoccer-player-widget',
            PLAYER_MANAGEMENT_URL . 'css/player-management-widget.css',
            [],
            PLAYER_MANAGEMENT_VERSION
        );
    }
    
    /**
     * Enqueue styles in Elementor editor
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'intersoccer-player-widget',
            PLAYER_MANAGEMENT_URL . 'css/player-management-widget.css',
            [],
            PLAYER_MANAGEMENT_VERSION
        );
    }
    
    /**
     * Add widget category for InterSoccer widgets
     */
    public function add_widget_categories($elements_manager) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: Adding widget category');
        }
        
        $elements_manager->add_category(
            'intersoccer-player',
            [
                'title' => __('InterSoccer Players', 'player-management'),
                'icon' => 'eicon-user-circle-o',
            ]
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: Widget category added');
        }
    }
    
    /**
     * Register all Player Management widgets
     */
    public function register_widgets($widgets_manager) {
        // Ensure widgets manager is valid
        if (!$widgets_manager || !method_exists($widgets_manager, 'register')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer Player Management: Invalid widgets manager');
            }
            return;
        }

        // Register Player Management Link widget
        try {
            $widget_file = PLAYER_MANAGEMENT_PATH . 'elementor/widgets/player-management-link-widget.php';
            if (!file_exists($widget_file)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('InterSoccer Player Management: Widget file not found: ' . $widget_file);
                }
                return;
            }
            
            require_once $widget_file;
            
            if (!class_exists('InterSoccer_Player_Management_Link_Widget')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('InterSoccer Player Management: Widget class not found after requiring file');
                }
                return;
            }
            
            $widgets_manager->register(new InterSoccer_Player_Management_Link_Widget());
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer Player Management: Registered widget: intersoccer_player_management_link');
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer Player Management: Widget registration failed: ' . $e->getMessage());
                error_log('InterSoccer Player Management: Stack trace: ' . $e->getTraceAsString());
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: Widget registration completed');
        }
    }
}

/**
 * Helper function to check if a user has registered players
 * 
 * @param int $user_id User ID to check
 * @return bool True if user has at least one valid player, false otherwise
 */
function intersoccer_user_has_players($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $players = get_user_meta($user_id, 'intersoccer_players', true);
    
    if (!is_array($players) || empty($players)) {
        return false;
    }
    
    // Check if there's at least one valid player with required fields
    foreach ($players as $player) {
        if (is_array($player) && 
            !empty($player['first_name']) && 
            !empty($player['last_name'])) {
            return true;
        }
    }
    
    return false;
}


