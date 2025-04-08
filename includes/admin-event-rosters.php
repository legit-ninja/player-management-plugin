<?php
/**
 * Admin Feature: Event Rosters Tab for Generating Player Rosters
 */

// Handle export requests for event rosters
if (isset($_GET['export-roster'])) {
    $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
    if ($event_id) {
        export_event_roster($event_id);
        exit;
    }
}
?>

<!-- Event Rosters -->
<h2><?php _e('Event Rosters', 'intersoccer-player-management'); ?></h2>
<?php
// Fetch all Event Tickets events
$events = tribe_get_events(array(
    'posts_per_page' => -1,
    'post_status' => 'publish',
));

if (empty($events)) {
    echo '<p>' . __('No events found. Please create an event or generate events from variable products.', 'intersoccer-player-management') . '</p>';
} else {
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Event Name', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Registration End Date', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Status', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Actions', 'intersoccer-player-management'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $current_date = current_time('Y-m-d');
            foreach ($events as $event) {
                $event_id = $event->ID;
                $tickets = Tribe__Tickets__Tickets::get_event_tickets($event_id);
                $registration_end_date = '';

                // Get the latest ticket end date
                foreach ($tickets as $ticket) {
                    $end_date = get_post_meta($ticket->ID, '_ticket_end_date', true);
                    if ($end_date && (!$registration_end_date || $end_date > $registration_end_date)) {
                        $registration_end_date = $end_date;
                    }
                }

                $registration_closed = $registration_end_date && strtotime($registration_end_date) < strtotime($current_date);
                ?>
                <tr>
                    <td><?php echo esc_html($event->post_title); ?></td>
                    <td><?php echo esc_html($registration_end_date ?: __('Not Set', 'intersoccer-player-management')); ?></td>
                    <td><?php echo $registration_closed ? __('Closed', 'intersoccer-player-management') : __('Open', 'intersoccer-player-management'); ?></td>
                    <td>
                        <?php if ($registration_closed): ?>
                            <a href="#roster-<?php echo esc_attr($event_id); ?>" class="button generate-roster"><?php _e('Generate Roster', 'intersoccer-player-management'); ?></a>
                            <a href="<?php echo esc_url(home_url('/mobile-checkin/' . $event_id)); ?>" class="button"><?php _e('Mobile Check-In', 'intersoccer-player-management'); ?></a>
                        <?php else: ?>
                            <?php _e('Registration still open', 'intersoccer-player-management'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="roster-<?php echo esc_attr($event_id); ?>" style="display: none;">
                    <td colspan="4">
                        <?php
                        // Fetch attendees for this event
                        $attendees = tribe_tickets_get_attendees($event_id);
                        $roster = array();

                        foreach ($attendees as $attendee) {
                            $order_id = $attendee['order_id'];
                            $player_name = get_post_meta($attendee['ID'], 'player_name', true);
                            if (!$player_name) {
                                continue;
                            }

                            $order = wc_get_order($order_id);
                            if (!$order) {
                                continue;
                            }

                            $user_id = $order->get_user_id();
                            if (!$user_id) {
                                continue;
                            }

                            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
                            $player_data = null;
                            foreach ($players as $player) {
                                if ($player['name'] === $player_name) {
                                    $player_data = $player;
                                    break;
                                }
                            }

                            if ($player_data) {
                                $dob = $player_data['dob'];
                                $age = $dob ? date_diff(date_create($dob), date_create('today'))->y : 'N/A';
                                $roster[] = array(
                                    'name' => $player_name,
                                    'age' => $age,
                                    'medical_conditions' => $player_data['medical_conditions'],
                                );
                            }
                        }

                        if (empty($roster)) {
                            echo '<p>' . __('No players registered for this event.', 'intersoccer-player-management') . '</p>';
                        } else {
                            ?>
                            <h3><?php _e('Roster for', 'intersoccer-player-management'); ?> <?php echo esc_html($event->post_title); ?></h3>
                            <p><?php printf(__('Total Players: %d', 'intersoccer-player-management'), count($roster)); ?></p>
                            <a href="?page=intersoccer-players&tab=event-rosters&export-roster=csv&event_id=<?php echo esc_attr($event_id); ?>" class="button"><?php _e('Download CSV', 'intersoccer-player-management'); ?></a>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Player Name', 'intersoccer-player-management'); ?></th>
                                        <th><?php _e('Age', 'intersoccer-player-management'); ?></th>
                                        <th><?php _e('Medical Conditions', 'intersoccer-player-management'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roster as $player): ?>
                                        <tr>
                                            <td><?php echo esc_html($player['name']); ?></td>
                                            <td><?php echo esc_html($player['age']); ?></td>
                                            <td><?php echo esc_html($player['medical_conditions']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <script>
        jQuery(document).ready(function($) {
            $('.generate-roster').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                $(target).toggle();
            });
        });
    </script>
    <?php
}

// Export the event roster as CSV
function export_event_roster($event_id) {
    $event = tribe_get_event($event_id);
    if (!$event) {
        wp_die(__('Invalid event ID.', 'intersoccer-player-management'));
    }

    $attendees = tribe_tickets_get_attendees($event_id);
    $roster = array();

    foreach ($attendees as $attendee) {
        $order_id = $attendee['order_id'];
        $player_name = get_post_meta($attendee['ID'], 'player_name', true);
        if (!$player_name) {
            continue;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            continue;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            continue;
        }

        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
        $player_data = null;
        foreach ($players as $player) {
            if ($player['name'] === $player_name) {
                $player_data = $player;
                break;
            }
        }

        if ($player_data) {
            $dob = $player_data['dob'];
            $age = $dob ? date_diff(date_create($dob), date_create('today'))->y : 'N/A';
            $roster[] = array(
                'name' => $player_name,
                'age' => $age,
                'medical_conditions' => $player_data['medical_conditions'],
            );
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=event-roster-' . sanitize_title($event->post_title) . '-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, array(
        __('Player Name', 'intersoccer-player-management'),
        __('Age', 'intersoccer-player-management'),
        __('Medical Conditions', 'intersoccer-player-management'),
    ));

    foreach ($roster as $player) {
        fputcsv($output, array(
            $player['name'],
            $player['age'],
            $player['medical_conditions'],
        ));
    }

    fclose($output);
}
?>

