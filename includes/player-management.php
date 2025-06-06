<?php
/**
 * File: player-management.php
 * Description: Renders the player management form on the WooCommerce My Account page and admin dashboard. Displays players in a table with add/edit/delete functionality, includes admin filters, and supports Elementor widget customization.
 * Author: Jeremy Lee
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Render player management form
function intersoccer_render_players_form($is_admin = false, $settings = [])
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Rendering player management form, is_admin: ' . ($is_admin ? 'true' : 'false'));
    }

    if (!is_user_logged_in()) {
        return '<p>' . esc_html__('Please log in to manage your Attendee(s).', 'intersoccer-player-management') . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">Log in</a> or <a href="' . esc_url(wp_registration_url()) . '">register</a>.</p>';
    }

    $user_id = $is_admin ? null : get_current_user_id();
    if (!$user_id && !$is_admin) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: No user ID found for player management');
        }
        return '<p>' . esc_html__('Error: Unable to load user data.', 'intersoccer-player-management') . '</p>';
    }

    // Elementor widget settings defaults
    $show_first_name = isset($settings['show_first_name']) ? $settings['show_first_name'] === 'yes' : true;
    $show_last_name = isset($settings['show_last_name']) ? $settings['show_last_name'] === 'yes' : true;
    $show_dob = isset($settings['show_dob']) ? $settings['show_dob'] === 'yes' : true;
    $show_gender = isset($settings['show_gender']) ? $settings['show_gender'] === 'yes' : true;
    $show_avs_number = isset($settings['show_avs_number']) ? $settings['show_avs_number'] === 'yes' : true;
    $show_events = isset($settings['show_events']) ? $settings['show_events'] === 'yes' : true;
    $show_add_button = isset($settings['show_add_button']) ? $settings['show_add_button'] === 'yes' : true;
    $show_form_title = isset($settings['show_form_title']) ? $settings['show_form_title'] === 'yes' : true;

    $first_name_heading = !empty($settings['first_name_heading']) ? $settings['first_name_heading'] : __('First Name', 'intersoccer-player-management');
    $last_name_heading = !empty($settings['last_name_heading']) ? $settings['last_name_heading'] : __('Last Name', 'intersoccer-player-management');
    $dob_heading = !empty($settings['dob_heading']) ? $settings['dob_heading'] : __('DOB', 'intersoccer-player-management');
    $gender_heading = !empty($settings['gender_heading']) ? $settings['gender_heading'] : __('Gender', 'intersoccer-player-management');
    $avs_number_heading = !empty($settings['avs_number_heading']) ? $settings['avs_number_heading'] : __('AVS Number', 'intersoccer-player-management');
    $events_heading = !empty($settings['events_heading']) ? $settings['events_heading'] : __('Events', 'intersoccer-player-management');
    $actions_heading = !empty($settings['actions_heading']) ? $settings['actions_heading'] : __('Actions', 'intersoccer-player-management');
    $form_title_text = !empty($settings['form_title_text']) ? $settings['form_title_text'] : ($is_admin ? __('Manage All Players', 'intersoccer-player-management') : __('Manage Your Attendees', 'intersoccer-player-management'));
    $total_players = isset($settings['total_players']) ? (int)$settings['total_players'] : 0;

    // Styling settings for Elementor widget
    $container_background = !empty($settings['container_background']) ? $settings['container_background'] : '';
    $container_text_color = !empty($settings['container_text_color']) ? $settings['container_text_color'] : '';
    $container_padding = !empty($settings['container_padding']) ? $settings['container_padding'] : '';
    $table_border_color = !empty($settings['table_border_color']) ? $settings['table_border_color'] : '#ddd';
    $header_background = !empty($settings['header_background']) ? $settings['header_background'] : '#7ab55c'; // Green to match "Book Now" button

    $players = [];
    $cantons = $settings['cantons'] ?? []; // Populated by admin-players.php
    $age_groups = $settings['age_groups'] ?? [];
    $event_types = $settings['event_types'] ?? [];

    if ($is_admin) {
        $users = get_users(['role__in' => ['customer', 'subscriber']]);
        foreach ($users as $user) {
            $user_players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $billing_state = get_user_meta($user->ID, 'billing_state', true) ?: '';
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

        // Deduplicate players based on first_name, last_name, and dob
        $unique_players = [];
        $seen = [];
        foreach ($players as $index => $player) {
            $key = $player['first_name'] . '|' . $player['last_name'] . '|' . $player['dob'];
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

    // Enqueue scripts
    wp_enqueue_script(
        'intersoccer-player-management-core',
        plugin_dir_url(__FILE__) . '../js/player-management-core.js',
        ['jquery'],
        '1.0.' . time(),
        true
    );
    wp_enqueue_script(
        'intersoccer-player-management-filters',
        plugin_dir_url(__FILE__) . '../js/player-management-filters.js',
        ['intersoccer-player-management-core', 'select2'],
        '1.0.' . time(),
        true
    );
    wp_enqueue_script(
        'intersoccer-player-management-actions',
        plugin_dir_url(__FILE__) . '../js/player-management-actions.js',
        ['intersoccer-player-management-filters'],
        '1.0.' . time(),
        true
    );

    // Enqueue Select2 for admin filters
    if ($is_admin) {
        wp_enqueue_script('select2', admin_url('js/select2/select2.min.js'), ['jquery'], '4.0.13', true);
        wp_enqueue_style('select2', admin_url('css/select2.min.css'), [], '4.0.13');
    }

    wp_localize_script(
        'intersoccer-player-management-core',
        'intersoccerPlayer',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_player_nonce'),
            'user_id' => $user_id ?: 0,
            'is_admin' => $is_admin ? '1' : '0',
            'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
        ]
    );

    // Enqueue stylesheet
    wp_enqueue_style(
        'intersoccer-player-management',
        plugin_dir_url(__FILE__) . '../css/player-management.css',
        [],
        '1.0.' . time()
    );

    // Add inline CSS for dynamic Elementor widget styles
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
    // Add CSS for Select2 filters and total players display
    if ($is_admin) {
        $inline_css .= '
            .intersoccer-filters { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 15px; }
            .intersoccer-filters label { margin-right: 5px; }
            .intersoccer-filters .select2-container { min-width: 200px; }
            .intersoccer-filters .select2-selection--multiple { min-height: 30px; }
            .intersoccer-filters .select2-selection--single { height: 30px; }
            .intersoccer-filters .select2-selection__rendered { line-height: 28px; }
            .total-players { margin-bottom: 10px; font-size: 16px; color: #333; }
        ';
    }
    if (!empty($inline_css)) {
        wp_add_inline_style('intersoccer-player-management', $inline_css);
    }

    ob_start();
?>
    <div class="intersoccer-player-management" role="region" aria-label="<?php esc_attr_e('Attendee Management Dashboard', 'intersoccer-player-management'); ?>">
        <?php if ($show_form_title) : ?>
            <h2 class="intersoccer-form-title"><?php echo esc_html($form_title_text); ?></h2>
        <?php endif; ?>
        <div class="intersoccer-message" style="display: none;" role="alert" aria-live="polite"></div>

        <?php if ($is_admin && $total_players > 0) : ?>
            <div class="total-players">
                <?php printf(esc_html__('Total Players: %d', 'intersoccer-player-management'), $total_players); ?>
            </div>
        <?php endif; ?>

        <?php if ($is_admin) : ?>
            <div class="intersoccer-filters">
                <label for="filter-canton"><?php esc_html_e('Filter by Canton', 'intersoccer-player-management'); ?></label>
                <select id="filter-canton" class="filter-select select2" aria-label="<?php esc_attr_e('Filter players by canton', 'intersoccer-player-management'); ?>">
                    <option value=""><?php esc_html_e('All Cantons', 'intersoccer-player-management'); ?></option>
                    <?php foreach ($cantons as $canton) : ?>
                        <option value="<?php echo esc_attr($canton); ?>"><?php echo esc_html($canton); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-age-group"><?php esc_html_e('Filter by Age Group', 'intersoccer-player-management'); ?></label>
                <select id="filter-age-group" class="filter-select select2" multiple aria-label="<?php esc_attr_e('Filter players by age group (multiple selection)', 'intersoccer-player-management'); ?>" aria-multiselectable="true">
                    <option value=""><?php esc_html_e('All Age Groups', 'intersoccer-player-management'); ?></option>
                    <?php foreach ($age_groups as $age_group) : ?>
                        <option value="<?php echo esc_attr($age_group); ?>"><?php echo esc_html($age_group); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-event-type"><?php esc_html_e('Filter by Event Type', 'intersoccer-player-management'); ?></label>
                <select id="filter-event-type" class="filter-select select2" aria-label="<?php esc_attr_e('Filter players by event type', 'intersoccer-player-management'); ?>">
                    <option value=""><?php esc_html_e('All Event Types', 'intersoccer-player-management'); ?></option>
                    <?php foreach ($event_types as $event_type) : ?>
                        <option value="<?php echo esc_attr($event_type); ?>"><?php echo esc_html($event_type); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-gender"><?php esc_html_e('Filter by Gender', 'intersoccer-player-management'); ?></label>
                <select id="filter-gender" class="filter-select select2" aria-label="<?php esc_attr_e('Filter players by gender', 'intersoccer-player-management'); ?>">
                    <option value=""><?php esc_html_e('All Genders', 'intersoccer-player-management'); ?></option>
                    <option value="male"><?php esc_html_e('Male', 'intersoccer-player-management'); ?></option>
                    <option value="female"><?php esc_html_e('Female', 'intersoccer-player-management'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'intersoccer-player-management'); ?></option>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show_add_button) : ?>
            <div class="intersoccer-player-actions">
                <a href="#" class="toggle-add-player" aria-label="<?php esc_attr_e('Add New Player', 'intersoccer-player-management'); ?>" aria-controls="add-player-section">
                    <?php esc_html_e('Add', 'intersoccer-player-management'); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="intersoccer-table-wrapper" role="region" aria-label="<?php esc_attr_e('Attendee Table', 'intersoccer-player-management'); ?>">
            <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php esc_attr_e('List of attendees', 'intersoccer-player-management'); ?>">
                <thead>
                    <tr>
                        <?php if ($is_admin) : ?>
                            <th scope="col"><?php esc_html_e('User ID', 'intersoccer-player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Canton', 'intersoccer-player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('City', 'intersoccer-player-management'); ?></th>
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
                            <th scope="col"><?php esc_html_e('Medical Conditions', 'intersoccer-player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Creation Date', 'intersoccer-player-management'); ?></th>
                            <th scope="col"><?php esc_html_e('Past Events', 'intersoccer-player-management'); ?></th>
                        <?php endif; ?>
                        <th scope="col" class="actions"><?php echo esc_html($actions_heading); ?></th>
                    </tr>
                </thead>
                <tbody id="player-table">
                    <?php if (empty($players)) : ?>
                        <tr class="no-players">
                            <td colspan="<?php echo ($is_admin ? 5 : 0) + ($show_first_name ? 1 : 0) + ($show_last_name ? 1 : 0) + ($show_dob ? 1 : 0) + ($show_gender ? 1 : 0) + ($show_avs_number ? 1 : 0) + ($show_events ? 1 : 0) + ($is_admin ? 2 : 0) + 1; ?>">
                                <?php esc_html_e('No attendees added yet.', 'intersoccer-player-management'); ?>
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
                                data-event-age-groups="<?php echo esc_attr(implode(',', $player['event_age_groups'] ?? [])); ?>"
                                data-event-types="<?php echo esc_attr(implode(',', $player['event_types'] ?? [])); ?>">
                                <?php if ($is_admin) : ?>
                                    <td class="display-user-id">
                                        <a href="<?php echo esc_url(get_edit_user_link($player['user_id'])); ?>" aria-label="<?php esc_attr_e('Edit user profile', 'intersoccer-player-management'); ?>">
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
                                            <?php esc_html_e('No past events.', 'intersoccer-player-management'); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="actions">
                                    <a href="#" class="edit-player" data-index="<?php echo esc_attr($is_admin ? $player['player_index'] : $index); ?>" data-user-id="<?php echo esc_attr($player['user_id'] ?? $user_id); ?>" aria-label="<?php esc_attr_e('Edit player', 'intersoccer-player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>" aria-expanded="false">
                                        <?php esc_html_e('Edit', 'intersoccer-player-management'); ?>
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
                                    <input type="number" id="player_user_id" name="player_user_id" required aria-required="true" min="1">
                                    <span class="error-message" style="display: none;"></span>
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
                                        <option value=""><?php esc_html_e('Day', 'intersoccer-player-management'); ?></option>
                                        <?php for ($day = 1; $day <= 31; $day++) : ?>
                                            <option value="<?php echo esc_attr(sprintf('%02d', $day)); ?>"><?php echo esc_html($day); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select id="player_dob_month" name="player_dob_month" required aria-required="true">
                                        <option value=""><?php esc_html_e('Month', 'intersoccer-player-management'); ?></option>
                                        <option value="01"><?php esc_html_e('January', 'intersoccer-player-management'); ?></option>
                                        <option value="02"><?php esc_html_e('February', 'intersoccer-player-management'); ?></option>
                                        <option value="03"><?php esc_html_e('March', 'intersoccer-player-management'); ?></option>
                                        <option value="04"><?php esc_html_e('April', 'intersoccer-player-management'); ?></option>
                                        <option value="05"><?php esc_html_e('May', 'intersoccer-player-management'); ?></option>
                                        <option value="06"><?php esc_html_e('June', 'intersoccer-player-management'); ?></option>
                                        <option value="07"><?php esc_html_e('July', 'intersoccer-player-management'); ?></option>
                                        <option value="08"><?php esc_html_e('August', 'intersoccer-player-management'); ?></option>
                                        <option value="09"><?php esc_html_e('September', 'intersoccer-player-management'); ?></option>
                                        <option value="10"><?php esc_html_e('October', 'intersoccer-player-management'); ?></option>
                                        <option value="11"><?php esc_html_e('November', 'intersoccer-player-management'); ?></option>
                                        <option value="12"><?php esc_html_e('December', 'intersoccer-player-management'); ?></option>
                                    </select>
                                    <select id="player_dob_year" name="player_dob_year" required aria-required="true">
                                        <option value=""><?php esc_html_e('Year', 'intersoccer-player-management'); ?></option>
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
                                        <option value=""><?php esc_html_e('Select Gender', 'intersoccer-player-management'); ?></option>
                                        <option value="male"><?php esc_html_e('Male', 'intersoccer-player-management'); ?></option>
                                        <option value="female"><?php esc_html_e('Female', 'intersoccer-player-management'); ?></option>
                                        <option value="other"><?php esc_html_e('Other', 'intersoccer-player-management'); ?></option>
                                    </select>
                                    <span class="error-message" style="display: none;"></span>
                                </td>
                            <?php endif; ?>
                            <?php if ($show_avs_number) : ?>
                                <td>
                                    <input type="text" id="player_avs_number" name="player_avs_number" required aria-required="true" maxlength="16">
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
                                    <span class="display-past-events"><?php esc_html_e('No past events.', 'intersoccer-player-management'); ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="actions">
                                <a href="#" class="player-submit" aria-label="<?php esc_attr_e('Save Player', 'intersoccer-player-management'); ?>">
                                    <?php esc_html_e('Save', 'intersoccer-player-management'); ?>
                                    <span class="spinner" style="display: none;"></span>
                                </a>
                                <a href="#" class="cancel-add" aria-label="<?php esc_attr_e('Cancel Add', 'intersoccer-player-management'); ?>">
                                    <?php esc_html_e('Cancel', 'intersoccer-player-management'); ?>
                                </a>
                            </td>
                        </tr>
                        <tr class="add-player-section add-player-medical">
                            <td colspan="<?php echo ($is_admin ? 5 : 0) + ($show_first_name ? 1 : 0) + ($show_last_name ? 1 : 0) + ($show_dob ? 1 : 0) + ($show_gender ? 1 : 0) + ($show_avs_number ? 1 : 0) + ($show_events ? 1 : 0) + ($is_admin ? 2 : 0) + 1; ?>">
                                <label for="player_medical"><?php esc_html_e('Medical Conditions:', 'intersoccer-player-management'); ?></label>
                                <textarea id="player_medical" name="player_medical" maxlength="500" aria-describedby="medical-instructions"></textarea>
                                <span id="medical-instructions" class="screen-reader-text"><?php esc_html_e('Optional field for medical conditions.', 'intersoccer-player-management'); ?></span>
                                <span class="error-message" style="display: none;"></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Hook form to manage-players endpoint
add_action('woocommerce_account_manage-players_endpoint', function () {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Triggered woocommerce_account_manage-players_endpoint');
    }
    echo intersoccer_render_players_form();
});
?>
