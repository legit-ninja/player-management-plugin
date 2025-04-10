<?php
/**
 * Admin Feature: Sync Products to Events Tab
 * Handles syncing WooCommerce products to The Events Calendar events.
 *
 * @package InterSoccer_Player_Management
 */

// Prevent direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// Handle AJAX request to save attributes and sync
add_action('wp_ajax_intersoccer_save_and_sync_product', 'intersoccer_save_and_sync_product');
function intersoccer_save_and_sync_product() {
    check_ajax_referer('intersoccer_sync_product_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $is_variation = isset($_POST['is_variation']) ? (bool) $_POST['is_variation'] : false;
    $attributes = isset($_POST['attributes']) ? (array) $_POST['attributes'] : array();

    if (!$product_id) {
        error_log('InterSoccer: Invalid product ID in intersoccer_save_and_sync_product');
        wp_send_json_error(array('message' => __('Invalid product ID.', 'intersoccer-player-management')));
    }

    $sync_results = array('linked' => array(), 'skipped' => array());

    // Load the product
    $product = wc_get_product($product_id);
    if (!$product) {
        error_log('InterSoccer: Invalid product for ID ' . $product_id);
        wp_send_json_error(array('message' => __('Invalid product.', 'intersoccer-player-management')));
    }

    // If it's a variation, get the parent product
    $parent_product = $is_variation ? wc_get_product($product->get_parent_id()) : $product;
    if ($is_variation && !$parent_product) {
        error_log('InterSoccer: Invalid parent product for variation ID ' . $product_id);
        wp_send_json_error(array('message' => __('Invalid parent product.', 'intersoccer-player-management')));
    }

    $item_name = $is_variation ? $parent_product->get_name() . ' (' . implode(', ', array_map(function($key, $value) {
        return ucfirst(str_replace('attribute_', '', $key)) . ': ' . $value;
    }, array_keys($product->get_attributes()), $product->get_attributes())) . ')' : $parent_product->get_name();

    // Determine if the product is a Camp
    $product_categories = wp_get_post_terms($is_variation ? $parent_product->get_id() : $product_id, 'product_cat', array('fields' => 'names'));
    $is_camp = in_array('Camps', $product_categories);

    // Prepare attributes for saving
    $required_attributes = array(
        'pa_booking-type' => 'Booking Type',
        'pa_intersoccer-venues' => 'Venue',
        'pa_event-terms' => 'Term',
        'pa_event-times' => 'Event Times',
        'pa_age-group' => 'Age Group',
    );

    // Only require Day of Week for Courses, not Camps
    if (!$is_camp) {
        $required_attributes['pa_day-of-week'] = 'Day of Week';
    }

    $variation_attributes = $is_variation ? $product->get_attributes() : array();
    $missing_attributes = array();

    foreach ($required_attributes as $attr_key => $attr_name) {
        if (isset($attributes[$attr_key]) && !empty($attributes[$attr_key])) {
            $variation_attributes[$attr_key] = sanitize_text_field($attributes[$attr_key]);
        } else {
            $value = isset($variation_attributes[$attr_key]) ? $variation_attributes[$attr_key] : '';
            if (empty($value)) {
                $terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, $attr_key, array('fields' => 'names'));
                $value = !empty($terms) ? $terms[0] : '';
            }
            if (empty($value)) {
                $missing_attributes[] = $attr_name;
            }
        }
    }

    if (!empty($missing_attributes)) {
        error_log('InterSoccer: Missing required attributes for product ID ' . $product_id . ': ' . implode(', ', $missing_attributes));
        wp_send_json_error(array('message' => sprintf(__('Missing required attributes: %s.', 'intersoccer-player-management'), implode(', ', $missing_attributes))));
    }

    // Save non-required attributes
    $non_required_attributes = array(
        'pa_hot-lunch' => 'Hot Lunch',
        'pa_holidays' => 'Holidays',
        'pa_camp-terms' => 'Camp Terms',
    );

    foreach ($non_required_attributes as $attr_key => $attr_name) {
        if (isset($attributes[$attr_key]) && !empty($attributes[$attr_key])) {
            $variation_attributes[$attr_key] = sanitize_text_field($attributes[$attr_key]);
        }
    }

    // Save custom meta fields (start_date, end_date)
    if (isset($attributes['event_start_date']) && !empty($attributes['event_start_date'])) {
        update_post_meta($product_id, 'event_start_date', sanitize_text_field($attributes['event_start_date']));
    }
    if (isset($attributes['event_end_date']) && !empty($attributes['event_end_date'])) {
        update_post_meta($product_id, 'event_end_date', sanitize_text_field($attributes['event_end_date']));
    }

    // Update product attributes
    try {
        if ($is_variation) {
            // For variations, update the meta data directly
            foreach ($variation_attributes as $key => $value) {
                $meta_key = 'attribute_' . $key;
                update_post_meta($product_id, $meta_key, $value);
                error_log('InterSoccer: Updated variation attribute for product ID ' . $product_id . ': ' . $meta_key . ' = ' . $value);
            }
            $product->save();
            error_log('InterSoccer: Saved variation product ID ' . $product_id);
        } else {
            // For simple products, set attributes on the product object
            $product->set_attributes($variation_attributes);
            $product->save();
            error_log('InterSoccer: Saved simple product ID ' . $product_id);
        }
    } catch (Exception $e) {
        error_log('InterSoccer: Error saving attributes for product ID ' . $product_id . ': ' . $e->getMessage());
        wp_send_json_error(array('message' => __('Error saving product attributes: ', 'intersoccer-player-management') . $e->getMessage()));
    }

    // Generate event
    try {
        sync_product_to_event($product_id, $sync_results, $is_variation);
    } catch (Exception $e) {
        error_log('InterSoccer: Error generating event for product ID ' . $product_id . ': ' . $e->getMessage());
        wp_send_json_error(array('message' => __('Error generating event: ', 'intersoccer-player-management') . $e->getMessage()));
    }

    if (!empty($sync_results['linked'])) {
        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
        $event_title = $event_id && tribe_is_event($event_id) ? tribe_get_event($event_id)->post_title : 'Event Created';
        error_log('InterSoccer: Successfully synced product ID ' . $product_id . ' to event ID ' . $event_id);
        wp_send_json_success(array('event_title' => $event_title));
    } else {
        error_log('InterSoccer: Sync failed for product ID ' . $product_id . ': ' . ($sync_results['skipped'][0] ?? 'Unknown error'));
        wp_send_json_error(array('message' => $sync_results['skipped'][0] ?? __('Unknown error during sync.', 'intersoccer-player-management')));
    }
}

