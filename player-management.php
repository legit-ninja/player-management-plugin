<?php
/**
 * Plugin Name: Player Management
 * Plugin URI: https://github.com/legit-ninja/player-management-plugin
 * Description: Manages players for InterSoccer events, integrating with WooCommerce My Account page and providing an admin dashboard.
 * Version: 1.11.11
 * Author: Jeremy Lee
 * Author URI: https://underdogunlimited.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: player-management
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * Network: false
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('PLAYER_MANAGEMENT_VERSION', '1.11.11');
define('PLAYER_MANAGEMENT_PATH', plugin_dir_path(__FILE__));
define('PLAYER_MANAGEMENT_URL', plugin_dir_url(__FILE__));

// Load translation
add_action('init', function () {
    $locale = determine_locale();
    $mofile = sprintf('%s-%s.mo', 'player-management', $locale);
    
    // Try to load from plugin directory first
    $plugin_mofile = PLAYER_MANAGEMENT_PATH . 'languages/' . $mofile;
    
    if (file_exists($plugin_mofile)) {
        load_textdomain('player-management', $plugin_mofile);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Loaded translation from plugin directory | Locale: ' . $locale . ' | File: ' . $plugin_mofile);
        }
    } else {
        // Fallback to standard loading (checks global dir)
        load_plugin_textdomain('player-management', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Translation file NOT found in plugin directory | Locale: ' . $locale . ' | Checked: ' . $plugin_mofile);
        }
    }
});

// Check if WooCommerce is active (required)
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Player Management requires WooCommerce to be installed and active.', 'player-management'); ?></p>
        </div>
        <?php
    });
    return;
}

// Include core files (check if they exist first)
$core_files = [
    'includes/player-management.php',
    'includes/ajax-handlers.php', 
    'includes/data-deletion.php'
];

foreach ($core_files as $file) {
    $file_path = PLAYER_MANAGEMENT_PATH . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Log missing file in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer Plugin: Missing core file: $file");
        }
    }
}

// Include admin files only in admin context
if (is_admin()) {
    $admin_files = [
        'includes/admin-players.php',
        'includes/admin-advanced.php',
        'includes/user-profile-players.php'
    ];
    
    foreach ($admin_files as $file) {
        $file_path = PLAYER_MANAGEMENT_PATH . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // Log missing file in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Plugin: Missing admin file: $file");
            }
        }
    }
}

// Add endpoint for manage-players with WPML support
add_action('init', function () {
    // Register the slug string with WPML for translation
    if (function_exists('icl_register_string')) {
        icl_register_string('WordPress', 'URL manage-players slug', 'manage-players');
    }
    
    // Register ALL language versions of the endpoint, not just current language
    // This is critical for WordPress to recognize the endpoint in all languages
    $endpoint_slugs = [
        'en' => 'manage-players',
        'fr' => 'gerer-participants',
        'de' => 'teilnehmer-verwalten',
    ];
    
    // Get current language from WPML
    $current_lang = apply_filters('wpml_current_language', null);
    
    // Register all endpoint slugs
    foreach ($endpoint_slugs as $lang => $slug) {
        add_rewrite_endpoint($slug, EP_ROOT | EP_PAGES);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'InterSoccer Player Management: Registered endpoint | Lang: %s | Slug: %s | Current: %s',
                $lang,
                $slug,
                $lang === $current_lang ? 'YES' : 'no'
            ));
        }
    }
});

// Add content rendering for all endpoint language versions
function intersoccer_render_manage_players_content() {
    // Prevent duplicate rendering with static flag
    static $already_rendered = false;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'InterSoccer Player Management: Endpoint content called | Already rendered: %s | Current URL: %s',
            $already_rendered ? 'YES' : 'no',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
    }
    
    if ($already_rendered) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: Content already rendered, skipping duplicate.');
        }
        return;
    }
    
    $already_rendered = true;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer Player Management: Rendering player management form via endpoint hook.');
    }
    
    if (function_exists('intersoccer_render_players_form')) {
        echo intersoccer_render_players_form();
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer Player Management: ERROR - intersoccer_render_players_form() function not found!');
        }
        echo '<p>Player management functionality is not available.</p>';
    }
}

// Hook content rendering to all language versions of the endpoint
add_action('woocommerce_account_manage-players_endpoint', 'intersoccer_render_manage_players_content');
add_action('woocommerce_account_gerer-participants_endpoint', 'intersoccer_render_manage_players_content');
add_action('woocommerce_account_teilnehmer-verwalten_endpoint', 'intersoccer_render_manage_players_content');

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('InterSoccer Player Management: Registered endpoint content hooks for: manage-players, gerer-participants, teilnehmer-verwalten');
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, function () {
    // Register all language versions of the endpoint
    $endpoint_slugs = ['manage-players', 'gerer-participants', 'teilnehmer-verwalten'];
    foreach ($endpoint_slugs as $slug) {
        add_rewrite_endpoint($slug, EP_ROOT | EP_PAGES);
    }
    flush_rewrite_rules();
    
    // Set version option
    add_option('intersoccer_player_management_version', PLAYER_MANAGEMENT_VERSION);
    
    // Create database tables if needed
    intersoccer_create_plugin_tables();
});

// Flush rewrite rules on deactivation
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// Add manage-players to My Account menu with translated slug
add_filter('woocommerce_account_menu_items', function ($items) {
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
    
    // Get the menu label and register/translate it with WPML
    $label = __('Manage Players', 'player-management');
    
    // Register the string with WPML for translation
    if (function_exists('icl_register_string') || (defined('ICL_SITEPRESS_VERSION') && ICL_SITEPRESS_VERSION)) {
        do_action(
            'wpml_register_single_string',
            'Player Management',
            'woocommerce_account_menu_manage_players',
            $label
        );
        
        // Get the translated label
        $label = apply_filters(
            'wpml_translate_single_string',
            $label,
            'Player Management',
            'woocommerce_account_menu_manage_players'
        );
    }
    
    $new_items = [];
    $inserted = false;
    foreach ($items as $key => $item_label) {
        // Preserve original labels - don't modify them
        $new_items[$key] = $item_label;
        if ($key === 'dashboard' && !$inserted) {
            $new_items[$endpoint_slug] = $label;
            $inserted = true;
        }
    }
    if (!$inserted) {
        $new_items[$endpoint_slug] = $label;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'InterSoccer Player Management: Menu item added | Language: %s | Slug: %s | Label: %s',
            $current_lang ?: 'default',
            $endpoint_slug,
            $label
        ));
    }
    
    return $new_items;
}, 10);

// Add endpoint title for translated page title
function intersoccer_player_management_endpoint_title($title, $post_id = null) {
    // Prevent recursion with static flag
    static $is_filtering = false;
    if ($is_filtering) {
        return $title;
    }
    
    global $wp_query;
    
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
    
    // Check if we're on the manage-players endpoint (check both translated and default)
    if (is_wc_endpoint_url($endpoint_slug) || 
        is_wc_endpoint_url('manage-players') || 
        (isset($wp_query->query_vars[$endpoint_slug])) ||
        (isset($wp_query->query_vars['manage-players']))) {
        
        $is_filtering = true;
        $title = __('Manage Your Attendees', 'player-management');
        $is_filtering = false;
    }
    
    return $title;
}
add_filter('the_title', 'intersoccer_player_management_endpoint_title', 10, 2);

// Add custom roles
add_action('init', function () {
    if (!get_role('coach')) {
        add_role('coach', __('Coach', 'player-management'), ['read' => true, 'edit_posts' => true]);
    }
    if (!get_role('organizer')) {
        add_role('organizer', __('Organizer', 'player-management'), ['read' => true, 'edit_posts' => true]);
    }
});

// Ensure endpoint is recognized by Elementor and WooCommerce with translated slug
add_filter('woocommerce_get_query_vars', function ($query_vars) {
    // Register ALL language versions so WooCommerce recognizes them all
    $endpoint_slugs = [
        'en' => 'manage-players',
        'fr' => 'gerer-participants',
        'de' => 'teilnehmer-verwalten',
    ];
    
    foreach ($endpoint_slugs as $lang => $slug) {
        $query_vars[$slug] = $slug;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer Player Management: Registered query vars for all languages: ' . implode(', ', array_values($endpoint_slugs)));
    }
    
    return $query_vars;
});

// Flush permalinks on activation
add_action('init', function () {
    if (get_option('player_management_flush_permalinks')) {
        flush_rewrite_rules();
        delete_option('player_management_flush_permalinks');
    }
});

/**
 * Create database tables for future features
 */
