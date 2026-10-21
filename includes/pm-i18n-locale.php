<?php
/**
 * WPML-aware locale, textdomain loading, and customer admin-ajax URLs.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML language code for the current request (e.g. fr, de), or empty when unavailable.
 *
 * @return string
 */
function intersoccer_pm_get_wpml_language_code() {
    $lang = apply_filters('wpml_current_language', null);
    if (!is_string($lang)) {
        return '';
    }
    $lang = trim($lang);
    return $lang === '' ? '' : $lang;
}

/**
 * Map a WPML language code to a WordPress locale string for .mo files.
 *
 * @param string $code
 * @return string
 */
function intersoccer_pm_locale_from_wpml_code($code) {
    $code = trim((string) $code);
    if ($code === '') {
        return intersoccer_pm_get_request_locale();
    }
    if (strpos($code, '_') !== false) {
        return $code;
    }
    $map = [
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'de' => 'de_DE',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }

    return $code;
}

/**
 * @return string
 */
function intersoccer_pm_get_request_locale() {
    if (function_exists('determine_locale')) {
        return (string) determine_locale();
    }

    return function_exists('get_locale') ? (string) get_locale() : 'en_US';
}

/**
 * Resolve plugin .mo path for a locale (full locale, then short language code).
 *
 * @param string $locale
 * @return string
 */
function intersoccer_pm_mofile_for_locale($locale) {
    $locale = (string) $locale;
    if ($locale === '' || !defined('PLAYER_MANAGEMENT_PATH')) {
        return '';
    }

    $base = PLAYER_MANAGEMENT_PATH . 'languages/player-management-';
    if (file_exists($base . $locale . '.mo')) {
        return $base . $locale . '.mo';
    }

    $short = strlen($locale) >= 2 ? substr($locale, 0, 2) : $locale;
    if (file_exists($base . $short . '.mo')) {
        return $base . $short . '.mo';
    }

    return '';
}

/**
 * Load or reload the player-management textdomain for the current or given locale.
 *
 * @param string|null $locale
 * @return bool
 */
function intersoccer_pm_load_textdomain_for_current_locale($locale = null) {
    $domain = INTERSOCCER_PLAYER_TEXT_DOMAIN;
    $locale = $locale !== null && $locale !== ''
        ? (string) $locale
        : intersoccer_pm_get_request_locale();

    $previous = isset($GLOBALS['intersoccer_pm_textdomain_locale'])
        ? (string) $GLOBALS['intersoccer_pm_textdomain_locale']
        : '';

    $loaded = function_exists('is_textdomain_loaded') && is_textdomain_loaded($domain);
    if ($loaded && $previous === $locale) {
        return true;
    }

    if ($loaded && function_exists('unload_textdomain')) {
        unload_textdomain($domain, true);
    }

    $mofile = intersoccer_pm_mofile_for_locale($locale);
    $ok = false;
    if ($mofile !== '' && function_exists('load_textdomain')) {
        $ok = (bool) load_textdomain($domain, $mofile, $locale);
    }

    if (!$ok && function_exists('load_plugin_textdomain')) {
        $ok = (bool) load_plugin_textdomain(
            $domain,
            false,
            dirname(plugin_basename(PLAYER_MANAGEMENT_PATH . 'player-management.php')) . '/languages/'
        );
    }

    $GLOBALS['intersoccer_pm_textdomain_locale'] = $locale;

    return $ok;
}

/**
 * admin-ajax.php URL with optional WPML lang query arg for storefront requests.
 *
 * @param array<string, string> $extra_args
 * @param bool                  $include_wpml_lang
 * @return string
 */
function intersoccer_pm_admin_ajax_url(array $extra_args = [], $include_wpml_lang = true) {
    $args = $extra_args;
    if ($include_wpml_lang) {
        $lang = intersoccer_pm_get_wpml_language_code();
        if ($lang !== '') {
            $args['lang'] = $lang;
        }
    }

    $url = admin_url('admin-ajax.php');
    if ($args !== []) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

/**
 * Shared ajax_url / nonce_refresh_url (and lang when WPML is active) for customer scripts.
 *
 * @param bool $include_wpml_lang
 * @return array<string, string>
 */
function intersoccer_pm_customer_script_localize_base($include_wpml_lang = true) {
    $data = [
        'ajax_url' => intersoccer_pm_admin_ajax_url([], $include_wpml_lang),
        'nonce_refresh_url' => intersoccer_pm_admin_ajax_url(
            ['action' => 'intersoccer_refresh_nonce'],
            $include_wpml_lang
        ),
    ];

    if ($include_wpml_lang) {
        $lang = intersoccer_pm_get_wpml_language_code();
        if ($lang !== '') {
            $data['lang'] = $lang;
        }
    }

    return $data;
}

/**
 * Switch WPML + reload PM textdomain on customer admin-ajax when lang is present.
 */
function intersoccer_pm_bootstrap_customer_ajax_language() {
    if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
        return;
    }

    if (function_exists('intersoccer_pm_is_admin_english_context')
        && intersoccer_pm_is_admin_english_context()
    ) {
        return;
    }

    $lang = '';
    if (isset($_REQUEST['lang'])) {
        $lang = sanitize_text_field(wp_unslash((string) $_REQUEST['lang']));
    }
    if ($lang === '') {
        return;
    }

    if (has_action('wpml_switch_language')) {
        do_action('wpml_switch_language', $lang);
    }

    intersoccer_pm_load_textdomain_for_current_locale(intersoccer_pm_locale_from_wpml_code($lang));
}

/**
 * Register textdomain and AJAX locale hooks.
 */
function intersoccer_pm_register_i18n_locale_hooks() {
    add_action('init', 'intersoccer_pm_load_textdomain_for_current_locale', 20);
    add_action('wpml_language_has_switched', 'intersoccer_pm_load_textdomain_for_current_locale', 10, 0);
    add_action('change_locale', 'intersoccer_pm_load_textdomain_for_current_locale', 10, 0);
    add_action('admin_init', 'intersoccer_pm_bootstrap_customer_ajax_language', 0);
}

intersoccer_pm_register_i18n_locale_hooks();
