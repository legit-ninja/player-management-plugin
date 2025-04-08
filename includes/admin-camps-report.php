<?php
/**
 * Admin Feature: Camps Report Tab for Summer and Spring-Summer Football Camps
 */

// Function to get the week range for a given week number in 2025
function get_week_range($week_number) {
    $start_date = new DateTime('2025-06-23'); // Start of Week 1: June 23, 2025
    $start_date->modify('+' . (($week_number - 1) * 7) . ' days');
    $end_date = clone $start_date;
    $end_date->modify('+4 days'); // Most weeks are 5 days (Mon-Fri)

    // Special case for Week 6 (July 29 - August 2, 4 days due to August 1 holiday)
    if ($week_number == 6) {
        $end_date->modify('-1 day'); // 4 days: Mon, Wed, Thu, Fri
    }

    return array(
        'start' => $start_date->format('Y-m-d'),
        'end' => $end_date->format('Y-m-d'),
        'label' => $start_date->format('F j') . ' - ' . $end_date->format('F j'),
    );
}

// Function to calculate camp registrations
function calculate_camp_registrations($event, $camp_type) {
    $attendees = tribe_tickets_get_attendees($event->ID);
    $registrations = array(
        'full_week' => 0,
        'buyclub' => 0,
        'days' => array('M' => 0, 'T' => 0, 'W' => 0, 'T' => 0, 'F' => 0),
        'total_min' => 0,
        'total_max' => 0,
    );

    foreach ($attendees as $attendee) {
        $order_id = $attendee['order_id'];
        $order = wc_get_order($order_id);
        if (!$order) continue;

        $user_id = $order->get_user_id();
        if (!$user_id) continue;

        $player_name = get_post_meta($attendee['ID'], 'player_name', true);
        if (!$player_name) continue;

        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
        $player_data = null;
        foreach ($players as $player) {
            if ($player['name'] === $player_name) {
                $player_data = $player;
                break;
            }
        }
        if (!$player_data) continue;

        // Determine age to filter by camp type (Full Day: 5-13, Mini-Half Day: 3-5)
        $dob = $player_data['dob'];
        $age = $dob ? date_diff(date_create($dob), date_create('2025-06-01'))->y : 0;
        if ($camp_type === 'full_day' && ($age < 5 || $age > 13)) continue;
        if ($camp_type === 'mini_half_day' && ($age < 3 || $age > 5)) continue;

        // Assume metadata for registration type and days attending
        $registration_type = get_post_meta($attendee['ID'], 'registration_type', true) ?: 'individual'; // 'full_week' or 'individual'
        $is_buyclub = get_post_meta($attendee['ID'], 'buyclub_member', true) === 'yes';
        $days_attending = get_post_meta($attendee['ID'], 'days_attending', true) ?: array('M', 'T', 'W', 'T', 'F'); // Array of days (M, T, W, T, F)

        if ($registration_type === 'full_week') {
            $registrations['full_week']++;
            $days = array('M', 'T', 'W', 'T', 'F');
            if (isset($event->week_number) && $event->week_number == 6) {
                $days = array('M', 'W', 'T', 'F'); // 4-day week
            }
            foreach ($days as $day) {
                $registrations['days'][$day]++;
            }
        } else {
            foreach ($days_attending as $day) {
                if (in_array($day, array('M', 'T', 'W', 'T', 'F'))) {
                    $registrations['days'][$day]++;
                }
            }
        }

        if ($is_buyclub) {
            $registrations['buyclub']++;
        }
    }

    // Calculate total min-max
    $registrations['total_min'] = $registrations['full_week'] * (isset($event->week_number) && $event->week_number == 6 ? 4 : 5);
    foreach ($registrations['days'] as $day => $count) {
        $registrations['total_min'] += $count;
    }
    $registrations['total_max'] = $registrations['total_min']; // For simplicity, assume min = max unless flexible days are specified

    return $registrations;
}

// Display the Camps Report
?>
<h2><?php _e('Camps Report', 'intersoccer-player-management'); ?></h2>
<?php
// Define the weeks for Summer 2025 (June 23 - August 30)
$weeks = array();
for ($i = 1; $i <= 10; $i++) {
    $weeks[$i] = get_week_range($i);
}

// Fetch all events for Summer 2025
$events = tribe_get_events(array(
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'meta_query' => array(
        array(
            'key' => '_EventStartDate',
            'value' => array('2025-06-23 00:00:00', '2025-08-30 23:59:59'),
            'compare' => 'BETWEEN',
            'type' => 'DATETIME',
        ),
    ),
));

