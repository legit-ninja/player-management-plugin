<?php
/**
 * File: admin-players.php (MAIN FILE)
 * Description: Main admin interface for player management
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the separate class files
require_once plugin_dir_path(__FILE__) . 'class-player-overview.php';
require_once plugin_dir_path(__FILE__) . 'class-player-list.php';
require_once plugin_dir_path(__FILE__) . 'class-player-utils.php';

class Player_Management_Admin {
    private $overview;
    private $player_list;
    private $utils;
    
    public function __construct() {
        // Initialize component classes
        $this->utils = new Player_Management_Utils();
        $this->overview = new Player_Management_Overview($this->utils);
        $this->player_list = new Player_Management_List($this->utils);
        
        // Hook into WordPress
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'remove_duplicate_menu'], 999);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
        add_action('admin_init', [$this, 'handle_player_actions']);
        
        // Add AJAX handlers
        add_action('wp_ajax_load_more_players', [$this->player_list, 'ajax_load_more_players']);
    }

    public function add_admin_menu() {
        $hook = add_menu_page(
            __('Players', 'player-management'),
            __('Players', 'player-management'),
            'manage_options',
            'intersoccer-players',
            [$this, 'render_overview_page'],
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'intersoccer-players',
            __('Overview', 'player-management'),
            __('Overview', 'player-management'),
            'manage_options',
            'intersoccer-players',
            [$this, 'render_overview_page']
        );
        
        add_submenu_page(
            'intersoccer-players',
            __('All Players', 'player-management'),
            __('All Players', 'player-management'),
            'manage_options',
            'intersoccer-players-all',
            [$this, 'render_all_players_page']
        );
        
        add_submenu_page(
            'intersoccer-players',
            __('Advanced', 'player-management'),
            __('Advanced', 'player-management'),
            'manage_options',
            'intersoccer-players-advanced',
            'player_management_render_advanced_tab'
        );

        add_action("load-$hook", [$this, 'screen_option']);
    }

    public function remove_duplicate_menu() {
        global $menu;
        $found = 0;
        foreach ($menu as $index => $item) {
            if ($item[2] === 'intersoccer-players') {
                $found++;
                if ($found > 1) {
                    unset($menu[$index]);
                }
            }
        }
    }

    public function screen_option() {
        $option = 'per_page';
        $args = [
            'label' => __('Players per page', 'player-management'),
            'default' => 20,
            'option' => 'players_per_page'
        ];
        add_screen_option($option, $args);
    }

    public function set_screen_option($status, $option, $value) {
        return $value;
    }

    public function handle_player_actions() {
        // Handle any global player actions here
    }

    public function render_overview_page() {
        $this->overview->render();
    }

    public function render_all_players_page() {
        $this->player_list->render();
    }
}

// Initialize the admin interface
if (is_admin()) {
    new Player_Management_Admin();
}

// Include the order item functions
require_once plugin_dir_path(__FILE__) . 'order-item-functions.php';