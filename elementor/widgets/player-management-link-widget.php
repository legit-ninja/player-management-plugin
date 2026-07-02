<?php
/**
 * File: elementor/widgets/player-management-link-widget.php
 * InterSoccer Player Management Link Widget
 * 
 * Elementor widget that displays a link/icon to the Player Management page
 * with optional Shepherd.js tour for users without registered players
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class InterSoccer_Player_Management_Link_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'intersoccer_player_management_link';
    }

    public function get_title() {
        return esc_html__('Player Management Link', 'player-management');
    }

    public function get_icon() {
        return 'eicon-user-circle-o';
    }

    public function get_categories() {
        return ['intersoccer-player'];
    }

    public function get_keywords() {
        return ['intersoccer', 'player', 'management', 'attendee', 'registration'];
    }
    
    public function get_style_depends() {
        return ['intersoccer-player-widget'];
    }
    
    public function _register_controls() {
        $this->register_controls();
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content Settings', 'player-management'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'display_type',
            [
                'label' => esc_html__('Display Type', 'player-management'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'icon_text' => esc_html__('Icon + Text', 'player-management'),
                    'icon_only' => esc_html__('Icon Only', 'player-management'),
                    'text_only' => esc_html__('Text Only', 'player-management'),
                ],
                'default' => 'icon_text',
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => esc_html__('Icon', 'player-management'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'eicon-user-circle-o',
                    'library' => 'eicons',
                ],
                'condition' => [
                    'display_type!' => 'text_only',
                ],
            ]
        );

        $this->add_control(
            'link_text',
            [
                'label' => esc_html__('Link Text', 'player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Manage Players', 'player-management'),
                'condition' => [
                    'display_type!' => 'icon_only',
                ],
            ]
        );

        $this->add_control(
            'show_tour',
            [
                'label' => esc_html__('Enable Shepherd.js Tour', 'player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'player-management'),
                'label_off' => esc_html__('No', 'player-management'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => esc_html__('Show guided tour for users without registered players', 'player-management'),
            ]
        );

        $this->add_control(
            'tour_auto_start',
            [
                'label' => esc_html__('Auto-start Tour', 'player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'player-management'),
                'label_off' => esc_html__('No', 'player-management'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'show_tour' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'tour_delay',
            [
                'label' => esc_html__('Tour Delay (seconds)', 'player-management'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 10,
                'step' => 0.5,
                'default' => 2,
                'condition' => [
                    'show_tour' => 'yes',
                    'tour_auto_start' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Style', 'player-management'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_size',
            [
                'label' => esc_html__('Icon Size', 'player-management'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'em' => [
                        'min' => 0.5,
                        'max' => 5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-link__icon' => 'font-size: {{SIZE}}{{UNIT}} !important; width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon svg' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon i' => 'font-size: {{SIZE}}{{UNIT}} !important;',
                ],
                'condition' => [
                    'display_type!' => 'text_only',
                ],
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => esc_html__('Icon Color', 'player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-link__icon' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon svg' => 'fill: {{VALUE}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon svg path' => 'fill: {{VALUE}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon svg *' => 'fill: {{VALUE}} !important;',
                    '{{WRAPPER}} .intersoccer-player-link__icon i' => 'color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'display_type!' => 'text_only',
                ],
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => esc_html__('Text Color', 'player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-link__text' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'display_type!' => 'text_only',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'selector' => '{{WRAPPER}} .intersoccer-player-link__text',
                'condition' => [
                    'display_type!' => 'icon_only',
                ],
            ]
        );

        $this->add_control(
            'link_alignment',
            [
                'label' => esc_html__('Alignment', 'player-management'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'player-management'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'player-management'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'player-management'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-link' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'spacing',
            [
                'label' => esc_html__('Icon-Text Spacing', 'player-management'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-link__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'display_type' => 'icon_text',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Check if we're in Elementor editor
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        
        // In editor, always show preview; on frontend, only show for logged-in users
        if (!$is_editor && !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Get the Player Management URL
        $manage_players_url = $this->get_manage_players_url();
        
        // Check if user has players (only on frontend, not in editor)
        $has_players = false;
        if (!$is_editor && function_exists('intersoccer_user_has_players')) {
            $has_players = intersoccer_user_has_players($user_id);
        }
        
        // Determine if tour should be shown (never in editor)
        $show_tour = !$is_editor && $settings['show_tour'] === 'yes' && !$has_players;
        $tour_auto_start = $settings['tour_auto_start'] === 'yes';
        $tour_delay = floatval($settings['tour_delay'] ?? 2);
        
        // Add data attributes for Shepherd.js targeting
        $data_attrs = '';
        if ($show_tour) {
            $data_attrs = sprintf(
                'data-shepherd-target="yes" data-tour-auto-start="%s" data-tour-delay="%s"',
                $tour_auto_start ? 'yes' : 'no',
                esc_attr($tour_delay)
            );
        }
        
        // Determine display type
        $display_type = $settings['display_type'] ?? 'icon_text';
        
        ?>
        <div class="intersoccer-player-link" <?php echo $data_attrs; ?>>
            <a href="<?php echo esc_url($manage_players_url); ?>" class="intersoccer-player-link__anchor">
                <?php if ($display_type !== 'text_only' && !empty($settings['icon']['value'])): ?>
                    <span class="intersoccer-player-link__icon">
                        <?php
                        if (class_exists('\Elementor\Icons_Manager')) {
                            \Elementor\Icons_Manager::render_icon(
                                $settings['icon'],
                                ['aria-hidden' => 'true']
                            );
                        } else {
                            printf(
                                '<i class="%s" aria-hidden="true"></i>',
                                esc_attr($settings['icon']['value'])
                            );
                        }
                        ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($display_type !== 'icon_only' && !empty($settings['link_text'])): ?>
                    <span class="intersoccer-player-link__text">
                        <?php echo esc_html($settings['link_text']); ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
        <?php
        
        // Enqueue Shepherd.js and tour script if tour is enabled (never in editor)
        if ($show_tour) {
            $this->enqueue_tour_assets($tour_auto_start, $tour_delay);
        }
        
        // Always enqueue widget styles (needed for editor and frontend)
        wp_enqueue_style(
            'intersoccer-player-widget',
            PLAYER_MANAGEMENT_URL . 'css/player-management-widget.css',
            [],
            PLAYER_MANAGEMENT_VERSION
        );
    }
    
    /**
     * Render widget output in the editor for live preview
     */
    protected function content_template() {
        ?>
        <#
        var displayType = settings.display_type || 'icon_text';
        var iconHTML = '';
        var textHTML = '';
        
        if (displayType !== 'text_only' && settings.icon && settings.icon.value) {
            var iconElement = elementor.helpers.renderIcon(view, settings.icon, { 'aria-hidden': true }, 'i', 'object' );
            if (iconElement.rendered) {
                iconHTML = '<span class="intersoccer-player-link__icon">' + iconElement.value + '</span>';
            }
        }
        
        if (displayType !== 'icon_only' && settings.link_text) {
            textHTML = '<span class="intersoccer-player-link__text">' + settings.link_text + '</span>';
        }
        #>
        <div class="intersoccer-player-link">
            <a href="#" class="intersoccer-player-link__anchor">
                {{{ iconHTML }}}
                {{{ textHTML }}}
            </a>
        </div>
        <?php
    }

    /**
     * Get the Player Management URL with WPML support
     */
    private function get_manage_players_url() {
        // Get current language from WPML
        $current_lang = apply_filters('wpml_current_language', null);
        
        // Get translated slug for current language
        $endpoint_slug = apply_filters('wpml_translate_single_string', 'manage-players', 'WordPress', 'URL manage-players slug');
        
        // Manual fallback for known translations
        if ($endpoint_slug === 'manage-players' && $current_lang) {
            $manual_translations = [
                'fr' => 'gerer-participants',
                'de' => 'teilnehmer-verwalten',
                'en' => 'manage-players',
            ];
            if (isset($manual_translations[$current_lang])) {
                $endpoint_slug = $manual_translations[$current_lang];
            }
        }
        
        // Use WooCommerce function if available
        if (function_exists('wc_get_account_endpoint_url')) {
            return wc_get_account_endpoint_url($endpoint_slug);
        }
        
        // Fallback to manual URL construction
        $account_url = wc_get_page_permalink('myaccount');
        if (!$account_url) {
            $account_url = home_url('/my-account/');
        }
        
        return trailingslashit($account_url) . $endpoint_slug . '/';
    }

    /**
     * Enqueue Shepherd.js and tour script
     */
    private function enqueue_tour_assets($auto_start, $delay) {
        // Enqueue Shepherd.js CSS
        wp_enqueue_style(
            'shepherd-js',
            'https://cdn.jsdelivr.net/npm/shepherd.js@latest/dist/css/shepherd.css',
            [],
            null
        );
        
        // Enqueue Shepherd.js JS
        wp_enqueue_script(
            'shepherd-js',
            'https://cdn.jsdelivr.net/npm/shepherd.js@latest/dist/js/shepherd.min.js',
            [],
            null,
            true
        );
        
        // Enqueue tour script
        wp_enqueue_script(
            'intersoccer-player-tour',
            PLAYER_MANAGEMENT_URL . 'js/shepherd-tour.js',
            ['shepherd-js', 'jquery'],
            PLAYER_MANAGEMENT_VERSION,
            true
        );
        
        // Enqueue widget styles
        wp_enqueue_style(
            'intersoccer-player-widget',
            PLAYER_MANAGEMENT_URL . 'css/player-management-widget.css',
            [],
            PLAYER_MANAGEMENT_VERSION
        );
        
        // Localize script with tour settings
        wp_localize_script('intersoccer-player-tour', 'intersoccerPlayerTour', [
            'autoStart' => $auto_start,
            'delay' => $delay,
            'hasPlayers' => function_exists('intersoccer_user_has_players') ? intersoccer_user_has_players() : false,
            'managePlayersUrl' => $this->get_manage_players_url(),
            'i18n' => [
                'tourTitle' => __('Register Your Players', 'player-management'),
                'tourIntro' => __('Before you can register for camps and courses, you need to add at least one player (attendee) to your account.', 'player-management'),
                'tourHighlight' => __('Click here to manage your players and add your first attendee.', 'player-management'),
                'tourAction' => __('Click this link to get started!', 'player-management'),
                'tourDismiss' => __('Don\'t show again', 'player-management'),
                'tourNext' => __('Next', 'player-management'),
                'tourBack' => __('Back', 'player-management'),
                'tourComplete' => __('Got it!', 'player-management'),
            ],
        ]);
    }
}


