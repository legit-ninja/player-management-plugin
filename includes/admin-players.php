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
        $users = get_users(['role' => 'customer']);
        $gender_data = [];
        $region_data = [];
        $age_counts = array_fill(3, 11, 0); // Ages 3 to 13
        $event_type_counts = ['Camps' => 0, 'Courses' => 0, 'Birthdays' => 0];
        $total_players = 0;
        $total_ages = 0;
        $player_count = 0;
        $active_players = 0; // Players who participated in events this season
        $new_registrations = 0; // Players registered this month
        $players_with_medical_conditions = 0;
        $coaches_organizers = [];
        $event_trends = [
            'months' => [],
            'camps' => [],
            'courses' => [],
            'birthdays' => [],
        ];
        $registration_trends = [
            'months' => [],
            'counts' => [],
        ];
        $season_counts = ['Spring' => 0, 'Summer' => 0, 'Autumn' => 0, 'Winter' => 0];
        $venue_counts = [];
        $attended_players = 0; // Players who attended at least one event this season
        $total_events = 0; // Total events this season
        $total_event_participations = 0; // Total participations across all players

        // Calculate event trends for the past 6 months and active players
        $current_date = new DateTime('2025-06-05');
        $current_season = 'Summer'; // Based on June
        $season_start = (clone $current_date)->modify('first day of March 2025')->format('Y-m-d'); // Example season start
        $month_start = (clone $current_date)->modify('first day of this month')->format('Y-m-d'); // Start of June 2025
        for ($i = 5; $i >= 0; $i--) {
            $month_date = (clone $current_date)->modify("-$i months");
            $month_key = $month_date->format('Y-m');
            $event_trends['months'][] = $month_date->format('M Y');
            $event_trends['camps'][$month_key] = 0;
            $event_trends['courses'][$month_key] = 0;
            $event_trends['birthdays'][$month_key] = 0;
            $registration_trends['months'][] = $month_date->format('M Y');
            $registration_trends['counts'][$month_key] = 0;
        }

        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: '';
            $billing_city = get_user_meta($user->ID, 'billing_city', true) ?: '';
            $region = $billing_state && $billing_city ? "$billing_state - $billing_city" : ($billing_state ?: ($billing_city ?: 'Unknown'));
            $region_data[$region] = isset($region_data[$region]) ? $region_data[$region] + count($players) : count($players);
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1,
                'date_after' => (clone $current_date)->modify('-6 months')->format('Y-m-d'),
            ]);

            $has_active_events = false;
            foreach ($players as $player) {
                $total_players++;
                $gender = isset($player['gender']) ? strtolower($player['gender']) : 'other';
                $gender_data[$gender] = isset($gender_data[$gender]) ? $gender_data[$gender] + 1 : 1;

                if (!empty($player['medical_conditions'])) {
                    $players_with_medical_conditions++;
                }

                $dob = $player['dob'] ?? '';
                if ($dob) {
                    $age = date_diff(date_create($dob), date_create('2025-06-05'))->y;
                    if ($age >= 3 && $age <= 13) {
                        $age_counts[$age]++;
                        $total_ages += $age;
                        $player_count++;
                    }
                }

                // Check for new registrations this month and registration trends
                if (!empty($player['creation_timestamp'])) {
                    $reg_date = new DateTime();
                    $reg_date->setTimestamp($player['creation_timestamp']);
                    $reg_month_key = $reg_date->format('Y-m');
                    if ($reg_date->format('Y-m-d') >= $month_start) {
                        $new_registrations++;
                    }
                    if (isset($registration_trends['counts'][$reg_month_key])) {
                        $registration_trends['counts'][$reg_month_key]++;
                    }
                    $month = (int)$reg_date->format('m');
                    if ($month >= 3 && $month <= 5) {
                        $season_counts['Spring']++;
                    } elseif ($month >= 6 && $month <= 8) {
                        $season_counts['Summer']++;
                    } elseif ($month >= 9 && $month <= 11) {
                        $season_counts['Autumn']++;
                    } else {
                        $season_counts['Winter']++;
                    }
                }

                foreach ($orders as $order) {
                    $order_players = $order->get_meta('intersoccer_players', true);
                    $player_name = isset($player['name']) ? $player['name'] : (isset($player['first_name']) ? $player['first_name'] : '');
                    if ($order_players && in_array($player_name, (array)$order_players)) {
                        $order_date = new DateTime($order->get_date_created()->date('Y-m-d'));
                        $month_key = $order_date->format('Y-m');
                        // Check if order is within this season
                        if ($order_date->format('Y-m-d') >= $season_start) {
                            $has_active_events = true;
                            $total_events++;
                            $total_event_participations++;
                        }
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();
                            if (!$product) continue;
                            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
                            if (in_array('Camps', $categories)) {
                                $event_type_counts['Camps']++;
                                if (isset($event_trends['camps'][$month_key])) {
                                    $event_trends['camps'][$month_key]++;
                                }
                            } elseif (in_array('Courses', $categories)) {
                                $event_type_counts['Courses']++;
                                if (isset($event_trends['courses'][$month_key])) {
                                    $event_trends['courses'][$month_key]++;
                                }
                            } elseif (in_array('Birthdays', $categories)) {
                                $event_type_counts['Birthdays']++;
                                if (isset($event_trends['birthdays'][$month_key])) {
                                    $event_trends['birthdays'][$month_key]++;
                                }
                            }

                            // Venue participation
                            $venue = $product->get_attribute('pa_venue') ?: 'Unknown';
                            $venue_counts[$venue] = isset($venue_counts[$venue]) ? $venue_counts[$venue] + 1 : 1;

                            // Assign players to coaches/organizers
                            $coach = $order->get_meta('assigned_coach', true);
                            $organizer = $order->get_meta('assigned_organizer', true);
                            if ($coach) {
                                $coaches_organizers[$coach] = isset($coaches_organizers[$coach]) ? $coaches_organizers[$coach] + 1 : 1;
                            }
                            if ($organizer) {
                                $coaches_organizers[$organizer] = isset($coaches_organizers[$organizer]) ? $coaches_organizers[$organizer] + 1 : 1;
                            }
                        }
                    }
                }
                if ($has_active_events) {
                    $active_players++;
                    $attended_players++;
                }
            }
        }

        // Calculate additional stats
        $average_age = $player_count > 0 ? $total_ages / $player_count : 0;
        $attendance_rate = $total_players > 0 ? ($attended_players / $total_players) * 100 : 0;
        $top_event_type = array_keys($event_type_counts, max($event_type_counts))[0];
        $avg_events_per_player = $total_players > 0 ? $total_event_participations / $total_players : 0;

        // Sort regions by player count (descending) for Top 5 Regions
        arsort($region_data);
        $top_regions = array_slice($region_data, 0, 5, true);

        // Sort venues by participation (descending)
        arsort($venue_counts);
        $top_venues = array_slice($venue_counts, 0, 5, true);

        // Prepare event trends and registration trends data
        $event_trends_data = [
            'camps' => [],
            'courses' => [],
            'birthdays' => [],
        ];
        $registration_trends_data = [];
        foreach ($event_trends['months'] as $month) {
            $month_key = DateTime::createFromFormat('M Y', $month)->format('Y-m');
            $event_trends_data['camps'][] = $event_trends['camps'][$month_key] ?? 0;
            $event_trends_data['courses'][] = $event_trends['courses'][$month_key] ?? 0;
            $event_trends_data['birthdays'][] = $event_trends['birthdays'][$month_key] ?? 0;
            $registration_trends_data[] = $registration_trends['counts'][$month_key] ?? 0;
        }

        // Inline CSS for dense stock broker-style dashboard
        $inline_css = '
            .wrap { 
                background: #1e2529; 
                color: #fff; 
                padding: 10px; 
                border-radius: 8px; 
                font-family: "Arial", sans-serif; 
                min-height: 100vh; 
            }
            h1 { 
                color: #00ff00; 
                text-align: center; 
                font-size: 22px; 
                margin-bottom: 10px; 
                text-transform: uppercase; 
                letter-spacing: 2px; 
                text-shadow: 0 0 5px rgba(0,255,0,0.5); 
            }
            .dashboard-section { margin-bottom: 8px; }
            .quick-stats { 
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); 
                gap: 5px; 
                padding: 8px; 
                background: #252c31; 
                border-radius: 6px; 
            }
            .quick-stats > div { 
                background: linear-gradient(135deg, #2c3439, #1e2529); 
                padding: 10px; 
                border-radius: 4px; 
                text-align: center; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.5); 
                transition: transform 0.3s ease, box-shadow 0.3s ease; 
                border: 1px solid rgba(0,255,0,0.1); 
            }
            .quick-stats > div:hover { 
                transform: translateY(-2px); 
                box-shadow: 0 3px 8px rgba(0,255,0,0.4); 
                border-color: rgba(0,255,0,0.3); 
            }
            .quick-stats h3 { 
                margin: 0 0 3px 0; 
                font-size: 11px; 
                color: #bbb; 
                text-transform: uppercase; 
                letter-spacing: 0.8px; 
            }
            .quick-stats p { 
                font-size: 16px; 
                margin: 0; 
                color: #00ff00; 
                font-weight: 600; 
                text-shadow: 0 0 2px rgba(0,255,0,0.3); 
            }
            .quick-stats .alert p { 
                color: #ff4444; 
                text-shadow: 0 0 2px rgba(255,68,68,0.3); 
            }
            .main-dashboard { 
                display: grid; 
                grid-template-columns: 30% 40% 30%; 
                gap: 5px; 
                padding: 8px; 
                background: #252c31; 
                border-radius: 6px; 
            }
            .left-column, .center-column, .right-column { 
                display: flex; 
                flex-direction: column; 
                gap: 5px; 
            }
            .widget { 
                background: linear-gradient(135deg, #2c3439, #1e2529); 
                padding: 10px; 
                border-radius: 4px; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.5); 
                transition: box-shadow 0.3s ease; 
                border: 1px solid rgba(0,255,0,0.1); 
            }
            .widget:hover { 
                box-shadow: 0 3px 8px rgba(0,255,0,0.4); 
                border-color: rgba(0,255,0,0.3); 
            }
            .widget h2 { 
                font-size: 12px; 
                margin: 0 0 6px 0; 
                color: #00ff00; 
                text-align: center; 
                text-transform: uppercase; 
                letter-spacing: 0.8px; 
                text-shadow: 0 0 2px rgba(0,255,0,0.2); 
            }
            .chart-container { 
                width: 100% !important; 
                height: 180px !important; 
                margin: 0 auto; 
                position: relative; 
            }
            .event-trends .chart-container { 
                width: 100% !important; 
                height: 250px !important; 
            }
            .mini-chart .chart-container { 
                height: 120px !important; 
            }
            .widget canvas { 
                width: 100% !important; 
                height: 100% !important; 
            }
            .small-charts { 
                display: grid; 
                grid-template-columns: repeat(2, 1fr); 
                gap: 5px; 
            }
            .tables-section { 
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
                gap: 5px; 
                padding: 8px; 
                background: #252c31; 
                border-radius: 6px; 
            }
            .tables-section > div, .coaches-section, .venues-section { 
                background: linear-gradient(135deg, #2c3439, #1e2529); 
                padding: 10px; 
                border-radius: 4px; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.5); 
                border: 1px solid rgba(0,255,0,0.1); 
            }
            .coaches-section, .venues-section { 
                grid-column: 1 / -1; 
                margin-top: 5px; 
            }
            .venues-section { 
                max-height: 200px; 
                overflow-y: auto; 
            }
            .wp-list-table { 
                background: transparent; 
                color: #fff; 
                border: none; 
                font-size: 11px; 
            }
            .wp-list-table th { 
                background: #2c3439; 
                color: #00ff00; 
                border: 1px solid #3a4449; 
                font-size: 11px; 
                text-transform: uppercase; 
                letter-spacing: 0.8px; 
            }
            .wp-list-table td { 
                background: #1e2529; 
                border: 1px solid #3a4449; 
                font-size: 10px; 
            }
            .wp-list-table tr:hover td { 
                background: #2c3439; 
                transition: background 0.2s ease; 
            }
            .wp-list-table .sortable { 
                cursor: pointer; 
            }
            .wp-list-table .sortable:hover { 
                color: #ff4444; 
            }

            @media (max-width: 1200px) {
                .main-dashboard { grid-template-columns: 50% 50%; }
                .right-column { display: none; }
            }
            @media (max-width: 768px) {
                .main-dashboard { grid-template-columns: 1fr; }
                .tables-section { grid-template-columns: 1fr; }
                .event-trends .chart-container { height: 200px !important; }
                .quick-stats { grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); }
                .small-charts { grid-template-columns: 1fr; }
                .chart-container { height: 160px !important; }
                .mini-chart .chart-container { height: 100px !important; }
            }
        ';
        wp_add_inline_style('intersoccer-player-management', $inline_css);
        ?>
        <div class="wrap">
            <h1><?php _e('Players Overview Dashboard', 'player-management'); ?></h1>

            <!-- Quick Stats -->
            <div class="dashboard-section quick-stats">
                <div>
                    <h3><?php _e('Total Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_players); ?></p>
                </div>
                <div>
                    <h3><?php _e('Active Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($active_players); ?></p>
                </div>
                <div>
                    <h3><?php _e('Attendance Rate', 'player-management'); ?></h3>
                    <p><?php echo esc_html(number_format($attendance_rate, 1)); ?>%</p>
                </div>
                <div>
                    <h3><?php _e('Total Events', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_events); ?></p>
                </div>
                <div>
                    <h3><?php _e('Avg Events/Player', 'player-management'); ?></h3>
                    <p><?php echo esc_html(number_format($avg_events_per_player, 1)); ?></p>
                </div>
                <div class="<?php echo $players_with_medical_conditions > 0 ? 'alert' : ''; ?>">
                    <h3><?php _e('Medical Alerts', 'player-management'); ?></h3>
                    <p><?php echo esc_html($players_with_medical_conditions); ?></p>
                </div>
                <div>
                    <h3><?php _e('New This Month', 'player-management'); ?></h3>
                    <p><?php echo esc_html($new_registrations); ?></p>
                </div>
                <div>
                    <h3><?php _e('Top Event Type', 'player-management'); ?></h3>
                    <p><?php echo esc_html($top_event_type); ?></p>
                </div>
            </div>

            <!-- Main Dashboard -->
            <div class="dashboard-section main-dashboard">
                <!-- Left Column -->
                <div class="left-column">
                    <div class="widget">
                        <h2><?php _e('Gender Breakdown', 'player-management'); ?></h2>
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                    <div class="widget">
                        <h2><?php _e('Age Distribution', 'player-management'); ?></h2>
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                    <div class="widget">
                        <h2><?php _e('Event Type Distribution', 'player-management'); ?></h2>
                        <div class="chart-container">
                            <canvas id="eventTypeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Center Column -->
                <div class="center-column">
                    <div class="widget event-trends">
                        <h2><?php _e('Event Participation Trends', 'player-management'); ?></h2>
                        <div class="chart-container">
                            <canvas id="eventTrendsChart"></canvas>
                        </div>
                    </div>
                    <div class="small-charts">
                        <div class="widget">
                            <h2><?php _e('Registrations by Season', 'player-management'); ?></h2>
                            <div class="chart-container">
                                <canvas id="seasonChart"></canvas>
                            </div>
                        </div>
                        <div class="widget">
                            <h2><?php _e('Participation by Venue (Top 5)', 'player-management'); ?></h2>
                            <div class="chart-container">
                                <canvas id="venueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-column">
                    <div class="widget mini-chart">
                        <h2><?php _e('Registrations Over Time', 'player-management'); ?></h2>
                        <div class="chart-container">
                            <canvas id="registrationTrendsChart"></canvas>
                        </div>
                    </div>
                    <div class="widget">
                        <h2><?php _e('Top 5 Regions by Player Count', 'player-management'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Region', 'player-management'); ?></th>
                                    <th><?php _e('Player Count', 'player-management'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_regions as $region => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html($region); ?></td>
                                        <td><?php echo esc_html($count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="dashboard-section tables-section">
                <div>
                    <h2><?php _e('Region Distribution', 'player-management'); ?></h2>
                    <table class="wp-list-table widefat fixed striped" id="regionTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="string"><?php _e('Region', 'player-management'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></th>
                                <th class="sortable" data-sort="number"><?php _e('Player Count', 'player-management'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($region_data as $region => $count) : ?>
                                <tr>
                                    <td><?php echo esc_html($region); ?></td>
                                    <td><?php echo esc_html($count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Venues Section -->
            <div class="dashboard-section venues-section">
                <h2><?php _e('Venue Utilization', 'player-management'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Venue', 'player-management'); ?></th>
                            <th><?php _e('Participation Count', 'player-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($venue_counts as $venue => $count) : ?>
                            <tr>
                                <td><?php echo esc_html($venue); ?></td>
                                <td><?php echo esc_html($count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Coaches and Organizers -->
            <div class="dashboard-section coaches-section">
                <h2><?php _e('Players per Coach/Organizer', 'player-management'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Coach/Organizer', 'player-management'); ?></th>
                            <th><?php _e('Player Count', 'player-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coaches_organizers as $name => $count) : ?>
                            <tr>
                                <td><?php echo esc_html($name); ?></td>
                                <td><?php echo esc_html($count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($coaches_organizers)) : ?>
                            <tr>
                                <td colspan="2"><?php _e('No coaches or organizers assigned.', 'player-management'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    Chart.defaults.color = '#fff';
                    Chart.defaults.borderColor = '#3a4449';

                    // Gender Breakdown
                    const genderData = {
                        labels: <?php echo json_encode(array_keys($gender_data)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($gender_data)); ?>,
                            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                        }]
                    };
                    new Chart(document.getElementById('genderChart'), {
                        type: 'pie',
                        data: genderData,
                        options: { 
                            responsive: false, 
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
                        }
                    });

                    // Age Distribution
                    const ageData = {
                        labels: <?php echo json_encode(range(3, 13)); ?>,
                        datasets: [{
                            label: 'Players',
                            data: <?php echo json_encode(array_values($age_counts)); ?>,
                            backgroundColor: '#36A2EB'
                        }]
                    };
                    new Chart(document.getElementById('ageChart'), {
                        type: 'bar',
                        data: ageData,
                        options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#3a4449' }, ticks: { font: { size: 8 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Event Type Distribution
                    const eventTypeData = {
                        labels: <?php echo json_encode(array_keys($event_type_counts)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($event_type_counts)); ?>,
                            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                        }]
                    };
                    new Chart(document.getElementById('eventTypeChart'), {
                        type: 'pie',
                        data: eventTypeData,
                        options: { 
                            responsive: false, 
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
                        }
                    });

                    // Event Participation Trends
                    const eventTrendsData = {
                        labels: <?php echo json_encode($event_trends['months']); ?>,
                        datasets: [
                            { label: 'Camps', data: <?php echo json_encode($event_trends_data['camps']); ?>, borderColor: '#FF6384', fill: false },
                            { label: 'Courses', data: <?php echo json_encode($event_trends_data['courses']); ?>, borderColor: '#36A2EB', fill: false },
                            { label: 'Birthdays', data: <?php echo json_encode($event_trends_data['birthdays']); ?>, borderColor: '#FFCE56', fill: false }
                        ]
                    };
                    new Chart(document.getElementById('eventTrendsChart'), {
                        type: 'line',
                        data: eventTrendsData,
                        options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#3a4449' }, ticks: { font: { size: 8 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
                            },
                            plugins: { legend: { labels: { font: { size: 10 } } } }
                        }
                    });

                    // Registrations by Season
                    const seasonData = {
                        labels: <?php echo json_encode(array_keys($season_counts)); ?>,
                        datasets: [{
                            label: 'Registrations',
                            data: <?php echo json_encode(array_values($season_counts)); ?>,
                            backgroundColor: '#00ff00'
                        }]
                    };
                    new Chart(document.getElementById('seasonChart'), {
                        type: 'bar',
                        data: seasonData,
                        options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#3a4449' }, ticks: { font: { size: 8 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Participation by Venue (Top 5)
                    const venueData = {
                        labels: <?php echo json_encode(array_keys($top_venues)); ?>,
                        datasets: [{
                            label: 'Participation',
                            data: <?php echo json_encode(array_values($top_venues)); ?>,
                            backgroundColor: '#FFCE56'
                        }]
                    };
                    new Chart(document.getElementById('venueChart'), {
                        type: 'bar',
                        data: venueData,
                        options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#3a4449' }, ticks: { font: { size: 8 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Registrations Over Time
                    const registrationTrendsData = {
                        labels: <?php echo json_encode($registration_trends['months']); ?>,
                        datasets: [{
                            label: 'Registrations',
                            data: <?php echo json_encode($registration_trends_data); ?>,
                            borderColor: '#00ff00',
                            fill: false
                        }]
                    };
                    new Chart(document.getElementById('registrationTrendsChart'), {
                        type: 'line',
                        data: registrationTrendsData,
                        options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#3a4449' }, ticks: { font: { size: 8 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Region Table Sorting
                    const table = document.getElementById('regionTable');
                    const headers = table.querySelectorAll('.sortable');
                    headers.forEach(header => {
                        header.addEventListener('click', () => {
                            const sortType = header.getAttribute('data-sort');
                            const columnIndex = Array.from(header.parentElement.children).indexOf(header);
                            const rows = Array.from(table.querySelector('tbody').children);
                            const isAscending = header.classList.contains('asc');

                            headers.forEach(h => h.classList.remove('asc', 'desc'));
                            header.classList.add(isAscending ? 'desc' : 'asc');

                            rows.sort((a, b) => {
                                let aValue = a.children[columnIndex].textContent;
                                let bValue = b.children[columnIndex].textContent;
                                if (sortType === 'number') {
                                    aValue = parseInt(aValue);
                                    bValue = parseInt(bValue);
                                    return isAscending ? bValue - aValue : aValue - bValue;
                                } else {
                                    return isAscending 
                                        ? bValue.localeCompare(aValue) 
                                        : aValue.localeCompare(bValue);
                                }
                            });

                            const tbody = table.querySelector('tbody');
                            rows.forEach(row => tbody.appendChild(row));
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    public function render_all_players_page() {
        $users = get_users(['role' => 'customer']);
        $all_players = [];
        $all_cantons = [];
        $all_age_groups = array_map(function($age) { return "Age $age"; }, range(3, 14)); // Ages 3-14
        $all_event_types = [];
        $all_genders = ['Male', 'Female', 'Other'];
        $today = '2025-06-05';

        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: '';
            $billing_city = get_user_meta($user->ID, 'billing_city', true) ?: '';
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1
            ]);

            foreach ($players as $index => $player) {
                $player['user_id'] = $user->ID;
                $player['user_email'] = $user->user_email;
                $player['index'] = $index;
                $player['canton'] = $billing_state;
                $player['city'] = $billing_city;
                $player['event_count'] = 0;
                $player['past_events'] = [];
                $player['event_age_groups'] = [];
                $player['event_types'] = [];

                // Calculate age group based on DOB
                $dob = $player['dob'] ?? '';
                if ($dob) {
                    $age = date_diff(date_create($dob), date_create($today))->y;
                    if ($age >= 3 && $age <= 14) {
                        $player['event_age_groups'] = ["Age $age"];
                    } else {
                        $player['event_age_groups'] = [];
                    }
                }

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
                            // Collect event type data
                            if ($event_type && !in_array($event_type, $player['event_types'])) {
                                $player['event_types'][] = $event_type;
                            }
                        }
                    }
                }

                $all_players[] = $player;
                if ($billing_state && !in_array($billing_state, $all_cantons)) {
                    $all_cantons[] = $billing_state;
                }
                foreach ($player['event_types'] as $event_type) {
                    if (!in_array($event_type, $all_event_types)) {
                        $all_event_types[] = $event_type;
                    }
                }
            }
        }

        sort($all_cantons);
        sort($all_event_types);

        // Pass filter data and total players to intersoccer_render_players_form
        $settings = [
            'cantons' => $all_cantons,
            'age_groups' => $all_age_groups,
            'event_types' => $all_event_types,
            'total_players' => count($all_players),
        ];
        ?>
        <div class="wrap">
            <h1><?php _e('All Players', 'player-management'); ?></h1>
            <?php echo intersoccer_render_players_form(true, $settings); ?>
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
