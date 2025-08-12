<?php
/**
 * Plugin Name: InterSoccer Player Management
 * Plugin URI: https://github.com/legit-ninja/player-management-plugin
 * Description: Manages players for InterSoccer events, including registration, metadata storage (e.g., DOB, gender, medical/dietary), and integration with WooCommerce orders for rosters.
 * Version: 1.3.96
 * Author: Jeremy Lee
 * Author URI: https://underdogunlimited.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: intersoccer-player-management
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define background process class only if library is available
if (class_exists('WP_Background_Process')) {
    class Player_Management_Attribute_Process extends WP_Background_Process {
        protected $action = 'player_management_create_attributes';

        protected function task($attribute) {
            $attribute_id = wc_create_attribute([
                'name' => $attribute['label'],
                'slug' => $attribute['slug'],
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);
            if (!is_wp_error($attribute_id)) {
                register_taxonomy($attribute['slug'], 'product', [
                    'label' => $attribute['label'],
                    'hierarchical' => false,
                    'public' => false,
                    'show_ui' => true,
                ]);
                foreach ($attribute['values'] as $slug => $name) {
                    if (!term_exists($slug, $attribute['slug'])) {
                        wp_insert_term($name, $attribute['slug'], ['slug' => $slug]);
                    }
                }
            }
            return false;
        }
    }
}

add_action('init', function () {
    register_post_type('intersoccer_camp_term', [
        'labels' => ['name' => __('Camp Terms', 'player-management')],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'custom-fields'],
    ]);
});

add_action('admin_notices', function () {
    if (!class_exists('WP_Background_Process')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Please install the wp-background-processing library for asynchronous attribute creation in Player Management.', 'player-management') . '</p></div>';
    }
});

/**
 * Database cleanup function to remove users without players
 */
function intersoccer_cleanup_empty_users() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'player-management'));
    }

    $cutoff_days = isset($_POST['cleanup_days']) ? (int)$_POST['cleanup_days'] : 30;
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cutoff_days} days"));
    
    // Get users without players who are older than cutoff date
    $users_query = new WP_User_Query([
        'role__in' => ['customer', 'subscriber'],
        'date_query' => [
            [
                'before' => $cutoff_date,
                'inclusive' => true,
            ],
        ],
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'intersoccer_players',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key' => 'intersoccer_players',
                'value' => '',
                'compare' => '='
            ],
            [
                'key' => 'intersoccer_players',
                'value' => 'a:0:{}', // Empty serialized array
                'compare' => '='
            ]
        ],
        'fields' => 'all_with_meta'
    ]);
    
    $users_to_delete = $users_query->get_results();
    $deletion_log = [];
    $deleted_count = 0;
    $preserved_count = 0;
    
    foreach ($users_to_delete as $user) {
        // Double-check: Make sure user has no orders
        $orders = wc_get_orders([
            'customer_id' => $user->ID,
            'limit' => 1,
            'status' => 'any'
        ]);
        
        // Check if user has any meaningful activity
        $has_activity = false;
        
        // Check for WooCommerce orders
        if (!empty($orders)) {
            $has_activity = true;
        }
        
        // Check for posts/comments (if they've been active)
        $post_count = count_user_posts($user->ID);
        $comment_count = get_comments([
            'user_id' => $user->ID,
            'count' => true
        ]);
        
        if ($post_count > 0 || $comment_count > 0) {
            $has_activity = true;
        }
        
        // Check for important meta data
        $important_meta = [
            'billing_address_1',
            'billing_phone',
            'billing_company',
            'woocommerce_stripe_customer_id',
            'last_login'
        ];
        
        foreach ($important_meta as $meta_key) {
            if (get_user_meta($user->ID, $meta_key, true)) {
                $has_activity = true;
                break;
            }
        }
        
        if (!$has_activity) {
            // Safe to delete
            $deletion_log[] = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'registered' => $user->user_registered,
                'action' => 'deleted'
            ];
            
            // Use require_once to ensure the function is available
            if (!function_exists('wp_delete_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            
            wp_delete_user($user->ID);
            $deleted_count++;
        } else {
            // Preserve user due to activity
            $deletion_log[] = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'registered' => $user->user_registered,
                'action' => 'preserved',
                'reason' => 'has_activity'
            ];
            $preserved_count++;
        }
    }
    
    // Log the cleanup action
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('INTERSOCCER_CLEANUP: Deleted ' . $deleted_count . ' users, preserved ' . $preserved_count . ' users with activity');
        error_log('INTERSOCCER_CLEANUP_LOG: ' . json_encode($deletion_log));
    }
    
    return [
        'deleted' => $deleted_count,
        'preserved' => $preserved_count,
        'log' => $deletion_log
    ];
}

