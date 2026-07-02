#!/usr/bin/env php
<?php
// Simple script to add/update headers in all PHP files (excludes vendor/).
$header = <<<'HEADER'
<?php
/**
 * Plugin Name: InterSoccer Player Management
 * Plugin URI: https://github.com/legit-ninja/player-management-plugin
 * Description: Manages players for InterSoccer events, including registration, metadata storage (e.g., DOB, gender, medical/dietary), and integration with WooCommerce orders for rosters.
 * Version: 1.3.96
 * Author: Jeremy Lee
 * Author URI: https://underdogunlimited.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: player-management
 * Domain Path: /languages
 */

HEADER;

// Recursively find all .php files, exclude vendor/.
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getPathname(), '/vendor/') === false) {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Check if header is missing (look for 'Plugin Name').
    if (strpos($content, 'Plugin Name: InterSoccer Player Management') === false) {
        // Remove existing <?php if at top to avoid dupes.
        $content = preg_replace('/^\s*<\?php\s*/', '', ltrim($content));
        // Prepend header.
        file_put_contents($file, $header . $content);
        echo "Updated header in $file\n";
    } else {
        echo "Header already up-to-date in $file\n";
    }
}