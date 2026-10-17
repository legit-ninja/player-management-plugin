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

    private function mockAjaxAuth(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        $GLOBALS['wp_stub_current_user_id'] = 1;
    }

    public function test_denied_edit_preserves_stored_medical_and_dob(): void
    {
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true, 'edit_users' => true]);
        $this->mockAjaxAuth();

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

    public function test_allowed_edit_can_update_medical_and_dob(): void
    {
        intersoccer_test_set_user_caps(['manage_options' => true]);
        $this->mockAjaxAuth();

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
}
