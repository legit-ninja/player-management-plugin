<?php
/**
 * Rendering Logic for Sync Products to Events Tab
 * Displays a table of WooCommerce products and variations for syncing with events.
 *
 * @package InterSoccer_Player_Management
 */

// Prevent direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// Check dependencies
if (!class_exists('WooCommerce')) {
    echo '<div class="error"><p>' . esc_html__('WooCommerce is required for this feature. Please install and activate it.', 'intersoccer-player-management') . '</p></div>';
    return;
}
if (!function_exists('tribe_get_events')) {
    echo '<div class="error"><p>' . esc_html__('The Events Calendar is required for this feature. Please install and activate it.', 'intersoccer-player-management') . '</p></div>';
    return;
}

// Fetch all published WooCommerce products
$all_products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish',
));

// Array to hold filtered products and variations
$filtered_products = array();

// Collect unique attribute values for filters
$booking_types = array();
$venues = array();
$venue_names = array();
$event_terms = array();
$hot_lunches = array();
$event_times = array();
$holidays = array();
$age_groups = array();
$days_of_week = array();
$camp_terms = array();
$categories = array();

foreach ($all_products as $product) {
    if ($product->is_type('simple')) {
        // Check simple product attributes
        $booking_type = $product->get_attribute('pa_booking-type');
        if (in_array(strtolower($booking_type), array('week', 'full_term'))) {
            $filtered_products[] = array(
                'obj' => $product,
                'is_variation' => false,
            );
        }
    } elseif ($product->is_type('variable')) {
        // Check variations for variable products
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $booking_type = $variation_obj->get_attribute('pa_booking-type');
            if (in_array(strtolower($booking_type), array('week', 'full_term'))) {
                $filtered_products[] = array(
                    'obj' => $variation_obj,
                    'is_variation' => true,
                );
            }
        }
    }

    // Collect attribute values for filters from both simple products and variations
    $booking_type = $product->get_attribute('pa_booking-type');
    if ($booking_type && !in_array($booking_type, $booking_types)) {
        $booking_types[] = $booking_type;
    }
    $venue = $product->get_attribute('pa_intersoccer-venues');
    if ($venue && !in_array($venue, $venues)) {
        $venues[] = $venue;
        // Fetch the term name for display
        $terms = get_terms(array(
            'taxonomy' => 'pa_intersoccer-venues',
            'slug' => $venue,
            'fields' => 'names',
        ));
        $venue_name = !empty($terms) ? $terms[0] : $venue;
        if (!in_array($venue_name, $venue_names)) {
            $venue_names[$venue] = $venue_name;
        }
    }
    $event_term = $product->get_attribute('pa_event-terms');
    if ($event_term && !in_array($event_term, $event_terms)) {
        $event_terms[] = $event_term;
    }
    $hot_lunch = $product->get_attribute('pa_hot-lunch');
    if ($hot_lunch && !in_array($hot_lunch, $hot_lunches)) {
        $hot_lunches[] = $hot_lunch;
    }
    $event_time = $product->get_attribute('pa_event-times');
    if ($event_time && !in_array($event_time, $event_times)) {
        $event_times[] = $event_time;
    }
    $holiday = $product->get_attribute('pa_holidays');
    if ($holiday && !in_array($holiday, $holidays)) {
        $holidays[] = $holiday;
    }
    $age_group = $product->get_attribute('pa_age-group');
    if ($age_group && !in_array($age_group, $age_groups)) {
        $age_groups[] = $age_group;
    }
    $day_of_week = $product->get_attribute('pa_day-of-week');
    if ($day_of_week && !in_array($day_of_week, $days_of_week)) {
        $days_of_week[] = $day_of_week;
    }
    // Collect camp terms individually
    $camp_term = $product->get_attribute('pa_camp-terms');
    if ($camp_term) {
        // Split the value if it contains multiple terms (e.g., comma-separated)
        $terms = array_map('trim', explode(',', $camp_term));
        foreach ($terms as $term) {
            if ($term && !in_array($term, $camp_terms)) {
                $camp_terms[] = $term;
            }
        }
    }

    // Collect product categories
    $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
    foreach ($product_cats as $cat) {
        if ($cat && !in_array($cat, $categories)) {
            $categories[] = $cat;
        }
    }

    // Also collect attributes from variations
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $booking_type = $variation_obj->get_attribute('pa_booking-type');
            if ($booking_type && !in_array($booking_type, $booking_types)) {
                $booking_types[] = $booking_type;
            }
            $venue = $variation_obj->get_attribute('pa_intersoccer-venues');
            if ($venue && !in_array($venue, $venues)) {
                $venues[] = $venue;
                $terms = get_terms(array(
                    'taxonomy' => 'pa_intersoccer-venues',
                    'slug' => $venue,
                    'fields' => 'names',
                ));
                $venue_name = !empty($terms) ? $terms[0] : $venue;
                if (!in_array($venue_name, $venue_names)) {
                    $venue_names[$venue] = $venue_name;
                }
            }
            $event_term = $variation_obj->get_attribute('pa_event-terms');
            if ($event_term && !in_array($event_term, $event_terms)) {
                $event_terms[] = $event_term;
            }
            $hot_lunch = $variation_obj->get_attribute('pa_hot-lunch');
            if ($hot_lunch && !in_array($hot_lunch, $hot_lunches)) {
                $hot_lunches[] = $hot_lunch;
            }
            $event_time = $variation_obj->get_attribute('pa_event-times');
            if ($event_time && !in_array($event_time, $event_times)) {
                $event_times[] = $event_time;
            }
            $holiday = $variation_obj->get_attribute('pa_holidays');
            if ($holiday && !in_array($holiday, $holidays)) {
                $holidays[] = $holiday;
            }
            $age_group = $variation_obj->get_attribute('pa_age-group');
            if ($age_group && !in_array($age_group, $age_groups)) {
                $age_groups[] = $age_group;
            }
            $day_of_week = $variation_obj->get_attribute('pa_day-of-week');
            if ($day_of_week && !in_array($day_of_week, $days_of_week)) {
                $days_of_week[] = $day_of_week;
            }
            $camp_term = $variation_obj->get_attribute('pa_camp-terms');
            if ($camp_term) {
                $terms = array_map('trim', explode(',', $camp_term));
                foreach ($terms as $term) {
                    if ($term && !in_array($term, $camp_terms)) {
                        $camp_terms[] = $term;
                    }
                }
            }
        }
    }
}

