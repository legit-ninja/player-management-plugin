<?php
/**
 * Player Management Admin Dashboard
 * Handles the admin dashboard for managing players, including columns and filters.
 * Author: Jeremy Lee
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include WP_List_Table
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Player_Management_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
    }

    public function add_admin_menu() {
        $hook = add_menu_page(
            __('Player Management', 'player-management'),
            __('Player Management', 'player-management'),
            'manage_options',
            'player-management',
            [$this, 'render_admin_page'],
            'dashicons-groups',
            30
        );
        add_action("load-$hook", [$this, 'screen_option']);
    }

    public function screen_option() {
        $option = 'per_page';
        $args = [
            'label' => __('Players per page', 'player-management'),
            'default' => 20,
            'option' => 'players_per_page'
        ];
        add_screen_option($option, $args);
        $this->players_table = new Player_Management_Players_Table();
    }

    public function set_screen_option($status, $option, $value) {
        return $value;
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Player Management', 'player-management'); ?></h1>
            <?php $this->players_table->prepare_items(); ?>
            <form method="get">
                <input type="hidden" name="page" value="player-management">
                <?php $this->players_table->display(); ?>
            </form>
        </div>
        <?php
    }
}

class Player_Management_Players_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Player', 'player-management'),
            'plural' => __('Players', 'player-management'),
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', 'player-management'),
            'age_group' => __('Age Group', 'player-management'),
            'region' => __('Region', 'player-management'),
            'events' => __('Events', 'player-management'),
            'past_events' => __('Past Events', 'player-management')
        ];
    }

    public function get_sortable_columns() {
        return [
            'name' => ['name', true],
            'age_group' => ['age_group', true],
            'region' => ['region', true],
            'events' => ['events', true]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
                return esc_html($item['name']);
            case 'age_group':
                return esc_html($item['age_group'] ?: 'N/A');
            case 'region':
                return esc_html($item['region'] ?: 'Unknown');
            case 'events':
                return intval($item['events']);
            case 'past_events':
                return $item['past_events'] ? wp_kses_post($item['past_events']) : __('No past events', 'player-management');
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="player[]" value="%s" />', esc_attr($item['id']));
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'player-management')
        ];
    }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('players_per_page', 20);
        $current_page = $this->get_pagenum();
        $filters = [
            'region' => isset($_GET['region']) ? sanitize_text_field($_GET['region']) : '',
            'age_group' => isset($_GET['age_group']) ? sanitize_text_field($_GET['age_group']) : '',
            'event_type' => isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : ''
        ];

        $players = $this->get_players($filters, $per_page, $current_page);
        $total_items = $this->get_players_count($filters);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page
        ]);

        $this->items = $players;

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
    }

    private function get_players($filters, $per_page, $current_page) {
        $players = [];
        $users = get_users(['role' => 'customer']);
        $today = current_time('Y-m-d');

        foreach ($users as $user) {
            $player_data = get_user_meta($user->ID, 'intersoccer_players', true);
            if (!$player_data || !is_array($player_data)) {
                continue;
            }

            foreach ($player_data as $player) {
                $region = get_user_meta($user->ID, 'billing_region', true) ?: 'Unknown';
                $events_count = 0;
                $past_events = [];

                // Query orders for player assignments
                $orders = wc_get_orders([
                    'customer_id' => $user->ID,
                    'status' => ['wc-completed', 'wc-processing'],
                    'limit' => -1
                ]);

                foreach ($orders as $order) {
                    $order_players = $order->get_meta('intersoccer_players', true);
                    if ($order_players && in_array($player['name'], (array)$order_players)) {
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();
                            $end_date = $product->get_attribute('pa_end-date');
                            $event_type = $this->get_event_type($product);

                            // Apply event type filter
                            if ($filters['event_type'] && $event_type !== $filters['event_type']) {
                                continue;
                            }

                            $events_count++;
                            if ($end_date && $this->is_date_past($end_date, $today)) {
                                $past_events[] = esc_html($item->get_name());
                            }
                        }
                    }
                }

                // Apply filters
                if ($filters['region'] && $region !== $filters['region']) {
                    continue;
                }
                if ($filters['age_group'] && $player['age_group'] !== $filters['age_group']) {
                    continue;
                }

                $players[] = [
                    'id' => $user->ID . '-' . md5($player['name']),
                    'name' => $player['name'],
                    'age_group' => $player['age_group'] ?: 'N/A',
                    'region' => $region,
                    'events' => $events_count,
                    'past_events' => $past_events ? implode(', ', $past_events) : ''
                ];
            }
        }

        // Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';
        usort($players, function($a, $b) use ($orderby, $order) {
            $value_a = $a[$orderby];
            $value_b = $b[$orderby];
            if ($orderby === 'events') {
                return $order === 'asc' ? $value_a - $value_b : $value_b - $value_a;
            }
            return $order === 'asc' ? strcmp($value_a, $value_b) : strcmp($value_b, $value_a);
        });

        // Pagination
        $offset = ($current_page - 1) * $per_page;
        return array_slice($players, $offset, $per_page);
    }

    private function get_players_count($filters) {
        $count = 0;
        $users = get_users(['role' => 'customer']);
        foreach ($users as $user) {
            $player_data = get_user_meta($user->ID, 'intersoccer_players', true);
            if (!$player_data || !is_array($player_data)) {
                continue;
            }
            $region = get_user_meta($user->ID, 'billing_region', true) ?: 'Unknown';
            foreach ($player_data as $player) {
                if ($filters['region'] && $region !== $filters['region']) {
                    continue;
                }
                if ($filters['age_group'] && $player['age_group'] !== $filters['age_group']) {
                    continue;
                }
                $count++;
            }
        }
        return $count;
    }

    private function is_date_past($end_date, $today) {
        try {
            $end = DateTime::createFromFormat('d/m/Y', $end_date);
            $today_date = DateTime::createFromFormat('Y-m-d', $today);
            return $end && $today_date && $end < $today_date;
        } catch (Exception $e) {
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

    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <select name="region">
                <option value=""><?php _e('All Regions', 'player-management'); ?></option>
                <?php
                $regions = [
                    'German-speaking (Zurich)',
                    'German-speaking (Bern)',
                    'German-speaking (Basel)',
                    'French-speaking (Geneva)',
                    'French-speaking (Lausanne)',
                    'French-speaking (Vaud)',
                    'English-speaking (Ticino)',
                    'German-speaking (Lucerne)',
                    'German-speaking (Fribourg)',
                    'French-speaking (Nyon)',
                    'French-speaking (Pully)',
                    'German-speaking (Etoy)'
                ];
                foreach ($regions as $region) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr($region),
                        selected(isset($_GET['region']) && $_GET['region'] === $region, true, false),
                        esc_html($region)
                    );
                }
                ?>
            </select>
            <select name="age_group">
                <option value=""><?php _e('All Age Groups', 'player-management'); ?></option>
                <?php
                $age_groups = ['Mini Soccer', 'Fun Footy', 'Soccer League'];
                foreach ($age_groups as $group) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr($group),
                        selected(isset($_GET['age_group']) && $_GET['age_group'] === $group, true, false),
                        esc_html($group)
                    );
                }
                ?>
            </select>
            <select name="event_type">
                <option value=""><?php _e('All Event Types', 'player-management'); ?></option>
                <?php
                $event_types = ['Camps', 'Courses', 'Birthdays'];
                foreach ($event_types as $type) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr($type),
                        selected(isset($_GET['event_type']) && $_GET['event_type'] === $type, true, false),
                        esc_html($type)
                    );
                }
                ?>
            </select>
            <input type="submit" class="button" value="<?php _e('Filter', 'player-management'); ?>">
        </div>
        <?php
    }
}

// Instantiate only if in admin
if (is_admin()) {
    new Player_Management_Admin();
}
?>