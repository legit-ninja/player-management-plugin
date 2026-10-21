<?php
/**
 * WPML language on customer admin-ajax URLs and AJAX bootstrap.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-locale.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/pm-i18n-locale.php';

class PmWpmlAjaxTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('is_textdomain_loaded')) {
            function is_textdomain_loaded($domain) {
                return !empty($GLOBALS['intersoccer_pm_test_textdomain_loaded'][$domain]);
            }
        }
        if (!function_exists('unload_textdomain')) {
            function unload_textdomain($domain, $reloadable = false) {
                unset($GLOBALS['intersoccer_pm_test_textdomain_loaded'][$domain]);
            }
        }
        if (!function_exists('load_textdomain')) {
            function load_textdomain($domain, $mofile, $locale = null) {
                $GLOBALS['intersoccer_pm_test_textdomain_loaded'][$domain] = true;
                return true;
            }
        }
        if (!function_exists('load_plugin_textdomain')) {
            function load_plugin_textdomain($domain, $deprecated, $path) {
                $GLOBALS['intersoccer_pm_test_textdomain_loaded'][$domain] = true;
                return true;
            }
        }
        if (!function_exists('plugin_basename')) {
            function plugin_basename($file) {
                return 'player-management/player-management.php';
            }
        }
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wp_stub_is_admin'],
            $GLOBALS['wp_stub_doing_ajax'],
            $GLOBALS['wp_stub_locale_switched'],
            $GLOBALS['intersoccer_pm_textdomain_locale'],
            $GLOBALS['intersoccer_pm_wpml_switched']
        );
        unset($_REQUEST['lang'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    public function test_customer_localize_includes_lang_and_query_arg()
    {
        WP_Mock::onFilter('wpml_current_language')->with(null)->reply('fr');

        $data = intersoccer_pm_customer_script_localize_base(true);

        $this->assertSame('fr', $data['lang']);
        $this->assertStringContainsString('lang=fr', $data['ajax_url']);
        $this->assertStringContainsString('lang=fr', $data['nonce_refresh_url']);
        $this->assertStringContainsString('action=intersoccer_refresh_nonce', $data['nonce_refresh_url']);
    }

    public function test_admin_localize_omits_wpml_lang()
    {
        WP_Mock::onFilter('wpml_current_language')->with(null)->reply('de');

        $data = intersoccer_pm_customer_script_localize_base(false);

        $this->assertArrayNotHasKey('lang', $data);
        $this->assertStringNotContainsString('lang=', $data['ajax_url']);
    }

    public function test_bootstrap_switches_wpml_on_customer_ajax()
    {
        $_REQUEST['lang'] = 'fr';
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = true;
        $_SERVER['HTTP_REFERER'] = 'https://example.com/fr/mon-compte/';
        $GLOBALS['intersoccer_pm_wpml_switched'] = null;

        WP_Mock::userFunction('has_action')
            ->with('wpml_switch_language')
            ->andReturn(true);
        WP_Mock::onAction('wpml_switch_language')
            ->with('fr')
            ->perform(static function () {
                $GLOBALS['intersoccer_pm_wpml_switched'] = 'fr';
            });

        intersoccer_pm_bootstrap_customer_ajax_language();

        $this->assertSame('fr', $GLOBALS['intersoccer_pm_wpml_switched']);
        $this->assertSame('fr_FR', $GLOBALS['intersoccer_pm_textdomain_locale']);
    }

    public function test_bootstrap_skips_admin_english_ajax()
    {
        $_REQUEST['lang'] = 'fr';
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_doing_ajax'] = true;
        $_SERVER['HTTP_REFERER'] = 'https://example.com/wp-admin/users.php';
        $GLOBALS['intersoccer_pm_wpml_switched'] = null;

        WP_Mock::userFunction('has_action')
            ->with('wpml_switch_language')
            ->andReturn(true);

        intersoccer_pm_bootstrap_customer_ajax_language();

        $this->assertNull($GLOBALS['intersoccer_pm_wpml_switched']);
    }
}
