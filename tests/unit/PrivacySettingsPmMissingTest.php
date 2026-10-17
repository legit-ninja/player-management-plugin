<?php
/**
 * Caller contract when Player Management privacy helpers are not loaded.
 */

use PHPUnit\Framework\TestCase;

class PrivacySettingsPmMissingTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_callers_deny_medical_and_dob_when_helpers_not_loaded(): void {
		$this->assertFalse( function_exists( 'intersoccer_user_can_view_medical' ) );
		$this->assertFalse( function_exists( 'intersoccer_user_can_view_dob' ) );

		$admin_user_id = 1;

		$can_view_medical = function_exists( 'intersoccer_user_can_view_medical' )
			? intersoccer_user_can_view_medical( $admin_user_id )
			: false;

		$can_view_dob = function_exists( 'intersoccer_user_can_view_dob' )
			? intersoccer_user_can_view_dob( $admin_user_id )
			: false;

		$this->assertFalse( $can_view_medical );
		$this->assertSame( false, $can_view_medical );
		$this->assertFalse( $can_view_dob );
		$this->assertSame( false, $can_view_dob );
	}
}
