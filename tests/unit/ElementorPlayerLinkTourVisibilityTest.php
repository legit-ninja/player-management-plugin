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
        $GLOBALS['intersoccer_test_enqueued_scripts'] = [];
        $GLOBALS['intersoccer_test_enqueued_styles']  = [];
        $GLOBALS['intersoccer_test_localized_scripts'] = [];

        require_once PLAYER_MANAGEMENT_PATH . 'includes/shepherd-tour-assets.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-helpers.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-widget.php';

        WP_Mock::userFunction('is_checkout')->andReturn(false);
        WP_Mock::userFunction('is_cart')->andReturn(false);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function ($text) {
            return $text;
        });
        WP_Mock::userFunction('wp_enqueue_script')->andReturnUsing(function ($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
            $GLOBALS['intersoccer_test_enqueued_scripts'][] = [
                'handle' => $handle,
                'src'    => $src,
            ];
        });
        WP_Mock::userFunction('wp_enqueue_style')->andReturnUsing(function ($handle, $src = '', $deps = [], $ver = false) {
            $GLOBALS['intersoccer_test_enqueued_styles'][] = [
                'handle' => $handle,
                'src'    => $src,
            ];
        });
        WP_Mock::userFunction('wp_localize_script')->andReturnUsing(function ($handle, $object_name, $data) {
            $GLOBALS['intersoccer_test_localized_scripts'][] = [
                'handle'       => $handle,
                'object_name'  => $object_name,
                'data'         => $data,
            ];
        });
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_stub_logged_in'], $GLOBALS['wp_stub_current_user_id']);
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
        $GLOBALS['wp_stub_logged_in'] = true;
        $GLOBALS['wp_stub_current_user_id'] = $user_id;

        $this->assertTrue(intersoccer_user_has_players($user_id));
        $this->assertFalse(
            intersoccer_pm_player_link_widget_should_show_tour(false, [], $user_id)
        );
        $this->simulateWidgetTourEnqueue(false, [], $user_id);
        $this->assertNoTourAssetsEnqueued();
    }

    public function test_tour_enabled_when_user_has_no_players(): void
    {
        $user_id = 99;
        $GLOBALS['wp_stub_users'][$user_id]['meta']['intersoccer_players'] = [];
        $GLOBALS['wp_stub_logged_in'] = true;
        $GLOBALS['wp_stub_current_user_id'] = $user_id;

        $this->assertFalse(intersoccer_user_has_players($user_id));
        $this->assertTrue(
            intersoccer_pm_player_link_widget_should_show_tour(false, [], $user_id)
        );
        $this->simulateWidgetTourEnqueue(false, [], $user_id);
        $this->assertTourAssetsEnqueued();
    }

    public function test_tour_disabled_for_logged_out_visitor(): void
    {
        $GLOBALS['wp_stub_logged_in'] = false;
        $GLOBALS['wp_stub_current_user_id'] = 0;

        $this->assertFalse(
            intersoccer_pm_player_link_widget_should_show_tour(false, [], 0)
        );
        $this->simulateWidgetTourEnqueue(false, [], 0);
        $this->assertNoTourAssetsEnqueued();
    }

    /**
     * Mirrors the widget render() tour enqueue gate (without Elementor).
     */
    private function simulateWidgetTourEnqueue(bool $is_editor, array $stored_settings, int $user_id): void
    {
        $show_tour = intersoccer_pm_player_link_widget_should_show_tour($is_editor, $stored_settings, $user_id);
        if (!$show_tour) {
            return;
        }

        intersoccer_pm_enqueue_shepherd_vendor_assets();
        wp_enqueue_script(
            'intersoccer-player-tour',
            PLAYER_MANAGEMENT_URL . 'js/shepherd-tour.js',
            [intersoccer_pm_shepherd_asset_handle(), 'jquery'],
            PLAYER_MANAGEMENT_VERSION,
            true
        );
        wp_localize_script('intersoccer-player-tour', 'intersoccerPlayerTour', [
            'autoStart'        => true,
            'delay'            => 2,
            'hasPlayers'       => intersoccer_user_has_players($user_id),
            'managePlayersUrl' => 'http://example.com/my-account/manage-players/',
            'i18n'             => intersoccer_pm_player_link_widget_tour_i18n(),
        ]);
    }

    private function assertNoTourAssetsEnqueued(): void
    {
        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_scripts']);
        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_styles']);
        $this->assertEmpty($GLOBALS['intersoccer_test_localized_scripts']);
    }

    private function assertTourAssetsEnqueued(): void
    {
        $handles = array_column($GLOBALS['intersoccer_test_enqueued_scripts'], 'handle');
        $this->assertContains('intersoccer-pm-shepherd', $handles);
        $this->assertContains('intersoccer-player-tour', $handles);
        $style_handles = array_column($GLOBALS['intersoccer_test_enqueued_styles'], 'handle');
        $this->assertContains('intersoccer-pm-shepherd', $style_handles);
        $this->assertNotEmpty($GLOBALS['intersoccer_test_localized_scripts']);
        $this->assertSame('intersoccerPlayerTour', $GLOBALS['intersoccer_test_localized_scripts'][0]['object_name']);
    }
}
