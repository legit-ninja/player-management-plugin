<?php
/**
 * Unit tests for includes/privacy-settings.php.
 */

require_once __DIR__ . '/../helpers/TestCase.php';

use WP_Mock\InvokedFilterValue;

class PrivacySettingsTest extends InterSoccer_Test_Case {

	private static bool $privacy_plugin_booted = false;

	protected function bootPrivacyPluginOnce(): void {
		if (self::$privacy_plugin_booted) {
			return;
		}

		require_once __DIR__ . '/../helpers/privacy-settings-stubs.php';
		require_once __DIR__ . '/../../includes/privacy-settings.php';
		self::$privacy_plugin_booted = true;
	}

	protected function registerSensitiveAccessFilterMocks(): void {
		$callback = static function ( $allowed, $user_id ) {
			return intersoccer_privacy_filter_can_view_sensitive( $allowed, $user_id );
		};
		$invoked = new InvokedFilterValue( $callback );

		foreach ( [ true, false ] as $allowed ) {
			for ( $uid = 0; $uid <= 100; $uid++ ) {
				WP_Mock::onFilter( 'intersoccer_can_view_medical' )->with( $allowed, $uid )->reply( $invoked );
				WP_Mock::onFilter( 'intersoccer_can_view_dob' )->with( $allowed, $uid )->reply( $invoked );
			}
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->bootPrivacyPluginOnce();
		$this->registerSensitiveAccessFilterMocks();
		$GLOBALS['wp_stub_options']           = [];
		$GLOBALS['wp_stub_user_objects']      = [];
		$GLOBALS['wp_stub_register_setting_calls'] = [];
		$GLOBALS['wp_stub_filters']           = [];
		$GLOBALS['wp_stub_user_caps_by_user'] = [];
		$GLOBALS['wp_stub_timezone']          = 'UTC';
		$GLOBALS['wp_stub_current_user_id']   = 0;
		$GLOBALS['wp_stub_user_can']          = false;

	}

	protected function setUserRoles(int $user_id, array $roles): void {
		$GLOBALS['wp_stub_user_objects'][ $user_id ] = (object) [
			'ID'    => $user_id,
			'roles' => $roles,
		];
	}

	protected function setUserCaps(int $user_id, array $caps): void {
		$GLOBALS['wp_stub_user_caps_by_user'][ $user_id ] = $caps;
	}

	protected function configureMedicalRoles(array $roles): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_MEDICAL_ROLES ] = $roles;
	}

	public function test_defaults_when_options_absent(): void {
		$this->assertSame( [ 'administrator' ], intersoccer_get_medical_roles() );
		$this->assertFalse( intersoccer_medical_roles_is_configured() );
		$this->assertSame( 0, intersoccer_get_retention_months() );
		$this->assertSame( 0, intersoccer_get_retention_inactive_account_months() );
		$this->assertFalse( intersoccer_is_retention_enabled() );
		$this->assertNull( intersoccer_get_retention_cutoff_date() );
		$this->assertSame( 1, intersoccer_privacy_settings_schema_version() );
	}

	public function test_sanitize_medical_roles_from_array_and_string(): void {
		$this->assertSame(
			[ 'administrator', 'coach' ],
			intersoccer_sanitize_medical_roles( [ 'coach', 'administrator', 'coach' ] )
		);
		$this->assertSame(
			[ 'administrator', 'shop_manager' ],
			intersoccer_sanitize_medical_roles( 'shop_manager, administrator' )
		);
	}

	public function test_sanitize_medical_roles_drops_unknown_and_junk(): void {
		$this->assertSame( [ 'administrator' ], intersoccer_sanitize_medical_roles( [ 'not_a_role' ] ) );
		$this->assertSame( [ 'administrator' ], intersoccer_sanitize_medical_roles( null ) );
		$this->assertSame( [ 'administrator' ], intersoccer_sanitize_medical_roles( 5 ) );
		$this->assertSame( [ 'administrator' ], intersoccer_sanitize_medical_roles( (object) [ 'x' => 1 ] ) );
	}

	public function test_sanitize_medical_roles_applies_sanitize_key(): void {
		$this->assertSame(
			[ 'administrator', 'coach' ],
			intersoccer_sanitize_medical_roles( [ 'CoAcH' ] )
		);
	}

	public function test_sanitize_retention_months(): void {
		$this->assertSame( 0, intersoccer_sanitize_retention_months( -1 ) );
		$this->assertSame( 0, intersoccer_sanitize_retention_months( 'abc' ) );
		$this->assertSame( 1, intersoccer_sanitize_retention_months( 1.5 ) );
		$this->assertSame( 12, intersoccer_sanitize_retention_months( '12' ) );
		$this->assertSame( 0, intersoccer_sanitize_retention_months( 0 ) );
		$this->assertSame( 120, intersoccer_sanitize_retention_months( 120 ) );
		$this->assertSame( 120, intersoccer_sanitize_retention_months( 121 ) );
	}

	public function test_getters_re_sanitize_bad_stored_values(): void {
		$this->configureMedicalRoles( [ 'bogus_role', 'coach' ] );
		$this->assertSame( [ 'administrator', 'coach' ], intersoccer_get_medical_roles() );

		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 999;
		$this->assertSame( 120, intersoccer_get_retention_months() );
	}

	public function test_corrupt_medical_option_not_configured_for_access(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_MEDICAL_ROLES ] = 'not-an-array';
		$this->assertFalse( intersoccer_medical_roles_is_configured() );
		$this->setUserRoles( 2, [ 'shop_manager' ] );
		$this->setUserCaps( 2, [ 'manage_woocommerce' => true ] );
		$this->assertTrue( intersoccer_filter_can_view_medical( true, 2 ) );
		$this->assertTrue( intersoccer_user_can_view_medical( 2 ) );
	}

	public function test_roles_filter_non_array_is_sanitized(): void {
		WP_Mock::onFilter( 'intersoccer_medical_roles' )
			->with( [ 'administrator' ] )
			->reply(
				new InvokedFilterValue(
					static function () {
						return 'coach';
					}
				)
			);
		$this->assertSame( [ 'administrator', 'coach' ], intersoccer_get_medical_roles() );
	}

	public function test_getter_filters_apply_and_re_sanitize_roles(): void {
		WP_Mock::onFilter( 'intersoccer_medical_roles' )
			->with( [ 'administrator' ] )
			->reply(
				new InvokedFilterValue(
					static function () {
						return [ 'coach', 'invalid' ];
					}
				)
			);
		$this->assertSame( [ 'administrator', 'coach' ], intersoccer_get_medical_roles() );
	}

	public function test_retention_months_zero_skips_filter(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 0;
		WP_Mock::onFilter( 'intersoccer_retention_months' )->with( 0 )->reply( 12 );
		$this->assertSame( 0, intersoccer_get_retention_months() );
	}

	public function test_retention_months_filter_when_enabled(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		WP_Mock::onFilter( 'intersoccer_retention_months' )->with( 6 )->reply( 200 );
		$this->assertSame( 120, intersoccer_get_retention_months() );
	}

	public function test_inactive_account_months_zero_skips_filter(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS ] = 0;
		WP_Mock::onFilter( 'intersoccer_retention_inactive_account_months' )->with( 0 )->reply( 6 );
		$this->assertSame( 0, intersoccer_get_retention_inactive_account_months() );
	}

	public function test_retention_cutoff_mid_month(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		$now = new DateTimeImmutable( '2026-08-15', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2026-02-15', intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_aug_31_minus_six_months(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		$now = new DateTimeImmutable( '2026-08-31', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2026-02-28', intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_leap_year_aug_31_minus_six_months(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		$now = new DateTimeImmutable( '2028-08-31', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2028-02-29', intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_mar_31_minus_one_month(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 1;
		$now = new DateTimeImmutable( '2026-03-31', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2026-02-28', intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_null_when_off(): void {
		$this->assertNull( intersoccer_get_retention_cutoff_date( new DateTimeImmutable( '2026-08-31' ) ) );
	}

	public function test_user_has_medical_role_rejects_invalid_ids(): void {
		$this->assertFalse( intersoccer_user_has_medical_role( null ) );
		$this->assertFalse( intersoccer_user_has_medical_role( 'abc' ) );
		$this->assertFalse( intersoccer_user_has_medical_role( 0 ) );
		$this->assertFalse( intersoccer_user_has_medical_role( -1 ) );
	}

	public function test_public_view_helpers_reject_invalid_user_ids(): void {
		foreach ( [ null, 'abc', 0, -1 ] as $invalid ) {
			$this->assertFalse( intersoccer_user_can_view_medical( $invalid ) );
			$this->assertFalse( intersoccer_user_can_view_dob( $invalid ) );
			$this->assertSame( false, intersoccer_user_can_view_medical( $invalid ) );
		}
	}

	public function test_public_view_helpers_accept_numeric_string_user_id(): void {
		$this->configureMedicalRoles( [ 'administrator', 'coach' ] );
		$this->setUserRoles( 5, [ 'coach' ] );
		$this->setUserCaps( 5, [] );

		$this->assertTrue( intersoccer_user_can_view_medical( '5' ) );
		$this->assertTrue( intersoccer_user_can_view_dob( '5' ) );
	}

	public function test_filter_not_configured_passes_through(): void {
		$this->setUserRoles( 1, [ 'customer' ] );
		$this->assertTrue( intersoccer_filter_can_view_medical( true, 1 ) );
		$this->assertFalse( intersoccer_filter_can_view_medical( false, 1 ) );
		$this->assertSame( 'maybe', intersoccer_filter_can_view_medical( 'maybe', 1 ) );
	}

	public function test_register_setting_user_facing_options_only(): void {
		intersoccer_privacy_settings_register_options();
		$this->assertCount( 3, $GLOBALS['wp_stub_register_setting_calls'] );

		$names = array_column( $GLOBALS['wp_stub_register_setting_calls'], 'name' );
		$this->assertNotContains( INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, $names );
	}

	public function test_getters_do_not_write_options(): void {
		$before = $GLOBALS['wp_stub_options'];
		intersoccer_get_medical_roles();
		intersoccer_get_retention_months();
		intersoccer_get_retention_inactive_account_months();
		intersoccer_get_retention_cutoff_date();
		intersoccer_filter_can_view_medical( true, 1 );
		intersoccer_user_can_view_medical( 1 );
		$this->assertSame( $before, $GLOBALS['wp_stub_options'] );
	}

	public function test_schema_bootstrap_adds_option_once(): void {
		intersoccer_privacy_settings_bootstrap_schema_version();
		$this->assertArrayHasKey( INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, $GLOBALS['wp_stub_options'] );
		$this->assertSame( 1, $GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] );
	}

	public function test_cross_plugin_fixture_not_configured(): void {
		$this->setUserRoles( 10, [ 'administrator' ] );
		$this->setUserRoles( 11, [ 'shop_manager' ] );
		$this->setUserCaps( 11, [ 'manage_woocommerce' => true ] );

		$this->assertTrue( $this->fixtureStaffMedicalAccess( 10, true, false ) );
		$this->assertTrue( $this->fixtureStaffMedicalAccess( 11, false, true ) );
	}

	public function test_cross_plugin_fixture_configured(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->setUserRoles( 10, [ 'administrator' ] );
		$this->setUserRoles( 11, [ 'shop_manager' ] );
		$this->setUserCaps( 11, [ 'manage_woocommerce' => true ] );

		$this->assertTrue( $this->fixtureStaffMedicalAccess( 10, true, false ) );
		$this->assertFalse( $this->fixtureStaffMedicalAccess( 11, false, true ) );
	}

	public function test_privacy_settings_defaults_map(): void {
		$defaults = intersoccer_privacy_settings_defaults();
		$this->assertNull( $defaults[ INTERSOCCER_OPT_MEDICAL_ROLES ] );
		$this->assertSame( 0, $defaults[ INTERSOCCER_OPT_RETENTION_MONTHS ] );
		$this->assertSame( 0, $defaults[ INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS ] );
		$this->assertSame( 1, $defaults[ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] );
	}

	public function test_policy_administrator_always_allowed_medical_and_dob(): void {
		$this->configureMedicalRoles( [ 'administrator', 'coach' ] );
		$this->setUserRoles( 1, [ 'administrator' ] );
		$this->setUserCaps( 1, [] );

		$this->assertTrue( intersoccer_user_can_view_medical( 1 ) );
		$this->assertTrue( intersoccer_user_can_view_dob( 1 ) );
	}

	public function test_policy_unconfigured_pass_through_shop_manager_medical_and_dob(): void {
		$this->setUserRoles( 2, [ 'shop_manager' ] );
		$this->setUserCaps( 2, [ 'manage_woocommerce' => true ] );

		$this->assertTrue( intersoccer_user_can_view_medical( 2 ) );
		$this->assertTrue( intersoccer_user_can_view_dob( 2 ) );
	}

	public function test_policy_configured_strict_allow_list_medical_and_dob(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->setUserRoles( 2, [ 'shop_manager' ] );
		$this->setUserCaps( 2, [ 'manage_woocommerce' => true ] );

		$this->assertFalse( intersoccer_user_can_view_medical( 2 ) );
		$this->assertFalse( intersoccer_user_can_view_dob( 2 ) );

		$this->configureMedicalRoles( [ 'administrator', 'shop_manager' ] );
		$this->assertTrue( intersoccer_user_can_view_medical( 2 ) );
		$this->assertTrue( intersoccer_user_can_view_dob( 2 ) );
	}

	public function test_policy_corrupt_option_pass_through_medical_and_dob(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_MEDICAL_ROLES ] = 99;
		$this->setUserRoles( 2, [ 'shop_manager' ] );
		$this->setUserCaps( 2, [ 'manage_woocommerce' => true ] );

		$this->assertTrue( intersoccer_user_can_view_medical( 2 ) );
		$this->assertTrue( intersoccer_user_can_view_dob( 2 ) );
	}

	public function test_admin_notice_visible_for_capable_user_when_unconfigured(): void {
		$GLOBALS['wp_stub_current_user_id'] = 5;
		$GLOBALS['wp_stub_user_caps']       = [ 'manage_options' => true ];

		$this->assertTrue( intersoccer_privacy_should_show_medical_roles_notice() );
	}

	public function test_admin_notice_hidden_when_user_lacks_capability(): void {
		$GLOBALS['wp_stub_current_user_id'] = 5;
		$GLOBALS['wp_stub_user_caps']       = [];

		$this->assertFalse( intersoccer_privacy_should_show_medical_roles_notice() );
	}

	public function test_admin_notice_hidden_when_configured(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$GLOBALS['wp_stub_current_user_id'] = 5;
		$GLOBALS['wp_stub_user_caps']       = [ 'manage_options' => true ];

		$this->assertFalse( intersoccer_privacy_should_show_medical_roles_notice() );
	}

	public function test_admin_notice_render_outputs_markup_when_shown(): void {
		$GLOBALS['wp_stub_current_user_id'] = 5;
		$GLOBALS['wp_stub_user_caps']       = [ 'manage_options' => true ];
		WP_Mock::userFunction( 'admin_url' )->andReturn( 'https://example.com/wp-admin/admin.php' );
		WP_Mock::userFunction( 'add_query_arg' )->andReturn( 'https://example.com/wp-admin/dismiss' );
		WP_Mock::userFunction( 'wp_nonce_url' )->andReturn( 'https://example.com/wp-admin/dismiss&nonce=1' );
		WP_Mock::userFunction( 'esc_url' )->andReturnUsing(
			static function ( $url ) {
				return (string) $url;
			}
		);
		WP_Mock::userFunction( 'esc_html' )->andReturnUsing(
			static function ( $text ) {
				return (string) $text;
			}
		);
		WP_Mock::userFunction( 'esc_html__' )->andReturnUsing(
			static function ( $text ) {
				return (string) $text;
			}
		);

		ob_start();
		intersoccer_privacy_render_medical_roles_admin_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'Configure medical access', $html );
	}

	private function fixtureStaffMedicalAccess(int $user_id, bool $manage_options, bool $manage_woocommerce): bool {
		if ( ! function_exists( 'intersoccer_user_can_view_medical' ) ) {
			return false;
		}

		$this->setUserCaps(
			$user_id,
			[
				'manage_options'       => $manage_options,
				'manage_woocommerce'   => $manage_woocommerce,
			]
		);

		return intersoccer_user_can_view_medical( $user_id );
	}
}
