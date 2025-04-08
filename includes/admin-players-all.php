<?php
/**
 * Admin Feature: All Players Tab with Zoho CRM Import
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Handle import from Zoho CRM
if (isset($_POST['import_players']) && !empty($_POST['import_players_nonce']) && wp_verify_nonce($_POST['import_players_nonce'], 'import_players_action')) {
    $module = sanitize_text_field($_POST['zoho_module'] ?? 'Contacts');
    $field_mappings = array(
        'First_Name' => 'first_name',
        'Last_Name' => 'last_name',
        'Date_of_Birth' => 'dob',
        'Medical_Conditions' => 'medical_conditions',
        'Consent_URL' => 'consent_url',
    );

    // Fetch data from Zoho CRM using WP Swings Zoho CRM Connect
    if (function_exists('wpswings_zoho_crm_get_records')) {
        $records = wpswings_zoho_crm_get_records($module);
        if (is_array($records) && !empty($records)) {
            foreach ($records as $record) {
                // Map Zoho fields to player data
                $player_data = array(
                    'name' => trim(($record['First_Name'] ?? '') . ' ' . ($record['Last_Name'] ?? '')),
                    'dob' => $record['Date_of_Birth'] ?? '',
                    'medical_conditions' => $record['Medical_Conditions'] ?? 'No known medical conditions',
                    'consent_url' => $record['Consent_URL'] ?? '',
                );

                // Skip if essential fields are missing
                if (empty($player_data['name']) || empty($player_data['dob'])) {
                    continue;
                }

                // Find user by email (assuming Zoho Contact has an Email field)
                $email = $record['Email'] ?? '';
                if (empty($email)) {
                    continue;
                }

                $user = get_user_by('email', $email);
                if (!$user) {
                    // Optionally create a new user if not found
                    $username = sanitize_user(str_replace(' ', '_', $player_data['name']));
                    $password = wp_generate_password();
                    $user_id = wp_create_user($username, $password, $email);
                    if (is_wp_error($user_id)) {
                        wc_add_notice(sprintf(__('Failed to create user for %s: %s', 'intersoccer-player-management'), $email, $user_id->get_error_message()), 'error');
                        continue;
                    }
                    $user = get_user_by('id', $user_id);
                }

                // Update user meta with player data
                $existing_players = get_user_meta($user->ID, 'intersoccer_players', true) ?: array();
                $existing_players[] = $player_data;
                update_user_meta($user->ID, 'intersoccer_players', $existing_players);
            }
            wc_add_notice(__('Players imported successfully from Zoho CRM!', 'intersoccer-player-management'), 'success');
        } else {
            wc_add_notice(__('No records found in Zoho CRM or an error occurred.', 'intersoccer-player-management'), 'error');
        }
    } else {
        wc_add_notice(__('WP Swings Zoho CRM Connect plugin is required for importing players.', 'intersoccer-player-management'), 'error');
    }
}
?>

<!-- Import Form -->
<h2><?php _e('Import Players from Zoho CRM', 'intersoccer-player-management'); ?></h2>
<form method="post" action="">
    <?php wp_nonce_field('import_players_action', 'import_players_nonce'); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="zoho_module"><?php _e('Zoho Module', 'intersoccer-player-management'); ?></label></th>
            <td>
                <select name="zoho_module" id="zoho_module">
                    <option value="Contacts"><?php _e('Contacts', 'intersoccer-player-management'); ?></option>
                    <option value="Leads"><?php _e('Leads', 'intersoccer-player-management'); ?></option>
                </select>
                <p class="description"><?php _e('Select the Zoho CRM module to import players from.', 'intersoccer-player-management'); ?></p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" name="import_players" class="button button-primary" value="<?php _e('Import Players', 'intersoccer-player-management'); ?>" />
    </p>
</form>

<!-- Players Table -->
<h2><?php _e('All Players', 'intersoccer-player-management'); ?></h2>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('User', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Player Name', 'intersoccer-player-management'); ?></th>
            <th><?php _e('DOB', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Medical Conditions', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Consent File', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Order ID', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Product', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Categories', 'intersoccer-player-management'); ?></th>
            <th><?php _e('Variations & Attributes', 'intersoccer-player-management'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        // Get all users
        $users = get_users();
        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: array();
            if (empty($players)) {
                continue;
            }

            // Get orders for this user
            $orders = wc_get_orders(array(
                'customer_id' => $user->ID,
                'status' => array('wc-completed', 'wc-processing'),
            ));

            foreach ($players as $player) {
                $player_row = array(
                    'user' => esc_html($user->display_name . ' (' . $user->user_email . ')'),
                    'player_name' => esc_html($player['name']),
                    'dob' => esc_html($player['dob']),
                    'medical_conditions' => esc_html($player['medical_conditions']),
                    'consent_file' => !empty($player['consent_url']) ? '<a href="' . esc_url($player['consent_url']) . '" target="_blank">' . __('View Consent', 'intersoccer-player-management') . '</a>' : __('None', 'intersoccer-player-management'),
                    'order_id' => __('N/A', 'intersoccer-player-management'),
                    'product' => __('N/A', 'intersoccer-player-management'),
                    'categories' => __('N/A', 'intersoccer-player-management'),
                    'variations_attributes' => __('N/A', 'intersoccer-player-management'),
                );

                // Find orders associated with this player
                $found_in_order = false;
                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        $order_player = $item->get_meta('Player');
                        if ($order_player === $player['name']) {
                            $found_in_order = true;
                            $product = $item->get_product();
                            $product_id = $item->get_product_id();
                            $variation_id = $item->get_variation_id();

                            // Product categories
                            $categories = get_the_terms($product_id, 'product_cat');
                            $category_names = $categories ? wp_list_pluck($categories, 'name') : array();
                            $category_list = !empty($category_names) ? implode(', ', $category_names) : __('N/A', 'intersoccer-player-management');

                            // Product variations and attributes
                            $variation_attributes = array();
                            if ($variation_id) {
                                $variation = wc_get_product($variation_id);
                                $variation_attributes = $variation->get_attributes();
                            }

                            // Specified attributes
                            $attributes_to_display = array(
                                'pa_event-terms' => __('Event Terms', 'intersoccer-player-management'),
                                'pa_intersoccer-venues' => __('InterSoccer Venues', 'intersoccer-player-management'),
                                'pa_summer-camp-terms-2025' => __('Summer Camp Terms - 2025', 'intersoccer-player-management'),
                                'pa_hot-lunch' => __('Hot Lunch', 'intersoccer-player-management'),
                                'pa_camp-times' => __('Camp Times', 'intersoccer-player-management'),
                                'pa_camp-holidays' => __('Camp Holidays', 'intersoccer-player-management'),
                                'pa_booking-type' => __('Booking Type', 'intersoccer-player-management'),
                                'pa_age-open' => __('Age Open', 'intersoccer-player-management'),
                            );

                            $attribute_list = array();
                            foreach ($attributes_to_display as $attribute_key => $attribute_label) {
                                $term = '';
                                if ($variation_id && isset($variation_attributes[$attribute_key])) {
                                    $term = $variation_attributes[$attribute_key];
                                } else {
                                    $terms = wc_get_product_terms($product_id, $attribute_key, array('fields' => 'names'));
                                    $term = !empty($terms) ? $terms[0] : '';
                                }
                                if ($term) {
                                    $attribute_list[] = "$attribute_label: $term";
                                }
                            }
                            $attributes_display = !empty($attribute_list) ? implode('<br>', $attribute_list) : __('N/A', 'intersoccer-player-management');

                            // Output row with order details
                            $player_row['order_id'] = $order->get_id();
                            $player_row['product'] = esc_html($product->get_name());
                            $player_row['categories'] = esc_html($category_list);
                            $player_row['variations_attributes'] = $attributes_display;

                            echo '<tr>';
                            echo '<td>' . $player_row['user'] . '</td>';
                            echo '<td>' . $player_row['player_name'] . '</td>';
                            echo '<td>' . $player_row['dob'] . '</td>';
                            echo '<td>' . $player_row['medical_conditions'] . '</td>';
                            echo '<td>' . $player_row['consent_file'] . '</td>';
                            echo '<td>' . $player_row['order_id'] . '</td>';
                            echo '<td>' . $player_row['product'] . '</td>';
                            echo '<td>' . $player_row['categories'] . '</td>';
                            echo '<td>' . $player_row['variations_attributes'] . '</td>';
                            echo '</tr>';
                        }
                    }
                }

                // If player is not associated with any order, display a row without order details
                if (!$found_in_order) {
                    echo '<tr>';
                    echo '<td>' . $player_row['user'] . '</td>';
                    echo '<td>' . $player_row['player_name'] . '</td>';
                    echo '<td>' . $player_row['dob'] . '</td>';
                    echo '<td>' . $player_row['medical_conditions'] . '</td>';
                    echo '<td>' . $player_row['consent_file'] . '</td>';
                    echo '<td>' . $player_row['order_id'] . '</td>';
                    echo '<td>' . $player_row['product'] . '</td>';
                    echo '<td>' . $player_row['categories'] . '</td>';
                    echo '<td>' . $player_row['variations_attributes'] . '</td>';
                    echo '</tr>';
                }
            }
        }
        ?>
    </tbody>
</table>

<?php
// Mock function to simulate WP Swings Zoho CRM Connect API (replace with actual implementation)
if (!function_exists('wpswings_zoho_crm_get_records')) {
    function wpswings_zoho_crm_get_records($module) {
        return array(
            array(
                'First_Name' => 'Jessica',
                'Last_Name' => 'Smith',
                'Email' => 'jessica.smith@example.com',
                'Date_of_Birth' => '2018-09-06',
                'Medical_Conditions' => 'Asthma',
                'Consent_URL' => 'https://example.com/consent.pdf',
            ),
            array(
                'First_Name' => 'Alex',
                'Last_Name' => 'Johnson',
                'Email' => 'alex.johnson@example.com',
                'Date_of_Birth' => '2015-05-20',
                'Medical_Conditions' => 'No known medical conditions',
                'Consent_URL' => '',
            ),
        );
    }
}
?>

