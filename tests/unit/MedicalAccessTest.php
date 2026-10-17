<?php

use PHPUnit\Framework\TestCase;

class MedicalAccessTest extends TestCase
{
    private static bool $privacy_booted = false;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->bootPrivacyPluginIfAvailable();
        require_once dirname(__DIR__, 2) . '/includes/medical-access.php';
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        unset(
            $GLOBALS['wp_stub_user_caps'],
            $GLOBALS['wp_stub_user_can'],
            $GLOBALS['wp_stub_current_user_id'],
            $GLOBALS['wp_stub_users']
        );
        parent::tearDown();
    }

    private function bootPrivacyPluginIfAvailable(): void
    {
        if (self::$privacy_booted) {
            return;
        }
        $privacy = dirname(__DIR__, 2) . '/includes/privacy-settings.php';
        if (!is_readable($privacy)) {
            return;
        }
        require_once __DIR__ . '/../helpers/privacy-settings-stubs.php';
        require_once $privacy;
        self::$privacy_booted = true;
    }

    public function test_coach_only_role_denied_without_privacy_helpers(): void
    {
        if (function_exists('intersoccer_user_can_view_medical')) {
            $this->markTestSkipped('Privacy helpers loaded; use filter-backed tests instead.');
        }
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true], 99);
        $this->assertFalse(intersoccer_pm_can_view_player_medical(1, 99));
    }

    public function test_privacy_filter_grants_medical_and_dob_for_staff(): void
    {
        if (!function_exists('intersoccer_user_can_view_medical')) {
            $this->markTestSkipped('Privacy helpers are not loaded in this worktree.');
        }
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true], 99);
        $GLOBALS['wp_stub_user_objects'][99] = (object) ['ID' => 99, 'roles' => ['coach']];
        WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 99)->reply(true);
        WP_Mock::onFilter('intersoccer_can_view_dob')->with(false, 99)->reply(true);
        $this->assertTrue(intersoccer_pm_can_view_player_medical(1, 99));
        $this->assertTrue(intersoccer_pm_can_view_player_dob(1, 99));
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

    public function test_account_owner_can_view_own_player_medical_despite_filter(): void
    {
        intersoccer_test_set_user_caps(['customer' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 7;
        if (function_exists('intersoccer_user_can_view_medical')) {
            WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 7)->reply(false);
        }
        $this->assertTrue(intersoccer_pm_can_view_player_medical(7, 7));
    }

    public function test_coach_cannot_view_other_users_player_medical_without_privacy_grant(): void
    {
        if (function_exists('intersoccer_user_can_view_medical')) {
            WP_Mock::onFilter('intersoccer_can_view_medical')->with(false, 99)->reply(false);
            WP_Mock::onFilter('intersoccer_can_view_dob')->with(false, 99)->reply(false);
        }
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $this->assertFalse(intersoccer_pm_can_view_player_medical(1, 99));
        $display = intersoccer_pm_format_medical_display_value('SECRET peanut allergy', 1, 99);
        $this->assertSame('Restricted', $display);
    }

    public function test_load_more_ajax_redacts_age_and_event_age_groups(): void
    {
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-utils.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-list.php';

        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        $GLOBALS['wp_stub_current_user_id'] = 99;
        intersoccer_test_set_user_caps(['manage_options' => true]);

        $user = (object) [
            'ID' => 1,
            'user_email' => 'parent@example.com',
            'display_name' => 'Parent',
        ];
        $GLOBALS['wp_stub_users'] = [$user];
        $GLOBALS['wp_stub_users'][1]['meta']['intersoccer_players'] = [[
            'first_name' => 'Kid',
            'last_name' => 'One',
            'dob' => '2015-01-01',
            'gender' => 'male',
            'avs_number' => '0000',
            'medical_conditions' => 'Asthma',
        ]];

        $_POST = [
            'nonce' => wp_create_nonce('intersoccer_player_list_nonce'),
            'page' => 1,
        ];

        $utils = new Player_Management_Utils();
        $list = new Player_Management_List($utils);

        $payload = null;
        try {
            $list->ajax_load_more_players();
            $this->fail('Expected JSON success');
        } catch (WPSendJsonExit $e) {
            $this->assertSame('success', $e->getMessage());
            $payload = $e->payload;
        }

        $this->assertIsArray($payload);
        $player = $payload['players'][0] ?? [];
        $this->assertSame('Restricted', $player['age'] ?? null);
        $this->assertSame('Restricted', $player['event_age_groups'] ?? null);
    }

    public function test_preload_redact_helper_matches_localize_path(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'edit_users' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $players = [[
            'first_name' => 'Kid',
            'dob' => '2015-01-01',
            'medical_conditions' => 'Secret',
        ]];
        $redacted = intersoccer_pm_redact_players_for_preload($players, 1, 99);
        $this->assertSame('Restricted', $redacted[0]['dob']);
        $this->assertSame('Restricted', $redacted[0]['medical_conditions']);
    }

    public function test_preserve_missing_medical_key_stays_empty_for_denied_staff(): void
    {
        intersoccer_test_set_user_caps(['coach' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $existing = ['dob' => '2015-01-01'];
        $incoming = [
            'dob' => '2015-01-01',
            'medical_conditions' => 'Injected',
            'first_name' => 'Kid',
        ];
        $merged = intersoccer_pm_preserve_sensitive_fields_on_save($existing, $incoming, 1, 99);
        $this->assertSame('', $merged['medical_conditions']);
    }

    public function test_denied_staff_add_form_renders_notice_not_medical_field(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'edit_users' => true]);
        $GLOBALS['wp_stub_current_user_id'] = 99;

        ob_start();
        intersoccer_pm_render_medical_input_field(1);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('pm-medical-field-denied-note', $html);
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
    }

    public function test_player_list_modal_reads_medical_from_has_medical_title(): void
    {
        $contents = file_get_contents(PLAYER_MANAGEMENT_PATH . 'includes/class-player-list.php');
        $this->assertIsString($contents);
        $this->assertStringContainsString(".display-medical .has-medical", $contents);
    }
}
