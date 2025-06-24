<?php
/**
 * File: admin-players.php
 * Description: Manages the admin dashboard for the player-management plugin, including the "Players Overview" and "All Players" pages. Displays player statistics, charts, and a table of all players with filtering capabilities.
 * Author: Jeremy Lee
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Player_Management_Admin {
    private $all_players_data = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'remove_duplicate_menu'], 999);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
        add_action('admin_init', [$this, 'handle_player_actions']);
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
        $canton_data = []; // Renamed from region_data to reflect canton focus
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
            <script type="text/javascript">
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
        </div>
        <?php
    }

    public function render_all_players_page() {
        $users = get_users(['role' => 'customer']);
        $all_players = [];
        $total_players = 0;
        $male_count = 0;
        $female_count = 0;
        $today = '2025-06-23';

        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: 'Unknown';
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1
            ]);

            foreach ($players as $index => $player) {
                $total_players++;
                $player['user_id'] = $user->ID;
                $player['user_email'] = $user->user_email;
                $player['index'] = $index;
                $player['canton'] = $billing_state;
                $player['city'] = get_user_meta($user->ID, 'billing_city', true) ?: '';
                $player['event_count'] = 0;
                $player['past_events'] = [];
                $player['event_types'] = [];

                $dob = $player['dob'] ?? '';
                if ($dob) {
                    $age = date_diff(date_create($dob), date_create($today))->y;
                    if ($age >= 3 && $age <= 14) {
                        $player['event_age_groups'] = ["Age $age"];
                    } else {
                        $player['event_age_groups'] = [];
                    }
                }

                $gender = strtolower($player['gender'] ?? 'other');
                if ($gender === 'male') $male_count++;
                elseif ($gender === 'female') $female_count++;

                foreach ($orders as $order) {
                    $order_players = $order->get_meta('intersoccer_players', true);
                    $player_name = isset($player['name']) ? $player['name'] : (isset($player['first_name']) ? $player['first_name'] : '');
                    if ($order_players && in_array($player_name, (array)$order_players)) {
                        foreach ($order->get_items() as $item) {
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

                $all_players[] = $player;
            }
        }

        // Store all_players_data for use in render_overview_page
        $this->all_players_data = $all_players;

        $inline_css = '
            .quick-stats { display: flex; justify-content: space-between; margin-bottom: 15px; }
            .quick-stats div { text-align: center; flex: 1; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; }
            .quick-stats div h3 { margin: 0 0 5px; font-size: 14px; }
            .quick-stats div p { margin: 0; font-size: 16px; font-weight: bold; }
            @media (max-width: 600px) {
                .quick-stats { flex-direction: column; }
                .quick-stats div { margin-bottom: 10px; }
            }
            .filter-section { margin-bottom: 15px; }
            @media (max-width: 600px) {
                .intersoccer-player-management table, .intersoccer-player-management thead, .intersoccer-player-management tbody, .intersoccer-player-management th, .intersoccer-player-management td, .intersoccer-player-management tr {
                    display: block;
                }
                .intersoccer-player-management thead tr { position: absolute; top: -9999px; left: -9999px; }
                .intersoccer-player-management tr { margin-bottom: 15px; border: 1px solid #ddd; }
                .intersoccer-player-management td { border: none; position: relative; padding-left: 50%; }
                .intersoccer-player-management td:before {
                    content: attr(data-label);
                    position: absolute;
                    left: 10px;
                    width: 45%;
                    padding-right: 10px;
                    white-space: nowrap;
                    font-weight: bold;
                }
                .intersoccer-player-management .actions { text-align: right; padding-right: 10px; }
                .intersoccer-player-management .actions a { display: block; margin: 5px 0; }
            }
        ';
        wp_add_inline_style('intersoccer-player-management', $inline_css);
        ?>
        <div class="wrap">
            <h1><?php _e('All Players', 'player-management'); ?></h1>

            <!-- Quick Stats with Total Players, Males, and Females side-by-side -->
            <div class="dashboard-section quick-stats">
                <div>
                    <h3><?php _e('Total Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_players); ?></p>
                </div>
                <div>
                    <h3><?php _e('Male Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($male_count); ?></p>
                </div>
                <div>
                    <h3><?php _e('Female Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($female_count); ?></p>
                </div>
            </div>

            <!-- Filter Section with only Name Search -->
            <div class="filter-section">
                <label for="player-search"><?php _e('Search by Name:', 'player-management'); ?></label>
                <input type="text" id="player-search" placeholder="<?php _e('Enter player name...', 'player-management'); ?>" class="widefat">
            </div>

            <?php echo intersoccer_render_players_form(true, ['total_players' => $total_players]); ?>
        </div>
        <?php
    }

    private function is_date_past($end_date, $today) {
        try {
            $end = DateTime::createFromFormat('d/m/Y', $end_date);
            $today_date = DateTime::createFromFormat('Y-m-d', $today);
            return $end && $today_date && $end < $today_date;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer: Date comparison error - " . $e->getMessage());
            }
            return false;
        }
    }

    private function get_event_type($product) {
        if (!$product) {
            return '';
        }
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        if (in_array('Camps', $categories)) {
            return 'Camps';
        } elseif (in_array('Courses', $categories)) {
            return 'Courses';
        } elseif (in_array('Birthdays', $categories)) {
            return 'Birthdays';
        }
        return '';
    }
}

// Instantiate only if in admin
if (is_admin()) {
    new Player_Management_Admin();
}
?>
