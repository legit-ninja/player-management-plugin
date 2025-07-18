<?php
/**
 * File: user-profile-players.php
 * Description: Adds player management section to WordPress user profile for admin UI and frontend My Account page.
 */

if (!defined('ABSPATH')) exit;

function intersoccer_add_user_profile_players($user) {
    if (!current_user_can('edit_user', $user->ID)) return;

    $players = get_user_meta($user->ID, 'intersoccer_players', true);
    if (!is_array($players)) {
        $players = $players ? (array) maybe_unserialize($players) : [];
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Rendering user profile players for user ' . $user->ID . ', players: ' . json_encode($players));
        error_log('InterSoccer: Raw metadata for user ' . $user->ID . ': ' . print_r(get_user_meta($user->ID, 'intersoccer_players', true), true));
        error_log('InterSoccer: Pre-localize players for user ' . $user->ID . ': ' . json_encode($players));
        error_log('InterSoccer: User role for user ' . $user->ID . ': ' . wp_get_current_user()->roles[0]);
    }

    $is_admin = current_user_can('edit_users');
    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
    // Corrected paths to point to plugin root directory from includes/
    wp_enqueue_script('intersoccer-player-management-js', plugins_url('../js/player-management.js', __FILE__), ['jquery', 'flatpickr'], '1.0.12', true);
    // Enqueue player-management-actions.js for frontend-like form handling
    wp_enqueue_script('intersoccer-player-management-actions-js', plugins_url('../js/player-management-actions.js', __FILE__), ['jquery', 'intersoccer-player-management-js'], '1.0.12', true);
    wp_enqueue_style('intersoccer-player-management-css', plugins_url('../css/player-management.css', __FILE__), [], '1.0.12');

    // Only enqueue admin JS if explicitly needed; skip for user profile to avoid inline edit conflicts
    if ($is_admin && basename($_SERVER['SCRIPT_NAME']) !== 'profile.php' && basename($_SERVER['SCRIPT_NAME']) !== 'user-edit.php') {
        wp_enqueue_script('intersoccer-admin-core-js', plugins_url('../js/admin-core.js', __FILE__), ['jquery', 'intersoccer-player-management-js'], '1.0.12', true);
        wp_enqueue_script('intersoccer-admin-actions-js', plugins_url('../js/admin-actions.js', __FILE__), ['jquery', 'intersoccer-player-management-js', 'intersoccer-admin-core-js'], '1.0.12', true);
    }

    $localize_data = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('intersoccer_player_nonce'),
        'user_id' => $user->ID,
        'is_admin' => $is_admin ? '1' : '0',
        'nonce_refresh_url' => admin_url('admin-ajax.php?action=intersoccer_refresh_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
        'preload_players' => $players ?: [],
        'server_time' => current_time('mysql'),
    ];
    wp_localize_script('intersoccer-player-management-js', 'intersoccerPlayer', $localize_data);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Localized intersoccerPlayer data for user profile: ' . json_encode($localize_data));
        error_log('InterSoccer: Nonce generated for user ' . $user->ID . ': ' . $localize_data['nonce']);
    }

    $colspan = $is_admin ? 10 : 6; // Adjust colspan for admin (10 columns) vs non-admin (6 columns)
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
                    <?php if ($is_admin): ?>
                        <th><?php esc_html_e('User ID', 'player-management'); ?></th>
                        <th><?php esc_html_e('Canton', 'player-management'); ?></th>
                        <th><?php esc_html_e('City', 'player-management'); ?></th>
                        <th><?php esc_html_e('Actions', 'player-management'); ?></th>
                    <?php else: ?>
                        <th><?php esc_html_e('Events', 'player-management'); ?></th>
                        <th><?php esc_html_e('Actions', 'player-management'); ?></th>
                    <?php endif; ?>
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
                            <td data-label="First Name"><?php echo esc_html($player['first_name'] ?? 'N/A'); ?></td>
                            <td data-label="Last Name"><?php echo esc_html($player['last_name'] ?? 'N/A'); ?></td>
                            <td data-label="DOB"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                            <td data-label="Gender"><?php echo esc_html($player['gender'] ?? 'N/A'); ?></td>
                            <td data-label="AVS Number"><?php echo esc_html($player['avs_number'] ?? 'N/A'); ?></td>
                            <td data-label="Medical Conditions"><?php echo esc_html(substr($player['medical_conditions'] ?? '', 0, 20) . (strlen($player['medical_conditions'] ?? '') > 20 ? '...' : '')); ?></td>
                            <?php if ($is_admin): ?>
                                <td data-label="User ID"><?php echo esc_html($user->ID); ?></td>
                                <td data-label="Canton"><?php echo esc_html($player['canton'] ?? 'N/A'); ?></td>
                                <td data-label="City"><?php echo esc_html($player['city'] ?? 'N/A'); ?></td>
                                <td class="actions" data-label="Actions">
                                    <a href="#" class="edit-player" data-index="<?php echo esc_attr($index); ?>">Edit</a>
                                    <a href="#" class="delete-player" data-index="<?php echo esc_attr($index); ?>">Delete</a>
                                </td>
                            <?php else: ?>
                                <td data-label="Events"><?php echo esc_html($player['event_count'] ?? 0); ?></td>
                                <td class="actions" data-label="Actions">
                                    <a href="#" class="edit-player" data-index="<?php echo esc_attr($index); ?>">Edit</a>
                                    <a href="#" class="delete-player" data-index="<?php echo esc_attr($index); ?>">Delete</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="player-form" style="display: none; margin-top: 20px;">
            <input type="hidden" name="player_index" value="">
            <input type="hidden" name="player_user_id" value="<?php echo esc_attr($user->ID); ?>">
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
                <label for="player_dob"><?php esc_html_e('Date of Birth', 'player-management'); ?></label>
                <input type="text" id="player_dob" name="player_dob" class="date-picker" required maxlength="10">
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_gender"><?php esc_html_e('Gender', 'player-management'); ?></label>
                <select id="player_gender" name="player_gender" required>
                    <option value=""><?php esc_html_e('Select', 'player-management'); ?></option>
                    <option value="male"><?php esc_html_e('Male', 'player-management'); ?></option>
                    <option value="female"><?php esc_html_e('Female', 'player-management'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'player-management'); ?></option>
                </select>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row">
                <label for="player_avs_number"><?php esc_html_e('AVS Number', 'player-management'); ?></label>
                <input type="text" id="player_avs_number" name="player_avs_number" maxlength="50">
                <span class="avs-instruction"><?php esc_html_e('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', 'player-management'); ?></span>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-row add-player-medical">
                <label for="player_medical"><?php esc_html_e('Medical Conditions', 'player-management'); ?></label>
                <textarea id="player_medical" name="player_medical" maxlength="500"></textarea>
                <span class="error-message" style="display: none;"></span>
            </div>
            <div class="form-actions">
                <a href="#" class="player-submit button"><?php esc_html_e('Save', 'player-management'); ?></a>
                <a href="#" class="cancel-add button"><?php esc_html_e('Cancel', 'player-management'); ?></a>
            </div>
        </div>
    </div>
<?php
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('InterSoccer: Form rendered for user ' . $user->ID);
    }
}
add_action('show_user_profile', 'intersoccer_add_user_profile_players');
add_action('edit_user_profile', 'intersoccer_add_user_profile_players');
// Add support for WooCommerce My Account endpoint
add_action('woocommerce_account_manage-players_endpoint', 'intersoccer_add_user_profile_players');
?>