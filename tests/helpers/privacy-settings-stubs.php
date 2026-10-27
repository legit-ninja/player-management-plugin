<?php
/**
 * Test-only helpers for privacy settings unit tests (loaded from PrivacySettingsTest only).
 */

if (! isset($GLOBALS['wp_stub_filters'])) {
	$GLOBALS['wp_stub_filters'] = [];
}

if (! isset($GLOBALS['wp_stub_register_setting_calls'])) {
	$GLOBALS['wp_stub_register_setting_calls'] = [];
}

if (! isset($GLOBALS['wp_stub_user_objects'])) {
	$GLOBALS['wp_stub_user_objects'] = [];
}

if (! isset($GLOBALS['wp_stub_user_caps_by_user'])) {
	$GLOBALS['wp_stub_user_caps_by_user'] = [];
}

if (! function_exists('intersoccer_privacy_test_apply_filters')) {
	/**
	 * Run filters registered via wp-stub add_filter / privacy_settings_stub_add_filter.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	function intersoccer_privacy_test_apply_filters($hook, $value, ...$args) {
		$callbacks = [];
		if (! empty($GLOBALS['wp_stub_filters'][ $hook ])) {
			$callbacks = array_merge($callbacks, $GLOBALS['wp_stub_filters'][ $hook ]);
		}
		if (! empty($GLOBALS['wp_stub_actions'][ $hook ])) {
			$callbacks = array_merge($callbacks, $GLOBALS['wp_stub_actions'][ $hook ]);
		}

		if ($callbacks === []) {
			return $value;
		}

		usort(
			$callbacks,
			static function ($a, $b) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		foreach ($callbacks as $entry) {
			$callback = $entry['callback'];
			$accepted = max(1, (int) $entry['accepted_args']);
			$invoke   = array_merge([ $value ], $args);
			$invoke   = array_slice($invoke, 0, $accepted);
			$value    = call_user_func_array($callback, $invoke);
		}

		return $value;
	}
}

if (! function_exists('privacy_settings_stub_add_filter')) {
	function privacy_settings_stub_add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
		$GLOBALS['wp_stub_filters'][ $hook ][] = [
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}
}

if (! function_exists('register_setting')) {
	function register_setting($option_group, $option_name, $args = []) {
		$GLOBALS['wp_stub_register_setting_calls'][] = [
			'group' => $option_group,
			'name'  => $option_name,
			'args'  => $args,
		];

		return true;
	}
}

if (! function_exists('add_option')) {
	function add_option($option, $value = '', $deprecated = '', $autoload = 'yes') {
		if (array_key_exists($option, $GLOBALS['wp_stub_options'])) {
			return false;
		}

		$GLOBALS['wp_stub_options'][ $option ] = $value;

		return true;
	}
}

if (! function_exists('get_userdata')) {
	function get_userdata($user_id) {
		if (empty($GLOBALS['wp_stub_user_objects'][ $user_id ])) {
			return false;
		}

		return $GLOBALS['wp_stub_user_objects'][ $user_id ];
	}
}

if (! function_exists('user_can')) {
	function user_can($user, $capability) {
		$user_id = is_object($user) ? (int) $user->ID : (int) $user;
		if (! empty($GLOBALS['wp_stub_user_caps_by_user'][ $user_id ][ $capability ])) {
			return true;
		}

		if (isset($GLOBALS['wp_stub_user_caps'][ $capability ])) {
			return (bool) $GLOBALS['wp_stub_user_caps'][ $capability ];
		}

		return false;
	}
}

if (! function_exists('wp_roles')) {
	function wp_roles() {
		if (! isset($GLOBALS['wp_stub_wp_roles'])) {
			$GLOBALS['wp_stub_wp_roles'] = (object) [
				'roles' => [
					'administrator' => [],
					'shop_manager'  => [],
					'coach'         => [],
					'customer'      => [],
				],
			];
		}

		return $GLOBALS['wp_stub_wp_roles'];
	}
}

if (! function_exists('add_query_arg')) {
	function add_query_arg($args, $url = '') {
		if (is_array($args)) {
			$query = http_build_query($args);
			$sep   = (strpos((string) $url, '?') !== false) ? '&' : '?';

			return (string) $url . $sep . $query;
		}

		return (string) $url;
	}
}

if (! function_exists('wp_timezone')) {
	function wp_timezone() {
		if (! empty($GLOBALS['wp_stub_timezone'])) {
			return new DateTimeZone($GLOBALS['wp_stub_timezone']);
		}

		return new DateTimeZone('UTC');
	}
}

