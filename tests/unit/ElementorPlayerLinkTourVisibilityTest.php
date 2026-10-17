<?php

use PHPUnit\Framework\TestCase;

/**
 * Minimal load path matching player-management.php Elementor registration (no integration bootstrap).
 */
class ElementorPlayerLinkTourVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        require_once PLAYER_MANAGEMENT_PATH . 'includes/shepherd-tour-assets.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-helpers.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-widget.php';

        WP_Mock::userFunction('is_checkout')->andReturn(false);
        WP_Mock::userFunction('is_cart')->andReturn(false);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_register_path_loads_user_has_players_helper(): void
    {
        $this->assertTrue(function_exists('intersoccer_user_has_players'));
        $this->assertTrue(function_exists('intersoccer_pm_player_link_widget_should_show_tour'));
    }

    public function test_tour_suppressed_when_user_has_players(): void
    {
        $user_id = 46231;
        $GLOBALS['wp_stub_users'][$user_id]['meta']['intersoccer_players'] = [
            ['first_name' => 'Ada', 'last_name' => 'Player'],
            ['first_name' => 'Bob', 'last_name' => 'Player'],
        ];

        $this->assertTrue(intersoccer_user_has_players($user_id));
        $this->assertFalse(
            intersoccer_pm_player_link_widget_should_show_tour(false, [], $user_id)
        );
    }

    public function test_tour_enabled_when_user_has_no_players(): void
    {
        $user_id = 99;
        $GLOBALS['wp_stub_users'][$user_id]['meta']['intersoccer_players'] = [];

        $this->assertFalse(intersoccer_user_has_players($user_id));
        $this->assertTrue(
            intersoccer_pm_player_link_widget_should_show_tour(false, [], $user_id)
        );
    }
}
