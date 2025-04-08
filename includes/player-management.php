<?php
/**
 * Player Management - Enhanced Elementor Widget with Fixed Button Alignments, Title Customization, Stats/Awards, and Profile Image
 */

// Register the Elementor widget
add_action('elementor/widgets/register', 'register_player_management_widget');
function register_player_management_widget($widgets_manager) {
    class Player_Management_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'player_management';
        }

        public function get_title() {
            return __('Player Management Form', 'intersoccer-player-management');
        }

        public function get_icon() {
            return 'eicon-person';
        }

        public function get_categories() {
            return ['woocommerce'];
        }

        protected function register_controls() {
            // Content Tab: General Settings
            $this->start_controls_section(
                'section_general_settings',
                [
                    'label' => __('General Settings', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'form_title',
                [
                    'label' => __('Form Title', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Manage Players', 'intersoccer-player-management'),
                    'placeholder' => __('Manage Players', 'intersoccer-player-management'),
                ]
            );

            $this->end_controls_section();

            // Content Tab: Form Settings
            $this->start_controls_section(
                'section_form_settings',
                [
                    'label' => __('Form Settings', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'max_players',
                [
                    'label' => __('Maximum Players', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::NUMBER,
                    'default' => 10,
                    'min' => 1,
                    'description' => __('Set the maximum number of players a user can add.', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'success_message',
                [
                    'label' => __('Success Message', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Players updated successfully!', 'intersoccer-player-management'),
                    'placeholder' => __('Players updated successfully!', 'intersoccer-player-management'),
                ]
            );

            $this->end_controls_section();

            // Content Tab: Field Visibility
            $this->start_controls_section(
                'section_field_visibility',
                [
                    'label' => __('Field Visibility', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'show_medical_conditions',
                [
                    'label' => __('Show Medical Conditions Field', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'show_consent_file',
                [
                    'label' => __('Show Consent File Field', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'show_profile_image',
                [
                    'label' => __('Show Profile Image Field', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'show_goals_scored',
                [
                    'label' => __('Show Goals Scored Field', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'show_awards',
                [
                    'label' => __('Show Awards Field', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->end_controls_section();

            // Content Tab: Required Fields
            $this->start_controls_section(
                'section_required_fields',
                [
                    'label' => __('Required Fields', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'require_name',
                [
                    'label' => __('Require Player Name', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Yes', 'intersoccer-player-management'),
                    'label_off' => __('No', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'require_dob',
                [
                    'label' => __('Require Date of Birth', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Yes', 'intersoccer-player-management'),
                    'label_off' => __('No', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'require_medical_conditions',
                [
                    'label' => __('Require Medical Conditions (if shown)', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Yes', 'intersoccer-player-management'),
                    'label_off' => __('No', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                    'condition' => [
                        'show_medical_conditions' => 'yes',
                    ],
                ]
            );

            $this->end_controls_section();

            // Content Tab: Default Values
            $this->start_controls_section(
                'section_default_values',
                [
                    'label' => __('Default Values', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'default_medical_conditions',
                [
                    'label' => __('Default Medical Conditions', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('No known medical conditions', 'intersoccer-player-management'),
                    'placeholder' => __('No known medical conditions', 'intersoccer-player-management'),
                    'condition' => [
                        'show_medical_conditions' => 'yes',
                    ],
                ]
            );

            $this->end_controls_section();

            // Content Tab: Button Texts
            $this->start_controls_section(
                'section_button_texts',
                [
                    'label' => __('Button Texts', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'add_player_button_text',
                [
                    'label' => __('Add Player Button Text', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Add Another Player', 'intersoccer-player-management'),
                    'placeholder' => __('Add Another Player', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'remove_player_button_text',
                [
                    'label' => __('Remove Player Button Text', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Remove', 'intersoccer-player-management'),
                    'placeholder' => __('Remove', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'save_players_button_text',
                [
                    'label' => __('Save Players Button Text', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Save Players', 'intersoccer-player-management'),
                    'placeholder' => __('Save Players', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'show_add_player_button',
                [
                    'label' => __('Show Add Player Button', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->add_control(
                'show_remove_player_button',
                [
                    'label' => __('Show Remove Player Button', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('Show', 'intersoccer-player-management'),
                    'label_off' => __('Hide', 'intersoccer-player-management'),
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );

            $this->end_controls_section();

            // Content Tab: Field Labels
            $this->start_controls_section(
                'section_field_labels',
                [
                    'label' => __('Field Labels', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'player_name_label',
                [
                    'label' => __('Player Name Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Player Name', 'intersoccer-player-management'),
                    'placeholder' => __('Player Name', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'dob_label',
                [
                    'label' => __('Date of Birth Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Date of Birth', 'intersoccer-player-management'),
                    'placeholder' => __('Date of Birth', 'intersoccer-player-management'),
                ]
            );

            $this->add_control(
                'has_medical_conditions_label',
                [
                    'label' => __('Has Medical Conditions Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Has Known Medical Conditions?', 'intersoccer-player-management'),
                    'placeholder' => __('Has Known Medical Conditions?', 'intersoccer-player-management'),
                    'condition' => [
                        'show_medical_conditions' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'medical_conditions_label',
                [
                    'label' => __('Medical Conditions Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Medical Conditions', 'intersoccer-player-management'),
                    'placeholder' => __('Medical Conditions', 'intersoccer-player-management'),
                    'condition' => [
                        'show_medical_conditions' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'medical_consent_label',
                [
                    'label' => __('Medical Consent Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Medical Consent Form (PDF/Image)', 'intersoccer-player-management'),
                    'placeholder' => __('Medical Consent Form (PDF/Image)', 'intersoccer-player-management'),
                    'condition' => [
                        'show_consent_file' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'profile_image_label',
                [
                    'label' => __('Profile Image Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Player Profile Image (JPG/PNG)', 'intersoccer-player-management'),
                    'placeholder' => __('Player Profile Image (JPG/PNG)', 'intersoccer-player-management'),
                    'condition' => [
                        'show_profile_image' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'goals_scored_label',
                [
                    'label' => __('Goals Scored Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Goals Scored', 'intersoccer-player-management'),
                    'placeholder' => __('Goals Scored', 'intersoccer-player-management'),
                    'condition' => [
                        'show_goals_scored' => 'yes',
                    ],
                ]
            );

            $this->add_control(
                'awards_label',
                [
                    'label' => __('Awards Label', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Awards', 'intersoccer-player-management'),
                    'placeholder' => __('Awards', 'intersoccer-player-management'),
                    'condition' => [
                        'show_awards' => 'yes',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Form Styles
            $this->start_controls_section(
                'section_form_styles',
                [
                    'label' => __('Form Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'form_alignment',
                [
                    'label' => __('Form Alignment', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::CHOOSE,
                    'options' => [
                        'left' => [
                            'title' => __('Left', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-left',
                        ],
                        'center' => [
                            'title' => __('Center', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-center',
                        ],
                        'right' => [
                            'title' => __('Right', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-right',
                        ],
                    ],
                    'default' => 'left',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry' => 'text-align: {{VALUE}};',
                        '{{WRAPPER}} .player-entry label' => 'display: inline-block; text-align: {{VALUE}};',
                        '{{WRAPPER}} .player-entry input, {{WRAPPER}} .player-entry textarea' => 'display: inline-block; width: 100%; max-width: 300px;',
                    ],
                ]
            );

            $this->add_control(
                'form_background_color',
                [
                    'label' => __('Form Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f9f9f9',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'form_border_color',
                [
                    'label' => __('Form Border Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ddd',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry' => 'border-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'form_padding',
                [
                    'label' => __('Form Padding', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '15',
                        'right' => '15',
                        'bottom' => '15',
                        'left' => '15',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .player-entry' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Text and Field Styles
            $this->start_controls_section(
                'section_text_styles',
                [
                    'label' => __('Text and Field Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'label_text_color',
                [
                    'label' => __('Label Text Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry label' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'field_text_color',
                [
                    'label' => __('Field Text Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry input, {{WRAPPER}} .player-entry textarea' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'error_text_color',
                [
                    'label' => __('Error Text Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ff0000',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry .error-message' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'field_background_color',
                [
                    'label' => __('Field Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ffffff',
                    'selectors' => [
                        '{{WRAPPER}} .player-entry input, {{WRAPPER}} .player-entry textarea' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Tab: Button Styles
            $this->start_controls_section(
                'section_button_styles',
                [
                    'label' => __('Button Styles', 'intersoccer-player-management'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'add_button_background_color',
                [
                    'label' => __('Add Button Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#007cba',
                    'selectors' => [
                        '{{WRAPPER}} #add-player' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'add_button_alignment',
                [
                    'label' => __('Add Button Alignment', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::CHOOSE,
                    'options' => [
                        'left' => [
                            'title' => __('Left', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-left',
                        ],
                        'center' => [
                            'title' => __('Center', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-center',
                        ],
                        'right' => [
                            'title' => __('Right', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-right',
                        ],
                    ],
                    'default' => 'left',
                    'selectors' => [
                        '{{WRAPPER}} #add-player' => 'display: block; width: auto; text-align: {{VALUE}}; margin: 0 auto; float: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'add_button_spacing',
                [
                    'label' => __('Add Button Spacing', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '10',
                        'right' => '0',
                        'bottom' => '10',
                        'left' => '0',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} #add-player' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->add_control(
                'remove_button_background_color',
                [
                    'label' => __('Remove Button Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#dc3545',
                    'selectors' => [
                        '{{WRAPPER}} .remove-player' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'remove_button_alignment',
                [
                    'label' => __('Remove Button Alignment', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::CHOOSE,
                    'options' => [
                        'left' => [
                            'title' => __('Left', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-left',
                        ],
                        'center' => [
                            'title' => __('Center', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-center',
                        ],
                        'right' => [
                            'title' => __('Right', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-right',
                        ],
                    ],
                    'default' => 'left',
                    'selectors' => [
                        '{{WRAPPER}} .remove-player' => 'display: block; width: auto; text-align: {{VALUE}}; margin: 0 auto; float: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'remove_button_spacing',
                [
                    'label' => __('Remove Button Spacing', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '0',
                        'right' => '0',
                        'bottom' => '0',
                        'left' => '0',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .remove-player' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->add_control(
                'save_button_background_color',
                [
                    'label' => __('Save Button Background Color', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#28a745',
                    'selectors' => [
                        '{{WRAPPER}} input[name="save_players"]' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'save_button_alignment',
                [
                    'label' => __('Save Button Alignment', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::CHOOSE,
                    'options' => [
                        'left' => [
                            'title' => __('Left', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-left',
                        ],
                        'center' => [
                            'title' => __('Center', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-center',
                        ],
                        'right' => [
                            'title' => __('Right', 'intersoccer-player-management'),
                            'icon' => 'eicon-text-align-right',
                        ],
                    ],
                    'default' => 'left',
                    'selectors' => [
                        '{{WRAPPER}} input[name="save_players"]' => 'display: block; width: auto; text-align: {{VALUE}}; margin: 0 auto; float: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'save_button_spacing',
                [
                    'label' => __('Save Button Spacing', 'intersoccer-player-management'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '0',
                        'right' => '0',
                        'bottom' => '0',
                        'left' => '0',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} input[name="save_players"]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            // Pass the settings to the display_players_form function
            display_players_form($settings);
        }
    }

    $widgets_manager->register(new Player_Management_Widget());
}

// The display_players_form function, updated to include profile_image
function display_players_form($settings = []) {
    if (!is_user_logged_in()) {
        wc_add_notice(__('Please log in to manage players.', 'intersoccer-player-management'), 'error');
        return;
    }

    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
    $max_players = isset($settings['max_players']) ? (int) $settings['max_players'] : 10;
    $success_message = $settings['success_message'] ?? __('Players updated successfully!', 'intersoccer-player-management');

    // Handle form submission
    if (isset($_POST['save_players']) && !empty($_POST['players_nonce']) && wp_verify_nonce($_POST['players_nonce'], 'save_players_action')) {
        $new_players = array();
        $names = $_POST['player_name'] ?? array();
        $dobs = $_POST['player_dob'] ?? array();
        $has_conditions = $_POST['has_medical_conditions'] ?? array();
        $conditions = $_POST['medical_conditions'] ?? array();
        $consents = $_FILES['medical_consent'] ?? array();
        $profile_images = $_FILES['profile_image'] ?? array();
        $goals_scored = $_POST['goals_scored'] ?? array();
        $awards = $_POST['awards'] ?? array();
        $existing_names = array_column($players, 'name');

        // Process only non-empty entries
        for ($i = 0; $i < count($names) && count($new_players) < $max_players; $i++) {
            $name = isset($names[$i]) ? sanitize_text_field($names[$i]) : '';
            $dob = isset($dobs[$i]) ? sanitize_text_field($dobs[$i]) : '';

            // Skip completely empty entries
            if (empty($name) && empty($dob)) {
                continue;
            }

            // Validate name
            if (empty($name) && ($settings['require_name'] ?? 'yes') === 'yes') {
                wc_add_notice(__('A player name is empty.', 'intersoccer-player-management'), 'error');
                continue;
            }

            // Check for duplicates, but allow the same name if it's an existing player being edited
            if (!empty($name) && in_array($name, $existing_names) && $name !== ($players[$i]['name'] ?? '')) {
                wc_add_notice(sprintf(__('Player name "%s" already exists.', 'intersoccer-player-management'), $name), 'error');
                continue;
            }

            // Validate DOB
            if (empty($dob) && ($settings['require_dob'] ?? 'yes') === 'yes') {
                wc_add_notice(sprintf(__('Date of birth for %s is required.', 'intersoccer-player-management'), $name), 'error');
                continue;
            }

            if (!empty($dob)) {
                $age = date_diff(date_create($dob), date_create('today'))->y;
                if ($age < 4 || $age > 18) {
                    wc_add_notice(sprintf(__('Player %s must be between 4 and 18 years old.', 'intersoccer-player-management'), $name), 'error');
                    continue;
                }
            }

            // Handle medical conditions
            $condition = !empty($has_conditions[$i]) && !empty($conditions[$i]) ? sanitize_textarea_field($conditions[$i]) : ($settings['default_medical_conditions'] ?? 'No known medical conditions');
            if (($settings['show_medical_conditions'] ?? 'yes') === 'yes' && !empty($has_conditions[$i]) && empty($conditions[$i]) && ($settings['require_medical_conditions'] ?? 'yes') === 'yes') {
                wc_add_notice(sprintf(__('Medical conditions for %s are required if checked.', 'intersoccer-player-management'), $name), 'error');
                continue;
            }

            // Handle consent file upload
            $consent_url = $players[$i]['consent_url'] ?? '';
            if (($settings['show_consent_file'] ?? 'yes') === 'yes' && !empty($consents['name'][$i]) && $consents['error'][$i] == 0) {
                $file = array(
                    'name' => $consents['name'][$i],
                    'type' => $consents['type'][$i],
                    'tmp_name' => $consents['tmp_name'][$i],
                    'error' => $consents['error'][$i],
                    'size' => $consents['size'][$i]
                );
                $upload_overrides = array('test_form' => false);
                $upload = wp_handle_upload($file, $upload_overrides);
                if (isset($upload['error'])) {
                    wc_add_notice(sprintf(__('Failed to upload consent file for %s: %s', 'intersoccer-player-management'), $name, $upload['error']), 'error');
                    continue;
                }
                $consent_url = $upload['url'];
            }

            // Handle profile image upload
            $profile_image_url = $players[$i]['profile_image'] ?? '';
            if (($settings['show_profile_image'] ?? 'yes') === 'yes' && !empty($profile_images['name'][$i]) && $profile_images['error'][$i] == 0) {
                $file = array(
                    'name' => $profile_images['name'][$i],
                    'type' => $profile_images['type'][$i],
                    'tmp_name' => $profile_images['tmp_name'][$i],
                    'error' => $profile_images['error'][$i],
                    'size' => $profile_images['size'][$i]
                );
                $upload_overrides = array('test_form' => false);
                $upload = wp_handle_upload($file, $upload_overrides);
                if (isset($upload['error'])) {
                    wc_add_notice(sprintf(__('Failed to upload profile image for %s: %s', 'intersoccer-player-management'), $name, $upload['error']), 'error');
                    continue;
                }
                $profile_image_url = $upload['url'];
            }

            // Handle goals scored and awards
            $goals = isset($goals_scored[$i]) ? absint($goals_scored[$i]) : ($players[$i]['goals_scored'] ?? 0);
            $player_awards = isset($awards[$i]) ? array_map('sanitize_text_field', (array) $awards[$i]) : ($players[$i]['awards'] ?? array());

            $new_players[] = array(
                'name' => $name,
                'dob' => $dob,
                'medical_conditions' => $condition,
                'consent_url' => $consent_url,
                'profile_image' => $profile_image_url,
                'goals_scored' => $goals,
                'awards' => $player_awards,
            );
        }

        if (!empty($new_players)) {
            $result = update_user_meta($user_id, 'intersoccer_players', $new_players);
            if ($result) {
                wc_add_notice($success_message, 'success');
                $players = $new_players;
            } else {
                wc_add_notice(__('Failed to save players. Please try again.', 'intersoccer-player-management'), 'error');
                error_log('Failed to update user meta for user ' . $user_id);
            }
        } else {
            wc_add_notice(__('No valid players to save.', 'intersoccer-player-management'), 'error');
        }
    }
    ?>
    <h2><?php echo esc_html($settings['form_title'] ?? __('Manage Players', 'intersoccer-player-management')); ?></h2>
    <p><?php _e('Add your children as Players for Summer Football Camps.', 'intersoccer-player-management'); ?></p>
    <form method="post" id="manage-players-form" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('save_players_action', 'players_nonce'); ?>
        <div id="players-list">
            <?php foreach ($players as $i => $player): ?>
                <div class="player-entry">
                    <label style="display: block; margin-bottom: 10px;">
                        <?php echo esc_html($settings['player_name_label'] ?? __('Player Name', 'intersoccer-player-management')); ?>
                        <input type="text" name="player_name[<?php echo $i; ?>]" value="<?php echo esc_attr($player['name']); ?>" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Player name is required.', 'intersoccer-player-management'); ?></span>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <?php echo esc_html($settings['dob_label'] ?? __('Date of Birth', 'intersoccer-player-management')); ?>
                        <input type="date" name="player_dob[<?php echo $i; ?>]" value="<?php echo esc_attr($player['dob']); ?>" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Date of birth is required.', 'intersoccer-player-management'); ?></span>
                    </label>
                    <?php if ($settings['show_medical_conditions'] === 'yes'): ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="has_medical_conditions[<?php echo $i; ?>]" class="has-medical-conditions" <?php checked($player['medical_conditions'] !== ($settings['default_medical_conditions'] ?? 'No known medical conditions')); ?> />
                            <?php echo esc_html($settings['has_medical_conditions_label'] ?? __('Has Known Medical Conditions?', 'intersoccer-player-management')); ?>
                        </label>
                        <div class="medical-conditions" style="display: <?php echo $player['medical_conditions'] !== ($settings['default_medical_conditions'] ?? 'No known medical conditions') ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                            <label style="display: block; margin-bottom: 5px;">
                                <?php echo esc_html($settings['medical_conditions_label'] ?? __('Medical Conditions', 'intersoccer-player-management')); ?>
                                <textarea name="medical_conditions[<?php echo $i; ?>]" style="width: 100%; max-width: 300px; height: 100px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo esc_textarea($player['medical_conditions'] !== ($settings['default_medical_conditions'] ?? 'No known medical conditions') ? $player['medical_conditions'] : ''); ?></textarea>
                                <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Medical conditions are required if checked.', 'intersoccer-player-management'); ?></span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <?php if ($settings['show_consent_file'] === 'yes'): ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <?php echo esc_html($settings['medical_consent_label'] ?? __('Medical Consent Form (PDF/Image)', 'intersoccer-player-management')); ?>
                            <input type="file" name="medical_consent[<?php echo $i; ?>]" accept=".pdf,.jpg,.png" style="width: 100%; max-width: 300px;" />
                            <?php if (!empty($player['consent_url'])): ?>
                                <a href="<?php echo esc_url($player['consent_url']); ?>" target="_blank" style="display: block; margin-top: 5px;"><?php _e('View Current Consent', 'intersoccer-player-management'); ?></a>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                    <?php if ($settings['show_profile_image'] === 'yes'): ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <?php echo esc_html($settings['profile_image_label'] ?? __('Player Profile Image (JPG/PNG)', 'intersoccer-player-management')); ?>
                            <input type="file" name="profile_image[<?php echo $i; ?>]" accept=".jpg,.png" style="width: 100%; max-width: 300px;" />
                            <?php if (!empty($player['profile_image'])): ?>
                                <a href="<?php echo esc_url($player['profile_image']); ?>" target="_blank" style="display: block; margin-top: 5px;"><?php _e('View Current Profile Image', 'intersoccer-player-management'); ?></a>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                    <?php if ($settings['show_goals_scored'] === 'yes'): ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <?php echo esc_html($settings['goals_scored_label'] ?? __('Goals Scored', 'intersoccer-player-management')); ?>
                            <input type="number" name="goals_scored[<?php echo $i; ?>]" value="<?php echo esc_attr($player['goals_scored'] ?? 0); ?>" min="0" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        </label>
                    <?php endif; ?>
                    <?php if ($settings['show_awards'] === 'yes'): ?>
                        <label style="display: block; margin-bottom: 10px;">
                            <?php echo esc_html($settings['awards_label'] ?? __('Awards', 'intersoccer-player-management')); ?>
                            <select name="awards[<?php echo $i; ?>][]" multiple style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <?php
                                $available_awards = array(
                                    'most_improved' => __('Most Improved', 'intersoccer-player-management'),
                                    'top_scorer' => __('Top Scorer', 'intersoccer-player-management'),
                                    'best_defender' => __('Best Defender', 'intersoccer-player-management'),
                                    'team_player' => __('Team Player', 'intersoccer-player-management'),
                                );
                                $selected_awards = $player['awards'] ?? array();
                                foreach ($available_awards as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php echo in_array($value, $selected_awards) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($settings['show_remove_player_button'] === 'yes'): ?>
                        <button type="button" class="remove-player button"><?php echo esc_html($settings['remove_player_button_text'] ?? __('Remove', 'intersoccer-player-management')); ?></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($settings['show_add_player_button'] === 'yes' && count($players) < $max_players): ?>
            <button type="button" id="add-player" class="button"><?php echo esc_html($settings['add_player_button_text'] ?? __('Add Another Player', 'intersoccer-player-management')); ?></button>
        <?php endif; ?>
        <p><input type="submit" name="save_players" class="button" value="<?php echo esc_attr($settings['save_players_button_text'] ?? __('Save Players', 'intersoccer-player-management')); ?>" /></p>
    </form>

    <div id="player-template" style="display: none;">
        <div class="player-entry">
            <label style="display: block; margin-bottom: 10px;">
                <?php echo esc_html($settings['player_name_label'] ?? __('Player Name', 'intersoccer-player-management')); ?>
                <input type="text" name="player_name_template" value="" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled />
                <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Player name is required.', 'intersoccer-player-management'); ?></span>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <?php echo esc_html($settings['dob_label'] ?? __('Date of Birth', 'intersoccer-player-management')); ?>
                <input type="date" name="player_dob_template" value="" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled />
                <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Date of birth is required.', 'intersoccer-player-management'); ?></span>
            </label>
            <?php if ($settings['show_medical_conditions'] === 'yes'): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <input type="checkbox" name="has_medical_conditions_template" class="has-medical-conditions" />
                    <?php echo esc_html($settings['has_medical_conditions_label'] ?? __('Has Known Medical Conditions?', 'intersoccer-player-management')); ?>
                </label>
                <div class="medical-conditions" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px;">
                        <?php echo esc_html($settings['medical_conditions_label'] ?? __('Medical Conditions', 'intersoccer-player-management')); ?>
                        <textarea name="medical_conditions_template" style="width: 100%; max-width: 300px; height: 100px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                        <span class="error-message" style="display: none; font-size: 0.9em;"><?php _e('Medical conditions are required if checked.', 'intersoccer-player-management'); ?></span>
                    </label>
                </div>
            <?php endif; ?>
            <?php if ($settings['show_consent_file'] === 'yes'): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <?php echo esc_html($settings['medical_consent_label'] ?? __('Medical Consent Form (PDF/Image)', 'intersoccer-player-management')); ?>
                    <input type="file" name="medical_consent_template" accept=".pdf,.jpg,.png" style="width: 100%; max-width: 300px;" disabled />
                </label>
            <?php endif; ?>
            <?php if ($settings['show_profile_image'] === 'yes'): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <?php echo esc_html($settings['profile_image_label'] ?? __('Player Profile Image (JPG/PNG)', 'intersoccer-player-management')); ?>
                    <input type="file" name="profile_image_template" accept=".jpg,.png" style="width: 100%; max-width: 300px;" disabled />
                </label>
            <?php endif; ?>
            <?php if ($settings['show_goals_scored'] === 'yes'): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <?php echo esc_html($settings['goals_scored_label'] ?? __('Goals Scored', 'intersoccer-player-management')); ?>
                    <input type="number" name="goals_scored_template" value="0" min="0" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled />
                </label>
            <?php endif; ?>
            <?php if ($settings['show_awards'] === 'yes'): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <?php echo esc_html($settings['awards_label'] ?? __('Awards', 'intersoccer-player-management')); ?>
                    <select name="awards_template[]" multiple style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled>
                        <?php
                        $available_awards = array(
                            'most_improved' => __('Most Improved', 'intersoccer-player-management'),
                            'top_scorer' => __('Top Scorer', 'intersoccer-player-management'),
                            'best_defender' => __('Best Defender', 'intersoccer-player-management'),
                            'team_player' => __('Team Player', 'intersoccer-player-management'),
                        );
                        foreach ($available_awards as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($settings['show_remove_player_button'] === 'yes'): ?>
                <button type="button" class="remove-player button"><?php echo esc_html($settings['remove_player_button_text'] ?? __('Remove', 'intersoccer-player-management')); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Function to reindex form inputs
            function reindexFormInputs() {
                $('#players-list .player-entry').each(function(index) {
                    $(this).find('input[name^="player_name"]').attr('name', 'player_name[' + index + ']');
                    $(this).find('input[name^="player_dob"]').attr('name', 'player_dob[' + index + ']');
                    $(this).find('input[name^="has_medical_conditions"]').attr('name', 'has_medical_conditions[' + index + ']');
                    $(this).find('textarea[name^="medical_conditions"]').attr('name', 'medical_conditions[' + index + ']');
                    $(this).find('input[name^="medical_consent"]').attr('name', 'medical_consent[' + index + ']');
                    $(this).find('input[name^="profile_image"]').attr('name', 'profile_image[' + index + ']');
                    $(this).find('input[name^="goals_scored"]').attr('name', 'goals_scored[' + index + ']');
                    $(this).find('select[name^="awards"]').attr('name', 'awards[' + index + '][]');
                });
            }

            // Add new player
            $('#add-player').click(function() {
                var maxPlayers = <?php echo esc_js($max_players); ?>;
                if ($('#players-list .player-entry').length >= maxPlayers) {
                    alert('<?php _e('Maximum number of players reached.', 'intersoccer-player-management'); ?>');
                    return;
                }
                var $newEntry = $('#player-template .player-entry').clone();
                $newEntry.find('input, textarea, select').each(function() {
                    if ($(this).attr('name') === 'player_name_template') {
                        $(this).attr('name', 'player_name[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'player_dob_template') {
                        $(this).attr('name', 'player_dob[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'has_medical_conditions_template') {
                        $(this).attr('name', 'has_medical_conditions[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'medical_conditions_template') {
                        $(this).attr('name', 'medical_conditions[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'medical_consent_template') {
                        $(this).attr('name', 'medical_consent[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'profile_image_template') {
                        $(this).attr('name', 'profile_image[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'goals_scored_template') {
                        $(this).attr('name', 'goals_scored[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'awards_template[]') {
                        $(this).attr('name', 'awards[' + $('#players-list .player-entry').length + '][]');
                    }
                    $(this).css({
                        'display': 'block',
                        'width': '100%',
                        'max-width': '300px',
                        'padding': '8px'
                    }).prop('disabled', false);
                });
                $newEntry.find('.error-message').hide();
                $('#players-list').append($newEntry);
                reindexFormInputs();
            });

            // Remove player
            $(document).on('click', '.remove-player', function() {
                if (confirm('<?php _e('Are you sure?', 'intersoccer-player-management'); ?>')) {
                    $(this).closest('.player-entry').remove();
                    reindexFormInputs();
                }
            });

            // Toggle medical conditions field
            $(document).on('change', '.has-medical-conditions', function() {
                var $parent = $(this).closest('.player-entry');
                var $medicalConditions = $parent.find('.medical-conditions');
                var $textarea = $medicalConditions.find('textarea');
                var $error = $medicalConditions.find('.error-message');

                if ($(this).is(':checked')) {
                    $medicalConditions.show();
                    $textarea.addClass('required');
                } else {
                    $medicalConditions.hide();
                    $textarea.removeClass('required').val('');
                    $error.hide();
                }
            });

            // Custom validation on form submission
            $('#manage-players-form').on('submit', function(e) {
                var hasErrors = false;
                $('#players-list .player-entry').each(function() {
                    var $entry = $(this);
                    var $name = $entry.find('input[name^="player_name"]');
                    var $dob = $entry.find('input[name^="player_dob"]');
                    var $hasConditions = $entry.find('input[name^="has_medical_conditions"]');
                    var $conditions = $entry.find('textarea[name^="medical_conditions"]');
                    var $nameError = $name.next('.error-message');
                    var $dobError = $dob.next('.error-message');
                    var $conditionsError = $conditions.next('.error-message');

                    // Reset error states
                    $nameError.hide();
                    $dobError.hide();
                    $conditionsError.hide();
                    $name.css('border', '1px solid #ccc');
                    $dob.css('border', '1px solid #ccc');
                    $conditions.css('border', '1px solid #ccc');

                    // Debug values
                    console.log('Name:', $name.val(), 'DOB:', $dob.val(), 'Conditions:', $conditions.val());

                    // Validate name
                    var nameValue = $name.val() ? $name.val().trim() : '';
                    if (!nameValue && '<?php echo esc_js($settings['require_name'] ?? 'yes'); ?>' === 'yes') {
                        $nameError.show();
                        $name.css('border', '1px solid red');
                        hasErrors = true;
                    }

                    // Validate DOB
                    var dobValue = $dob.val();
                    if (!dobValue && '<?php echo esc_js($settings['require_dob'] ?? 'yes'); ?>' === 'yes') {
                        $dobError.show();
                        $dob.css('border', '1px solid red');
                        hasErrors = true;
                    }

                    // Validate medical conditions
                    if ('<?php echo esc_js($settings['show_medical_conditions'] ?? 'yes'); ?>' === 'yes' && $hasConditions.is(':checked')) {
                        var conditionsValue = $conditions.val() ? $conditions.val().trim() : '';
                        if (!conditionsValue && '<?php echo esc_js($settings['require_medical_conditions'] ?? 'yes'); ?>' === 'yes') {
                            $conditionsError.show();
                            $conditions.css('border', '1px solid red');
                            hasErrors = true;
                        }
                    }
                });

                if (hasErrors) {
                    e.preventDefault();
                    alert('<?php _e('Please fill in all required fields.', 'intersoccer-player-management'); ?>');
                } else if ($('#players-list .player-entry').length === 0) {
                    e.preventDefault();
                    alert('<?php _e('Please add at least one player.', 'intersoccer-player-management'); ?>');
                }
            });
        });
    </script>
    <?php
}
?>

