<?php
/**
 * Elementor Player Management Link widget registration (load-order safe).
 *
 * @package InterSoccer_Player_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Player Management Link widget with Elementor.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
 */
function intersoccer_pm_register_player_link_elementor_widget($widgets_manager) {
    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    if (!$widgets_manager || !is_object($widgets_manager) || !method_exists($widgets_manager, 'register')) {
        return;
    }

    $widget_file = PLAYER_MANAGEMENT_PATH . 'elementor/widgets/player-management-link-widget.php';
    if (!is_readable($widget_file)) {
        return;
    }

    require_once $widget_file;

    if (!class_exists('InterSoccer_Player_Management_Link_Widget', false)) {
        return;
    }

    $widgets_manager->register(new InterSoccer_Player_Management_Link_Widget());
}

/**
 * Bootstrap categories, styles, and additional Elementor widgets.
 */
function intersoccer_pm_bootstrap_elementor_integration() {
    static $booted = false;
    if ($booted) {
        return;
    }

    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    $booted = true;

    $bootstrap = PLAYER_MANAGEMENT_PATH . 'includes/class-elementor-bootstrap.php';
    if (!is_readable($bootstrap)) {
        return;
    }

    require_once $bootstrap;

    if (class_exists('InterSoccer_Player_Elementor_Bootstrap', false)) {
        InterSoccer_Player_Elementor_Bootstrap::init();
    }
}

/**
 * If Elementor registered widgets before this plugin loaded, register immediately.
 */
function intersoccer_pm_register_player_link_elementor_widget_if_late() {
    if (!did_action('elementor/widgets/register')) {
        return;
    }

    if (!class_exists('\Elementor\Plugin')) {
        return;
    }

    $plugin = \Elementor\Plugin::instance();
    if (!isset($plugin->widgets_manager)) {
        return;
    }

    intersoccer_pm_register_player_link_elementor_widget($plugin->widgets_manager);
}

/**
 * Wire Elementor hooks after plugins are loaded (handles missed elementor/loaded).
 */
function intersoccer_pm_setup_elementor_player_link_widget() {
    if (class_exists('\Elementor\Widget_Base') || did_action('elementor/loaded')) {
        intersoccer_pm_bootstrap_elementor_integration();
        intersoccer_pm_register_player_link_elementor_widget_if_late();
    } elseif (!has_action('elementor/loaded', 'intersoccer_pm_bootstrap_elementor_integration')) {
        add_action('elementor/loaded', 'intersoccer_pm_bootstrap_elementor_integration', 5);
    }
}
