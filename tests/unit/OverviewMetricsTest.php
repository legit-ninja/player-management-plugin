<?php
/**
 * Tests for overview KPI metric helpers.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/overview-metrics.php';

class OverviewMetricsTest extends InterSoccer_Test_Case
{
    public function test_profile_incomplete_when_dob_missing()
    {
        $this->assertTrue(intersoccer_pm_player_profile_is_incomplete([
            'dob' => '',
            'medical_conditions' => 'Peanut allergy',
        ]));
        $this->assertTrue(intersoccer_pm_player_profile_is_incomplete([
            'dob' => 'N/A',
            'medical_conditions' => 'None',
        ]));
    }

    public function test_profile_incomplete_when_medical_empty()
    {
        $this->assertTrue(intersoccer_pm_player_profile_is_incomplete([
            'dob' => '2015-05-15',
            'medical_conditions' => '',
        ]));
        $this->assertTrue(intersoccer_pm_player_profile_is_incomplete([
            'dob' => '2015-05-15',
        ]));
    }

    public function test_profile_complete_when_dob_and_medical_present()
    {
        $this->assertFalse(intersoccer_pm_player_profile_is_incomplete([
            'dob' => '2015-05-15',
            'medical_conditions' => 'None noted',
        ]));
    }

    public function test_canton_chart_collapses_to_other()
    {
        $input = [
            'ZH' => 10,
            'GE' => 8,
            'VD' => 6,
            'BE' => 4,
            'BS' => 3,
            'AG' => 2,
            'LU' => 2,
            'SG' => 1,
            'TI' => 1,
            'NE' => 1,
        ];
        $result = intersoccer_pm_overview_canton_chart_data($input, 3);

        $this->assertArrayHasKey('ZH', $result);
        $this->assertArrayHasKey('GE', $result);
        $this->assertArrayHasKey('VD', $result);
        $this->assertArrayHasKey('Other', $result);
        $this->assertSame(14, $result['Other']);
        $this->assertCount(4, $result);
    }

    public function test_canton_chart_empty_fallback()
    {
        $result = intersoccer_pm_overview_canton_chart_data([]);
        $this->assertSame(['Unknown' => 0], $result);
    }

    public function test_overview_filter_url_includes_query_arg()
    {
        WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
        });
        WP_Mock::userFunction('add_query_arg')->andReturnUsing(function ($args, $url) {
            return $url . '?' . http_build_query($args);
        });
        WP_Mock::userFunction('admin_url')->andReturnUsing(function ($path) {
            return 'https://example.com/wp-admin/' . ltrim($path, '/');
        });

        $url = intersoccer_pm_overview_filter_url('never_booked');
        $this->assertStringContainsString('overview_filter=never_booked', $url);
        $this->assertStringContainsString('page=intersoccer-all-players', $url);
    }

    public function test_empty_payload_has_v4_keys_not_collage_keys()
    {
        $payload = intersoccer_pm_overview_empty_payload();

        $this->assertArrayHasKey('never_booked_lifetime', $payload);
        $this->assertArrayHasKey('incomplete_profiles', $payload);
        $this->assertArrayHasKey('users_without_players', $payload);
        $this->assertArrayHasKey('total_players', $payload);
        $this->assertArrayNotHasKey('assigned_count', $payload);
        $this->assertArrayNotHasKey('unassigned_count', $payload);
        $this->assertArrayNotHasKey('gender_data', $payload);
        $this->assertArrayNotHasKey('top_cantons', $payload);
        $this->assertSame('batch_v4', $payload['processing_method']);
    }

    public function test_never_booked_true_when_event_count_zero()
    {
        require_once __DIR__ . '/../../includes/player-management.php';

        $this->setUserMeta(5, [
            'intersoccer_players' => [
                [
                    'first_name' => 'Ada',
                    'last_name' => 'Lovelace',
                    'dob' => '2015-01-01',
                ],
            ],
        ]);

        WP_Mock::userFunction('wc_get_orders', [
            'return' => [],
        ]);

        $this->assertTrue(intersoccer_pm_player_never_booked_lifetime(5, 0));
    }

    public function test_never_booked_false_when_name_matches_order()
    {
        require_once __DIR__ . '/../../includes/player-management.php';

        $this->setUserMeta(6, [
            'intersoccer_players' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'dob' => '2015-05-15',
                ],
            ],
        ]);

        $mockOrder = Mockery::mock('WC_Order');
        $mockOrder->shouldReceive('get_id')->andReturn(100);
        $mockOrder->shouldReceive('get_status')->andReturn('completed');

        $mockItem = Mockery::mock('WC_Order_Item_Product');
        $mockItem->shouldReceive('get_meta')->with('Assigned Attendee')->andReturn('John Doe');
        $mockItem->shouldReceive('get_meta')->with('assigned_player', true)->andReturn('');
        $mockItem->shouldReceive('get_meta')->with('intersoccer_player_index')->andReturn(null);
        $mockItem->shouldReceive('get_meta')->with('Player Index')->andReturn(null);
        $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);

        WP_Mock::userFunction('wc_get_orders', [
            'return' => [$mockOrder],
        ]);

        $this->assertFalse(intersoccer_pm_player_never_booked_lifetime(6, 0));
    }
}
