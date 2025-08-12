<?php
/**
 * File: admin-players.php (OPTIMIZED VERSION)
 * Description: Memory-optimized version that fixes the out-of-memory error
 * Author: Jeremy Lee (Optimized by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Player_Management_Admin {
    private $all_players_data = [];
    
    // Add memory tracking
    private function log_memory($checkpoint) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $memory_usage = memory_get_usage(true);
            $memory_peak = memory_get_peak_usage(true);
            error_log("INTERSOCCER_MEMORY: $checkpoint - Current: " . $this->format_bytes($memory_usage) . ", Peak: " . $this->format_bytes($memory_peak));
        }
    }
    
    private function format_bytes($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    private function count_matching_players($search_term) {
        $all_users = get_users([
            'role__in' => ['customer', 'subscriber'],
            'fields' => ['ID'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ],
            'number' => -1 // Get all for counting
        ]);
        
        $matching_count = 0;
        foreach ($all_users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            foreach ($players as $player) {
                $full_name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
                $user_email = get_user_by('ID', $user->ID)->user_email ?? '';
                $avs_number = $player['avs_number'] ?? '';
                
                $search_matches = (
                    stripos($full_name, $search_term) !== false ||
                    stripos($user_email, $search_term) !== false ||
                    stripos($avs_number, $search_term) !== false
                );
                
                if ($search_matches) {
                    $matching_count++;
                }
            }
        }
        
        return $matching_count;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'remove_duplicate_menu'], 999);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
        add_action('admin_init', [$this, 'handle_player_actions']);
        
        // Add AJAX handlers
        add_action('wp_ajax_load_more_players', [$this, 'ajax_load_more_players']);
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
        // Placeholder for future actions if needed
    }

    public function render_overview_page() {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);

        global $wpdb;
        $players = $this->all_players_data;
        $canton_data = [];
        $gender_data = ['male' => 0, 'female' => 0, 'other' => 0];
        $top_cantons = [];
        $total_players = 0;
        $assigned_count = 0;
        $unassigned_count = 0;
        $users_without_players = 0;

        // Collect data
        $all_users = get_users(['role' => 'customer']);
        $roster_players = $wpdb->get_results(
            "SELECT DISTINCT first_name, last_name FROM {$wpdb->prefix}intersoccer_rosters",
            ARRAY_A
        );
        $assigned_players = [];
        foreach ($roster_players as $roster_player) {
            $assigned_players[$roster_player['first_name'] . '|' . $roster_player['last_name']] = true;
        }

        foreach ($all_users as $user) {
            $user_players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            if (empty($user_players)) {
                $users_without_players++;
                continue;
            }
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: 'Unknown';
            foreach ($user_players as $player) {
                $total_players++;
                $canton_data[$billing_state] = isset($canton_data[$billing_state]) ? $canton_data[$billing_state] + 1 : 1;
                $gender_data[strtolower($player['gender'] ?? 'other')]++;

                $player_key = $player['first_name'] . '|' . $player['last_name'];
                if (isset($assigned_players[$player_key])) {
                    $assigned_count++;
                }
            }
        }
        $unassigned_count = $total_players - $assigned_count;

        // Sort and get top 5 cantons
        arsort($canton_data);
        $top_cantons = array_slice($canton_data, 0, 5, true);

        $inline_css = '
            .wrap { background: #1e2529; color: #fff; padding: 10px; }
            .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .dashboard-card { background: #2a2f33; padding: 15px; border-radius: 5px; text-align: center; }
            .dashboard-card h3 { margin: 0 0 10px; font-size: 14px; color: #ddd; }
            .dashboard-card p { margin: 0; font-size: 20px; font-weight: bold; color: #fff; }
            .chart-container { width: 100%; height: 200px; }
            @media (max-width: 600px) {
                .dashboard-grid { grid-template-columns: 1fr; }
                .chart-container { height: 150px; }
            }
        ';
        wp_add_inline_style('intersoccer-player-management', $inline_css);
        ?>
        <div class="wrap">
            <h1><?php _e('Players Overview Dashboard', 'player-management'); ?></h1>
            <div class="dashboard-grid">
                <!-- Total Players -->
                <div class="dashboard-card">
                    <h3><?php _e('Total Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_players); ?></p>
                </div>
                <!-- Users Without Players -->
                <div class="dashboard-card">
                    <h3><?php _e('Users Without Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($users_without_players); ?></p>
                </div>
                <!-- Assigned vs Unassigned -->
                <div class="dashboard-card">
                    <h3><?php _e('Assigned vs Unassigned', 'player-management'); ?></h3>
                    <div class="chart-container"><canvas id="assignedChart"></canvas></div>
                </div>
                <!-- Gender Breakdown -->
                <div class="dashboard-card">
                    <h3><?php _e('Gender Breakdown', 'player-management'); ?></h3>
                    <div class="chart-container"><canvas id="genderChart"></canvas></div>
                </div>
                <!-- Players by Canton -->
                <div class="dashboard-card">
                    <h3><?php _e('Players by Canton', 'player-management'); ?></h3>
                    <div class="chart-container"><canvas id="cantonChart"></canvas></div>
                </div>
                <!-- Top 5 Cantons -->
                <div class="dashboard-card">
                    <h3><?php _e('Top 5 Cantons', 'player-management'); ?></h3>
                    <div class="chart-container"><canvas id="topCantonsChart"></canvas></div>
                </div>
            </div>
            <<script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    // Assigned vs Unassigned Pie Chart
                    new Chart(document.getElementById('assignedChart'), {
                        type: 'pie',
                        data: {
                            labels: ['Assigned', 'Unassigned'],
                            datasets: [{
                                data: [<?php echo esc_js($assigned_count); ?>, <?php echo esc_js($unassigned_count); ?>],
                                backgroundColor: ['#36A2EB', '#FF6384']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                    });

                    // Gender Breakdown Pie Chart
                    new Chart(document.getElementById('genderChart'), {
                        type: 'pie',
                        data: {
                            labels: ['Male', 'Female', 'Other'],
                            datasets: [{
                                data: [<?php echo esc_js($gender_data['male']); ?>, <?php echo esc_js($gender_data['female']); ?>, <?php echo esc_js($gender_data['other']); ?>],
                                backgroundColor: ['#4BC0C0', '#FFCD56', '#C9CBCF']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                    });

                    // Players by Canton Bar Chart
                    new Chart(document.getElementById('cantonChart'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_keys($canton_data)); ?>,
                            datasets: [{
                                label: 'Players',
                                data: <?php echo json_encode(array_values($canton_data)); ?>,
                                backgroundColor: '#36A2EB'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
                    });

                    // Top 5 Cantons Bar Chart
                    new Chart(document.getElementById('topCantonsChart'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_keys($top_cantons)); ?>,
                            datasets: [{
                                label: 'Registrations',
                                data: <?php echo json_encode(array_values($top_cantons)); ?>,
                                backgroundColor: '#FF6384'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
                    });
                });
                </script>
        
        <?php
        $this->log_memory('render_complete');
    }

    /**
     * Render pagination links
     */
    private function render_pagination_links($current_page, $total_pages, $search_term = '') {
        if ($total_pages <= 1) {
            return; // Don't show pagination if only one page
        }

        $base_url = admin_url('admin.php?page=intersoccer-players-all');
        if (!empty($search_term)) {
            $base_url .= '&search=' . urlencode($search_term);
        }
        
        echo '<div class="pagination-links">';
        
        // Previous page
        if ($current_page > 1) {
            echo '<a href="' . $base_url . '&paged=' . ($current_page - 1) . '">&laquo; ' . __('Previous', 'player-management') . '</a>';
        }
        
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            echo '<a href="' . $base_url . '&paged=1">1</a>';
            if ($start_page > 2) {
                echo '<span>...</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="' . $base_url . '&paged=' . $i . '">' . $i . '</a>';
            }
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span>...</span>';
            }
            echo '<a href="' . $base_url . '&paged=' . $total_pages . '">' . $total_pages . '</a>';
        }
        
        // Next page
        if ($current_page < $total_pages) {
            echo '<a href="' . $base_url . '&paged=' . ($current_page + 1) . '">' . __('Next', 'player-management') . ' &raquo;</a>';
        }
        
        echo '</div>';
    }

    /**
     * Add responsive styles for better mobile experience
     */
    private function add_responsive_styles() {
        ?>
        <style>
            .pagination-info {
                background: #f1f1f1;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }
            .pagination-links {
                text-align: center;
                margin: 20px 0;
            }
            .pagination-links a, .pagination-links span {
                display: inline-block;
                padding: 8px 12px;
                margin: 0 4px;
                text-decoration: none;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .pagination-links .current {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            .pagination-links a:hover {
                background: #f1f1f1;
                border-color: #999;
            }
            .search-form {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .search-form input[type="text"] {
                width: 300px;
                margin-right: 10px;
            }
            .quick-stats { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 15px; 
            }
            .quick-stats div { 
                text-align: center; 
                flex: 1; 
                padding: 10px; 
                background: #f9f9f9; 
                border: 1px solid #ddd; 
                border-radius: 5px; 
                margin: 0 5px;
            }
            .quick-stats div h3 { 
                margin: 0 0 5px; 
                font-size: 14px; 
            }
            .quick-stats div p { 
                margin: 0; 
                font-size: 16px; 
                font-weight: bold; 
            }
            .loading {
                display: none;
                text-align: center;
                padding: 20px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                margin: 10px 0;
            }
            
            @media (max-width: 768px) {
                .pagination-links a, .pagination-links span {
                    padding: 6px 8px;
                    margin: 0 2px;
                    font-size: 12px;
                }
                
                .search-form input[type="text"] {
                    width: 100%;
                    margin-bottom: 10px;
                }
                
                .quick-stats {
                    flex-direction: column;
                }
                
                .quick-stats div {
                    margin: 5px 0;
                }
                
                .pagination-info {
                    font-size: 12px;
                    line-height: 1.4;
                }

                .intersoccer-player-management table, 
                .intersoccer-player-management thead, 
                .intersoccer-player-management tbody, 
                .intersoccer-player-management th, 
                .intersoccer-player-management td, 
                .intersoccer-player-management tr {
                    display: block;
                }
                
                .intersoccer-player-management thead tr { 
                    position: absolute; 
                    top: -9999px; 
                    left: -9999px; 
                }
                
                .intersoccer-player-management tr { 
                    margin-bottom: 15px; 
                    border: 1px solid #ddd; 
                    padding: 10px;
                    border-radius: 4px;
                }
                
                .intersoccer-player-management td { 
                    border: none; 
                    position: relative; 
                    padding-left: 50%; 
                    padding-bottom: 8px;
                }
                
                .intersoccer-player-management td:before {
                    content: attr(data-label) ": ";
                    position: absolute;
                    left: 10px;
                    width: 45%;
                    padding-right: 10px;
                    white-space: nowrap;
                    font-weight: bold;
                    color: #666;
                }
            }
            
            /* Loading spinner */
            .loading {
                position: relative;
            }
            
            .loading:after {
                content: '';
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-left: 10px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }

    /**
     * AJAX endpoint for loading more players (for future infinite scroll implementation)
     */
    public function ajax_load_more_players() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'player-management'));
        }
        
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Use the same logic as render_all_players_page but return JSON
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $user_query_args = [
            'role__in' => ['customer', 'subscriber'],
            'number' => $per_page,
            'offset' => $offset,
            'fields' => ['ID', 'user_email'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        if (!empty($search)) {
            $user_query_args['search'] = '*' . $search . '*';
            $user_query_args['search_columns'] = ['user_email', 'user_login'];
        }
        
        $users = get_users($user_query_args);
        $players_html = '';
        $player_count = 0;
        
        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            if (empty($players)) continue;
            
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: 'Unknown';
            $billing_city = get_user_meta($user->ID, 'billing_city', true) ?: '';
            
            foreach ($players as $index => $player) {
                if (!empty($search)) {
                    $full_name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
                    if (stripos($full_name, $search) === false) {
                        continue;
                    }
                }
                
                $player_count++;
                $players_html .= '<tr>';
                $players_html .= '<td><a href="/wp-admin/user-edit.php?user_id=' . esc_attr($user->ID) . '">' . esc_html($user->ID) . '</a></td>';
                $players_html .= '<td>' . esc_html($billing_state) . '</td>';
                $players_html .= '<td>' . esc_html($billing_city) . '</td>';
                $players_html .= '<td>' . esc_html($player['first_name'] ?? '') . '</td>';
                $players_html .= '<td>' . esc_html($player['last_name'] ?? '') . '</td>';
                $players_html .= '<td>' . esc_html($player['dob'] ?? '') . '</td>';
                $players_html .= '<td>' . esc_html($player['gender'] ?? '') . '</td>';
                $players_html .= '<td>' . esc_html($player['avs_number'] ?? '') . '</td>';
                $players_html .= '<td>0</td>'; // Event count would need additional processing
                $players_html .= '<td>' . esc_html(substr($player['medical_conditions'] ?? '', 0, 20)) . '</td>';
                $players_html .= '<td>' . esc_html($player['creation_timestamp'] ? date('Y-m-d', $player['creation_timestamp']) : 'N/A') . '</td>';
                $players_html .= '<td></td>'; // Past events would need additional processing
                $players_html .= '</tr>';
            }
        }
        
        wp_send_json_success([
            'html' => $players_html,
            'has_more' => count($users) === $per_page,
            'player_count' => $player_count
        ]);
    }

    /**
     * OPTIMIZED: Fixed the memory issue in render_all_players_page
     */
    public function render_all_players_page() {

        // Temporarily suppress warnings for production
        $old_error_reporting = error_reporting();
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            error_reporting(E_ERROR | E_PARSE); // Only show fatal errors
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'intersoccer-player-management'));
        }

        $this->log_memory('render_all_players_page_start');

        // Get pagination parameters
        $current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = 50; // Show 50 players per page
        $offset = ($current_page - 1) * $per_page;
        
        // Get search parameter
        $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer: Rendering players page $current_page, offset $offset, search: '$search_term'");
        }

        // OPTIMIZATION 1: Get total counts first (lightweight queries)
        $total_users = count(get_users([
            'role__in' => ['customer', 'subscriber'],
            'fields' => 'ID'
        ]));
        
        $users_with_players = count(get_users([
            'role__in' => ['customer', 'subscriber'],
            'fields' => 'ID',
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ]));
        
        $users_without_players = $total_users - $users_with_players;

        $this->log_memory('after_user_counts');

        // OPTIMIZATION 2: Load users in paginated batches
        $user_query_args = [
            'role__in' => ['customer', 'subscriber'],
            'number' => $per_page,
            'offset' => $offset,
            'fields' => ['ID', 'user_email'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        

        $users = get_users($user_query_args);
        
        $this->log_memory('after_paginated_users_loaded');

        // Get total player count for this page
       if (empty($search_term)) {
            // No search - use normal pagination
            $total_players_query = new WP_User_Query([
                'role__in' => ['customer', 'subscriber'],
                'fields' => 'ID',
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            $total_users_with_players = $total_players_query->get_total();
            $total_pages = ceil($total_users_with_players / $per_page);
        } else {
            // Search mode - we need to count matching players across all users
            $search_total = $this->count_matching_players($search_term);
            $total_pages = ceil($search_total / $per_page);
            $total_users_with_players = $search_total;
        }
        

        // OPTIMIZATION 3: Pre-load orders for this batch only
        $user_ids = wp_list_pluck($users, 'ID');
        $order_player_mapping = [];
        
        if (!empty($user_ids)) {
            $batch_orders = wc_get_orders([
                'customer' => $user_ids,
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => 500, // Reasonable limit for this batch
            ]);
            
            foreach ($batch_orders as $order) {
                $user_id = $order->get_user_id();
                if (!$user_id) continue;
                
                $order_players = $order->get_meta('intersoccer_players', true);
                if ($order_players) {
                    if (!isset($order_player_mapping[$user_id])) {
                        $order_player_mapping[$user_id] = [];
                    }
                    $order_player_mapping[$user_id][] = [
                        'order' => $order,
                        'players' => (array)$order_players
                    ];
                }
            }
        }

        if (!empty($search_term)) {
            // Load more users when searching since we'll filter players
            $search_user_args = [
                'role__in' => ['customer', 'subscriber'],
                'number' => $per_page * 3, // Load 3x more to account for filtering
                'offset' => max(0, ($current_page - 1) * $per_page * 3),
                'fields' => ['ID', 'user_email'],
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS'
                    ]
                ]
            ];
            $users = get_users($search_user_args);
        }
        
        $this->log_memory('after_batch_orders_loaded');

        // Process players for this page
        $all_players = [];
        $page_total_players = 0;
        $page_male_count = 0;
        $page_female_count = 0;
        $today = current_time('mysql');

        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            if (empty($players)) continue;
            
            // Get user billing info once
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: 'Unknown';
            $billing_city = get_user_meta($user->ID, 'billing_city', true) ?: '';
            
            foreach ($players as $index => $player) {
                // Apply search filter to player names if search term exists
                if (!empty($search_term)) {
                    $full_name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
                    $user_email = $user->user_email ?? '';
                    $search_matches = (
                        stripos($full_name, $search_term) !== false ||
                        stripos($user_email, $search_term) !== false ||
                        stripos($player['avs_number'] ?? '', $search_term) !== false
                    );
                    
                    if (!$search_matches) {
                        continue; // Skip this player if no match
                    }
                }
                
                // Only process up to $per_page players for pagination
                if ($page_total_players >= $per_page) {
                    break 2; // Break out of both loops
                }
                
                $page_total_players++;
                $player['user_id'] = $user->ID;
                $player['user_email'] = $user->user_email;
                $player['index'] = $index;
                $player['canton'] = $billing_state;
                $player['city'] = $billing_city;
                $player['event_count'] = 0;
                $player['past_events'] = [];
                $player['event_types'] = [];

                // Calculate age
                $dob = $player['dob'] ?? '';
                if ($dob) {
                    try {
                        $age = date_diff(date_create($dob), date_create($today))->y;
                        if ($age >= 3 && $age <= 14) {
                            $player['event_age_groups'] = ["Age $age"];
                        } else {
                            $player['event_age_groups'] = [];
                        }
                    } catch (Exception $e) {
                        $player['event_age_groups'] = [];
                    }
                }

                // Count gender
                $gender = strtolower($player['gender'] ?? 'other');
                if ($gender === 'male') $page_male_count++;
                elseif ($gender === 'female') $page_female_count++;

                // OPTIMIZATION 5: Use pre-built order mapping instead of querying per player
                $player_name = isset($player['first_name']) ? $player['first_name'] . ' ' . $player['last_name'] : '';
                
                if (isset($order_player_mapping[$user->ID])) {
                    foreach ($order_player_mapping[$user->ID] as $order_data) {
                        if (in_array($player_name, $order_data['players'])) {
                            foreach ($order_data['order']->get_items() as $item) {
                                $product = $item->get_product();
                                if (!$product) continue;
                                
                                $end_date = $product->get_attribute('pa_end-date');
                                $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
                                $event_type = '';
                                
                                if (in_array('Camps', $categories)) {
                                    $event_type = 'Camps';
                                } elseif (in_array('Courses', $categories)) {
                                    $event_type = 'Courses';
                                } elseif (in_array('Birthdays', $categories)) {
                                    $event_type = 'Birthdays';
                                }
                                
                                $player['event_count']++;
                                if ($end_date && $this->is_date_past($end_date, $today)) {
                                    $player['past_events'][] = $item->get_name();
                                }
                                if ($event_type && !in_array($event_type, $player['event_types'])) {
                                    $player['event_types'][] = $event_type;
                                }
                            }
                        }
                    }
                }

                $all_players[] = $player;
            }
            
            // Periodic memory cleanup
            if ($page_total_players % 100 === 0) {
                $this->log_memory("processed_{$page_total_players}_players");
            }
        }

        // Clean up mapping from memory
        unset($order_player_mapping);
        $this->log_memory('data_processing_complete');

        // Enqueue scripts
        wp_enqueue_script('jquery');
        
        // Add responsive styles
        $this->add_responsive_styles();
        ?>
        
        <div class="wrap">
            <h1><?php _e('All Players', 'player-management'); ?></h1>

            <!-- Search Form -->
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="intersoccer-players-all">
                <div class="search-container">
                    <input type="text" name="search" value="<?php echo esc_attr($search_term); ?>" 
                        placeholder="<?php _e('Search by name, email, or AVS number...', 'player-management'); ?>"
                        style="width: 300px;">
                    <input type="submit" class="button button-primary" value="<?php _e('Search', 'player-management'); ?>">
                    <?php if (!empty($search_term)): ?>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-players-all'); ?>" class="button">
                            <?php _e('Clear Search', 'player-management'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Advanced Search Options (collapsible) -->
                <div class="advanced-search" style="margin-top: 10px;">
                    <a href="#" onclick="jQuery('.advanced-search-options').toggle(); return false;" class="button button-secondary">
                        <?php _e('Advanced Search', 'player-management'); ?>
                    </a>
                    <div class="advanced-search-options" style="display: none; margin-top: 10px; background: #f9f9f9; padding: 10px; border-radius: 4px;">
                        <label>
                            <input type="checkbox" name="search_options[]" value="name" checked> 
                            <?php _e('Search Names', 'player-management'); ?>
                        </label>
                        <label style="margin-left: 15px;">
                            <input type="checkbox" name="search_options[]" value="email" checked> 
                            <?php _e('Search Emails', 'player-management'); ?>
                        </label>
                        <label style="margin-left: 15px;">
                            <input type="checkbox" name="search_options[]" value="avs" checked> 
                            <?php _e('Search AVS Numbers', 'player-management'); ?>
                        </label>
                    </div>
                </div>
            </form>

            <style>
            .search-form {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
                border-left: 4px solid #0073aa;
            }
            .search-container {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .advanced-search-options label {
                display: inline-block;
                margin: 5px 0;
            }
            @media (max-width: 600px) {
                .search-container {
                    flex-direction: column;
                    align-items: stretch;
                }
                .search-container input[type="text"] {
                    width: 100% !important;
                    margin-bottom: 10px;
                }
            }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // Real-time search highlighting
                var searchTerm = '<?php echo esc_js($search_term); ?>';
                if (searchTerm) {
                    $('#player-table tbody tr').each(function() {
                        var $row = $(this);
                        var html = $row.html();
                        var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                        html = html.replace(regex, '<mark style="background-color: yellow;">$1</mark>');
                        $row.html(html);
                    });
                }
                
                // Enhanced search with enter key
                $('input[name="search"]').on('keypress', function(e) {
                    if (e.which === 13) {
                        $(this).closest('form').submit();
                    }
                });
            });
            </script>

            <!-- Pagination Info -->
            <div class="pagination-info">
                <strong><?php _e('Showing:', 'player-management'); ?></strong>
                <?php printf(
                    __('Page %d of %d | %d players on this page | %d total users with players | %d users without players', 'player-management'),
                    $current_page,
                    $total_pages,
                    $page_total_players,
                    $total_users_with_players,
                    $users_without_players
                ); ?>
                <?php if (!empty($search_term)): ?>
                    <br><strong><?php _e('Search results for:', 'player-management'); ?></strong> "<?php echo esc_html($search_term); ?>"
                <?php endif; ?>
            </div>

            <!-- Quick Stats for Current Page -->
            <div class="dashboard-section quick-stats">
                <div>
                    <h3><?php _e('Players on Page', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_total_players); ?></p>
                </div>
                <div>
                    <h3><?php _e('Male (Page)', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_male_count); ?></p>
                </div>
                <div>
                    <h3><?php _e('Female (Page)', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_female_count); ?></p>
                </div>
                <div>
                    <h3><?php _e('Total Pages', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_pages); ?></p>
                </div>
            </div>

            <!-- Pagination Links -->
            <?php $this->render_pagination_links($current_page, $total_pages, $search_term); ?>

            <!-- Players Table -->
            <div class="intersoccer-player-management">
                <table id="player-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('User ID', 'player-management'); ?></th>
                            <th><?php _e('Canton', 'player-management'); ?></th>
                            <th><?php _e('City', 'player-management'); ?></th>
                            <th><?php _e('First Name', 'player-management'); ?></th>
                            <th><?php _e('Last Name', 'player-management'); ?></th>
                            <th><?php _e('DOB', 'player-management'); ?></th>
                            <th><?php _e('Gender', 'player-management'); ?></th>
                            <th><?php _e('AVS Number', 'player-management'); ?></th>
                            <th><?php _e('Event Count', 'player-management'); ?></th>
                            <th><?php _e('Medical Conditions', 'player-management'); ?></th>
                            <th><?php _e('Creation Date', 'player-management'); ?></th>
                            <th><?php _e('Past Events', 'player-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_players)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 40px;">
                                    <?php if (!empty($search_term)): ?>
                                        <?php _e('No players found matching your search.', 'player-management'); ?>
                                    <?php else: ?>
                                        <?php _e('No players found on this page.', 'player-management'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_players as $player): ?>
                                <tr data-player-index="<?php echo esc_attr($player['index']); ?>" 
                                    data-user-id="<?php echo esc_attr($player['user_id']); ?>"
                                    data-first-name="<?php echo esc_attr($player['first_name'] ?? ''); ?>"
                                    data-last-name="<?php echo esc_attr($player['last_name'] ?? ''); ?>"
                                    data-dob="<?php echo esc_attr($player['dob'] ?? ''); ?>"
                                    data-gender="<?php echo esc_attr($player['gender'] ?? ''); ?>"
                                    data-avs-number="<?php echo esc_attr($player['avs_number'] ?? ''); ?>"
                                    data-event-count="<?php echo esc_attr($player['event_count']); ?>"
                                    data-canton="<?php echo esc_attr($player['canton']); ?>"
                                    data-city="<?php echo esc_attr($player['city']); ?>"
                                    data-medical-conditions="<?php echo esc_attr($player['medical_conditions'] ?? ''); ?>"
                                    data-creation-timestamp="<?php echo esc_attr($player['creation_timestamp'] ?? ''); ?>"
                                    data-past-events="<?php echo esc_attr(json_encode($player['past_events'])); ?>">
                                    <td class="display-user-id" data-label="User ID">
                                        <a href="/wp-admin/user-edit.php?user_id=<?php echo esc_attr($player['user_id']); ?>">
                                            <?php echo esc_html($player['user_id']); ?>
                                        </a>
                                    </td>
                                    <td class="display-canton" data-label="Canton"><?php echo esc_html($player['canton']); ?></td>
                                    <td class="display-city" data-label="City"><?php echo esc_html($player['city']); ?></td>
                                    <td class="display-first-name" data-label="First Name"><?php echo esc_html($player['first_name'] ?? ''); ?></td>
                                    <td class="display-last-name" data-label="Last Name"><?php echo esc_html($player['last_name'] ?? ''); ?></td>
                                    <td class="display-dob" data-label="DOB"><?php echo esc_html($player['dob'] ?? ''); ?></td>
                                    <td class="display-gender" data-label="Gender"><?php echo esc_html($player['gender'] ?? ''); ?></td>
                                    <td class="display-avs-number" data-label="AVS Number"><?php echo esc_html($player['avs_number'] ?? ''); ?></td>
                                    <td class="display-event-count" data-label="Event Count"><?php echo esc_html($player['event_count']); ?></td>
                                    <td class="display-medical-conditions" data-label="Medical Conditions">
                                        <?php 
                                        $medical = $player['medical_conditions'] ?? '';
                                        echo esc_html(strlen($medical) > 20 ? substr($medical, 0, 20) . '...' : $medical); 
                                        ?>
                                    </td>
                                    <td class="display-creation-date" data-label="Creation Date">
                                        <?php echo esc_html($player['creation_timestamp'] ? date('Y-m-d', $player['creation_timestamp']) : 'N/A'); ?>
                                    </td>
                                    <td class="display-past-events" data-label="Past Events">
                                        <?php 
                                        $past_events = $player['past_events'];
                                        if (!empty($past_events)) {
                                            $events_text = implode(', ', $past_events);
                                            echo esc_html(strlen($events_text) > 50 ? substr($events_text, 0, 50) . '...' : $events_text);
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Pagination -->
            <?php $this->render_pagination_links($current_page, $total_pages, $search_term); ?>

            <!-- Performance Info (only show in debug mode) -->
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div style="background: #f9f9f9; padding: 10px; margin-top: 20px; font-size: 12px; color: #666;">
                    <strong>Debug Info:</strong>
                    Memory Peak: <?php echo $this->format_bytes(memory_get_peak_usage(true)); ?> |
                    Players on Page: <?php echo count($all_players); ?> |
                    Page: <?php echo $current_page; ?>/<?php echo $total_pages; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Add loading indicator for pagination clicks
            $('.pagination-links a').on('click', function() {
                $('<div class="loading">Loading players...</div>').insertAfter('.pagination-info');
            });
            
            // Add real-time search filtering for current page (optional)
            var searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                var searchTerm = $(this).val().toLowerCase();
                
                searchTimeout = setTimeout(function() {
                    $('#player-table tbody tr').each(function() {
                        var $row = $(this);
                        var firstName = $row.find('.display-first-name').text().toLowerCase();
                        var lastName = $row.find('.display-last-name').text().toLowerCase();
                        var fullName = firstName + ' ' + lastName;
                        
                        if (searchTerm === '' || fullName.includes(searchTerm)) {
                            $row.show();
                        } else {
                            $row.hide();
                        }
                    });
                }, 300); // Debounce search
            });
        });
        </script>
        
        <?php
        $this->log_memory('render_complete');

        error_reporting($old_error_reporting);
    }

    private function is_date_past($end_date, $today) {
        try {
            $end = DateTime::createFromFormat('d/m/Y', $end_date);
            $today_date = DateTime::createFromFormat('Y-m-d H:i:s', $today);
            return $end && $today_date && $end < $today_date;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer: Date comparison error - " . $e->getMessage());
            }
            return false;
        }
    }
}

