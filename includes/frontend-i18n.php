<?php
/**
 * Localized strings passed to customer-facing player management JavaScript.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, string>
 */
function intersoccer_pm_frontend_js_i18n() {
    return [
        'edit' => __('Edit', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'delete' => __('Delete', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'save' => __('Save', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'cancel' => __('Cancel', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'edit_player_aria' => __('Edit player %s', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'delete_player_aria' => __('Delete player %s', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'save_player_aria' => __('Save Player', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'cancel_edit_aria' => __('Cancel Edit', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'no_attendees' => __('No attendees added yet.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'required_fields' => __('First name, last name, date of birth, and gender are required.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'invalid_dob' => __('Invalid date of birth format. Use YYYY-MM-DD.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'player_updated' => __('Player updated successfully.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'player_added' => __('Player added successfully.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'error_saving' => __('Error saving player.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'error_saving_detail' => __('Error saving player: %s', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'error_load_edit' => __('Error: Could not load player data for editing.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'edit_canceled' => __('Edit canceled.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'confirm_delete' => __('Are you sure you want to delete this player?', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'failed_delete' => __('Failed to delete player.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'error_refresh_nonce' => __('Error: Failed to refresh security token.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'error_delete_detail' => __('Error: Unable to delete player - %s', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'add_canceled' => __('Add player canceled.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'unknown_error' => __('Unknown error', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'select_gender' => __('Select Gender', INTERSOCCER_PLAYER_TEXT_DOMAIN),
        'avs_instruction' => __('No AVS? Enter foreign insurance number or "0000" and email us the insurance details.', INTERSOCCER_PLAYER_TEXT_DOMAIN),
    ];
}
