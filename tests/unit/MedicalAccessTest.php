<?php

use PHPUnit\Framework\TestCase;

class MedicalAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wp_stub_user_caps'],
            $GLOBALS['wp_stub_user_can'],
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

    public function test_denied_user_sees_restricted_medical_display(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $display = intersoccer_pm_format_medical_display_value('SECRET peanut allergy');
        $this->assertSame('Restricted', $display);
    }

    public function test_strip_sensitive_fields_on_save_for_denied_user(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $stripped = intersoccer_pm_strip_medical_from_player_update_data([
            'first_name' => 'Kid',
            'dob' => '1970-01-01',
            'medical_conditions' => 'Hacked',
        ]);
        $this->assertSame(['first_name' => 'Kid'], $stripped);
    }

    public function test_preserve_sensitive_fields_on_save_for_denied_user(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $existing = [
            'dob' => '2015-01-01',
            'medical_conditions' => 'Asthma',
        ];
        $incoming = [
            'dob' => '1970-01-01',
            'medical_conditions' => '',
            'first_name' => 'New',
        ];
        $merged = intersoccer_pm_preserve_sensitive_fields_on_save($existing, $incoming);
        $this->assertSame('2015-01-01', $merged['dob']);
        $this->assertSame('Asthma', $merged['medical_conditions']);
        $this->assertSame('New', $merged['first_name']);
    }
}
