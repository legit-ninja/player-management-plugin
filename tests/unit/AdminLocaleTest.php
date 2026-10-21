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
        unset($GLOBALS['wp_locale_switcher']);
        unset($_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    private function stub_locale_switcher(bool $switched): void
    {
        $GLOBALS['wp_locale_switcher'] = new class($switched) {
            /** @var bool */
            private $switched;

            public function __construct(bool $switched)
            {
                $this->switched = $switched;
            }

            public function is_switched(): bool
            {
                return $this->switched;
            }
        };
        $GLOBALS['wp_stub_locale_switched'] = $switched;
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
        $this->stub_locale_switcher(true);

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

    public function test_admin_context_without_locale_switcher_does_not_fatal()
    {
        unset($GLOBALS['wp_locale_switcher']);
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = false;
        $GLOBALS['wp_stub_locale_switched'] = false;

        $this->assertTrue(intersoccer_pm_is_admin_english_context());
        $this->assertSame(
            'Manage Players',
            intersoccer_pm_filter_admin_english_gettext('Gérer les participants', 'Manage Players', INTERSOCCER_PLAYER_TEXT_DOMAIN)
        );
    }

    public function test_locale_switcher_helper_false_when_switcher_missing()
    {
        unset($GLOBALS['wp_locale_switcher']);
        $this->assertFalse(intersoccer_pm_is_locale_switched());
    }

}
