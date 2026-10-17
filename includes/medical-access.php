<?php
/**
 * Medical / allergy / DOB visibility for Player Management UI and AJAX.
 *
 * @package InterSoccer_Player_Management
 */

defined('ABSPATH') || exit;

if (!function_exists('intersoccer_pm_dob_sensitive_field_keys')) {
    /**
     * @return string[]
     */
    function intersoccer_pm_dob_sensitive_field_keys(): array {
        return [
            'dob',
            'age',
            'event_age_groups',
        ];
    }
}

if (!function_exists('intersoccer_pm_medical_text_sensitive_field_keys')) {
    /**
     * @return string[]
     */
    function intersoccer_pm_medical_text_sensitive_field_keys(): array {
        return [
            'medical_conditions',
            'dietary_requirements',
        ];
    }
}

if (!function_exists('intersoccer_pm_medical_sensitive_field_keys')) {
    /**
     * All sensitive keys (DOB-derived + medical text).
     *
     * @return string[]
     */
    function intersoccer_pm_medical_sensitive_field_keys(): array {
        return array_merge(
            intersoccer_pm_dob_sensitive_field_keys(),
            intersoccer_pm_medical_text_sensitive_field_keys()
        );
    }
}

if (!function_exists('intersoccer_pm_viewer_may_see_medical')) {
    function intersoccer_pm_viewer_may_see_medical(int $viewer_id): bool {
        if ($viewer_id <= 0) {
            return false;
        }
        if (!function_exists('intersoccer_user_can_view_medical')) {
            return false;
        }

        return intersoccer_user_can_view_medical($viewer_id) === true;
    }
}

if (!function_exists('intersoccer_pm_viewer_may_see_dob')) {
    function intersoccer_pm_viewer_may_see_dob(int $viewer_id): bool {
        if ($viewer_id <= 0) {
            return false;
        }
        if (!function_exists('intersoccer_user_can_view_dob')) {
            return false;
        }

        return intersoccer_user_can_view_dob($viewer_id) === true;
    }
}

if (!function_exists('intersoccer_pm_current_user_can_view_medical')) {
    /**
     * @param int|null $user_id Optional viewer user ID (defaults to current user).
     */
    function intersoccer_pm_current_user_can_view_medical($user_id = null): bool {
        $user_id = $user_id !== null ? (int) $user_id : (int) get_current_user_id();

        return intersoccer_pm_viewer_may_see_medical($user_id);
    }
}

if (!function_exists('intersoccer_pm_current_user_can_view_dob')) {
    /**
     * @param int|null $user_id Optional viewer user ID (defaults to current user).
     */
    function intersoccer_pm_current_user_can_view_dob($user_id = null): bool {
        $user_id = $user_id !== null ? (int) $user_id : (int) get_current_user_id();

        return intersoccer_pm_viewer_may_see_dob($user_id);
    }
}

if (!function_exists('intersoccer_pm_can_view_player_medical')) {
    function intersoccer_pm_can_view_player_medical(int $owner_user_id, $viewer_id = null): bool {
        $viewer_id = $viewer_id !== null ? (int) $viewer_id : (int) get_current_user_id();
        $owner_user_id = (int) $owner_user_id;

        if ($viewer_id <= 0 || $owner_user_id <= 0) {
            return false;
        }

        if ($viewer_id === $owner_user_id) {
            return true;
        }

        return intersoccer_pm_viewer_may_see_medical($viewer_id);
    }
}

if (!function_exists('intersoccer_pm_can_view_player_dob')) {
    function intersoccer_pm_can_view_player_dob(int $owner_user_id, $viewer_id = null): bool {
        $viewer_id = $viewer_id !== null ? (int) $viewer_id : (int) get_current_user_id();
        $owner_user_id = (int) $owner_user_id;

        if ($viewer_id <= 0 || $owner_user_id <= 0) {
            return false;
        }

        if ($viewer_id === $owner_user_id) {
            return true;
        }

        return intersoccer_pm_viewer_may_see_dob($viewer_id);
    }
}

if (!function_exists('intersoccer_pm_medical_restricted_label')) {
    function intersoccer_pm_medical_restricted_label(): string {
        return (string) __('Restricted', INTERSOCCER_PLAYER_TEXT_DOMAIN);
    }
}

