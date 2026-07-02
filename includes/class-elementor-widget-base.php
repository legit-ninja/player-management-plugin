<?php
/**
 * InterSoccer Player Management Elementor Widget Base
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for all InterSoccer Player Elementor widgets
 */
abstract class InterSoccer_Player_Elementor_Widget_Base extends \Elementor\Widget_Base {

    /**
     * Database instance
     */
    protected $database;

    /**
     * Validator instance
     */
    protected $validator;

    /**
     * Logger instance
     */
    protected $logger;

    /**
     * Constructor
     */
    public function __construct($data = array(), $args = null) {
        parent::__construct($data, $args);

        $this->database = new InterSoccer_Player_Database();
        $this->validator = new InterSoccer_Player_Validator();
        $this->logger = new InterSoccer_Player_Logger();
    }

    /**
     * Get widget category
     */
    public function get_categories() {
        return array('intersoccer-player');
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return array('intersoccer', 'player', 'management', 'sports', 'soccer', 'football');
    }

    /**
     * Register common controls
     */
    protected function register_common_controls() {
        // Layout Section
        $this->start_controls_section(
            'layout_section',
            array(
                'label' => __('Layout', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'columns',
            array(
                'label' => __('Columns', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3',
                'options' => array(
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '6' => '6',
                ),
            )
        );

        $this->add_control(
            'gap',
            array(
                'label' => __('Gap', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 100,
                        'step' => 5,
                    ),
                    'em' => array(
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 20,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Query Section
        $this->start_controls_section(
            'query_section',
            array(
                'label' => __('Query', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Limit', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 100,
            )
        );

        $this->add_control(
            'order_by',
            array(
                'label' => __('Order By', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'name',
                'options' => array(
                    'name' => __('Name', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'age' => __('Age', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'created' => __('Created Date', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'events' => __('Event Count', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Order', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => array(
                    'ASC' => __('Ascending', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'DESC' => __('Descending', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
            )
        );

        $this->end_controls_section();

        // Filter Section
        $this->start_controls_section(
            'filter_section',
            array(
                'label' => __('Filters', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'show_filters',
            array(
                'label' => __('Show Filters', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'label_off' => __('Hide', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'filter_by_age',
            array(
                'label' => __('Filter by Age', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'condition' => array(
                    'show_filters' => 'yes',
                ),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'filter_by_gender',
            array(
                'label' => __('Filter by Gender', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'condition' => array(
                    'show_filters' => 'yes',
                ),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'filter_by_activity',
            array(
                'label' => __('Filter by Activity Type', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'condition' => array(
                    'show_filters' => 'yes',
                ),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     */
    protected function register_style_controls() {
        // Card Style Section
        $this->start_controls_section(
            'card_style_section',
            array(
                'label' => __('Card Style', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Background Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'label' => __('Border', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-card',
            )
        );

        $this->add_control(
            'card_border_radius',
            array(
                'label' => __('Border Radius', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_box_shadow',
                'label' => __('Box Shadow', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-card',
            )
        );

        $this->add_control(
            'card_padding',
            array(
                'label' => __('Padding', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Typography Section
        $this->start_controls_section(
            'typography_section',
            array(
                'label' => __('Typography', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'label' => __('Title Typography', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-card-title',
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Title Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-card-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'content_typography',
                'label' => __('Content Typography', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-card-content',
            )
        );

        $this->add_control(
            'content_color',
            array(
                'label' => __('Content Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-card-content' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Button Style Section
        $this->start_controls_section(
            'button_style_section',
            array(
                'label' => __('Button Style', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            array(
                'label' => __('Normal', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Text Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_color',
            array(
                'label' => __('Background Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#007cba',
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            array(
                'label' => __('Hover', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'button_hover_text_color',
            array(
                'label' => __('Text Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_background_color',
            array(
                'label' => __('Background Color', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'label' => __('Typography', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-button',
                'separator' => 'before',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'button_border',
                'label' => __('Border', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'selector' => '{{WRAPPER}} .intersoccer-button',
            )
        );

        $this->add_control(
            'button_border_radius',
            array(
                'label' => __('Border Radius', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'button_padding',
            array(
                'label' => __('Padding', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .intersoccer-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Get grid classes based on columns setting
     */
    protected function get_grid_classes($columns) {
        $grid_classes = 'intersoccer-grid';
        
        switch ($columns) {
            case '1':
                $grid_classes .= ' intersoccer-grid-1';
                break;
            case '2':
                $grid_classes .= ' intersoccer-grid-2';
                break;
            case '3':
                $grid_classes .= ' intersoccer-grid-3';
                break;
            case '4':
                $grid_classes .= ' intersoccer-grid-4';
                break;
            case '6':
                $grid_classes .= ' intersoccer-grid-6';
                break;
            default:
                $grid_classes .= ' intersoccer-grid-3';
        }
        
        return $grid_classes;
    }

    /**
     * Render filter controls
     */
    protected function render_filters($settings) {
        if ($settings['show_filters'] !== 'yes') {
            return;
        }

        ?>
        <div class="intersoccer-filters">
            <?php if ($settings['filter_by_gender'] === 'yes'): ?>
                <div class="intersoccer-filter-item">
                    <label for="gender-filter"><?php _e('Gender:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                    <select id="gender-filter" class="intersoccer-filter-select" data-filter="gender">
                        <option value=""><?php _e('All', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="male"><?php _e('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="female"><?php _e('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="other"><?php _e('Other', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($settings['filter_by_age'] === 'yes'): ?>
                <div class="intersoccer-filter-item">
                    <label for="age-filter"><?php _e('Age Group:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                    <select id="age-filter" class="intersoccer-filter-select" data-filter="age">
                        <option value=""><?php _e('All', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="3-5"><?php _e('3-5 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="6-8"><?php _e('6-8 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="9-12"><?php _e('9-12 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="13-18"><?php _e('13-18 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($settings['filter_by_activity'] === 'yes'): ?>
                <div class="intersoccer-filter-item">
                    <label for="activity-filter"><?php _e('Activity:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                    <select id="activity-filter" class="intersoccer-filter-select" data-filter="activity">
                        <option value=""><?php _e('All', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="camp"><?php _e('Camps', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="course"><?php _e('Courses', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="birthday"><?php _e('Birthday Parties', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="intersoccer-filter-item">
                <button type="button" class="intersoccer-button intersoccer-filter-reset">
                    <?php _e('Reset Filters', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render loading spinner
     */
    protected function render_loading() {
        ?>
        <div class="intersoccer-loading">
            <div class="intersoccer-spinner"></div>
            <span><?php _e('Loading...', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
        </div>
        <?php
    }

    /**
     * Render no data message
     */
    protected function render_no_data($message = '') {
        if (empty($message)) {
            $message = __('No data found.', INTERSOCCER_PLAYER_TEXT_DOMAIN);
        }
        ?>
        <div class="intersoccer-no-data">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    /**
     * Calculate age from date of birth
     */
    protected function calculate_age($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    }

    /**
     * Format date for display
     */
    protected function format_date($date, $format = 'F j, Y') {
        if (empty($date)) {
            return '';
        }
        
        $datetime = new DateTime($date);
        return $datetime->format($format);
    }

    /**
     * Get gender display label
     */
    protected function get_gender_label($gender) {
        switch ($gender) {
            case 'male':
                return __('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            case 'female':
                return __('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            case 'other':
                return __('Other', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            default:
                return __('Not Specified', INTERSOCCER_PLAYER_TEXT_DOMAIN);
        }
    }

    /**
     * Get activity type label
     */
    protected function get_activity_type_label($activity_type) {
        switch ($activity_type) {
            case 'camp':
                return __('Camp', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            case 'course':
                return __('Course', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            case 'birthday':
                return __('Birthday Party', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            default:
                return ucfirst($activity_type);
        }
    }

    /**
     * Validate widget settings
     */
    protected function validate_settings($settings) {
        // Sanitize limit
        $settings['limit'] = absint($settings['limit']);
        if ($settings['limit'] <= 0) {
            $settings['limit'] = 10;
        }
        if ($settings['limit'] > 100) {
            $settings['limit'] = 100;
        }

        // Validate columns
        $valid_columns = array('1', '2', '3', '4', '6');
        if (!in_array($settings['columns'], $valid_columns)) {
            $settings['columns'] = '3';
        }

        // Validate order
        $valid_orders = array('ASC', 'DESC');
        if (!in_array($settings['order'], $valid_orders)) {
            $settings['order'] = 'ASC';
        }

        return $settings;
    }

    /**
     * Log widget render
     */
    protected function log_widget_render($widget_name, $settings = array()) {
        $this->logger->debug("Elementor widget rendered: {$widget_name}", array(
            'widget' => $widget_name,
            'settings' => $settings,
            'user_id' => get_current_user_id(),
        ));
    }
}