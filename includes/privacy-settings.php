<?php
/**
 * Privacy settings: medical role access and data retention (options + getters).
 *
 * Loaded from player-management.php only when WooCommerce is active.
 */

defined('ABSPATH') || exit;

if (!defined('INTERSOCCER_OPT_MEDICAL_ROLES')) {
	define('INTERSOCCER_OPT_MEDICAL_ROLES', 'intersoccer_medical_roles');
}
if (!defined('INTERSOCCER_OPT_RETENTION_MONTHS')) {
	define('INTERSOCCER_OPT_RETENTION_MONTHS', 'intersoccer_retention_months');
}
if (!defined('INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS')) {
	define('INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS', 'intersoccer_retention_inactive_account_months');
}
if (!defined('INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION')) {
	define('INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION', 'intersoccer_privacy_settings_schema_version');
}
if (!defined('INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION')) {
	define('INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION', 1);
}

if (!function_exists('intersoccer_privacy_settings_defaults')) {
	/**
	 * Default values for privacy options (option name => default).
	 *
	 * @return array<string, mixed>
	 */
	function intersoccer_privacy_settings_defaults(): array {
		return [
			INTERSOCCER_OPT_MEDICAL_ROLES        => null,
			INTERSOCCER_OPT_RETENTION_MONTHS     => 0,
			INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS => 0,
			INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION => INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION,
		];
	}
}

if (!function_exists('intersoccer_sanitize_medical_roles')) {
	/**
	 * @param mixed $value Raw medical roles value.
	 * @return string[]
	 */
	function intersoccer_sanitize_medical_roles($value): array {
		if (is_array($value)) {
			$slugs = $value;
		} elseif (is_string($value)) {
			$parts = explode(',', $value);
			$slugs = array_map('trim', $parts);
		} else {
			$slugs = [];
		}

		$slugs = array_map('sanitize_key', $slugs);
		$slugs = array_filter($slugs, static function ($slug) {
			return $slug !== '';
		});

		if (function_exists('wp_roles')) {
			$wp_roles = wp_roles();
			if ($wp_roles && isset($wp_roles->roles) && is_array($wp_roles->roles)) {
				$valid = array_keys($wp_roles->roles);
				$slugs = array_values(array_intersect($slugs, $valid));
			}
		}

		$slugs[] = 'administrator';
		$slugs = array_unique($slugs);
		sort($slugs);

		return array_values($slugs);
	}
}

if (!function_exists('intersoccer_sanitize_retention_months')) {
	/**
	 * @param mixed $value Raw retention months value.
	 */
	function intersoccer_sanitize_retention_months($value): int {
		if (!is_numeric($value)) {
			return 0;
		}

		if ((float) $value < 0) {
			return 0;
		}

		$months = absint($value);
		if ($months > 120) {
			$months = 120;
		}

		return $months;
	}
}

if (!function_exists('intersoccer_sanitize_privacy_schema_version')) {
	/**
	 * @param mixed $value Stored schema version.
	 */
	function intersoccer_sanitize_privacy_schema_version($value): int {
		$stored = get_option(INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION);
		$current = is_numeric($stored) ? (int) $stored : INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION;
		$new = is_numeric($value) ? (int) $value : INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION;

		return max($current, $new, INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION);
	}
}

if (!function_exists('intersoccer_get_medical_roles')) {
	/**
	 * @return string[]
	 */
	function intersoccer_get_medical_roles(): array {
		$raw = get_option(INTERSOCCER_OPT_MEDICAL_ROLES, null);
		if ($raw === null) {
			$value = [ 'administrator' ];
		} else {
			$value = intersoccer_sanitize_medical_roles($raw);
		}

		$value = apply_filters('intersoccer_medical_roles', $value);

		return intersoccer_sanitize_medical_roles($value);
	}
}

if (!function_exists('intersoccer_medical_roles_is_configured')) {
	function intersoccer_medical_roles_is_configured(): bool {
		return get_option(INTERSOCCER_OPT_MEDICAL_ROLES, null) !== null;
	}
}

if (!function_exists('intersoccer_user_has_medical_role')) {
	function intersoccer_user_has_medical_role(int $user_id): bool {
		if ($user_id <= 0) {
			return false;
		}

		$user = get_userdata($user_id);
		if (!$user || empty($user->roles) || !is_array($user->roles)) {
			return false;
		}

		$allowed = intersoccer_get_medical_roles();

		return (bool) array_intersect($user->roles, $allowed);
	}
}