// Sort filter options
sort($booking_types);
sort($venues);
sort($event_terms);
sort($hot_lunches);
sort($event_times);
sort($holidays);
sort($age_groups);
sort($days_of_week);
sort($camp_terms);
sort($categories);

// Get filter values from GET parameters
$filter_booking_type = isset($_GET['filter_booking_type']) ? sanitize_text_field($_GET['filter_booking_type']) : '';
$filter_venue = isset($_GET['filter_venue']) ? sanitize_text_field($_GET['filter_venue']) : '';
$filter_term = isset($_GET['filter_term']) ? sanitize_text_field($_GET['filter_term']) : '';
$filter_day_of_week = isset($_GET['filter_day_of_week']) ? sanitize_text_field($_GET['filter_day_of_week']) : '';
$filter_course_time = isset($_GET['filter_course_time']) ? sanitize_text_field($_GET['filter_course_time']) : '';
$filter_age_group = isset($_GET['filter_age_group']) ? sanitize_text_field($_GET['filter_age_group']) : '';
$filter_unsynced = isset($_GET['filter_unsynced']) && $_GET['filter_unsynced'] === '1';

// Apply filters to the product list
if ($filter_booking_type || $filter_venue || $filter_term || $filter_day_of_week || $filter_course_time || $filter_age_group || $filter_unsynced) {
    $filtered_products = array_filter($filtered_products, function($item) use ($filter_booking_type, $filter_venue, $filter_term, $filter_day_of_week, $filter_course_time, $filter_age_group, $filter_unsynced, $venue_names) {
        $product = $item['obj'];
        $item_id = $product->get_id();
        $matches_booking_type = !$filter_booking_type || strtolower($product->get_attribute('pa_booking-type')) === strtolower($filter_booking_type);
        $matches_venue = !$filter_venue || strtolower($product->get_attribute('pa_intersoccer-venues')) === strtolower($filter_venue);
        $matches_term = !$filter_term || strtolower($product->get_attribute('pa_event-terms')) === strtolower($filter_term);
        $matches_day_of_week = !$filter_day_of_week || strtolower($product->get_attribute('pa_day-of-week')) === strtolower($filter_day_of_week);
        $matches_course_time = !$filter_course_time || strtolower($product->get_attribute('pa_event-times')) === strtolower($filter_course_time);
        $matches_age_group = !$filter_age_group || strtolower($product->get_attribute('pa_age-group')) === strtolower($filter_age_group);
        $event_id = get_post_meta($item_id, '_tribe_event_id', true);
        $matches_unsynced = !$filter_unsynced || !($event_id && tribe_is_event($event_id));
        return $matches_booking_type && $matches_venue && $matches_term && $matches_day_of_week && $matches_course_time && $matches_age_group && $matches_unsynced;
    });
    $filtered_products = array_values($filtered_products); // Reindex array
}

