<?php

/**
 * File: elementor-widgets.php
 * Description: Registers Elementor widgets for the InterSoccer Player Management plugin, including the Attendee Management widget with full customization options.
 * Dependencies: Elementor, player-management.php
 * Changes:
 * - Initial Elementor widget for attendee management (2025-05-18).
 * - Added achievements and events display with toggle controls (2025-05-21).
 * - Added full customization for columns, headings, and styling, defaulting to "off" (2025-05-21).
 * - Added safety check for Elementor availability (2025-05-21).
 * Testing:
 * - Verify widget renders on Elementor-powered pages.
 * - Test all customization controls (column visibility, headings, styling).
 * - Confirm defaults are "off" and form elements can be fully customized.
 * - Ensure compatibility with player-management.php rendering.
 * - Confirm no errors when Elementor is not active (handled by intersoccer-player-management.php).
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Safety check: Ensure Elementor is loaded before proceeding
if (!class_exists('\Elementor\Widget_Base')) {
    return; // Silently exit if Elementor is not available
}

class InterSoccer_Attendee_Management_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'intersoccer-attendee-management';
    }

    public function get_title()
    {
        return __('InterSoccer Attendee Management', 'intersoccer-player-management');
    }

    public function get_icon()
    {
        return 'eicon-person';
    }

    public function get_categories()
    {
        return ['intersoccer'];
    }

    protected function _register_controls()
    {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'intersoccer-player-management'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Form Title
        $this->add_control(
            'show_form_title',
            [
                'label' => __('Show Form Title', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'form_title_text',
            [
                'label' => __('Form Title Text', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Manage Your Attendees', 'intersoccer-player-management'),
                'condition' => [
                    'show_form_title' => 'yes',
                ],
            ]
        );

        // Add Button
        $this->add_control(
            'show_add_button',
            [
                'label' => __('Show Add Button', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        // Column Visibility
        $this->add_control(
            'show_first_name',
            [
                'label' => __('Show First Name Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_last_name',
            [
                'label' => __('Show Last Name Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_dob',
            [
                'label' => __('Show DOB Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_gender',
            [
                'label' => __('Show Gender Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_avs_number',
            [
                'label' => __('Show AVS Number Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_events',
            [
                'label' => __('Show Events Column', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'intersoccer-player-management'),
                'label_off' => __('No', 'intersoccer-player-management'),
                'default' => 'no',
            ]
        );

        // Column Headings
        $this->add_control(
            'first_name_heading',
            [
                'label' => __('First Name Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('First Name', 'intersoccer-player-management'),
                'condition' => [
                    'show_first_name' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'last_name_heading',
            [
                'label' => __('Last Name Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Last Name', 'intersoccer-player-management'),
                'condition' => [
                    'show_last_name' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'dob_heading',
            [
                'label' => __('DOB Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('DOB', 'intersoccer-player-management'),
                'condition' => [
                    'show_dob' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'gender_heading',
            [
                'label' => __('Gender Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Gender', 'intersoccer-player-management'),
                'condition' => [
                    'show_gender' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'avs_number_heading',
            [
                'label' => __('AVS Number Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('AVS Number', 'intersoccer-player-management'),
                'condition' => [
                    'show_avs_number' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'events_heading',
            [
                'label' => __('Events Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Events', 'intersoccer-player-management'),
                'condition' => [
                    'show_events' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'actions_heading',
            [
                'label' => __('Actions Heading', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Actions', 'intersoccer-player-management'),
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'intersoccer-player-management'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Background Color', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-management' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'container_text_color',
            [
                'label' => __('Text Color', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-management' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label' => __('Padding', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-management' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'table_border_color',
            [
                'label' => __('Table Border Color', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ddd',
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-management table' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .intersoccer-player-management th' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .intersoccer-player-management td' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_background',
            [
                'label' => __('Header Background Color', 'intersoccer-player-management'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f9f9f9',
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-player-management th' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        echo intersoccer_render_players_form(false, $settings);
    }

    protected function _content_template()
    {
?>
        <div class="intersoccer-player-management">
            <h2 class="intersoccer-form-title"><?php echo esc_html($this->get_settings('form_title_text')); ?></h2>
            <div class="intersoccer-message" style="display: none;" role="alert" aria-live="polite"></div>
            <div class="intersoccer-player-actions">
                <a href="#" class="toggle-add-player">Add</a>
            </div>
            <div class="intersoccer-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <# if ( settings.show_first_name ) { #>
                                <th scope="col">{{{ settings.first_name_heading }}}</th>
                                <# } #>
                                    <# if ( settings.show_last_name ) { #>
                                        <th scope="col">{{{ settings.last_name_heading }}}</th>
                                        <# } #>
                                            <# if ( settings.show_dob ) { #>
                                                <th scope="col">{{{ settings.dob_heading }}}</th>
                                                <# } #>
                                                    <# if ( settings.show_gender ) { #>
                                                        <th scope="col">{{{ settings.gender_heading }}}</th>
                                                        <# } #>
                                                            <# if ( settings.show_avs_number ) { #>
                                                                <th scope="col">{{{ settings.avs_number_heading }}}</th>
                                                                <# } #>
                                                                    <# if ( settings.show_events ) { #>
                                                                        <th scope="col">{{{ settings.events_heading }}}</th>
                                                                        <# } #>
                                                                            <th scope="col" class="actions">{{{ settings.actions_heading }}}</th>
                        </tr>
                    </thead>
                    <tbody id="player-table">
                        <tr class="no-players">
                            <td colspan="7">No attendees added yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
<?php
    }
}

// Register the widget
add_action('elementor/widgets/register', function ($widgets_manager) {
    $widgets_manager->register(new InterSoccer_Attendee_Management_Widget());
});

// Register the widget category
add_action('elementor/elements/categories_registered', function ($elements_manager) {
    $elements_manager->add_category(
        'intersoccer',
        [
            'title' => __('InterSoccer', 'intersoccer-player-management'),
            'icon' => 'fa fa-plug',
        ]
    );
});
?>
