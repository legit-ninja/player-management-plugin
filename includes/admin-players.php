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

        // Export handler (admin-post.php)
        add_action('admin_post_intersoccer_players_export', [$this, 'handle_players_export']);
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

    /**
     * Stream an Excel-friendly CSV of all players (optionally filtered by search).
     */
    public function handle_players_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'player-management'));
        }

        check_admin_referer('intersoccer_players_export');

        if (!function_exists('intersoccer_sanitize_csv_cell')) {
            /**
             * Prevent CSV formula injection by prefixing cells that start with
             * formula-triggering characters (=, +, -, @) with a tab character.
             */
            function intersoccer_sanitize_csv_cell(string $value): string {
                if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                    return "\t" . $value;
                }
                return $value;
            }
        }

        $search_term = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=intersoccer_all_players_' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            wp_die(__('Unable to open output stream.', 'player-management'));
        }

        fputcsv($out, [
            'User ID',
            'User Email',
            'Canton',
            'City',
            'First Name',
            'Last Name',
            'DOB',
            'Gender',
            'AVS Number',
            'Age',
            'Medical Conditions',
            'Creation Date',
        ]);

        $chunk_size = 200;
        $offset = 0;

        while (true) {
            $users = get_users([
                'role__in' => ['customer', 'subscriber'],
                'number' => $chunk_size,
                'offset' => $offset,
                'fields' => ['ID', 'user_email'],
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            if (empty($users)) {
                break;
            }

            foreach ($users as $user) {
                $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
                if (!is_array($players) || empty($players)) {
                    continue;
                }

                $canton = get_user_meta($user->ID, 'billing_state', true) ?: '';
                $city = get_user_meta($user->ID, 'billing_city', true) ?: '';

                foreach ($players as $player) {
                    if (!is_array($player)) {
                        continue;
                    }

                    $first = (string)($player['first_name'] ?? '');
                    $last = (string)($player['last_name'] ?? '');
                    $dob = (string)($player['dob'] ?? '');
                    $gender = (string)($player['gender'] ?? '');
                    $avs = (string)($player['avs_number'] ?? '');
                    $medical = (string)($player['medical_conditions'] ?? '');

                    if ($search_term !== '') {
                        $full_name = trim($first . ' ' . $last);
                        $matches = (
                            stripos($full_name, $search_term) !== false ||
                            stripos((string)($user->user_email ?? ''), $search_term) !== false ||
                            stripos($avs, $search_term) !== false
                        );
                        if (!$matches) {
                            continue;
                        }
                    }

                    $age = '';
                    if ($dob !== '') {
                        try {
                            $dob_dt = new DateTime($dob);
                            $now_dt = new DateTime(current_time('mysql'));
                            $age = (string)$dob_dt->diff($now_dt)->y;
                        } catch (Throwable $e) {
                            $age = '';
                        }
                    }

                    $creation_date = '';
                    $ts = $player['creation_timestamp'] ?? '';
                    if (is_numeric($ts) && (int)$ts > 0) {
                        $creation_date = gmdate('Y-m-d', (int)$ts);
                    }

                    fputcsv($out, [
                        intersoccer_sanitize_csv_cell((string)$user->ID),
                        intersoccer_sanitize_csv_cell((string)($user->user_email ?? '')),
                        intersoccer_sanitize_csv_cell((string)$canton),
                        intersoccer_sanitize_csv_cell((string)$city),
                        intersoccer_sanitize_csv_cell($first),
                        intersoccer_sanitize_csv_cell($last),
                        intersoccer_sanitize_csv_cell($dob),
                        intersoccer_sanitize_csv_cell($gender),
                        intersoccer_sanitize_csv_cell($avs),
                        intersoccer_sanitize_csv_cell((string)$age),
                        intersoccer_sanitize_csv_cell($medical),
                        intersoccer_sanitize_csv_cell($creation_date),
                    ]);
                }
            }

            $offset += $chunk_size;
            if (function_exists('flush')) {
                flush();
            }
        }

        fclose($out);
        exit;
    }
}

// Initialize the admin interface
if (is_admin()) {
    new Player_Management_Admin();
}
