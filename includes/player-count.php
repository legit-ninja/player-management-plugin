<?php
/**
 * Player Count - Elementor Widget for Displaying the Total Number of Players
 */

// Register the Elementor widget
add_action('elementor/widgets/register', 'register_player_count_widget');
function register_player_count_widget($widgets_manager) {
    class Player_Count_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'player_count';
        }

        public function get_title() {
            return __('Player Count', 'intersoccer-player-management');
        }

        public function get_icon() {
            return 'eicon-number-field';
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
                'count_text',
                [
                    'label' => __('Count Text', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('You have %d players registered', 'intersoccer-player-management'),
                    'placeholder' => __('You have %d players registered', 'intersoccer-player-management'),
                    'description' => __('Use %d to represent the player count.', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'show_add_player_link',
                [
                    'label' => __('Show Add Player Link', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'add_player_link_text',
                [
                    'label' => __('Add Player Link Text', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Add a Player', 'intersoccer-player-management'),
                    'placeholder' => __('Add a Player', 'intersoccer-player-management'),
                    'condition' => [
                        'show_add_player_link' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'add_player_link_url',
                [
                    'label' => __('Add Player Link URL', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::URL,
                    'placeholder' => __('https://your-site.com/add-player', 'intersoccer-player-management'),
                    'default' => [
                        'url' => wc_get_account_endpoint_url('dashboard'), // Default to My Account page
                    ],
                    'condition' => [
                        'show_add_player_link' => 'yes',
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
                'count_text_color',
                [
                    'label' => __('Text Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .player-count' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'count_typography',
                    'label' => __('Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .player-count',
                    'default' => [
                        'font_size' => '18px',
                    ],
                ]
            );

            $this->add_control(
                'count_background_color',
                [
                    'label' => __('Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f9f9f9',
                    'selectors' => [
                        '{{WRAPPER}} .player-count' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'count_padding',
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
                        '{{WRAPPER}} .player-count' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Link Styles
            $this->start_controls_section(
                'section_link_styles',
                [
                    'label' => __('Link Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                    'condition' => [
                        'show_add_player_link' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'link_color',
                [
                    'label' => __('Link Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#007cba',
                    'selectors' => [
                        '{{WRAPPER}} .player-count a' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'link_typography',
                    'label' => __('Link Typography', 'intersoccer-player-management'),
                    'selector' => '{{WRAPPER}} .player-count a',
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
                echo '<p>' . __('Please log in to view your player count.', 'intersoccer-player-management') . '</p>';
                return;
            }

            $user_id = get_current_user_id();
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
            $player_count = count($players);
            $count_text = sprintf($settings['count_text'], $player_count);
            ?>
            <div class="player-count">
                <p><?php echo esc_html($count_text); ?></p>
                <?php if ($settings['show_add_player_link'] === 'yes' && !empty($settings['add_player_link_url']['url'])): ?>
                    <a href="<?php echo esc_url($settings['add_player_link_url']['url']); ?>">
                        <?php echo esc_html($settings['add_player_link_text']); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    $widgets_manager->register(new Player_Count_Widget());
}
?>

