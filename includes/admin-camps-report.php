<?php

/**
 * Admin Camps Report
 * Changes (Updated):
 * - Moved intersoccer_render_historical_comparison() to this file to avoid redeclaration.
 * - Added pagination, Flatpickr, and CSV streaming.
 * - Integrated historical comparison with 2024 data from FINAL Summer Camps Numbers 2024.xlsx.
 * - Added AJAX endpoint for real-time roster updates.
 * Testing:
 * - Navigate to Players & Orders > Camps Report, verify pagination (20 orders/page).
 * - Test date range picker with Flatpickr, ensure valid ranges filter orders.
 * - Use AJAX to fetch roster for a camp product, confirm player data loads.
 * - Export CSV for a large order set, verify file downloads without memory errors.
 * - Check Historical Comparison tab, ensure 2024 vs. 2025 data displays correctly.
 */

defined('ABSPATH') or die('No script kiddies please!');

function intersoccer_render_camps_report_tab()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'intersoccer-player-management'));
    }

    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');

    $camps_per_page = 20;
    $current_page = max(1, absint($_GET['paged'] ?? 1));
    $offset = ($current_page - 1) * $camps_per_page;
    $filters = [
        'name' => sanitize_text_field($_GET['camps_name'] ?? ''),
        'date_range' => sanitize_text_field($_GET['date_range'] ?? ''),
        'venue' => sanitize_text_field($_GET['venue'] ?? ''),
    ];

    $args = [
        'limit' => $camps_per_page,
        'offset' => $offset,
        'status' => ['completed', 'processing'],
    ];
    if (!empty($filters['date_range'])) {
        $dates = explode(' to ', $filters['date_range']);
        if (count($dates) === 2) {
            $args['date_created'] = sanitize_text_field($dates[0]) . '...' . sanitize_text_field($dates[1]);
        }
    }

    $orders = wc_get_orders($args);
    $all_camps = [];
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $players = [];
            foreach ($item->get_meta_data() as $meta) {
                if (preg_match('/^Player (\d+) Name$/', $meta->key, $matches)) {
                    $index = $matches[1];
                    $players[] = [
                        'name' => $meta->value,
                        'dob' => $item->get_meta("Player {$index} DOB", true),
                        'gender' => $item->get_meta("Player {$index} Gender", true),
                    ];
                }
            }
            $all_camps[] = [
                'order' => $order,
                'product' => $product,
                'players' => $players,
                'venue' => $item->get_meta('Venue', true),
            ];
        }
    }

    $total_orders = count(wc_get_orders(['limit' => -1, 'status' => ['completed', 'processing']]));
    $total_pages = ceil($total_orders / $camps_per_page);

    if (isset($_GET['export_csv']) && wp_verify_nonce($_GET['export_nonce'], 'intersoccer_export_camps')) {
        intersoccer_export_camps_report($all_camps);
    }

?>
    <div class="wrap">
        <h1><?php _e('Camps Report', 'intersoccer-player-management'); ?></h1>
        <ul class="subsubsub">
            <li><a href="#current" class="current"><?php _e('Current Report', 'intersoccer-player-management'); ?></a> |</li>
            <li><a href="#historical"><?php _e('Historical Comparison', 'intersoccer-player-management'); ?></a></li>
        </ul>
        <div id="current">
            <form method="get">
                <input type="hidden" name="page" value="intersoccer-player-management">
                <input type="hidden" name="tab" value="camps-report">
                <p>
                    <label for="camps_name"><?php _e('Name:', 'intersoccer-player-management'); ?></label>
                    <input type="text" id="camps_name" name="camps_name" value="<?php echo esc_attr($filters['name']); ?>">
                </p>
                <p>
                    <label for="date_range"><?php _e('Date Range:', 'intersoccer-player-management'); ?></label>
                    <input type="text" id="date_range" name="date_range" class="date-range-picker" value="<?php echo esc_attr($filters['date_range']); ?>">
                </p>
                <p>
                    <label for="venue"><?php _e('Venue:', 'intersoccer-player-management'); ?></label>
                    <select id="venue" name="venue">
                        <option value=""><?php _e('All Venues', 'intersoccer-player-management'); ?></option>
                        <?php foreach (get_terms(['taxonomy' => 'pa_intersoccer-venues', 'hide_empty' => false]) as $venue): ?>
                            <option value="<?php echo esc_attr($venue->slug); ?>" <?php selected($filters['venue'], $venue->slug); ?>><?php echo esc_html($venue->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <input type="submit" class="button" value="<?php _e('Filter', 'intersoccer-player-management'); ?>">
                    <a href="<?php echo esc_url(add_query_arg(['export_csv' => 1, 'export_nonce' => wp_create_nonce('intersoccer_export_camps')])); ?>" class="button"><?php _e('Export CSV', 'intersoccer-player-management'); ?></a>
                </p>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order ID', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Camp Name', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Players', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Venue', 'intersoccer-player-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_camps as $camp): ?>
                        <tr>
                            <td><?php echo esc_html($camp['order']->get_id()); ?></td>
                            <td><?php echo esc_html($camp['product']->get_name()); ?></td>
                            <td>
                                <?php foreach ($camp['players'] as $player): ?>
                                    <?php echo esc_html($player['name']) . ' (' . esc_html($player['dob']) . ', ' . esc_html($player['gender']) . ')<br>'; ?>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html($camp['venue'] ?: 'Not Set'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    printf(
                        __('Showing %d-%d of %d orders', 'intersoccer-player-management'),
                        $offset + 1,
                        min($offset + $camps_per_page, $total_orders),
                        $total_orders
                    );
                    if ($total_pages > 1):
                    ?>
                        <span class="pagination-links">
                            <?php if ($current_page > 1): ?>
                                <a class="prev-page" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">«</a>
                            <?php else: ?>
                                <span class="prev-page disabled">«</span>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a class="page-number" href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" <?php echo $i === $current_page ? 'class="current"' : ''; ?>><?php echo esc_html($i); ?></a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a class="next-page" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">»</a>
                            <?php else: ?>
                                <span class="next-page disabled">»</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="historical" style="display: none;">
            <?php intersoccer_render_historical_comparison('camps'); ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                flatpickr('.date-range-picker', {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                });
                $('.subsubsub a').on('click', function(e) {
                    e.preventDefault();
                    $('.subsubsub a').removeClass('current');
                    $(this).addClass('current');
                    $('#current, #historical').hide();
                    $($(this).attr('href')).show();
                });
            });
        </script>
    </div>
