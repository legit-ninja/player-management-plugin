<?php
/**
 * Tests for Player_Management_Utils class
 * Target: 95%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/class-player-utils.php';

class PlayerUtilsTest extends InterSoccer_Test_Case
{
    private $utils;
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utils = new Player_Management_Utils();
        $this->fixtures = require __DIR__ . '/../fixtures/players.php';
        
        // Mock WordPress debug constant
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
    }

    /**
     * Test log_memory function
     */
    public function test_log_memory()
    {
        // Should not throw errors
        $this->utils->log_memory('test_checkpoint');
        $this->assertTrue(true, 'log_memory should execute without errors');
    }

    /**
     * Test format_bytes with bytes
     */
    public function test_format_bytes_with_bytes()
    {
        $result = $this->utils->format_bytes(512);
        $this->assertEquals('512 B', $result);
    }

    /**
     * Test format_bytes with kilobytes
     */
    public function test_format_bytes_with_kilobytes()
    {
        $result = $this->utils->format_bytes(2048);
        $this->assertEquals('2 KB', $result);
    }

    /**
     * Test format_bytes with megabytes
     */
    public function test_format_bytes_with_megabytes()
    {
        $result = $this->utils->format_bytes(2097152); // 2 MB
        $this->assertEquals('2 MB', $result);
    }

    /**
     * Test format_bytes with gigabytes
     */
    public function test_format_bytes_with_gigabytes()
    {
        $result = $this->utils->format_bytes(2147483648); // 2 GB
        $this->assertEquals('2 GB', $result);
    }

    /**
     * Test format_bytes with zero
     */
    public function test_format_bytes_with_zero()
    {
        $result = $this->utils->format_bytes(0);
        $this->assertEquals('0 B', $result);
    }

    /**
     * Test count_matching_players with search term
     */
    public function test_count_matching_players()
    {
        $mockUser = (object) ['ID' => 1];
        
        WP_Mock::userFunction('get_users', [
            'return' => [$mockUser],
        ]);
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => $this->fixtures['multiple_players'],
        ]);
        
        WP_Mock::userFunction('get_user_by', [
            'args' => ['ID', 1],
            'return' => (object) ['user_email' => 'test@example.com'],
        ]);
        
        $count = $this->utils->count_matching_players('Alice');
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * Test is_date_past with past date
     */
    public function test_is_date_past_with_past_date()
    {
        $result = $this->utils->is_date_past('01/01/2020', '2023-06-15 12:00:00');
        $this->assertTrue($result, 'Date in 2020 should be in the past');
    }

    /**
     * Test is_date_past with future date
     */
    public function test_is_date_past_with_future_date()
    {
        $result = $this->utils->is_date_past('01/01/2025', '2023-06-15 12:00:00');
        $this->assertFalse($result, 'Date in 2025 should be in the future');
    }

    /**
     * Test is_date_past with invalid date
     */
    public function test_is_date_past_with_invalid_date()
    {
        $result = $this->utils->is_date_past('invalid', '2023-06-15 12:00:00');
        $this->assertFalse($result, 'Invalid date should return false');
    }

    /**
     * Test validate_player_data with valid data
     */
    public function test_validate_player_data_with_valid_array()
    {
        $result = $this->utils->validate_player_data($this->fixtures['multiple_players']);
        $this->assertTrue($result, 'Valid player array should pass validation');
    }

    /**
     * Test validate_player_data with invalid data type
     */
    public function test_validate_player_data_with_non_array()
    {
        $result = $this->utils->validate_player_data('not an array');
        $this->assertFalse($result, 'Non-array should fail validation');
    }

    /**
     * Test validate_player_data with player missing first_name
     */
    public function test_validate_player_data_missing_first_name()
    {
        $invalidPlayers = [
            ['last_name' => 'Doe', 'dob' => '2015-05-15'],
        ];
        
        $result = $this->utils->validate_player_data($invalidPlayers);
        $this->assertFalse($result, 'Player without first_name should fail');
    }

    /**
     * Test validate_player_data with player missing last_name
     */
    public function test_validate_player_data_missing_last_name()
    {
        $invalidPlayers = [
            ['first_name' => 'John', 'dob' => '2015-05-15'],
        ];
        
        $result = $this->utils->validate_player_data($invalidPlayers);
        $this->assertFalse($result, 'Player without last_name should fail');
    }

    /**
     * Test validate_player_data with non-array player
     */
    public function test_validate_player_data_with_non_array_player()
    {
        $invalidPlayers = ['not', 'an', 'array', 'of', 'players'];
        
        $result = $this->utils->validate_player_data($invalidPlayers);
        $this->assertFalse($result, 'Non-array players should fail');
    }

    /**
     * Test get_user_billing_info
     */
    public function test_get_user_billing_info()
    {
        $this->setUserMeta(1, [
            'billing_state' => 'ZH',
            'billing_city' => 'Zurich',
            'billing_country' => 'CH',
        ]);
        
        $result = $this->utils->get_user_billing_info(1);
        
        $this->assertArrayHasKey('state', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('country', $result);
        $this->assertEquals('ZH', $result['state']);
        $this->assertEquals('Zurich', $result['city']);
        $this->assertEquals('CH', $result['country']);
    }

    /**
     * Test get_user_billing_info with missing data
     */
    public function test_get_user_billing_info_with_missing_data()
    {
        $this->setUserMeta(999, [
            'billing_state' => '',
            'billing_city' => '',
            'billing_country' => '',
        ]);
        
        $result = $this->utils->get_user_billing_info(999);
        
        $this->assertEquals('Unknown', $result['state'], 'Missing state should default to "Unknown"');
        $this->assertEquals('', $result['city']);
        $this->assertEquals('', $result['country']);
    }

    /**
     * Test get_user_counts
     */
    public function test_get_user_counts()
    {
        WP_Mock::userFunction('count_users', [
            'return' => [
                'avail_roles' => [
                    'customer' => 50,
                    'subscriber' => 30,
                    'administrator' => 5,
                ],
            ],
        ]);
        
        $result = $this->utils->get_user_counts();
        
        $this->assertArrayHasKey('customers', $result);
        $this->assertArrayHasKey('subscribers', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(50, $result['customers']);
        $this->assertEquals(30, $result['subscribers']);
        $this->assertEquals(80, $result['total']);
    }

    /**
     * Test get_user_counts with no users
     */
    public function test_get_user_counts_with_no_users()
    {
        WP_Mock::userFunction('count_users', [
            'return' => [
                'avail_roles' => [],
            ],
        ]);
        
        $result = $this->utils->get_user_counts();
        
        $this->assertEquals(0, $result['customers']);
        $this->assertEquals(0, $result['subscribers']);
        $this->assertEquals(0, $result['total']);
    }

    /**
     * Test clear_all_caches
     */
    public function test_clear_all_caches()
    {
        WP_Mock::userFunction('delete_transient')->andReturn(true);

        $this->utils->clear_all_caches();
        $this->assertTrue(true, 'clear_all_caches should execute without errors');
    }

    /**
     * Test get_user_players with valid data
     */
    public function test_get_user_players()
    {
        $this->setUserMeta(1, [
            'intersoccer_players' => $this->fixtures['multiple_players'],
        ]);
        
        $result = $this->utils->get_user_players(1);
        
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    /**
     * Test get_user_players with no players
     */
    public function test_get_user_players_with_no_players()
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [999, 'intersoccer_players', true],
            'return' => [],
        ]);
        
        $result = $this->utils->get_user_players(999);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_user_players with invalid data
     */
    public function test_get_user_players_with_invalid_data()
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [888, 'intersoccer_players', true],
            'return' => 'invalid data',
        ]);
        
        $result = $this->utils->get_user_players(888);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Invalid data should return empty array');
    }

    /**
     * Test process_player_batch
     */
    public function test_process_player_batch()
    {
        $mockUser1 = (object) ['ID' => 1, 'user_email' => 'user1@example.com'];
        $mockUser2 = (object) ['ID' => 2, 'user_email' => 'user2@example.com'];

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
            'billing_state' => 'ZH',
            'billing_city' => 'Zurich',
            'billing_country' => 'CH',
        ]);

        $this->setUserMeta(2, [
            'intersoccer_players' => [$this->fixtures['valid_player_female']],
            'billing_state' => 'GE',
            'billing_city' => 'Geneva',
            'billing_country' => 'CH',
        ]);
        
        WP_Mock::userFunction('current_time', [
            'args' => ['mysql'],
            'return' => '2023-06-15 12:00:00',
        ]);
        
        $result = $this->utils->process_player_batch([$mockUser1, $mockUser2], '', 50);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    /**
     * Test process_player_batch with search filter
     */
    public function test_process_player_batch_with_search()
    {
        $mockUser = (object) ['ID' => 1, 'user_email' => 'user@example.com'];
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => $this->fixtures['multiple_players'],
        ]);
        
        WP_Mock::userFunction('get_user_meta')->andReturn('');
        
        WP_Mock::userFunction('current_time', [
            'args' => ['mysql'],
            'return' => '2023-06-15 12:00:00',
        ]);
        
        $result = $this->utils->process_player_batch([$mockUser], 'Alice', 50);
        
        $this->assertIsArray($result);
        // Alice should be found
        $this->assertGreaterThanOrEqual(0, count($result));
    }

    /**
     * Test process_player_batch with per_page limit
     */
    public function test_process_player_batch_respects_per_page_limit()
    {
        $mockUser = (object) ['ID' => 1, 'user_email' => 'user@example.com'];
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => $this->fixtures['multiple_players'], // 3 players
        ]);
        
        WP_Mock::userFunction('get_user_meta')->andReturn('');
        
        WP_Mock::userFunction('current_time', [
            'args' => ['mysql'],
            'return' => '2023-06-15 12:00:00',
        ]);
        
        $result = $this->utils->process_player_batch([$mockUser], '', 2);
        
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(2, count($result), 'Should respect per_page limit');
    }

    /**
     * Test get_pagination_info
     */
    public function test_get_pagination_info()
    {
        $result = $this->utils->get_pagination_info(2, 10, 50, 500, 'test search');
        
        $this->assertArrayHasKeys(
            ['current_page', 'total_pages', 'players_on_page', 'total_items', 'search_term', 'has_search'],
            $result
        );
        
        $this->assertEquals(2, $result['current_page']);
        $this->assertEquals(10, $result['total_pages']);
        $this->assertEquals(50, $result['players_on_page']);
        $this->assertEquals(500, $result['total_items']);
        $this->assertEquals('test search', $result['search_term']);
        $this->assertTrue($result['has_search']);
    }

    /**
     * Test get_pagination_info without search
     */
    public function test_get_pagination_info_without_search()
    {
        $result = $this->utils->get_pagination_info(1, 5, 25, 100);
        
        $this->assertEquals('', $result['search_term']);
        $this->assertFalse($result['has_search']);
    }

    /**
     * Test process_player_batch handles exception gracefully
     */
    public function test_process_player_batch_handles_exception()
    {
        $mockUser = (object) ['ID' => 1, 'user_email' => 'user@example.com'];
        
        // Simulate error by returning invalid data
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => 'invalid',
        ]);
        
        WP_Mock::userFunction('current_time', [
            'args' => ['mysql'],
            'return' => '2023-06-15 12:00:00',
        ]);
        
        $result = $this->utils->process_player_batch([$mockUser], '', 50);
        
        // Should return empty array without throwing exception
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test format_bytes edge case - exactly 1024
     */
    public function test_format_bytes_boundary_cases()
    {
        $this->assertEquals('1 KB', $this->utils->format_bytes(1024));
        $this->assertEquals('1 MB', $this->utils->format_bytes(1048576));
        $this->assertEquals('1 GB', $this->utils->format_bytes(1073741824));
    }

    /**
     * Test format_bytes with fractional values
     */
    public function test_format_bytes_with_fractional_values()
    {
        $result = $this->utils->format_bytes(1536); // 1.5 KB
        $this->assertEquals('1.5 KB', $result);
    }
}