if (is_admin()) {
    new Player_Management_Admin();
}

// Render assigned attendee dropdown in admin order item
function intersoccer_render_order_item_player_dropdown( $product, $item, $item_id ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'InterSoccer: Entered render_order_item_player_dropdown for item ID: ' . $item_id . ', product ID: ' . ( $product ? $product->get_id() : 'none' ) . ', product type: ' . ( $product ? $product->get_type() : 'none' ) );
    }

    if ( ! $product ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'InterSoccer: No product for item ID: ' . $item_id . ' - skipping dropdown' );
        }
        return;
    }

    // Get activity type from attribute (fall back to parent for variations)
    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
    $activity_type = wc_get_product( $product_id )->get_attribute( 'pa_activity-type' );
    $normalized_type = strtolower( trim( $activity_type ) );

    if ( ! in_array( $normalized_type, [ 'camp', 'course' ] ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'InterSoccer: Skipping dropdown for item ID: ' . $item_id . ' - Activity Type not Camp or Course: "' . $activity_type . '"' );
        }
        return;
    }

    $order = $item->get_order();
    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'InterSoccer: Skipping dropdown for item ID: ' . $item_id . ' - no user ID (guest order)' );
        }
        echo '<p>' . esc_html__( 'Guest order - no attendees available.', 'player-management' ) . '</p>';
        return;
    }

    $players = get_user_meta( $user_id, 'intersoccer_players', true ) ?: [];
    if ( empty( $players ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'InterSoccer: No players for user ID: ' . $user_id . ' on item ID: ' . $item_id . '. Raw meta: ' . print_r( get_user_meta( $user_id, 'intersoccer_players' ), true ) );
        }
        echo '<p>' . esc_html__( 'No registered attendees for this customer.', 'player-management' ) . '</p>';
        return;
    }

    $selected_index = $item->get_meta( 'intersoccer_player_index' );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'InterSoccer: Rendering dropdown for item ID: ' . $item_id . ', user ID: ' . $user_id . ', players count: ' . count( $players ) . ', selected index: ' . ( $selected_index ?: 'none' ) . ', activity_type: "' . $activity_type . '"' );
    }

    echo '<div class="wc-order-item-player">';
    echo '<label for="intersoccer_player_index_' . esc_attr( $item_id ) . '">' . esc_html__( 'Assigned Attendee', 'player-management' ) . '</label>';
    echo '<select name="intersoccer_player_index[' . esc_attr( $item_id ) . ']" id="intersoccer_player_index_' . esc_attr( $item_id ) . '">';
    echo '<option value="">' . esc_html__( 'Select Attendee', 'player-management' ) . '</option>';
    foreach ( $players as $index => $player ) {
        $name = ( $player['first_name'] ?? '' ) . ' ' . ( $player['last_name'] ?? '' );
        echo '<option value="' . esc_attr( $index ) . '" ' . selected( $selected_index, $index, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}
add_action( 'woocommerce_admin_order_item_values', 'intersoccer_render_order_item_player_dropdown', 10, 3 );

// Save assigned attendee on order update
function intersoccer_save_order_item_player( $post_id, $post ) {
    if ( isset( $_POST['intersoccer_player_index'] ) && is_array( $_POST['intersoccer_player_index'] ) ) {
        $order = wc_get_order( $post_id );
        foreach ( $order->get_items() as $item_id => $item ) {
            if ( isset( $_POST['intersoccer_player_index'][ $item_id ] ) ) {
                $index = sanitize_text_field( $_POST['intersoccer_player_index'][ $item_id ] );
                $item->update_meta_data( 'intersoccer_player_index', $index );

                // Also save attendee name for display
                if ( $index !== '' ) {
                    $user_id = $order->get_user_id();
                    $players = get_user_meta( $user_id, 'intersoccer_players', true ) ?: [];
                    if ( isset( $players[ $index ] ) ) {
                        $player = $players[ $index ];
                        $name = ( $player['first_name'] ?? '' ) . ' ' . ( $player['last_name'] ?? '' );
                        $item->update_meta_data( 'Assigned Attendee', $name );
                    }
                }

                $item->save();

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'InterSoccer: Saved assigned attendee for order item ID: ' . $item_id . ', index: ' . $index );
                }
            }
        }
    }
}
add_action( 'woocommerce_process_shop_order_meta', 'intersoccer_save_order_item_player', 10, 2 );
