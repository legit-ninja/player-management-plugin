<?php
/**
 * Tests for player-data.php helpers.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-data.php';

class PlayerDataTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_stub_cache'] = [];
    }

    public function test_add_player_assigns_uuid()
    {
        $uuid = intersoccer_generate_player_id();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function test_backfill_player_ids_is_idempotent()
    {
        $user_id = 42;
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => ['first_name' => 'A', 'last_name' => 'B', 'dob' => '2015-01-01'],
                2 => ['player_id' => 'existing-uuid', 'first_name' => 'C', 'last_name' => 'D', 'dob' => '2014-01-01'],
            ],
        ]);

        $first = intersoccer_backfill_player_ids($user_id, true);
        $this->assertSame(1, $first['updated']);
        $this->assertNotEmpty($first['players'][0]['player_id']);
        $this->assertSame('existing-uuid', $first['players'][2]['player_id']);

        $second = intersoccer_backfill_player_ids($user_id, true);
        $this->assertSame(0, $second['updated']);
    }

    public function test_get_player_by_id_resolves_after_delete_of_other_player()
    {
        $user_id = 7;
        $players = [
            0 => [
                'player_id' => 'uuid-keep',
                'first_name' => 'Keep',
                'last_name' => 'Me',
                'dob' => '2015-01-01',
            ],
            1 => [
                'player_id' => 'uuid-remove',
                'first_name' => 'Remove',
                'last_name' => 'Me',
                'dob' => '2014-01-01',
            ],
        ];
        $this->setUserMeta($user_id, ['intersoccer_players' => $players]);

        unset($players[1]);
        update_user_meta($user_id, 'intersoccer_players', $players);
        intersoccer_invalidate_user_players_cache($user_id);

        $found = intersoccer_get_player_by_id($user_id, 'uuid-keep');
        $this->assertNotNull($found);
        $this->assertSame(0, $found['key']);
        $this->assertSame('Keep', $found['player']['first_name']);

        $missing = intersoccer_get_player_by_id($user_id, 'uuid-remove');
        $this->assertNull($missing);
    }

    public function test_delete_player_preserves_other_indices()
    {
        $user_id = 9;
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => ['player_id' => 'a', 'first_name' => 'One', 'last_name' => 'Test', 'dob' => '2015-01-01', 'gender' => 'male'],
                2 => ['player_id' => 'b', 'first_name' => 'Two', 'last_name' => 'Test', 'dob' => '2014-01-01', 'gender' => 'female'],
            ],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => $user_id,
            'player_index' => 0,
        ];
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;

        require_once __DIR__ . '/../../includes/ajax-handlers.php';

        try {
            intersoccer_delete_player();
        } catch (WPSendJsonExit $e) {
            $this->assertSame('success', $e->getMessage());
        }

        $stored = get_user_meta($user_id, 'intersoccer_players', true);
        $this->assertArrayNotHasKey(0, $stored);
        $this->assertArrayHasKey(2, $stored);
        $this->assertSame('b', $stored[2]['player_id']);
    }
}
