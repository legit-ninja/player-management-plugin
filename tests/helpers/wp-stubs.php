<?php
/**
 * Minimal WordPress function stubs for unit/integration tests.
 *
 * These lightweight implementations allow the PHPUnit suite to execute
 * without bootstrapping a full WordPress environment. Functions return
 * deterministic values that are sufficient for exercising plugin logic.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content');
}

if (!is_dir(WP_CONTENT_DIR)) {
    @mkdir(WP_CONTENT_DIR, 0777, true);
}

// -------------------------------------------------------------------------
// Sanitization helpers
// -------------------------------------------------------------------------
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (!is_string($value)) {
            return $value;
        }

        $filtered = strip_tags($value);
        $filtered = preg_replace('/[\r\n\t\0\x0B]/', '', $filtered);

        return trim($filtered);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        if (!is_string($value)) {
            return $value;
        }

        $filtered = strip_tags($value);

        return trim($filtered);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $raw_key = strtolower($key);

        return preg_replace('/[^a-z0-9_\-]/', '', $raw_key);
    }
}

// -------------------------------------------------------------------------
// Time helpers
// -------------------------------------------------------------------------
if (!function_exists('current_time')) {
    function current_time($type = 'timestamp') {
        $timestamp = time();

        if ($type === 'mysql' || $type === 'Y-m-d H:i:s') {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }

        if ($type === 'timestamp') {
            return $timestamp;
        }

        return date($type, $timestamp);
    }
}

// -------------------------------------------------------------------------
// Options API
// -------------------------------------------------------------------------
$GLOBALS['wp_stub_options'] = [];

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return array_key_exists($option, $GLOBALS['wp_stub_options'])
            ? $GLOBALS['wp_stub_options'][$option]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $GLOBALS['wp_stub_options'][$option] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset($GLOBALS['wp_stub_options'][$option]);

        return true;
    }
}

// -------------------------------------------------------------------------
// Cache API
// -------------------------------------------------------------------------
$GLOBALS['wp_stub_cache'] = [];

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        $bucket = $group . ':' . $key;

        return $GLOBALS['wp_stub_cache'][$bucket] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $expire = 0) {
        $bucket = $group . ':' . $key;
        $GLOBALS['wp_stub_cache'][$bucket] = $value;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        $bucket = $group . ':' . $key;
        unset($GLOBALS['wp_stub_cache'][$bucket]);

        return true;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        $GLOBALS['wp_stub_cache'] = [];

        return true;
    }
}

// -------------------------------------------------------------------------
// User helpers
// -------------------------------------------------------------------------
$GLOBALS['wp_stub_users'] = [];

if (!function_exists('get_users')) {
    function get_users($args = []) {
        return $GLOBALS['wp_stub_users'];
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        $meta = $GLOBALS['wp_stub_users'][$user_id]['meta'][$key] ?? ($single ? '' : []);

        return $single ? $meta : [$meta];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value, $prev_value = '') {
        $GLOBALS['wp_stub_users'][$user_id]['meta'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key, $value = '') {
        unset($GLOBALS['wp_stub_users'][$user_id]['meta'][$key]);

        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return $GLOBALS['wp_stub_current_user_id'] ?? 0;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($GLOBALS['wp_stub_logged_in']);
    }
}

// -------------------------------------------------------------------------
// Misc WordPress helpers
// -------------------------------------------------------------------------
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (is_dir($target)) {
            return true;
        }

        return @mkdir($target, 0777, true);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_login_url')) {
    function wp_login_url($redirect = '') {
        $url = 'https://example.com/wp-login.php';

        if (!empty($redirect)) {
            $url .= '?redirect_to=' . rawurlencode($redirect);
        }

        return $url;
    }
}

if (!function_exists('wp_registration_url')) {
    function wp_registration_url() {
        return 'https://example.com/wp-login.php?action=register';
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '') {
        throw new RuntimeException($message ?: 'wp_die was called.');
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr = []) {
        return rand(1000, 9999);
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr = []) {
        return $postarr['ID'] ?? true;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force_delete = false) {
        return true;
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($user_id, $reassign = null) {
        unset($GLOBALS['wp_stub_users'][$user_id]);

        return true;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        return true;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        return [];
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $field = sprintf('<input type="hidden" name="%s" value="%s" />', $name, wp_create_nonce($action));

        if ($echo) {
            echo $field;
        }

        return $field;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return substr(md5($action . '_nonce'), 0, 10);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return $nonce === wp_create_nonce($action);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value);
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) {
        if (!is_string($data)) {
            return $data;
        }

        $data = trim($data);

        if ($data === '') {
            return $data;
        }

        if ($data[0] !== 'a' && $data[0] !== 'O' && $data[0] !== 's') {
            return $data;
        }

        $unserialized = @unserialize($data);

        return $unserialized === false ? $data : $unserialized;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return ['response' => ['code' => 200]];
    }
}

if (!function_exists('__return_false')) {
    function __return_false() {
        return false;
    }
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
}

if (!function_exists('__return_zero')) {
    function __return_zero() {
        return 0;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($queries) {
        return [];
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return !empty($GLOBALS['wp_stub_ajax_nonce_valid']);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, $object_id = null) {
        if (isset($GLOBALS['wp_stub_user_caps'][$capability])) {
            return (bool) $GLOBALS['wp_stub_user_caps'][$capability];
        }

        return !empty($GLOBALS['wp_stub_user_can']);
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return !empty($GLOBALS['wp_stub_is_admin']);
    }
}

if (!class_exists('WPSendJsonExit', false)) {
    class WPSendJsonExit extends Exception {
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new WPSendJsonExit('error');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new WPSendJsonExit('success');
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_stub_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return add_action($hook, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}
