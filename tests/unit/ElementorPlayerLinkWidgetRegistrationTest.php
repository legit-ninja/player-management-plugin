<?php

use PHPUnit\Framework\TestCase;

class ElementorPlayerLinkWidgetRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $GLOBALS['wp_stub_actions'] = [];
        $GLOBALS['wp_stub_did_actions'] = [];

        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-widget.php';
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_player_management_registers_elementor_widgets_register_callback(): void
    {
        $GLOBALS['wp_stub_actions'] = [];
        add_action('elementor/widgets/register', 'intersoccer_pm_register_player_link_elementor_widget', 10);

        $this->assertTrue(
            has_action('elementor/widgets/register', 'intersoccer_pm_register_player_link_elementor_widget')
        );
    }

    public function test_register_callback_is_noop_without_elementor(): void
    {
        $manager = new class () {
            public $registered = false;

            public function register($widget)
            {
                $this->registered = true;
            }
        };

        intersoccer_pm_register_player_link_elementor_widget($manager);

        $this->assertFalse($manager->registered);
    }

    public function test_widget_declares_dynamic_content(): void
    {
        $widget_file = PLAYER_MANAGEMENT_PATH . 'elementor/widgets/player-management-link-widget.php';
        $contents    = file_get_contents($widget_file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('function is_dynamic_content(): bool', $contents);
        $this->assertStringContainsString('return true;', $contents);
    }
}
