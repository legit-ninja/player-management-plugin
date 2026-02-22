<?php
/**
 * Plugin Name: InterSoccer Player Management
 * Plugin URI: https://github.com/legit-ninja/player-management-plugin
 * Description: Manages players for InterSoccer events, including registration, metadata storage (e.g., DOB, gender, medical/dietary), and integration with WooCommerce orders for rosters.
 * Version: 1.3.130
 * Author: Jeremy Lee
 * Author URI: https://underdogunlimited.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: player-management
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to translate gender value for display
 * Stores gender as English in database, displays in user's language
 */
if (!function_exists('intersoccer_translate_gender')) {
    function intersoccer_translate_gender($gender_value) {
        if (empty($gender_value) || $gender_value === 'N/A') {
            return 'N/A';
        }
        
        // Normalize to lowercase for comparison
        $gender_normalized = strtolower($gender_value);
        
        // Translate based on stored value
        switch ($gender_normalized) {
            case 'male':
                return __('Male', 'player-management');
            case 'female':
                return __('Female', 'player-management');
            case 'other':
                return __('Other', 'player-management');
            default:
                return $gender_value; // Return as-is if not recognized
        }
    }
}

// Helper function to count events for a player
if (!function_exists('intersoccer_get_player_event_count')) {
    function intersoccer_get_player_event_count($user_id, $player_index) {
        $count = 0;
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        
        if (!isset($players[$player_index])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Player not found at index ' . $player_index . ' for user ' . $user_id);
            }
            return 0;
        }
        
        $player = $players[$player_index];
        $full_name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
        
        if (empty($full_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Empty full name for player index ' . $player_index . ' user ' . $user_id);
            }
            return 0;
        }
        
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'], // Expanded statuses
            'limit' => -1
        ]);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Counting events for user ' . $user_id . ', player_index: ' . $player_index . ', full_name: "' . $full_name . '"');
            error_log('InterSoccer: Found ' . count($orders) . ' orders for user ' . $user_id);
        }
        
        foreach ($orders as $order) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Order ID ' . $order->get_id() . ', Status: ' . $order->get_status());
            }
            
            foreach ($order->get_items() as $item_id => $item) {
                // Primary: Match by name
                $attendee = trim($item->get_meta('Assigned Attendee') ?? '');
                
                // Fallback: Check index if name doesn't match
                $player_index_meta = $item->get_meta('intersoccer_player_index');
                
                $name_match = ($attendee === $full_name);
                $index_match = ($player_index_meta == $player_index); // Loose comparison
                
                if ($name_match || $index_match) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer: Match found for item ID ' . $item_id . 
                                ' - Name: "' . $attendee . '" (match: ' . ($name_match ? 'yes' : 'no') . 
                                '), Index: ' . print_r($player_index_meta, true) . ' (match: ' . ($index_match ? 'yes' : 'no') . ')');
                    }
                    $count++;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer: No match for item ID ' . $item_id . 
                                ' - Attendee: "' . $attendee . '", Index: ' . print_r($player_index_meta, true));
                    }
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: Final event count for player_index ' . $player_index . ' ("' . $full_name . '"): ' . $count);
        }
        
        return $count;
    }
}

// Render player management form
function intersoccer_render_players_form($is_admin = false, $settings = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $safe_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown'));
        error_log('InterSoccer: Rendering player management form, is_admin: ' . ($is_admin ? 'true' : 'false') . ', endpoint: ' . $safe_uri);
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

        // Deduplicate and compute event_count
        $unique_players = [];
        $seen = [];
        foreach ($players as $index => $player) {
            $key = strtolower($player['first_name']) . '|' . strtolower($player['last_name']) . '|' . $player['dob'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $player['event_count'] = intersoccer_get_player_event_count($user_id, $index);
                $unique_players[$index] = $player;
            }
        }
        $players = $unique_players;

        // Compute event_count for each player (total bookings in completed/processing orders)
        foreach ($players as $index => &$player) {
            $player['event_count'] = intersoccer_get_player_event_count($user_id, $index);
        }
        unset($player); // Unset reference to avoid issues
    }

    // Calculate colspan for table rows
    $colspan = ($is_admin ? 3 : 0) + 1 + ($show_dob ? 1 : 0) + ($show_gender ? 1 : 0) + ($show_avs_number ? 1 : 0) + ($show_events ? 1 : 0) + ($is_admin ? 3 : 0) + 1;

    ob_start();
