<?php
/**
 * Overview KPI metric helpers — decision-first counts for admin Players overview.
 *
 * @package PlayerManagement
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Whether a participant profile is incomplete (v1: missing DOB or empty medical field).
 *
 * @param array $player Player row from intersoccer_players meta.
 * @return bool
 */
function intersoccer_pm_player_profile_is_incomplete(array $player) {
	$dob = isset($player['dob']) ? trim((string) $player['dob']) : '';
	if ($dob === '' || strcasecmp($dob, 'N/A') === 0) {
		return true;
	}

	$medical = isset($player['medical_conditions']) ? trim((string) $player['medical_conditions']) : '';
	return $medical === '';
}

/**
 * Whether a participant has never booked (lifetime event count is zero).
 *
 * @param int $user_id      Parent user ID.
 * @param int $player_index Index in intersoccer_players.
 * @return bool
 */
function intersoccer_pm_player_never_booked_lifetime($user_id, $player_index) {
	if (!function_exists('intersoccer_get_player_event_count')) {
		return true;
	}
	return (int) intersoccer_get_player_event_count((int) $user_id, (int) $player_index) === 0;
}

/**
 * Collapse canton counts to top N plus an Other bucket for the secondary chart.
 *
 * @param array<string, int> $canton_data Canton => count (unsorted OK).
 * @param int                $top_n       Number of named cantons to keep.
 * @return array<string, int>
 */
function intersoccer_pm_overview_canton_chart_data(array $canton_data, $top_n = 8) {
	$top_n = max(1, (int) $top_n);
	if (empty($canton_data)) {
		return ['Unknown' => 0];
	}

	arsort($canton_data);
	if (count($canton_data) <= $top_n) {
		return $canton_data;
	}

	$top = array_slice($canton_data, 0, $top_n, true);
	$rest = array_slice($canton_data, $top_n, null, true);
	$other = array_sum($rest);
	if ($other > 0) {
		$top['Other'] = (int) $other;
	}

	return $top;
}

/**
 * Admin drill-down URL for an overview filter (list may wire filters later).
 *
 * @param string $filter Filter slug: no_players|never_booked|incomplete.
 * @return string
 */
function intersoccer_pm_overview_filter_url($filter) {
	$filter = sanitize_key((string) $filter);
	$allowed = ['no_players', 'never_booked', 'incomplete'];
	if (!in_array($filter, $allowed, true)) {
		$filter = '';
	}

	$args = ['page' => 'intersoccer-all-players'];
	if ($filter !== '') {
		$args['overview_filter'] = $filter;
	}

	return admin_url(add_query_arg($args, 'admin.php'));
}

/**
 * Empty overview payload shape for v4 KPI dashboard.
 *
 * @return array<string, mixed>
 */
function intersoccer_pm_overview_empty_payload() {
	return [
		'total_players' => 0,
		'users_without_players' => 0,
		'never_booked_lifetime' => 0,
		'incomplete_profiles' => 0,
		'canton_data' => ['Unknown' => 0],
		'generation_time' => '',
		'total_users_processed' => 0,
		'processing_method' => 'batch_v4',
	];
}
