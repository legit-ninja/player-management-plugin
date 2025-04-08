<?php
/**
 * Data Deletion Feature for GDPR Compliance
 */

// Register the shortcode for the delete data button
add_shortcode('delete_data_button', 'render_delete_data_button');
function render_delete_data_button() {
    if (!is_user_logged_in()) {
        return '<p>' . sprintf(__('Please <a href="%s">log in</a> to delete your data.', 'intersoccer-player-management'), esc_url(wp_login_url())) . '</p>';
    }

    ob_start();
    ?>
    <form method="post" id="delete-data-form">
        <p><?php _e('Click below to request deletion of your account and all associated data. This action cannot be undone.', 'intersoccer-player-management'); ?></p>
        <label for="confirm_deletion">
            <input type="checkbox" id="confirm_deletion" name="confirm_deletion" required>
            <?php _e('I confirm that I want to delete my account and all associated data.', 'intersoccer-player-management'); ?>
        </label>
        <br><br>
        <button type="submit" name="delete_data_request" style="background-color: #ff4444; color: white; padding: 10px 20px; border: none; cursor: pointer;"><?php _e('Delete My Account', 'intersoccer-player-management'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}

// Process the data deletion request
add_action('template_redirect', 'process_delete_data_request');
function process_delete_data_request() {
    if (isset($_POST['delete_data_request']) && is_user_logged_in() && isset($_POST['confirm_deletion'])) {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $user_email = $user->user_email;

        // Log the deletion request for audit purposes
        error_log(sprintf(__('GDPR Data Deletion: User ID %d (%s) requested account deletion on %s.', 'intersoccer-player-management'), $user_id, $user_email, date('Y-m-d H:i:s')));

        // Notify admin (optional)
        $admin_email = get_option('admin_email');
        $subject = __('GDPR Data Deletion Request', 'intersoccer-player-management');
        $message = sprintf(__('User ID %d (%s) has requested account deletion on %s.', 'intersoccer-player-management'), $user_id, $user_email, date('Y-m-d H:i:s'));
        wp_mail($admin_email, $subject, $message);

        // Delete plugin-specific data
        // 1. Delete intersoccer_players user meta
        delete_user_meta($user_id, 'intersoccer_players');

        // 2. Delete event registrations (attendees in Event Tickets)
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('wc-completed', 'wc-processing'),
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $player_name = $item->get_meta('Player');
                if ($player_name) {
                    // Find events associated with this order
                    $events = tribe_get_events(array(
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                    ));
                    foreach ($events as $event) {
                        $attendees = tribe_tickets_get_attendees($event->ID);
                        foreach ($attendees as $attendee) {
                            $attendee_player_name = get_post_meta($attendee['ID'], 'player_name', true);
                            if ($attendee_player_name === $player_name) {
                                // Delete the attendee record
                                wp_delete_post($attendee['ID'], true);
                            }
                        }
                    }
                }
            }
        }

        // Delete the user account
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id); // Deletes user; no reassignment needed as orders remain for audit purposes

        // Log out the user
        wp_logout();

        // Redirect to a confirmation page
        $confirmation_page = get_page_by_path('data-deletion-confirmed');
        if ($confirmation_page) {
            wp_redirect(get_permalink($confirmation_page));
        } else {
            wp_redirect(add_query_arg('data-deleted', 'true', home_url()));
        }
        exit;
    }
}

// Display a confirmation message if redirected to homepage with data-deleted query arg
add_action('wp', 'display_data_deletion_confirmation');
function display_data_deletion_confirmation() {
    if (isset($_GET['data-deleted']) && $_GET['data-deleted'] === 'true') {
        add_action('wp_footer', function() {
            echo '<div style="position: fixed; bottom: 20px; left: 20px; background-color: #ff4444; color: white; padding: 10px 20px; border-radius: 5px;">';
            echo '<p>' . __('Your account and all associated data have been deleted.', 'intersoccer-player-management') . '</p>';
            echo '</div>';
        });
    }
}
?>