function intersoccer_create_plugin_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Player events table for future gamification
    $table_name = $wpdb->prefix . 'intersoccer_player_events';
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        player_index int(11) NOT NULL,
        player_name varchar(255) NOT NULL,
        order_id bigint(20) NOT NULL,
        item_id bigint(20) NOT NULL,
        event_type varchar(50) NOT NULL,
        event_name varchar(255) NOT NULL,
        event_start date,
        event_end date,
        venue varchar(255),
        status varchar(50) DEFAULT 'registered',
        registration_date datetime DEFAULT CURRENT_TIMESTAMP,
        completion_date datetime,
        attendance_days int(11) DEFAULT 0,
        total_days int(11) DEFAULT 0,
        attendance_percentage decimal(5,2),
        points_earned int(11) DEFAULT 0,
        notes text,
        PRIMARY KEY (id),
        KEY user_player (user_id, player_index),
        KEY order_item (order_id, item_id),
        KEY event_type (event_type),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Helper function to get player event count (fallback if file missing)
 */
if (!function_exists('intersoccer_get_player_event_count')) {
    function intersoccer_get_player_event_count($user_id, $player_index) {
        // Simple fallback - count from orders
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => -1
        ]);
        
        $count = 0;
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        
        if (!isset($players[$player_index])) {
            return 0;
        }
        
        $player_name = ($players[$player_index]['first_name'] ?? '') . ' ' . ($players[$player_index]['last_name'] ?? '');
        
        foreach ($orders as $order) {
            $order_players = $order->get_meta('intersoccer_players', true);
            if ($order_players && in_array($player_name, (array)$order_players)) {
                $count++;
            }
        }
        
        return $count;
    }
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function () {
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
    
    // Check for both translated slug and default slug
    $is_manage_players_page = is_account_page() && (
        isset($_GET[$endpoint_slug]) || 
        isset($_GET['manage-players']) ||
        strpos($_SERVER['REQUEST_URI'], $endpoint_slug) !== false ||
        strpos($_SERVER['REQUEST_URI'], 'manage-players') !== false
    );
    
    if ($is_manage_players_page) {
        $user_id = get_current_user_id();
        if ($user_id) {
            // Fetch player data for preloading
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
            $preload_players = [];
            foreach ($players as $index => $player) {
                $preload_players[$index] = [
                    'first_name' => $player['first_name'] ?? 'N/A',
                    'last_name' => $player['last_name'] ?? 'N/A',
                    'dob' => $player['dob'] ?? 'N/A',
                    'gender' => $player['gender'] ?? 'N/A',
                    'avs_number' => $player['avs_number'] ?? 'N/A',
                    'event_count' => intersoccer_get_player_event_count($user_id, $index) ?? 0,
                    'medical_conditions' => $player['medical_conditions'] ?? '',
                    'creation_timestamp' => $player['creation_timestamp'] ?? '',
                    'user_id' => $user_id,
                    'canton' => get_user_meta($user_id, 'billing_state', true) ?: '',
                    'city' => get_user_meta($user_id, 'billing_city', true) ?: '',
                ];
            }

            // Enqueue styles (check if files exist)
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'css/player-management.css')) {
                wp_enqueue_style(
                    'intersoccer-player-management',
                    PLAYER_MANAGEMENT_URL . 'css/player-management.css',
                    [],
                    PLAYER_MANAGEMENT_VERSION
                );
            }
            
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'css/loading.css')) {
                wp_enqueue_style(
                    'intersoccer-loading',
                    PLAYER_MANAGEMENT_URL . 'css/loading.css',
                    [],
                    PLAYER_MANAGEMENT_VERSION
                );
            }

            // Enqueue scripts (check if files exist)
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management-core.js')) {
                wp_enqueue_script(
                    'intersoccer-player-management-core',
                    PLAYER_MANAGEMENT_URL . 'js/player-management-core.js',
                    ['jquery'],
                    PLAYER_MANAGEMENT_VERSION,
                    true
                );
            }
            
            if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/player-management-actions.js')) {
                wp_enqueue_script(
                    'intersoccer-player-management-actions',
                    PLAYER_MANAGEMENT_URL . 'js/player-management-actions.js',
                    ['intersoccer-player-management-core'],
                    PLAYER_MANAGEMENT_VERSION,
                    true
                );
            }

            // Localize script
            $localize_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_player_nonce'),
                'user_id' => $user_id,
                'is_admin' => current_user_can('manage_options') ? '1' : '0',
                'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
                'preload_players' => $preload_players,
                'server_time' => current_time('mysql'),
                'version' => PLAYER_MANAGEMENT_VERSION,
                // Gender translations for JavaScript
                'i18n' => [
                    'gender' => [
                        'male' => __('Male', 'player-management'),
                        'female' => __('Female', 'player-management'),
                        'other' => __('Other', 'player-management'),
                    ],
                ],
            ];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Localizing intersoccerPlayer data: ' . json_encode($localize_data));
            }
            
            wp_localize_script(
                'intersoccer-player-management-core',
                'intersoccerPlayer',
                $localize_data
            );
        }
    }
});

