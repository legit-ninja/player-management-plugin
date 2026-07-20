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
	return intersoccer_pm_is_account_dashboard() || intersoccer_pm_is_manage_players_request();
}

/**
 * Redirect legacy manage-players URLs to the My Account Dashboard.
 *
 * @return void
 */
function intersoccer_pm_redirect_manage_players_to_dashboard() {
	if (!intersoccer_pm_is_manage_players_request()) {
		return;
	}
	if (!function_exists('wc_get_account_endpoint_url')) {
		return;
	}

	$dashboard_url = wc_get_account_endpoint_url('dashboard');
	if (!$dashboard_url) {
		return;
	}

	wp_safe_redirect($dashboard_url);
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
