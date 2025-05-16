<?php

/**
 * Player Count
 * Changes:
 * - Added shortcode to display total player count.
 * - Implemented caching for user queries.
 * - Added region filter based on intersoccer_players meta.
 * - Ensured queries run on init to avoid translation issues.
 * Testing:
 * - Add [intersoccer_player_count] shortcode to a page, verify total player count displays.
 * - Add [intersoccer_player_count region="Geneva"], confirm only Geneva players are counted.
 * - Check caching, ensure database queries are minimized.
 * - Verify no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

add_action('init', function () {
    add_shortcode('intersoccer_player_count', 'intersoccer_player_count_shortcode');
});

function intersoccer_player_count_shortcode($atts)
{
    $atts = shortcode_atts(['region' => ''], $atts, 'intersoccer_player_count');
    $region = sanitize_text_field($atts['region']);
    $cache_key = 'intersoccer_player_count_' . ($region ? md5($region) : 'all');
    $count = wp_cache_get($cache_key, 'intersoccer');

    if (false === $count) {
        $args = [
            'role' => 'customer',
            'meta_key' => 'intersoccer_players',
            'meta_compare' => 'EXISTS',
        ];
        if ($region) {
            $args['meta_query'] = [
                [
                    'key' => 'intersoccer_players',
                    'value' => '"region":"' . $region . '"',
                    'compare' => 'LIKE',
                ],
            ];
        }
        $users = get_users($args);
        $count = 0;
        foreach ($users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            $count += count($players);
        }
        wp_cache_set($cache_key, $count, 'intersoccer', 3600);
    }

    return '<p>' . sprintf(__('Total Players%s: %d', 'intersoccer-player-management'), $region ? ' (' . esc_html($region) . ')' : '', $count) . '</p>';
}