if (!function_exists('intersoccer_get_retention_months')) {
	function intersoccer_get_retention_months(): int {
		$defaults = intersoccer_privacy_settings_defaults();
		$raw = get_option(INTERSOCCER_OPT_RETENTION_MONTHS, $defaults[ INTERSOCCER_OPT_RETENTION_MONTHS ]);
		$value = intersoccer_sanitize_retention_months($raw);
		$value = apply_filters('intersoccer_retention_months', $value);

		return intersoccer_sanitize_retention_months($value);
	}
}

if (!function_exists('intersoccer_is_retention_enabled')) {
	function intersoccer_is_retention_enabled(): bool {
		return intersoccer_get_retention_months() > 0;
	}
}

if (!function_exists('intersoccer_get_retention_inactive_account_months')) {
	function intersoccer_get_retention_inactive_account_months(): int {
		$defaults = intersoccer_privacy_settings_defaults();
		$raw = get_option(
			INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS,
			$defaults[ INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS ]
		);
		$value = intersoccer_sanitize_retention_months($raw);
		$value = apply_filters('intersoccer_retention_inactive_account_months', $value);

		return intersoccer_sanitize_retention_months($value);
	}
}

if (!function_exists('intersoccer_get_retention_cutoff_date')) {
	/**
	 * @param DateTimeImmutable|null $now Reference instant (site timezone applied when set).
	 */
	function intersoccer_get_retention_cutoff_date(?DateTimeImmutable $now = null): ?string {
		$months = intersoccer_get_retention_months();
		if ($months <= 0) {
			return null;
		}

		$tz = wp_timezone();
		if ($now === null) {
			$now = new DateTimeImmutable('now', $tz);
		} else {
			$now = $now->setTimezone($tz);
		}

		$cutoff = $now->modify(sprintf('-%d months', $months));

		return $cutoff->format('Y-m-d');
	}
}

if (!function_exists('intersoccer_privacy_settings_schema_version')) {
	function intersoccer_privacy_settings_schema_version(): int {
		$raw = get_option(
			INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION,
			INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION
		);

		if (!is_numeric($raw)) {
			return INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION;
		}

		return max((int) $raw, INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION);
	}
}

if (!function_exists('intersoccer_filter_can_view_medical')) {
	/**
	 * Filter callback for intersoccer_can_view_medical.
	 *
	 * @param mixed $allowed Base allowance from the caller.
	 * @param mixed $user_id   User ID.
	 * @return mixed
	 */
	function intersoccer_filter_can_view_medical($allowed, $user_id) {
		if (!intersoccer_medical_roles_is_configured()) {
			return $allowed;
		}

		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		if (!get_userdata($user_id)) {
			return false;
		}

		return intersoccer_user_has_medical_role($user_id) ? true : false;
	}
}

if (!function_exists('intersoccer_privacy_settings_maybe_upgrade')) {
	function intersoccer_privacy_settings_maybe_upgrade(): void {
		$version = intersoccer_privacy_settings_schema_version();
		if ($version < INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION) {
			return;
		}
		// v1: no migrations.
	}
}

if (!function_exists('intersoccer_privacy_settings_register_options')) {
	function intersoccer_privacy_settings_register_options(): void {
		$defaults = intersoccer_privacy_settings_defaults();

		register_setting(
			'intersoccer_privacy',
			INTERSOCCER_OPT_MEDICAL_ROLES,
			[
				'type'              => 'array',
				'sanitize_callback' => 'intersoccer_sanitize_medical_roles',
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'intersoccer_privacy',
			INTERSOCCER_OPT_RETENTION_MONTHS,
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intersoccer_sanitize_retention_months',
				'default'           => $defaults[ INTERSOCCER_OPT_RETENTION_MONTHS ],
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'intersoccer_privacy',
			INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS,
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intersoccer_sanitize_retention_months',
				'default'           => $defaults[ INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS ],
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'intersoccer_privacy',
			INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION,
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intersoccer_sanitize_privacy_schema_version',
				'default'           => $defaults[ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ],
				'show_in_rest'      => false,
			]
		);
	}
}

if (!function_exists('intersoccer_privacy_settings_bootstrap_schema_version')) {
	function intersoccer_privacy_settings_bootstrap_schema_version(): void {
		if (get_option(INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, null) === null) {
			add_option(
				INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION,
				INTERSOCCER_PRIVACY_SETTINGS_SCHEMA_VERSION,
				'',
				'yes'
			);
		}
	}
}

if (!function_exists('intersoccer_privacy_settings_init')) {
	function intersoccer_privacy_settings_init(): void {
		intersoccer_privacy_settings_register_options();
		intersoccer_privacy_settings_bootstrap_schema_version();
		intersoccer_privacy_settings_maybe_upgrade();
	}
}

add_action('init', 'intersoccer_privacy_settings_init');
add_filter('intersoccer_can_view_medical', 'intersoccer_filter_can_view_medical', 10, 2);
