<?php
/**
 * Ensures only the bootstrap file advertises a WordPress plugin header.
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class PluginHeaderTest extends InterSoccer_Test_Case
{
    private const BOOTSTRAP = 'player-management.php';

    /**
     * @return string[]
     */
    private function plugin_php_files(): array
    {
        $root = dirname(__DIR__, 2);
        $skip = ['vendor', 'tests', 'node_modules'];
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            foreach ($skip as $segment) {
                if (strpos($path, DIRECTORY_SEPARATOR . $segment . DIRECTORY_SEPARATOR) !== false) {
                    continue 2;
                }
            }
            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    public function test_only_bootstrap_has_plugin_name_header(): void
    {
        $with_header = [];
        $root = dirname(__DIR__, 2);

        foreach ($this->plugin_php_files() as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                $this->fail('Could not read ' . $path);
            }
            if (preg_match('/^\s*\*?\s*Plugin Name:/m', $contents) === 1) {
                $with_header[] = substr($path, strlen($root) + 1);
            }
        }

        $this->assertSame([self::BOOTSTRAP], $with_header);
    }
}
