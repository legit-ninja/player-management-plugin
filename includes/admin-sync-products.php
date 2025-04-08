<?php
/**
 * Admin Feature: Sync Products to Events Tab with Simplified Taxonomy Columns and Updated Product Name
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

    // Get date range and duration from pa_summer-camp-terms-2025 if booking type is 'week'
    if ($booking_type === 'week') {
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

// Handle bulk sync
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'sync_selected' && !empty($_POST['bulk_sync_products_nonce']) && wp_verify_nonce($_POST['bulk_sync_products_nonce'], 'bulk_sync_products_action')) {
    $selected_items = isset($_POST['selected_items']) ? array_map('sanitize_text_field', (array) $_POST['selected_items']) : array();
    $sync_results = array('success' => array(), 'skipped' => array(), 'linked' => array(), 'errors' => array());

    foreach ($selected_items as $item) {
        if (strpos($item, 'variation_') === 0) {
            $variation_id = absint(str_replace('variation_', '', $item));
            sync_product_to_event($variation_id, $sync_results, true);
        } else {
            $product_id = absint($item);
            sync_product_to_event($product_id, $sync_results, false);
        }
    }

    // Display sync results
    if (!empty($sync_results['success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Sync Results:', 'intersoccer-player-management') . '</p><ul>';
        foreach ($sync_results['success'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($sync_results['linked'])) {
        echo '<div class="notice notice-info is-dismissible"><p>' . __('Linked to Existing Events:', 'intersoccer-player-management') . '</p><ul>';
        foreach ($sync_results['linked'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($sync_results['skipped'])) {
        echo '<div class="notice notice-info is-dismissible"><p>' . __('Skipped (Already Linked):', 'intersoccer-player-management') . '</p><ul>';
        foreach ($sync_results['skipped'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($sync_results['errors'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Errors:', 'intersoccer-player-management') . '</p><ul>';
        foreach ($sync_results['errors'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }
    if (empty($sync_results['success']) && empty($sync_results['skipped']) && empty($sync_results['linked']) && empty($sync_results['errors'])) {
        echo '<div class="notice notice-warning is-dismissible"><p>' . __('No items were selected for syncing.', 'intersoccer-player-management') . '</p></div>';
    }
}

// Handle manual sync (existing functionality)
if (isset($_POST['sync_products_to_events']) && !empty($_POST['sync_products_nonce']) && wp_verify_nonce($_POST['sync_products_nonce'], 'sync_products_to_events_action')) {
    $product_event_mappings = isset($_POST['product_event_mapping']) ? (array) $_POST['product_event_mapping'] : array();
    $sync_results = array('success' => array(), 'errors' => array());

    foreach ($product_event_mappings as $product_id => $event_id) {
        $event_id = absint($event_id);
        $item_name = '';
        if (strpos($product_id, 'variation_') === 0) {
            $variation_id = absint(str_replace('variation_', '', $product_id));
            $variation_obj = wc_get_product($variation_id);
            $parent_product = wc_get_product($variation_obj->get_parent_id());
            $variation_attributes = $variation_obj->get_variation_attributes();
            $item_name = $parent_product->get_name() . ' (' . implode(', ', array_map(function($key, $value) {
                return ucfirst(str_replace('attribute_', '', $key)) . ': ' . $value;
            }, array_keys($variation_attributes), $variation_attributes)) . ')';
            update_post_meta($variation_id, '_tribe_event_id', $event_id);
        } else {
            $product_id = absint($product_id);
            $product = wc_get_product($product_id);
            $item_name = $product->get_name();
            update_post_meta($product_id, '_tribe_event_id', $event_id);
        }
        $sync_results['success'][] = sprintf(__('Manually linked %s to event ID %d.', 'intersoccer-player-management'), $item_name, $event_id);
    }

    if (!empty($sync_results['success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Manual Sync Results:', 'intersoccer-player-management') . '</p><ul>';
        foreach ($sync_results['success'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }
}
?>

<!-- Sync Products to Events -->
<h2><?php _e('Sync Products to Events', 'intersoccer-player-management'); ?></h2>
<?php
// Fetch all WooCommerce products
$products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish',
));

// Fetch all Event Tickets events with broader criteria
$events = tribe_get_events(array(
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'eventDisplay' => 'all', // Ensure all events are included, regardless of date
));

// Debug: Log the fetched events to verify
error_log('Fetched Events: ' . print_r(array_map(function($event) { return $event->ID . ' - ' . $event->post_title . ' (' . $event->post_status . ')'; }, $events), true));

// Define the taxonomies to display as columns
$taxonomies_to_display = array(
    'pa_booking-type' => __('Booking Type', 'intersoccer-player-management'),
    'pa_intersoccer-venues' => __('InterSoccer Venues', 'intersoccer-player-management'),
    'pa_summer-camp-terms-2025' => __('Summer Camp Terms 2025', 'intersoccer-player-management'),
    'pa_course-start-and-end-dates' => __('Course Start/End Dates', 'intersoccer-player-management'),
);

if (empty($products)) {
    echo '<p>' . __('No WooCommerce products found.', 'intersoccer-player-management') . '</p>';
} else {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('bulk_sync_products_action', 'bulk_sync_products_nonce'); ?>
        <div class="bulk-actions">
            <select name="bulk_action">
                <option value=""><?php _e('Bulk Actions', 'intersoccer-player-management'); ?></option>
                <option value="sync_selected"><?php _e('Sync Selected', 'intersoccer-player-management'); ?></option>
            </select>
            <input type="submit" class="button" value="<?php _e('Apply', 'intersoccer-player-management'); ?>" />
            <p class="description"><?php _e('Select products/variations and choose "Sync Selected" to automatically sync them to events.', 'intersoccer-player-management'); ?></p>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="checkbox-column"><input type="checkbox" id="select-all" /></th>
                    <th><?php _e('Product Name', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Type', 'intersoccer-player-management'); ?></th>
                    <?php foreach ($taxonomies_to_display as $taxonomy => $label): ?>
                        <th><?php echo esc_html($label); ?></th>
                    <?php endforeach; ?>
                    <th><?php _e('Sync Status', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Linked Event', 'intersoccer-player-management'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($products as $product) {
                    $product_id = $product->get_id();
                    $product_type = $product->get_type();
                    $product_name = $product->get_name();

                    if ($product_type === 'simple' && $product_name === 'Birthday Party') {
                        // Handle the Birthday Party simple product
                        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
                        $base_event_title = $product_name;
                        $variation_attributes = array();
                        $item_name = $base_event_title;

                        // Build event title for checking existing events
                        $event_title = $base_event_title;
                        $venue_terms = wc_get_product_terms($product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
                        $event_location = !empty($venue_terms) ? $venue_terms[0] : 'TBD';
                        $event_title .= '- ' . $event_location;

                        // Check sync status
                        $sync_status = 'not_synced';
                        $sync_icon = '<span class="dashicons dashicons-no" style="color: red;" title="' . esc_attr__('Not Synced', 'intersoccer-player-management') . '"></span>';
                        $existing_event_id = 0;
                        if ($event_id && tribe_is_event($event_id)) {
                            $sync_status = 'synced';
                            $sync_icon = '<span class="dashicons dashicons-yes" style="color: green;" title="' . esc_attr__('Synced', 'intersoccer-player-management') . '"></span>';
                        } else {
                            $existing_event = tribe_get_events(array(
                                'posts_per_page' => 1,
                                'post_status' => array('publish', 'draft', 'pending', 'private'),
                                'eventDisplay' => 'all',
                                'title' => $event_title,
                            ));
                            if (!empty($existing_event)) {
                                $sync_status = 'event_exists';
                                $sync_icon = '<span class="dashicons dashicons-admin-links" style="color: blue;" title="' . esc_attr__('Event Exists (Not Linked)', 'intersoccer-player-management') . '"></span>';
                                $existing_event_id = $existing_event[0]->ID;
                            }
                        }

                        // Debug: Log the event ID and status
                        error_log("Product ID: $product_id, Event ID: $event_id, Sync Status: $sync_status, Existing Event ID: $existing_event_id");
                        ?>
                        <tr>
                            <td class="checkbox-column"><input type="checkbox" name="selected_items[]" value="<?php echo esc_attr($product_id); ?>" class="select-item" /></td>
                            <td><?php echo esc_html($product->get_name()); ?></td>
                            <td><?php _e('Simple', 'intersoccer-player-management'); ?></td>
                            <?php
                            foreach ($taxonomies_to_display as $taxonomy => $label) {
                                $terms = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'names'));
                                $value = !empty($terms) ? implode(', ', $terms) : 'N/A';
                                echo '<td>' . esc_html($value) . '</td>';
                            }
                            ?>
                            <td><?php echo $sync_icon; ?></td>
                            <td>
                                <?php if (empty($events)): ?>
                                    <?php _e('No events available. Use bulk actions to sync.', 'intersoccer-player-management'); ?>
                                <?php else: ?>
                                    <select name="product_event_mapping[<?php echo esc_attr($product_id); ?>]">
                                        <option value=""><?php _e('None', 'intersoccer-player-management'); ?></option>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id ? $event_id : $existing_event_id, $event->ID); ?>>
                                                <?php echo esc_html($event->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    } elseif ($product_type === 'variable') {
                        // Handle variable products (events)
                        $variations = $product->get_available_variations();
                        foreach ($variations as $variation) {
                            $variation_id = $variation['variation_id'];
                            $variation_obj = wc_get_product($variation_id);
                            $event_id = get_post_meta($variation_id, '_tribe_event_id', true);
                            $variation_attributes = $variation_obj->get_attributes();

                            // Build event title for checking existing events
                            $base_event_title = $product->get_name();
                            $event_title = $base_event_title;
                            $venue_terms = isset($variation_attributes['pa_intersoccer-venues']) ? $variation_attributes['pa_intersoccer-venues'] : '';
                            if (empty($venue_terms)) {
                                $venue_terms = wc_get_product_terms($product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
                                $venue_terms = !empty($venue_terms) ? $venue_terms[0] : '';
                            }
                            $event_location = !empty($venue_terms) ? $venue_terms : 'TBD';
                            $event_title .= '- ' . $event_location;

                            $booking_type = isset($variation_attributes['pa_booking-type']) ? $variation_attributes['pa_booking-type'] : '';
                            if (empty($booking_type)) {
                                $booking_types = wc_get_product_terms($product_id, 'pa_booking-type', array('fields' => 'names'));
                                $booking_type = !empty($booking_types) ? $booking_types[0] : '';
                            }

                            if ($booking_type === 'week') {
                                $term_value = isset($variation_attributes['pa_summer-camp-terms-2025']) ? $variation_attributes['pa_summer-camp-terms-2025'] : '';
                                if (empty($term_value)) {
                                    $terms = wc_get_product_terms($product_id, 'pa_summer-camp-terms-2025', array('fields' => 'names'));
                                    $term_value = !empty($terms) ? $terms[0] : '';
                                }
                                if (!empty($term_value)) {
                                    if (preg_match('/([a-z]+-\d{1,2}-[a-z]+-\d{1,2})/', $term_value, $date_match)) {
                                        $date_range = $date_match[1];
                                        $date_parts = explode('-', $date_range);
                                        if (count($date_parts) === 4) {
                                            $start_month = ucfirst($date_parts[0]);
                                            $start_day = $date_parts[1];
                                            $end_month = ucfirst($date_parts[2]);
                                            $end_day = $date_parts[3];
                                            $event_title .= ' ' . $start_day . ' ' . $start_month . '-' . $end_day . ' ' . $end_month;
                                        }
                                    }
                                    if (preg_match('/(\d+-days)/', $term_value, $duration_match)) {
                                        $duration = str_replace('-days', ' days', $duration_match[1]);
                                        $event_title .= " ($duration)";
                                    }
                                }
                            }

                            // Check sync status
                            $sync_status = 'not_synced';
                            $sync_icon = '<span class="dashicons dashicons-no" style="color: red;" title="' . esc_attr__('Not Synced', 'intersoccer-player-management') . '"></span>';
                            $existing_event_id = 0;
                            if ($event_id && tribe_is_event($event_id)) {
                                $sync_status = 'synced';
                                $sync_icon = '<span class="dashicons dashicons-yes" style="color: green;" title="' . esc_attr__('Synced', 'intersoccer-player-management') . '"></span>';
                            } else {
                                $existing_event = tribe_get_events(array(
                                    'posts_per_page' => 1,
                                    'post_status' => array('publish', 'draft', 'pending', 'private'),
                                    'eventDisplay' => 'all',
                                    'title' => $event_title,
                                ));
                                if (!empty($existing_event)) {
                                    $sync_status = 'event_exists';
                                    $sync_icon = '<span class="dashicons dashicons-admin-links" style="color: blue;" title="' . esc_attr__('Event Exists (Not Linked)', 'intersoccer-player-management') . '"></span>';
                                    $existing_event_id = $existing_event[0]->ID;
                                }
                            }

                            // Debug: Log the event ID and status
                            error_log("Variation ID: $variation_id, Event ID: $event_id, Sync Status: $sync_status, Existing Event ID: $existing_event_id");
                            ?>
                            <tr>
                                <td class="checkbox-column"><input type="checkbox" name="selected_items[]" value="variation_<?php echo esc_attr($variation_id); ?>" class="select-item" /></td>
                                <td><?php echo esc_html($product->get_name()); ?></td>
                                <td><?php _e('Variation', 'intersoccer-player-management'); ?></td>
                                <?php
                                foreach ($taxonomies_to_display as $taxonomy => $label) {
                                    $value = isset($variation_attributes[$taxonomy]) ? $variation_attributes[$taxonomy] : '';
                                    if (empty($value)) {
                                        $terms = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'names'));
                                        $value = !empty($terms) ? implode(', ', $terms) : 'N/A';
                                    }
                                    echo '<td>' . esc_html($value) . '</td>';
                                }
                                ?>
                                <td><?php echo $sync_icon; ?></td>
                                <td>
                                    <?php if (empty($events)): ?>
                                        <?php _e('No events available. Use bulk actions to sync.', 'intersoccer-player-management'); ?>
                                    <?php else: ?>
                                        <select name="product_event_mapping[variation_<?php echo esc_attr($variation_id); ?>]">
                                            <option value=""><?php _e('None', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($events as $event): ?>
                                                <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id ? $event_id : $existing_event_id, $event->ID); ?>>
                                                    <?php echo esc_html($event->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                }
                ?>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" name="sync_products_to_events" class="button button-primary" value="<?php _e('Save Manual Sync', 'intersoccer-player-management'); ?>" />
            <p class="description"><?php _e('Manually link products/variations to existing events.', 'intersoccer-player-management'); ?></p>
        </p>
    </form>
    <script>
        jQuery(document).ready(function($) {
            $('#select-all').on('change', function() {
                $('.select-item').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
    <style>
        .bulk-actions {
            margin-bottom: 10px;
        }
        .bulk-actions select {
            margin-right: 10px;
        }
        .notice ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        .checkbox-column {
            width: 30px;
            text-align: center;
        }
    </style>
    <?php
}
?>

