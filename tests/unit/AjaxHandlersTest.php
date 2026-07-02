<?php
/**
 * Audit regression tests for player AJAX handlers.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-management.php';
require_once __DIR__ . '/../../includes/ajax-handlers.php';

class AjaxHandlersTest extends InterSoccer_Test_Case
{
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = require __DIR__ . '/../fixtures/players.php';
        $GLOBALS['wp_stub_cache'] = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST = [];
        $GLOBALS['wp_stub_cache'] = [];
    }

    private function mockAjaxAuthSuccess(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
    }

    // Regression: AUDIT-001 — Object cache for intersoccer_players not invalidated after AJAX CRUD
    public function test_add_player_invalidates_players_cache()
    {
        $user_id = 1;
        $cache_key = 'intersoccer_players_' . $user_id;

        $this->setUserMeta($user_id, ['intersoccer_players' => []]);
        wp_cache_set($cache_key, [], 'intersoccer', 3600);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => $user_id,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Smith',
            'player_dob' => '2015-08-20',
            'player_gender' => 'female',
        ];

        $this->mockAjaxAuthSuccess();

        try {
            intersoccer_add_player();
        } catch (WPSendJsonExit $e) {
            $this->assertSame('success', $e->getMessage());
        }

        $cached = wp_cache_get($cache_key, 'intersoccer');
        $meta_players = get_user_meta($user_id, 'intersoccer_players', true);

        $this->assertNotEmpty($meta_players, 'Player should be persisted in user meta');
        $this->assertNotFalse($cached, 'Players cache should be invalidated or refreshed after add_player');
        $this->assertCount(
            count($meta_players),
            is_array($cached) ? $cached : [],
            'Cached players list should reflect new player without stale window'
        );
    }

    // Regression: AUDIT-004 — Unchanged player save returns 500 because update_user_meta returns false
    public function test_edit_player_unchanged_data_succeeds()
    {
        $player = $this->fixtures['valid_player'];
        $this->setUserMeta(1, ['intersoccer_players' => [$player]]);

        WP_Mock::userFunction('update_user_meta', [
            'return' => function ($user_id, $key, $value) {
                $existing = get_user_meta($user_id, $key, true);
                if ($existing === $value) {
                    return false;
                }
                $GLOBALS['wp_stub_users'][$user_id]['meta'][$key] = $value;

                return true;
            },
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => $player['first_name'],
            'player_last_name' => $player['last_name'],
            'player_dob' => $player['dob'],
            'player_gender' => $player['gender'],
            'player_avs_number' => $player['avs_number'],
            'player_medical' => $player['medical_conditions'],
        ];

        $this->mockAjaxAuthSuccess();

        try {
            intersoccer_edit_player();
            $this->fail('Expected wp_send_json_success for unchanged player data');
        } catch (WPSendJsonExit $e) {
            $this->assertSame(
                'success',
                $e->getMessage(),
                'Saving identical player data should return JSON success, not 500'
            );
        }
    }
}
