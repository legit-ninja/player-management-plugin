<?php
/**
 * Admin Feature: Advanced Tab
 * Handles advanced operations like purging old player data, exporting CSV, and reconciling products to events.
 *
 * @package InterSoccer_Player_Management
 */

// Prevent direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// Handle advanced actions on admin_init
add_action('admin_init', 'intersoccer_advanced_tab_init');
/**
 * Handles form submissions for advanced operations.
 */
function intersoccer_advanced_tab_init() {
    // Purge children older than 13
    if (isset($_POST['purge_old_players']) && !empty($_POST['purge_old_players_nonce']) && wp_verify_nonce($_POST['purge_old_players_nonce'], 'purge_old_players_action')) {
        intersoccer_purge_old_players();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Old player data purged successfully.', 'intersoccer-player-management') . '</p></div>';
        });
    }

    // Export master CSV
    if (isset($_POST['export_master_csv']) && !empty($_POST['export_master_csv_nonce']) && wp_verify_nonce($_POST['export_master_csv_nonce'], 'export_master_csv_action')) {
        intersoccer_export_master_csv();
    }

    // Reconcile products to events
    if (isset($_POST['reconcile_products']) && !empty($_POST['reconcile_products_nonce']) && wp_verify_nonce($_POST['reconcile_products_nonce'], 'reconcile_products_action')) {
        intersoccer_reconcile_products_to_events();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Product reconciliation started.', 'intersoccer-player-management') . '</p></div>';
        });
    }
}

/**
 * Purges player data for children older than 13.
 */
function intersoccer_purge_old_players() {
    $users = get_users(array(
        'meta_key' => 'intersoccer_players',
        'meta_compare' => 'EXISTS',
    ));

    foreach ($users as $user) {
        $players = get_user_meta($user->ID, 'intersoccer_players', true);
        if (is_array($players)) {
            $filtered_players = array_filter($players, function($player) {
                $dob = new DateTime($player['dob']);
                $today = new DateTime();
                $age = $today->diff($dob)->y;
                return $age <= 13;
            });
            update_user_meta($user->ID, 'intersoccer_players', array_values($filtered_players));
        }
    }
}

/**
 * Exports a CSV file containing all player data.
 */
function intersoccer_export_master_csv() {
    $users = get_users(array(
        'meta_key' => 'intersoccer_players',
        'meta_compare' => 'EXISTS',
    ));

    $csv_data = array();
    $csv_data[] = array('User ID', 'Player Name', 'Date of Birth', 'Medical Conditions', 'Consent File');

    foreach ($users as $user) {
        $players = get_user_meta($user->ID, 'intersoccer_players', true);
        if (is_array($players)) {
            foreach ($players as $player) {
                $csv_data[] = array(
                    $user->ID,
                    isset($player['name']) ? $player['name'] : '',
                    isset($player['dob']) ? $player['dob'] : '',
                    isset($player['medical_conditions']) ? $player['medical_conditions'] : '',
                    isset($player['consent_file']) ? $player['consent_file'] : '',
                );
            }
        }
    }

    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="intersoccer_players.csv"');
    $fp = fopen('php://output', 'wb');
    foreach ($csv_data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

/**
 * Reconciles all products to events based on predefined rules.
 */
function intersoccer_reconcile_products_to_events() {
    // Placeholder for reconciliation logic
    // Example: Sync WooCommerce products to events based on attributes or categories
    $products = wc_get_products(array('limit' => -1)); // Requires WooCommerce
    foreach ($products as $product) {
        // Define your reconciliation rules here, e.g., match product category to event
        // Update product meta or event associations as needed
    }
    // Note: This is a placeholder. Replace with actual logic based on your requirements.
}

// Render the Advanced tab content
function intersoccer_render_advanced_tab() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Advanced Settings', 'intersoccer-player-management'); ?></h1>

        <!-- Purge Old Players -->
        <h2><?php echo esc_html__('Purge Old Player Data', 'intersoccer-player-management'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('purge_old_players_action', 'purge_old_players_nonce'); ?>
            <p><?php echo esc_html__('Delete player data for children older than 13 years.', 'intersoccer-player-management'); ?></p>
            <input type="submit" name="purge_old_players" class="button button-primary" value="<?php echo esc_attr__('Purge Old Players', 'intersoccer-player-management'); ?>" />
        </form>

        <!-- Export Master CSV -->
        <h2><?php echo esc_html__('Export Master CSV', 'intersoccer-player-management'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('export_master_csv_action', 'export_master_csv_nonce'); ?>
            <p><?php echo esc_html__('Export all player data to a CSV file.', 'intersoccer-player-management'); ?></p>
            <input type="submit" name="export_master_csv" class="button button-primary" value="<?php echo esc_attr__('Export CSV', 'intersoccer-player-management'); ?>" />
        </form>

        <!-- Reconcile Products to Events -->
        <h2><?php echo esc_html__('Reconcile Products to Events', 'intersoccer-player-management'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('reconcile_products_action', 'reconcile_products_nonce'); ?>
            <p><?php echo esc_html__('Re-sync all products to events based on predefined rules.', 'intersoccer-player-management'); ?></p>
            <input type="submit" name="reconcile_products" class="button button-primary" value="<?php echo esc_attr__('Reconcile All Products', 'intersoccer-player-management'); ?>" />
        </form>
    </div>
    <?php
}
?>

