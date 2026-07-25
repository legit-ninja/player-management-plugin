<?php
/**
 * Unit tests for InterSoccer_Player_Management_Underdog_Updater.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/class-underdog-updater.php';

class UnderdogUpdaterTest extends InterSoccer_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_stub_options'] = array();

		WP_Mock::userFunction('untrailingslashit')->andReturnUsing(
			static function ($value) {
				return rtrim((string) $value, '/');
			}
		);
		WP_Mock::userFunction('apply_filters')->andReturnUsing(
			static function ($hook, $value) {
				return $value;
			}
		);
	}

	public function test_url_requires_auth_for_metadata_and_download() {
		$base = InterSoccer_Player_Management_Underdog_Updater::get_update_base();
		$this->assertTrue(
			InterSoccer_Player_Management_Underdog_Updater::url_requires_auth(
				$base . '/api/plugins/v1/player-management'
			)
		);
		$this->assertTrue(
			InterSoccer_Player_Management_Underdog_Updater::url_requires_auth(
				$base . '/api/plugins/v1/player-management/download'
			)
		);
	}

	public function test_url_requires_auth_rejects_unrelated_hosts() {
		$this->assertFalse(
			InterSoccer_Player_Management_Underdog_Updater::url_requires_auth(
				'https://example.com/api/plugins/v1/player-management'
			)
		);
		$this->assertFalse(
			InterSoccer_Player_Management_Underdog_Updater::url_requires_auth('')
		);
	}

	public function test_http_request_args_skips_when_token_empty() {
		$url  = InterSoccer_Player_Management_Underdog_Updater::get_metadata_url();
		$args = InterSoccer_Player_Management_Underdog_Updater::http_request_args(
			array('timeout' => 5),
			$url
		);

		$this->assertArrayNotHasKey('Authorization', $args['headers'] ?? array());
	}

	public function test_http_request_args_injects_bearer_when_token_set() {
		$GLOBALS['wp_stub_options'][ InterSoccer_Player_Management_Underdog_Updater::OPTION_TOKEN ] = 'udpl_test_token_value';

		$url  = InterSoccer_Player_Management_Underdog_Updater::get_metadata_url();
		$args = InterSoccer_Player_Management_Underdog_Updater::http_request_args(
			array('timeout' => 5, 'headers' => array('Accept' => 'application/json')),
			$url
		);

		$this->assertSame('Bearer udpl_test_token_value', $args['headers']['Authorization']);
		$this->assertSame('application/json', $args['headers']['Accept']);
	}

	public function test_http_request_args_does_not_auth_unrelated_url() {
		$GLOBALS['wp_stub_options'][ InterSoccer_Player_Management_Underdog_Updater::OPTION_TOKEN ] = 'udpl_test_token_value';

		$args = InterSoccer_Player_Management_Underdog_Updater::http_request_args(
			array('timeout' => 5),
			'https://api.wordpress.org/plugins/info/1.0/'
		);

		$this->assertArrayNotHasKey('Authorization', $args['headers'] ?? array());
	}

	public function test_metadata_url_uses_slug() {
		$url = InterSoccer_Player_Management_Underdog_Updater::get_metadata_url();
		$this->assertStringEndsWith('/api/plugins/v1/player-management', $url);
		$this->assertStringStartsWith('https://plugins.underdogunlimited.com', $url);
	}
}
