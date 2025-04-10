<?php
/**
 * Logic for syncing a WooCommerce product or variation to an Event Tickets event.
 */

// Function to sync a single product/variation to an event
function sync_product_to_event($product_id, &$sync_results, $is_variation = false) {
    if ($is_variation) {
        $variation_id = $product_id;
        $variation_obj = wc_get_product($variation_id);
        $parent_product = wc_get_product($variation_obj->get_parent_id());
        $base_event_title = $parent_product->get_name();
        $event_description = $parent_product->get_description() ?: $parent_product->get_short_description() ?: '';
        $variation_attributes = $variation_obj->get_attributes();
        $meta_key = 'variation_' . $variation_id;
        $item_name = $base_event_title . ' (' . implode(', ', array_map(function($key, $value) {
            return ucfirst(str_replace('attribute_', '', $key)) . ': ' . $value;
        }, array_keys($variation_attributes), $variation_attributes)) . ')';
    } else {
        $product = wc_get_product($product_id);
        $base_event_title = $product->get_name();
        $event_description = $product->get_description() ?: $parent_product->get_short_description() ?: '';
        $variation_attributes = $product->get_attributes();
        $meta_key = $product_id;
        $item_name = $base_event_title;
    }

    // Check if already linked to a valid event
    $event_id = get_post_meta($product_id, '_tribe_event_id', true);
    if ($event_id && tribe_is_event($event_id)) {
        $event = tribe_get_event($event_id);
        $sync_results['skipped'][] = sprintf(__('Skipped %s: Already linked to event "%s".', 'intersoccer-player-management'), $item_name, $event->post_title);
        return;
    }

    // Get booking type
    $booking_type = isset($variation_attributes['pa_booking-type']) ? $variation_attributes['pa_booking-type'] : '';
    if (empty($booking_type)) {
        $booking_types = wc_get_product_terms($product_id, 'pa_booking-type', array('fields' => 'names'));
        $booking_type = !empty($booking_types) ? $booking_types[0] : '';
    }

    // Determine if this is a Camp or Course based on product name and attributes
    $is_camp = stripos($base_event_title, 'camp') !== false && isset($variation_attributes['pa_summer-camp-terms-2025']);
    $is_course = stripos($base_event_title, 'course') !== false && !isset($variation_attributes['pa_summer-camp-terms-2025']);

    // Only sync "week" for Camps and "full_term" for Courses
    if ($is_camp && $booking_type !== 'week') {
        $sync_results['skipped'][] = sprintf(__('Skipped %s: Only "week" booking type is synced for Camps.', 'intersoccer-player-management'), $item_name);
        return;
    }
    if ($is_course && $booking_type !== 'full_term') {
        $sync_results['skipped'][] = sprintf(__('Skipped %s: Only "full_term" booking type is synced for Courses.', 'intersoccer-player-management'), $item_name);
        return;
    }

    // Build event title with specific attributes
    $event_title = $base_event_title;
    $event_date = '';
    $event_location = '';
    $start_date = '';
    $end_date = '';
    $duration = '';
    $booking_type = '';

    // Get booking type
    $booking_type = isset($variation_attributes['pa_booking-type']) ? $variation_attributes['pa_booking-type'] : '';
    if (empty($booking_type)) {
        $booking_types = wc_get_product_terms($product_id, 'pa_booking-type', array('fields' => 'names'));
        $booking_type = !empty($booking_types) ? $booking_types[0] : '';
    }

    // Get venue from pa_intersoccer-venues
    $event_location = isset($variation_attributes['pa_intersoccer-venues']) ? $variation_attributes['pa_intersoccer-venues'] : '';
    if (empty($event_location)) {
        $venue_terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
        $event_location = !empty($venue_terms) ? $venue_terms[0] : 'TBD';
    }
    $event_title .= '- ' . $event_location;

    // Handle Camps (week-based)
    if ($is_camp && $booking_type === 'week') {
        $term_value = isset($variation_attributes['pa_summer-camp-terms-2025']) ? $variation_attributes['pa_summer-camp-terms-2025'] : '';
        if (empty($term_value)) {
            $terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_summer-camp-terms-2025', array('fields' => 'names'));
            $term_value = !empty($terms) ? $terms[0] : '';
        }
        if (!empty($term_value)) {
            // Extract date range (e.g., "june-30-july-4")
            if (preg_match('/([a-z]+-\d{1,2}-[a-z]+-\d{1,2})/', $term_value, $date_match)) {
                $date_range = $date_match[1]; // e.g., "june-30-july-4"
                $date_parts = explode('-', $date_range);
                if (count($date_parts) === 4) {
                    $start_month = ucfirst($date_parts[0]); // e.g., "June"
                    $start_day = $date_parts[1]; // e.g., "30"
                    $end_month = ucfirst($date_parts[2]); // e.g., "July"
                    $end_day = $date_parts[3]; // e.g., "4"
                    $event_title .= ' ' . $start_day . ' ' . $start_month . '-' . $end_day . ' ' . $end_month;
                    // Set start and end dates for the event
                    $current_year = date('Y');
                    $start_date = date('Y-m-d H:i:s', strtotime("$start_month $start_day $current_year 09:00:00"));
                    $end_date = date('Y-m-d H:i:s', strtotime("$end_month $end_day $current_year 17:00:00"));
                }
            }
            // Extract duration (e.g., "5-days")
            if (preg_match('/(\d+-days)/', $term_value, $duration_match)) {
                $duration = str_replace('-days', ' days', $duration_match[1]); // e.g., "5 days"
                $event_title .= " ($duration)";
            }
        }
    }

    // Handle Courses (full_term)
    if ($is_course && $booking_type === 'full_term') {
        // Check if this is a Spring After School Course on Wednesday
        $is_spring_course = stripos($base_event_title, 'spring') !== false && stripos($base_event_title, 'after school') !== false;
        if ($is_spring_course) {
            // Assume the course runs for a term (e.g., April to June 2025)
            // For simplicity, we'll set the start date to the first Wednesday in April 2025
            $start_date = new DateTime('2025-04-02'); // First Wednesday in April 2025
            $end_date = new DateTime('2025-06-25'); // Last Wednesday in June 2025
            $start_date->setTime(15, 0); // 15:00 (3:00 PM)
            $end_date->setTime(16, 30); // 16:30 (4:30 PM)
            $start_date = $start_date->format('Y-m-d H:i:s');
            $end_date = $end_date->format('Y-m-d H:i:s');
            $event_title .= ' (Spring Term - Wednesdays)';
        } else {
            // For other full-term courses, assume a default term (e.g., April to June 2025)
            $start_date = '2025-04-01 09:00:00';
            $end_date = '2025-06-30 17:00:00';
            $event_title .= ' (Full Term)';
        }
    }

    // Fallback for dates if not set
    if (!$start_date || !$end_date) {
        $start_date = $event_date ? date('Y-m-d H:i:s', strtotime($event_date)) : date('Y-m-d H:i:s');
        $end_date = $event_date ? date('Y-m-d H:i:s', strtotime($event_date . ' +1 hour')) : date('Y-m-d H:i:s', strtotime('+1 hour'));
    }

    // Check if an event with this title already exists
    $existing_event = tribe_get_events(array(
        'posts_per_page' => 1,
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'title' => $event_title,
    ));

    if (!empty($existing_event)) {
        $event_id = $existing_event[0]->ID;
        update_post_meta($product_id, '_tribe_event_id', $event_id);
        $sync_results['linked'][] = sprintf(__('Linked %s to existing event "%s".', 'intersoccer-player-management'), $item_name, $event_title);
        return;
    }

    // Sync venue with Event Tickets
    $venue_id = 0;
    if ($event_location && $event_location !== 'TBD') {
        $venue = get_posts(array(
            'post_type' => 'tribe_venue',
            'post_status' => 'publish',
            'title' => $event_location,
            'posts_per_page' => 1,
        ));

        if (empty($venue)) {
            // Create a new venue
            $venue_args = array(
                'post_title' => $event_location,
                'post_type' => 'tribe_venue',
                'post_status' => 'publish',
            );
            $venue_id = wp_insert_post($venue_args);
            if (is_wp_error($venue_id)) {
                $sync_results['errors'][] = sprintf(__('Failed to create venue for %s: %s', 'intersoccer-player-management'), $event_location, $venue_id->get_error_message());
                $venue_id = 0;
            }
        } else {
            $venue_id = $venue[0]->ID;
        }
    }

    // Create the event
    $event_args = array(
        'post_title' => $event_title,
        'post_content' => $event_description,
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'meta_input' => array(
            '_EventStartDate' => $start_date,
            '_EventEndDate' => $end_date,
            '_EventVenueID' => $venue_id,
        ),
    );

    $new_event_id = wp_insert_post($event_args);
    if (is_wp_error($new_event_id)) {
        $sync_results['errors'][] = sprintf(__('Failed to create event for %s: %s', 'intersoccer-player-management'), $event_title, $new_event_id->get_error_message());
        return;
    }

    // Add an RSVP ticket to the event using Tribe__Tickets__RSVP
    $rsvp = Tribe__Tickets__RSVP::get_instance();
    $ticket_args = array(
        'ticket_name' => __('General Admission', 'intersoccer-player-management'),
        'ticket_description' => __('General admission ticket for the event.', 'intersoccer-player-management'),
        'capacity' => -1, // Unlimited capacity
        'start_date' => date('Y-m-d', strtotime($start_date)),
        'start_time' => date('H:i', strtotime($start_date)),
        'end_date' => date('Y-m-d', strtotime($end_date)),
        'end_time' => date('H:i', strtotime($end_date)),
        'show_not_going' => false,
    );

    // Create the RSVP ticket using ticket_add()
    $ticket_id = $rsvp->ticket_add($new_event_id, $ticket_args);
    if (!$ticket_id) {
        $sync_results['errors'][] = sprintf(__('Failed to create RSVP ticket for event ID %d.', 'intersoccer-player-management'), $new_event_id);
    }

    // Link the event to the product/variation
    update_post_meta($product_id, '_tribe_event_id', $new_event_id);
    $sync_results['success'][] = sprintf(__('Created new event "%s" for %s.', 'intersoccer-player-management'), $event_title, $item_name);
}
?>