add_action('admin_enqueue_scripts', function($hook) {
    // Only load scripts for admin dashboard pages, not user profile pages
    // User profile pages are handled by user-profile-players.php
    if (strpos($hook, 'intersoccer-players') !== false) {
        // Enqueue for dashboard
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        
        if (file_exists(PLAYER_MANAGEMENT_PATH . 'js/admin.js')) {
            wp_enqueue_script('intersoccer-admin-js', PLAYER_MANAGEMENT_URL . 'js/admin.js', ['jquery', 'flatpickr'], PLAYER_MANAGEMENT_VERSION, true);
            wp_localize_script('intersoccer-admin-js', 'intersoccerPlayer', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_player_nonce'),
                'version' => PLAYER_MANAGEMENT_VERSION
            ]);
        }
    }
});

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=intersoccer-players') . '">' . __('Settings', 'player-management') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Plugin status check for debugging
add_action('admin_notices', function() {
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
        $missing_files = [];
        
        // Check required files
        $required_files = [
            'includes/admin-players.php',
            'js/player-management-core.js'
        ];
        
        foreach ($required_files as $file) {
            if (!file_exists(PLAYER_MANAGEMENT_PATH . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (!empty($missing_files)) {
            ?>
            <div class="notice notice-warning">
                <p><strong><?php esc_html_e('Player Management:', 'player-management'); ?></strong> <?php printf(esc_html__('Missing files detected: %s', 'player-management'), implode(', ', $missing_files)); ?></p>
            </div>
            <?php
        }
    }
});
?>