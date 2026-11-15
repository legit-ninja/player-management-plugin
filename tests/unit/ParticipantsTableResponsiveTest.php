<?php
/**
 * Participants table stays usable on a narrow My Account dashboard.
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class ParticipantsTableResponsiveTest extends InterSoccer_Test_Case
{
    public function test_participants_table_css_scrolls_and_wraps_without_colours()
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/player-management.css');
        $this->assertIsString($css);

        $start = strpos($css, 'pm-participants-table-responsive:start');
        $end = strpos($css, 'pm-participants-table-responsive:end');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        $block = substr($css, $start, $end - $start);

        $this->assertStringContainsString('.intersoccer-table-wrapper', $block);
        $this->assertStringContainsString('overflow-x: auto', $block);
        $this->assertStringContainsString('minmax(0, 1fr)', $block);
        $this->assertStringContainsString('@media screen and (max-width: 768px)', $block);
        $this->assertStringContainsString('overflow-wrap: break-word', $block);
        $this->assertStringContainsString('white-space: normal', $block);

        $this->assertStringNotContainsString('color:', $block);
        $this->assertStringNotContainsString('background', $block);
        $this->assertStringNotContainsString('border-color', $block);
        $this->assertStringNotContainsString('!important', $block);
    }

    public function test_participants_stylesheet_is_enqueued_for_the_account_dashboard()
    {
        $plugin = file_get_contents(dirname(__DIR__, 2) . '/player-management.php');
        $this->assertIsString($plugin);
        $this->assertStringContainsString('intersoccer_pm_should_enqueue_player_assets', $plugin);
        $this->assertStringContainsString('css/player-management.css', $plugin);
    }
}
