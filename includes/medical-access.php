<?php
/**
 * Medical / allergy / DOB visibility for Player Management UI and AJAX.
 *
 * @package InterSoccer_Player_Management
 */

defined('ABSPATH') || exit;

if (!function_exists('intersoccer_pm_medical_sensitive_field_keys')) {
    /**
     * Player array keys treated as medical or DOB (fail closed when access denied).
     *
     * @return string[]
     */
    function intersoccer_pm_medical_sensitive_field_keys(): array {
        return [
            'dob',
            'age',
            'event_age_groups',
            'medical_conditions',
            'dietary_requirements',
        ];
    }
}

if (!function_exists('intersoccer_pm_current_user_can_view_medical')) {
    /**
     * Whether the user may see or change medical/allergy/dietary text and DOB in PM UI.
     *
     * @param int|null $user_id Optional user ID (defaults to current user).
     */
    function intersoccer_pm_current_user_can_view_medical($user_id = null): bool {
        $user_id = $user_id !== null ? (int) $user_id : (int) get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $allowed = false;

        if ($user_id === (int) get_current_user_id()) {
            $allowed = current_user_can('manage_options') || current_user_can('manage_woocommerce');
        } elseif (function_exists('user_can')) {
            $allowed = user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce');
        }

        return apply_filters('intersoccer_can_view_medical', $allowed, $user_id) === true;
    }
}

if (!function_exists('intersoccer_pm_can_view_player_medical')) {
    /**
     * Owner-aware medical/DOB access for a specific player's account.
     *
     * Account owners always pass; staff/coach access uses intersoccer_pm_current_user_can_view_medical().
     *
     * @param int      $owner_user_id WordPress user ID that owns the player row.
     * @param int|null $viewer_id     Viewer user ID (defaults to current user).
     */
    function intersoccer_pm_can_view_player_medical(int $owner_user_id, $viewer_id = null): bool {
        $viewer_id = $viewer_id !== null ? (int) $viewer_id : (int) get_current_user_id();
        $owner_user_id = (int) $owner_user_id;

        if ($viewer_id <= 0 || $owner_user_id <= 0) {
            return false;
        }

        if ($viewer_id === $owner_user_id) {
            return true;
        }

        return intersoccer_pm_current_user_can_view_medical($viewer_id);
    }
}

if (!function_exists('intersoccer_pm_medical_restricted_label')) {
    function intersoccer_pm_medical_restricted_label(): string {
        return (string) __('Restricted', INTERSOCCER_PLAYER_TEXT_DOMAIN);
    }
}

if (!function_exists('intersoccer_pm_format_medical_display_value')) {
    /**
     * Medical/dietary/allergy cell text (caller escapes HTML).
     *
     * @param mixed    $raw
     * @param int      $owner_user_id
     * @param int|null $viewer_id
     */
    function intersoccer_pm_format_medical_display_value($raw, int $owner_user_id, $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return intersoccer_pm_medical_restricted_label();
        }

        return trim((string) $raw);
    }
}

if (!function_exists('intersoccer_pm_format_medical_display_snippet')) {
    /**
     * Truncated medical column text for tables (caller escapes HTML).
     *
     * @param mixed    $raw
     * @param int      $owner_user_id
     * @param int      $max_len
     * @param int|null $viewer_id
     */
    function intersoccer_pm_format_medical_display_snippet($raw, int $owner_user_id, int $max_len = 20, $viewer_id = null): string {
        $can_view = intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id);
        if (!$can_view) {
            return intersoccer_pm_medical_restricted_label();
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }

        return strlen($text) > $max_len ? substr($text, 0, $max_len) . '...' : $text;
    }
}

if (!function_exists('intersoccer_pm_format_dob_display_value')) {
    /**
     * DOB cell text (caller escapes HTML).
     *
     * @param mixed    $raw
     * @param int      $owner_user_id
     * @param int|null $viewer_id
     */
    function intersoccer_pm_format_dob_display_value($raw, int $owner_user_id, $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return intersoccer_pm_medical_restricted_label();
        }

        $dob = trim((string) $raw);

        return $dob !== '' ? $dob : 'N/A';
    }
}

