<?php
/**
 * Shepherd.js tour vendor assets (pinned local copy).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pinned Shepherd.js release (MIT). See assets/vendor/shepherd/{version}/LICENSE.
 */
function intersoccer_pm_shepherd_js_version(): string {
    return '11.2.0';
}

/**
 * WordPress script/style handle for pinned Shepherd assets (unique to this plugin).
 */
function intersoccer_pm_shepherd_asset_handle(): string {
    return 'intersoccer-pm-shepherd';
}

/**
 * Whether the Player Management link widget should run the Shepherd tour.
 *
 * Uses stored Elementor settings (see get_data('settings')), not get_settings_for_display().
 * Elementor omits control values equal to the control default when saving, so legacy widgets
 * saved with show_tour on often have no show_tour key; treat missing as enabled.
 *
 * @param array<string, mixed> $stored_settings Raw widget settings from Elementor JSON.
 */
function intersoccer_pm_player_link_widget_show_tour_enabled(array $stored_settings): bool {
    if (!array_key_exists('show_tour', $stored_settings)) {
        return true;
    }

    return $stored_settings['show_tour'] === 'yes';
}

/**
 * WooCommerce contexts where third-party tour scripts must not load.
 */
function intersoccer_pm_shepherd_is_wc_blocked_context(): bool {
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }
    if (function_exists('is_cart') && is_cart()) {
        return true;
    }
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
        return true;
    }

    return false;
}

/**
 * Enqueue pinned Shepherd.js CSS/JS from the plugin bundle (never CDN).
 */
function intersoccer_pm_enqueue_shepherd_vendor_assets(): void {
    if (intersoccer_pm_shepherd_is_wc_blocked_context()) {
        return;
    }

    $version = intersoccer_pm_shepherd_js_version();
    $base    = PLAYER_MANAGEMENT_URL . 'assets/vendor/shepherd/' . $version . '/';

    $handle = intersoccer_pm_shepherd_asset_handle();

    wp_enqueue_style(
        $handle,
        $base . 'shepherd.css',
        [],
        $version
    );

    wp_enqueue_script(
        $handle,
        $base . 'shepherd.min.js',
        [],
        $version,
        true
    );
}
