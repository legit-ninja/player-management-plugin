<?php
/**
 * Chart.js vendor assets (pinned local copy).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pinned Chart.js release (MIT). See assets/vendor/chart.js/{version}/LICENSE.
 */
function intersoccer_pm_chart_js_version(): string {
    return '3.9.1';
}

/**
 * WordPress script handle for pinned Chart.js (unique to this plugin).
 */
function intersoccer_pm_chart_js_asset_handle(): string {
    return 'intersoccer-pm-chart-js';
}

/**
 * Enqueue pinned Chart.js from the plugin bundle (never CDN).
 */
function intersoccer_pm_enqueue_chart_js_vendor_asset(): void {
    $version = intersoccer_pm_chart_js_version();
    $base    = PLAYER_MANAGEMENT_URL . 'assets/vendor/chart.js/' . $version . '/';

    wp_enqueue_script(
        intersoccer_pm_chart_js_asset_handle(),
        $base . 'chart.min.js',
        [],
        $version,
        true
    );
}
