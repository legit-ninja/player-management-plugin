<?php
/**
 * Admin Feature: Courses Report Tab for Generating Player Reports by Course
 */

// Handle export requests for courses report
if (isset($_GET['export-courses-report'])) {
    export_courses_report();
    exit;
}

// Function to export the courses report as PDF
function export_courses_report() {
    // Clean output buffer to prevent corruption
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Increase memory limit to handle large datasets
    ini_set('memory_limit', '256M');

    // Include TCPDF library
    require_once plugin_dir_path(__FILE__) . '../vendor/tecnickcom/tcpdf/tcpdf.php';

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('InterSoccer Player Management');
    $pdf->SetTitle('Courses Report');
    $pdf->SetSubject('Player Registrations by Course');
    $pdf->SetKeywords('InterSoccer, Courses, Players, Report');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'InterSoccer Courses Report', 'Player Registrations by Course');

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();

    // Fetch all Event Tickets events
    $events = tribe_get_events(array(
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

    $html = '<h1>' . __('Courses Report', 'intersoccer-player-management') . '</h1>';
    $html .= '<table border="1" cellpadding="4">';
    $html .= '<thead><tr style="background-color:#f0f0f0;">';
    $html .= '<th>' . __('Course Name', 'intersoccer-player-management') . '</th>';
    $html .= '<th>' . __('Player Name', 'intersoccer-player-management') . '</th>';
    $html .= '<th>' . __('Age', 'intersoccer-player-management') . '</th>';
    $html .= '<th>' . __('Medical Conditions', 'intersoccer-player-management') . '</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($events as $event) {
        $event_id = $event->ID;
        $attendees = tribe_tickets_get_attendees($event_id);
        $course_players = array();

        foreach ($attendees as $attendee) {
            $order_id = $attendee['order_id'];
            $player_name = get_post_meta($attendee['ID'], 'player_name', true);
            if (!$player_name) {
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                continue;
            }

            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
            $player_data = null;
            foreach ($players as $player) {
                if ($player['name'] === $player_name) {
                    $player_data = $player;
                    break;
                }
            }

            if ($player_data) {
                $dob = $player_data['dob'];
                $age = $dob ? date_diff(date_create($dob), date_create('today'))->y : 'N/A';
                $course_players[] = array(
                    'course_name' => $event->post_title,
                    'player_name' => $player_name,
                    'age' => $age,
                    'medical_conditions' => $player_data['medical_conditions'],
                );
            }
        }

        foreach ($course_players as $player) {
            // Ensure all data is UTF-8 encoded to prevent encoding issues
            $course_name = mb_convert_encoding($player['course_name'], 'UTF-8', 'auto');
            $player_name = mb_convert_encoding($player['player_name'], 'UTF-8', 'auto');
            $age = mb_convert_encoding($player['age'], 'UTF-8', 'auto');
            $medical_conditions = mb_convert_encoding($player['medical_conditions'], 'UTF-8', 'auto');

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($course_name, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($player_name, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($age, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($medical_conditions, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }
    }

    $html .= '</tbody></table>';

    // Write HTML content to PDF
    try {
        $pdf->writeHTML($html, true, false, true, false, '');
    } catch (Exception $e) {
        error_log('TCPDF Error: ' . $e->getMessage());
        wp_die(__('An error occurred while generating the PDF: ', 'intersoccer-player-management') . $e->getMessage());
    }

    // Set proper headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="courses-report-' . date('Y-m-d') . '.pdf"');
    header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');

    // Output PDF
    $pdf->Output('courses-report-' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>

<!-- Courses Report -->
<h2><?php _e('Courses Report', 'intersoccer-player-management'); ?></h2>
<?php
// Fetch all Event Tickets events
$events = tribe_get_events(array(
    'posts_per_page' => -1,
    'post_status' => 'publish',
));

if (empty($events)) {
    echo '<p>' . __('No events found. Please create an event or generate events from variable products.', 'intersoccer-player-management') . '</p>';
} else {
    ?>
    <a href="?page=intersoccer-players&tab=courses-report&export-courses-report=pdf" class="button"><?php _e('Export as PDF', 'intersoccer-player-management'); ?></a>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Course Name', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Player Name', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Age', 'intersoccer-player-management'); ?></th>
                <th><?php _e('Medical Conditions', 'intersoccer-player-management'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($events as $event) {
                $event_id = $event->ID;
                $attendees = tribe_tickets_get_attendees($event_id);
                $course_players = array();

                foreach ($attendees as $attendee) {
                    $order_id = $attendee['order_id'];
                    $player_name = get_post_meta($attendee['ID'], 'player_name', true);
                    if (!$player_name) {
                        continue;
                    }

                    $order = wc_get_order($order_id);
                    if (!$order) {
                        continue;
                    }

                    $user_id = $order->get_user_id();
                    if (!$user_id) {
                        continue;
                    }

                    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
                    $player_data = null;
                    foreach ($players as $player) {
                        if ($player['name'] === $player_name) {
                            $player_data = $player;
                            break;
                        }
                    }

                    if ($player_data) {
                        $dob = $player_data['dob'];
                        $age = $dob ? date_diff(date_create($dob), date_create('today'))->y : 'N/A';
                        $course_players[] = array(
                            'course_name' => $event->post_title,
                            'player_name' => $player_name,
                            'age' => $age,
                            'medical_conditions' => $player_data['medical_conditions'],
                        );
                    }
                }

                foreach ($course_players as $player) {
                    ?>
                    <tr>
                        <td><?php echo esc_html($player['course_name']); ?></td>
                        <td><?php echo esc_html($player['player_name']); ?></td>
                        <td><?php echo esc_html($player['age']); ?></td>
                        <td><?php echo esc_html($player['medical_conditions']); ?></td>
                    </tr>
                    <?php
                }
            }
            ?>
        </tbody>
    </table>
    <?php
}
?>

