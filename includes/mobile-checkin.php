<?php
/**
 * Mobile Check-In Feature for Coaches and Event Organizers
 */

// Register the mobile check-in page
add_action('init', 'register_mobile_checkin_endpoint');
function register_mobile_checkin_endpoint() {
    add_rewrite_rule(
        'mobile-checkin/([^/]+)/?$',
        'index.php?mobile_checkin=1&event_id=$matches[1]',
        'top'
    );
}

add_filter('query_vars', 'mobile_checkin_query_vars');
function mobile_checkin_query_vars($vars) {
    $vars[] = 'mobile_checkin';
    $vars[] = 'event_id';
    return $vars;
}

add_action('template_redirect', 'render_mobile_checkin_page');
function render_mobile_checkin_page() {
    if (get_query_var('mobile_checkin') && get_query_var('event_id')) {
        // Check if user is logged in and has the correct role
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }

        $user = wp_get_current_user();
        if (!in_array('coach', (array) $user->roles) && !in_array('organizer', (array) $user->roles) && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'intersoccer-player-management'));
        }

        $event_id = absint(get_query_var('event_id'));
        $event = tribe_get_event($event_id);
        if (!$event) {
            wp_die(__('Invalid event ID.', 'intersoccer-player-management'));
        }

        // Handle check-in via AJAX
        if (isset($_POST['action']) && $_POST['action'] === 'checkin_player') {
            checkin_player();
            exit;
        }

        // Render the mobile check-in page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo esc_html($event->post_title); ?> - <?php _e('Mobile Check-In', 'intersoccer-player-management'); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #f4f4f4;
                }
                h1 {
                    font-size: 24px;
                    margin-bottom: 20px;
                }
                #search-bar {
                    width: 100%;
                    padding: 10px;
                    font-size: 16px;
                    margin-bottom: 20px;
                    box-sizing: border-box;
                }
                .player {
                    background-color: white;
                    padding: 10px;
                    margin-bottom: 10px;
                    border-radius: 5px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .player.checked-in {
                    background-color: #e0ffe0;
                }
                .player button {
                    padding: 5px 10px;
                    background-color: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                }
                .player button:disabled {
                    background-color: #cccccc;
                }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html($event->post_title); ?> - <?php _e('Mobile Check-In', 'intersoccer-player-management'); ?></h1>
            <input type="text" id="search-bar" placeholder="<?php _e('Search players...', 'intersoccer-player-management'); ?>">
            <div id="player-list">
                <?php
                $attendees = tribe_tickets_get_attendees($event_id);
                $players = array();
                foreach ($attendees as $attendee) {
                    $player_name = get_post_meta($attendee['ID'], 'player_name', true);
                    if ($player_name) {
                        $order_id = $attendee['order_id'];
                        $order = wc_get_order($order_id);
                        if (!$order) continue;

                        $user_id = $order->get_user_id();
                        if (!$user_id) continue;

                        $player_data = array(
                            'name' => $player_name,
                            'attendee_id' => $attendee['ID'],
                            'checked_in' => get_post_meta($attendee['ID'], 'checked_in', true) === 'yes',
                        );
                        $players[] = $player_data;
                    }
                }

                foreach ($players as $player) {
                    ?>
                    <div class="player <?php echo $player['checked_in'] ? 'checked-in' : ''; ?>" data-name="<?php echo esc_attr(strtolower($player['name'])); ?>">
                        <span><?php echo esc_html($player['name']); ?></span>
                        <button class="checkin-btn" data-attendee-id="<?php echo esc_attr($player['attendee_id']); ?>" <?php echo $player['checked_in'] ? 'disabled' : ''; ?>>
                            <?php echo $player['checked_in'] ? __('Checked In', 'intersoccer-player-management') : __('Check In', 'intersoccer-player-management'); ?>
                        </button>
                    </div>
                    <?php
                }
                ?>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Search functionality
                    const searchBar = document.getElementById('search-bar');
                    const players = document.querySelectorAll('.player');

                    searchBar.addEventListener('input', function() {
                        const searchTerm = searchBar.value.toLowerCase();
                        players.forEach(player => {
                            const playerName = player.getAttribute('data-name');
                            player.style.display = playerName.includes(searchTerm) ? 'flex' : 'none';
                        });
                    });

                    // Check-in functionality
                    document.querySelectorAll('.checkin-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const attendeeId = this.getAttribute('data-attendee-id');
                            const data = new FormData();
                            data.append('action', 'checkin_player');
                            data.append('attendee_id', attendeeId);

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: data
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    button.textContent = '<?php _e('Checked In', 'intersoccer-player-management'); ?>';
                                    button.disabled = true;
                                    button.parentElement.classList.add('checked-in');
                                } else {
                                    alert('<?php _e('Failed to check in player.', 'intersoccer-player-management'); ?>');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('<?php _e('An error occurred.', 'intersoccer-player-management'); ?>');
                            });
                        });
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// AJAX handler for checking in a player
add_action('wp_ajax_checkin_player', 'checkin_player');
function checkin_player() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error();
        return;
    }

    $attendee_id = isset($_POST['attendee_id']) ? absint($_POST['attendee_id']) : 0;
    if (!$attendee_id) {
        wp_send_json_error();
        return;
    }

    // Mark the attendee as checked in
    update_post_meta($attendee_id, 'checked_in', 'yes');
    update_post_meta($attendee_id, 'checkin_time', current_time('mysql'));

    wp_send_json_success();
}
?>

