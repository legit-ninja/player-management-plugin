<?php
/**
 * InterSoccer Player List Elementor Widget
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Player List widget for Elementor
 */
class InterSoccer_Player_Elementor_Player_List extends InterSoccer_Player_Elementor_Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'intersoccer-player-list';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Player List', INTERSOCCER_PLAYER_TEXT_DOMAIN);
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'fa fa-users';
    }

    /**
     * Register widget controls
     */
    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Content', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Title', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Our Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'placeholder' => __('Enter widget title', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'show_title',
            array(
                'label' => __('Show Title', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'label_off' => __('Hide', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'player_source',
            array(
                'label' => __('Player Source', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => array(
                    'all' => __('All Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'current_user' => __('Current User Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'specific_user' => __('Specific User Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'recent' => __('Recently Added', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
            )
        );

        $this->add_control(
            'specific_user_id',
            array(
                'label' => __('User ID', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => array(
                    'player_source' => 'specific_user',
                ),
                'min' => 1,
            )
        );

        $this->add_control(
            'display_fields',
            array(
                'label' => __('Display Fields', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'default' => array('name', 'age', 'gender', 'events'),
                'options' => array(
                    'name' => __('Name', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'age' => __('Age', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'gender' => __('Gender', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'dob' => __('Date of Birth', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'events' => __('Event Count', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'medical' => __('Medical Conditions, Dietary Restrictions, and Allergies', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'emergency' => __('Emergency Contact', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
            )
        );

        $this->add_control(
            'show_avatar',
            array(
                'label' => __('Show Avatar', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'label_off' => __('Hide', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_actions',
            array(
                'label' => __('Show Action Buttons', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'label_off' => __('Hide', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();

        // Register common controls
        $this->register_common_controls();

        // Age Filter Section
        $this->start_controls_section(
            'age_filter_section',
            array(
                'label' => __('Age Filters', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'min_age',
            array(
                'label' => __('Minimum Age', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 18,
                'default' => 3,
            )
        );

        $this->add_control(
            'max_age',
            array(
                'label' => __('Maximum Age', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 18,
                'default' => 18,
            )
        );

        $this->add_control(
            'gender_filter',
            array(
                'label' => __('Gender Filter', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => array(
                    'all' => __('All', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'male' => __('Male Only', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'female' => __('Female Only', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
            )
        );

        $this->end_controls_section();

        // Register style controls
        $this->register_style_controls();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $settings = $this->validate_settings($settings);

        $this->log_widget_render('player-list', $settings);

        // Get players based on settings
        $players = $this->get_players($settings);

        if (empty($players)) {
            $this->render_no_data(__('No players found matching your criteria.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
            return;
        }

        $grid_classes = $this->get_grid_classes($settings['columns']);

        ?>
        <div class="intersoccer-player-list-widget">
            <?php if ($settings['show_title'] === 'yes' && !empty($settings['title'])): ?>
                <h3 class="intersoccer-widget-title"><?php echo esc_html($settings['title']); ?></h3>
            <?php endif; ?>

            <?php $this->render_filters($settings); ?>

            <div class="<?php echo esc_attr($grid_classes); ?>">
                <?php foreach ($players as $player): ?>
                    <div class="intersoccer-card intersoccer-player-card" data-player-id="<?php echo esc_attr($player->id); ?>">
                        <?php if ($settings['show_avatar'] === 'yes'): ?>
                            <div class="intersoccer-player-avatar">
                                <?php echo $this->get_player_avatar($player); ?>
                            </div>
                        <?php endif; ?>

                        <div class="intersoccer-card-content">
                            <?php $this->render_player_fields($player, $settings['display_fields']); ?>
                        </div>

                        <?php if ($settings['show_actions'] === 'yes'): ?>
                            <div class="intersoccer-card-actions">
                                <?php $this->render_player_actions($player); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get players based on settings
     */
    private function get_players($settings) {
        $filters = array();

        // Apply age filters
        if (!empty($settings['min_age']) || !empty($settings['max_age'])) {
            $filters['age_range'] = array(
                'min' => $settings['min_age'] ?? 3,
                'max' => $settings['max_age'] ?? 18,
            );
        }

        // Apply gender filter
        if (!empty($settings['gender_filter']) && $settings['gender_filter'] !== 'all') {
            $filters['gender'] = $settings['gender_filter'];
        }

        // Apply limit
        $filters['limit'] = $settings['limit'];

        // Apply order
        $filters['order_by'] = $settings['order_by'];
        $filters['order'] = $settings['order'];

        // Apply source filter
        switch ($settings['player_source']) {
            case 'current_user':
                $filters['user_id'] = get_current_user_id();
                break;
            case 'specific_user':
                if (!empty($settings['specific_user_id'])) {
                    $filters['user_id'] = $settings['specific_user_id'];
                }
                break;
            case 'recent':
                $filters['recent_days'] = 30;
                break;
        }

        return $this->database->get_players_filtered($filters);
    }

    /**
     * Get player avatar
     */
    private function get_player_avatar($player) {
        // Generate initials-based avatar since we don't store player photos
        $initials = strtoupper(substr($player->first_name, 0, 1) . substr($player->last_name, 0, 1));
        $gender_class = 'intersoccer-avatar-' . $player->gender;
        
        return sprintf(
            '<div class="intersoccer-avatar %s"><span>%s</span></div>',
            esc_attr($gender_class),
            esc_html($initials)
        );
    }

    /**
     * Render player fields
     */
    private function render_player_fields($player, $display_fields) {
        ?>
        <div class="intersoccer-player-info">
            <?php if (in_array('name', $display_fields)): ?>
                <h4 class="intersoccer-card-title intersoccer-player-name">
                    <?php echo esc_html($player->first_name . ' ' . $player->last_name); ?>
                </h4>
            <?php endif; ?>

            <?php if (in_array('age', $display_fields) && !empty($player->dob)): ?>
                <div class="intersoccer-player-age">
                    <span class="intersoccer-label"><?php _e('Age:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value"><?php echo esc_html($this->calculate_age($player->dob)); ?></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('gender', $display_fields)): ?>
                <div class="intersoccer-player-gender">
                    <span class="intersoccer-label"><?php _e('Gender:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value"><?php echo esc_html($this->get_gender_label($player->gender)); ?></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('dob', $display_fields) && !empty($player->dob)): ?>
                <div class="intersoccer-player-dob">
                    <span class="intersoccer-label"><?php _e('Date of Birth:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value"><?php echo esc_html($this->format_date($player->dob)); ?></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('events', $display_fields)): ?>
                <div class="intersoccer-player-events">
                    <span class="intersoccer-label"><?php _e('Events:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value intersoccer-event-count"><?php echo esc_html($player->event_count ?? 0); ?></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('medical', $display_fields) && !empty($player->medical_conditions)): ?>
                <div class="intersoccer-player-medical">
                    <span class="intersoccer-label"><?php _e('Medical:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value"><?php echo esc_html(wp_trim_words($player->medical_conditions, 10)); ?></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('emergency', $display_fields) && !empty($player->emergency_contact)): ?>
                <div class="intersoccer-player-emergency">
                    <span class="intersoccer-label"><?php _e('Emergency Contact:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                    <span class="intersoccer-value"><?php echo esc_html($player->emergency_contact); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render player actions
     */
    private function render_player_actions($player) {
        $current_user_id = get_current_user_id();
        $can_edit = ($player->user_id == $current_user_id) || current_user_can('manage_options');
        
        ?>
        <div class="intersoccer-player-actions">
            <button type="button" 
                    class="intersoccer-button intersoccer-button-small intersoccer-view-player" 
                    data-player-id="<?php echo esc_attr($player->id); ?>">
                <?php _e('View', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
            </button>
            
            <?php if ($can_edit): ?>
                <button type="button" 
                        class="intersoccer-button intersoccer-button-small intersoccer-edit-player" 
                        data-player-id="<?php echo esc_attr($player->id); ?>">
                    <?php _e('Edit', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                </button>
            <?php endif; ?>
            
            <button type="button" 
                    class="intersoccer-button intersoccer-button-small intersoccer-player-events" 
                    data-player-id="<?php echo esc_attr($player->id); ?>">
                <?php _e('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor
     */
    protected function _content_template() {
        ?>
        <#
        var gridClass = 'intersoccer-grid intersoccer-grid-' + settings.columns;
        #>
        <div class="intersoccer-player-list-widget">
            <# if ( settings.show_title === 'yes' && settings.title ) { #>
                <h3 class="intersoccer-widget-title">{{{ settings.title }}}</h3>
            <# } #>
            
            <# if ( settings.show_filters === 'yes' ) { #>
                <div class="intersoccer-filters">
                    <div class="intersoccer-filter-item">
                        <label><?php _e('Gender:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                        <select class="intersoccer-filter-select">
                            <option><?php _e('All', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                            <option><?php _e('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                            <option><?php _e('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        </select>
                    </div>
                </div>
            <# } #>
            
            <div class="{{ gridClass }}">
                <# for ( var i = 1; i <= 3; i++ ) { #>
                    <div class="intersoccer-card intersoccer-player-card">
                        <# if ( settings.show_avatar === 'yes' ) { #>
                            <div class="intersoccer-player-avatar">
                                <div class="intersoccer-avatar"><span>P{{ i }}</span></div>
                            </div>
                        <# } #>
                        
                        <div class="intersoccer-card-content">
                            <div class="intersoccer-player-info">
                                <# if ( settings.display_fields.includes('name') ) { #>
                                    <h4 class="intersoccer-card-title">Player {{ i }}</h4>
                                <# } #>
                                
                                <# if ( settings.display_fields.includes('age') ) { #>
                                    <div class="intersoccer-player-age">
                                        <span class="intersoccer-label"><?php _e('Age:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                                        <span class="intersoccer-value">{{ 8 + i }}</span>
                                    </div>
                                <# } #>
                                
                                <# if ( settings.display_fields.includes('gender') ) { #>
                                    <div class="intersoccer-player-gender">
                                        <span class="intersoccer-label"><?php _e('Gender:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                                        <span class="intersoccer-value">{{ i % 2 ? '<?php _e('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>' : '<?php _e('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>' }}</span>
                                    </div>
                                <# } #>
                                
                                <# if ( settings.display_fields.includes('events') ) { #>
                                    <div class="intersoccer-player-events">
                                        <span class="intersoccer-label"><?php _e('Events:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                                        <span class="intersoccer-value intersoccer-event-count">{{ i * 2 }}</span>
                                    </div>
                                <# } #>
                            </div>
                        </div>
                        
                        <# if ( settings.show_actions === 'yes' ) { #>
                            <div class="intersoccer-card-actions">
                                <button class="intersoccer-button intersoccer-button-small"><?php _e('View', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
                                <button class="intersoccer-button intersoccer-button-small"><?php _e('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
                            </div>
                        <# } #>
                    </div>
                <# } #>
            </div>
        </div>
        <?php
    }
}

// Add method to database class to support filtered queries (this would go in the database class)
/*
public function get_players_filtered($filters = array()) {
    $where = array("status = 'active'");
    $values = array();

    // User filter
    if (!empty($filters['user_id'])) {
        $where[] = "user_id = %d";
        $values[] = $filters['user_id'];
    }

    // Gender filter
    if (!empty($filters['gender'])) {
        $where[] = "gender = %s";
        $values[] = $filters['gender'];
    }

    // Age range filter
    if (!empty($filters['age_range'])) {
        $min_date = date('Y-m-d', strtotime('-' . $filters['age_range']['max'] . ' years'));
        $max_date = date('Y-m-d', strtotime('-' . $filters['age_range']['min'] . ' years'));
        $where[] = "dob BETWEEN %s AND %s";
        $values[] = $min_date;
        $values[] = $max_date;
    }

    // Recent filter
    if (!empty($filters['recent_days'])) {
        $cutoff = time() - ($filters['recent_days'] * DAY_IN_SECONDS);
        $where[] = "creation_timestamp > %d";
        $values[] = $cutoff;
    }

    // Build query
    $where_clause = implode(' AND ', $where);
    
    // Order by
    $order_by = $filters['order_by'] ?? 'first_name';
    $order = $filters['order'] ?? 'ASC';
    
    $valid_order_fields = array('first_name', 'last_name', 'dob', 'creation_timestamp', 'event_count');
    if (!in_array($order_by, $valid_order_fields)) {
        $order_by = 'first_name';
    }
    
    if ($order_by === 'name') {
        $order_clause = "ORDER BY first_name {$order}, last_name {$order}";
    } elseif ($order_by === 'age') {
        $order_clause = "ORDER BY dob {$order}";
    } elseif ($order_by === 'created') {
        $order_clause = "ORDER BY creation_timestamp {$order}";
    } else {
        $order_clause = "ORDER BY {$order_by} {$order}";
    }
    
    // Limit
    $limit = '';
    if (!empty($filters['limit'])) {
        $limit = 'LIMIT ' . absint($filters['limit']);
    }

    $sql = "SELECT * FROM {$this->tables['players']} WHERE {$where_clause} {$order_clause} {$limit}";

    if (empty($values)) {
        return $this->wpdb->get_results($sql);
    } else {
        return $this->wpdb->get_results($this->wpdb->prepare($sql, ...$values));
    }
}
*/