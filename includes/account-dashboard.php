<?php
/**
 * My Account Dashboard integration — embed players, suppress WC blurb, redirect legacy endpoints.
 *
 * @package PlayerManagement
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * WPML-aware manage-players endpoint slugs (EN / FR / DE).
 *
 * @return string[]
 */
function intersoccer_pm_manage_players_endpoint_slugs() {
	return [
		'manage-players',
		'gerer-participants',
		'teilnehmer-verwalten',
	];
}

/**
 * Whether a menu/query key is a manage-players endpoint slug.
 *
 * @param string $slug Endpoint or menu key.
 * @return bool
 */
function intersoccer_pm_is_manage_players_endpoint_slug($slug) {
	return in_array((string) $slug, intersoccer_pm_manage_players_endpoint_slugs(), true);
}

/**
 * Remove manage-players entries from WooCommerce My Account menu items.
 *
 * @param array<string, string> $items Menu items.
 * @return array<string, string>
 */
function intersoccer_pm_strip_manage_players_menu_items(array $items) {
	foreach (intersoccer_pm_manage_players_endpoint_slugs() as $slug) {
		unset($items[$slug]);
	}
	return $items;
}

/**
 * Detect the default WooCommerce dashboard description string (English source).
 *
 * @param string $text Original gettext string.
 * @return bool
 */
function intersoccer_pm_is_woocommerce_dashboard_desc_string($text) {
	return is_string($text)
		&& strpos($text, 'From your account dashboard you can view your') === 0;
}

/**
 * Whether the current request is the My Account Dashboard (no endpoint).
 *
 * @return bool
 */
function intersoccer_pm_is_account_dashboard() {
	if (!function_exists('is_account_page') || !is_account_page()) {
		return false;
	}
	if (!function_exists('is_wc_endpoint_url')) {
		return false;
	}
	return !is_wc_endpoint_url();
}

/**
 * Whether the current request targets a manage-players endpoint (any language).
 *
 * @return bool
 */
function intersoccer_pm_is_manage_players_request() {
	if (!function_exists('is_account_page') || !is_account_page()) {
		return false;
	}

	if (function_exists('is_wc_endpoint_url')) {
		foreach (intersoccer_pm_manage_players_endpoint_slugs() as $slug) {
			if (is_wc_endpoint_url($slug)) {
				return true;
			}
		}
	}

	$request_uri = isset($_SERVER['REQUEST_URI'])
		? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
		: '';

	foreach (intersoccer_pm_manage_players_endpoint_slugs() as $slug) {
		if (isset($_GET[$slug]) || ($request_uri !== '' && strpos($request_uri, $slug) !== false)) {
			return true;
		}
	}

	return false;
}

/**
 * Whether player management frontend assets should load.
 *
 * @return bool
 */
function intersoccer_pm_should_enqueue_player_assets() {
	if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
		return false;
	}

	return intersoccer_pm_is_account_dashboard() || intersoccer_pm_is_manage_players_request();
}

/**
 * Safe product return URL, or an empty string when the value must not be followed.
 *
 * Product Variations owns the check when it is active. Otherwise this plugin
 * rejects off-site hosts, protocol-relative URLs, non-http(s) schemes, and CR/LF.
 *
 * @param mixed $url Raw redirect value.
 * @return string
 */
function intersoccer_pm_validate_return_url($url) {
	if (function_exists('intersoccer_pv_validate_return_url')) {
		$safe = intersoccer_pv_validate_return_url($url);
		return is_string($safe) ? $safe : '';
	}

	return intersoccer_pm_validate_return_url_fallback($url);
}

/**
 * Local return-URL check used only when Product Variations is not active.
 *
 * @param mixed $url Raw redirect value.
 * @return string
 */
function intersoccer_pm_validate_return_url_fallback($url) {
	if (!is_string($url)) {
		return '';
	}
	if (function_exists('wp_unslash')) {
		$unslashed = wp_unslash($url);
		if (!is_string($unslashed)) {
			return '';
		}
		$url = $unslashed;
	}
	$url = trim($url);
	if ($url === '' || intersoccer_pm_return_url_is_forbidden($url)) {
		return '';
	}

	$candidate = $url;
	if (function_exists('wp_validate_redirect')) {
		$validated = wp_validate_redirect($url, '');
		if (!is_string($validated) || $validated === '' || intersoccer_pm_return_url_is_forbidden($validated)) {
			return '';
		}
		$candidate = $validated;
	}

	if (!intersoccer_pm_return_url_host_allowed($candidate)) {
		return '';
	}

	return $candidate;
}

