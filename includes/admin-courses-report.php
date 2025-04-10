<?php
/**
 * Admin Feature: Courses Report Tab for Viewing Course Orders
 */

// Render the Courses Report tab content
function intersoccer_render_courses_report_tab() {
    // Filter parameters
    $filter_course_name = isset($_GET['course_name']) ? sanitize_text_field($_GET['course_name']) : '';
    $filter_date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '';
    $filter_venue = isset($_GET['venue']) ? sanitize_text_field($_GET['venue']) : '';

    // Fetch all orders with course products (products with pa_booking-type = 'full_term')
    $args = array(
        'limit' => -1,
        'status' => array('completed', 'processing'),
    );

    // Apply date range filter
    if (!empty($filter_date_range)) {
        $dates = explode(' to ', $filter_date_range);
        if (count($dates) === 2) {
            $start_date = sanitize_text_field($dates[0]);
            $end_date = sanitize_text_field($dates[1]);
            $args['date_created'] = "$start_date...$end_date";
        }
    }

    $orders = wc_get_orders($args);

    // Collect all courses with filtering
    $all_courses = array();
    foreach ($orders as $order) {
        $items = $order->get_items();
        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            // Check if the product has pa_booking-type = 'full_term'
            $booking_type = $product->get_attribute('pa_booking-type');
            if ($booking_type !== 'full_term') {
                continue;
            }

            // Get venue from pa_intersoccer-venues
            $venue = $product->get_attribute('pa_intersoccer-venues');
            if (empty($venue)) {
                $venue_terms = wc_get_product_terms($product->get_id(), 'pa_intersoccer-venues', array('fields' => 'names'));
                $venue = !empty($venue_terms) ? $venue_terms[0] : 'TBD';
            }

            // Get start date (we'll use the order date as a fallback if no specific start date is available)
            $start_date = get_post_meta($product->get_id(), '_start_date', true) ?: $order->get_date_created()->date('Y-m-d');

            // Apply filters
            $matches_course_name = empty($filter_course_name) || stripos($product->get_name(), $filter_course_name) !== false;
            $matches_venue = empty($filter_venue) || stripos($venue, $filter_venue) !== false;

            if ($matches_course_name && $matches_venue) {
                $all_courses[] = array(
                    'order' => $order,
                    'product' => $product,
                    'venue' => $venue,
                    'start_date' => $start_date,
                );
            }
        }
    }

    // Get unique venues for filter dropdown
    $venues = array();
    foreach ($all_courses as $course_data) {
        if ($course_data['venue'] && !in_array($course_data['venue'], $venues)) {
            $venues[] = $course_data['venue'];
        }
    }
    sort($venues);

    ?>
    <h2><?php _e('Courses Report', 'intersoccer-player-management'); ?></h2>

    <!-- Filters -->
    <form method="get" action="">
        <input type="hidden" name="page" value="intersoccer-players" />
        <input type="hidden" name="tab" value="courses-report" />
        <p class="search-box">
            <label for="course_name"><?php _e('Course Name:', 'intersoccer-player-management'); ?></label>
            <input type="text" id="course_name" name="course_name" value="<?php echo esc_attr($filter_course_name); ?>" placeholder="<?php _e('Enter course name', 'intersoccer-player-management'); ?>" />

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
            <a href="?page=intersoccer-players&tab=courses-report" class="button"><?php _e('Clear Filters', 'intersoccer-player-management'); ?></a>
        </p>
    </form>

    <?php if (empty($all_courses)): ?>
        <p><?php _e('No course orders found.', 'intersoccer-player-management'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Order ID', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Course Name', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Start Date', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Venue', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Customer', 'intersoccer-player-management'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_courses as $course_data): ?>
                    <?php
                    $order = $course_data['order'];
                    $product = $course_data['product'];
                    $venue = $course_data['venue'];
                    $start_date = $course_data['start_date'];
                    ?>
                    <tr>
                        <td><?php echo esc_html($order->get_id()); ?></td>
                        <td><?php echo esc_html($product->get_name()); ?></td>
                        <td><?php echo esc_html($start_date ? date('Y-m-d', strtotime($start_date)) : 'N/A'); ?></td>
                        <td><?php echo esc_html($venue ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?> (<?php echo esc_html($order->get_billing_email()); ?>)</td>
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
    </style>
    <?php
}
?>
