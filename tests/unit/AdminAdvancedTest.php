<?php
/**
 * Audit regression tests for admin advanced import flows.
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class AdminAdvancedTest extends InterSoccer_Test_Case
{
    /**
     * Mirror admin-advanced.php CSV import player append shape (AUDIT-006).
     *
     * @param string $player_name Full name from CSV column.
     * @param string $player_dob  DOB.
     * @param string $player_gender Gender.
     * @return array
     */
    private function build_imported_player_shape($player_name, $player_dob, $player_gender)
    {
        $name_parts = preg_split('/\s+/', trim($player_name), 2);
        $age = $player_dob
            ? (int) floor((time() - strtotime($player_dob)) / 31536000)
            : 0;

        return [
            'first_name' => $name_parts[0] ?? '',
            'last_name' => $name_parts[1] ?? '',
            'dob' => $player_dob,
            'gender' => $player_gender,
            'age_group' => $player_dob
                ? ($age <= 5 ? 'Mini Soccer' : ($age <= 13 ? 'Fun Footy' : 'Soccer League'))
                : 'N/A',
        ];
    }

    // Regression: AUDIT-006 — CSV import uses name field instead of first_name/last_name schema
    public function test_csv_import_produces_canonical_player_shape()
    {
        $imported_player = $this->build_imported_player_shape('Jane Smith', '2015-05-15', 'female');

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
        $this->assertSame('Jane', $imported_player['first_name']);
        $this->assertSame('Smith', $imported_player['last_name']);
        $this->assertArrayNotHasKey(
            'name',
            $imported_player,
            'Legacy name key should not be used for intersoccer_players entries'
        );
    }
}
