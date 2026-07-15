<?php
/**
 * Player data helpers — canonical read API for intersoccer_players user meta.
 *
 * @package Player_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Request-level cache for player lists (same request only).
 *
 * @return array<int, array>
 */
if (!function_exists('_intersoccer_pm_players_request_cache')) {
function &_intersoccer_pm_players_request_cache() {
    static $cache = [];
    return $cache;
}
}

/**
 * Generate a stable player UUID.
 *
 * @return string
 */
if (!function_exists('intersoccer_generate_player_id')) {
function intersoccer_generate_player_id() {
    if (function_exists('wp_generate_uuid4')) {
        return wp_generate_uuid4();
    }

    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}
}

/**
 * Assign player_id UUIDs to rows missing them (idempotent).
 *
 * @param int  $user_id WordPress user ID.
 * @param bool $persist When true, save back to user meta if any IDs were added.
 * @return array{players: array, updated: int}
 */
if (!function_exists('intersoccer_backfill_player_ids')) {
function intersoccer_backfill_player_ids($user_id, $persist = true) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['players' => [], 'updated' => 0];
    }

    $players = get_user_meta($user_id, 'intersoccer_players', true);
    if (is_string($players)) {
        $players = maybe_unserialize($players);
    }
    if (!is_array($players)) {
        $players = [];
    }

    $updated = 0;
    foreach ($players as $key => $player) {
        if (!is_array($player)) {
            continue;
        }
        if (empty($player['player_id'])) {
            $players[$key]['player_id'] = intersoccer_generate_player_id();
            $updated++;
        }
    }

    if ($updated > 0 && $persist) {
        update_user_meta($user_id, 'intersoccer_players', $players);
        intersoccer_invalidate_user_players_cache($user_id);
    }

    return ['players' => $players, 'updated' => $updated];
}
}

/**
 * Backfill missing player_id UUIDs for all users with intersoccer_players meta.
 *
 * @param int $batch_size Max users to process per call (0 = no limit).
 * @param int $offset     User query offset for batching.
 * @return array{users_processed:int,users_updated:int,players_updated:int,has_more:bool,next_offset:int}
 */
if (!function_exists('intersoccer_backfill_all_player_ids')) {
function intersoccer_backfill_all_player_ids($batch_size = 100, $offset = 0) {
    $batch_size = max(0, (int) $batch_size);
    $offset = max(0, (int) $offset);

    $query_args = [
        'fields' => 'ID',
        'meta_key' => 'intersoccer_players',
        'number' => $batch_size > 0 ? $batch_size : -1,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
    ];

    $user_ids = get_users($query_args);
    $summary = [
        'users_processed' => 0,
        'users_updated' => 0,
        'players_updated' => 0,
        'has_more' => false,
        'next_offset' => $offset,
    ];

    foreach ($user_ids as $user_id) {
        $user_id = (int) $user_id;
        $summary['users_processed']++;
        $result = intersoccer_backfill_player_ids($user_id, true);
        if ($result['updated'] > 0) {
            $summary['users_updated']++;
            $summary['players_updated'] += $result['updated'];
        }
    }

    if ($batch_size > 0) {
        $summary['next_offset'] = $offset + count($user_ids);
        $summary['has_more'] = count($user_ids) === $batch_size;
    }

    return $summary;
}
}

/**
 * Invalidate object cache and request cache after player mutations.
 *
 * @param int $user_id WordPress user ID.
 */
if (!function_exists('intersoccer_invalidate_user_players_cache')) {
function intersoccer_invalidate_user_players_cache($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    wp_cache_delete('intersoccer_players_' . $user_id, 'intersoccer');
    intersoccer_clear_user_players_cache($user_id);

    /**
     * Fired after player list caches are invalidated (PM CRUD).
     *
     * @param int $user_id WordPress user ID.
     */
    do_action('intersoccer_players_cache_invalidated', $user_id);
}
}

/**
 * Persist player rows and refresh caches.
 *
 * @param int   $user_id WordPress user ID.
 * @param array $players Player rows.
 * @return bool Whether user meta was updated.
 */
if (!function_exists('intersoccer_persist_user_players')) {
function intersoccer_persist_user_players($user_id, array $players) {
    $user_id = (int) $user_id;
    $updated = update_user_meta($user_id, 'intersoccer_players', $players);
    intersoccer_invalidate_user_players_cache($user_id);

    wp_cache_set('intersoccer_players_' . $user_id, $players, 'intersoccer', 3600);
    $cache = &_intersoccer_pm_players_request_cache();
    $cache[$user_id] = $players;

    return (bool) $updated;
}
}

