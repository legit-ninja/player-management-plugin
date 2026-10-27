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

    public function test_missing_player_index_is_rejected_instead_of_list_position()
    {
        $players = [
            0 => [
                'first_name' => 'Alice',
                'last_name' => 'A',
                'dob' => '2015-01-01',
                'medical_conditions' => 'none',
            ],
            2 => [
                'first_name' => 'Carol',
                'last_name' => 'C',
                'dob' => '2013-03-03',
                'medical_conditions' => 'asthma',
            ],
        ];

        $this->assertNull(intersoccer_resolve_intersoccer_players_meta_key($players, 1));
        $this->assertNull(intersoccer_resolve_intersoccer_players_meta_key($players, '1'));

        foreach ([0, '0'] as $requested) {
            $key = intersoccer_resolve_intersoccer_players_meta_key($players, $requested);
            $this->assertNotNull($key);
            $this->assertSame('Alice', $players[$key]['first_name']);
            $this->assertSame('2015-01-01', $players[$key]['dob']);
        }
        foreach ([2, '2'] as $requested) {
            $key = intersoccer_resolve_intersoccer_players_meta_key($players, $requested);
            $this->assertNotNull($key);
            $this->assertSame('Carol', $players[$key]['first_name']);
            $this->assertSame('asthma', $players[$key]['medical_conditions']);
        }
    }

    public function test_deleted_index_does_not_return_the_only_remaining_player()
    {
        $user_id = 11;
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'A',
                    'dob' => '2015-01-01',
                    'medical_conditions' => 'none',
                ],
            ],
        ]);

        $this->assertNull(intersoccer_get_player_by_index($user_id, 1));
        $alice = intersoccer_get_player_by_index($user_id, 0);
        $this->assertIsArray($alice);
        $this->assertSame('Alice', $alice['first_name']);
        $this->assertSame('2015-01-01', $alice['dob']);
    }

    // Regression: #56 — stale snapshot persist must not clobber PM-registered names (cart/AJAX race via backfill).
    public function test_persist_backfill_preserves_registered_names_from_database()
    {
        $user_id = 55;
        $player_id = 'uuid-francois';
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => $player_id,
                    'first_name' => 'François',
                    'last_name' => 'Dupont',
                    'dob' => '2015-06-01',
                    'gender' => 'male',
                ],
            ],
        ]);

        $stale_snapshot = [
            0 => [
                'player_id' => $player_id,
                'first_name' => 'Unknown',
                'last_name' => 'Player',
                'dob' => '2015-06-01',
                'gender' => 'male',
            ],
        ];

        intersoccer_persist_user_players($user_id, $stale_snapshot, ['intent' => 'backfill']);

        $stored = get_user_meta($user_id, 'intersoccer_players', true);
        $this->assertSame('François', $stored[0]['first_name']);
        $this->assertSame('Dupont', $stored[0]['last_name']);
    }

    public function test_persist_add_merges_concurrent_player_without_dropping_names()
    {
        $user_id = 56;
        $existing_id = 'uuid-existing';
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => $existing_id,
                    'first_name' => 'Registered',
                    'last_name' => 'Child',
                    'dob' => '2014-01-01',
                    'gender' => 'female',
                ],
                1 => [
                    'player_id' => 'uuid-sibling',
                    'first_name' => 'Sibling',
                    'last_name' => 'Child',
                    'dob' => '2016-02-02',
                    'gender' => 'male',
                ],
            ],
        ]);

        $stale_add_snapshot = [
            0 => [
                'player_id' => $existing_id,
                'first_name' => '',
                'last_name' => 'Child',
                'dob' => '2014-01-01',
                'gender' => 'female',
            ],
            2 => [
                'player_id' => 'uuid-new',
                'first_name' => 'New',
                'last_name' => 'Player',
                'dob' => '2017-03-03',
                'gender' => 'male',
            ],
        ];

        intersoccer_persist_user_players($user_id, $stale_add_snapshot, ['intent' => 'add']);

        $stored = get_user_meta($user_id, 'intersoccer_players', true);
        $this->assertSame('Registered', $stored[0]['first_name']);
        $this->assertSame('Sibling', $stored[1]['first_name']);
        $this->assertSame('New', $stored[2]['first_name']);
    }

    public function test_persist_edit_allows_intentional_name_change()
    {
        $user_id = 57;
        $player_id = 'uuid-edit-me';
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => $player_id,
                    'first_name' => 'Old',
                    'last_name' => 'Name',
                    'dob' => '2015-01-01',
                    'gender' => 'male',
                ],
            ],
        ]);

        $edited = [
            0 => [
                'player_id' => $player_id,
                'first_name' => 'New',
                'last_name' => 'Legal',
                'dob' => '2015-01-01',
                'gender' => 'male',
            ],
        ];

        intersoccer_persist_user_players($user_id, $edited, [
            'intent' => 'edit',
            'overwrite_name_player_ids' => [$player_id],
        ]);

        $stored = get_user_meta($user_id, 'intersoccer_players', true);
        $this->assertSame('New', $stored[0]['first_name']);
        $this->assertSame('Legal', $stored[0]['last_name']);
    }
}
