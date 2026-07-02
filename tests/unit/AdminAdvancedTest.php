<?php
/**
 * Audit regression tests for admin advanced import flows.
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class AdminAdvancedTest extends InterSoccer_Test_Case
{
    // Regression: AUDIT-006 — CSV import uses name field instead of first_name/last_name schema
    public function test_csv_import_produces_canonical_player_shape()
    {
        $player_name = 'Jane Smith';
        $player_dob = '2015-05-15';
        $player_gender = 'female';

        // Mirrors current admin-advanced.php import append shape.
        $imported_player = [
            'name' => $player_name,
            'dob' => $player_dob,
            'gender' => $player_gender,
            'age_group' => 'Fun Footy',
        ];

        $this->assertArrayHasKey(
            'first_name',
            $imported_player,
            'CSV import should map to canonical first_name key'
        );
        $this->assertArrayHasKey(
            'last_name',
            $imported_player,
            'CSV import should map to canonical last_name key'
        );
        $this->assertArrayNotHasKey(
            'name',
            $imported_player,
            'Legacy name key should not be used for intersoccer_players entries'
        );
    }
}
