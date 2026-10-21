<?php
/**
 * Tests for admin English locale helper.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-locale.php';

class AdminLocaleTest extends InterSoccer_Test_Case
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wp_stub_is_admin'], $GLOBALS['wp_stub_doing_ajax'], $GLOBALS['wp_stub_locale_switched']);
        unset($_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    public function test_admin_screen_forces_english_gettext()
    {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = false;
        $GLOBALS['wp_stub_locale_switched'] = false;

        $this->assertTrue(intersoccer_pm_is_admin_english_context());
        $this->assertSame(
            'Manage Players',
            intersoccer_pm_filter_admin_english_gettext('Gérer les participants', 'Manage Players', INTERSOCCER_PLAYER_TEXT_DOMAIN)
        );
    }

    public function test_frontend_ajax_keeps_translation()
    {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = true;
        $GLOBALS['wp_stub_locale_switched'] = false;
        $_SERVER['HTTP_REFERER'] = 'https://example.com/fr/mon-compte/';

        WP_Mock::userFunction('admin_url')->andReturn('https://example.com/wp-admin/');

        $this->assertFalse(intersoccer_pm_is_admin_english_context());
        $this->assertSame(
            'Gérer les participants',
            intersoccer_pm_filter_admin_english_gettext('Gérer les participants', 'Manage Players', INTERSOCCER_PLAYER_TEXT_DOMAIN)
        );
    }

    public function test_switched_locale_keeps_translation_even_in_admin()
    {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = false;
        $GLOBALS['wp_stub_locale_switched'] = true;

        $this->assertFalse(intersoccer_pm_is_admin_english_context());
        $this->assertSame(
            'Gespeichert',
            intersoccer_pm_filter_admin_english_gettext('Gespeichert', 'Saved', INTERSOCCER_PLAYER_TEXT_DOMAIN)
        );
    }

    public function test_admin_ajax_from_wp_admin_forces_english()
    {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = true;
        $GLOBALS['wp_stub_locale_switched'] = false;
        $_SERVER['HTTP_REFERER'] = 'https://example.com/wp-admin/users.php';

        WP_Mock::userFunction('admin_url')->andReturn('https://example.com/wp-admin/');

        $this->assertTrue(intersoccer_pm_is_admin_english_context());
    }

}