/**
 * Preview function to show what would be deleted without actually deleting
 */
function intersoccer_preview_cleanup() {
    $cutoff_days = isset($_POST['preview_days']) ? (int)$_POST['preview_days'] : 30;
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cutoff_days} days"));
    
    $users_query = new WP_User_Query([
        'role__in' => ['customer', 'subscriber'],
        'date_query' => [
            [
                'before' => $cutoff_date,
                'inclusive' => true,
            ],
        ],
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'intersoccer_players',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key' => 'intersoccer_players',
                'value' => '',
                'compare' => '='
            ],
            [
                'key' => 'intersoccer_players',
                'value' => 'a:0:{}',
                'compare' => '='
            ]
        ],
        'number' => 1000 // Limit preview to first 1000
    ]);
    
    return $users_query->get_results();
}

function player_management_render_advanced_tab() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'player-management'));
    }

    wp_enqueue_script('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.js', [], '5.65.7', true);
    wp_enqueue_script('codemirror-json', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/javascript/javascript.min.js', [], '5.65.7', true);
    wp_enqueue_style('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.css', [], '5.65.7');

    $message = '';
    $current_year = date('Y');
    $cutoff_year = $current_year - 14;

    // Handle form submissions
    if (isset($_POST['auto_create_attributes']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        $attributes_to_create = [
            'pa_booking-type' => ['label' => 'Booking Type', 'values' => ['week' => 'Week', 'full_term' => 'Full Term', 'day' => 'Day', 'buyclub' => 'BuyClub']],
            'pa_intersoccer-venues' => ['label' => 'Venues', 'values' => [
                'varembe' => 'Varembe',
                'seefeld' => 'FC Seefeld',
                'nyon' => 'Nyon',
                'rochettaz' => 'Stade de Rochettaz',
            ]],
        ];

        if (class_exists('WP_Background_Process')) {
            $process = new Player_Management_Attribute_Process();
            foreach ($attributes_to_create as $attribute_name => $attribute_data) {
                $process->push_to_queue([
                    'slug' => $attribute_name,
                    'label' => $attribute_data['label'],
                    'values' => $attribute_data['values'],
                ]);
            }
            $process->save()->dispatch();
            $message .= __('Attribute creation queued. Check status in Site Health > Info.', 'player-management') . '<br>';
        } else {
            foreach ($attributes_to_create as $attribute_name => $attribute_data) {
                $attribute_id = wc_create_attribute([
                    'name' => $attribute_data['label'],
                    'slug' => $attribute_name,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                ]);
                if (!is_wp_error($attribute_id)) {
                    register_taxonomy($attribute_name, 'product', [
                        'label' => $attribute_data['label'],
                        'hierarchical' => false,
                        'public' => false,
                        'show_ui' => true,
                    ]);
                    foreach ($attribute_data['values'] as $slug => $name) {
                        if (!term_exists($slug, $attribute_name)) {
                            wp_insert_term($name, $attribute_name, ['slug' => $slug]);
                        }
                    }
                }
            }
            $message .= __('Attributes created synchronously.', 'player-management') . '<br>';
        }
    }

    // Database cleanup
    if (isset($_POST['cleanup_empty_users']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        if (!isset($_POST['confirm_cleanup']) || $_POST['confirm_cleanup'] !== 'yes') {
            $message .= __('Please confirm user cleanup by checking the confirmation box.', 'player-management') . '<br>';
        } else {
            $cleanup_result = intersoccer_cleanup_empty_users();
            $message .= sprintf(
                __('User cleanup completed: %d users deleted, %d users preserved (had activity).', 'player-management'),
                $cleanup_result['deleted'],
                $cleanup_result['preserved']
            ) . '<br>';
            
            // Save detailed log to database
            update_option('intersoccer_last_cleanup_log', [
                'timestamp' => current_time('mysql'),
                'result' => $cleanup_result
            ]);
        }
    }

    // Preview cleanup
    if (isset($_POST['preview_cleanup']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        $preview_users = intersoccer_preview_cleanup();
        $message .= sprintf(__('Preview: %d users would be affected by cleanup.', 'player-management'), count($preview_users)) . '<br>';
    }

    if (isset($_POST['import_camp_terms']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
            $lines = explode("\n", $csv_data);
            $terms = [];
            foreach ($lines as $line) {
                $data = str_getcsv($line);
                if (isset($data[64]) && strpos($data[64], 'Summer Week') !== false) {
                    $terms[] = $data[64];
                }
            }
            foreach ($terms as $term) {
                wp_insert_term($term, 'pa_camp-terms', ['slug' => sanitize_title($term)]);
            }
            $message .= __('Camp terms imported successfully.', 'player-management') . '<br>';
        } else {
            $message .= __('No CSV file uploaded.', 'player-management') . '<br>';
        }
    }

    if (isset($_POST['import_users_players']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
            $lines = explode("\n", $csv_data);
            $header = str_getcsv(array_shift($lines));
            foreach ($lines as $line) {
                $data = str_getcsv($line);
                if (count($data) < 4) continue;
                $email = sanitize_email($data[0]);
                $first_name = sanitize_text_field($data[1]);
                $last_name = sanitize_text_field($data[2]);
                $region = sanitize_text_field($data[3]);
                $player_name = sanitize_text_field($data[4] ?? '');
                $player_dob = sanitize_text_field($data[5] ?? '');
                $player_gender = sanitize_text_field($data[6] ?? 'Other');

                // Create or update user
                $user = get_user_by('email', $email);
                if (!$user) {
                    $user_id = wp_create_user($email, wp_generate_password(), $email);
                    if (!is_wp_error($user_id)) {
                        wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name]);
                        update_user_meta($user_id, 'billing_region', $region);
                        add_user_to_role($user_id, 'customer');
                    }
                } else {
                    $user_id = $user->ID;
                    update_user_meta($user_id, 'billing_region', $region);
                }

                // Add player
                if ($player_name && $user_id) {
                    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
                    $players[] = [
                        'name' => $player_name,
                        'dob' => $player_dob,
                        'gender' => $player_gender,
                        'age_group' => $player_dob ? ($age <= 5 ? 'Mini Soccer' : ($age <= 13 ? 'Fun Footy' : 'Soccer League')) : 'N/A'
                    ];
                    update_user_meta($user_id, 'intersoccer_players', $players);
                }
            }
            $message .= __('Users and players imported successfully.', 'player-management') . '<br>';
        } else {
            $message .= __('No CSV file uploaded for import.', 'player-management') . '<br>';
        }
    }

    if (isset($_POST['export_users_players']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_players_export_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'First Name', 'Last Name', 'Region', 'Player Name', 'Player DOB', 'Player Gender']);

        $users = get_users(['role' => 'customer']);
        foreach ($users as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true) ?: '';
            $last_name = get_user_meta($user->ID, 'last_name', true) ?: '';
            $region = get_user_meta($user->ID, 'billing_region', true) ?: '';
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            foreach ($players as $player) {
                fputcsv($output, [
                    $user->user_email,
                    $first_name,
                    $last_name,
                    $region,
                    $player['name'] ?? '',
                    $player['dob'] ?? '',
                    $player['gender'] ?? 'Other'
                ]);
            }
        }
        fclose($output);
        exit;
    }

    if (isset($_POST['flag_older_players']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
        if (!isset($_POST['confirm_flag']) || $_POST['confirm_flag'] !== 'yes') {
            $message .= __('Please confirm player flagging.', 'player-management') . '<br>';
        } else {
            $users = get_users(['role' => 'customer']);
            $flagged_count = 0;
            foreach ($users as $user) {
                $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
                foreach ($players as $index => $player) {
                    $dob = $player['dob'] ?? '';
                    if ($dob) {
                        $birth_year = (int)substr($dob, 0, 4);
                        if ($current_year - $birth_year >= 14) {
                            $players[$index]['ineligible'] = true;
                            $flagged_count++;
                            error_log(sprintf('Flagged player %s (DOB: %s) for user %d on %s', $player['name'], $dob, $user->ID, current_time('mysql')));
                        }
                    }
                }
                update_user_meta($user->ID, 'intersoccer_players', $players);
            }
            $message .= sprintf(__('Flagged %d players as ineligible.', 'player-management'), $flagged_count) . '<br>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Advanced Settings', 'player-management'); ?></h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo wp_kses_post($message); ?></p></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('player_management_advanced_actions', 'advanced_nonce'); ?>
            
            <!-- Database Cleanup Section -->
            <h2><?php _e('🗄️ Database Cleanup', 'player-management'); ?></h2>
            <p><?php _e('Remove user accounts that have no players and no activity (orders, posts, etc.).', 'player-management'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Age Threshold', 'player-management'); ?></th>
                    <td>
                        <input type="number" name="cleanup_days" value="30" min="1" max="365" />
                        <p class="description"><?php _e('Only delete accounts older than this many days.', 'player-management'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Safety Check', 'player-management'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="confirm_cleanup" value="yes" required>
                            <?php _e('I understand this will permanently delete user accounts with no players and no activity.', 'player-management'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p>
                <input type="submit" name="preview_cleanup" class="button" 
                       value="<?php _e('Preview Cleanup', 'player-management'); ?>">
                <input type="submit" name="cleanup_empty_users" class="button button-secondary" 
                       value="<?php _e('Run Cleanup', 'player-management'); ?>"
                       onclick="return confirm('Are you sure? This action cannot be undone.');">
            </p>

            <!-- Show preview results if available -->
            <?php
            if (isset($_POST['preview_cleanup']) && wp_verify_nonce($_POST['advanced_nonce'], 'player_management_advanced_actions')) {
                $preview_users = intersoccer_preview_cleanup();
                if (!empty($preview_users)) {
                    echo '<h3>' . __('Preview of Users to be Cleaned Up', 'player-management') . '</h3>';
                    echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>ID</th><th>Email</th><th>Registered</th><th>Last Login</th><th>Orders</th></tr></thead><tbody>';
                    
                    foreach (array_slice($preview_users, 0, 50) as $user) { // Show first 50
                        $last_login = get_user_meta($user->ID, 'last_login', true) ?: 'Never';
                        $order_count = count(wc_get_orders(['customer_id' => $user->ID, 'limit' => 1]));
                        echo '<tr>';
                        echo '<td>' . esc_html($user->ID) . '</td>';
                        echo '<td>' . esc_html($user->user_email) . '</td>';
                        echo '<td>' . esc_html($user->user_registered) . '</td>';
                        echo '<td>' . esc_html($last_login) . '</td>';
                        echo '<td>' . esc_html($order_count) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                    echo '</div>';
                    
                    if (count($preview_users) > 50) {
                        echo '<p><em>' . sprintf(__('... and %d more users.', 'player-management'), count($preview_users) - 50) . '</em></p>';
                    }
                }
            }
            ?>

            <!-- Show last cleanup results -->
            <?php
            $last_cleanup = get_option('intersoccer_last_cleanup_log');
            if ($last_cleanup) {
                echo '<h3>' . __('Last Cleanup Results', 'player-management') . '</h3>';
                echo '<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin: 10px 0;">';
                echo '<p><strong>' . __('Date:', 'player-management') . '</strong> ' . esc_html($last_cleanup['timestamp']) . '</p>';
                echo '<p><strong>' . __('Deleted:', 'player-management') . '</strong> ' . esc_html($last_cleanup['result']['deleted']) . ' users</p>';
                echo '<p><strong>' . __('Preserved:', 'player-management') . '</strong> ' . esc_html($last_cleanup['result']['preserved']) . ' users (had activity)</p>';
                echo '</div>';
            }
            ?>

            <hr style="margin: 30px 0;">

            <!-- Auto-Create Attributes Section -->
            <h2><?php _e('⚙️ Auto-Create Attributes', 'player-management'); ?></h2>
            <p><?php _e('Create WooCommerce product attributes for InterSoccer events.', 'player-management'); ?></p>
            <p><input type="submit" name="auto_create_attributes" class="button button-primary" value="<?php _e('Create Attributes', 'player-management'); ?>"></p>

            <hr style="margin: 30px 0;">

            <!-- Import/Export Section -->
            <h2><?php _e('📁 Import/Export', 'player-management'); ?></h2>
            
            <h3><?php _e('Import Camp Terms from CSV', 'player-management'); ?></h3>
            <p><input type="file" name="csv_file" accept=".csv"></p>
            <p><input type="submit" name="import_camp_terms" class="button button-primary" value="<?php _e('Import Terms', 'player-management'); ?>"></p>
            
            <h3><?php _e('Import Users and Players from CSV', 'player-management'); ?></h3>
            <p><input type="file" name="csv_file" accept=".csv"></p>
            <p><input type="submit" name="import_users_players" class="button button-primary" value="<?php _e('Import Users & Players', 'player-management'); ?>"></p>
            
            <h3><?php _e('Export Users and Players', 'player-management'); ?></h3>
            <p><input type="submit" name="export_users_players" class="button button-primary" value="<?php _e('Export to CSV', 'player-management'); ?>"></p>

            <hr style="margin: 30px 0;">

            <!-- Player Management Section -->
            <h2><?php _e('👥 Player Management', 'player-management'); ?></h2>
            
            <h3><?php _e('Flag Older Players', 'player-management'); ?></h3>
            <p><?php printf(__('Flag players born before %s as ineligible.', 'player-management'), $cutoff_year); ?></p>
            <p><label><input type="checkbox" name="confirm_flag" value="yes"> <?php _e('Confirm Flagging', 'player-management'); ?></label></p>
            <p><input type="submit" name="flag_older_players" class="button button-danger" value="<?php _e('Flag Players', 'player-management'); ?>"></p>

            <hr style="margin: 30px 0;">

            <!-- Advanced Configuration -->
            <h2><?php _e('🔧 Advanced Configuration', 'player-management'); ?></h2>
            <h3><?php _e('Custom JSON Attributes', 'player-management'); ?></h3>
            <textarea id="json_attributes" name="json_attributes" rows="10" cols="50" placeholder='{"attribute_name": {"label": "Display Name", "values": {"value1": "Label1", "value2": "Label2"}}}'></textarea>
            <p><input type="submit" name="save_json_attributes" class="button button-primary" value="<?php _e('Save JSON', 'player-management'); ?>"></p>
        </form>

        <!-- System Information -->
        <hr style="margin: 30px 0;">
        <h2><?php _e('📊 System Information', 'player-management'); ?></h2>
        <div style="background: #f1f1f1; padding: 15px; border-radius: 4px;">
            <?php
            // Get system stats
            $total_users = count_users();
            $users_with_players = count(get_users([
                'role__in' => ['customer', 'subscriber'],
                'fields' => 'ID',
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]));
            
            $total_orders = count(wc_get_orders(['limit' => -1, 'return' => 'ids']));
            $memory_limit = ini_get('memory_limit');
            $memory_usage = memory_get_peak_usage(true);
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Total Users:', 'player-management'); ?></th>
                    <td><?php echo esc_html($total_users['total_users']); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Users with Players:', 'player-management'); ?></th>
                    <td><?php echo esc_html($users_with_players); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Users without Players:', 'player-management'); ?></th>
                    <td><?php echo esc_html($total_users['total_users'] - $users_with_players); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Total Orders:', 'player-management'); ?></th>
                    <td><?php echo esc_html($total_orders); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('PHP Memory Limit:', 'player-management'); ?></th>
                    <td><?php echo esc_html($memory_limit); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Current Memory Usage:', 'player-management'); ?></th>
                    <td><?php echo esc_html(round($memory_usage / 1024 / 1024, 2)) . ' MB'; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WordPress Version:', 'player-management'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WooCommerce Version:', 'player-management'); ?></th>
                    <td><?php echo esc_html(defined('WC_VERSION') ? WC_VERSION : 'Not Available'); ?></td>
                </tr>
            </table>
        </div>

        <!-- Help Section -->
        <hr style="margin: 30px 0;">
        <h2><?php _e('❓ Help & Documentation', 'player-management'); ?></h2>
        <div style="background: #e7f3ff; padding: 15px; border-radius: 4px; border-left: 4px solid #0073aa;">
            <h4><?php _e('Database Cleanup Guidelines:', 'player-management'); ?></h4>
            <ul>
                <li><?php _e('Always run a preview before performing cleanup', 'player-management'); ?></li>
                <li><?php _e('Users with orders, posts, or comments will be preserved', 'player-management'); ?></li>
                <li><?php _e('Users with billing information will be preserved', 'player-management'); ?></li>
                <li><?php _e('Recommended to backup database before major cleanups', 'player-management'); ?></li>
            </ul>
            
            <h4><?php _e('Performance Tips:', 'player-management'); ?></h4>
            <ul>
                <li><?php _e('Regular cleanup helps maintain database performance', 'player-management'); ?></li>
                <li><?php _e('Use 30-90 day age thresholds for safety', 'player-management'); ?></li>
                <li><?php _e('Monitor memory usage during large operations', 'player-management'); ?></li>
            </ul>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Initialize CodeMirror if available
                if (typeof CodeMirror !== 'undefined') {
                    CodeMirror.fromTextArea(document.getElementById('json_attributes'), {
                        mode: 'application/json',
                        lineNumbers: true,
                        theme: 'default',
                        autoCloseBrackets: true,
                        matchBrackets: true
                    });
                }

                // Add confirmation dialogs for destructive actions
                $('input[name="cleanup_empty_users"]').on('click', function(e) {
                    if (!$('input[name="confirm_cleanup"]').is(':checked')) {
                        e.preventDefault();
                        alert('<?php _e('Please check the confirmation box first.', 'player-management'); ?>');
                        return false;
                    }
                });

                $('input[name="flag_older_players"]').on('click', function(e) {
                    if (!$('input[name="confirm_flag"]').is(':checked')) {
                        e.preventDefault();
                        alert('<?php _e('Please check the confirmation box first.', 'player-management'); ?>');
                        return false;
                    }
                });

                // Auto-check cleanup preview when days change
                $('input[name="cleanup_days"], input[name="preview_days"]').on('change', function() {
                    var days = $(this).val();
                    if (days < 7) {
                        $(this).css('background-color', '#ffebee');
                        alert('<?php _e('Warning: Very short age thresholds may delete recent accounts.', 'player-management'); ?>');
                    } else {
                        $(this).css('background-color', '');
                    }
                });
            });
        </script>

        <style>
            .form-table th {
                width: 200px;
            }
            .button-danger {
                background: #dc3232;
                border-color: #dc3232;
                color: white;
            }
            .button-danger:hover {
                background: #a00;
                border-color: #a00;
            }
            .notice-success {
                margin: 20px 0;
            }
            h2 {
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            .wrap h3 {
                margin-top: 25px;
                margin-bottom: 15px;
            }
        </style>
    </div>
    <?php
}
?>