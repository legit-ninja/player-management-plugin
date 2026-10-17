<?php

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-data.php';
require_once __DIR__ . '/../../includes/player-management.php';
require_once __DIR__ . '/../../includes/medical-access.php';
require_once __DIR__ . '/../../includes/ajax-handlers.php';

class MedicalGatingAjaxTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('intersoccer_clear_user_players_cache')) {
            intersoccer_clear_user_players_cache(null);
        }
        wp_cache_flush();
        $_POST = [];
        unset($GLOBALS['intersoccer_medical_access_test_filters']);
    }

    private function basePlayer(): array
    {
        return [
            'player_id' => 'pid-1',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'dob' => '2015-05-15',
            'gender' => 'female',
            'avs_number' => '0000',
            'medical_conditions' => 'Peanuts',
            'creation_timestamp' => 1,
        ];
    }

    private function mockParentAjaxAuth(int $user_id = 1): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        $GLOBALS['wp_stub_current_user_id'] = $user_id;
        intersoccer_test_set_user_caps(['customer' => true, 'edit_users' => false, 'edit_user' => true]);
    }

    private function mockCoachAjaxAuth(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        $GLOBALS['wp_stub_current_user_id'] = 99;
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true, 'edit_users' => true]);
    }

    public function test_parent_add_player_stores_medical_text(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, ['intersoccer_players' => []]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Sam',
            'player_last_name' => 'Kid',
            'player_dob' => '2015-08-20',
            'player_gender' => 'male',
            'player_avs_number' => '0000',
            'player_medical' => 'Nut allergy',
        ];

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('Nut allergy', $stored['medical_conditions']);
    }

    public function test_parent_edit_own_child_updates_dob_and_medical(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Doe',
            'player_dob' => '2014-08-20',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'None',
        ];

        $this->expectException(WPSendJsonExit::class);
        intersoccer_edit_player();

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('2014-08-20', $stored['dob']);
        $this->assertSame('None', $stored['medical_conditions']);
    }

    public function test_parent_get_player_returns_unredacted_sensitive_fields(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $captured = null;
        try {
            intersoccer_get_player();
        } catch (WPSendJsonExit $e) {
            $captured = $e;
        }
        $this->assertNotNull($captured);

        $redacted = intersoccer_pm_redact_player_for_response($this->basePlayer(), 1, 1);
        $this->assertSame('2015-05-15', $redacted['dob']);
        $this->assertSame('Peanuts', $redacted['medical_conditions']);
    }

    public function test_coach_edit_other_users_child_preserves_medical_and_dob(): void
    {
        $this->mockCoachAjaxAuth();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Janet',
            'player_last_name' => 'Doe',
            'player_dob' => '2010-01-01',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'Hacked',
        ];

        $this->expectException(WPSendJsonExit::class);
        intersoccer_edit_player();

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('Janet', $stored['first_name']);
        $this->assertSame('2015-05-15', $stored['dob']);
        $this->assertSame('Peanuts', $stored['medical_conditions']);
    }

    public function test_coach_get_other_users_player_redacts_sensitive_fields(): void
    {
        $this->mockCoachAjaxAuth();
        $redacted = intersoccer_pm_redact_player_for_response($this->basePlayer(), 1, 99);
        $this->assertSame('Restricted', $redacted['dob']);
        $this->assertSame('Restricted', $redacted['medical_conditions']);
    }
}