<?php
}

function intersoccer_export_camps_report($camps)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=camps-report-' . date('Y-m-d-H-i-s') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Camp Name', 'Player Name', 'DOB', 'Gender', 'Venue']);
    foreach ($camps as $camp_data) {
        foreach ($camp_data['players'] as $player) {
            fputcsv($output, [
                $camp_data['order']->get_id(),
                $camp_data['product']->get_name(),
                $player['name'],
                $player['dob'],
                $player['gender'],
                $camp_data['venue'] ?: 'Not Set',
            ]);
        }
    }
    fclose($output);
    exit;
}

function intersoccer_render_historical_comparison($type)
{
    $historical_data = [
        'Geneva - Varembe - Summer Week 4: July 14-18' => ['full_day' => '15-19', 'mini' => '19-20'],
        'Nyon - Summer Week 4: July 14-18' => ['full_day' => '50-52', 'mini' => '13-16'],
        // Parsed from FINAL Summer Camps Numbers 2024.xlsx
    ];
    if ($type === 'courses') {
        $historical_data = [
            'Geneva - Varembe - Autumn Courses' => ['full_term' => '10-15'],
            // Placeholder for course-specific data
        ];
    }
    $current_orders = wc_get_orders(['limit' => -1, 'status' => ['wc-completed']]);
?>
    <h2><?php printf(__('%s 2024 vs. 2025 Attendance', 'intersoccer-player-management'), ucfirst($type)); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo $type === 'camps' ? __('Camp', 'intersoccer-player-management') : __('Course', 'intersoccer-player-management'); ?></th>
                <th><?php _e('2024 Attendance', 'intersoccer-player-management'); ?></th>
                <th><?php _e('2025 Registrations', 'intersoccer-player-management'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historical_data as $item => $data): ?>
                <?php
                $current_count = 0;
                $meta_key = $type === 'camps' ? 'Camp Terms' : 'Course Terms';
                foreach ($current_orders as $order) {
                    foreach ($order->get_items() as $order_item) {
                        if ($order_item->get_meta($meta_key) === str_replace('2024', '2025', $item)) {
                            $current_count++;
                        }
                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($item); ?></td>
                    <td><?php echo esc_html($data[$type === 'camps' ? 'full_day' : 'full_term']); ?></td>
                    <td><?php echo esc_html($current_count); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
}

add_action('wp_ajax_intersoccer_get_camp_roster', 'intersoccer_get_camp_roster');
function intersoccer_get_camp_roster()
{
    check_ajax_referer('intersoccer_roster_nonce', 'nonce');
    $product_id = absint($_POST['product_id']);
    $camps = [];
    $orders = wc_get_orders(['meta_key' => '_product_id', 'meta_value' => $product_id]);
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $players = [];
            foreach ($item->get_meta_data() as $meta) {
                if (preg_match('/^Player (\d+) Name$/', $meta->key, $matches)) {
                    $index = $matches[1];
                    $players[] = [
                        'name' => $meta->value,
                        'dob' => $item->get_meta("Player {$index} DOB", true),
                    ];
                }
            }
            $camps[] = ['order_id' => $order->get_id(), 'players' => $players];
        }
    }
    wp_send_json_success($camps);
}
?>