// Organize events by week and canton
$camps_by_week = array();
foreach ($events as $event) {
    $start_date = new DateTime(get_post_meta($event->ID, '_EventStartDate', true));
    $week_number = 0;
    for ($i = 1; $i <= 10; $i++) {
        $week_start = new DateTime($weeks[$i]['start']);
        $week_end = new DateTime($weeks[$i]['end']);
        if ($start_date >= $week_start && $start_date <= $week_end) {
            $week_number = $i;
            break;
        }
    }
    if (!$week_number) continue;

    $event->week_number = $week_number;

    // Get canton and location from attributes
    $canton = '';
    $location = '';
    $venue_terms = wc_get_product_terms($event->ID, 'pa_intersoccer-venues', array('fields' => 'names'));
    if (!empty($venue_terms)) {
        $venue = $venue_terms[0];
        if (stripos($venue, 'geneva') !== false || stripos($venue, 'vessy') !== false || stripos($venue, 'varembé') !== false || stripos($venue, 'versoix') !== false || stripos($venue, 'nyon') !== false) {
            $canton = 'GENEVA';
        } elseif (stripos($venue, 'zug') !== false || stripos($venue, 'luzern') !== false || stripos($venue, 'iszl') !== false || stripos($venue, 'cs cham') !== false) {
            $canton = 'Zug/Luzern';
        } elseif (stripos($venue, 'zurich') !== false || stripos($venue, 'seefeld') !== false || stripos($venue, 'langnau') !== false || stripos($venue, 'adliswil') !== false || stripos($venue, 'wallisellen') !== false || stripos($venue, 'zis wadenswil') !== false) {
            $canton = 'ZURICH';
        } elseif (stripos($venue, 'basel') !== false || stripos($venue, 'rankhof') !== false || stripos($venue, 'bachgraben') !== false) {
            $canton = 'Basel';
        } elseif (stripos($venue, 'etoy') !== false) {
            $canton = 'Etoy';
        } elseif (stripos($venue, 'pully') !== false || stripos($venue, 'rochettaz') !== false) {
            $canton = 'Pully';
        }
        $location = $venue;
    }

    if (!$canton || !$location) continue;

    // Determine camp type (Full Day or Mini-Half Day) based on age range
    $age_range = wc_get_product_terms($event->ID, 'pa_age-open', array('fields' => 'names'));
    $age_range = !empty($age_range) ? $age_range[0] : '5-13'; // Default to Full Day
    $camp_type = (stripos($age_range, '3-5') !== false) ? 'mini_half_day' : 'full_day';

    // Store event data
    if (!isset($camps_by_week[$week_number][$canton][$location])) {
        $camps_by_week[$week_number][$canton][$location] = array(
            'full_day' => array(),
            'mini_half_day' => array(),
        );
    }
    $camps_by_week[$week_number][$canton][$location][$camp_type][] = $event;
}