// Fetch all events from The Events Calendar (for display purposes only)
$event_args = array(
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'eventDisplay' => 'all',
    'start_date' => '1970-01-01', // Ensure we get all events, including past ones
);
$events = tribe_get_events($event_args);

// Enqueue jQuery UI for date picker
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

// Enqueue custom script for quick edit
wp_enqueue_script(
    'intersoccer-sync-products',
    plugin_dir_url(__FILE__) . '../js/sync-products.js',
    array('jquery', 'jquery-ui-datepicker'),
    '1.0',
    true
);
wp_localize_script('intersoccer-sync-products', 'intersoccerSync', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('intersoccer_sync_product_nonce'),
    'venueNames' => $venue_names, // Pass venue names to JavaScript
));

?>
<div class="wrap">
    <h1><?php echo esc_html__('Sync Products to Events', 'intersoccer-player-management'); ?></h1>

    <!-- Attribute Filters -->
    <form method="get" action="">
        <input type="hidden" name="page" value="intersoccer-players" />
        <input type="hidden" name="tab" value="sync-products" />
        <div class="tablenav top">
            <div class="alignleft actions filter-container">
                <div class="filter-item">
                    <label for="filter_booking_type"><?php echo esc_html__('Booking Type:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_booking_type" id="filter_booking_type">
                        <option value=""><?php echo esc_html__('All Booking Types', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($booking_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>" <?php selected($filter_booking_type, $type); ?>>
                                <?php echo esc_html($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_venue"><?php echo esc_html__('Venue:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_venue" id="filter_venue">
                        <option value=""><?php echo esc_html__('All Venues', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo esc_attr($venue); ?>" <?php selected($filter_venue, $venue); ?>>
                                <?php echo esc_html($venue_names[$venue] ?? $venue); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_term"><?php echo esc_html__('Term:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_term" id="filter_term">
                        <option value=""><?php echo esc_html__('All Terms', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($event_terms as $term): ?>
                            <option value="<?php echo esc_attr($term); ?>" <?php selected($filter_term, $term); ?>>
                                <?php echo esc_html($term); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_day_of_week"><?php echo esc_html__('Day of Week:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_day_of_week" id="filter_day_of_week">
                        <option value=""><?php echo esc_html__('All Days', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($days_of_week as $day): ?>
                            <option value="<?php echo esc_attr($day); ?>" <?php selected($filter_day_of_week, $day); ?>>
                                <?php echo esc_html($day); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_course_time"><?php echo esc_html__('Event Time:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_course_time" id="filter_course_time">
                        <option value=""><?php echo esc_html__('All Times', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($event_times as $time): ?>
                            <option value="<?php echo esc_attr($time); ?>" <?php selected($filter_course_time, $time); ?>>
                                <?php echo esc_html($time); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_age_group"><?php echo esc_html__('Age Group:', 'intersoccer-player-management'); ?></label>
                    <select name="filter_age_group" id="filter_age_group">
                        <option value=""><?php echo esc_html__('All Age Groups', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($age_groups as $age_group): ?>
                            <option value="<?php echo esc_attr($age_group); ?>" <?php selected($filter_age_group, $age_group); ?>>
                                <?php echo esc_html($age_group); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item toggle-item">
                    <label class="switch">
                        <input type="checkbox" name="filter_unsynced" id="filter_unsynced" value="1" <?php checked($filter_unsynced, '1'); ?> />
                        <span class="slider round"></span>
                    </label>
                    <label for="filter_unsynced"><?php echo esc_html__('Only show products that do not have Events', 'intersoccer-player-management'); ?></label>
                </div>

                <div class="filter-item">
                    <input type="submit" class="button" value="<?php echo esc_attr__('Filter', 'intersoccer-player-management'); ?>" />
                    <a href="?page=intersoccer-players&tab=sync-products" class="button"><?php echo esc_html__('Clear Filters', 'intersoccer-player-management'); ?></a>
                </div>
            </div>
        </div>
    </form>

    <?php if (empty($filtered_products)): ?>
        <p><?php echo esc_html__('No products or variations found matching the selected filters.', 'intersoccer-player-management'); ?></p>
    <?php else: ?>
        <form method="post" action="">
            <?php wp_nonce_field('sync_products_action', 'sync_products_nonce'); ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <input type="submit" name="bulk_sync" class="button button-primary" value="<?php echo esc_attr__('Sync Selected', 'intersoccer-player-management'); ?>" />
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><input type="checkbox" id="select-all-products" /></th>
                        <th><?php echo esc_html__('Product Name', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Categories', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Term', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Venue', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Booking Type', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Age Group', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Action', 'intersoccer-player-management'); ?></th>
                        <th><?php echo esc_html__('Synced Event', 'intersoccer-player-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_products as $item): 
                        $product = $item['obj'];
                        $is_variation = $item['is_variation'];
                        $item_id = $product->get_id();
                        // For variations, use the parent product name and ID for editing
                        $product_name = $is_variation ? wc_get_product($product->get_parent_id())->get_name() : $product->get_name();
                        $edit_product_id = $is_variation ? $product->get_parent_id() : $item_id;
                        $event_id = get_post_meta($item_id, '_tribe_event_id', true);
                        $event_title = ($event_id && tribe_is_event($event_id)) ? tribe_get_event($event_id)->post_title : esc_html__('Not Synced', 'intersoccer-player-management');
                        $product_categories = wp_get_post_terms($edit_product_id, 'product_cat', array('fields' => 'names'));
                        $is_camp = in_array('Camps', $product_categories);
                        $start_date = get_post_meta($item_id, 'event_start_date', true);
                        $end_date = get_post_meta($item_id, 'event_end_date', true);

                        // Fetch attributes for simple products and variations
                        $attributes = array();
                        $parent_product = $is_variation ? wc_get_product($product->get_parent_id()) : $product;
                        $variation_attributes = $is_variation ? $product->get_attributes() : array();
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
                            $value = isset($variation_attributes[$key]) ? $variation_attributes[$key] : '';
                            if (empty($value)) {
                                $terms = wc_get_product_terms($edit_product_id, $key, array('fields' => 'names'));
                                $value = !empty($terms) ? $terms[0] : '';
                            }
                            $attributes[$key] = $value;
                        }

                        // Get the venue name for display
                        $venue_slug = $attributes['pa_intersoccer-venues'];
                        $venue_display = isset($venue_names[$venue_slug]) ? $venue_names[$venue_slug] : $venue_slug;
                    ?>
                        <tr data-product-id="<?php echo esc_attr($item_id); ?>" data-is-variation="<?php echo $is_variation ? '1' : '0'; ?>" data-is-camp="<?php echo $is_camp ? '1' : '0'; ?>">
                            <td><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($item_id); ?>" class="product-checkbox" /></td>
                            <td>
                                <?php echo esc_html($product_name); ?>
                                <div class="row-actions">
                                    <span class="edit"><a href="#" class="quick-edit-link"><?php echo esc_html__('Quick Edit', 'intersoccer-player-management'); ?></a></span>
                                </div>
                            </td>
                            <td><?php echo esc_html(implode(', ', $product_categories)); ?></td>
                            <td><?php echo esc_html($attributes['pa_event-terms']); ?></td>
                            <td><?php echo esc_html($venue_display); ?></td>
                            <td><?php echo esc_html($attributes['pa_booking-type']); ?></td>
                            <td><?php echo esc_html($attributes['pa_age-group']); ?></td>
                            <td>
                                <input type="hidden" name="is_variation[<?php echo esc_attr($item_id); ?>]" value="<?php echo $is_variation ? '1' : '0'; ?>">
                                <?php if ($event_id && tribe_is_event($event_id)): ?>
                                    <span style="color: green; font-size: 16px;">✔</span>
                                <?php else: ?>
                                    <span style="color: red; font-size: 16px;">✘</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($event_title); ?></td>
                        </tr>
                        <tr class="quick-edit-row" style="display: none;">
                            <td colspan="9">
                                <div class="quick-edit-form">
                                    <h3><?php echo esc_html__('Quick Edit: ', 'intersoccer-player-management'); ?><?php echo esc_html($product_name); ?></h3>
                                    <div class="form-field">
                                        <label for="event_terms_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Event Terms:', 'intersoccer-player-management'); ?></label>
                                        <select id="event_terms_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_event-terms">
                                            <option value=""><?php echo esc_html__('Select Term', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($event_terms as $term): ?>
                                                <option value="<?php echo esc_attr($term); ?>" <?php selected($attributes['pa_event-terms'], $term); ?>>
                                                    <?php echo esc_html($term); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="venue_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Venue:', 'intersoccer-player-management'); ?></label>
                                        <select id="venue_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_intersoccer-venues">
                                            <option value=""><?php echo esc_html__('Select Venue', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($venues as $venue): ?>
                                                <option value="<?php echo esc_attr($venue); ?>" <?php selected($attributes['pa_intersoccer-venues'], $venue); ?>>
                                                    <?php echo esc_html($venue_names[$venue] ?? $venue); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="hot_lunch_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Hot Lunch:', 'intersoccer-player-management'); ?></label>
                                        <select id="hot_lunch_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_hot-lunch">
                                            <option value=""><?php echo esc_html__('Select Hot Lunch', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($hot_lunches as $hot_lunch): ?>
                                                <option value="<?php echo esc_attr($hot_lunch); ?>" <?php selected($attributes['pa_hot-lunch'], $hot_lunch); ?>>
                                                    <?php echo esc_html($hot_lunch); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="event_times_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Event Times:', 'intersoccer-player-management'); ?></label>
                                        <select id="event_times_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_event-times">
                                            <option value=""><?php echo esc_html__('Select Event Time', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($event_times as $time): ?>
                                                <option value="<?php echo esc_attr($time); ?>" <?php selected($attributes['pa_event-times'], $time); ?>>
                                                    <?php echo esc_html($time); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="holidays_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Holidays:', 'intersoccer-player-management'); ?></label>
                                        <select id="holidays_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_holidays">
                                            <option value=""><?php echo esc_html__('Select Holidays', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($holidays as $holiday): ?>
                                                <option value="<?php echo esc_attr($holiday); ?>" <?php selected($attributes['pa_holidays'], $holiday); ?>>
                                                    <?php echo esc_html($holiday); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="booking_type_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Booking Type:', 'intersoccer-player-management'); ?></label>
                                        <select id="booking_type_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_booking-type">
                                            <option value=""><?php echo esc_html__('Select Booking Type', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($booking_types as $type): ?>
                                                <option value="<?php echo esc_attr($type); ?>" <?php selected($attributes['pa_booking-type'], $type); ?>>
                                                    <?php echo esc_html($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="age_group_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Age Group:', 'intersoccer-player-management'); ?></label>
                                        <select id="age_group_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_age-group">
                                            <option value=""><?php echo esc_html__('Select Age Group', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($age_groups as $age_group): ?>
                                                <option value="<?php echo esc_attr($age_group); ?>" <?php selected($attributes['pa_age-group'], $age_group); ?>>
                                                    <?php echo esc_html($age_group); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field day-of-week-field" style="<?php echo $is_camp ? 'display: none;' : ''; ?>">
                                        <label for="day_of_week_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Day of Week:', 'intersoccer-player-management'); ?></label>
                                        <select id="day_of_week_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_day-of-week">
                                            <option value=""><?php echo esc_html__('Select Day', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($days_of_week as $day): ?>
                                                <option value="<?php echo esc_attr($day); ?>" <?php selected($attributes['pa_day-of-week'], $day); ?>>
                                                    <?php echo esc_html($day); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label for="start_date_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Start Date:', 'intersoccer-player-management'); ?></label>
                                        <input type="text" id="start_date_<?php echo esc_attr($item_id); ?>" class="attribute-field datepicker" data-meta="event_start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="<?php echo esc_attr__('Select Start Date', 'intersoccer-player-management'); ?>" />
                                    </div>
                                    <div class="form-field">
                                        <label for="end_date_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('End Date:', 'intersoccer-player-management'); ?></label>
                                        <input type="text" id="end_date_<?php echo esc_attr($item_id); ?>" class="attribute-field datepicker" data-meta="event_end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="<?php echo esc_attr__('Select End Date', 'intersoccer-player-management'); ?>" />
                                    </div>
                                    <div class="form-field">
                                        <label for="camp_terms_<?php echo esc_attr($item_id); ?>"><?php echo esc_html__('Camp Terms:', 'intersoccer-player-management'); ?></label>
                                        <select id="camp_terms_<?php echo esc_attr($item_id); ?>" class="attribute-field" data-attribute="pa_camp-terms">
                                            <option value=""><?php echo esc_html__('Select Camp Term', 'intersoccer-player-management'); ?></option>
                                            <?php foreach ($camp_terms as $term): ?>
                                                <option value="<?php echo esc_attr($term); ?>" <?php selected($attributes['pa_camp-terms'], $term); ?>>
                                                    <?php echo esc_html($term); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <button type="button" class="button save-attributes"><?php echo esc_html__('Save & Sync', 'intersoccer-player-management'); ?></button>
                                        <button type="button" class="button cancel-quick-edit"><?php echo esc_html__('Cancel', 'intersoccer-player-management'); ?></button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize date picker
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd'
    });

    // Show quick edit link on hover
    $('tr[data-product-id]').hover(
        function() {
            $(this).find('.row-actions').show();
        },
        function() {
            $(this).find('.row-actions').hide();
        }
    );

    // Show quick edit form
    $('.quick-edit-link').on('click', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var $quickEditRow = $row.next('.quick-edit-row');
        $quickEditRow.show();
        $row.hide();
    });

    // Cancel quick edit
    $('.cancel-quick-edit').on('click', function() {
        var $quickEditRow = $(this).closest('.quick-edit-row');
        var $mainRow = $quickEditRow.prev();
        $quickEditRow.hide();
        $mainRow.show();
    });

    // Save attributes and sync
    $('.save-attributes').on('click', function() {
        var $quickEditRow = $(this).closest('.quick-edit-row');
        var $mainRow = $quickEditRow.prev();
        var productId = $mainRow.data('product-id');
        var isVariation = $mainRow.data('is-variation');
        var attributes = {};

        // Collect all attributes for the row
        $quickEditRow.find('.attribute-field').each(function() {
            var key = $(this).data('attribute') || $(this).data('meta');
            var value = $(this).val();
            attributes[key] = value;
        });

        // AJAX request to save attributes and sync
        $.ajax({
            url: intersoccerSync.ajax_url,
            type: 'POST',
            data: {
                action: 'intersoccer_save_and_sync_product',
                nonce: intersoccerSync.nonce,
                product_id: productId,
                is_variation: isVariation,
                attributes: attributes
            },
            success: function(response) {
                if (response.success) {
                    alert('Product updated and event generated successfully.');
                    // Update table cells
                    $mainRow.find('td:nth-child(3)').text(attributes['pa_event-terms'] || '');
                    $mainRow.find('td:nth-child(4)').text(attributes['pa_intersoccer-venues'] ? (intersoccerSync.venueNames[attributes['pa_intersoccer-venues']] || attributes['pa_intersoccer-venues']) : '');
                    $mainRow.find('td:nth-child(5)').text(attributes['pa_booking-type'] || '');
                    $mainRow.find('td:nth-child(6)').text(attributes['pa_age-group'] || '');
                    $mainRow.find('td:nth-child(7)').html('<span style="color: green; font-size: 16px;">✔</span>');
                    $mainRow.find('td:nth-child(8)').text(response.data.event_title);
                    $quickEditRow.hide();
                    $mainRow.show();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred while saving the product: ' + error);
            }
        });
    });

    // Select all products checkbox
    $('#select-all-products').on('change', function() {
        $('.product-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>

<style>
    .wp-list-table th, .wp-list-table td {
        padding: 10px;
    }
    form h3 {
        margin-top: 20px;
    }
    form p {
        margin-bottom: 15px;
    }
    .tablenav .actions select {
        margin-right: 10px;
        padding: 5px;
        width: 200px;
    }
    .quick-edit-form {
        padding: 20px;
        background: #f1f1f1;
        border: 1px solid #ddd;
    }
    .quick-edit-form .form-field {
        margin-bottom: 15px;
    }
    .quick-edit-form .form-field label {
        display: inline-block;
        width: 150px;
        font-weight: bold;
    }
    .quick-edit-form .form-field select,
    .quick-edit-form .form-field input[type="text"] {
        width: 200px;
    }
    .row-actions {
        display: none;
        padding-top: 2px;
        font-size: 13px;
    }
    .row-actions .edit a {
        color: #0073aa;
        text-decoration: none;
    }
    .row-actions .edit a:hover {
        color: #00a0d2;
    }
    /* Toggle Switch Styles */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 20px;
        margin-right: 10px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #2196F3;
    }
    input:checked + .slider:before {
        transform: translateX(20px);
    }
    .slider.round {
        border-radius: 20px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
    /* Filter Container Styles */
    .filter-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 15px;
    }
    .filter-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .filter-item label {
        margin-bottom: 5px;
    }
    .filter-item select {
        width: 200px;
    }
    .toggle-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
</style>
<?php
// End of PHP file
?>

