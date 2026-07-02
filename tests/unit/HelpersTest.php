<?php
/**
 * Tests for global helper functions
 * Target: 90%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-management.php';

class HelpersTest extends InterSoccer_Test_Case
{
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = require __DIR__ . '/../fixtures/players.php';
        
        // Mock WordPress translation function
        WP_Mock::userFunction('__')->andReturnUsing(function($text) {
            return $text;
        });
    }

    /**
     * Test intersoccer_translate_gender with male
     */
    public function test_translate_gender_male()
    {
        $result = intersoccer_translate_gender('male');
        $this->assertEquals('Male', $result);
    }

    /**
     * Test intersoccer_translate_gender with female
     */
    public function test_translate_gender_female()
    {
        $result = intersoccer_translate_gender('female');
        $this->assertEquals('Female', $result);
    }

    /**
     * Test intersoccer_translate_gender with other
     */
    public function test_translate_gender_other()
    {
        $result = intersoccer_translate_gender('other');
        $this->assertEquals('Other', $result);
    }

    /**
     * Test intersoccer_translate_gender with empty value
     */
    public function test_translate_gender_empty()
    {
        $result = intersoccer_translate_gender('');
        $this->assertEquals('N/A', $result);
    }

    /**
     * Test intersoccer_translate_gender with N/A
     */
    public function test_translate_gender_na()
    {
        $result = intersoccer_translate_gender('N/A');
        $this->assertEquals('N/A', $result);
    }

    /**
     * Test intersoccer_translate_gender with mixed case
     */
    public function test_translate_gender_mixed_case()
    {
        $this->assertEquals('Male', intersoccer_translate_gender('Male'));
        $this->assertEquals('Male', intersoccer_translate_gender('MALE'));
        $this->assertEquals('Female', intersoccer_translate_gender('Female'));
        $this->assertEquals('Female', intersoccer_translate_gender('FEMALE'));
    }

    /**
     * Test intersoccer_translate_gender with unrecognized value
     */
    public function test_translate_gender_unrecognized()
    {
        $result = intersoccer_translate_gender('unknown');
        $this->assertEquals('unknown', $result, 'Unrecognized gender should be returned as-is');
    }

    /**
     * Test intersoccer_get_player_event_count with no events
     */
    public function test_get_player_event_count_no_events()
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => [$this->fixtures['valid_player']],
        ]);
        
        WP_Mock::userFunction('wc_get_orders', [
            'return' => [],
        ]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(0, $count);
    }

    /**
     * Test intersoccer_get_player_event_count with player not found
     */
    public function test_get_player_event_count_player_not_found()
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => [],
        ]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(0, $count, 'Should return 0 for non-existent player');
    }

    /**
     * Test intersoccer_get_player_event_count with empty player name
     */
    public function test_get_player_event_count_empty_name()
    {
        $playerWithEmptyName = [
            ['first_name' => '', 'last_name' => ''],
        ];
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => $playerWithEmptyName,
        ]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(0, $count, 'Should return 0 for player with empty name');
    }

    /**
     * Test intersoccer_get_player_event_count with matching name
     */
    public function test_get_player_event_count_with_matching_name()
    {
        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);
        
        $mockOrder = Mockery::mock('WC_Order');
        $mockOrder->shouldReceive('get_id')->andReturn(100);
        $mockOrder->shouldReceive('get_status')->andReturn('completed');
        
        $mockItem = Mockery::mock('WC_Order_Item_Product');
        $mockItem->shouldReceive('get_meta')
            ->with('Assigned Attendee')
            ->andReturn('John Doe');
        $mockItem->shouldReceive('get_meta')
            ->with('intersoccer_player_index')
            ->andReturn(null);
        
        $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);
        
        $this->mockWcGetOrders([$mockOrder]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(1, $count);
    }

    /**
     * Test intersoccer_get_player_event_count with matching index
     */
    public function test_get_player_event_count_with_matching_index()
    {
        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);
        
        $mockOrder = Mockery::mock('WC_Order');
        $mockOrder->shouldReceive('get_id')->andReturn(100);
        $mockOrder->shouldReceive('get_status')->andReturn('processing');
        
        $mockItem = Mockery::mock('WC_Order_Item_Product');
        $mockItem->shouldReceive('get_meta')
            ->with('Assigned Attendee')
            ->andReturn('Different Name');
        $mockItem->shouldReceive('get_meta')
            ->with('intersoccer_player_index')
            ->andReturn(0);
        
        $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);
        
        $this->mockWcGetOrders([$mockOrder]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(1, $count, 'Should match by index even if name differs');
    }

    /**
     * Test intersoccer_get_player_event_count with multiple orders
     */
    public function test_get_player_event_count_with_multiple_orders()
    {
        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);
        
        $mockOrder1 = Mockery::mock('WC_Order');
        $mockOrder1->shouldReceive('get_id')->andReturn(100);
        $mockOrder1->shouldReceive('get_status')->andReturn('completed');
        
        $mockItem1 = Mockery::mock('WC_Order_Item_Product');
        $mockItem1->shouldReceive('get_meta')
            ->with('Assigned Attendee')
            ->andReturn('John Doe');
        $mockItem1->shouldReceive('get_meta')
            ->with('intersoccer_player_index')
            ->andReturn(null);
        
        $mockOrder1->shouldReceive('get_items')->andReturn([1 => $mockItem1]);
        
        $mockOrder2 = Mockery::mock('WC_Order');
        $mockOrder2->shouldReceive('get_id')->andReturn(101);
        $mockOrder2->shouldReceive('get_status')->andReturn('processing');
        
        $mockItem2 = Mockery::mock('WC_Order_Item_Product');
        $mockItem2->shouldReceive('get_meta')
            ->with('Assigned Attendee')
            ->andReturn('John Doe');
        $mockItem2->shouldReceive('get_meta')
            ->with('intersoccer_player_index')
            ->andReturn(null);
        
        $mockOrder2->shouldReceive('get_items')->andReturn([2 => $mockItem2]);
        
        $this->mockWcGetOrders([$mockOrder1, $mockOrder2]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(2, $count);
    }

    /**
     * Test intersoccer_get_player_event_count with different statuses
     */
    public function test_get_player_event_count_different_statuses()
    {
        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);
        
        $statuses = ['completed', 'processing', 'on-hold', 'pending'];
        $mockOrders = [];
        
        foreach ($statuses as $index => $status) {
            $mockOrder = Mockery::mock('WC_Order');
            $mockOrder->shouldReceive('get_id')->andReturn(100 + $index);
            $mockOrder->shouldReceive('get_status')->andReturn($status);
            
            $mockItem = Mockery::mock('WC_Order_Item_Product');
            $mockItem->shouldReceive('get_meta')
                ->with('Assigned Attendee')
                ->andReturn('John Doe');
            $mockItem->shouldReceive('get_meta')
                ->with('intersoccer_player_index')
                ->andReturn(null);
            
            $mockOrder->shouldReceive('get_items')->andReturn([$index => $mockItem]);
            $mockOrders[] = $mockOrder;
        }
        
        $this->mockWcGetOrders($mockOrders);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(4, $count, 'Should count events across all statuses');
    }

    /**
     * Test intersoccer_render_players_form when not logged in
     */
    public function test_render_players_form_not_logged_in()
    {
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => false,
        ]);
        
        WP_Mock::userFunction('esc_html__', [
            'return_arg' => 0,
        ]);
        
        WP_Mock::userFunction('esc_url')->andReturnUsing(function($url) {
            return $url;
        });
        
        WP_Mock::userFunction('wp_login_url', [
            'return' => 'http://example.com/login',
        ]);
        
        WP_Mock::userFunction('wp_registration_url', [
            'return' => 'http://example.com/register',
        ]);
        
        WP_Mock::userFunction('get_permalink', [
            'return' => 'http://example.com/current-page',
        ]);
        
        $result = intersoccer_render_players_form();
        
        $this->assertStringContainsString('log in', $result);
        $this->assertStringContainsString('register', $result);
    }

    /**
     * Test intersoccer_render_players_form when logged in as regular user
     */
    public function test_render_players_form_logged_in_regular_user()
    {
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('get_current_user_id', [
            'return' => 1,
        ]);
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => [],
        ]);
        
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_attr')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_html')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('wp_nonce_field')->andReturn('');
        
        $result = intersoccer_render_players_form(false, []);
        
        // Should return form HTML
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test intersoccer_render_players_form in admin mode
     */
    public function test_render_players_form_admin_mode()
    {
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_attr')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_html')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('wp_nonce_field')->andReturn('');
        
        // In admin mode, user_id is null
        $result = intersoccer_render_players_form(true, []);
        
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test intersoccer_render_players_form with empty settings array
     */
    public function test_render_players_form_with_empty_settings()
    {
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('get_current_user_id', [
            'return' => 1,
        ]);
        
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => [],
        ]);
        
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_attr')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('esc_html')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('wp_nonce_field')->andReturn('');
        
        $result = intersoccer_render_players_form(false, ['' => 'invalid']);
        
        // Should filter out empty keys
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test intersoccer_get_player_event_count with no matching items
     */
    public function test_get_player_event_count_no_matching_items()
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [1, 'intersoccer_players', true],
            'return' => [$this->fixtures['valid_player']],
        ]);
        
        $mockOrder = Mockery::mock('WC_Order');
        $mockOrder->shouldReceive('get_id')->andReturn(100);
        $mockOrder->shouldReceive('get_status')->andReturn('completed');
        
        $mockItem = Mockery::mock('WC_Order_Item_Product');
        $mockItem->shouldReceive('get_meta')
            ->with('Assigned Attendee')
            ->andReturn('Different Person');
        $mockItem->shouldReceive('get_meta')
            ->with('intersoccer_player_index')
            ->andReturn(99);  // Different index
        
        $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);
        
        WP_Mock::userFunction('wc_get_orders', [
            'return' => [$mockOrder],
        ]);
        
        $count = intersoccer_get_player_event_count(1, 0);
        $this->assertEquals(0, $count, 'Should return 0 when no items match');
    }
}

