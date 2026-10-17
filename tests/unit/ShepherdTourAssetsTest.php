<?php

use PHPUnit\Framework\TestCase;

class ShepherdTourAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $GLOBALS['intersoccer_test_enqueued_scripts'] = [];
        $GLOBALS['intersoccer_test_enqueued_styles']  = [];

        require_once PLAYER_MANAGEMENT_PATH . 'includes/shepherd-tour-assets.php';

        WP_Mock::userFunction('wp_enqueue_script')->andReturnUsing(function ($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
            $GLOBALS['intersoccer_test_enqueued_scripts'][] = [
                'handle' => $handle,
                'src'    => $src,
                'ver'    => $ver,
            ];
        });
        WP_Mock::userFunction('wp_enqueue_style')->andReturnUsing(function ($handle, $src = '', $deps = [], $ver = false) {
            $GLOBALS['intersoccer_test_enqueued_styles'][] = [
                'handle' => $handle,
                'src'    => $src,
                'ver'    => $ver,
            ];
        });
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_enqueue_uses_local_pinned_shepherd_assets(): void
    {
        WP_Mock::userFunction('is_checkout')->andReturn(false);
        WP_Mock::userFunction('is_cart')->andReturn(false);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);

        intersoccer_pm_enqueue_shepherd_vendor_assets();

        $this->assertNotEmpty($GLOBALS['intersoccer_test_enqueued_scripts']);
        foreach ($GLOBALS['intersoccer_test_enqueued_scripts'] as $item) {
            $this->assertStringNotContainsString('cdn.jsdelivr', $item['src']);
            $this->assertStringNotContainsString('@latest', $item['src']);
            $this->assertStringContainsString('assets/vendor/shepherd/11.2.0/shepherd.min.js', $item['src']);
        }
        $this->assertSame('11.2.0', $GLOBALS['intersoccer_test_enqueued_scripts'][0]['ver']);

        $this->assertNotEmpty($GLOBALS['intersoccer_test_enqueued_styles']);
        $this->assertStringContainsString('assets/vendor/shepherd/11.2.0/shepherd.css', $GLOBALS['intersoccer_test_enqueued_styles'][0]['src']);
    }

    public function test_enqueue_skipped_on_checkout(): void
    {
        WP_Mock::userFunction('is_checkout')->andReturn(true);
        WP_Mock::userFunction('is_cart')->andReturn(false);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);

        intersoccer_pm_enqueue_shepherd_vendor_assets();

        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_scripts']);
        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_styles']);
    }

    public function test_enqueue_skipped_on_cart(): void
    {
        WP_Mock::userFunction('is_checkout')->andReturn(false);
        WP_Mock::userFunction('is_cart')->andReturn(true);
        WP_Mock::userFunction('is_wc_endpoint_url')->andReturn(false);

        intersoccer_pm_enqueue_shepherd_vendor_assets();

        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_scripts']);
    }

    public function test_enqueue_skipped_on_order_pay_endpoint(): void
    {
        WP_Mock::userFunction('is_checkout')->andReturn(false);
        WP_Mock::userFunction('is_cart')->andReturn(false);
        WP_Mock::userFunction('is_wc_endpoint_url')->with('order-pay')->andReturn(true);

        intersoccer_pm_enqueue_shepherd_vendor_assets();

        $this->assertEmpty($GLOBALS['intersoccer_test_enqueued_scripts']);
    }

    public function test_show_tour_control_defaults_to_off(): void
    {
        $widget_file = PLAYER_MANAGEMENT_PATH . 'elementor/widgets/player-management-link-widget.php';
        $contents    = file_get_contents($widget_file);
        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression(
            "/'show_tour'[\\s\\S]*?'default'\\s*=>\\s*''/",
            $contents
        );
    }

    public function test_widget_does_not_reference_cdn_shepherd_urls(): void
    {
        $widget_file = PLAYER_MANAGEMENT_PATH . 'elementor/widgets/player-management-link-widget.php';
        $contents    = file_get_contents($widget_file);
        $this->assertStringNotContainsString('cdn.jsdelivr', $contents);
        $this->assertStringNotContainsString('@latest', $contents);
    }
}
