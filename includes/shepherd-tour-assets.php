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

    wp_enqueue_style(
        'shepherd-js',
        $base . 'shepherd.css',
        [],
        $version
    );

    wp_enqueue_script(
        'shepherd-js',
        $base . 'shepherd.min.js',
        [],
        $version,
        true
    );
}
