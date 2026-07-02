<?php
/**
 * InterSoccer Player Management Elementor Manager
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Manager class for handling widget registration
 */
class InterSoccer_Player_Elementor_Manager {

    /**
     * Widget namespace
     */
    const WIDGET_NAMESPACE = 'intersoccer-player';

    /**
     * Widget class => file path relative to plugin root
     */
    private $widgets = array(
        'InterSoccer_Player_Elementor_Player_List' => 'includes/class-player-list-elementor-widget.php',
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'register_category'));
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_widget_styles'));
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager instance.
     */
    public function register_widgets($widgets_manager) {
        if (!$widgets_manager) {
            return;
        }

        foreach ($this->widgets as $widget_class => $widget_file) {
            $path = INTERSOCCER_PLAYER_PATH . $widget_file;
            if (!file_exists($path)) {
                continue;
            }

            require_once $path;

            if (!class_exists($widget_class)) {
                continue;
            }

            $instance = new $widget_class();
            if (method_exists($widgets_manager, 'register')) {
                $widgets_manager->register($instance);
            } elseif (method_exists($widgets_manager, 'register_widget_type')) {
                $widgets_manager->register_widget_type($instance);
            }
        }
    }

    /**
     * Register widget category
     *
     * @param \Elementor\Elements_Manager $elements_manager Elements manager instance.
     */
    public function register_category($elements_manager) {
        $elements_manager->add_category(
            'intersoccer-player',
            array(
                'title' => __('InterSoccer Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'icon' => 'fa fa-users',
            )
        );
    }

    /**
     * Enqueue widget styles when present on the page
     */
    public function enqueue_widget_styles() {
        $css_path = INTERSOCCER_PLAYER_PATH . 'css/player-management-widget.css';
        if (!file_exists($css_path)) {
            return;
        }

        wp_enqueue_style(
            'intersoccer-player-elementor-widgets',
            INTERSOCCER_PLAYER_URL . 'css/player-management-widget.css',
            array(),
            INTERSOCCER_PLAYER_VERSION
        );
    }

    /**
     * Get widget list
     *
     * @return array
     */
    public function get_widgets() {
        return $this->widgets;
    }
}