if (!function_exists('intersoccer_pm_sensitive_value_for_data_attribute')) {
    /**
     * Value for data-* attributes (empty when access denied so JS cannot read secrets).
     *
     * @param mixed    $raw
     * @param int      $owner_user_id
     * @param string   $fallback When allowed and value empty.
     * @param int|null $viewer_id
     */
    function intersoccer_pm_sensitive_value_for_data_attribute($raw, int $owner_user_id, string $fallback = '', $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return '';
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('intersoccer_pm_redact_player_for_response')) {
    /**
     * Strip sensitive fields from player payloads (AJAX / batch APIs).
     *
     * @param array<string,mixed> $player
     * @param int                 $owner_user_id
     * @param int|null            $viewer_id
     * @return array<string,mixed>
     */
    function intersoccer_pm_redact_player_for_response(array $player, int $owner_user_id, $viewer_id = null): array {
        if (intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return $player;
        }

        $restricted = intersoccer_pm_medical_restricted_label();
        foreach (intersoccer_pm_medical_sensitive_field_keys() as $key) {
            if (array_key_exists($key, $player)) {
                $player[$key] = $restricted;
            }
        }

        return $player;
    }
}

if (!function_exists('intersoccer_pm_strip_medical_from_player_update_data')) {
    /**
     * Prevent denied users from overwriting stored medical/DOB on save.
     *
     * @param array<string,mixed> $update_data
     * @param int                 $owner_user_id
     * @param int|null            $viewer_id
     * @return array<string,mixed>
     */
    function intersoccer_pm_strip_medical_from_player_update_data(array $update_data, int $owner_user_id, $viewer_id = null): array {
        if (intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return $update_data;
        }

        foreach (intersoccer_pm_medical_sensitive_field_keys() as $key) {
            unset($update_data[$key]);
        }

        return $update_data;
    }
}

if (!function_exists('intersoccer_pm_preserve_sensitive_fields_on_save')) {
    /**
     * Merge incoming player row with stored sensitive fields when access is denied.
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     * @param int                 $owner_user_id
     * @param int|null            $viewer_id
     * @return array<string,mixed>
     */
    function intersoccer_pm_preserve_sensitive_fields_on_save(array $existing, array $incoming, int $owner_user_id, $viewer_id = null): array {
        if (intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return $incoming;
        }

        foreach (intersoccer_pm_medical_sensitive_field_keys() as $key) {
            if (array_key_exists($key, $existing)) {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }
}

if (!function_exists('intersoccer_pm_medical_field_denied_notice')) {
    /**
     * Translatable notice when the viewer cannot enter medical/dietary/allergy text.
     */
    function intersoccer_pm_medical_field_denied_notice(): string {
        return esc_html__(
            'Medical and dietary information must be added by someone with medical access.',
            INTERSOCCER_PLAYER_TEXT_DOMAIN
        );
    }
}

if (!function_exists('intersoccer_pm_render_medical_input_field')) {
    /**
     * Add/edit form: medical textarea for owners/medical staff, or a denied notice.
     *
     * @param int   $owner_user_id Player account owner user ID.
     * @param array $args          Optional textarea_id, textarea_name, label_for, wrap_class.
     */
    function intersoccer_pm_render_medical_input_field(int $owner_user_id, array $args = []): void {
        $textarea_id   = $args['textarea_id'] ?? 'player_medical';
        $textarea_name = $args['textarea_name'] ?? 'player_medical';
        $label_for     = $args['label_for'] ?? $textarea_id;
        $wrap_class    = $args['wrap_class'] ?? 'form-row add-player-medical';
        $maxlength     = isset($args['maxlength']) ? (int) $args['maxlength'] : 500;

        if (!intersoccer_pm_can_view_player_medical($owner_user_id)) {
            echo '<p class="pm-medical-field-denied-note">' . intersoccer_pm_medical_field_denied_notice() . '</p>';

            return;
        }

        echo '<div class="' . esc_attr($wrap_class) . '">';
        echo '<label for="' . esc_attr($label_for) . '">';
        esc_html_e('Medical Conditions, Dietary Restrictions, and Allergies', INTERSOCCER_PLAYER_TEXT_DOMAIN);
        echo '</label>';
        echo '<textarea id="' . esc_attr($textarea_id) . '" name="' . esc_attr($textarea_name) . '" maxlength="' . esc_attr((string) $maxlength) . '"></textarea>';
        if (!empty($args['instructions_id'])) {
            echo '<span id="' . esc_attr($args['instructions_id']) . '" class="screen-reader-text">';
            esc_html_e('Optional field for medical conditions, dietary restrictions, and allergies.', INTERSOCCER_PLAYER_TEXT_DOMAIN);
            echo '</span>';
        }
        echo '<span class="error-message" style="display: none;"></span>';
        echo '</div>';
    }
}
