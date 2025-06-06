<?php
/**
 * Player Management Advanced Settings
 * Handles attribute creation, import/export, and player flagging.
 * Author: Jeremy Lee
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
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('player_management_advanced_actions', 'advanced_nonce'); ?>
            <h2><?php _e('Auto-Create Attributes', 'player-management'); ?></h2>
            <p><input type="submit" name="auto_create_attributes" class="button button-primary" value="<?php _e('Create Attributes', 'player-management'); ?>"></p>
            <h2><?php _e('Import Camp Terms from CSV', 'player-management'); ?></h2>
            <p><input type="file" name="csv_file" accept=".csv"></p>
            <p><input type="submit" name="import_camp_terms" class="button button-primary" value="<?php _e('Import Terms', 'player-management'); ?>"></p>
            <h2><?php _e('Import Users and Players from CSV', 'player-management'); ?></h2>
            <p><input type="file" name="csv_file" accept=".csv"></p>
            <p><input type="submit" name="import_users_players" class="button button-primary" value="<?php _e('Import Users & Players', 'player-management'); ?>"></p>
            <h2><?php _e('Export Users and Players', 'player-management'); ?></h2>
            <p><input type="submit" name="export_users_players" class="button button-primary" value="<?php _e('Export to CSV', 'player-management'); ?>"></p>
            <h2><?php _e('Flag Older Players', 'player-management'); ?></h2>
            <p><?php printf(__('Flag players born before %s as ineligible.', 'player-management'), $cutoff_year); ?></p>
            <p><label><input type="checkbox" name="confirm_flag" value="yes"> <?php _e('Confirm Flagging', 'player-management'); ?></label></p>
            <p><input type="submit" name="flag_older_players" class="button button-danger" value="<?php _e('Flag Players', 'player-management'); ?>"></p>
            <h2><?php _e('Custom JSON Attributes', 'player-management'); ?></h2>
            <textarea id="json_attributes" name="json_attributes" rows="10" cols="50"></textarea>
            <p><input type="submit" name="save_json_attributes" class="button button-primary" value="<?php _e('Save JSON', 'player-management'); ?>"></p>
        </form>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        <script>
            jQuery(document).ready(function($) {
                if (typeof CodeMirror !== 'undefined') {
                    CodeMirror.fromTextArea(document.getElementById('json_attributes'), {
                        mode: 'application/json',
                        lineNumbers: true,
                        theme: 'default'
                    });
                }
            });
        </script>
    </div>
    <?php
}
?>
