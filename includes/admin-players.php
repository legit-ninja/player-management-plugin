<?php
/**
 * Admin Feature: Manage Players, Courses Report, Sync Products to Events, and Event Rosters
 *
 * This file handles the admin menu page for the InterSoccer Player Management plugin.
 * It defines tabs dynamically, includes necessary files, and renders content based on the selected tab.
 *
 * @package InterSoccer_Player_Management
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu page
add_action('admin_menu', 'intersoccer_admin_menu');

/**
 * Registers the "Players & Orders" menu page in the WordPress admin menu.
 */
function intersoccer_admin_menu() {
    add_menu_page(
        __('Player Management', 'intersoccer-player-management'), // Page title
        __('Players & Orders', 'intersoccer-player-management'), // Menu title
        'manage_options', // Capability required (admin-level access)
        'intersoccer-players', // Menu slug
        'intersoccer_players_admin_page', // Callback function
        'dashicons-groups', // Icon
        56 // Position
    );
}

/**
 * Renders the admin page for the "Players & Orders" menu.
 * Dynamically handles tabs and includes their respective files and render functions.
 */
function intersoccer_players_admin_page() {
    global $pagenow;

    // Verify we're on the correct admin page
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($pagenow !== 'admin.php' || $current_page !== 'intersoccer-players') {
        return;
    }

    // Define tabs with slugs, labels, files, and render functions
    $tabs = array(
        'players' => array(
            'label' => __('All Players', 'intersoccer-player-management'),
            'file' => 'admin-players-all.php',
            'render_function' => 'intersoccer_render_players_all_tab',
        ),
        'courses-report' => array(
            'label' => __('Courses Report', 'intersoccer-player-management'),
            'file' => 'admin-courses-report.php',
            'render_function' => 'intersoccer_render_courses_report_tab',
        ),
        'camps-report' => array(
            'label' => __('Camps Report', 'intersoccer-player-management'),
            'file' => 'admin-camps-report.php',
            'render_function' => 'intersoccer_render_camps_report_tab',
        ),
        'sync-products' => array(
            'label' => __('Sync Products to Events', 'intersoccer-player-management'),
            'file' => 'admin-sync-products.php',
            'render_function' => 'intersoccer_render_sync_products_tab',
        ),
        'event-rosters' => array(
            'label' => __('Event Rosters', 'intersoccer-player-management'),
            'file' => 'admin-event-rosters.php',
            'render_function' => 'intersoccer_render_event_rosters_tab',
        ),
        'advanced' => array(
            'label' => __('Advanced', 'intersoccer-player-management'),
            'file' => 'admin-advanced.php',
            'render_function' => 'intersoccer_render_advanced_tab',
        ),
    );

    // Include tab files dynamically
    foreach ($tabs as $tab_slug => $tab_data) {
        $file_path = plugin_dir_path(__FILE__) . $tab_data['file'];
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            error_log(sprintf('InterSoccer: Failed to include admin file: %s', $file_path));
        }
    }

    // Determine the current tab, default to 'players'
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'players';

    // Validate the current tab exists, fallback to 'players' if invalid
    if (!isset($tabs[$current_tab])) {
        $current_tab = 'players';
    }

    // Check if the render function exists for the current tab
    if (!function_exists($tabs[$current_tab]['render_function'])) {
        echo '<div class="error"><p>' . esc_html(sprintf(
            __('Error: The render function for the "%s" tab is not defined.', 'intersoccer-player-management'),
            $current_tab
        )) . '</p></div>';
        return;
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(__('Player Management', 'intersoccer-player-management')); ?></h1>

        <!-- Dynamic Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_slug => $tab_data) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_slug, admin_url('admin.php?page=intersoccer-players'))); ?>"
                   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_data['label']); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <!-- Render the current tab content -->
        <?php call_user_func($tabs[$current_tab]['render_function']); ?>
    </div>
    <?php
}
?>

