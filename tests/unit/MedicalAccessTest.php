<?php

use PHPUnit\Framework\TestCase;

class MedicalAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        require_once dirname(__DIR__, 2) . '/includes/medical-access.php';
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        unset(
            $GLOBALS['wp_stub_user_caps'],
            $GLOBALS['wp_stub_user_can'],
            $GLOBALS['wp_stub_current_user_id']
        );
        parent::tearDown();
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

    public function test_guest_cannot_view_medical(): void
    {
        $GLOBALS['wp_stub_current_user_id'] = 0;
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical());
        $this->assertFalse(intersoccer_pm_can_view_player_medical(1));
    }

    public function test_explicit_viewer_id_zero_denied_even_for_admin(): void
    {
        intersoccer_test_set_user_caps(['manage_options' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 1;
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical(0));
        $this->assertFalse(intersoccer_pm_can_view_player_medical(1, 0));
    }

    public function test_filter_strict_true_grants_access(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 42)->reply(true);
        $this->assertTrue(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_filter_truthy_without_strict_true_denies(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 42)->reply(1);
        $this->assertFalse(intersoccer_pm_current_user_can_view_medical());
    }

    public function test_account_owner_can_view_own_player_medical_despite_filter(): void
    {
        intersoccer_test_set_user_caps(['customer' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 7;
        WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 7)->reply(false);
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

    public function test_redact_load_more_payload_includes_age_and_event_age_groups(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $player = [
            'user_id' => 1,
            'dob' => '2015-01-01',
            'age' => 10,
            'event_age_groups' => ['U12'],
            'medical_conditions' => 'Asthma',
        ];
        $redacted = intersoccer_pm_redact_player_for_response($player, 1, 99);
        $this->assertSame('Restricted', $redacted['age']);
        $this->assertSame('Restricted', $redacted['event_age_groups']);
        $this->assertSame('Restricted', $redacted['dob']);
    }

    public function test_preload_players_redacted_for_denied_staff(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'edit_users' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $players = [
            [
                'first_name' => 'Kid',
                'dob' => '2015-01-01',
                'medical_conditions' => 'Secret',
            ],
        ];
        $redacted = array_map(
            static fn ($player) => intersoccer_pm_redact_player_for_response($player, 1, 99),
            $players
        );
        $this->assertSame('Restricted', $redacted[0]['dob']);
        $this->assertSame('Restricted', $redacted[0]['medical_conditions']);
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

    public function test_denied_staff_add_form_renders_notice_not_medical_field(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'edit_users' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;

        ob_start();
        intersoccer_pm_render_medical_input_field(1);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('pm-medical-field-denied-note', $html);
        $this->assertStringContainsString(
            'Medical and dietary information must be added by someone with medical access.',
            $html
        );
        $this->assertStringNotContainsString('name="player_medical"', $html);
    }

    public function test_owner_add_form_renders_medical_textarea(): void
    {
        intersoccer_test_set_user_caps(['customer' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 7;

        ob_start();
        intersoccer_pm_render_medical_input_field(7);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="player_medical"', $html);
        $this->assertStringNotContainsString('pm-medical-field-denied-note', $html);
    }
}
