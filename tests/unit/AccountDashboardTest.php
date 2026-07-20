<?php
/**
 * Tests for My Account Dashboard helpers (menu strip, blurb detect, enqueue gates).
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/account-dashboard.php';

class AccountDashboardTest extends InterSoccer_Test_Case
{
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
        WP_Mock::userFunction('is_account_page', ['return' => true]);
        WP_Mock::userFunction('is_wc_endpoint_url', ['return' => false]);

        $this->assertTrue(intersoccer_pm_should_enqueue_player_assets());
    }

    public function test_should_not_enqueue_off_account()
    {
        WP_Mock::userFunction('is_account_page', ['return' => false]);

        $this->assertFalse(intersoccer_pm_should_enqueue_player_assets());
    }
}
