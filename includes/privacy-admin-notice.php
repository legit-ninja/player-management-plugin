<?php
/**
 * Admin notice when medical / DOB role access has not been configured.
 */

defined('ABSPATH') || exit;

if (!defined('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META')) {
	define('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META', 'intersoccer_privacy_medical_roles_notice_dismissed');
}

if (!function_exists('intersoccer_privacy_medical_notice_capability')) {
	function intersoccer_privacy_medical_notice_capability(): string {
		/**
		 * Capability required to see the medical roles configuration notice.
		 *
		 * @param string $capability Default manage_options.
		 */
		return (string) apply_filters('intersoccer_privacy_medical_notice_capability', 'manage_options');
	}
}

if (!function_exists('intersoccer_privacy_medical_settings_admin_url')) {
	function intersoccer_privacy_medical_settings_admin_url(): string {
		$default = admin_url('admin.php?page=intersoccer-privacy-medical');

		/**
		 * URL for the medical roles settings screen.
		 *
		 * @param string $url Default admin page slug.
		 */
		return (string) apply_filters('intersoccer_privacy_medical_settings_admin_url', $default);
	}
}

if (!function_exists('intersoccer_privacy_should_show_medical_roles_notice')) {
	function intersoccer_privacy_should_show_medical_roles_notice(): bool {
		if (intersoccer_medical_roles_is_configured()) {
			return false;
		}

		if (! function_exists('get_current_user_id') || ! function_exists('current_user_can')) {
			return false;
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return false;
		}

		if (! current_user_can(intersoccer_privacy_medical_notice_capability())) {
			return false;
		}

		if (function_exists('get_user_meta')) {
			$dismissed = get_user_meta($user_id, INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META, true);
			if (! empty($dismissed)) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('intersoccer_privacy_render_medical_roles_admin_notice')) {
	function intersoccer_privacy_render_medical_roles_admin_notice(): void {
		if (! intersoccer_privacy_should_show_medical_roles_notice()) {
			return;
		}

		$settings_url = intersoccer_privacy_medical_settings_admin_url();
		$dismiss_url  = wp_nonce_url(
			add_query_arg('intersoccer_dismiss_privacy_medical_notice', '1'),
			'intersoccer_dismiss_privacy_medical_notice',
			'_intersoccer_privacy_notice_nonce'
		);

		printf(
			'<div class="notice notice-warning is-dismissible" data-dismiss-url="%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_url($dismiss_url),
			esc_html__(
				'Medical and date-of-birth access roles are not configured yet. Staff access will follow legacy rules until you save the medical roles setting.',
				'player-management'
			),
			esc_url($settings_url),
			esc_html__('Configure medical access', 'player-management')
		);
	}
}

if (!function_exists('intersoccer_privacy_handle_medical_notice_dismiss')) {
	function intersoccer_privacy_handle_medical_notice_dismiss(): void {
		if (
			empty($_GET['intersoccer_dismiss_privacy_medical_notice'])
			|| ! function_exists('get_current_user_id')
			|| ! function_exists('update_user_meta')
		) {
			return;
		}

		if (
			! isset($_GET['_intersoccer_privacy_notice_nonce'])
			|| ! wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['_intersoccer_privacy_notice_nonce'])),
				'intersoccer_dismiss_privacy_medical_notice'
			)
		) {
			return;
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		update_user_meta($user_id, INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META, '1');
	}
}

add_action('admin_init', 'intersoccer_privacy_handle_medical_notice_dismiss');
add_action('admin_notices', 'intersoccer_privacy_render_medical_roles_admin_notice');
