<?php
/**
 * Upcoming Events - Elementor Widget for Displaying Upcoming Events a Player is Registered For
 */

// Register the Elementor widget
add_action('elementor/widgets/register', 'register_upcoming_events_widget');
function register_upcoming_events_widget($widgets_manager) {
    class Upcoming_Events_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'upcoming_events';
        }

        public function get_title() {
            return __('Upcoming Events', 'intersoccer-player-management');
        }

        public function get_icon() {
            return 'eicon-calendar';
        }

        public function get_categories() {
            return ['woocommerce'];
        }

        protected function register_controls() {
            // Content Tab: General Settings
            $this->start_controls_section(
                'section_general',
                [
                    'label' => __('General Settings', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'layout',
                [
                    'label' => __('Layout', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'list' => __('List', 'intersoccer-player-management'),
                        'calendar' => __('Calendar', 'intersoccer-player-management'),
                    ],
                    'default' => 'list',
                ]
            );

            $this->add_control(
                'show_player_filter',
                [
                    'label' => __('Show Player Filter', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->end_controls_section();

            // Style Tab: Event Styles
            $this->start_controls_section(
                'section_event_styles',
                [
                    'label' => __('Event Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'event_background_color',
                [
                    'label' => __('Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f9f9f9',
                    'selectors' => [
                        '{{WRAPPER}} .event-item' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'event_border_color',
                [
                    'label' => __('Border Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ddd',
                    'selectors' => [
                        '{{WRAPPER}} .event-item' => 'border-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'event_padding',
                [
                    'label' => __('Padding', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '10',
                        'right' => '10',
                        'bottom' => '10',
                        'left' => '10',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .event-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Text Styles
            $this->start_controls_section(
                'section_text_styles',
                [
                    'label' => __('Text Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'event_title_color',
                [
                    'label' => __('Event Title Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .event-item .event-title' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'event_title_typography',
                    'label' => __('Event Title Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .event-item .event-title',
                    'default' => [
                        'font_size' => '18px',
                        'font_weight' => 'bold',
                    ],
                ]
            );

            $this->add_control(
                'event_details_color',
                [
                    'label' => __('Event Details Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#666',
                    'selectors' => [
                        '{{WRAPPER}} .event-item .event-details' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'event_details_typography',
                    'label' => __('Event Details Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .event-item .event-details',
                    'default' => [
                        'font_size' => '14px',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            if (!is_user_logged_in()) {
                echo '<p>' . __('Please log in to view upcoming events.', 'intersoccer-player-management') . '</p>';
                return;
            }

            $user_id = get_current_user_id();
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();

            if (empty($players)) {
                echo '<p>' . __('No players found. Please add a player to view upcoming events.', 'intersoccer-player-management') . '</p>';
                return;
            }

            // Fetch upcoming events
            $current_date = current_time('Y-m-d H:i:s');
            $events = tribe_get_events(array(
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => '_EventStartDate',
                        'value' => $current_date,
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ),
                ),
                'orderby' => 'meta_value',
                'meta_key' => '_EventStartDate',
                'order' => 'ASC',
            ));

            $player_events = array();
            foreach ($players as $player) {
                $player_name = $player['name'];
                $player_events[$player_name] = array();
                foreach ($events as $event) {
                    $attendees = tribe_tickets_get_attendees($event->ID);
                    foreach ($attendees as $attendee) {
                        $attendee_player_name = get_post_meta($attendee['ID'], 'player_name', true);
                        if ($attendee_player_name === $player_name) {
                            $player_events[$player_name][] = $event;
                            break;
                        }
                    }
                }
            }

            // Filter by selected player if applicable
            $selected_player = isset($_GET['player_filter']) ? sanitize_text_field($_GET['player_filter']) : '';
            if ($selected_player && !array_key_exists($selected_player, $player_events)) {
                $selected_player = '';
            }

            ?>
            <div class="upcoming-events">
                <?php if ($settings['show_player_filter'] === 'yes' && count($players) > 1): ?>
                    <form method="get" class="player-filter">
                        <label for="player_filter"><?php _e('Filter by Player:', 'intersoccer-player-management'); ?></label>
                        <select name="player_filter" id="player_filter" onchange="this.form.submit()">
                            <option value=""><?php _e('All Players', 'intersoccer-player-management'); ?></option>
                            <?php foreach ($players as $player): ?>
                                <option value="<?php echo esc_attr($player['name']); ?>" <?php selected($selected_player, $player['name']); ?>>
                                    <?php echo esc_html($player['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>

                <?php
                $layout_class = $settings['layout'] === 'calendar' ? 'events-calendar' : 'events-list';
                ?>
                <div class="<?php echo esc_attr($layout_class); ?>">
                    <?php
                    $displayed_events = array();
                    foreach ($player_events as $player_name => $events) {
                        if ($selected_player && $selected_player !== $player_name) {
                            continue;
                        }

                        foreach ($events as $event) {
                            if (in_array($event->ID, $displayed_events)) {
                                continue;
                            }
                            $displayed_events[] = $event->ID;

                            $start_date = get_post_meta($event->ID, '_EventStartDate', true);
                            $venue = get_post_meta($event->ID, '_EventVenue', true);
                            ?>
                            <div class="event-item">
                                <h3 class="event-title"><?php echo esc_html($event->post_title); ?></h3>
                                <p class="event-details"><?php _e('Date:', 'intersoccer-player-management'); ?> <?php echo esc_html(date('F j, Y, g:i a', strtotime($start_date))); ?></p>
                                <p class="event-details"><?php _e('Location:', 'intersoccer-player-management'); ?> <?php echo esc_html($venue ?: 'TBD'); ?></p>
                                <p class="event-details"><?php _e('Player:', 'intersoccer-player-management'); ?> <?php echo esc_html($player_name); ?></p>
                            </div>
                            <?php
                        }
                    }

                    if (empty($displayed_events)) {
                        echo '<p>' . __('No upcoming events found for the selected player(s).', 'intersoccer-player-management') . '</p>';
                    }
                    ?>
                </div>
            </div>
            <style>
                .player-filter {
                    margin-bottom: 20px;
                }
                .player-filter label {
                    margin-right: 10px;
                }
                .player-filter select {
                    padding: 5px;
                    border-radius: 4px;
                    border: 1px solid #ccc;
                }
                .events-list .event-item {
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    margin-bottom: 10px;
                }
                .events-calendar {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 10px;
                }
                .event-item .event-title {
                    margin: 0 0 10px 0;
                }
                .event-item .event-details {
                    margin: 5px 0;
                }
            </style>
            <?php
        }
    }

    $widgets_manager->register(new Upcoming_Events_Widget());
}
?>