if (!function_exists('intersoccer_pm_format_medical_display_value')) {
    function intersoccer_pm_format_medical_display_value($raw, int $owner_user_id, $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return intersoccer_pm_medical_restricted_label();
        }

        return trim((string) $raw);
    }
}

if (!function_exists('intersoccer_pm_format_medical_display_snippet')) {
    function intersoccer_pm_format_medical_display_snippet($raw, int $owner_user_id, int $max_len = 20, $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
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
    function intersoccer_pm_format_dob_display_value($raw, int $owner_user_id, $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id)) {
            return intersoccer_pm_medical_restricted_label();
        }

        $dob = trim((string) $raw);

        return $dob !== '' ? $dob : 'N/A';
    }
}

if (!function_exists('intersoccer_pm_sensitive_value_for_data_attribute')) {
    function intersoccer_pm_sensitive_value_for_data_attribute($raw, int $owner_user_id, string $fallback = '', $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id)) {
            return '';
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('intersoccer_pm_sensitive_medical_value_for_data_attribute')) {
    function intersoccer_pm_sensitive_medical_value_for_data_attribute($raw, int $owner_user_id, string $fallback = '', $viewer_id = null): string {
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            return '';
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('intersoccer_pm_redact_player_for_response')) {
    /**
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    function intersoccer_pm_redact_player_for_response(array $player, int $owner_user_id, $viewer_id = null): array {
        $can_dob = intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id);
        $can_medical = intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id);
        if ($can_dob && $can_medical) {
            return $player;
        }

        $restricted = intersoccer_pm_medical_restricted_label();
        if (!$can_dob) {
            foreach (intersoccer_pm_dob_sensitive_field_keys() as $key) {
                if (array_key_exists($key, $player)) {
                    $player[$key] = $restricted;
                }
            }
        }
        if (!$can_medical) {
            foreach (intersoccer_pm_medical_text_sensitive_field_keys() as $key) {
                if (array_key_exists($key, $player)) {
                    $player[$key] = $restricted;
                }
            }
        }

        return $player;
    }
}

if (!function_exists('intersoccer_pm_redact_players_for_preload')) {
    /**
     * @param array<int|string,mixed> $players
     * @return array<int|string,mixed>
     */
    function intersoccer_pm_redact_players_for_preload(array $players, int $owner_user_id, $viewer_id = null): array {
        return array_map(
            static function ($player) use ($owner_user_id, $viewer_id) {
                return is_array($player)
                    ? intersoccer_pm_redact_player_for_response($player, $owner_user_id, $viewer_id)
                    : $player;
            },
            $players
        );
    }
}

if (!function_exists('intersoccer_pm_strip_medical_from_player_update_data')) {
    /**
     * @param array<string,mixed> $update_data
     * @return array<string,mixed>
     */
    function intersoccer_pm_strip_medical_from_player_update_data(array $update_data, int $owner_user_id, $viewer_id = null): array {
        if (intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)
            && intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id)) {
            return $update_data;
        }

        if (!intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id)) {
            foreach (intersoccer_pm_dob_sensitive_field_keys() as $key) {
                unset($update_data[$key]);
            }
        }
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            foreach (intersoccer_pm_medical_text_sensitive_field_keys() as $key) {
                unset($update_data[$key]);
            }
        }

        return $update_data;
    }
}

if (!function_exists('intersoccer_pm_preserve_sensitive_fields_on_save')) {
    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    function intersoccer_pm_preserve_sensitive_fields_on_save(array $existing, array $incoming, int $owner_user_id, $viewer_id = null): array {
        if (!intersoccer_pm_can_view_player_dob($owner_user_id, $viewer_id)) {
            foreach (intersoccer_pm_dob_sensitive_field_keys() as $key) {
                $incoming[$key] = $existing[$key] ?? '';
            }
        }
        if (!intersoccer_pm_can_view_player_medical($owner_user_id, $viewer_id)) {
            foreach (intersoccer_pm_medical_text_sensitive_field_keys() as $key) {
                $incoming[$key] = $existing[$key] ?? '';
            }
        }

        return $incoming;
    }
}

if (!function_exists('intersoccer_pm_medical_field_denied_notice')) {
    function intersoccer_pm_medical_field_denied_notice(): string {
        return esc_html__(
            'Medical and dietary information must be added by someone with medical access.',
            INTERSOCCER_PLAYER_TEXT_DOMAIN
        );
    }
}

if (!function_exists('intersoccer_pm_render_medical_input_field')) {
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