?>
    <div class="intersoccer-player-management" role="region" aria-label="<?php esc_attr_e('Attendee Management Dashboard', 'player-management'); ?>">
        <form accept-charset="UTF-8" style="width: 100%; max-width: 100%;">
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
                    <a href="#" class="toggle-add-player" aria-label="<?php esc_attr_e('Add New Player', 'player-management'); ?>" aria-controls="player-form">
                        <?php esc_html_e('Add', 'player-management'); ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="intersoccer-table-wrapper" role="region" aria-label="<?php esc_attr_e('Attendee Table', 'player-management'); ?>">
                <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php esc_attr_e('List of attendees', 'player-management'); ?>" style="width: 100%; max-width: 100%;">
                    <thead>
                        <tr>
                            <?php if ($is_admin) : ?>
                                <th scope="col"><?php esc_html_e('User ID', 'player-management'); ?></th>
                                <th scope="col"><?php esc_html_e('Canton', 'player-management'); ?></th>
                                <th scope="col"><?php esc_html_e('City', 'player-management'); ?></th>
                            <?php endif; ?>
                            <th scope="col"><?php esc_html_e('Name', 'player-management'); ?></th>
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
                                    data-event-types="<?php echo esc_attr(implode(',', $player['event_types'] ?? [])); ?>"
                                    data-medical-conditions="<?php echo esc_attr($player['medical_conditions'] ?? ''); ?>">
                                    <?php if ($is_admin) : ?>
                                        <td class="display-user-id" data-label="<?php esc_attr_e('User ID', 'player-management'); ?>">
                                            <a href="<?php echo esc_url(get_edit_user_link($player['user_id'])); ?>" aria-label="<?php esc_attr_e('Edit user profile', 'player-management'); ?>">
                                                <?php echo esc_html($player['user_id'] ?? 'N/A'); ?>
                                            </a>
                                        </td>
                                        <td class="display-canton" data-label="<?php esc_attr_e('Canton', 'player-management'); ?>"><?php echo esc_html($player['canton'] ?: 'N/A'); ?></td>
                                        <td class="display-city" data-label="<?php esc_attr_e('City', 'player-management'); ?>"><?php echo esc_html($player['city'] ?: 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td class="display-name" data-label="<?php esc_attr_e('Name', 'player-management'); ?>"><?php echo esc_html(($player['first_name'] ?? 'N/A') . ' ' . ($player['last_name'] ?? '')); ?></td>
                                    <?php if ($show_dob) : ?>
                                        <td class="display-dob" data-label="<?php echo esc_attr($dob_heading); ?>"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_gender) : ?>
                                        <td class="display-gender" data-label="<?php echo esc_attr($gender_heading); ?>"><?php echo esc_html(intersoccer_translate_gender($player['gender'] ?? 'N/A')); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_avs_number) : ?>
                                        <td class="display-avs-number" data-label="<?php echo esc_attr($avs_number_heading); ?>"><?php echo esc_html($player['avs_number'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_events) : ?>
                                        <td class="display-event-count" data-label="<?php echo esc_attr($events_heading); ?>"><?php echo esc_html($player['event_count'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if ($is_admin) : ?>
                                        <td class="display-medical-conditions" data-label="<?php esc_attr_e('Medical Conditions', 'player-management'); ?>"><?php echo esc_html($player['medical_conditions_display'] ?? ''); ?></td>
                                        <td class="display-creation-date" data-label="<?php esc_attr_e('Creation Date', 'player-management'); ?>"><?php echo esc_html($player['creation_date'] ?? 'N/A'); ?></td>
                                        <td class="display-past-events" data-label="<?php esc_attr_e('Past Events', 'player-management'); ?>">
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
                                    <td class="actions" data-label="<?php echo esc_attr($actions_heading); ?>">
                                        <a href="#" class="edit-player" data-index="<?php echo esc_attr($is_admin ? $player['player_index'] : $index); ?>" data-user-id="<?php echo esc_attr($player['user_id'] ?? $user_id); ?>" aria-label="<?php esc_attr_e('Edit player', 'player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>" aria-expanded="false">
                                            <?php esc_html_e('Edit', 'player-management'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Separate Add/Edit Form -->
            <?php if ($show_add_button) : ?>
                <div id="player-form" style="display: none; width: 100%; max-width: 100%;">
                    <div class="form-row">
                        <label for="player_first_name"><?php echo esc_html($first_name_heading); ?></label>
                        <input type="text" id="player_first_name" name="player_first_name" required aria-required="true" maxlength="50">
                        <span class="error-message" style="display: none;"></span>
                    </div>
                    <div class="form-row">
                        <label for="player_last_name"><?php echo esc_html($last_name_heading); ?></label>
                        <input type="text" id="player_last_name" name="player_last_name" required aria-required="true" maxlength="50">
                        <span class="error-message" style="display: none;"></span>
                    </div>
                    <?php if ($show_dob) : ?>
                        <div class="form-row">
                            <label for="player_dob"><?php esc_html_e('Date of Birth', 'player-management'); ?></label>
                            <input type="date" id="player_dob" name="player_dob" required aria-required="true" min="<?php echo date('Y-m-d', strtotime('-13 years')); ?>" max="<?php echo date('Y-m-d', strtotime('-3 years')); ?>">
                            <span class="error-message" style="display: none;"></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_gender) : ?>
                        <div class="form-row">
                            <label for="player_gender"><?php echo esc_html($gender_heading); ?></label>
                            <select id="player_gender" name="player_gender" required aria-required="true">
                                <option value=""><?php esc_html_e('Select Gender', 'player-management'); ?></option>
                                <option value="male"><?php esc_html_e('Male', 'player-management'); ?></option>
                                <option value="female"><?php esc_html_e('Female', 'player-management'); ?></option>
                                <option value="other"><?php esc_html_e('Other', 'player-management'); ?></option>
                            </select>
                            <span class="error-message" style="display: none;"></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_avs_number) : ?>
                        <div class="form-row">
                            <label for="player_avs_number"><?php echo esc_html($avs_number_heading); ?></label>
                            <input type="text" id="player_avs_number" name="player_avs_number" aria-required="true" maxlength="50">
                            <div class="avs-instruction"><?php esc_html_e('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', 'player-management'); ?></div>
                            <span class="error-message" style="display: none;"></span>
                        </div>
                    <?php endif; ?>
                    <div class="form-row">
                        <label for="player_medical"><?php esc_html_e('Medical Conditions:', 'player-management'); ?></label>
                        <textarea id="player_medical" name="player_medical" maxlength="500" aria-describedby="medical-instructions"></textarea>
                        <span id="medical-instructions" class="screen-reader-text"><?php esc_html_e('Optional field for medical conditions.', 'player-management'); ?></span>
                        <span class="error-message" style="display: none;"></span>
                    </div>
                    <div class="form-actions">
                        <a href="#" id="save-player" aria-label="<?php esc_attr_e('Save Player', 'player-management'); ?>">
                            <?php esc_html_e('Save', 'player-management'); ?>
                            <span class="spinner" style="display: none;"></span>
                        </a>
                        <a href="#" id="cancel-player" aria-label="<?php esc_attr_e('Cancel', 'player-management'); ?>">
                            <?php esc_html_e('Cancel', 'player-management'); ?>
                        </a>
                        <input type="hidden" id="player_index" name="player_index" value="-1">
                        <input type="hidden" id="player_user_id" name="player_user_id" value="<?php echo esc_attr($user_id); ?>">
                    </div>
                </div>
            <?php endif; ?>
        </form>
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
        error_log('InterSoccer: Localizing intersoccerPlayer data for user profile, user: ' . $user->ID . ', player count: ' . count($players));
    }
    wp_localize_script(
        'intersoccer-player-management-js',
        'intersoccerPlayer',
        $localize_data
    );

    $colspan = 7; // For user profile table: First Name, Last Name, DOB, Gender, AVS Number, Medical Conditions, Actions
?>
    <div class="profile-players intersoccer-player-management">
        <h2><?php esc_html_e('InterSoccer Players', 'player-management'); ?></h2>
        <div class="intersoccer-message" style="display: none;" role="alert" aria-live="polite"></div>
        <a href="#" class="toggle-add-player button"><?php esc_html_e('Add New Player', 'player-management'); ?></a>
        <table class="wp-list-table widefat fixed striped" id="player-table">
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
            <tbody>
                <?php if (empty($players)): ?>
                    <tr class="no-players"><td colspan="<?php echo esc_attr($colspan); ?>"><?php esc_html_e('No players added yet.', 'player-management'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($players as $index => $player): ?>
                        <tr data-player-index="<?php echo esc_attr($index); ?>" 
                            data-user-id="<?php echo esc_attr($user->ID); ?>"
                            data-first-name="<?php echo esc_attr($player['first_name'] ?? 'N/A'); ?>"
                            data-last-name="<?php echo esc_attr($player['last_name'] ?? 'N/A'); ?>"
                            data-dob="<?php echo esc_attr($player['dob'] ?? 'N/A'); ?>"
                            data-gender="<?php echo esc_attr($player['gender'] ?? 'N/A'); ?>"
                            data-avs-number="<?php echo esc_attr($player['avs_number'] ?? 'N/A'); ?>"
                            data-medical-conditions="<?php echo esc_attr($player['medical_conditions'] ?? ''); ?>">
                            <td class="display-first-name"><?php echo esc_html($player['first_name'] ?? 'N/A'); ?></td>
                            <td class="display-last-name"><?php echo esc_html($player['last_name'] ?? 'N/A'); ?></td>
                            <td class="display-dob"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                            <td class="display-gender"><?php echo esc_html(intersoccer_translate_gender($player['gender'] ?? 'N/A')); ?></td>
                            <td class="display-avs-number"><?php echo esc_html($player['avs_number'] ?? 'N/A'); ?></td>
                            <td class="display-medical-conditions"><?php echo esc_html(substr($player['medical_conditions'] ?? '', 0, 20) . (strlen($player['medical_conditions'] ?? '') > 20 ? '...' : '')); ?></td>
                            <td class="actions">
                                <a href="#" class="edit-player" data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Edit', 'player-management'); ?></a>
                                <a href="#" class="delete-player" data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Delete', 'player-management'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- Add form (match classes for core.js) -->
        <div class="add-player-section" style="display: none; margin-top: 20px;">
            <div class="form-row">
                <label for="player_first_name"><?php esc_html_e('First Name', 'player-management'); ?></label>
                <input type="text" id="player_first_name" name="player_first_name" required maxlength="50">
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_last_name"><?php esc_html_e('Last Name', 'player-management'); ?></label>
                <input type="text" id="player_last_name" name="player_last_name" required maxlength="50">
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_dob_day"><?php esc_html_e('Date of Birth', 'player-management'); ?></label>
                <select name="player_dob_day" required>
                    <option value=""><?php esc_html_e('Day', 'player-management'); ?></option>
                    <?php for ($day = 1; $day <= 31; $day++) echo '<option value="' . str_pad($day, 2, '0', STR_PAD_LEFT) . '">' . $day . '</option>'; ?>
                </select>
                <select name="player_dob_month" required>
                    <option value=""><?php esc_html_e('Month', 'player-management'); ?></option>
                    <?php for ($m = 1; $m <= 12; $m++) echo '<option value="' . str_pad($m, 2, '0', STR_PAD_LEFT) . '">' . date('F', mktime(0,0,0,$m,1)) . '</option>'; ?>
                </select>
                <select name="player_dob_year" required>
                    <option value=""><?php esc_html_e('Year', 'player-management'); ?></option>
                    <?php for ($y = date('Y') - 13; $y >= date('Y') - 3; $y--) echo '<option value="' . $y . '">' . $y . '</option>'; ?>
                </select>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_gender"><?php esc_html_e('Gender', 'player-management'); ?></label>
                <select name="player_gender" required>
                    <option value=""><?php esc_html_e('Select', 'player-management'); ?></option>
                    <option value="male"><?php esc_html_e('Male', 'player-management'); ?></option>
                    <option value="female"><?php esc_html_e('Female', 'player-management'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'player-management'); ?></option>
                </select>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_avs_number"><?php esc_html_e('AVS Number', 'player-management'); ?></label>
                <input type="text" name="player_avs_number" maxlength="50">
                <span class="avs-instruction"><?php esc_html_e('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', 'player-management'); ?></span>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_medical"><?php esc_html_e('Medical Conditions', 'player-management'); ?></label>
                <textarea name="player_medical" maxlength="500"></textarea>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-actions">
                <a href="#" class="player-submit button"><?php esc_html_e('Save', 'player-management'); ?></a>
                <a href="#" class="cancel-add button"><?php esc_html_e('Cancel', 'player-management'); ?></a>
            </div>
        </div>
    </div>
<?php
}

/**
 * NOTE: Endpoint content rendering is now handled in player-management.php
 * for all language versions (manage-players, gerer-participants, teilnehmer-verwalten).
 * This duplicate hook has been removed to prevent double rendering.
 * See: player-management.php lines 141-175
 */

// Display assigned attendee in frontend order details (My Account > Orders)
function intersoccer_display_order_item_attendee( $item_id, $item, $order, $plain_text = false ) {
    $attendee = $item->get_meta( 'Assigned Attendee' );
    if ( $attendee ) {
        echo '<p><strong>' . esc_html__( 'Assigned Attendee', 'player-management' ) . ':</strong> ' . esc_html( $attendee ) . '</p>';
    }
}
add_action( 'woocommerce_order_item_meta_end', 'intersoccer_display_order_item_attendee', 10, 3 );
?>