/**
 * Reject control characters, backslashes, protocol-relative URLs, and non-http(s) schemes.
 *
 * @param string $url URL after unslash and trim.
 * @return bool
 */
function intersoccer_pm_return_url_is_forbidden($url) {
	if (!is_string($url) || $url === '') {
		return true;
	}
	if (preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
		return true;
	}
	$decoded = rawurldecode($url);
	if (!is_string($decoded) || preg_match('/[\x00-\x1F\x7F\\\\]/', $decoded)) {
		return true;
	}
	$trim = ltrim($decoded);
	if ($trim === '' || strpos($trim, '//') === 0) {
		return true;
	}
	if (preg_match('#^([a-z][a-z0-9+.\-]*)\:#i', $trim, $match)) {
		$scheme = strtolower($match[1]);
		if ($scheme !== 'http' && $scheme !== 'https') {
			return true;
		}
	}
	return false;
}

/**
 * Same-host http(s) URL, or a site-relative path. Unknown site hosts fail closed.
 *
 * @param string $url Candidate URL.
 * @return bool
 */
function intersoccer_pm_return_url_host_allowed($url) {
	$parts = parse_url($url);
	if (!is_array($parts)) {
		return false;
	}
	$host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
	if ($host === '') {
		$path = isset($parts['path']) ? (string) $parts['path'] : '';
		return $path !== '' && isset($path[0]) && $path[0] === '/' && (!isset($path[1]) || $path[1] !== '/');
	}
	if (!isset($parts['scheme'])) {
		return false;
	}
	$scheme = strtolower((string) $parts['scheme']);
	if ($scheme !== 'http' && $scheme !== 'https') {
		return false;
	}
	$allowed = intersoccer_pm_return_site_hosts();
	if ($allowed === []) {
		return false;
	}
	return in_array($host, $allowed, true);
}

/**
 * Hosts from home_url() and site_url().
 *
 * @return string[]
 */
function intersoccer_pm_return_site_hosts() {
	$hosts = [];
	foreach (['home_url', 'site_url'] as $function_name) {
		if (!function_exists($function_name)) {
			continue;
		}
		$base = $function_name('/');
		if (!is_string($base) || $base === '') {
			continue;
		}
		$host = parse_url($base, PHP_URL_HOST);
		if (is_string($host) && $host !== '') {
			$hosts[] = strtolower($host);
		}
	}
	return array_values(array_unique($hosts));
}

/**
 * Validated `redirect` from POST, then GET.
 *
 * @return string
 */
function intersoccer_pm_request_return_url() {
	foreach (['_POST', '_GET'] as $source) {
		$bag = $source === '_POST' ? $_POST : $_GET;
		if (!isset($bag['redirect']) || !is_string($bag['redirect'])) {
			continue;
		}
		$safe = intersoccer_pm_validate_return_url($bag['redirect']);
		if ($safe !== '') {
			return $safe;
		}
	}
	return '';
}

/**
 * Whether this request carried bf_return=1 beside the redirect value.
 *
 * @return bool
 */
function intersoccer_pm_request_has_booking_return_flag() {
	foreach (['_GET', '_POST'] as $source) {
		$bag = $source === '_GET' ? $_GET : $_POST;
		if (!isset($bag['bf_return']) || is_array($bag['bf_return'])) {
			continue;
		}
		$value = (string) $bag['bf_return'];
		if (function_exists('wp_unslash')) {
			$value = wp_unslash($value);
		}
		if (trim($value) === '1') {
			return true;
		}
	}
	return false;
}

/**
 * Dashboard URL plus a validated redirect, on the current-language account URL.
 *
 * @param string $dashboard_url URL from wc_get_account_endpoint_url('dashboard').
 * @return string
 */
function intersoccer_pm_dashboard_url_with_booking_return($dashboard_url) {
	if (!is_string($dashboard_url) || $dashboard_url === '') {
		return '';
	}
	$args = [];
	$return_url = intersoccer_pm_request_return_url();
	if ($return_url !== '') {
		$args['redirect'] = $return_url;
	}
	if (intersoccer_pm_request_has_booking_return_flag()) {
		$args['bf_return'] = '1';
	}
	if ($args === []) {
		return $dashboard_url;
	}
	if (!function_exists('add_query_arg')) {
		return $dashboard_url;
	}
	$url = add_query_arg($args, $dashboard_url);
	return is_string($url) ? $url : $dashboard_url;
}

