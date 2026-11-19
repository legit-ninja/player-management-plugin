<?php
/**
 * Tests for My Account Dashboard helpers (menu strip, blurb detect, enqueue gates).
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/account-dashboard.php';

class AccountDashboardTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearBookingReturnRequest();
    }

    protected function tearDown(): void
    {
        $this->clearBookingReturnRequest();
        parent::tearDown();
    }

    public function test_manage_players_endpoint_slugs()
    {
        $slugs = intersoccer_pm_manage_players_endpoint_slugs();
        $this->assertContains('manage-players', $slugs);
        $this->assertContains('gerer-participants', $slugs);
        $this->assertContains('teilnehmer-verwalten', $slugs);
        $this->assertCount(3, $slugs);
    }

    public function test_is_manage_players_endpoint_slug()
    {
        $this->assertTrue(intersoccer_pm_is_manage_players_endpoint_slug('manage-players'));
        $this->assertTrue(intersoccer_pm_is_manage_players_endpoint_slug('gerer-participants'));
        $this->assertFalse(intersoccer_pm_is_manage_players_endpoint_slug('orders'));
        $this->assertFalse(intersoccer_pm_is_manage_players_endpoint_slug(''));
    }

    public function test_strip_manage_players_menu_items()
    {
        $items = [
            'dashboard' => 'Dashboard',
            'manage-players' => 'Manage Players',
            'gerer-participants' => 'Gérer participants',
            'teilnehmer-verwalten' => 'Teilnehmer verwalten',
            'orders' => 'Orders',
        ];

        $stripped = intersoccer_pm_strip_manage_players_menu_items($items);

        $this->assertArrayHasKey('dashboard', $stripped);
        $this->assertArrayHasKey('orders', $stripped);
        $this->assertArrayNotHasKey('manage-players', $stripped);
        $this->assertArrayNotHasKey('gerer-participants', $stripped);
        $this->assertArrayNotHasKey('teilnehmer-verwalten', $stripped);
    }

    public function test_is_woocommerce_dashboard_desc_string()
    {
        $billing = 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">billing address</a>, and <a href="%3$s">edit your password and account details</a>.';
        $shipping = 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.';

        $this->assertTrue(intersoccer_pm_is_woocommerce_dashboard_desc_string($billing));
        $this->assertTrue(intersoccer_pm_is_woocommerce_dashboard_desc_string($shipping));
        $this->assertFalse(intersoccer_pm_is_woocommerce_dashboard_desc_string('Hello %1$s'));
        $this->assertFalse(intersoccer_pm_is_woocommerce_dashboard_desc_string(''));
    }

    public function test_suppress_dashboard_desc_on_account_dashboard()
    {
        WP_Mock::userFunction('is_account_page', ['return' => true]);
        WP_Mock::userFunction('is_wc_endpoint_url', ['return' => false]);

        $source = 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.';
        $result = intersoccer_pm_suppress_woocommerce_dashboard_desc('Translated blurb', $source, 'woocommerce');

        $this->assertSame('', $result);
    }

    public function test_suppress_dashboard_desc_leaves_other_strings()
    {
        WP_Mock::userFunction('is_account_page', ['return' => true]);
        WP_Mock::userFunction('is_wc_endpoint_url', ['return' => false]);

        $result = intersoccer_pm_suppress_woocommerce_dashboard_desc('Hello', 'Hello %s', 'woocommerce');
        $this->assertSame('Hello', $result);
    }

    public function test_should_enqueue_on_dashboard()
    {
        $GLOBALS['wp_stub_logged_in'] = true;
        WP_Mock::userFunction('is_account_page', ['return' => true]);
        WP_Mock::userFunction('is_wc_endpoint_url', ['return' => false]);

        $this->assertTrue(intersoccer_pm_should_enqueue_player_assets());
    }

    public function test_should_not_enqueue_on_logged_out_account_page()
    {
        $GLOBALS['wp_stub_logged_in'] = false;
        WP_Mock::userFunction('is_account_page', ['return' => true]);
        WP_Mock::userFunction('is_wc_endpoint_url', ['return' => false]);

        $this->assertFalse(intersoccer_pm_should_enqueue_player_assets());
    }

    public function test_should_not_enqueue_off_account()
    {
        WP_Mock::userFunction('is_account_page', ['return' => false]);

        $this->assertFalse(intersoccer_pm_should_enqueue_player_assets());
    }

    public function test_manage_players_redirect_keeps_valid_redirect_and_inner_args()
    {
        $product = 'https://example.test/product/camp/?attribute_pa_booking-type=full-week&camp_days%5B%5D=Monday&quantity=1&coupon=sample10&bf_return=1';
        $dashboard = 'https://example.test/fr/mon-compte/';
        $this->primeManagePlayersRequest();
        $this->primeReturnUrlChecks();
        WP_Mock::userFunction('wc_get_account_endpoint_url')->andReturn($dashboard);

        $_GET['redirect'] = $product;
        $target = intersoccer_pm_manage_players_redirect_target();

        $this->assertStringStartsWith($dashboard . '?', $target);
        $this->assertStringContainsString('redirect=' . rawurlencode($product), $target);
        $query = $this->queryArgs($target);
        $this->assertSame($product, $query['redirect']);
        $this->assertStringContainsString('bf_return=1', $query['redirect']);
        $this->assertStringContainsString('attribute_pa_booking-type=full-week', $query['redirect']);
        $this->assertStringContainsString('camp_days%5B%5D=Monday', $query['redirect']);
        $this->assertStringContainsString('quantity=1', $query['redirect']);
        $this->assertStringContainsString('coupon=sample10', $query['redirect']);
        $this->assertArrayNotHasKey('bf_return', $query);
    }

    public function test_manage_players_redirect_drops_invalid_redirect()
    {
        $dashboard = 'https://example.test/fr/mon-compte/';
        $this->primeManagePlayersRequest();
        $this->primeReturnUrlChecks();
        WP_Mock::userFunction('wc_get_account_endpoint_url')->andReturn($dashboard);

        $invalid = [
            'javascript:alert(1)',
            'data:text/html,hi',
            '//example.test/product/camp/',
            'https://evil.test/product/camp/',
            "https://example.test/product/camp/\r\nX: 1",
            '%2F%2Fevil.test/product',
        ];

        foreach ($invalid as $redirect) {
            $_GET['redirect'] = $redirect;
            unset($_POST['redirect']);

            $this->assertSame(
                $dashboard,
                intersoccer_pm_manage_players_redirect_target(),
                $redirect
            );
        }
    }

    public function test_manage_players_redirect_keeps_sibling_flag_and_drops_other_args()
    {
        $product = 'https://example.test/product/camp/?bf_return=1';
        $dashboard = 'https://example.test/de/mein-konto/';
        $this->primeManagePlayersRequest();
        $this->primeReturnUrlChecks();
        WP_Mock::userFunction('wc_get_account_endpoint_url')->andReturn($dashboard);
        $_GET['redirect'] = $product;
        $_GET['bf_return'] = '1';
        $_GET['coupon'] = 'sample10';
        $_GET['extra'] = 'drop-me';

        $query = $this->queryArgs(intersoccer_pm_manage_players_redirect_target());

        $this->assertSame($product, $query['redirect']);
        $this->assertSame('1', (string) $query['bf_return']);
        $this->assertArrayNotHasKey('coupon', $query);
        $this->assertArrayNotHasKey('extra', $query);
    }

    public function test_manage_players_redirect_ignores_sibling_flag_other_than_one()
    {
        $dashboard = 'https://example.test/my-account/';
        $this->primeManagePlayersRequest();
        $this->primeReturnUrlChecks();
        WP_Mock::userFunction('wc_get_account_endpoint_url')->andReturn($dashboard);
        $_GET['bf_return'] = '0';

        $this->assertSame($dashboard, intersoccer_pm_manage_players_redirect_target());
    }

    public function test_manage_players_redirect_without_return_args_is_the_dashboard()
    {
        $dashboard = 'https://example.test/my-account/';
        $this->primeManagePlayersRequest();
        WP_Mock::userFunction('wc_get_account_endpoint_url')->andReturn($dashboard);

        $this->assertSame($dashboard, intersoccer_pm_manage_players_redirect_target());
    }

    public function test_manage_players_redirect_target_empty_off_the_endpoint()
    {
        WP_Mock::userFunction('is_account_page')->andReturn(true);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $this->assertSame('', intersoccer_pm_manage_players_redirect_target());
    }

    public function test_request_return_url_prefers_post_then_falls_through()
    {
        $this->primeReturnUrlChecks();
        $_POST['redirect'] = 'https://example.test/product/from-post/?bf_return=1';
        $_GET['redirect'] = 'https://example.test/product/from-get/?bf_return=1';
        $this->assertSame($_POST['redirect'], intersoccer_pm_request_return_url());

        $_POST['redirect'] = 'javascript:alert(1)';
        $this->assertSame($_GET['redirect'], intersoccer_pm_request_return_url());
    }

    public function test_relative_return_url_is_kept()
    {
        $this->primeReturnUrlChecks();
        $_GET['redirect'] = '/product/camp/?bf_return=1&quantity=1';

        $this->assertSame('/product/camp/?bf_return=1&quantity=1', intersoccer_pm_request_return_url());
    }

    public function test_localized_return_url_is_validated()
    {
        $this->primeReturnUrlChecks();
        $product = 'https://example.test/product/camp/?attribute_pa_booking-type=full-week&coupon=sample10&bf_return=1';
        $_GET['redirect'] = $product;

        $empty_account = intersoccer_pm_player_script_return_data(0);
        $this->assertSame($product, $empty_account['returnUrl']);
        $this->assertSame('1', $empty_account['returnAfterFirstPlayer']);

        $with_players = intersoccer_pm_player_script_return_data(2);
        $this->assertSame($product, $with_players['returnUrl']);
        $this->assertSame('0', $with_players['returnAfterFirstPlayer']);

        $_GET['redirect'] = 'https://evil.test/product/camp/';
        $rejected = intersoccer_pm_player_script_return_data(0);
        $this->assertSame('', $rejected['returnUrl']);
        $this->assertSame('0', $rejected['returnAfterFirstPlayer']);
    }

    public function test_submit_label_and_back_link()
    {
        WP_Mock::userFunction('esc_url')->andReturnUsing(function ($url) {
            $url = (string) $url;
            if (preg_match('#^\s*(javascript|data):#i', $url)) {
                return '';
            }
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        });
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        });

        $safe = 'https://example.test/product/camp/?bf_return=1&coupon=sample10';
        $this->assertSame(
            'Save and go back to your booking',
            intersoccer_pm_add_player_submit_label(0, $safe)
        );
        $this->assertSame('Save', intersoccer_pm_add_player_submit_label(1, $safe));
        $this->assertSame('Save', intersoccer_pm_add_player_submit_label(0, ''));

        $html = intersoccer_pm_booking_return_link_html($safe);
        $this->assertStringContainsString('class="intersoccer-back-to-booking"', $html);
        $this->assertStringContainsString('Back to your booking', $html);
        $this->assertStringContainsString('bf_return=1&amp;coupon=sample10', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame('', intersoccer_pm_booking_return_link_html(''));
        $this->assertSame('', intersoccer_pm_booking_return_link_html('javascript:alert(1)'));
    }

    public function test_wp_validate_redirect_rejection_is_honored()
    {
        $this->useExampleSite();
        WP_Mock::userFunction('wp_validate_redirect')->andReturn('');
        $_GET['redirect'] = 'https://example.test/product/camp/?bf_return=1';

        $this->assertSame('', intersoccer_pm_request_return_url());
    }

    /**
     * Product Variations' validator is used when it is loaded. Kept in its own
     * process so the rest of the suite still exercises the local fallback.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_validate_return_url_delegates_to_pv_when_present()
    {
        $this->assertFalse(function_exists('intersoccer_pv_validate_return_url'));

        function intersoccer_pv_validate_return_url($url) {
            return is_string($url) && strpos($url, 'https://example.test/') === 0 ? $url : '';
        }

        $this->assertSame(
            'https://example.test/product/camp/?bf_return=1',
            intersoccer_pm_validate_return_url('https://example.test/product/camp/?bf_return=1')
        );
        $this->assertSame('', intersoccer_pm_validate_return_url('https://evil.test/product/'));
        $this->assertSame('', intersoccer_pm_validate_return_url('javascript:alert(1)'));
    }

    /**
     * Local fallback when neither Product Variations nor wp_validate_redirect exists.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_validate_return_url_fallback_without_pv_or_wp_validate_redirect()
    {
        $this->assertFalse(function_exists('intersoccer_pv_validate_return_url'));
        $this->assertFalse(function_exists('wp_validate_redirect'));

        function home_url($path = '') {
            return 'https://example.test/';
        }
        function site_url($path = '') {
            return 'https://example.test/';
        }

        $this->assertSame(
            'https://example.test/product/camp/?bf_return=1',
            intersoccer_pm_validate_return_url('https://example.test/product/camp/?bf_return=1')
        );
        $this->assertSame('/product/camp/?bf_return=1', intersoccer_pm_validate_return_url('/product/camp/?bf_return=1'));
        $this->assertSame('', intersoccer_pm_validate_return_url('https://evil.test/product/'));
        $this->assertSame('', intersoccer_pm_validate_return_url('javascript:alert(1)'));
        $this->assertSame('', intersoccer_pm_validate_return_url('data:text/html,hi'));
        $this->assertSame('', intersoccer_pm_validate_return_url('//example.test/product/'));
        $this->assertSame('', intersoccer_pm_validate_return_url("https://example.test/product/\r\nX: 1"));
    }

    private function clearBookingReturnRequest()
    {
        unset(
            $_GET['redirect'],
            $_POST['redirect'],
            $_GET['bf_return'],
            $_POST['bf_return'],
            $_GET['coupon'],
            $_GET['extra'],
            $_GET['manage-players'],
            $_GET['gerer-participants'],
            $_GET['teilnehmer-verwalten']
        );
    }

    private function primeManagePlayersRequest()
    {
        WP_Mock::userFunction('is_account_page')->andReturn(true);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);
        $_SERVER['REQUEST_URI'] = '/my-account/manage-players/';
        $_GET['manage-players'] = '';
    }

    private function useExampleSite()
    {
        WP_Mock::userFunction('home_url')->andReturn('https://example.test/');
        WP_Mock::userFunction('site_url')->andReturn('https://example.test/');
    }

    private function primeReturnUrlChecks()
    {
        $this->useExampleSite();
        WP_Mock::userFunction('wp_validate_redirect')->andReturnUsing(function ($location, $fallback = '') {
            return $location;
        });
        WP_Mock::userFunction('add_query_arg')->andReturnUsing(function ($args, $url = '') {
            if (!is_array($args)) {
                return (string) $url;
            }
            $fragment = '';
            $url = (string) $url;
            $hash = strpos($url, '#');
            if ($hash !== false) {
                $fragment = substr($url, $hash);
                $url = substr($url, 0, $hash);
            }
            $pairs = [];
            $query_pos = strpos($url, '?');
            if ($query_pos !== false) {
                $existing = substr($url, $query_pos + 1);
                if ($existing !== '') {
                    $pairs[] = $existing;
                }
                $url = substr($url, 0, $query_pos);
            }
            // WordPress add_query_arg() does not encode newly added values.
            foreach ($args as $key => $value) {
                if ($value === false || $value === null) {
                    continue;
                }
                $pairs[] = rawurlencode((string) $key) . '=' . (string) $value;
            }
            $query = implode('&', $pairs);
            return $url . ($query !== '' ? '?' . $query : '') . $fragment;
        });
    }

    private function queryArgs($url)
    {
        $parts = parse_url((string) $url);
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        return $query;
    }
}
