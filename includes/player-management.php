<?php
/**
 * File: includes/player-management.php
 * Description: Renders the player management form on the WooCommerce My Account page and admin dashboard. Displays players in a table with add/edit/delete functionality and supports Elementor widget customization. Includes a user profile section for admins to manage players in user metadata.
 * Author: Jeremy Lee
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function to count events for a player
if (!function_exists('intersoccer_get_player_event_count')) {
    function intersoccer_get_player_event_count($user_id, $player_index) {
        $count = 0;
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-completed', 'wc-processing'],
        ]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $player_index_meta = $item->get_meta('intersoccer_player_index');
                if ($player_index_meta == $player_index) {
                    $count++;
                }
            }
        }
        return $count;
    }
}


// Render player management form
function intersoccer_render_players_form($is_admin = false, $settings = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Rendering player management form, is_admin: ' . ($is_admin ? 'true' : 'false') . ', endpoint: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'));
    }

    if (!is_user_logged_in()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: User not logged in for player management');
        }
        return '<p>' . esc_html__('Please log in to manage your Attendees.', 'player-management') . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">Log in</a> or <a href="' . esc_url(wp_registration_url()) . '">register</a>.</p>';
    }

    $user_id = $is_admin ? null : get_current_user_id();
    if (!$user_id && !$is_admin) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: No user ID found for player management');
        }
        return '<p>' . esc_html__('Error: Unable to load user data.', 'player-management') . '</p>';
    }

    // Validate settings array
    $settings = array_filter($settings, function($key) {
        if ($key === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Removed empty key from settings array');
            }
            return false;
        }
        return true;
    }, ARRAY_FILTER_USE_KEY);

    // Elementor widget settings defaults
    $show_first_name = isset($settings['show_first_name']) ? $settings['show_first_name'] === 'yes' : true;
    $show_last_name = isset($settings['show_last_name']) ? $settings['show_last_name'] === 'yes' : true;
    $show_dob = isset($settings['show_dob']) ? $settings['show_dob'] === 'yes' : true;
    $show_gender = isset($settings['show_gender']) ? $settings['show_gender'] === 'yes' : true;
    $show_avs_number = isset($settings['show_avs_number']) ? $settings['show_avs_number'] === 'yes' : true;
    $show_events = isset($settings['show_events']) ? $settings['show_events'] === 'yes' : true;
    $show_add_button = isset($settings['show_add_button']) ? $settings['show_add_button'] === 'yes' : true;
    $show_form_title = isset($settings['show_form_title']) ? $settings['show_form_title'] === 'yes' : true;

    $first_name_heading = !empty($settings['first_name_heading']) ? $settings['first_name_heading'] : __('First Name', 'player-management');
    $last_name_heading = !empty($settings['last_name_heading']) ? $settings['last_name_heading'] : __('Last Name', 'player-management');
    $dob_heading = !empty($settings['dob_heading']) ? $settings['dob_heading'] : __('DOB', 'player-management');
    $gender_heading = !empty($settings['gender_heading']) ? $settings['gender_heading'] : __('Gender', 'player-management');
    $avs_number_heading = !empty($settings['avs_number_heading']) ? $settings['avs_number_heading'] : __('AVS Number', 'player-management');
    $events_heading = !empty($settings['events_heading']) ? $settings['events_heading'] : __('Events', 'player-management');
    $actions_heading = !empty($settings['actions_heading']) ? $settings['actions_heading'] : __('Actions', 'player-management');
    $form_title_text = !empty($settings['form_title_text']) ? $settings['form_title_text'] : ($is_admin ? __('Manage All Players', 'player-management') : __('Manage Your Attendees', 'player-management'));
    $total_players = isset($settings['total_players']) ? (int)$settings['total_players'] : 0;

    // Styling settings for Elementor widget
    $container_background = !empty($settings['container_background']) ? $settings['container_background'] : '';
    $container_text_color = !empty($settings['container_text_color']) ? $settings['container_text_color'] : '';
    $container_padding = !empty($settings['container_padding']) ? $settings['container_padding'] : '';
    $table_border_color = !empty($settings['table_border_color']) ? $settings['table_border_color'] : '#ddd';
    $header_background = !empty($settings['header_background']) ? $settings['header_background'] : '#7ab55c'; // Green to match "Book Now" button

    $players = [];
    $cantons = $settings['cantons'] ?? [];
    $age_groups = $settings['age_groups'] ?? [];
    $event_types = $settings['event_types'] ?? [];

    if ($is_admin) {
        $users = get_users(['role__in' => ['customer', 'subscriber']]);
        foreach ($users as $user) {
            $user_players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: 'Unknown';
            $billing_city = get_user_meta($user->ID, 'billing_city', true) ?: '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: User ' . $user->ID . ' canton: ' . $billing_state . ', city: ' . $billing_city);
            }
            foreach ($user_players as $index => $player) {
                $event_count = intersoccer_get_player_event_count($user->ID, $index);
                $creation_date = !empty($player['creation_timestamp']) ? date('Y-m-d', $player['creation_timestamp']) : 'N/A';
                $medical_conditions = !empty($player['medical_conditions']) ? substr($player['medical_conditions'], 0, 20) . (strlen($player['medical_conditions']) > 20 ? '...' : '') : '';
                $past_events = intersoccer_get_player_past_events($user->ID, $index);
                $players[] = array_merge($player, [
                    'user_id' => $user->ID,
                    'player_index' => $index,
                    'event_count' => $event_count,
                    'canton' => $billing_state,
                    'city' => $billing_city,
                    'creation_date' => $creation_date,
                    'medical_conditions_display' => $medical_conditions,
                    'past_events' => $past_events,
                ]);
            }
        }
    } else {
        $cache_key = 'intersoccer_players_' . $user_id;
        $players = wp_cache_get($cache_key, 'intersoccer');
        if (false === $players) {
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
            wp_cache_set($cache_key, $players, 'intersoccer', 3600);
        }

        // Deduplicate players based on first_name, last_name, and dob (case-insensitive)
        $unique_players = [];
        $seen = [];
        foreach ($players as $index => $player) {
            $key = strtolower($player['first_name']) . '|' . strtolower($player['last_name']) . '|' . $player['dob'];
            if (!in_array($key, $seen)) {
                $seen[] = $key;
                $player['event_count'] = intersoccer_get_player_event_count($user_id, $index);
                $player['creation_date'] = !empty($player['creation_timestamp']) ? date('Y-m-d', $player['creation_timestamp']) : 'N/A';
                $player['medical_conditions_display'] = !empty($player['medical_conditions']) ? substr($player['medical_conditions'], 0, 20) . (strlen($player['medical_conditions']) > 20 ? '...' : '') : '';
                $unique_players[] = $player;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('InterSoccer: Removed duplicate player for user ' . $user_id . ': ' . $key);
                }
            }
        }
        $players = $unique_players;
    }

    // Calculate colspan for table rows
    $colspan = ($is_admin ? 3 : 0) + ($show_first_name ? 1 : 0) + ($show_last_name ? 1 : 0) + ($show_dob ? 1 : 0) + ($show_gender ? 1 : 0) + ($show_avs_number ? 1 : 0) + ($show_events ? 1 : 0) + ($is_admin ? 3 : 0) + 1;

    ob_start();
?>
    <div class="intersoccer-player-management" role="region" aria-label="<?php esc_attr_e('Attendee Management Dashboard', 'player-management'); ?>">
        <?php if ($show_form_title) : ?>
            <h2 class="intersoccer-form-title"><?php echo esc_html($form_title_text); ?></h2>
        <?php endif; ?>
        <div class="intersoccer-message" style="display: none;" role="alert" aria-live="polite"></div>

        <?php if ($is_admin && $total_players > 0) : ?>
            <div class="total-players">
                <?php printf(esc_html__('Total Players: %d', 'player-management'), $total_players); ?>
            </div>
        <?php endif; ?>

        <?php if ($show_add_button) : ?>
            <div class="intersoccer-player-actions">
                <a href="#" class="toggle-add-player" aria-label="<?php esc_attr_e('Add New Player', 'player-management'); ?>" aria-controls="add-player-section">
                    <?php esc_html_e('Add', 'player-management'); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="intersoccer-table-wrapper" role="region" aria-label="<?php esc_attr_e('Attendee Table', 'player-management'); ?>">
            <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php esc_attr_e('List of attendees', 'player-management'); ?>">
                <thead>
                    <tr>
                        <?php if ($is_admin) : ?>
                            <th scope="col"><?php esc_html_e('User ID', 'player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Canton', 'player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('City', 'player-management'); ?></th>
                        <?php endif; ?>
                        <?php if ($show_first_name) : ?>
                            <th scope="col"><?php echo esc_html($first_name_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($show_last_name) : ?>
                            <th scope="col"><?php echo esc_html($last_name_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($show_dob) : ?>
                            <th scope="col"><?php echo esc_html($dob_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($show_gender) : ?>
                            <th scope="col"><?php echo esc_html($gender_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($show_avs_number) : ?>
                            <th scope="col"><?php echo esc_html($avs_number_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($show_events) : ?>
                            <th scope="col"><?php echo esc_html($events_heading); ?></th>
                        <?php endif; ?>
                        <?php if ($is_admin) : ?>
                            <th scope="col"><?php esc_html_e('Medical Conditions', 'player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Creation Date', 'player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Past Events', 'player-management'); ?></th>
                        <?php endif; ?>
                        <th scope="col" class="actions"><?php echo esc_html($actions_heading); ?></th>
                    </tr>
                </thead>
                <tbody id="player-table">
                    <?php if (empty($players)) : ?>
                        <tr class="no-players">
                            <td colspan="<?php echo esc_attr($colspan); ?>">
                                <?php esc_html_e('No attendees added yet.', 'player-management'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($players as $index => $player) : ?>
                            <tr data-player-index="<?php echo esc_attr($is_admin ? $player['player_index'] : $index); ?>"
                                data-user-id="<?php echo esc_attr($player['user_id'] ?? $user_id); ?>"
                                data-first-name="<?php echo esc_attr($player['first_name'] ?? 'N/A'); ?>"
                                data-last-name="<?php echo esc_attr($player['last_name'] ?? 'N/A'); ?>"
                                data-dob="<?php echo esc_attr($player['dob'] ?? 'N/A'); ?>"
                                data-gender="<?php echo esc_attr(strtolower($player['gender'] ?? 'N/A')); ?>"
                                data-avs-number="<?php echo esc_attr($player['avs_number'] ?? 'N/A'); ?>"
                                data-event-count="<?php echo esc_attr($player['event_count'] ?? 0); ?>"
                                data-canton="<?php echo esc_attr($player['canton'] ?? ''); ?>"
                                data-city="<?php echo esc_attr($player['city'] ?? ''); ?>"
                                data-creation-timestamp="<?php echo esc_attr($player['creation_timestamp'] ?? ''); ?>"
                                data-event-age-groups="<?php echo esc_attr(implode(',', $player['event_age_groups'] ?? [])); ?>"
                                data-event-types="<?php echo esc_attr(implode(',', $player['event_types'] ?? [])); ?>">
                                <?php if ($is_admin) : ?>
                                    <td class="display-user-id">
                                        <a href="<?php echo esc_url(get_edit_user_link($player['user_id'])); ?>" aria-label="<?php esc_attr_e('Edit user profile', 'player-management'); ?>">
                                            <?php echo esc_html($player['user_id'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td class="display-canton"><?php echo esc_html($player['canton'] ?: 'N/A'); ?></td>
                                    <td class="display-city"><?php echo esc_html($player['city'] ?: 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_first_name) : ?>
                                    <td class="display-first-name"><?php echo esc_html($player['first_name'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_last_name) : ?>
                                    <td class="display-last-name"><?php echo esc_html($player['last_name'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_dob) : ?>
                                    <td class="display-dob"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_gender) : ?>
                                    <td class="display-gender"><?php echo esc_html($player['gender'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_avs_number) : ?>
                                    <td class="display-avs-number"><?php echo esc_html($player['avs_number'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($show_events) : ?>
                                    <td class="display-event-count"><?php echo esc_html($player['event_count'] ?? 0); ?></td>
                                <?php endif; ?>
                                <?php if ($is_admin) : ?>
                                    <td class="display-medical-conditions"><?php echo esc_html($player['medical_conditions_display'] ?? ''); ?></td>
                                    <td class="display-creation-date"><?php echo esc_html($player['creation_date'] ?? 'N/A'); ?></td>
                                    <td class="display-past-events">
                                        <?php if (!empty($player['past_events'])) : ?>
                                            <ul>
                                                <?php foreach ($player['past_events'] as $event) : ?>
                                                    <li>
                                                        <?php echo esc_html(is_array($event) ? ($event['name'] ?? $event) : $event); ?>
                                                        <?php if (is_array($event) && isset($event['date'], $event['venue'])) : ?>
                                                            (<?php echo esc_html($event['date']); ?>, <?php echo esc_html($event['venue']); ?>)
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <?php esc_html_e('No past events.', 'player-management'); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="actions">
                                    <a href="#" class="edit-player" data-index="<?php echo esc_attr($is_admin ? $player['player_index'] : $index); ?>" data-user-id="<?php echo esc_attr($player['user_id'] ?? $user_id); ?>" aria-label="<?php esc_attr_e('Edit player', 'player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>" aria-expanded="false">
                                        <?php esc_html_e('Edit', 'player-management'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Add Attendee Section -->
                    <?php if ($show_add_button) : ?>
                        <tr class="add-player-section" id="add-player-section">
                            <?php if ($is_admin) : ?>
                                <td>
                                    <span class="display-user-id">N/A</span>
                                </td>
                                <td>
                                    <span class="display-canton">N/A</span>
                                </td>
                                <td>
                                    <span class="display-city">N/A</span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_first_name) : ?>
                                <td>
                                    <input type="text" id="player_first_name" name="player_first_name" required aria-required="true" maxlength="50">
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_last_name) : ?>
                                <td>
                                    <input type="text" id="player_last_name" name="player_last_name" required aria-required="true" maxlength="50">
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_dob) : ?>
                                <td>
                                    <select id="player_dob_day" name="player_dob_day" required aria-required="true">
                                        <option value=""><?php esc_html_e('Day', 'player-management'); ?></option>
                                        <?php for ($day = 1; $day <= 31; $day++) : ?>
                                            <option value="<?php echo esc_attr(sprintf('%02d', $day)); ?>"><?php echo esc_html($day); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select id="player_dob_month" name="player_dob_month" required aria-required="true">
                                        <option value=""><?php esc_html_e('Month', 'player-management'); ?></option>
                                        <option value="01"><?php esc_html_e('January', 'player-management'); ?></option>
                                        <option value="02"><?php esc_html_e('February', 'player-management'); ?></option>
                                        <option value="03"><?php esc_html_e('March', 'player-management'); ?></option>
                                        <option value="04"><?php esc_html_e('April', 'player-management'); ?></option>
                                        <option value="05"><?php esc_html_e('May', 'player-management'); ?></option>
                                        <option value="06"><?php esc_html_e('June', 'player-management'); ?></option>
                                        <option value="07"><?php esc_html_e('July', 'player-management'); ?></option>
                                        <option value="08"><?php esc_html_e('August', 'player-management'); ?></option>
                                        <option value="09"><?php esc_html_e('September', 'player-management'); ?></option>
                                        <option value="10"><?php esc_html_e('October', 'player-management'); ?></option>
                                        <option value="11"><?php esc_html_e('November', 'player-management'); ?></option>
                                        <option value="12"><?php esc_html_e('December', 'player-management'); ?></option>
                                    </select>
                                    <select id="player_dob_year" name="player_dob_year" required aria-required="true">
                                        <option value=""><?php esc_html_e('Year', 'player-management'); ?></option>
                                        <?php for ($year = 2023; $year >= 2012; $year--) : ?>
                                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_gender) : ?>
                                <td>
                                    <select id="player_gender" name="player_gender" required aria-required="true">
                                        <option value=""><?php esc_html_e('Select Gender', 'player-management'); ?></option>
                                        <option value="male"><?php esc_html_e('Male', 'player-management'); ?></option>
                                        <option value="female"><?php esc_html_e('Female', 'player-management'); ?></option>
                                        <option value="other"><?php esc_html_e('Other', 'player-management'); ?></option>
                                    </select>
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_avs_number) : ?>
                                <td>
                                    <input type="text" id="player_avs_number" name="player_avs_number" aria-required="true" maxlength="50">
                                    <span class="avs-instruction"><?php esc_html_e('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', 'player-management'); ?></span>
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_events) : ?>
                                <td>
                                    <span class="display-event-count">0</span>
                                </td>
                            <?php endif; ?>
                            <?php if ($is_admin) : ?>
                                <td>
                                    <span class="display-medical-conditions"></span>
                                </td>
                                <td>
                                    <span class="display-creation-date"></span>
                                </td>
                                <td>
                                    <span class="display-past-events"><?php esc_html_e('No past events.', 'player-management'); ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="actions">
                                <a href="#" class="player-submit" aria-label="<?php esc_attr_e('Save Player', 'player-management'); ?>">
                                    <?php esc_html_e('Save', 'player-management'); ?>
                                    <span class="spinner" style="display: none;"></span>
                                </a>
                                <a href="#" class="cancel-add" aria-label="<?php esc_attr_e('Cancel Add', 'player-management'); ?>">
                                    <?php esc_html_e('Cancel', 'player-management'); ?>
                                </a>
                            </td>
                        </tr>
                        <tr class="add-player-medical">
                            <td colspan="<?php echo esc_attr($colspan); ?>">
                                <label for="player_medical"><?php esc_html_e('Medical Conditions:', 'player-management'); ?></label>
                                <textarea id="player_medical" name="player_medical" maxlength="500" aria-describedby="medical-instructions"></textarea>
                                <span id="medical-instructions" class="screen-reader-text"><?php esc_html_e('Optional field for medical conditions.', 'player-management'); ?></span>
                                <span class="error-message" style="display: none;"></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Add inline CSS for dynamic Elementor widget styles and loading animation
        $inline_css = '';
        if (!empty($container_background) || !empty($container_text_color) || !empty($container_padding) || !empty($table_border_color) || !empty($header_background)) {
            $inline_css .= '.intersoccer-player-management {';
            if (!empty($container_background)) {
                $inline_css .= 'background-color: ' . esc_attr($container_background) . ';';
            }
            if (!empty($container_text_color)) {
                $inline_css .= 'color: ' . esc_attr($container_text_color) . ';';
            }
            if (!empty($container_padding)) {
                $inline_css .= 'padding: ' . esc_attr($container_padding) . ';';
            }
            $inline_css .= '}';

            if (!empty($table_border_color)) {
                $inline_css .= '.intersoccer-player-management table { border: 1px solid ' . esc_attr($table_border_color) . '; }';
                $inline_css .= '.intersoccer-player-management th, .intersoccer-player-management td { border: 1px solid ' . esc_attr($table_border_color) . '; }';
            }

            if (!empty($header_background)) {
                $inline_css .= '.intersoccer-player-management th { background: ' . esc_attr($header_background) . '; color: #ffffff; }';
            }
        }
        // Add CSS for loading animation, AVS instruction, and editing highlight
        $inline_css .= '
            .loading::before {
                content: "";
                position: absolute;
                width: 20px;
                height: 20px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #7ab55c;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 1000;
            }
            @keyframes spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
            .loading td { position: relative; }
            .avs-instruction { font-size: 0.9em; color: #666; margin-top: 5px; display: block; }
            .profile-players { margin-top: 20px; }
            .profile-players table { width: 100%; border-collapse: collapse; }
            .profile-players th, .profile-players td { border: 1px solid #ddd; padding: 8px; }
            .profile-players th { background: #7ab55c; color: #ffffff; }
            .profile-players .actions a { margin-right: 10px; }
            .profile-players .error-message { color: red; display: none; }
            .editing { background-color: #fff3cd; }
        ';
        if (!empty($inline_css)) {
            wp_add_inline_style('intersoccer-player-management', $inline_css);
        }
        ?>
    </div>
<?php
    $output = ob_get_clean();

    // Add user profile player management section
    if ($is_admin) {
        add_action('show_user_profile', 'intersoccer_render_user_profile_players');
        add_action('edit_user_profile', 'intersoccer_render_user_profile_players');
    }

    return $output;
}

// Render player management section in user profile
function intersoccer_render_user_profile_players($user) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Rendering user profile players for user ' . $user->ID . ', players: ' . json_encode($players));
    }

    // Localize script for user profile
    $localize_data = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('intersoccer_player_nonce'),
        'user_id' => $user->ID,
        'is_admin' => '1',
        'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
        'preload_players' => $players,
        'server_time' => current_time('mysql'),
    ];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Localizing intersoccerPlayer data for user profile: ' . json_encode($localize_data));
    }
    wp_localize_script(
        'intersoccer-player-management-core',
        'intersoccerPlayer',
        $localize_data
    );

    $colspan = 7; // For user profile table: First Name, Last Name, DOB, Gender, AVS Number, Medical Conditions, Actions
?>
    <div class="profile-players">
        <h2><?php esc_html_e('InterSoccer Players', 'player-management'); ?></h2>
        <div class="intersoccer-message" style="display: none;" role="alert" aria-live="polite"></div>
        <div class="intersoccer-player-actions">
            <a href="#" class="toggle-add-player" aria-label="<?php esc_attr_e('Add New Player', 'player-management'); ?>" aria-controls="add-profile-player-section">
                <?php esc_html_e('Add New Player', 'player-management'); ?>
            </a>
        </div>
        <div class="intersoccer-table-wrapper" role="region" aria-label="<?php esc_attr_e('Attendee Table', 'player-management'); ?>">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('First Name', 'player-management'); ?></th>
                        <th><?php esc_html_e('Last Name', 'player-management'); ?></th>
                        <th><?php esc_html_e('DOB', 'player-management'); ?></th>
                        <th><?php esc_html_e('Gender', 'player-management'); ?></th>
                        <th><?php esc_html_e('AVS Number', 'player-management'); ?></th>
                        <th><?php esc_html_e('Medical Conditions', 'player-management'); ?></th>
                        <th><?php esc_html_e('Actions', 'player-management'); ?></th>
                    </tr>
                </thead>
                <tbody id="profile-player-table">
                    <?php if (empty($players)) : ?>
                        <tr class="no-players">
                            <td colspan="<?php echo esc_attr($colspan); ?>"><?php esc_html_e('No players added yet.', 'player-management'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($players as $index => $player) : ?>
                            <tr data-player-index="<?php echo esc_attr($index); ?>" 
                                data-user-id="<?php echo esc_attr($user->ID); ?>"
                                data-first-name="<?php echo esc_attr($player['first_name'] ?? 'N/A'); ?>"
                                data-last-name="<?php echo esc_attr($player['last_name'] ?? 'N/A'); ?>"
                                data-dob="<?php echo esc_attr($player['dob'] ?? 'N/A'); ?>"
                                data-gender="<?php echo esc_attr(strtolower($player['gender'] ?? 'N/A')); ?>"
                                data-avs-number="<?php echo esc_attr($player['avs_number'] ?? 'N/A'); ?>">
                                <td class="display-first-name"><?php echo esc_html($player['first_name'] ?? 'N/A'); ?></td>
                                <td class="display-last-name"><?php echo esc_html($player['last_name'] ?? 'N/A'); ?></td>
                                <td class="display-dob"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                                <td class="display-gender"><?php echo esc_html($player['gender'] ?? 'N/A'); ?></td>
                                <td class="display-avs-number"><?php echo esc_html($player['avs_number'] ?? 'N/A'); ?></td>
                                <td class="display-medical-conditions"><?php echo esc_html($player['medical_conditions'] ? substr($player['medical_conditions'], 0, 20) . (strlen($player['medical_conditions']) > 20 ? '...' : '') : ''); ?></td>
                                <td class="actions">
                                    <a href="#" class="edit-profile-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php esc_attr_e('Edit player', 'player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>">Edit</a>
                                    <a href="#" class="delete-profile-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php esc_attr_e('Delete player', 'player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="add-profile-player-section" style="display: none;">
                        <td>
                            <input type="text" name="player_first_name" required aria-required="true" maxlength="50">
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td>
                            <input type="text" name="player_last_name" required aria-required="true" maxlength="50">
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td>
                            <select name="player_dob_day" required aria-required="true">
                                <option value=""><?php esc_html_e('Day', 'player-management'); ?></option>
                                <?php for ($day = 1; $day <= 31; $day++) : ?>
                                    <option value="<?php echo esc_attr(sprintf('%02d', $day)); ?>"><?php echo esc_html($day); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="player_dob_month" required aria-required="true">
                                <option value=""><?php esc_html_e('Month', 'player-management'); ?></option>
                                <option value="01"><?php esc_html_e('January', 'player-management'); ?></option>
                                <option value="02"><?php esc_html_e('February', 'player-management'); ?></option>
                                <option value="03"><?php esc_html_e('March', 'player-management'); ?></option>
                                <option value="04"><?php esc_html_e('April', 'player-management'); ?></option>
                                <option value="05"><?php esc_html_e('May', 'player-management'); ?></option>
                                <option value="06"><?php esc_html_e('June', 'player-management'); ?></option>
                                <option value="07"><?php esc_html_e('July', 'player-management'); ?></option>
                                <option value="08"><?php esc_html_e('August', 'player-management'); ?></option>
                                <option value="09"><?php esc_html_e('September', 'player-management'); ?></option>
                                <option value="10"><?php esc_html_e('October', 'player-management'); ?></option>
                                <option value="11"><?php esc_html_e('November', 'player-management'); ?></option>
                                <option value="12"><?php esc_html_e('December', 'player-management'); ?></option>
                            </select>
                            <select name="player_dob_year" required aria-required="true">
                                <option value=""><?php esc_html_e('Year', 'player-management'); ?></option>
                                <?php for ($year = 2023; $year >= 2012; $year--) : ?>
                                    <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td>
                            <select name="player_gender" required aria-required="true">
                                <option value=""><?php esc_html_e('Select Gender', 'player-management'); ?></option>
                                <option value="male"><?php esc_html_e('Male', 'player-management'); ?></option>
                                <option value="female"><?php esc_html_e('Female', 'player-management'); ?></option>
                                <option value="other"><?php esc_html_e('Other', 'player-management'); ?></option>
                            </select>
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td>
                            <input type="text" name="player_avs_number" aria-required="true" maxlength="50">
                            <span class="avs-instruction"><?php esc_html_e('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', 'player-management'); ?></span>
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td>
                            <textarea name="player_medical" maxlength="500"></textarea>
                            <span class="error-message" style="display: none;"></span>
                        </td>
                        <td class="actions">
                            <a href="#" class="save-profile-player" aria-label="<?php esc_attr_e('Save Player', 'player-management'); ?>">Save</a>
                            <a href="#" class="cancel-profile-player" aria-label="<?php esc_attr_e('Cancel', 'player-management'); ?>">Cancel</a>
                        </td>
                    </tr>
                    <tr class="add-profile-player-medical" style="display: none;">
                        <td colspan="<?php echo esc_attr($colspan); ?>">
                            <label for="player_medical_profile"><?php esc_html_e('Medical Conditions:', 'player-management'); ?></label>
                            <textarea id="player_medical_profile" name="player_medical" maxlength="500" aria-describedby="medical-instructions-profile"></textarea>
                            <span id="medical-instructions-profile" class="screen-reader-text"><?php esc_html_e('Optional field for medical conditions.', 'player-management'); ?></span>
                            <span class="error-message" style="display: none;"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            const $profileTable = $('#profile-player-table');
            const $message = $('.profile-players .intersoccer-message');
            const isAdmin = intersoccerPlayer.is_admin === '1';
            const debugEnabled = intersoccerPlayer.debug === '1';

            // Toggle Add Player section
            $('.toggle-add-player').on('click', function(e) {
                e.preventDefault();
                $('.add-profile-player-section, .add-profile-player-medical').toggle();
                const isVisible = $('.add-profile-player-section').is(':visible');
                $(this).attr('aria-expanded', isVisible);
                if (isVisible) {
                    $('.add-profile-player-section input, .add-profile-player-section select, .add-profile-player-section textarea').val('');
                    $('.add-profile-player-section .error-message, .add-profile-player-medical .error-message').hide();
                    $('.add-profile-player-section input[name="player_first_name"]').focus();
                    if (debugEnabled) console.log('InterSoccer: Toggled add player section in user profile, visible:', isVisible);
                }
            });

            // Cancel adding player
            $('.cancel-profile-player').on('click', function(e) {
                e.preventDefault();
                $('.add-profile-player-section, .add-profile-player-medical').hide();
                $('.toggle-add-player').attr('aria-expanded', 'false').focus();
                $('.add-profile-player-section input, .add-profile-player-section select, .add-profile-player-section textarea').val('');
                $('.add-profile-player-section .error-message, .add-profile-player-medical .error-message').hide();
                if (debugEnabled) console.log('InterSoccer: Canceled adding player in user profile');
            });

            // Save new player
            $profileTable.on('click', '.save-profile-player', function(e) {
                e.preventDefault();
                const $row = $(this).closest('tr');
                const index = $row.data('player-index') || -1;
                const userId = intersoccerPlayer.user_id;
                const $medicalRow = $('.add-profile-player-medical');
                const firstName = $row.find('[name="player_first_name"]').val().trim();
                const lastName = $row.find('[name="player_last_name"]').val().trim();
                const dobDay = $row.find('[name="player_dob_day"]').val();
                const dobMonth = $row.find('[name="player_dob_month"]').val();
                const dobYear = $row.find('[name="player_dob_year"]').val();
                const dob = dobDay && dobMonth && dobYear ? `${dobYear}-${dobMonth}-${dobDay}` : '';
                const gender = $row.find('[name="player_gender"]').val();
                const avsNumber = $row.find('[name="player_avs_number"]').val().trim() || '0000';
                const medical = $medicalRow.find('[name="player_medical"]').val().trim();

                if (!intersoccerValidateRow($row, index === -1)) {
                    return;
                }

                const action = index === -1 ? 'intersoccer_add_player' : 'intersoccer_edit_player';
                const data = {
                    action: action,
                    nonce: intersoccerPlayer.nonce,
                    user_id: userId,
                    player_user_id: userId,
                    player_first_name: firstName,
                    player_last_name: lastName,
                    player_dob: dob,
                    player_gender: gender,
                    player_avs_number: avsNumber,
                    player_medical: medical,
                    is_admin: isAdmin ? '1' : '0',
                };
                if (index !== -1) data.player_index = index;

                if (debugEnabled) console.log('InterSoccer: Saving player in user profile, data:', JSON.stringify(data));

                $.ajax({
                    url: intersoccerPlayer.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            $message.text(response.data.message).show();
                            setTimeout(() => $message.hide(), 5000);
                            if (debugEnabled) console.log('InterSoccer: Player saved successfully:', response.data);
                            window.location.reload(); // Reload to reflect changes
                        } else {
                            $message.text(response.data.message || 'Failed to save player.').show();
                            setTimeout(() => $message.hide(), 5000);
                            if (debugEnabled) console.error('InterSoccer: Failed to save player:', response.data?.message);
                        }
                    },
                    error: function(xhr) {
                        $message.text('Error: Failed to save player - ' + (xhr.responseText || 'Unknown error')).show();
                        setTimeout(() => $message.hide(), 5000);
                        if (debugEnabled) console.error('InterSoccer: AJAX error saving player:', xhr.status, xhr.responseText, 'Response JSON:', JSON.stringify(xhr.responseJSON));
                    }
                });
            });

            // Edit player
            $profileTable.on('click', '.edit-profile-player', function(e) {
                e.preventDefault();
                const $row = $(this).closest('tr');
                const index = $row.data('player-index');
                const player = intersoccerPlayer.preload_players[index];
                if (!player) {
                    $message.text('Error: Could not load player data.').show();
                    setTimeout(() => $message.hide(), 5000);
                    if (debugEnabled) console.error('InterSoccer: Failed to load player data for index:', index);
                    return;
                }

                const dobParts = player.dob ? player.dob.split('-') : ['', '', ''];
                $row.addClass('editing');
                $message.text(`Editing player: ${player.first_name} ${player.last_name}`).show();
                setTimeout(() => $message.hide(), 5000);
                $row.html(`
                    <td>
                        <input type="text" name="player_first_name" value="${player.first_name || ''}" required aria-required="true" maxlength="50">
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td>
                        <input type="text" name="player_last_name" value="${player.last_name || ''}" required aria-required="true" maxlength="50">
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td>
                        <select name="player_dob_day" required aria-required="true">
                            <option value="">Day</option>
                            ${Array.from({ length: 31 }, (_, i) => i + 1).map(day => `<option value="${String(day).padStart(2, '0')}" ${dobParts[2] === String(day).padStart(2, '0') ? 'selected' : ''}>${day}</option>`).join('')}
                        </select>
                        <select name="player_dob_month" required aria-required="true">
                            <option value="">Month</option>
                            <option value="01" ${dobParts[1] === '01' ? 'selected' : ''}>January</option>
                            <option value="02" ${dobParts[1] === '02' ? 'selected' : ''}>February</option>
                            <option value="03" ${dobParts[1] === '03' ? 'selected' : ''}>March</option>
                            <option value="04" ${dobParts[1] === '04' ? 'selected' : ''}>April</option>
                            <option value="05" ${dobParts[1] === '05' ? 'selected' : ''}>May</option>
                            <option value="06" ${dobParts[1] === '06' ? 'selected' : ''}>June</option>
                            <option value="07" ${dobParts[1] === '07' ? 'selected' : ''}>July</option>
                            <option value="08" ${dobParts[1] === '08' ? 'selected' : ''}>August</option>
                            <option value="09" ${dobParts[1] === '09' ? 'selected' : ''}>September</option>
                            <option value="10" ${dobParts[1] === '10' ? 'selected' : ''}>October</option>
                            <option value="11" ${dobParts[1] === '11' ? 'selected' : ''}>November</option>
                            <option value="12" ${dobParts[1] === '12' ? 'selected' : ''}>December</option>
                        </select>
                        <select name="player_dob_year" required aria-required="true">
                            <option value="">Year</option>
                            ${Array.from({ length: 2023 - 2012 + 1 }, (_, i) => 2023 - i).map(year => `<option value="${year}" ${dobParts[0] === String(year) ? 'selected' : ''}>${year}</option>`).join('')}
                        </select>
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td>
                        <select name="player_gender" required aria-required="true">
                            <option value="">Select Gender</option>
                            <option value="male" ${player.gender === 'male' ? 'selected' : ''}>Male</option>
                            <option value="female" ${player.gender === 'female' ? 'selected' : ''}>Female</option>
                            <option value="other" ${player.gender === 'other' ? 'selected' : ''}>Other</option>
                        </select>
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td>
                        <input type="text" name="player_avs_number" value="${player.avs_number || ''}" aria-required="true" maxlength="50">
                        <span class="avs-instruction">No AVS? Enter foreign insurance number or "0000" and email us the insurance details.</span>
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td>
                        <textarea name="player_medical" maxlength="500">${player.medical_conditions || ''}</textarea>
                        <span class="error-message" style="display: none;"></span>
                    </td>
                    <td class="actions">
                        <a href="#" class="save-profile-player" data-index="${index}" aria-label="Save Player">Save</a>
                        <a href="#" class="cancel-profile-player" aria-label="Cancel">Cancel</a>
                    </td>
                `);
                $row.find('[name="player_first_name"]').focus();
                if (debugEnabled) console.log('InterSoccer: Editing player in user profile, index:', index);
            });

            // Cancel edit
            $profileTable.on('click', '.cancel-profile-player', function(e) {
                e.preventDefault();
                const $row = $(this).closest('tr');
                const index = $row.data('player-index');
                const player = intersoccerPlayer.preload_players[index];
                if (!player) {
                    $message.text('Error: Could not load player data.').show();
                    setTimeout(() => $message.hide(), 5000);
                    if (debugEnabled) console.error('InterSoccer: Failed to load player data for cancel, index:', index);
                    return;
                }

                $row.removeClass('editing');
                $row.html(`
                    <td class="display-first-name">${player.first_name || 'N/A'}</td>
                    <td class="display-last-name">${player.last_name || 'N/A'}</td>
                    <td class="display-dob">${player.dob || 'N/A'}</td>
                    <td class="display-gender">${player.gender || 'N/A'}</td>
                    <td class="display-avs-number">${player.avs_number || 'N/A'}</td>
                    <td class="display-medical-conditions">${player.medical_conditions ? player.medical_conditions.substring(0, 20) + (player.medical_conditions.length > 20 ? '...' : '') : ''}</td>
                    <td class="actions">
                        <a href="#" class="edit-profile-player" data-index="${index}" aria-label="Edit player ${player.first_name || ''}">Edit</a>
                        <a href="#" class="delete-profile-player" data-index="${index}" aria-label="Delete player ${player.first_name || ''}">Delete</a>
                    </td>
                `);
                $message.text('Edit canceled.').show();
                setTimeout(() => $message.hide(), 5000);
                if (debugEnabled) console.log('InterSoccer: Canceled edit in user profile, index:', index);
            });

            // Delete player
            $profileTable.on('click', '.delete-profile-player', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete this player?')) return;
                const index = $(this).data('player-index');
                const userId = intersoccerPlayer.user_id;
                if (debugEnabled) console.log('InterSoccer: Deleting player in user profile, index:', index, 'userId:', userId);

                $.ajax({
                    url: intersoccerPlayer.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_delete_player',
                        nonce: intersoccerPlayer.nonce,
                        user_id: userId,
                        player_user_id: userId,
                        player_index: index,
                        is_admin: isAdmin ? '1' : '0'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.text(response.data.message).show();
                            setTimeout(() => $message.hide(), 5000);
                            window.location.reload(); // Reload to reflect changes
                            if (debugEnabled) console.log('InterSoccer: Player deleted successfully:', response.data);
                        } else {
                            $message.text(response.data.message || 'Failed to delete player.').show();
                            setTimeout(() => $message.hide(), 5000);
                            if (debugEnabled) console.error('InterSoccer: Failed to delete player:', response.data?.message);
                        }
                    },
                    error: function(xhr) {
                        $message.text('Error: Failed to delete player - ' + (xhr.responseText || 'Unknown error')).show();
                        setTimeout(() => $message.hide(), 5000);
                        if (debugEnabled) console.error('InterSoccer: AJAX error deleting player:', xhr.status, xhr.responseText, 'Response JSON:', JSON.stringify(xhr.responseJSON));
                    }
                });
            });
        });
    </script>
<?php
}

// Hook form to manage-players endpoint
add_action('woocommerce_account_manage-players_endpoint', function () {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Triggered woocommerce_account_manage-players_endpoint');
    }
    echo intersoccer_render_players_form();
});
?>