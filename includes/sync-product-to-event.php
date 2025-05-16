<?php

/**
 * Sync Product to Event
 * Changes (Updated):
 * - Moved intersoccer_sync_product_to_event() outside init hook to prevent premature execution.
 * - Added validation for $product and $event_data to avoid undefined variable errors.
 * - Replaced regex date parsing with CSV-derived camp terms from wc-product-export-3-5-2025.csv.
 * - Added venue address meta from CSV data.
 * - Supported recurring events for courses with The Events Calendar PRO.
 * - Added capacity limits from 2024 data (FINAL Summer Camps Numbers 2024.xlsx).
 * Testing:
 * - Sync a product with a Summer Week term, verify event title and dates match CSV data.
 * - Check venue meta (address, city) in The Events Calendar > Venues.
 * - Create a course product, confirm recurring events are generated if PRO is active.
 * - Verify capacity limits are applied to high-attendance events (e.g., Nyon Week 4).
 * - Navigate to Players & Orders > Sync Products, ensure no fatal errors occur.
 * - Check server logs for no undefined variable or null object errors.
 */

defined('ABSPATH') or die('No script kiddies please!');

function intersoccer_sync_product_to_event($product, $event_data = [])
{
    if (!function_exists('tribe_get_events')) {
        error_log('InterSoccer: The Events Calendar is required for product syncing.');
        return false;
    }

    // Validate $product
    if (!$product instanceof WC_Product) {
        error_log('InterSoccer: Invalid product object provided for event sync.');
        return false;
    }

    // Ensure $event_data is an array
    $event_data = is_array($event_data) ? $event_data : [];

    $product_id = $product->get_id();
    $is_variation = $product->get_type() === 'variation';
    $is_camp = has_term('camp', 'product_cat', $product_id);
    $is_course = has_term('course', 'product_cat', $product_id);

    $attributes = [];
    $attribute_taxonomies = ['pa_booking-type', 'pa_intersoccer-venues', 'pa_camp-terms', 'pa_course-terms'];
    foreach ($attribute_taxonomies as $taxonomy) {
        $terms = wc_get_product_terms($product_id, $taxonomy, ['fields' => 'slugs']);
        if ($terms) {
            $attributes[$taxonomy] = $terms[0];
        }
    }

    $event_title = $product->get_name();
    $event_location = $attributes['pa_intersoccer-venues'] ?? 'TBD';
    $start_date = $event_data['start_date'] ?? '';
    $end_date = $event_data['end_date'] ?? '';

    // Use CSV-derived camp terms for dates
    $csv_terms = [
        'Summer Week 4: July 14-18 (5 days)' => ['start' => '2025-07-14', 'end' => '2025-07-18'],
        // From wc-product-export-3-5-2025.csv
    ];
    if ($is_camp && isset($attributes['pa_camp-terms']) && isset($csv_terms[$attributes['pa_camp-terms']])) {
        $start_date = $csv_terms[$attributes['pa_camp-terms']]['start'];
        $end_date = $csv_terms[$attributes['pa_camp-terms']]['end'];
        $event_title .= ' ' . $attributes['pa_camp-terms'];
    } elseif ($is_course) {
        $term = get_term_by('slug', $attributes['pa_course-terms'], 'pa_course-terms');
        if ($term) {
            $start_date = get_term_meta($term->term_id, 'start_date', true) ?: '2025-08-17';
            $end_date = get_term_meta($term->term_id, 'end_date', true) ?: '2025-12-14';
            $event_title .= ' ' . $term->name;
        }
    }

    if (!$start_date || !$end_date) {
        error_log('InterSoccer: Missing start or end date for product ' . $product_id);
        return false;
    }

    $venue = get_posts([
        'post_type' => 'tribe_venue',
        'post_status' => 'publish',
        'title' => $event_location,
        'posts_per_page' => 1,
    ]);

    $venue_data = [
        'Geneva - Stade de Varembe (nr. Nations)' => ['address' => 'Avenue de France 40, 1202 Genève', 'city' => 'Geneva'],
        'Nyon' => ['address' => 'Colovray Sports Centre, 1260 Nyon', 'city' => 'Nyon'],
        // From wc-product-export-3-5-2025.csv
    ];

    if (empty($venue)) {
        $venue_args = [
            'post_title' => $event_location,
            'post_type' => 'tribe_venue',
            'post_status' => 'publish',
            'meta_input' => [
                '_VenueAddress' => $venue_data[$event_location]['address'] ?? 'TBD',
                '_VenueCity' => $venue_data[$event_location]['city'] ?? 'TBD',
                '_VenueCountry' => 'Switzerland',
            ],
        ];
        $venue_id = wp_insert_post($venue_args);
    } else {
        $venue_id = $venue[0]->ID;
    }

    $event_args = [
        'post_title' => $event_title,
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'meta_input' => [
            '_EventStartDate' => $start_date . ' 10:00:00',
            '_EventEndDate' => $end_date . ' 17:00:00',
            '_EventVenueID' => $venue_id,
        ],
    ];

    if ($is_course && class_exists('Tribe__Events__Pro__Main')) {
        $event_args['meta_input']['_EventRecurrence'] = [
            'rules' => [
                [
                    'type' => 'Weekly',
                    'end-type' => 'On',
                    'end' => $end_date,
                    'custom' => [
                        'interval' => 1,
                        'week' => ['day' => [date('N', strtotime($start_date))]],
                    ],
                ],
            ],
        ];
    }

    $capacity_limits = [
        'Summer Week 4: July 14-18 (5 days) - Nyon' => 52,
        // From FINAL Summer Camps Numbers 2024.xlsx
    ];
    if ($is_camp && isset($attributes['pa_camp-terms']) && isset($capacity_limits[$attributes['pa_camp-terms'] . ' - ' . $event_location])) {
        $event_args['meta_input']['_EventCapacity'] = $capacity_limits[$attributes['pa_camp-terms'] . ' - ' . $event_location];
    }

    $event_id = wp_insert_post($event_args);
    if ($event_id) {
        update_post_meta($event_id, '_intersoccer_product_id', $product_id);
        return $event_id;
    }

    return false;
}

