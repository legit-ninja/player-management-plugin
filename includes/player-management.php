<?php

/**
 * File: player-management.php
 * Description: Renders the player management form on the WooCommerce My Account page at the /my-account/manage-players/ endpoint. Displays existing players at the top with Quick Edit feature and text hyperlinks for Edit/Delete, and an Add Player form below. Supports AJAX for dynamic updates, with a date picker for Date of Birth.
 * Dependencies: ajax-handlers.php (for AJAX operations)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Render player management form
function intersoccer_render_players_form()
{
    error_log('InterSoccer: Rendering player management form');
    if (!is_user_logged_in()) {
        return '<p>' . esc_html__('Please log in to manage your players.', 'intersoccer-player-management') . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">Log in</a> or <a href="' . esc_url(wp_registration_url()) . '">register</a>.</p>';
    }

    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
    ob_start();
?>
    <div class="intersoccer-player-management" role="region" aria-label="<?php esc_attr_e('Player Management Dashboard', 'intersoccer-player-management'); ?>">
        <h2><?php esc_html_e('Manage Your Players', 'intersoccer-player-management'); ?></h2>
        <p class="message" style="display: none;" aria-live="polite"></p>
        <h3><?php esc_html_e('Your Players', 'intersoccer-player-management'); ?></h3>
        <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php esc_attr_e('List of Players', 'intersoccer-player-management'); ?>">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('First Name', 'intersoccer-player-management'); ?></th>
                    <th scope="col"><?php esc_html_e('Last Name', 'intersoccer-player-management'); ?></th>
                    <th scope="col"><?php esc_html_e('DOB', 'intersoccer-player-management'); ?></th>
                    <th scope="col"><?php esc_html_e('Gender', 'intersoccer-player-management'); ?></th>
                    <th scope="col"><?php esc_html_e('Medical Conditions', 'intersoccer-player-management'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', 'intersoccer-player-management'); ?></th>
                </tr>
            </thead>
            <tbody id="players-table">
                <?php if (empty($players)) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No players added yet.', 'intersoccer-player-management'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($players as $index => $player) : ?>
                        <tr data-player-index="<?php echo esc_attr($index); ?>">
                            <td class="display-first-name"><?php echo esc_html($player['first_name'] ?? 'N/A'); ?></td>
                            <td class="display-last-name"><?php echo esc_html($player['last_name'] ?? 'N/A'); ?></td>
                            <td class="display-dob"><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                            <td class="display-gender"><?php echo esc_html($player['gender'] ?? 'N/A'); ?></td>
                            <td class="display-medical"><?php echo esc_html($player['medical_conditions'] ?? 'None'); ?></td>
                            <td>
                                <a href="#" class="quick-edit-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php esc_attr_e('Quick Edit player', 'intersoccer-player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>">
                                    <?php esc_html_e('Edit', 'intersoccer-player-management'); ?>
                                </a> /
                                <a href="#" class="delete-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php esc_attr_e('Delete player', 'intersoccer-player-management'); ?> <?php echo esc_attr($player['first_name'] ?? ''); ?>">
                                    <?php esc_html_e('Delete', 'intersoccer-player-management'); ?>
                                </a>
                            </td>
                        </tr>
                        <tr class="quick-edit-row" data-player-index="<?php echo esc_attr($index); ?>" style="display: none;">
                            <td>
                                <input type="text" class="edit-first-name" value="<?php echo esc_attr($player['first_name'] ?? ''); ?>" required>
                            </td>
                            <td>
                                <input type="text" class="edit-last-name" value="<?php echo esc_attr($player['last_name'] ?? ''); ?>" required>
                            </td>
                            <td>
                                <input type="text" class="edit-dob date-picker" value="<?php echo esc_attr($player['dob'] ?? ''); ?>" placeholder="YYYY-MM-DD">
                            </td>
                            <td>
                                <select class="edit-gender">
                                    <option value="" <?php selected($player['gender'] ?? '', ''); ?>><?php esc_html_e('Select Gender', 'intersoccer-player-management'); ?></option>
                                    <option value="male" <?php selected($player['gender'] ?? '', 'male'); ?>><?php esc_html_e('Male', 'intersoccer-player-management'); ?></option>
                                    <option value="female" <?php selected($player['gender'] ?? '', 'female'); ?>><?php esc_html_e('Female', 'intersoccer-player-management'); ?></option>
                                    <option value="other" <?php selected($player['gender'] ?? '', 'other'); ?>><?php esc_html_e('Other', 'intersoccer-player-management'); ?></option>
                                </select>
                            </td>
                            <td>
                                <textarea class="edit-medical"><?php echo esc_textarea($player['medical_conditions'] ?? ''); ?></textarea>
                            </td>

                            <td>
                                <button class="button save-player" data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Save', 'intersoccer-player-management'); ?></button>
                                <button class="button cancel-edit"><?php esc_html_e('Cancel', 'intersoccer-player-management'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="intersoccer-player-spacer">&nbsp;</div>
        <h3><?php esc_html_e('Add New Player', 'intersoccer-player-management'); ?></h3>
        <form id="player-form" aria-describedby="form-instructions">
            <p id="form-instructions" class="screen-reader-text"><?php esc_html_e('Fill out the form to add a new player.', 'intersoccer-player-management'); ?></p>
            <p>
                <label for="player_first_name"><?php esc_html_e('First Name of Child:', 'intersoccer-player-management'); ?></label>
                <input type="text" id="player_first_name" name="player_first_name" required aria-required="true">
            </p>
            <p>
                <label for="player_last_name"><?php esc_html_e('Last Name of Child:', 'intersoccer-player-management'); ?></label>
                <input type="text" id="player_last_name" name="player_last_name" required aria-required="true">
            </p>
            <p>
                <label for="player_dob"><?php esc_html_e('Date of Birth:', 'intersoccer-player-management'); ?></label>
                <input type="text" id="player_dob" name="player_dob" class="date-picker" placeholder="YYYY-MM-DD" required aria-required="true">
            </p>
            <p>
                <label for="player_gender"><?php esc_html_e('Gender:', 'intersoccer-player-management'); ?></label>
                <select id="player_gender" name="player_gender">
                    <option value=""><?php esc_html_e('Select Gender', 'intersoccer-player-management'); ?></option>
                    <option value="male"><?php esc_html_e('Male', 'intersoccer-player-management'); ?></option>
                    <option value="female"><?php esc_html_e('Female', 'intersoccer-player-management'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'intersoccer-player-management'); ?></option>
                </select>
            </p>
            <p>
                <label for="player_medical"><?php esc_html_e('Medical Conditions:', 'intersoccer-player-management'); ?></label>
                <textarea id="player_medical" name="player_medical" aria-describedby="medical-instructions"></textarea>
                <span id="medical-instructions" class="screen-reader-text"><?php esc_html_e('Optional field for medical conditions.', 'intersoccer-player-management'); ?></span>
            </p>

            <p>
                <button type="submit" class="button" id="player_submit" aria-label="<?php esc_attr_e('Add Player', 'intersoccer-player-management'); ?>">
                    <?php esc_html_e('Add Player', 'intersoccer-player-management'); ?>
                    <span class="spinner" style="display: none;"></span>
                </button>
            </p>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Hook form to manage-players endpoint
add_action('woocommerce_account_manage-players_endpoint', function () {
    error_log('InterSoccer: Triggered woocommerce_account_manage-players_endpoint');
    echo intersoccer_render_players_form();
});
?>