/**
 * Syncs a single product or variation to a newly generated event.
 *
 * @param int   $product_id    The ID of the product or variation.
 * @param array $sync_results  Reference to an array to store sync results.
 * @param bool  $is_variation  Whether the item is a variation.
 */
function sync_product_to_event($product_id, &$sync_results, $is_variation = false) {
    if ($is_variation) {
        $variation_obj = wc_get_product($product_id);
        if (!$variation_obj) {
            $sync_results['skipped'][] = sprintf(__('Skipped Variation ID %d: Invalid variation.', 'intersoccer-player-management'), $product_id);
            return;
        }
        $parent_product = wc_get_product($variation_obj->get_parent_id());
        if (!$parent_product) {
            $sync_results['skipped'][] = sprintf(__('Skipped Variation ID %d: Invalid parent product.', 'intersoccer-player-management'), $product_id);
            return;
        }
        $base_event_title = $parent_product->get_name();
        $event_description = $parent_product->get_description() ?: $parent_product->get_short_description() ?: '';
        $variation_attributes = array();
        $attribute_keys = array(
            'pa_event-terms',
            'pa_intersoccer-venues',
            'pa_hot-lunch',
            'pa_event-times',
            'pa_holidays',
            'pa_booking-type',
            'pa_age-group',
            'pa_day-of-week',
            'pa_camp-terms',
        );
        foreach ($attribute_keys as $key) {
            $meta_key = 'attribute_' . $key;
            $value = get_post_meta($product_id, $meta_key, true);
            if ($value) {
                $variation_attributes[$key] = $value;
            } else {
                $terms = wc_get_product_terms($parent_product->get_id(), $key, array('fields' => 'names'));
                $variation_attributes[$key] = !empty($terms) ? $terms[0] : '';
            }
        }
        $meta_key = 'variation_' . $product_id;
        $item_name = $base_event_title . ' (' . implode(', ', array_map(function($key, $value) {
            return ucfirst(str_replace('pa_', '', $key)) . ': ' . $value;
        }, array_keys($variation_attributes), $variation_attributes)) . ')';
    } else {
        $product = wc_get_product($product_id);
        if (!$product) {
            $sync_results['skipped'][] = sprintf(__('Skipped Product ID %d: Invalid product.', 'intersoccer-player-management'), $product_id);
            return;
        }
        $base_event_title = $product->get_name();
        $event_description = $product->get_description() ?: $product->get_short_description() ?: '';
        $variation_attributes = array();
        $attribute_keys = array(
            'pa_event-terms',
            'pa_intersoccer-venues',
            'pa_hot-lunch',
            'pa_event-times',
            'pa_holidays',
            'pa_booking-type',
            'pa_age-group',
            'pa_day-of-week',
            'pa_camp-terms',
        );
        foreach ($attribute_keys as $key) {
            $terms = wc_get_product_terms($product->get_id(), $key, array('fields' => 'names'));
            $variation_attributes[$key] = !empty($terms) ? $terms[0] : '';
        }
        $meta_key = $product_id;
        $item_name = $base_event_title;
    }

    // Check if already linked to a valid event
    $existing_event_id = get_post_meta($product_id, '_tribe_event_id', true);
    if ($existing_event_id && tribe_is_event($existing_event_id)) {
        $event = tribe_get_event($existing_event_id);
        $sync_results['skipped'][] = sprintf(__('Skipped %s: Already linked to event "%s".', 'intersoccer-player-management'), $item_name, $event->post_title);
        return;
    }

    // Build event title with specific attributes
    $event_title = $base_event_title;
    $event_location = '';
    $start_date = get_post_meta($product_id, 'event_start_date', true);
    $end_date = get_post_meta($product_id, 'event_end_date', true);
    $duration = '';
    $booking_type = '';

    // Get booking type
    $booking_type = isset($variation_attributes['pa_booking-type']) ? $variation_attributes['pa_booking-type'] : '';
    if (empty($booking_type)) {
        $booking_types = wc_get_product_terms($product_id, 'pa_booking-type', array('fields' => 'names'));
        $booking_type = !empty($booking_types) ? $booking_types[0] : '';
    }
    error_log('InterSoccer: Booking type for product ID ' . $product_id . ': ' . $booking_type);

    // Get venue from pa_intersoccer-venues
    $event_location = isset($variation_attributes['pa_intersoccer-venues']) ? $variation_attributes['pa_intersoccer-venues'] : '';
    if (empty($event_location)) {
        $venue_terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
        $event_location = !empty($venue_terms) ? $venue_terms[0] : 'TBD';
    }
    $event_title .= ' - ' . $event_location;
    error_log('InterSoccer: Event location for product ID ' . $product_id . ': ' . $event_location);

    // Get day of week and event time for event title
    $day_of_week = isset($variation_attributes['pa_day-of-week']) ? $variation_attributes['pa_day-of-week'] : '';
    if (empty($day_of_week)) {
        $day_terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_day-of-week', array('fields' => 'names'));
        $day_of_week = !empty($day_terms) ? $day_terms[0] : '';
    }
    $event_time = isset($variation_attributes['pa_event-times']) ? $variation_attributes['pa_event-times'] : '';
    if (empty($event_time)) {
        $time_terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_event-times', array('fields' => 'names'));
        $event_time = !empty($time_terms) ? $time_terms[0] : '';
    }
    if ($day_of_week && $event_time) {
        $event_title = "$day_of_week $event_time";
    }
    error_log('InterSoccer: Event title for product ID ' . $product_id . ': ' . $event_title);

    // Get date range and duration from pa_event-terms if booking type is 'week'
    if (strtolower($booking_type) === 'week') {
        $term_value = isset($variation_attributes['pa_event-terms']) ? $variation_attributes['pa_event-terms'] : '';
        if (empty($term_value)) {
            $terms = wc_get_product_terms($is_variation ? $parent_product->get_id() : $product_id, 'pa_event-terms', array('fields' => 'names'));
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
                    // Use pa_event-terms dates if start_date and end_date are not set
                    if (!$start_date || !$end_date) {
                        $current_year = date('Y');
                        $start_date = date('Y-m-d H:i:s', strtotime("$start_month $start_day $current_year 09:00:00"));
                        $end_date = date('Y-m-d H:i:s', strtotime("$end_month $end_day $current_year 17:00:00"));
                    }
                }
            }
            // Extract duration (e.g., "5-days")
            if (preg_match('/(\d+-days)/', $term_value, $duration_match)) {
                $duration = str_replace('-days', ' days', $duration_match[1]); // e.g., "5 days"
                $event_title .= " ($duration)";
            }
        }
    }

    // Fallback for dates if not set
    if (!$start_date || !$end_date) {
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+1 hour'));
    }
    error_log('InterSoccer: Start date for product ID ' . $product_id . ': ' . $start_date);
    error_log('InterSoccer: End date for product ID ' . $product_id . ': ' . $end_date);

    // Create a new event
    $event_args = array(
        'post_title' => $event_title,
        'post_content' => $event_description,
        'post_status' => 'publish',
        'post_type' => 'tribe_events',
        'meta_input' => array(
            '_EventStartDate' => $start_date,
            '_EventEndDate' => $end_date,
            '_EventShowMap' => 0,
            '_EventShowMapLink' => 0,
        ),
    );

    $event_id = wp_insert_post($event_args);
    if (is_wp_error($event_id)) {
        $sync_results['skipped'][] = sprintf(__('Failed to create event for %s: %s', 'intersoccer-player-management'), $item_name, $event_id->get_error_message());
        return;
    }

    // Link the product/variation to the new event
    update_post_meta($product_id, '_tribe_event_id', $event_id);
    $sync_results['linked'][] = sprintf(__('Created and linked %s to new event "%s".', 'intersoccer-player-management'), $item_name, $event_title);
}

// Render the Sync Products tab content
function intersoccer_render_sync_products_tab() {
    // Include the rendering logic
    require_once plugin_dir_path(__FILE__) . '/sync-product-render.php';
}
?>

