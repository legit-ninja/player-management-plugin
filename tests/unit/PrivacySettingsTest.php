<?php
/**
 * Unit tests for includes/privacy-settings.php.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../helpers/privacy-settings-stubs.php';
require_once __DIR__ . '/../../includes/privacy-settings.php';

class PrivacySettingsTest extends InterSoccer_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_stub_options'] = [];
		$GLOBALS['wp_stub_user_objects'] = [];
		$GLOBALS['wp_stub_register_setting_calls'] = [];
		$GLOBALS['wp_stub_filters'] = [];
		$GLOBALS['wp_stub_timezone'] = 'UTC';
		add_filter( 'intersoccer_can_view_medical', 'intersoccer_filter_can_view_medical', 10, 2 );
	}

	protected function setUserRoles(int $user_id, array $roles): void {
		$GLOBALS['wp_stub_user_objects'][ $user_id ] = (object) [
			'ID'    => $user_id,
			'roles' => $roles,
		];
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
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_MEDICAL_ROLES ] = [ 'bogus_role', 'coach' ];
		$this->assertSame( [ 'administrator', 'coach' ], intersoccer_get_medical_roles() );

		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 999;
		$this->assertSame( 120, intersoccer_get_retention_months() );
	}

	public function test_getter_filters_apply_and_re_sanitize(): void {
		privacy_settings_stub_add_filter(
			'intersoccer_medical_roles',
			static function () {
				return [ 'coach', 'invalid' ];
			}
		);
		$this->assertSame( [ 'administrator', 'coach' ], intersoccer_get_medical_roles() );

		privacy_settings_stub_add_filter(
			'intersoccer_retention_months',
			static function () {
				return 200;
			}
		);
		$this->assertSame( 120, intersoccer_get_retention_months() );
	}

	public function test_retention_cutoff_date_with_fixed_now(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		$now = new DateTimeImmutable( '2024-08-15', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2024-02-15', intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_month_end_case(): void {
		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_RETENTION_MONTHS ] = 6;
		$now = new DateTimeImmutable( '2024-08-31', new DateTimeZone( 'UTC' ) );
		$expected = $now->modify( '-6 months' )->format( 'Y-m-d' );
		$this->assertSame( $expected, intersoccer_get_retention_cutoff_date( $now ) );
	}

	public function test_retention_cutoff_null_when_off(): void {
		$this->assertNull( intersoccer_get_retention_cutoff_date( new DateTimeImmutable( '2024-08-31' ) ) );
	}

	public function test_filter_not_configured_passes_through(): void {
		$this->assertTrue( intersoccer_filter_can_view_medical( true, 1 ) );
		$this->assertFalse( intersoccer_filter_can_view_medical( false, 1 ) );
		$this->assertSame( 'maybe', intersoccer_filter_can_view_medical( 'maybe', 1 ) );
	}

	public function test_filter_configured_administrator_only(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->setUserRoles( 1, [ 'administrator' ] );
		$this->setUserRoles( 2, [ 'shop_manager' ] );

		$this->assertTrue( intersoccer_filter_can_view_medical( true, 1 ) );
		$this->assertFalse( intersoccer_filter_can_view_medical( true, 2 ) );
	}

	public function test_filter_configured_shop_manager_when_ticked(): void {
		$this->configureMedicalRoles( [ 'administrator', 'shop_manager' ] );
		$this->setUserRoles( 2, [ 'shop_manager' ] );
		$this->assertTrue( intersoccer_filter_can_view_medical( false, 2 ) );
	}

	public function test_filter_configured_coach_when_ticked(): void {
		$this->configureMedicalRoles( [ 'administrator', 'coach' ] );
		$this->setUserRoles( 3, [ 'coach' ] );
		$this->assertTrue( intersoccer_filter_can_view_medical( false, 3 ) );
	}

	public function test_filter_configured_invalid_user_false(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->assertFalse( intersoccer_filter_can_view_medical( true, 0 ) );
		$this->assertFalse( intersoccer_filter_can_view_medical( true, 99 ) );
	}

	public function test_filter_configured_always_strict_bool(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->setUserRoles( 1, [ 'administrator' ] );
		$result = intersoccer_filter_can_view_medical( true, 1 );
		$this->assertTrue( $result );
		$this->assertSame( true, $result );
	}

	public function test_register_setting_called_for_each_option(): void {
		intersoccer_privacy_settings_register_options();
		$this->assertCount( 4, $GLOBALS['wp_stub_register_setting_calls'] );

		foreach ( $GLOBALS['wp_stub_register_setting_calls'] as $call ) {
			$this->assertSame( 'intersoccer_privacy', $call['group'] );
			$this->assertFalse( $call['args']['show_in_rest'] );
		}

		$names = array_column( $GLOBALS['wp_stub_register_setting_calls'], 'name' );
		$this->assertContains( INTERSOCCER_OPT_MEDICAL_ROLES, $names );
		$this->assertContains( INTERSOCCER_OPT_RETENTION_MONTHS, $names );
		$this->assertContains( INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS, $names );
		$this->assertContains( INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, $names );

		$medical = null;
		foreach ( $GLOBALS['wp_stub_register_setting_calls'] as $call ) {
			if ( $call['name'] === INTERSOCCER_OPT_MEDICAL_ROLES ) {
				$medical = $call;
			}
		}
		$this->assertNotNull( $medical );
		$this->assertArrayNotHasKey( 'default', $medical['args'] );
	}

	public function test_getters_do_not_write_options(): void {
		$before = $GLOBALS['wp_stub_options'];
		intersoccer_get_medical_roles();
		intersoccer_get_retention_months();
		intersoccer_get_retention_inactive_account_months();
		intersoccer_get_retention_cutoff_date();
		intersoccer_filter_can_view_medical( true, 1 );
		$this->assertSame( $before, $GLOBALS['wp_stub_options'] );
	}

	public function test_schema_bootstrap_adds_option_once(): void {
		intersoccer_privacy_settings_bootstrap_schema_version();
		$this->assertArrayHasKey( INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION, $GLOBALS['wp_stub_options'] );
		$this->assertSame( 1, $GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] );

		$GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] = 1;
		intersoccer_privacy_settings_bootstrap_schema_version();
		$this->assertSame( 1, $GLOBALS['wp_stub_options'][ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] );
	}

	public function test_rr_medical_access_fixture_not_configured(): void {
		$this->setUserRoles( 10, [ 'administrator' ] );
		$this->setUserRoles( 11, [ 'shop_manager' ] );
		$this->setUserRoles( 12, [ 'coach' ] );

		$GLOBALS['wp_stub_user_caps'] = [
			'manage_options'       => false,
			'manage_woocommerce'   => false,
		];

		$this->assertTrue( $this->fixtureRrUserCanViewMedical( 10, true, false ) );
		$this->assertTrue( $this->fixtureRrUserCanViewMedical( 11, false, true ) );
		$this->assertFalse( $this->fixtureRrUserCanViewMedical( 12, false, false ) );
	}

	public function test_rr_medical_access_fixture_configured(): void {
		$this->configureMedicalRoles( [ 'administrator' ] );
		$this->setUserRoles( 10, [ 'administrator' ] );
		$this->setUserRoles( 11, [ 'shop_manager' ] );
		$this->setUserRoles( 12, [ 'coach' ] );

		$this->assertTrue( $this->fixtureRrUserCanViewMedical( 10, true, false ) );
		$this->assertFalse( $this->fixtureRrUserCanViewMedical( 11, false, true ) );
		$this->assertFalse( $this->fixtureRrUserCanViewMedical( 12, false, false ) );

		$this->configureMedicalRoles( [ 'administrator', 'coach' ] );
		$this->assertTrue( $this->fixtureRrUserCanViewMedical( 12, false, false ) );
	}

	public function test_function_exists_fallback_pattern_denies_when_pm_missing(): void {
		$undefined_helper = 'intersoccer_user_has_medical_role_pm_missing_fixture';
		$this->assertFalse( function_exists( $undefined_helper ) );

		$this->assertFalse( $this->fixtureCrossPluginMedicalAccessWhenPmMissing( 1, true, true ) );
		$this->assertFalse( $this->fixtureCrossPluginMedicalAccessWhenPmMissing( 1, false, true ) );

		$direct = function_exists( $undefined_helper ) ? $undefined_helper( 1 ) : false;
		$this->assertFalse( $direct );
		$this->assertSame( false, $direct );
	}

	public function test_privacy_settings_defaults_map(): void {
		$defaults = intersoccer_privacy_settings_defaults();
		$this->assertNull( $defaults[ INTERSOCCER_OPT_MEDICAL_ROLES ] );
		$this->assertSame( 0, $defaults[ INTERSOCCER_OPT_RETENTION_MONTHS ] );
		$this->assertSame( 0, $defaults[ INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS ] );
		$this->assertSame( 1, $defaults[ INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION ] );
	}

	/**
	 * Documented RR/PV fallback when PM helpers are not loaded (deny all users).
	 */
	private function fixtureCrossPluginMedicalAccessWhenPmMissing(
		int $user_id,
		bool $manage_options,
		bool $manage_woocommerce
	): bool {
		if ( ! function_exists( 'intersoccer_user_has_medical_role_pm_missing_fixture' ) ) {
			return false;
		}

		return $this->fixtureRrUserCanViewMedical( $user_id, $manage_options, $manage_woocommerce );
	}

	/**
	 * Fixture mirroring Reports/Rosters medical access helper (draft PR #110).
	 */
	private function fixtureRrUserCanViewMedical(int $user_id, bool $manage_options, bool $manage_woocommerce): bool {
		$GLOBALS['wp_stub_user_caps'] = [
			'manage_options'     => $manage_options,
			'manage_woocommerce' => $manage_woocommerce,
		];

		$allowed = $manage_options || $manage_woocommerce;
		$filtered = apply_filters( 'intersoccer_can_view_medical', $allowed, $user_id );

		if ( $filtered === true ) {
			return true;
		}
		if ( $filtered === false ) {
			return false;
		}

		return false;
	}
}
