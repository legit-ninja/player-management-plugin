<?php
/**
 * Ensures customer-facing JS strings are translated in fr_FR and de_DE catalogs.
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
}
