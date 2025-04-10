<?php
/**
 * Admin Feature: Event Rosters Tab for Viewing Event Attendees
 */

// Render the Event Rosters tab content
function intersoccer_render_event_rosters_tab() {
    // Filter parameters
    $filter_event_title = isset($_GET['event_title']) ? sanitize_text_field($_GET['event_title']) : '';
    $filter_date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '';
    $filter_venue = isset($_GET['venue']) ? sanitize_text_field($_GET['venue']) : '';

    // Fetch all events
    $event_args = array(
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'eventDisplay' => 'all',
    );

    // Apply date range filter
    if (!empty($filter_date_range)) {
        $dates = explode(' to ', $filter_date_range);
        if (count($dates) === 2) {
            $start_date = sanitize_text_field($dates[0]);
            $end_date = sanitize_text_field($dates[1]);
            $event_args['meta_query'] = array(
                array(
                    'key' => '_EventStartDate',
                    'value' => array($start_date . ' 00:00:00', $end_date . ' 23:59:59'),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ),
            );
        }
    }

    $events = tribe_get_events($event_args);

    // Filter events by title
    if (!empty($filter_event_title)) {
        $events = array_filter($events, function($event) use ($filter_event_title) {
            return stripos($event->post_title, $filter_event_title) !== false;
        });
    }

    // Filter events by venue
    if (!empty($filter_venue)) {
        $events = array_filter($events, function($event) use ($filter_venue) {
            $venue_id = get_post_meta($event->ID, '_EventVenueID', true);
            $venue = $venue_id ? get_post($venue_id) : null;
            return $venue && stripos($venue->post_title, $filter_venue) !== false;
        });
    }

    // Get unique venues for filter dropdown
    $venues = array();
    foreach (tribe_get_events(array('posts_per_page' => -1)) as $event) {
        $venue_id = get_post_meta($event->ID, '_EventVenueID', true);
        if ($venue_id) {
            $venue = get_post($venue_id);
            if ($venue && !in_array($venue->post_title, $venues)) {
                $venues[] = $venue->post_title;
            }
        }
    }
    sort($venues);

    ?>
    <h2><?php _e('Event Rosters', 'intersoccer-player-management'); ?></h2>

    <!-- Filters -->
    <form method="get" action="">
        <input type="hidden" name="page" value="intersoccer-players" />
        <input type="hidden" name="tab" value="event-rosters" />
        <p class="search-box">
            <label for="event_title"><?php _e('Event Title:', 'intersoccer-player-management'); ?></label>
            <input type="text" id="event_title" name="event_title" value="<?php echo esc_attr($filter_event_title); ?>" placeholder="<?php _e('Enter event title', 'intersoccer-player-management'); ?>" />

            <label for="date_range"><?php _e('Date Range:', 'intersoccer-player-management'); ?></label>
            <input type="text" id="date_range" name="date_range" value="<?php echo esc_attr($filter_date_range); ?>" placeholder="<?php _e('YYYY-MM-DD to YYYY-MM-DD', 'intersoccer-player-management'); ?>" />

            <label for="venue"><?php _e('Venue:', 'intersoccer-player-management'); ?></label>
            <select id="venue" name="venue">
                <option value=""><?php _e('All Venues', 'intersoccer-player-management'); ?></option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?php echo esc_attr($venue); ?>" <?php selected($filter_venue, $venue); ?>><?php echo esc_html($venue); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button" value="<?php _e('Filter', 'intersoccer-player-management'); ?>" />
            <a href="?page=intersoccer-players&tab=event-rosters" class="button"><?php _e('Clear Filters', 'intersoccer-player-management'); ?></a>
        </p>
    </form>

    <?php if (empty($events)): ?>
        <p><?php _e('No events found.', 'intersoccer-player-management'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Event Title', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Start Date', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Venue', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Attendees', 'intersoccer-player-management'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <?php
                    $attendees = tribe_tickets_get_attendees($event->ID);
                    $venue_id = get_post_meta($event->ID, '_EventVenueID', true);
                    $venue = $venue_id ? get_post($venue_id) : null;
                    $start_date = get_post_meta($event->ID, '_EventStartDate', true);
                    ?>
                    <tr>
                        <td><?php echo esc_html($event->post_title); ?></td>
                        <td><?php echo esc_html($start_date ? date('Y-m-d H:i', strtotime($start_date)) : 'N/A'); ?></td>
                        <td><?php echo esc_html($venue ? $venue->post_title : 'N/A'); ?></td>
                        <td>
                            <?php if (empty($attendees)): ?>
                                <?php _e('No attendees.', 'intersoccer-player-management'); ?>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($attendees as $attendee): ?>
                                        <?php
                                        $player_name = get_post_meta($attendee['ID'], 'player_name', true);
                                        $order_id = $attendee['order_id'];
                                        $order = wc_get_order($order_id);
                                        $user_email = $order ? $order->get_billing_email() : 'N/A';
                                        ?>
                                        <li><?php echo esc_html($player_name ? $player_name : 'N/A'); ?> (<?php echo esc_html($user_email); ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <style>
        .search-box {
            margin-bottom: 20px;
        }
        .search-box label {
            margin-right: 10px;
        }
        .search-box input[type="text"],
        .search-box select {
            margin-right: 10px;
            padding: 5px;
            width: 200px;
        }
        .wp-list-table ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
    <?php
}
?>
