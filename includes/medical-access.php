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
            'medical_conditions',
            'dietary_requirements',
        ];
    }
}

/**
 * Whether the user may see or change medical/allergy/dietary text and DOB in PM UI.
 *
 * @param int|null $user_id Optional user ID (defaults to current user).
 */
function intersoccer_pm_current_user_can_view_medical($user_id = null): bool {
    $user_id = $user_id ? (int) $user_id : (int) get_current_user_id();
    $allowed = false;

    if ($user_id > 0) {
        if ($user_id === (int) get_current_user_id()) {
            $allowed = current_user_can('manage_options') || current_user_can('manage_woocommerce');
        } elseif (function_exists('user_can')) {
            $allowed = user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce');
        }
    }

    return apply_filters('intersoccer_can_view_medical', $allowed, $user_id) === true;
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
     * @param mixed $raw Stored medical text.
     */
    function intersoccer_pm_format_medical_display_value($raw): string {
        if (!intersoccer_pm_current_user_can_view_medical()) {
            return intersoccer_pm_medical_restricted_label();
        }

        return trim((string) $raw);
    }
}

if (!function_exists('intersoccer_pm_format_medical_display_snippet')) {
    /**
     * Truncated medical column text for tables (caller escapes HTML).
     *
     * @param mixed $raw
     * @param int   $max_len
     */
    function intersoccer_pm_format_medical_display_snippet($raw, int $max_len = 20): string {
        $text = intersoccer_pm_format_medical_display_value($raw);
        if (!intersoccer_pm_current_user_can_view_medical()) {
            return $text;
        }
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
     * @param mixed $raw
     */
    function intersoccer_pm_format_dob_display_value($raw): string {
        if (!intersoccer_pm_current_user_can_view_medical()) {
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
     * @param mixed  $raw
     * @param string $fallback When allowed and value empty.
     */
    function intersoccer_pm_sensitive_value_for_data_attribute($raw, string $fallback = ''): string {
        if (!intersoccer_pm_current_user_can_view_medical()) {
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
     * @return array<string,mixed>
     */
    function intersoccer_pm_redact_player_for_response(array $player): array {
        if (intersoccer_pm_current_user_can_view_medical()) {
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
     * @return array<string,mixed>
     */
    function intersoccer_pm_strip_medical_from_player_update_data(array $update_data): array {
        if (intersoccer_pm_current_user_can_view_medical()) {
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
     * @return array<string,mixed>
     */
    function intersoccer_pm_preserve_sensitive_fields_on_save(array $existing, array $incoming): array {
        if (intersoccer_pm_current_user_can_view_medical()) {
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
