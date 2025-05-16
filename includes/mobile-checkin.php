<?php
/**
 * Mobile Check-in
 * Changes:
 * - Added shortcode for mobile check-in interface for coaches.
 * - Implemented pagination and search for high-attendance events.
 * - Added REST API endpoint for real-time roster updates.
 * - Integrated venue details from CSV data.
 * - Ensured initialization on init to avoid translation issues.
 * Testing:
 * - Add [intersoccer_mobile_checkin] shortcode to a page, verify check-in interface loads for coaches.
 * - Test pagination for events with many attendees (e.g., Nyon Week 4).
 * - Search for a player, confirm results filter dynamically.
 * - Check in a player via AJAX, verify status updates in The Events Calendar.
 * - Test REST API endpoint (wp-json/intersoccer/v1/checkin/<event_id>), confirm roster updates.
 * - Ensure no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

add_action('init', function() {
    add_shortcode('intersoccer_mobile_checkin', 'intersoccer_mobile_checkin_shortcode');
});

function intersoccer_mobile_checkin_shortcode() {
    if (!current_user_can('edit_posts')) {
        return '<p>' . esc_html__('You must be a coach to access this page.', 'intersoccer-player-management') . '</p>';
    }

    wp_enqueue_script('intersoccer-mobile-checkin', plugin_dir_url(__FILE__) . '../js/mobile-checkin.js', ['jquery'], '1.0', true);
    wp_localize_script('intersoccer-mobile-checkin', 'intersoccerCheckin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('intersoccer_checkin_nonce'),
    ]);

    $events_per_page = 10;
    $current_page = max(1, absint($_GET['checkin_page'] ?? 1));
    $offset = ($current_page - 1) * $events_per_page;

    $cache_key = 'intersoccer_checkin_events_' . $current_page;
    $events = wp_cache_get($cache_key, 'intersoccer');
    if (false === $events) {
        $events = tribe_get_events([
            'posts_per_page' => $events_per_page,
            'offset' => $offset,
            'start_date' => current_time('Y-m-d'),
            'cache_results' => true,
        ]);
        wp_cache_set($cache_key, $events, 'intersoccer', 3600);
    }

    $total_events = count(tribe_get_events(['posts_per_page' => -1, 'start_date' => current_time('Y-m-d')]));
    $total_pages = ceil($total_events / $events_per_page);

    ob_start();
    ?>
    <div class="intersoccer-mobile-checkin">
        <h2><?php _e('Mobile Check-in', 'intersoccer-player-management'); ?></h2>
        <input type="text" id="checkin-search" placeholder="<?php _e('Search players...', 'intersoccer-player-management'); ?>">
        <ul class="event-list">
            <?php foreach ($events as $event): ?>
                <?php
                $venue_id = get_post_meta($event->ID, '_EventVenueID', true);
                $venue_name = tribe_get_venue($event->ID) ?: 'TBD';
                ?>
                <li>
                    <h3><?php echo esc_html($event->post_title); ?></h3>
                    <p><strong><?php _e('Venue:', 'intersoccer-player-management'); ?></strong> <?php echo esc_html($venue_name); ?></p>
                    <button class="button load-attendees" data-event-id="<?php echo esc_attr($event->ID); ?>"><?php _e('Load Attendees', 'intersoccer-player-management'); ?></button>
                    <div class="attendees-list" data-event-id="<?php echo esc_attr($event->ID); ?>"></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <div class="checkin-pagination">
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('checkin_page', $current_page - 1)); ?>" class="button"><?php _e('Previous', 'intersoccer-player-management'); ?></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?php echo esc_url(add_query_arg('checkin_page', $i)); ?>" class="button <?php echo $i === $current_page ? 'active' : ''; ?>"><?php echo esc_html($i); ?></a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(add_query_arg('checkin_page', $current_page + 1)); ?>" class="button"><?php _e('Next', 'intersoccer-player-management'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_action('rest_api_init', function() {
    register_rest_route('intersoccer/v1', '/checkin/(?P<event_id>\d+)', [
        'methods' => 'POST',
        'callback' => 'intersoccer_checkin_rest',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);
});

function intersoccer_checkin_rest(WP_REST_Request $request) {
    $event_id = $request['event_id'];
    $attendee_id = absint($request['attendee_id']);
    $checked_in = filter_var($request['checked_in'], FILTER_VALIDATE_BOOLEAN);

    if (!tribe_is_event($event_id)) {
        return new WP_Error('invalid_event', __('Invalid event.', 'intersoccer-player-management'), ['status' => 400]);
    }

    update_post_meta($attendee_id, 'checked_in', $checked_in ? 'yes' : 'no');
    wp_cache_delete('intersoccer_attendees_' . $event_id, 'intersoccer');
    return rest_ensure_response(['message' => $checked_in ? __('Player checked in.', 'intersoccer-player-management') : __('Player check-in removed.', 'intersoccer-player-management')]);
}
?>
