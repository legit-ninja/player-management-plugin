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

        $players = $this->all_players_data;
        $region_data = [];
        $event_type_counts = ['Camps' => 0, 'Courses' => 0, 'Birthdays' => 0];
        $total_players = 0;

        foreach ($players as $player) {
            $total_players++;
            $region = $player['canton'] && $player['city'] ? "{$player['canton']} - {$player['city']}" : ($player['canton'] ?: ($player['city'] ?: 'Unknown'));
            $region_data[$region] = isset($region_data[$region]) ? $region_data[$region] + 1 : 1;

            $orders = wc_get_orders(['customer_id' => $player['user_id'], 'status' => ['wc-completed', 'wc-processing'], 'limit' => -1]);
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
                        if (in_array('Camps', $categories)) $event_type_counts['Camps']++;
                        elseif (in_array('Courses', $categories)) $event_type_counts['Courses']++;
                        elseif (in_array('Birthdays', $categories)) $event_type_counts['Birthdays']++;
                    }
                }
            }
        }

        $inline_css = '.wrap { background: #1e2529; color: #fff; padding: 10px; } .chart-container { width: 100%; height: 200px; }';
        wp_add_inline_style('intersoccer-player-management', $inline_css);
        ?>
        <div class="wrap">
            <h1><?php _e('Players Overview Dashboard', 'player-management'); ?></h1>
            <div class="dashboard-section">
                <div class="widget">
                    <h2><?php _e('Players by Region', 'player-management'); ?></h2>
                    <div class="chart-container"><canvas id="regionChart"></canvas></div>
                </div>
                <div class="widget">
                    <h2><?php _e('Event Type Distribution', 'player-management'); ?></h2>
                    <div class="chart-container"><canvas id="eventTypeChart"></canvas></div>
                </div>
            </div>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    new Chart(document.getElementById('regionChart'), {
                        type: 'bar',
                        data: { labels: <?php echo json_encode(array_keys($region_data)); ?>, datasets: [{ label: 'Players', data: <?php echo json_encode(array_values($region_data)); ?>, backgroundColor: '#36A2EB' }] },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
                    });
                    new Chart(document.getElementById('eventTypeChart'), {
                        type: 'pie',
                        data: { labels: <?php echo json_encode(array_keys($event_type_counts)); ?>, datasets: [{ data: <?php echo json_encode(array_values($event_type_counts)); ?>, backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'] }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                    });
                });
            </script>
        </div>
        <?php
    }

    public function render_all_players_page() {
        $users = get_users(['role' => 'customer']);
        $all_players = [];
        $unique_cantons = [];
        $unique_genders = [];
        $today = '2025-06-23';

        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: '';
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
                if ($billing_state && !in_array($billing_state, $unique_cantons)) {
                    $unique_cantons[] = $billing_state;
                }
                $gender = isset($player['gender']) ? $player['gender'] : 'Unknown';
                if (!in_array($gender, $unique_genders)) {
                    $unique_genders[] = $gender;
                }
            }
        }

        // Store all_players_data for use in render_overview_page
        $this->all_players_data = $all_players;

        $total_players = count($all_players);
        ?>
        <div class="wrap">
            <h1><?php _e('All Players', 'player-management'); ?></h1>

            <!-- Quick Stats with Only Total Players -->
            <div class="dashboard-section quick-stats">
                <div>
                    <h3><?php _e('Total Players', 'player-management'); ?></h3>
                    <p><?php echo esc_html($total_players); ?></p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <label for="filter-canton"><?php _e('Filter by Canton:', 'player-management'); ?></label>
                <select id="filter-canton" class="widefat" style="width: 200px;">
                    <option value=""><?php _e('All Cantons', 'player-management'); ?></option>
                    <?php foreach ($unique_cantons as $canton) : ?>
                        <option value="<?php echo esc_attr($canton); ?>"><?php echo esc_html($canton); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="filter-gender"><?php _e('Filter by Gender:', 'player-management'); ?></label>
                <select id="filter-gender" class="widefat" style="width: 200px;">
                    <option value=""><?php _e('All Genders', 'player-management'); ?></option>
                    <?php foreach ($unique_genders as $gender) : ?>
                        <option value="<?php echo esc_attr(strtolower($gender)); ?>"><?php echo esc_html($gender); ?></option>
                    <?php endforeach; ?>
                </select>
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
