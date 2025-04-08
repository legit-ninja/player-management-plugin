<?php
/**
 * Admin Feature: Manage Players, Courses Report, Sync Products to Events, and Event Rosters
 */

// Add admin menu page
add_action('admin_menu', 'intersoccer_admin_menu');
function intersoccer_admin_menu() {
    $user = wp_get_current_user();
    $is_coach_or_organizer = in_array('coach', (array) $user->roles) || in_array('organizer', (array) $user->roles);

    // Define the capability based on user role
    $capability = $is_coach_or_organizer ? 'edit_posts' : 'manage_options';

    add_menu_page(
        __('Player Management', 'intersoccer-player-management'),
        __('Players & Orders', 'intersoccer-player-management'),
        $capability,
        'intersoccer-players',
        'intersoccer_players_admin_page',
        'dashicons-groups',
        56
    );
}

// Restrict menu visibility for coaches and organizers
add_action('admin_menu', 'restrict_coach_organizer_menu_access', 999);
function restrict_coach_organizer_menu_access() {
    $user = wp_get_current_user();
    if (in_array('coach', (array) $user->roles) || in_array('organizer', (array) $user->roles)) {
        // Remove all menu items except "Players & Orders"
        global $menu;
        $allowed_menu = 'intersoccer-players';
        foreach ($menu as $key => $item) {
            if (isset($item[2]) && $item[2] !== $allowed_menu) {
                remove_menu_page($item[2]);
            }
        }

        // Remove all submenu items except those under "Players & Orders"
        global $submenu;
        foreach ($submenu as $parent => $subitems) {
            if ($parent !== $allowed_menu) {
                remove_submenu_page($parent, $parent);
            }
        }
    }
}

// Redirect coaches and organizers to the "Players and Orders" page on admin dashboard access
add_action('admin_init', 'redirect_coach_organizer_to_players_page');
function redirect_coach_organizer_to_players_page() {
    $user = wp_get_current_user();
    if (in_array('coach', (array) $user->roles) || in_array('organizer', (array) $user->roles)) {
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $players_page = 'intersoccer-players';

        // Redirect if not already on the "Players and Orders" page
        if ($current_page !== $players_page && !wp_doing_ajax()) {
            wp_safe_redirect(admin_url('admin.php?page=intersoccer-players'));
            exit;
        }
    }
}

// Render the admin page
function intersoccer_players_admin_page() {
    // Determine the current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'players';

    // Include tab-specific files
    $tabs = array(
        'players' => 'admin-players-all.php',
        'courses-report' => 'admin-courses-report.php',
        'camps-report' => 'admin-camps-report.php',
        'sync-products' => 'admin-sync-products.php',
        'event-rosters' => 'admin-event-rosters.php',
    );

    // Validate the current tab
    if (!array_key_exists($current_tab, $tabs)) {
        $current_tab = 'players';
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Player Management', 'intersoccer-player-management'); ?></h1>

        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=intersoccer-players&tab=players" class="nav-tab <?php echo $current_tab === 'players' ? 'nav-tab-active' : ''; ?>"><?php _e('All Players', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=courses-report" class="nav-tab <?php echo $current_tab === 'courses-report' ? 'nav-tab-active' : ''; ?>"><?php _e('Courses Report', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=camps-report" class="nav-tab <?php echo $current_tab === 'camps-report' ? 'nav-tab-active' : ''; ?>"><?php _e('Camps Report', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=sync-products" class="nav-tab <?php echo $current_tab === 'sync-products' ? 'nav-tab-active' : ''; ?>"><?php _e('Sync Products to Events', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=event-rosters" class="nav-tab <?php echo $current_tab === 'event-rosters' ? 'nav-tab-active' : ''; ?>"><?php _e('Event Rosters', 'intersoccer-player-management'); ?></a>
        </h2>

        <?php
        // Include the appropriate tab file
        require_once plugin_dir_path(__FILE__) . $tabs[$current_tab];
        ?>
    </div>
    <?php
}
?>

