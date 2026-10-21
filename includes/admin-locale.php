<?php
/**
 * Force English strings in wp-admin for the player-management text domain.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether PM admin UI should bypass translations and show English msgids.
 *
 * @return bool
 */
function intersoccer_pm_is_admin_english_context() {
    $force = is_admin()
        && (!function_exists('wp_doing_ajax') || !wp_doing_ajax() || intersoccer_pm_is_admin_ajax_request())
        && (!function_exists('is_locale_switched') || !is_locale_switched());

    /**
     * Filter whether PM admin screens should force English for this text domain.
     *
     * @param bool $force
     */
    return (bool) apply_filters('intersoccer_pm_admin_force_english', $force);
}

/**
 * True when the current AJAX request originated from wp-admin (not the storefront).
 *
 * @return bool
 */
function intersoccer_pm_is_admin_ajax_request() {
    if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
        return false;
    }

    $referer = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
    if ($referer === '') {
        return true;
    }

    $admin_root = admin_url();
    return strpos($referer, $admin_root) === 0;
}

/**
 * Return the original (English) catalog string when admin English is active.
 *
 * @param string $translation
 * @param string $single
 * @param string $domain
 * @return string
 */
function intersoccer_pm_filter_admin_english_gettext($translation, $single, $domain) {
    if ($domain !== INTERSOCCER_PLAYER_TEXT_DOMAIN || !intersoccer_pm_is_admin_english_context()) {
        return $translation;
    }
    return $single;
}

/**
 * @param string $translation
 * @param string $single
 * @param string $plural
 * @param int    $number
 * @param string $domain
 * @return string
 */
function intersoccer_pm_filter_admin_english_ngettext($translation, $single, $plural, $number, $domain) {
    if ($domain !== INTERSOCCER_PLAYER_TEXT_DOMAIN || !intersoccer_pm_is_admin_english_context()) {
        return $translation;
    }
    return (int) $number === 1 ? $single : $plural;
}

/**
 * @param string $translation
 * @param string $single
 * @param string $context
 * @param string $domain
 * @return string
 */
function intersoccer_pm_filter_admin_english_gettext_with_context($translation, $single, $context, $domain) {
    if ($domain !== INTERSOCCER_PLAYER_TEXT_DOMAIN || !intersoccer_pm_is_admin_english_context()) {
        return $translation;
    }
    return $single;
}

/**
 * @param string $translation
 * @param string $single
 * @param string $plural
 * @param int    $number
 * @param string $context
 * @param string $domain
 * @return string
 */
function intersoccer_pm_filter_admin_english_ngettext_with_context($translation, $single, $plural, $number, $context, $domain) {
    if ($domain !== INTERSOCCER_PLAYER_TEXT_DOMAIN || !intersoccer_pm_is_admin_english_context()) {
        return $translation;
    }
    return (int) $number === 1 ? $single : $plural;
}

add_filter('gettext_' . INTERSOCCER_PLAYER_TEXT_DOMAIN, 'intersoccer_pm_filter_admin_english_gettext', 20, 3);
add_filter('ngettext_' . INTERSOCCER_PLAYER_TEXT_DOMAIN, 'intersoccer_pm_filter_admin_english_ngettext', 20, 5);
add_filter('gettext_with_context_' . INTERSOCCER_PLAYER_TEXT_DOMAIN, 'intersoccer_pm_filter_admin_english_gettext_with_context', 20, 4);
add_filter('ngettext_with_context_' . INTERSOCCER_PLAYER_TEXT_DOMAIN, 'intersoccer_pm_filter_admin_english_ngettext_with_context', 20, 6);
