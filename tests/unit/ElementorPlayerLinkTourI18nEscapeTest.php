<?php

use PHPUnit\Framework\TestCase;

class ElementorPlayerLinkTourI18nEscapeTest extends TestCase
{
    private const XSS = '<img src=x onerror=alert(1)>';

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('esc_html__')->andReturnUsing(function ($text, $domain = 'default') {
            $translated = $GLOBALS['intersoccer_test_gettext_override'] ?? $text;

            return htmlspecialchars((string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        });

        require_once PLAYER_MANAGEMENT_PATH . 'includes/elementor-player-link-helpers.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['intersoccer_test_gettext_override']);
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_tour_i18n_strings_are_html_escaped_when_translation_is_malicious(): void
    {
        $GLOBALS['intersoccer_test_gettext_override'] = self::XSS;

        $strings = intersoccer_pm_player_link_widget_tour_i18n();

        $this->assertNotEmpty($strings);
        foreach ($strings as $key => $value) {
            $this->assertIsString($value, $key);
            $this->assertStringNotContainsString('<img', $value, $key);
            $this->assertStringContainsString('&lt;img', $value, $key);
        }
    }
}