/**
 * Get players for a user with request-level caching and lazy UUID backfill.
 *
 * @param int  $user_id       WordPress user ID.
 * @param bool $force_refresh Bypass request cache.
 * @return array<int|string, array>
 */
if (!function_exists('intersoccer_get_user_players')) {
function intersoccer_get_user_players($user_id, $force_refresh = false) {
    if (empty($user_id) || !is_numeric($user_id)) {
        return [];
    }

    $user_id = (int) $user_id;
    $cache = &_intersoccer_pm_players_request_cache();

    if (!$force_refresh && isset($cache[$user_id])) {
        return $cache[$user_id];
    }

    $object_cache_key = 'intersoccer_players_' . $user_id;
    $players = false;
    if (!$force_refresh) {
        $players = wp_cache_get($object_cache_key, 'intersoccer');
    }

    if ($players === false) {
        $players = get_user_meta($user_id, 'intersoccer_players', true);
        if (is_string($players)) {
            $players = maybe_unserialize($players);
        }
        if (!is_array($players)) {
            $players = [];
        }

        $backfill = intersoccer_backfill_player_ids($user_id, true);
        $players = $backfill['players'];

        wp_cache_set($object_cache_key, $players, 'intersoccer', 3600);
    }

    $cache[$user_id] = $players;

    return $players;
}
}

/**
 * Clear request-level player cache.
 *
 * @param int|null $user_id User ID or null for all.
 */
if (!function_exists('intersoccer_clear_user_players_cache')) {
function intersoccer_clear_user_players_cache($user_id = null) {
    $cache = &_intersoccer_pm_players_request_cache();

    if ($user_id === null) {
        $cache = [];
        return;
    }

    unset($cache[(int) $user_id]);
}
}

/**
 * Map a posted slot to the real intersoccer_players array key.
 *
 * @param array<int|string, mixed> $players   Player rows.
 * @param mixed                    $requested Posted index or key.
 * @return int|string|null
 */
if (!function_exists('intersoccer_resolve_intersoccer_players_meta_key')) {
function intersoccer_resolve_intersoccer_players_meta_key(array $players, $requested) {
    if ($players === []) {
        return null;
    }
    if ($requested === null || $requested === '') {
        return null;
    }
    if (array_key_exists($requested, $players)) {
        return $requested;
    }

    $as_int = null;
    if (is_int($requested)) {
        $as_int = $requested;
    } elseif (is_string($requested) && $requested !== '' && ctype_digit($requested)) {
        $as_int = (int) $requested;
    }
    if ($as_int !== null && array_key_exists($as_int, $players)) {
        return $as_int;
    }
    if ($as_int === null) {
        return null;
    }

    $keys = array_keys($players);
    if ($as_int >= 0 && $as_int < count($keys)) {
        return $keys[$as_int];
    }

    return null;
}
}

/**
 * Find a player row by stable UUID.
 *
 * @param int    $user_id   WordPress user ID.
 * @param string $player_id Player UUID.
 * @return array{key: int|string, player: array}|null
 */
if (!function_exists('intersoccer_get_player_by_id')) {
function intersoccer_get_player_by_id($user_id, $player_id) {
    $player_id = sanitize_text_field((string) $player_id);
    if ($player_id === '') {
        return null;
    }

    $players = intersoccer_get_user_players($user_id);
    foreach ($players as $key => $player) {
        if (!is_array($player)) {
            continue;
        }
        if (!empty($player['player_id']) && (string) $player['player_id'] === $player_id) {
            return ['key' => $key, 'player' => $player];
        }
    }

    return null;
}
}

/**
 * Get a player row by array index/key.
 *
 * @param int        $user_id      WordPress user ID.
 * @param int|string $player_index Array key or list position.
 * @return array|null
 */
if (!function_exists('intersoccer_get_player_by_index')) {
function intersoccer_get_player_by_index($user_id, $player_index) {
    $players = intersoccer_get_user_players($user_id);
    if (!is_array($players)) {
        return null;
    }

    $key = intersoccer_resolve_intersoccer_players_meta_key($players, $player_index);
    if ($key !== null && isset($players[$key]) && is_array($players[$key])) {
        return $players[$key];
    }

    return null;
}
}