// Display the report
echo '<h3>' . __('Summer Camps Numbers 2025', 'intersoccer-player-management') . '</h3>';
echo '<table class="wp-list-table widefat fixed striped">';
echo '<thead>';
echo '<tr>';
echo '<th rowspan="2">' . __('Canton', 'intersoccer-player-management') . '</th>';
echo '<th rowspan="2">' . __('Location', 'intersoccer-player-management') . '</th>';
echo '<th colspan="8">' . __('Full Day Camps', 'intersoccer-player-management') . '</th>';
echo '<th colspan="8">' . __('Mini - Half Day Camps', 'intersoccer-player-management') . '</th>';
echo '</tr>';
echo '<tr>';
echo '<th>' . __('Full Week', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('BuyClub', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('M', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('T', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('W', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('T', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('F', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('Total min-max', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('Full Week', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('BuyClub', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('M', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('T', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('W', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('T', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('F', 'intersoccer-player-management') . '</th>';
echo '<th>' . __('Total min-max', 'intersoccer-player-management') . '</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$total_full_week = array('full_day' => 0, 'mini_half_day' => 0);
$total_buyclub = array('full_day' => 0, 'mini_half_day' => 0);
$total_individual_days = array('full_day' => 0, 'mini_half_day' => 0);

foreach ($weeks as $week_number => $week) {
    echo '<tr><td colspan="18"><strong>' . sprintf(__('Week %d: %s', 'intersoccer-player-management'), $week_number, $week['label']) . '</strong></td></tr>';

    if (!isset($camps_by_week[$week_number])) {
        echo '<tr><td colspan="18">' . __('No camps scheduled for this week.', 'intersoccer-player-management') . '</td></tr>';
        continue;
    }

    foreach ($camps_by_week[$week_number] as $canton => $locations) {
        foreach ($locations as $location => $camps) {
            $full_day_data = array(
                'full_week' => 0,
                'buyclub' => 0,
                'days' => array('M' => 0, 'T' => 0, 'W' => 0, 'T' => 0, 'F' => 0),
                'total_min' => 0,
                'total_max' => 0,
            );
            $mini_half_day_data = array(
                'full_week' => 0,
                'buyclub' => 0,
                'days' => array('M' => 0, 'T' => 0, 'W' => 0, 'T' => 0, 'F' => 0),
                'total_min' => 0,
                'total_max' => 0,
            );

            // Aggregate data for Full Day Camps
            if (!empty($camps['full_day'])) {
                foreach ($camps['full_day'] as $event) {
                    $event->week_number = $week_number;
                    $registrations = calculate_camp_registrations($event, 'full_day');
                    $full_day_data['full_week'] += $registrations['full_week'];
                    $full_day_data['buyclub'] += $registrations['buyclub'];
                    foreach ($registrations['days'] as $day => $count) {
                        $full_day_data['days'][$day] += $count;
                    }
                    $full_day_data['total_min'] += $registrations['total_min'];
                    $full_day_data['total_max'] += $registrations['total_max'];
                }
            }

            // Aggregate data for Mini-Half Day Camps
            if (!empty($camps['mini_half_day'])) {
                foreach ($camps['mini_half_day'] as $event) {
                    $event->week_number = $week_number;
                    $registrations = calculate_camp_registrations($event, 'mini_half_day');
                    $mini_half_day_data['full_week'] += $registrations['full_week'];
                    $mini_half_day_data['buyclub'] += $registrations['buyclub'];
                    foreach ($registrations['days'] as $day => $count) {
                        $mini_half_day_data['days'][$day] += $count;
                    }
                    $mini_half_day_data['total_min'] += $registrations['total_min'];
                    $mini_half_day_data['total_max'] += $registrations['total_max'];
                }
            }

            // Update totals
            $total_full_week['full_day'] += $full_day_data['full_week'];
            $total_buyclub['full_day'] += $full_day_data['buyclub'];
            foreach ($full_day_data['days'] as $count) {
                $total_individual_days['full_day'] += $count;
            }
            $total_full_week['mini_half_day'] += $mini_half_day_data['full_week'];
            $total_buyclub['mini_half_day'] += $mini_half_day_data['buyclub'];
            foreach ($mini_half_day_data['days'] as $count) {
                $total_individual_days['mini_half_day'] += $count;
            }

            // Display row
            echo '<tr>';
            echo '<td>' . esc_html($canton) . '</td>';
            echo '<td>' . esc_html($location) . ($full_day_data['total_min'] == 0 && $mini_half_day_data['total_min'] == 0 ? ' - CANCELLED' : '') . '</td>';
            echo '<td>' . esc_html($full_day_data['full_week']) . '</td>';
            echo '<td>' . esc_html($full_day_data['buyclub']) . '</td>';
            echo '<td>' . esc_html($full_day_data['days']['M']) . '</td>';
            echo '<td>' . esc_html($full_day_data['days']['T']) . '</td>';
            echo '<td>' . esc_html($full_day_data['days']['W']) . '</td>';
            echo '<td>' . esc_html($full_day_data['days']['T']) . '</td>';
            echo '<td>' . esc_html($full_day_data['days']['F']) . '</td>';
            echo '<td>' . esc_html($full_day_data['total_min'] . '-' . $full_day_data['total_max']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['full_week']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['buyclub']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['days']['M']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['days']['T']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['days']['W']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['days']['T']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['days']['F']) . '</td>';
            echo '<td>' . esc_html($mini_half_day_data['total_min'] . '-' . $mini_half_day_data['total_max']) . '</td>';
            echo '</tr>';
        }
    }
}

// Display totals
echo '<tr><td colspan="2"><strong>' . __('TOTAL', 'intersoccer-player-management') . '</strong></td>';
echo '<td>' . esc_html($total_full_week['full_day']) . '</td>';
echo '<td>' . esc_html($total_buyclub['full_day']) . '</td>';
echo '<td colspan="4"></td>';
echo '<td>' . esc_html($total_individual_days['full_day']) . '</td>';
echo '<td>' . esc_html($total_full_week['mini_half_day']) . '</td>';
echo '<td>' . esc_html($total_buyclub['mini_half_day']) . '</td>';
echo '<td colspan="4"></td>';
echo '<td>' . esc_html($total_individual_days['mini_half_day']) . '</td>';
echo '<td>' . esc_html($total_buyclub['full_day'] + $total_buyclub['mini_half_day']) . '</td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';
?>