/**
 * Destination for the manage-players to dashboard redirect, or '' when there is none.
 *
 * @return string
 */
function intersoccer_pm_manage_players_redirect_target() {
	if (!intersoccer_pm_is_manage_players_request()) {
		return '';
	}
	if (!function_exists('wc_get_account_endpoint_url')) {
		return '';
	}
	$dashboard_url = wc_get_account_endpoint_url('dashboard');
	if (!is_string($dashboard_url) || $dashboard_url === '') {
		return '';
	}
	return intersoccer_pm_dashboard_url_with_booking_return($dashboard_url);
}

/**
 * Script data for the product return. The URL is already validated.
 *
 * wp_localize_script JSON-encodes this value. Do not esc_url() it: that
 * entity-encodes "&" and breaks the query string in JavaScript.
 *
 * @param int $player_count Players already saved for this parent.
 * @return array{returnUrl: string, returnAfterFirstPlayer: string}
 */
function intersoccer_pm_player_script_return_data($player_count) {
	$return_url = intersoccer_pm_request_return_url();
	$first = ($return_url !== '' && (int) $player_count === 0) ? '1' : '0';
	return [
		'returnUrl' => $return_url,
		'returnAfterFirstPlayer' => $first,
	];
}

/**
 * Submit label for the add-player form.
 *
 * The long label is only used when saving will send the parent back, which is
 * the visit that started with no players.
 *
 * @param int         $player_count Players already saved.
 * @param string|null $return_url   Validated return URL, or null to read the request.
 * @return string
 */
function intersoccer_pm_add_player_submit_label($player_count, $return_url = null) {
	if ($return_url === null) {
		$return_url = intersoccer_pm_request_return_url();
	}
	if (is_string($return_url) && $return_url !== '' && (int) $player_count === 0) {
		return __('Save and go back to your booking', 'player-management');
	}
	return __('Save', 'player-management');
}

/**
 * Escaped "Back to your booking" link, or an empty string.
 *
 * @param string|null $return_url Validated return URL, or null to read the request.
 * @return string
 */
function intersoccer_pm_booking_return_link_html($return_url = null) {
	if ($return_url === null) {
		$return_url = intersoccer_pm_request_return_url();
	}
	if (!is_string($return_url) || $return_url === '') {
		return '';
	}
	if (!function_exists('esc_url') || !function_exists('esc_html__')) {
		return '';
	}
	$href = esc_url($return_url);
	if (!is_string($href) || $href === '') {
		return '';
	}
	$label = esc_html__('Back to your booking', 'player-management');
	return '<p class="intersoccer-booking-return"><a class="intersoccer-back-to-booking" href="' . $href . '">' . $label . '</a></p>';
}

/**
 * Redirect legacy manage-players URLs to the My Account Dashboard.
 *
 * Keeps a validated `redirect` (and a sibling bf_return=1) on the current-language dashboard URL.
 *
 * @return void
 */
function intersoccer_pm_redirect_manage_players_to_dashboard() {
	$target = intersoccer_pm_manage_players_redirect_target();
	if ($target === '') {
		return;
	}

	wp_safe_redirect($target);
	exit;
}

/**
 * Suppress the default WooCommerce dashboard description paragraph.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string
 */
function intersoccer_pm_suppress_woocommerce_dashboard_desc($translated, $text, $domain) {
	if ($domain !== 'woocommerce' || !intersoccer_pm_is_woocommerce_dashboard_desc_string($text)) {
		return $translated;
	}
	if (!intersoccer_pm_is_account_dashboard()) {
		return $translated;
	}
	return '';
}

/**
 * Render participants section on the My Account Dashboard.
 *
 * @return void
 */
function intersoccer_pm_render_dashboard_players() {
	echo '<p class="intersoccer-account-dashboard-intro">';
	echo esc_html__(
		'Add your participants below, then book a camp or course.',
		'player-management'
	);
	echo '</p>';

	intersoccer_render_manage_players_content([
		'show_form_title' => 'no',
	]);
}
