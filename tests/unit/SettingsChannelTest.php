<?php
/**
 * Unit tests for Player Management Settings helpers (Update Stream).
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/admin-settings.php';

class SettingsChannelTest extends InterSoccer_Test_Case {

	public function test_sanitize_channel_maps_aliases_and_defaults() {
		$this->assertSame('release', InterSoccer_Player_Management_Settings::sanitize_channel('release'));
		$this->assertSame('dev', InterSoccer_Player_Management_Settings::sanitize_channel('dev'));
		$this->assertSame('release', InterSoccer_Player_Management_Settings::sanitize_channel('prerelease'));
		$this->assertSame('release', InterSoccer_Player_Management_Settings::sanitize_channel('beta'));
		$this->assertSame('release', InterSoccer_Player_Management_Settings::sanitize_channel('invalid'));
		$this->assertSame('release', InterSoccer_Player_Management_Settings::sanitize_channel(''));
	}

	public function test_sanitize_tab() {
		$this->assertSame('general', InterSoccer_Player_Management_Settings::sanitize_tab('general'));
		$this->assertSame('license', InterSoccer_Player_Management_Settings::sanitize_tab('license'));
		$this->assertSame('general', InterSoccer_Player_Management_Settings::sanitize_tab('other'));
	}
}
