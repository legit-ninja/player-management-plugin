<?php

use PHPUnit\Framework\TestCase;

class MedicalAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wp_stub_user_caps'],
            $GLOBALS['wp_stub_user_can'],
            $GLOBALS['wp_stub_current_user_id'],
            $GLOBALS['intersoccer_medical_access_test_filters']
        );
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/includes/medical-access.php';
    }

    public function test_admin_manage_options_can_view_medical(): void
    {
        intersoccer_test_set_user_caps(['manage_options' => true]);
        $this->assertTrue(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_shop_manager_can_view_medical(): void
    {
        intersoccer_test_set_user_caps(['manage_woocommerce' => true]);
        $this->assertTrue(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_coach_only_role_denied(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true]);
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_filter_strict_true_grants_access(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['intersoccer_medical_access_test_filters'] = [
            static function ($allowed) {
                return true;
            },
        ];
        $this->assertTrue(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_filter_truthy_without_strict_true_denies(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['intersoccer_medical_access_test_filters'] = [
            static function ($allowed) {
                return 1;
            },
        ];
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_account_owner_can_view_own_player_medical_despite_filter(): void
    {
        intersoccer_test_set_user_caps(['customer' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 7;
        $GLOBALS['intersoccer_medical_access_test_filters'] = [
            static function () {
                return false;
            },
        ];
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical(7));
        $this->assertTrue(intersoccer_pm_can_view_player_medical(7, 7));
    }

    public function test_coach_cannot_view_other_users_player_medical(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $this->assertFalse(intersoccer_pm_can_view_player_medical(1, 99));
        $display = intersoccer_pm_format_medical_display_value('SECRET peanut allergy', 1, 99);
        $this->assertSame('Restricted', $display);
    }

    public function test_strip_sensitive_fields_on_save_for_denied_non_owner(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $stripped = intersoccer_pm_strip_medical_from_player_update_data([
            'first_name' => 'Kid',
            'dob' => '1970-01-01',
            'medical_conditions' => 'Hacked',
        ], 1, 99);
        $this->assertSame(['first_name' => 'Kid'], $stripped);
    }

    public function test_preserve_sensitive_fields_on_save_for_denied_non_owner(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $existing = [
            'dob' => '2015-01-01',
            'medical_conditions' => 'Asthma',
        ];
        $incoming = [
            'dob' => '1970-01-01',
            'medical_conditions' => '',
            'first_name' => 'New',
        ];
        $merged = intersoccer_pm_preserve_sensitive_fields_on_save($existing, $incoming, 1, 99);
        $this->assertSame('2015-01-01', $merged['dob']);
        $this->assertSame('Asthma', $merged['medical_conditions']);
        $this->assertSame('New', $merged['first_name']);
    }
}
