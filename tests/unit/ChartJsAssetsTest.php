<?php

use PHPUnit\Framework\TestCase;

class ChartJsAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $GLOBALS['intersoccer_test_enqueued_scripts'] = [];

        require_once PLAYER_MANAGEMENT_PATH . 'includes/chart-js-assets.php';

        WP_Mock::userFunction('wp_enqueue_script')->andReturnUsing(function ($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
            $GLOBALS['intersoccer_test_enqueued_scripts'][] = [
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

    public function test_chart_js_handle_and_local_file(): void
    {
        $this->assertSame('intersoccer-pm-chart-js', intersoccer_pm_chart_js_asset_handle());

        intersoccer_pm_enqueue_chart_js_vendor_asset();

        $this->assertCount(1, $GLOBALS['intersoccer_test_enqueued_scripts']);
        $item = $GLOBALS['intersoccer_test_enqueued_scripts'][0];
        $this->assertSame('intersoccer-pm-chart-js', $item['handle']);
        $this->assertStringContainsString('assets/vendor/chart.js/3.9.1/chart.min.js', $item['src']);
        $this->assertStringStartsWith(PLAYER_MANAGEMENT_URL, $item['src']);

        $path = PLAYER_MANAGEMENT_PATH . 'assets/vendor/chart.js/3.9.1/chart.min.js';
        $this->assertFileExists($path);
        $this->assertFileExists(PLAYER_MANAGEMENT_PATH . 'assets/vendor/chart.js/3.9.1/LICENSE');
    }

    public function test_overview_php_has_no_chart_js_cdn(): void
    {
        $contents = file_get_contents(PLAYER_MANAGEMENT_PATH . 'includes/class-player-overview.php');
        $this->assertIsString($contents);
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/chart.js', $contents);
    }

    public function test_overview_localizes_with_self_hosted_chart_handle(): void
    {
        $contents = file_get_contents(PLAYER_MANAGEMENT_PATH . 'includes/class-player-overview.php');
        $this->assertIsString($contents);
        $this->assertStringContainsString(
            "wp_localize_script(intersoccer_pm_chart_js_asset_handle(), 'intersoccerChartLabels'",
            $contents
        );
        $this->assertStringNotContainsString("wp_localize_script('chart-js'", $contents);
    }
}
