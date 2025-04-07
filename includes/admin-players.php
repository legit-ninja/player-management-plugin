<?php
/**
 * Admin Feature: Manage Players, Products, Orders, Import from Zoho CRM, Courses Report with Dynamic Taxonomies, Sync Products to Events, and Generate Events
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Add admin menu page
add_action('admin_menu', 'intersoccer_admin_menu');
function intersoccer_admin_menu() {
    add_menu_page(
        __('Player Management', 'intersoccer-player-management'),
        __('Players & Orders', 'intersoccer-player-management'),
        'manage_options',
        'intersoccer-players',
        'intersoccer_players_admin_page',
        'dashicons-groups',
        56
    );
}

// Render the admin page
function intersoccer_players_admin_page() {
    // Determine the current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'players';

    // Handle export requests
    if (isset($_GET['export']) && $current_tab === 'courses-report') {
        $export_type = sanitize_text_field($_GET['export']);
        export_courses_report($export_type);
        exit;
    }

    // Handle sync products to events
    if (isset($_POST['sync_products_to_events']) && !empty($_POST['sync_products_nonce']) && wp_verify_nonce($_POST['sync_products_nonce'], 'sync_products_to_events_action')) {
        $product_event_mappings = isset($_POST['product_event_mapping']) ? (array) $_POST['product_event_mapping'] : array();
        foreach ($product_event_mappings as $product_id => $event_id) {
            $event_id = absint($event_id);
            if (strpos($product_id, 'variation_') === 0) {
                // Handle variations
                $variation_id = absint(str_replace('variation_', '', $product_id));
                update_post_meta($variation_id, '_tribe_event_id', $event_id);
            } else {
                // Handle simple products
                $product_id = absint($product_id);
                update_post_meta($product_id, '_tribe_event_id', $event_id);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Product to event mappings updated successfully!', 'intersoccer-player-management') . '</p></div>';
    }

    // Handle generate events from variable products
    if (isset($_POST['generate_events']) && !empty($_POST['generate_events_nonce']) && wp_verify_nonce($_POST['generate_events_nonce'], 'generate_events_action')) {
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish',
            'type' => array('variable'),
        ));

        foreach ($products as $product) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_obj = wc_get_product($variation_id);
                $event_id = get_post_meta($variation_id, '_tribe_event_id', true);

                // Skip if already linked to an event
                if ($event_id && tribe_is_event($event_id)) {
                    continue;
                }

                // Get variation attributes to build event details
                $variation_attributes = $variation_obj->get_variation_attributes();
                $event_title = $product->get_name();
                $event_description = $product->get_description() ?: $product->get_short_description() ?: '';
                $event_date = '';
                $event_location = '';

                foreach ($variation_attributes as $attribute_key => $attribute_value) {
                    $attribute_name = wc_attribute_label(str_replace('attribute_', '', $attribute_key));
                    if (stripos($attribute_name, 'date') !== false) {
                        $event_date = $attribute_value;
                    } elseif (stripos($attribute_name, 'location') !== false || stripos($attribute_name, 'venue') !== false) {
                        $event_location = $attribute_value;
                    }
                    $event_title .= ' - ' . $attribute_name . ': ' . $attribute_value;
                }

                // Create the event
                $event_args = array(
                    'post_title' => $event_title,
                    'post_content' => $event_description,
                    'post_type' => 'tribe_events',
                    'post_status' => 'publish',
                    'meta_input' => array(
                        '_EventStartDate' => $event_date ? date('Y-m-d H:i:s', strtotime($event_date)) : date('Y-m-d H:i:s'), // Default to today if no date
                        '_EventEndDate' => $event_date ? date('Y-m-d H:i:s', strtotime($event_date . ' +1 hour')) : date('Y-m-d H:i:s', strtotime('+1 hour')),
                        '_EventVenue' => $event_location,
                    ),
                );

                $new_event_id = wp_insert_post($event_args);
                if (is_wp_error($new_event_id)) {
                    error_log('Failed to create Event Tickets event: ' . $new_event_id->get_error_message());
                    continue;
                }

                // Add an RSVP ticket to the event
                $ticket_args = array(
                    'ticket_name' => __('General Admission', 'intersoccer-player-management'),
                    'ticket_description' => __('General admission ticket for the event.', 'intersoccer-player-management'),
                    'ticket_price' => 0, // Free ticket for the free version of Event Tickets
                    'ticket_capacity' => -1, // Unlimited capacity
                    'ticket_start_date' => date('Y-m-d'),
                    'ticket_end_date' => $event_date ? date('Y-m-d', strtotime($event_date)) : date('Y-m-d', strtotime('+1 month')),
                );

                $ticket_id = tribe_tickets()->create_ticket($new_event_id, 'rsvp', $ticket_args);
                if (is_wp_error($ticket_id)) {
                    error_log('Failed to create RSVP ticket for event ID ' . $new_event_id . ': ' . $ticket_id->get_error_message());
                }

                // Link the event to the variation
                update_post_meta($variation_id, '_tribe_event_id', $new_event_id);
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . __('Events generated successfully from variable products!', 'intersoccer-player-management') . '</p></div>';
    }

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
    <div class="wrap">
        <h1><?php _e('Player Management', 'intersoccer-player-management'); ?></h1>

        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=intersoccer-players&tab=players" class="nav-tab <?php echo $current_tab === 'players' ? 'nav-tab-active' : ''; ?>"><?php _e('All Players', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=courses-report" class="nav-tab <?php echo $current_tab === 'courses-report' ? 'nav-tab-active' : ''; ?>"><?php _e('Courses Report', 'intersoccer-player-management'); ?></a>
            <a href="?page=intersoccer-players&tab=sync-products" class="nav-tab <?php echo $current_tab === 'sync-products' ? 'nav-tab-active' : ''; ?>"><?php _e('Sync Products to Events', 'intersoccer-player-management'); ?></a>
        </h2>

        <?php if ($current_tab === 'players'): ?>
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
        <?php elseif ($current_tab === 'courses-report'): ?>
            <!-- Courses Report -->
            <h2><?php _e('Courses Report', 'intersoccer-player-management'); ?></h2>
            <p>
                <a href="?page=intersoccer-players&tab=courses-report&export=csv" class="button"><?php _e('Export to CSV', 'intersoccer-player-management'); ?></a>
                <a href="?page=intersoccer-players&tab=courses-report&export=excel" class="button"><?php _e('Export to Excel', 'intersoccer-player-management'); ?></a>
                <a href="?page=intersoccer-players&tab=courses-report&export=pdf" class="button"><?php _e('Export to PDF', 'intersoccer-player-management'); ?></a>
            </p>
            <?php
            // Fetch all locations from the pa_intersoccer-venues taxonomy
            $locations_terms = get_terms(array(
                'taxonomy' => 'pa_intersoccer-venues',
                'hide_empty' => false,
            ));

            if (is_wp_error($locations_terms) || empty($locations_terms)) {
                echo '<p>' . __('No locations found in the pa_intersoccer-venues taxonomy.', 'intersoccer-player-management') . '</p>';
                return;
            }

            // Initialize the locations array dynamically
            $locations = array();
            foreach ($locations_terms as $term) {
                $location_name = strtoupper($term->name);
                $locations[$location_name] = array();
            }

            // Fetch all course names from product names and taxonomies
            $products = wc_get_products(array(
                'limit' => -1,
                'status' => 'publish',
            ));

            $course_names = array();
            foreach ($products as $product) {
                $product_id = $product->get_id();
                $product_type = $product->get_type();

                if ($product_type === 'variable') {
                    $variations = $product->get_available_variations();
                    foreach ($variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $variation_obj = wc_get_product($variation_id);
                        $variation_attributes = $variation_obj->get_variation_attributes();

                        // Get location
                        $location = isset($variation_attributes['pa_intersoccer-venues']) ? strtoupper($variation_attributes['pa_intersoccer-venues']) : '';
                        if (!$location || !isset($locations[$location])) {
                            continue;
                        }

                        // Build course name using product name and relevant taxonomies
                        $course_name = $product->get_name();
                        $taxonomies_to_check = array('pa_day-of-week', 'pa_event-terms', 'pa_summer-camp-terms-2025', 'pa_camp-times', 'pa_camp-holidays');
                        foreach ($taxonomies_to_check as $taxonomy) {
                            if (isset($variation_attributes[$taxonomy]) && !empty($variation_attributes[$taxonomy])) {
                                $course_name .= ' ' . ucfirst(str_replace('attribute_', '', $taxonomy)) . ': ' . $variation_attributes[$taxonomy];
                            }
                        }

                        if (!isset($locations[$location][$course_name])) {
                            $locations[$location][$course_name] = array(
                                'bo' => 0,
                                'pitch_side' => 0,
                                'buy_club' => 0,
                                'total' => 0,
                                'final_2023' => 0,
                                'girls_free' => 0,
                            );
                        }
                    }
                }
            }

            // Fetch orders for 2024 and 2023
            $orders_2024 = wc_get_orders(array(
                'date_created' => '>=2024-01-01 00:00:00',
                'date_created' => '<=2024-12-31 23:59:59',
                'status' => array('wc-completed', 'wc-processing'),
            ));

            $orders_2023 = wc_get_orders(array(
                'date_created' => '>=2023-01-01 00:00:00',
                'date_created' => '<=2023-12-31 23:59:59',
                'status' => array('wc-completed', 'wc-processing'),
            ));

            $total_buy_club = 0;
            $total_girls_free = 0;

            // Process 2024 orders
            foreach ($orders_2024 as $order) {
                $is_buy_club = false;
                $is_girls_free = false;

                // Check for Buy Club and Girls Free codes
                $coupons = $order->get_coupon_codes();
                if (in_array('BUYCLUB', array_map('strtoupper', $coupons))) {
                    $is_buy_club = true;
                    $total_buy_club++;
                }
                if (in_array('GIRLSFREE24', array_map('strtoupper', $coupons))) {
                    $is_girls_free = true;
                    $total_girls_free++;
                }

                foreach ($order->get_items() as $item) {
                    $player_name = $item->get_meta('Player');
                    if (!$player_name) {
                        continue;
                    }

                    $product = $item->get_product();
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();

                    // Get location (InterSoccer Venues attribute)
                    $location = '';
                    if ($variation_id) {
                        $variation = wc_get_product($variation_id);
                        $variation_attributes = $variation->get_attributes();
                        $location = isset($variation_attributes['pa_intersoccer-venues']) ? strtoupper($variation_attributes['pa_intersoccer-venues']) : '';
                    } else {
                        $terms = wc_get_product_terms($product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
                        $location = !empty($terms) ? strtoupper($terms[0]) : '';
                    }

                    if (!$location || !isset($locations[$location])) {
                        continue;
                    }

                    // Build course name
                    $course_name = $product->get_name();
                    $taxonomies_to_check = array('pa_day-of-week', 'pa_event-terms', 'pa_summer-camp-terms-2025', 'pa_camp-times', 'pa_camp-holidays');
                    if ($variation_id) {
                        $variation_attributes = $variation->get_attributes();
                        foreach ($taxonomies_to_check as $taxonomy) {
                            if (isset($variation_attributes[$taxonomy]) && !empty($variation_attributes[$taxonomy])) {
                                $course_name .= ' ' . ucfirst(str_replace('attribute_', '', $taxonomy)) . ': ' . $variation_attributes[$taxonomy];
                            }
                        }
                    } else {
                        foreach ($taxonomies_to_check as $taxonomy) {
                            $terms = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'names'));
                            if (!empty($terms)) {
                                $course_name .= ' ' . ucfirst(str_replace('attribute_', '', $taxonomy)) . ': ' . $terms[0];
                            }
                        }
                    }

                    if (!isset($locations[$location][$course_name])) {
                        $locations[$location][$course_name] = array(
                            'bo' => 0,
                            'pitch_side' => 0,
                            'buy_club' => 0,
                            'total' => 0,
                            'final_2023' => 0,
                            'girls_free' => 0,
                        );
                    }

                    // Update metrics
                    $locations[$location][$course_name]['total']++;
                    if ($is_buy_club) {
                        $locations[$location][$course_name]['buy_club']++;
                    }
                    if ($is_girls_free) {
                        $locations[$location][$course_name]['girls_free']++;
                    }

                    // Determine booking type (BO, Pitch Side, Buy Club)
                    $booking_type = $item->get_meta('Booking Type') ?? 'BO';
                    if ($booking_type === 'Pitch Side') {
                        $locations[$location][$course_name]['pitch_side']++;
                    } else {
                        $locations[$location][$course_name]['bo']++;
                    }
                }
            }

            // Process 2023 orders for "Final" column
            foreach ($orders_2023 as $order) {
                foreach ($order->get_items() as $item) {
                    $player_name = $item->get_meta('Player');
                    if (!$player_name) {
                        continue;
                    }

                    $product = $item->get_product();
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();

                    // Get location
                    $location = '';
                    if ($variation_id) {
                        $variation = wc_get_product($variation_id);
                        $variation_attributes = $variation->get_attributes();
                        $location = isset($variation_attributes['pa_intersoccer-venues']) ? strtoupper($variation_attributes['pa_intersoccer-venues']) : '';
                    } else {
                        $terms = wc_get_product_terms($product_id, 'pa_intersoccer-venues', array('fields' => 'names'));
                        $location = !empty($terms) ? strtoupper($terms[0]) : '';
                    }

                    if (!$location || !isset($locations[$location])) {
                        continue;
                    }

                    // Build course name
                    $course_name = $product->get_name();
                    $taxonomies_to_check = array('pa_day-of-week', 'pa_event-terms', 'pa_summer-camp-terms-2025', 'pa_camp-times', 'pa_camp-holidays');
                    if ($variation_id) {
                        $variation_attributes = $variation->get_attributes();
                        foreach ($taxonomies_to_check as $taxonomy) {
                            if (isset($variation_attributes[$taxonomy]) && !empty($variation_attributes[$taxonomy])) {
                                $course_name .= ' ' . ucfirst(str_replace('attribute_', '', $taxonomy)) . ': ' . $variation_attributes[$taxonomy];
                            }
                        }
                    } else {
                        foreach ($taxonomies_to_check as $taxonomy) {
                            $terms = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'names'));
                            if (!empty($terms)) {
                                $course_name .= ' ' . ucfirst(str_replace('attribute_', '', $taxonomy)) . ': ' . $terms[0];
                            }
                        }
                    }

                    if (!isset($locations[$location][$course_name])) {
                        $locations[$location][$course_name] = array(
                            'bo' => 0,
                            'pitch_side' => 0,
                            'buy_club' => 0,
                            'total' => 0,
                            'final_2023' => 0,
                            'girls_free' => 0,
                        );
                    }

                    // Update Final 2023 metric
                    $locations[$location][$course_name]['final_2023']++;
                }
            }

            // Calculate percentages
            foreach ($locations as &$location) {
                foreach ($location as &$course) {
                    $total_2024 = $course['total'];
                    $total_2023 = $course['final_2023'];
                    if ($total_2023 > 0) {
                        $course['percentage'] = round(($total_2024 / $total_2023) * 100);
                    } else {
                        $course['percentage'] = $total_2024 > 0 ? 100 : 0;
                    }
                }
            }
            unset($location, $course);

            // Calculate totals
            $grand_total = array(
                'bo' => 0,
                'pitch_side' => 0,
                'buy_club' => 0,
                'total' => 0,
                'final_2023' => 0,
                'percentage' => 0,
                'girls_free' => 0,
            );

            foreach ($locations as &$location) {
                $location_total = array(
                    'bo' => 0,
                    'pitch_side' => 0,
                    'buy_club' => 0,
                    'total' => 0,
                    'final_2023' => 0,
                    'percentage' => 0,
                    'girls_free' => 0,
                );

                foreach ($location as $course_name => $data) {
                    $location_total['bo'] += $data['bo'];
                    $location_total['pitch_side'] += $data['pitch_side'];
                    $location_total['buy_club'] += $data['buy_club'];
                    $location_total['total'] += $data['total'];
                    $location_total['final_2023'] += $data['final_2023'];
                    $location_total['girls_free'] += $data['girls_free'];
                }

                if ($location_total['final_2023'] > 0) {
                    $location_total['percentage'] = round(($location_total['total'] / $location_total['final_2023']) * 100);
                } else {
                    $location_total['percentage'] = $location_total['total'] > 0 ? 100 : 0;
                }

                $location['total'] = $location_total;

                $grand_total['bo'] += $location_total['bo'];
                $grand_total['pitch_side'] += $location_total['pitch_side'];
                $grand_total['buy_club'] += $location_total['buy_club'];
                $grand_total['total'] += $location_total['total'];
                $grand_total['final_2023'] += $location_total['final_2023'];
                $grand_total['girls_free'] += $location_total['girls_free'];
            }

            if ($grand_total['final_2023'] > 0) {
                $grand_total['percentage'] = round(($grand_total['total'] / $grand_total['final_2023']) * 100);
            } else {
                $grand_total['percentage'] = $grand_total['total'] > 0 ? 100 : 0;
            }

            // Store data for export
            $export_data = array(
                'locations' => $locations,
                'grand_total' => $grand_total,
                'total_buy_club' => $total_buy_club,
                'total_girls_free' => $total_girls_free,
            );
            set_transient('intersoccer_courses_report_data', $export_data, HOUR_IN_SECONDS);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name of Course / Day', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('BO', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Pitch Side', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Buy Club', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Total', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Final 2023', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('%', 'intersoccer-player-management'); ?></th>
                        <th><?php _e('Girls Free (GIRLSFREE24)', 'intersoccer-player-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($locations as $location_name => $courses) {
                        echo '<tr><td colspan="8"><strong>' . esc_html($location_name) . '</strong></td></tr>';
                        foreach ($courses as $course_name => $data) {
                            if ($course_name === 'total') {
                                continue;
                            }
                            echo '<tr>';
                            echo '<td>' . esc_html($course_name) . '</td>';
                            echo '<td>' . esc_html($data['bo']) . '</td>';
                            echo '<td>' . esc_html($data['pitch_side']) . '</td>';
                            echo '<td>' . esc_html($data['buy_club']) . '</td>';
                            echo '<td>' . esc_html($data['total']) . '</td>';
                            echo '<td>' . esc_html($data['final_2023']) . '</td>';
                            echo '<td>' . esc_html($data['percentage']) . '%</td>';
                            echo '<td>' . esc_html($data['girls_free']) . '</td>';
                            echo '</tr>';
                        }
                        echo '<tr>';
                        echo '<td><strong>' . __('TOTAL:', 'intersoccer-player-management') . '</strong></td>';
                        echo '<td>' . esc_html($courses['total']['bo']) . '</td>';
                        echo '<td>' . esc_html($courses['total']['pitch_side']) . '</td>';
                        echo '<td>' . esc_html($courses['total']['buy_club']) . '</td>';
                        echo '<td>' . esc_html($courses['total']['total']) . '</td>';
                        echo '<td>' . esc_html($courses['total']['final_2023']) . '</td>';
                        echo '<td>' . esc_html($courses['total']['percentage']) . '%</td>';
                        echo '<td>' . esc_html($courses['total']['girls_free']) . '</td>';
                        echo '</tr>';
                    }

                    // Grand Total
                    echo '<tr>';
                    echo '<td><strong>' . __('TOTAL:', 'intersoccer-player-management') . '</strong></td>';
                    echo '<td>' . esc_html($grand_total['bo']) . '</td>';
                    echo '<td>' . esc_html($grand_total['pitch_side']) . '</td>';
                    echo '<td>' . esc_html($grand_total['buy_club']) . '</td>';
                    echo '<td>' . esc_html($grand_total['total']) . '</td>';
                    echo '<td>' . esc_html($grand_total['final_2023']) . '</td>';
                    echo '<td>' . esc_html($grand_total['percentage']) . '%</td>';
                    echo '<td>' . esc_html($grand_total['girls_free']) . '</td>';
                    echo '</tr>';

                    // Additional Metrics
                    echo '<tr>';
                    echo '<td colspan="7"><strong>' . __('BuyClub Numbers', 'intersoccer-player-management') . '</strong></td>';
                    echo '<td>' . esc_html($total_buy_club) . '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td colspan="7"><strong>' . __('Girls booked with codes GIRLSFREE24', 'intersoccer-player-management') . '</strong></td>';
                    echo '<td>' . esc_html($total_girls_free) . '</td>';
                    echo '</tr>';
                    ?>
                </tbody>
            </table>
        <?php elseif ($current_tab === 'sync-products'): ?>
            <!-- Sync Products to Events -->
            <h2><?php _e('Sync Products to Events', 'intersoccer-player-management'); ?></h2>
            <?php
            // Fetch all WooCommerce products
            $products = wc_get_products(array(
                'limit' => -1,
                'status' => 'publish',
            ));

            // Fetch all Event Tickets events
            $events = tribe_get_events(array(
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ));

            if (empty($products)) {
                echo '<p>' . __('No WooCommerce products found.', 'intersoccer-player-management') . '</p>';
            } else {
                ?>
                <form method="post" action="">
                    <?php wp_nonce_field('generate_events_action', 'generate_events_nonce'); ?>
                    <p class="submit">
                        <input type="submit" name="generate_events" class="button button-primary" value="<?php _e('Generate Events from Variable Products', 'intersoccer-player-management'); ?>" />
                    </p>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field('sync_products_to_events_action', 'sync_products_nonce'); ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Product Name', 'intersoccer-player-management'); ?></th>
                                <th><?php _e('Type', 'intersoccer-player-management'); ?></th>
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
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($product->get_name()); ?></td>
                                        <td><?php _e('Simple', 'intersoccer-player-management'); ?></td>
                                        <td>
                                            <?php if (empty($events)): ?>
                                                <?php _e('No events available. Please create an event or generate events from variable products.', 'intersoccer-player-management'); ?>
                                            <?php else: ?>
                                                <select name="product_event_mapping[<?php echo esc_attr($product_id); ?>]">
                                                    <option value=""><?php _e('None', 'intersoccer-player-management'); ?></option>
                                                    <?php foreach ($events as $event): ?>
                                                        <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id, $event->ID); ?>>
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
                                        $variation_attributes = $variation_obj->get_variation_attributes();
                                        $variation_label = implode(', ', array_map(function($key, $value) {
                                            return ucfirst(str_replace('attribute_', '', $key)) . ': ' . $value;
                                        }, array_keys($variation_attributes), $variation_attributes));
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($product->get_name() . ' - ' . $variation_label); ?></td>
                                            <td><?php _e('Variation', 'intersoccer-player-management'); ?></td>
                                            <td>
                                                <?php if (empty($events)): ?>
                                                    <?php _e('No events available. Please generate events from variable products.', 'intersoccer-player-management'); ?>
                                                <?php else: ?>
                                                    <select name="product_event_mapping[variation_<?php echo esc_attr($variation_id); ?>]">
                                                        <option value=""><?php _e('None', 'intersoccer-player-management'); ?></option>
                                                        <?php foreach ($events as $event): ?>
                                                            <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id, $event->ID); ?>>
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
                        <input type="submit" name="sync_products_to_events" class="button button-primary" value="<?php _e('Save Sync', 'intersoccer-player-management'); ?>" />
                    </p>
                </form>
                <?php
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
}

// Export the courses report
function export_courses_report($export_type) {
    $export_data = get_transient('intersoccer_courses_report_data');
    if (!$export_data) {
        wp_die(__('No data available for export.', 'intersoccer-player-management'));
    }

    $locations = $export_data['locations'];
    $grand_total = $export_data['grand_total'];
    $total_buy_club = $export_data['total_buy_club'];
    $total_girls_free = $export_data['total_girls_free'];

    if ($export_type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=courses-report-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array(
            __('Name of Course / Day', 'intersoccer-player-management'),
            __('BO', 'intersoccer-player-management'),
            __('Pitch Side', 'intersoccer-player-management'),
            __('Buy Club', 'intersoccer-player-management'),
            __('Total', 'intersoccer-player-management'),
            __('Final 2023', 'intersoccer-player-management'),
            __('%', 'intersoccer-player-management'),
            __('Girls Free (GIRLSFREE24)', 'intersoccer-player-management'),
        ));

        foreach ($locations as $location_name => $courses) {
            fputcsv($output, array($location_name));
            foreach ($courses as $course_name => $data) {
                if ($course_name === 'total') {
                    continue;
                }
                fputcsv($output, array(
                    $course_name,
                    $data['bo'],
                    $data['pitch_side'],
                    $data['buy_club'],
                    $data['total'],
                    $data['final_2023'],
                    $data['percentage'] . '%',
                    $data['girls_free'],
                ));
            }
            fputcsv($output, array(
                __('TOTAL:', 'intersoccer-player-management'),
                $courses['total']['bo'],
                $courses['total']['pitch_side'],
                $courses['total']['buy_club'],
                $courses['total']['total'],
                $courses['total']['final_2023'],
                $courses['total']['percentage'] . '%',
                $courses['total']['girls_free'],
            ));
        }

        fputcsv($output, array(
            __('TOTAL:', 'intersoccer-player-management'),
            $grand_total['bo'],
            $grand_total['pitch_side'],
            $grand_total['buy_club'],
            $grand_total['total'],
            $grand_total['final_2023'],
            $grand_total['percentage'] . '%',
            $grand_total['girls_free'],
        ));

        fputcsv($output, array(__('BuyClub Numbers', 'intersoccer-player-management'), '', '', '', '', '', '', $total_buy_club));
        fputcsv($output, array(__('Girls booked with codes GIRLSFREE24', 'intersoccer-player-management'), '', '', '', '', '', '', $total_girls_free));

        fclose($output);
    } elseif ($export_type === 'excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Courses Report');

        // Headers
        $sheet->setCellValue('A1', __('Name of Course / Day', 'intersoccer-player-management'));
        $sheet->setCellValue('B1', __('BO', 'intersoccer-player-management'));
        $sheet->setCellValue('C1', __('Pitch Side', 'intersoccer-player-management'));
        $sheet->setCellValue('D1', __('Buy Club', 'intersoccer-player-management'));
        $sheet->setCellValue('E1', __('Total', 'intersoccer-player-management'));
        $sheet->setCellValue('F1', __('Final 2023', 'intersoccer-player-management'));
        $sheet->setCellValue('G1', __('%', 'intersoccer-player-management'));
        $sheet->setCellValue('H1', __('Girls Free (GIRLSFREE24)', 'intersoccer-player-management'));

        $row = 2;
        foreach ($locations as $location_name => $courses) {
            $sheet->setCellValue('A' . $row, $location_name);
            $row++;
            foreach ($courses as $course_name => $data) {
                if ($course_name === 'total') {
                    continue;
                }
                $sheet->setCellValue('A' . $row, $course_name);
                $sheet->setCellValue('B' . $row, $data['bo']);
                $sheet->setCellValue('C' . $row, $data['pitch_side']);
                $sheet->setCellValue('D' . $row, $data['buy_club']);
                $sheet->setCellValue('E' . $row, $data['total']);
                $sheet->setCellValue('F' . $row, $data['final_2023']);
                $sheet->setCellValue('G' . $row, $data['percentage'] . '%');
                $sheet->setCellValue('H' . $row, $data['girls_free']);
                $row++;
            }
            $sheet->setCellValue('A' . $row, __('TOTAL:', 'intersoccer-player-management'));
            $sheet->setCellValue('B' . $row, $courses['total']['bo']);
            $sheet->setCellValue('C' . $row, $courses['total']['pitch_side']);
            $sheet->setCellValue('D' . $row, $courses['total']['buy_club']);
            $sheet->setCellValue('E' . $row, $courses['total']['total']);
            $sheet->setCellValue('F' . $row, $courses['total']['final_2023']);
            $sheet->setCellValue('G' . $row, $courses['total']['percentage'] . '%');
            $sheet->setCellValue('H' . $row, $courses['total']['girls_free']);
            $row++;
        }

        $sheet->setCellValue('A' . $row, __('TOTAL:', 'intersoccer-player-management'));
        $sheet->setCellValue('B' . $row, $grand_total['bo']);
        $sheet->setCellValue('C' . $row, $grand_total['pitch_side']);
        $sheet->setCellValue('D' . $row, $grand_total['buy_club']);
        $sheet->setCellValue('E' . $row, $grand_total['total']);
        $sheet->setCellValue('F' . $row, $grand_total['final_2023']);
        $sheet->setCellValue('G' . $row, $grand_total['percentage'] . '%');
        $sheet->setCellValue('H' . $row, $grand_total['girls_free']);
        $row++;

        $sheet->setCellValue('A' . $row, __('BuyClub Numbers', 'intersoccer-player-management'));
        $sheet->setCellValue('H' . $row, $total_buy_club);
        $row++;

        $sheet->setCellValue('A' . $row, __('Girls booked with codes GIRLSFREE24', 'intersoccer-player-management'));
        $sheet->setCellValue('H' . $row, $total_girls_free);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=courses-report-' . date('Y-m-d') . '.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    } elseif ($export_type === 'pdf') {
        $dompdf = new Dompdf();
        $html = '<h1>' . __('Courses Report', 'intersoccer-player-management') . '</h1>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Name of Course / Day', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('BO', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('Pitch Side', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('Buy Club', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('Total', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('Final 2023', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('%', 'intersoccer-player-management') . '</th>';
        $html .= '<th>' . __('Girls Free (GIRLSFREE24)', 'intersoccer-player-management') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($locations as $location_name => $courses) {
            $html .= '<tr><td colspan="8"><strong>' . esc_html($location_name) . '</strong></td></tr>';
            foreach ($courses as $course_name => $data) {
                if ($course_name === 'total') {
                    continue;
                }
                $html .= '<tr>';
                $html .= '<td>' . esc_html($course_name) . '</td>';
                $html .= '<td>' . esc_html($data['bo']) . '</td>';
                $html .= '<td>' . esc_html($data['pitch_side']) . '</td>';
                $html .= '<td>' . esc_html($data['buy_club']) . '</td>';
                $html .= '<td>' . esc_html($data['total']) . '</td>';
                $html .= '<td>' . esc_html($data['final_2023']) . '</td>';
                $html .= '<td>' . esc_html($data['percentage']) . '%</td>';
                $html .= '<td>' . esc_html($data['girls_free']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '<tr>';
            $html .= '<td><strong>' . __('TOTAL:', 'intersoccer-player-management') . '</strong></td>';
            $html .= '<td>' . esc_html($courses['total']['bo']) . '</td>';
            $html .= '<td>' . esc_html($courses['total']['pitch_side']) . '</td>';
            $html .= '<td>' . esc_html($courses['total']['buy_club']) . '</td>';
            $html .= '<td>' . esc_html($courses['total']['total']) . '</td>';
            $html .= '<td>' . esc_html($courses['total']['final_2023']) . '</td>';
            $html .= '<td>' . esc_html($courses['total']['percentage']) . '%</td>';
            $html .= '<td>' . esc_html($courses['total']['girls_free']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr>';
        $html .= '<td><strong>' . __('TOTAL:', 'intersoccer-player-management') . '</strong></td>';
        $html .= '<td>' . esc_html($grand_total['bo']) . '</td>';
        $html .= '<td>' . esc_html($grand_total['pitch_side']) . '</td>';
        $html .= '<td>' . esc_html($grand_total['buy_club']) . '</td>';
        $html .= '<td>' . esc_html($grand_total['total']) . '</td>';
        $html .= '<td>' . esc_html($grand_total['final_2023']) . '</td>';
        $html .= '<td>' . esc_html($grand_total['percentage']) . '%</td>';
        $html .= '<td>' . esc_html($grand_total['girls_free']) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="7"><strong>' . __('BuyClub Numbers', 'intersoccer-player-management') . '</strong></td>';
        $html .= '<td>' . esc_html($total_buy_club) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="7"><strong>' . __('Girls booked with codes GIRLSFREE24', 'intersoccer-player-management') . '</strong></td>';
        $html .= '<td>' . esc_html($total_girls_free) . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('courses-report-' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
    }
}

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

