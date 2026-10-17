<?php
/**
 * Admin notice when medical / DOB role access has not been configured.
 */

defined('ABSPATH') || exit;

if (!defined('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META')) {
	define('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META', 'intersoccer_privacy_medical_roles_notice_dismissed');
}

if (!defined('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_AJAX_ACTION')) {
	define('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_AJAX_ACTION', 'intersoccer_dismiss_privacy_medical_notice');
}

if (!defined('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_NONCE_ACTION')) {
	define('INTERSOCCER_PRIVACY_MEDICAL_NOTICE_NONCE_ACTION', 'intersoccer_dismiss_privacy_medical_notice');
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
		// Slug matches InterSoccer_Player_Management_Settings::PAGE_SLUG (Players → Settings).
		$default = add_query_arg(
			[
				'page' => 'intersoccer-players-settings',
				'tab'  => 'general',
			],
			admin_url('admin.php')
		);

		/**
		 * URL for the medical roles settings screen.
		 *
		 * @param string $url Players settings page URL.
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

		printf(
			'<div class="notice notice-warning is-dismissible intersoccer-privacy-medical-notice"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__(
				'Medical and date-of-birth access roles are not configured yet. Staff access will follow legacy rules until you save the medical roles setting.',
				'player-management'
			),
			esc_url($settings_url),
			esc_html__('Configure medical access', 'player-management')
		);
	}
}

if (!function_exists('intersoccer_privacy_ajax_dismiss_medical_roles_notice')) {
	function intersoccer_privacy_ajax_dismiss_medical_roles_notice(): void {
		if (
			! check_ajax_referer(
				INTERSOCCER_PRIVACY_MEDICAL_NOTICE_NONCE_ACTION,
				'nonce',
				false
			)
		) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid security token.', 'player-management' ) ],
				403
			);
		}

		if (
			! function_exists('get_current_user_id')
			|| ! function_exists('current_user_can')
			|| ! function_exists('update_user_meta')
		) {
			wp_send_json_error(
				[ 'message' => __( 'Forbidden.', 'player-management' ) ],
				403
			);
		}

		if (! current_user_can(intersoccer_privacy_medical_notice_capability())) {
			wp_send_json_error(
				[ 'message' => __( 'Forbidden.', 'player-management' ) ],
				403
			);
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			wp_send_json_error(
				[ 'message' => __( 'Forbidden.', 'player-management' ) ],
				403
			);
		}

		update_user_meta($user_id, INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META, '1');
		wp_send_json_success();
	}
}

if (!function_exists('intersoccer_privacy_enqueue_medical_notice_assets')) {
	function intersoccer_privacy_enqueue_medical_notice_assets(): void {
		if (! function_exists('is_admin') || ! is_admin()) {
			return;
		}

		if (! intersoccer_privacy_should_show_medical_roles_notice()) {
			return;
		}

		if (! defined('PLAYER_MANAGEMENT_URL') || ! defined('PLAYER_MANAGEMENT_VERSION')) {
			return;
		}

		$handle = 'intersoccer-privacy-medical-notice';
		$src    = PLAYER_MANAGEMENT_URL . 'assets/js/privacy-medical-notice-dismiss.js';

		wp_enqueue_script($handle, $src, [ 'jquery' ], PLAYER_MANAGEMENT_VERSION, true);

		wp_localize_script(
			$handle,
			'intersoccerPrivacyMedicalNotice',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'action'  => INTERSOCCER_PRIVACY_MEDICAL_NOTICE_AJAX_ACTION,
				'nonce'   => wp_create_nonce(INTERSOCCER_PRIVACY_MEDICAL_NOTICE_NONCE_ACTION),
			]
		);
	}
}

add_action('wp_ajax_' . INTERSOCCER_PRIVACY_MEDICAL_NOTICE_AJAX_ACTION, 'intersoccer_privacy_ajax_dismiss_medical_roles_notice');
add_action('admin_enqueue_scripts', 'intersoccer_privacy_enqueue_medical_notice_assets');
add_action('admin_notices', 'intersoccer_privacy_render_medical_roles_admin_notice');
