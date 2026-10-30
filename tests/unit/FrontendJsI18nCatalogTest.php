<?php
/**
 * Ensures customer-facing strings are translated in fr_FR and de_DE catalogs.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once PLAYER_MANAGEMENT_PATH . 'includes/frontend-i18n.php';

class FrontendJsI18nCatalogTest extends InterSoccer_Test_Case
{
    /**
     * @return array<string, string>
     */
    private function load_po_translations(string $locale): array
    {
        $path = PLAYER_MANAGEMENT_PATH . 'languages/player-management-' . $locale . '.po';
        $this->assertFileExists($path);

        $raw = file_get_contents($path);
        $this->assertNotFalse($raw);

        $out = [];
        $msgid = null;
        $msgstr = null;
        $mode = null;

        foreach (explode("\n", $raw) as $line) {
            if (strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^msgid "(.*)"$/', $line, $m)) {
                if ($msgid !== null && $msgstr !== null) {
                    $out[$msgid] = $msgstr;
                }
                $msgid = stripcslashes($m[1]);
                $msgstr = null;
                $mode = 'msgid';
                continue;
            }
            if ($line === 'msgid ""') {
                if ($msgid !== null && $msgstr !== null) {
                    $out[$msgid] = $msgstr;
                }
                $msgid = '';
                $msgstr = null;
                $mode = 'msgid';
                continue;
            }
            if (preg_match('/^msgstr "(.*)"$/', $line, $m)) {
                $msgstr = stripcslashes($m[1]);
                $mode = 'msgstr';
                continue;
            }
            if ($line === 'msgstr ""') {
                $msgstr = '';
                $mode = 'msgstr';
                continue;
            }
            if (preg_match('/^"(.*)"$/', $line, $m) && $mode === 'msgid' && $msgid !== null) {
                $msgid .= stripcslashes($m[1]);
                continue;
            }
            if (preg_match('/^"(.*)"$/', $line, $m) && $mode === 'msgstr') {
                $msgstr .= stripcslashes($m[1]);
            }
        }

        if ($msgid !== null && $msgstr !== null) {
            $out[$msgid] = $msgstr;
        }

        return $out;
    }

    public function test_frontend_js_strings_translated_in_fr_and_de_po()
    {
        $strings = intersoccer_pm_frontend_js_i18n();

        foreach (['fr_FR', 'de_DE'] as $locale) {
            $map = $this->load_po_translations($locale);
            foreach ($strings as $key => $msgid) {
                $this->assertArrayHasKey($msgid, $map, $locale . ' missing msgid for ' . $key);
                $this->assertNotSame('', $map[$msgid], $locale . ' empty msgstr for ' . $key);
                $this->assertNotSame($msgid, $map[$msgid], $locale . ' untranslated msgid for ' . $key);
            }
        }
    }

    public function test_pm54_msgids_present_in_all_four_customer_po_files()
    {
        $msgids = [
            'No AVS? Enter foreign insurance number or "0000" and email us the insurance details.',
            'No participants yet. Use Add to register a participant before booking.',
            '<a href="%1$s">Log in</a> or <a href="%2$s">register</a>.',
        ];

        foreach (['fr_CH', 'fr_FR', 'de_CH', 'de_DE'] as $locale) {
            $map = $this->load_po_translations($locale);
            foreach ($msgids as $msgid) {
                $this->assertArrayHasKey($msgid, $map, $locale . ' missing msgid: ' . $msgid);
                $this->assertNotSame('', $map[$msgid], $locale . ' empty msgstr: ' . $msgid);
            }
        }
    }

    public function test_frontend_js_i18n_includes_empty_participants_string()
    {
        $strings = intersoccer_pm_frontend_js_i18n();

        $this->assertArrayHasKey('no_participants_empty', $strings);
        $this->assertSame(
            'No participants yet. Use Add to register a participant before booking.',
            $strings['no_participants_empty']
        );
    }

    /**
     * @return list<string>
     */
    private function customer_facing_msgids_from_sources(): array
    {
        $root = PLAYER_MANAGEMENT_PATH;
        $files = [
            'includes/user-profile-players.php',
            'includes/account-dashboard.php',
            'includes/ajax-handlers.php',
            'includes/frontend-i18n.php',
            'includes/class-validator.php',
        ];
        $strings = [];
        foreach ($files as $rel) {
            $strings = array_merge($strings, $this->extract_php_strings($root . $rel));
        }

        $link_widget = file_get_contents($root . 'elementor/widgets/player-management-link-widget.php');
        $this->assertNotFalse($link_widget);
        $tour_pos = strpos($link_widget, "'tourTitle'");
        $this->assertNotFalse($tour_pos);
        $strings = array_merge($strings, $this->extract_php_strings(substr($link_widget, $tour_pos)));

        $list_lines = file($root . 'includes/class-player-list-elementor-widget.php');
        $this->assertNotFalse($list_lines);
        $render_chunk = implode('', array_slice($list_lines, 199));
        $strings = array_merge($strings, $this->extract_php_strings($render_chunk));

        $pm_lines = file($root . 'includes/player-management.php');
        $this->assertNotFalse($pm_lines);
        $login_chunk = implode('', array_slice($pm_lines, 215, 25));
        $strings = array_merge($strings, $this->extract_php_strings($login_chunk));
        $manage_chunk = implode('', array_slice($pm_lines, 400, 400));
        $strings = array_merge($strings, $this->extract_php_strings($manage_chunk));

        $strings = array_values(array_unique($strings));
        sort($strings);

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function extract_php_strings(string $text): array
    {
        $found = [];
        $patterns = [
            "/__\\(\\s*'((?:\\\\'|[^'])*)'\\s*,/",
            '/_e\\(\\s*\'((?:\\\\\'|[^\'])*)\'\\s*,/',
            "/esc_html__\\(\\s*'((?:\\\\'|[^'])*)'\\s*,/",
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $raw) {
                    $found[] = str_replace("\\'", "'", $raw);
                }
            }
        }

        return $found;
    }

    /**
     * @return array<string, list<string>>
     */
    private function allowed_identical_msgids_by_locale(): array
    {
        return [
            'fr_FR' => ['Camp', 'Canton', 'Actions', 'Name'],
            'de_DE' => ['Camp', 'Name'],
        ];
    }

    public function test_customer_facing_php_strings_translated_in_fr_and_de_po()
    {
        $msgids = $this->customer_facing_msgids_from_sources();
        $this->assertNotEmpty($msgids);

        foreach (['fr_FR', 'de_DE'] as $locale) {
            $map = $this->load_po_translations($locale);
            $allowed_identical = $this->allowed_identical_msgids_by_locale()[$locale] ?? [];
            foreach ($msgids as $msgid) {
                $this->assertArrayHasKey($msgid, $map, $locale . ' missing msgid: ' . $msgid);
                $this->assertNotSame('', $map[$msgid], $locale . ' empty msgstr: ' . $msgid);
                if ($map[$msgid] === $msgid && !in_array($msgid, $allowed_identical, true)) {
                    $this->fail($locale . ' untranslated msgid: ' . $msgid);
                }
            }
        }
    }
}
