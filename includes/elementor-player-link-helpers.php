<?php
/**
 * Helpers for the Elementor Player Management Link widget (load on every front request).
 *
 * @package InterSoccer_Player_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the user has at least one valid registered player.
 *
 * @param int|null $user_id User ID (defaults to current user).
 */
if (!function_exists('intersoccer_user_has_players')) {
    function intersoccer_user_has_players($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $players = get_user_meta($user_id, 'intersoccer_players', true);

        if (!is_array($players) || empty($players)) {
            return false;
        }

        foreach ($players as $player) {
            if (is_array($player)
                && !empty($player['first_name'])
                && !empty($player['last_name'])) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Whether the link widget should show the Shepherd tour on the front end.
 *
 * @param bool               $is_editor        Elementor editor preview.
 * @param array<string,mixed> $stored_settings Raw Elementor widget settings.
 * @param int                $user_id          User to evaluate.
 */
/**
 * Escaped i18n strings for the Shepherd guided tour (HTML context in Shepherd.js).
 *
 * @return array<string, string>
 */
if (!function_exists('intersoccer_pm_player_link_widget_tour_i18n')) {
    function intersoccer_pm_player_link_widget_tour_i18n(): array {
        return [
            'tourTitle'     => esc_html__('Register Your Players', 'player-management'),
            'tourIntro'     => esc_html__(
                'Before you can register for camps and courses, you need to add at least one player (attendee) to your account.',
                'player-management'
            ),
            'tourHighlight' => esc_html__(
                'Click here to manage your players and add your first attendee.',
                'player-management'
            ),
            'tourAction'    => esc_html__('Click this link to get started!', 'player-management'),
            'tourDismiss'   => esc_html__('Don\'t show again', 'player-management'),
            'tourNext'      => esc_html__('Next', 'player-management'),
            'tourBack'      => esc_html__('Back', 'player-management'),
            'tourComplete'  => esc_html__('Got it!', 'player-management'),
        ];
    }
}

if (!function_exists('intersoccer_pm_player_link_widget_should_show_tour')) {
    function intersoccer_pm_player_link_widget_should_show_tour(
        bool $is_editor,
        array $stored_settings,
        int $user_id
    ): bool {
        if ($is_editor) {
            return false;
        }

        return !intersoccer_pm_shepherd_is_wc_blocked_context()
            && intersoccer_pm_player_link_widget_show_tour_enabled($stored_settings)
            && !intersoccer_user_has_players($user_id);
    }
}
