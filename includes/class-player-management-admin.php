<?php
/**
 * InterSoccer Player Management Admin
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for handling backend functionality
 */
class InterSoccer_Player_Admin {

    /**
     * Database instance
     */
    private $database;

    /**
     * Validator instance
     */
    private $validator;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new InterSoccer_Player_Database();
        $this->validator = new InterSoccer_Player_Validator();
        $this->logger = new InterSoccer_Player_Logger();

        // Initialize admin hooks
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_intersoccer_export_roster', array($this, 'handle_export_request'));
        add_action('wp_ajax_intersoccer_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_intersoccer_bulk_player_action', array($this, 'handle_bulk_action'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));

        // Add settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add custom columns to users table
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);

    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        $capability = 'manage_options';

        // Main menu page
        add_menu_page(
            __('InterSoccer Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('InterSoccer Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            $capability,
            'intersoccer-players',
            array($this, 'render_dashboard_page'),
            'dashicons-groups',
            30
        );

        // Dashboard submenu (rename main menu)
        add_submenu_page(
            'intersoccer-players',
            __('Dashboard', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('Dashboard', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            $capability,
            'intersoccer-players'
        );

        // Players management
        add_submenu_page(
            'intersoccer-players',
            __('All Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('All Players', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            $capability,
            'intersoccer-all-players',
            array($this, 'render_all_players_page')
        );

        // Rosters and exports
        add_submenu_page(
            'intersoccer-players',
            __('Rosters & Export', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('Rosters & Export', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            'export_rosters',
            'intersoccer-rosters',
            array($this, 'render_rosters_page')
        );

        // Events management
        add_submenu_page(
            'intersoccer-players',
            __('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            'manage_events',
            'intersoccer-events',
            array($this, 'render_events_page')
        );

        // Settings
        add_submenu_page(
            'intersoccer-players',
            __('Settings', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('Settings', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            $capability,
            'intersoccer-settings',
            array($this, 'render_settings_page')
        );

        // Tools and maintenance
        add_submenu_page(
            'intersoccer-players',
            __('Tools', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            __('Tools', INTERSOCCER_PLAYER_TEXT_DOMAIN),
            $capability,
            'intersoccer-tools',
            array($this, 'render_tools_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'intersoccer') === false) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'intersoccer-admin',
            INTERSOCCER_PLAYER_URL . 'assets/css/admin.css',
            array(),
            INTERSOCCER_PLAYER_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'intersoccer-admin',
            INTERSOCCER_PLAYER_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util', 'jquery-ui-datepicker'),
            INTERSOCCER_PLAYER_VERSION,
            true
        );

        // Chart.js for dashboard
        if ($hook === 'toplevel_page_intersoccer-players') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }

        // DataTables for player lists
        if (strpos($hook, 'intersoccer-all-players') !== false || strpos($hook, 'intersoccer-rosters') !== false) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
            
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
                array('jquery'),
                '1.13.6',
                true
            );
            
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
                array(),
                '1.13.6'
            );
        }

        // Localize script
        wp_localize_script(
            'intersoccer-admin',
            'intersoccerAdmin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this player?', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'confirm_bulk_delete' => __('Are you sure you want to delete the selected players?', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'export_processing' => __('Processing export...', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'export_complete' => __('Export completed successfully!', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                    'export_error' => __('Export failed. Please try again.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
                ),
                'current_page' => $hook,
            )
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->database->get_statistics();
        $recent_players = $this->database->get_players_filtered(array('recent_days' => 30, 'limit' => 5));
        
        ?>
        <div class="wrap intersoccer-admin-page">
            <h1><?php _e('InterSoccer Players Dashboard', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h1>
            
            <!-- Statistics Cards -->
            <div class="intersoccer-stats-grid">
                <div class="intersoccer-stat-card">
                    <h3><?php _e('Total Players', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                    <div class="intersoccer-stat-number"><?php echo esc_html($stats['total_players']); ?></div>
                </div>
                
                <div class="intersoccer-stat-card">
                    <h3><?php _e('Recent Registrations', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                    <div class="intersoccer-stat-number"><?php echo esc_html($stats['recent_registrations']); ?></div>
                    <small><?php _e('Last 30 days', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></small>
                </div>
                
                <div class="intersoccer-stat-card">
                    <h3><?php _e('Average Age', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                    <div class="intersoccer-stat-number"><?php echo esc_html(round($stats['average_age'], 1)); ?></div>
                </div>
                
                <div class="intersoccer-stat-card">
                    <h3><?php _e('Total Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                    <div class="intersoccer-stat-number"><?php echo esc_html(array_sum($stats['events_by_type'])); ?></div>
                </div>
            </div>

            <div class="intersoccer-dashboard-content">
                <!-- Charts Section -->
                <div class="intersoccer-dashboard-section">
                    <h2><?php _e('Analytics', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    
                    <div class="intersoccer-charts-grid">
                        <div class="intersoccer-chart-container">
                            <h3><?php _e('Gender Distribution', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                            <canvas id="genderChart"></canvas>
                        </div>
                        
                        <div class="intersoccer-chart-container">
                            <h3><?php _e('Events by Type', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                            <canvas id="eventsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Players Section -->
                <div class="intersoccer-dashboard-section">
                    <h2><?php _e('Recent Players', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    
                    <?php if (empty($recent_players)): ?>
                        <p><?php _e('No recent players found.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                    <?php else: ?>
                        <div class="intersoccer-recent-players">
                            <?php foreach ($recent_players as $player): ?>
                                <div class="intersoccer-player-item">
                                    <div class="player-info">
                                        <strong><?php echo esc_html($player->first_name . ' ' . $player->last_name); ?></strong>
                                        <span class="player-meta">
                                            <?php echo esc_html($this->get_gender_label($player->gender)); ?> | 
                                            <?php echo esc_html($this->calculate_age($player->dob)); ?> years old
                                        </span>
                                    </div>
                                    <div class="player-actions">
                                        <a href="<?php echo admin_url('admin.php?page=intersoccer-all-players&player_id=' . $player->id); ?>" 
                                           class="button button-small">
                                            <?php _e('View', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions Section -->
                <div class="intersoccer-dashboard-section">
                    <h2><?php _e('Quick Actions', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    
                    <div class="intersoccer-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-rosters'); ?>" class="button button-primary">
                            <?php _e('Export Rosters', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-all-players'); ?>" class="button">
                            <?php _e('View All Players', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-events'); ?>" class="button">
                            <?php _e('Manage Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-settings'); ?>" class="button">
                            <?php _e('Settings', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Data -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Gender Distribution Chart
            var genderCtx = document.getElementById('genderChart').getContext('2d');
            var genderChart = new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female', 'Other'],
                    datasets: [{
                        data: [
                            <?php echo intval($stats['gender_breakdown']['male'] ?? 0); ?>,
                            <?php echo intval($stats['gender_breakdown']['female'] ?? 0); ?>,
                            <?php echo intval($stats['gender_breakdown']['other'] ?? 0); ?>
                        ],
                        backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Events by Type Chart
            var eventsCtx = document.getElementById('eventsChart').getContext('2d');
            var eventsChart = new Chart(eventsCtx, {
                type: 'bar',
                data: {
                    labels: ['Camps', 'Courses', 'Birthdays'],
                    datasets: [{
                        label: 'Events',
                        data: [
                            <?php echo intval($stats['events_by_type']['camp'] ?? 0); ?>,
                            <?php echo intval($stats['events_by_type']['course'] ?? 0); ?>,
                            <?php echo intval($stats['events_by_type']['birthday'] ?? 0); ?>
                        ],
                        backgroundColor: '#36A2EB'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render all players page
     */
    public function render_all_players_page() {
        $players = $this->database->get_players_filtered(array('limit' => 1000)); // Limit for performance
        
        ?>
        <div class="wrap intersoccer-admin-page">
            <h1>
                <?php _e('All Players', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                <button type="button" class="page-title-action" id="add-new-player">
                    <?php _e('Add New Player', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                </button>
            </h1>

            <!-- Filters -->
            <div class="intersoccer-filters-section">
                <div class="intersoccer-filter-row">
                    <select id="gender-filter">
                        <option value=""><?php _e('All Genders', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="male"><?php _e('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="female"><?php _e('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="other"><?php _e('Other', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    </select>

                    <select id="age-filter">
                        <option value=""><?php _e('All Ages', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="3-5"><?php _e('3-5 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="6-8"><?php _e('6-8 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="9-12"><?php _e('9-12 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                        <option value="13-18"><?php _e('13-18 years', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    </select>

                    <input type="text" id="search-players" placeholder="<?php _e('Search players...', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>">
                    <button type="button" class="button" id="reset-filters"><?php _e('Reset', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
                </div>
            </div>

            <!-- Players Table -->
            <table id="players-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="select-all-players"></th>
                        <th><?php _e('Name', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Age', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Gender', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Parent', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Registration Date', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Actions', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                        <?php
                        $parent = get_user_by('id', $player->user_id);
                        $age = $this->calculate_age($player->dob);
                        ?>
                        <tr data-player-id="<?php echo esc_attr($player->id); ?>">
                            <td><input type="checkbox" class="player-checkbox" value="<?php echo esc_attr($player->id); ?>"></td>
                            <td>
                                <strong><?php echo esc_html($player->first_name . ' ' . $player->last_name); ?></strong>
                                <?php if (!empty($player->medical_conditions)): ?>
                                    <span class="intersoccer-medical-indicator" title="<?php echo esc_attr($player->medical_conditions); ?>">⚕️</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($age); ?></td>
                            <td><?php echo esc_html($this->get_gender_label($player->gender)); ?></td>
                            <td>
                                <?php if ($parent): ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $parent->ID); ?>">
                                        <?php echo esc_html($parent->display_name); ?>
                                    </a>
                                    <br><small><?php echo esc_html($parent->user_email); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($player->event_count); ?></td>
                            <td><?php echo esc_html(date('Y-m-d', $player->creation_timestamp)); ?></td>
                            <td class="actions">
                                <button type="button" class="button button-small edit-player" 
                                        data-player-id="<?php echo esc_attr($player->id); ?>">
                                    <?php _e('Edit', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="button button-small view-events" 
                                        data-player-id="<?php echo esc_attr($player->id); ?>">
                                    <?php _e('Events', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete delete-player" 
                                        data-player-id="<?php echo esc_attr($player->id); ?>">
                                    <?php _e('Delete', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Bulk Actions -->
            <div class="intersoccer-bulk-actions">
                <select id="bulk-action">
                    <option value=""><?php _e('Bulk Actions', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    <option value="delete"><?php _e('Delete Selected', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                    <option value="export"><?php _e('Export Selected', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                </select>
                <button type="button" class="button" id="apply-bulk-action"><?php _e('Apply', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
            </div>
        </div>

        <!-- Player Modal -->
        <div id="player-modal" class="intersoccer-modal" style="display: none;">
            <div class="intersoccer-modal-content">
                <span class="intersoccer-modal-close">&times;</span>
                <h2 id="modal-title"><?php _e('Player Details', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                <form id="player-form">
                    <div class="intersoccer-form-grid">
                        <div class="intersoccer-form-field">
                            <label for="player-first-name"><?php _e('First Name', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="text" id="player-first-name" name="first_name" required>
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-last-name"><?php _e('Last Name', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="text" id="player-last-name" name="last_name" required>
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-dob"><?php _e('Date of Birth', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="date" id="player-dob" name="dob" required>
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-gender"><?php _e('Gender', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <select id="player-gender" name="gender" required>
                                <option value="male"><?php _e('Male', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                <option value="female"><?php _e('Female', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                <option value="other"><?php _e('Other', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-avs"><?php _e('AVS Number', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="text" id="player-avs" name="avs_number" placeholder="756.XXXX.XXXX.XX">
                        </div>
                        <div class="intersoccer-form-field intersoccer-form-field-full">
                            <label for="player-medical"><?php _e('Medical Conditions, Dietary Restrictions, and Allergies', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <textarea id="player-medical" name="medical_conditions" rows="3"></textarea>
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-emergency-contact"><?php _e('Emergency Contact', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="text" id="player-emergency-contact" name="emergency_contact">
                        </div>
                        <div class="intersoccer-form-field">
                            <label for="player-emergency-phone"><?php _e('Emergency Phone', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                            <input type="tel" id="player-emergency-phone" name="emergency_phone">
                        </div>
                    </div>
                    <div class="intersoccer-form-actions">
                        <button type="submit" class="button button-primary"><?php _e('Save Player', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button" id="cancel-player-form"><?php _e('Cancel', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></button>
                    </div>
                    <input type="hidden" id="player-id" name="player_id">
                    <input type="hidden" id="player-user-id" name="user_id">
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render rosters page
     */
    public function render_rosters_page() {
        ?>
        <div class="wrap intersoccer-admin-page">
            <h1><?php _e('Rosters & Export', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h1>

            <div class="intersoccer-export-section">
                <div class="intersoccer-export-tabs">
                    <button class="intersoccer-tab-button active" data-tab="quick-export">
                        <?php _e('Quick Export', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                    </button>
                    <button class="intersoccer-tab-button" data-tab="custom-export">
                        <?php _e('Custom Export', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                    </button>
                    <button class="intersoccer-tab-button" data-tab="scheduled-exports">
                        <?php _e('Scheduled Exports', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                    </button>
                </div>

                <!-- Quick Export Tab -->
                <div id="quick-export" class="intersoccer-tab-content active">
                    <h2><?php _e('Quick Export Options', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('Export commonly requested roster formats:', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                    
                    <div class="intersoccer-quick-exports">
                        <div class="intersoccer-export-card">
                            <h3><?php _e('All Camps', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                            <p><?php _e('Export all camp rosters with player details.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                            <button type="button" class="button button-primary export-camps-btn" data-export-type="camps">
                                <?php _e('Export Camps', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                            </button>
                        </div>

                        <div class="intersoccer-export-card">
                            <h3><?php _e('All Courses', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                            <p><?php _e('Export all course rosters with schedule details.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                            <button type="button" class="button button-primary export-courses-btn" data-export-type="courses">
                                <?php _e('Export Courses', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                            </button>
                        </div>

                        <div class="intersoccer-export-card">
                            <h3><?php _e('Complete Roster', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h3>
                            <p><?php _e('Export all players and events in one comprehensive file.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                            <button type="button" class="button button-primary export-all-btn" data-export-type="all">
                                <?php _e('Export All', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Custom Export Tab -->
                <div id="custom-export" class="intersoccer-tab-content">
                    <h2><?php _e('Custom Export', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    
                    <form id="custom-export-form">
                        <div class="intersoccer-form-grid">
                            <div class="intersoccer-form-field">
                                <label for="export-activity-type"><?php _e('Activity Type', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <select id="export-activity-type" name="activity_type" multiple>
                                    <option value="camp"><?php _e('Camps', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="course"><?php _e('Courses', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="birthday"><?php _e('Birthday Parties', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>

                            <div class="intersoccer-form-field">
                                <label for="export-season"><?php _e('Season', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <select id="export-season" name="season">
                                    <option value=""><?php _e('All Seasons', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="Spring 2025"><?php _e('Spring 2025', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="Summer 2025"><?php _e('Summer 2025', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="Autumn 2025"><?php _e('Autumn 2025', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="Winter 2025"><?php _e('Winter 2025', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>

                            <div class="intersoccer-form-field">
                                <label for="export-date-from"><?php _e('Start Date', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <input type="date" id="export-date-from" name="date_from">
                            </div>

                            <div class="intersoccer-form-field">
                                <label for="export-date-to"><?php _e('End Date', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <input type="date" id="export-date-to" name="date_to">
                            </div>

                            <div class="intersoccer-form-field">
                                <label for="export-venue"><?php _e('Venue', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <select id="export-venue" name="venue">
                                    <option value=""><?php _e('All Venues', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <?php
                                    // Get venues from database or WooCommerce attributes
                                    $venues = $this->get_venue_options();
                                    foreach ($venues as $venue):
                                    ?>
                                        <option value="<?php echo esc_attr($venue); ?>"><?php echo esc_html($venue); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="intersoccer-form-field">
                                <label for="export-format"><?php _e('Export Format', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></label>
                                <select id="export-format" name="format">
                                    <option value="excel"><?php _e('Excel (.xlsx)', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="csv"><?php _e('CSV', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                    <option value="pdf"><?php _e('PDF', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="intersoccer-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Generate Custom Export', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Scheduled Exports Tab -->
                <div id="scheduled-exports" class="intersoccer-tab-content">
                    <h2><?php _e('Scheduled Exports', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('Set up automatic exports that run on a schedule.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
                    <p><em><?php _e('Coming soon...', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></em></p>
                </div>
            </div>

            <!-- Export History -->
            <div class="intersoccer-export-history">
                <h2><?php _e('Export History', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Type', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Records', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Format', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('User', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Actions', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $export_history = $this->get_export_history();
                        if (empty($export_history)):
                        ?>
                            <tr>
                                <td colspan="6"><?php _e('No export history found.', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($export_history as $export): ?>
                                <tr>
                                    <td><?php echo esc_html($export['date']); ?></td>
                                    <td><?php echo esc_html($export['type']); ?></td>
                                    <td><?php echo esc_html($export['record_count']); ?></td>
                                    <td><?php echo esc_html($export['format']); ?></td>
                                    <td><?php echo esc_html($export['user_name']); ?></td>
                                    <td>
                                        <?php if (!empty($export['file_path']) && file_exists($export['file_path'])): ?>
                                            <a href="<?php echo esc_url($export['download_url']); ?>" class="button button-small">
                                                <?php _e('Download', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="intersoccer-file-expired"><?php _e('Expired', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export Progress Modal -->
        <div id="export-progress-modal" class="intersoccer-modal" style="display: none;">
            <div class="intersoccer-modal-content">
                <h2><?php _e('Exporting Data', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></h2>
                <div class="intersoccer-progress-bar">
                    <div class="intersoccer-progress-fill" style="width: 0%"></div>
                </div>
                <p id="export-status"><?php _e('Preparing export...', INTERSOCCER_PLAYER_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Helper methods
     */
    private function calculate_age($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    }

    private function get_gender_label($gender) {
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

    private function get_venue_options() {
        // This would typically fetch from WooCommerce product attributes
        // For now, return some example venues
        return array(
            'Geneva - Stade de Varembé (Nations)',
            'Geneva - Stade Chênois, Thonex',
            'Basel - Stadion Rankhof, Basel City',
            'Zurich - Sportanlage Riedholz',
            'Lausanne - Stade Pierre-de-Coubertin'
        );
    }

    private function get_export_history() {
        // This would fetch from a database table tracking exports
        // For now, return empty array
        return array();
    }

    // Additional methods would continue here...
    // Including: render_events_page(), render_settings_page(), render_tools_page()
    // handle_export_request(), process_completed_order(), etc.
}