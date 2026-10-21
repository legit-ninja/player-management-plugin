<?php
/**
 * Office data-deletion notification must use English strings.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/data-deletion.php';

class DataDeletionAdminEmailTest extends InterSoccer_Test_Case
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wp_stub_last_wp_mail'],
            $GLOBALS['wp_stub_locale_switch_log'],
            $GLOBALS['wp_stub_locale_switch_history'],
            $GLOBALS['wp_stub_options']['admin_email']
        );
        parent::tearDown();
    }

    public function test_admin_email_switches_to_en_us_locale()
    {
        $GLOBALS['wp_stub_options']['admin_email'] = 'office@example.com';

        WP_Mock::userFunction('__')->andReturnUsing(static function ($text) {
            return $text;
        });

        $this->assertTrue(intersoccer_pm_send_data_deletion_admin_email(42, 'test'));

        $this->assertArrayHasKey('wp_stub_last_wp_mail', $GLOBALS);
        $this->assertSame('office@example.com', $GLOBALS['wp_stub_last_wp_mail']['to']);
        $this->assertSame('New Data Deletion Request', $GLOBALS['wp_stub_last_wp_mail']['subject']);
        $this->assertStringContainsString('ID: 42', $GLOBALS['wp_stub_last_wp_mail']['message']);
        $this->assertSame([], $GLOBALS['wp_stub_locale_switch_log'] ?? []);
        $this->assertContains('en_US', $GLOBALS['wp_stub_locale_switch_history'] ?? []);
    }
}
