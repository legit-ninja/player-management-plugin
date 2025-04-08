<?php
/**
 * Player Trading Cards - Elementor Widget for Displaying Player Cards with Stats, Awards, and Profile Image
 */

// Register the Elementor widget
add_action('elementor/widgets/register', 'register_player_trading_cards_widget');
function register_player_trading_cards_widget($widgets_manager) {
    class Player_Trading_Cards_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'player_trading_cards';
        }

        public function get_title() {
            return __('Player Trading Cards', 'intersoccer-player-management');
        }

        public function get_icon() {
            return 'eicon-gallery-grid';
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
                'card_layout',
                [
                    'label' => __('Card Layout', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'grid' => __('Grid', 'intersoccer-player-management'),
                        'carousel' => __('Carousel', 'intersoccer-player-management'),
                    ],
                    'default' => 'grid',
                ]
            );

            $this->end_controls_section();

            // Style Tab: Card Styles
            $this->start_controls_section(
                'section_card_styles',
                [
                    'label' => __('Card Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'card_background_color',
                [
                    'label' => __('Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ffffff',
                    'selectors' => [
                        '{{WRAPPER}} .player-card' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'card_border_color',
                [
                    'label' => __('Border Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ddd',
                    'selectors' => [
                        '{{WRAPPER}} .player-card' => 'border-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'card_border_radius',
                [
                    'label' => __('Border Radius', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', '%'],
                    'default' => [
                        'top' => '10',
                        'right' => '10',
                        'bottom' => '10',
                        'left' => '10',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .player-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->add_control(
                'card_shadow',
                [
                    'label' => __('Box Shadow', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::BOX_SHADOW,
                    'default' => [
                        'horizontal' => 0,
                        'vertical' => 4,
                        'blur' => 8,
                        'spread' => 0,
                        'color' => 'rgba(0,0,0,0.1)',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .player-card' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}};',
                    ],
                ]
            );

            $this->add_control(
                'card_padding',
                [
                    'label' => __('Padding', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '20',
                        'right' => '20',
                        'bottom' => '20',
                        'left' => '20',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .player-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                'player_name_color',
                [
                    'label' => __('Player Name Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .player-card .player-name' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'player_name_typography',
                    'label' => __('Player Name Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .player-card .player-name',
                    'default' => [
                        'font_size' => '20px',
                        'font_weight' => 'bold',
                    ],
                ]
            );

            $this->add_control(
                'stats_color',
                [
                    'label' => __('Stats Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#666',
                    'selectors' => [
                        '{{WRAPPER}} .player-card .stats p' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'stats_typography',
                    'label' => __('Stats Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .player-card .stats p',
                    'default' => [
                        'font_size' => '14px',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Awards Styles
            $this->start_controls_section(
                'section_awards_styles',
                [
                    'label' => __('Awards Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'awards_background_color',
                [
                    'label' => __('Awards Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ffcc00',
                    'selectors' => [
                        '{{WRAPPER}} .player-card .awards .award' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'awards_text_color',
                [
                    'label' => __('Awards Text Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .player-card .awards .award' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'awards_typography',
                    'label' => __('Awards Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .player-card .awards .award',
                    'default' => [
                        'font_size' => '12px',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            if (!is_user_logged_in()) {
                echo '<p>' . __('Please log in to view your player trading cards.', 'intersoccer-player-management') . '</p>';
                return;
            }

            $user_id = get_current_user_id();
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();

            if (empty($players)) {
                echo '<p>' . __('No players found. Please add a player to view trading cards.', 'intersoccer-player-management') . '</p>';
                return;
            }

            $layout_class = $settings['card_layout'] === 'carousel' ? 'player-cards-carousel' : 'player-cards-grid';
            ?>
            <div class="player-cards <?php echo esc_attr($layout_class); ?>">
                <?php foreach ($players as $player): ?>
                    <?php
                    // Calculate stats
                    $events_attended = 0;
                    $matches_played = 0;
                    $events = tribe_get_events(array(
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                    ));
                    foreach ($events as $event) {
                        $attendees = tribe_tickets_get_attendees($event->ID);
                        foreach ($attendees as $attendee) {
                            $attendee_player_name = get_post_meta($attendee['ID'], 'player_name', true);
                            if ($attendee_player_name === $player['name']) {
                                $events_attended++;
                                $matches_played++; // Assuming each event is a match
                            }
                        }
                    }

                    $goals_scored = $player['goals_scored'] ?? 0;
                    $awards = $player['awards'] ?? array();
                    $photo_url = $player['profile_image'] ?? 'https://via.placeholder.com/150'; // Use profile_image, fallback to placeholder
                    ?>
                    <div class="player-card">
                        <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($player['name']); ?>" class="player-photo" />
                        <h3 class="player-name"><?php echo esc_html($player['name']); ?></h3>
                        <div class="stats">
                            <p><?php printf(__('Events Attended: %d', 'intersoccer-player-management'), $events_attended); ?></p>
                            <p><?php printf(__('Matches Played: %d', 'intersoccer-player-management'), $matches_played); ?></p>
                            <p><?php printf(__('Goals Scored: %d', 'intersoccer-player-management'), $goals_scored); ?></p>
                        </div>
                        <?php if (!empty($awards)): ?>
                            <div class="awards">
                                <?php
                                $award_labels = array(
                                    'most_improved' => __('Most Improved', 'intersoccer-player-management'),
                                    'top_scorer' => __('Top Scorer', 'intersoccer-player-management'),
                                    'best_defender' => __('Best Defender', 'intersoccer-player-management'),
                                    'team_player' => __('Team Player', 'intersoccer-player-management'),
                                );
                                foreach ($awards as $award): ?>
                                    <span class="award"><?php echo esc_html($award_labels[$award] ?? $award); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <style>
                .player-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                    gap: 20px;
                }
                .player-cards-carousel {
                    display: flex;
                    overflow-x: auto;
                    gap: 20px;
                }
                .player-card {
                    border: 1px solid #ddd;
                    border-radius: 10px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                    text-align: center;
                    transition: transform 0.2s;
                }
                .player-card:hover {
                    transform: scale(1.05);
                }
                .player-photo {
                    width: 100%;
                    height: 150px;
                    object-fit: cover;
                    border-top-left-radius: 10px;
                    border-top-right-radius: 10px;
                }
                .player-name {
                    margin: 10px 0;
                    font-size: 20px;
                    font-weight: bold;
                }
                .stats p {
                    margin: 5px 0;
                    font-size: 14px;
                }
                .awards {
                    margin-top: 10px;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 5px;
                }
                .awards .award {
                    background-color: #ffcc00;
                    color: #333;
                    padding: 5px 10px;
                    border-radius: 12px;
                    font-size: 12px;
                    display: inline-block;
                }
            </style>
            <?php
        }
    }

    $widgets_manager->register(new Player_Trading_Cards_Widget());
}
?>

