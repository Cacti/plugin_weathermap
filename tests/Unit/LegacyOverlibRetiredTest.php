<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * List the plugin's own files below the given directories with the given extensions.
 *
 * Only repository sources are scanned; Cacti's vendored include/js is outside the plugin.
 *
 * @param string[] $directories Directories relative to the plugin root.
 * @param string[] $extensions  Lower-case file extensions without the dot.
 *
 * @return string[] Paths relative to the plugin root.
 */
function wm_retired_overlib_sources(array $directories, array $extensions): array {
	$root  = dirname(__DIR__, 2);
	$files = [];

	foreach ($directories as $directory) {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $file) {
			if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
				$files[] = substr($file->getPathname(), strlen($root) + 1);
			}
		}
	}

	sort($files);

	return $files;
}

it('no longer ships the overLIB library', function (): void {
	$root = dirname(__DIR__, 2);

	$bundled = array_filter(wm_retired_overlib_sources(['docs', 'js'], ['js']), fn ($file) => stripos(basename($file), 'overlib') !== false);

	expect(file_exists($root . '/docs/example/overlib.js'))->toBeFalse()
		->and($bundled)->toBe([]);
});

it('keeps dynamic code evaluation out of shipped JavaScript and documentation', function (string $file): void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect(preg_match('/\beval\s*\(/', $source))->toBe(0, "$file calls eval()")
		->and(preg_match('/\bnew\s+Function\s*\(/', $source))->toBe(0, "$file constructs a Function from a string")
		->and(preg_match('/\bset(?:Timeout|Interval)\s*\(\s*[\'"`]/', $source))->toBe(0, "$file passes a code string to a timer");
})->with(fn () => wm_retired_overlib_sources(['js', 'docs'], ['js', 'html', 'htm']));

it('does not reference overlib.js from any shipped page or HTML writer', function (string $file): void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect(preg_match('/src\s*=\s*\\\\?[\'"]?[^\'"\s>]*overlib\.js/i', $source))->toBe(0, "$file loads overlib.js")
		->and(preg_match('/\b(?:overlib|nd)\s*\(\s*[^)]/i', $source) + preg_match('/return\s+nd\s*\(\s*\)/i', $source))->toBe(0, "$file calls overLIB functions");
})->with(fn () => array_merge(
	['weathermap', 'setup.php', 'weathermap-cacti-plugin.php', 'weathermap-cacti-plugin-editor.php'],
	wm_retired_overlib_sources(['lib', 'cli', 'js', 'docs'], ['php', 'js', 'html', 'htm'])
));

it('loads only scripts that exist from bundled documentation pages', function (string $file): void {
	$root     = dirname(__DIR__, 2);
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$document->loadHTML(file_get_contents($root . '/' . $file));
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	$missing  = [];
	$handlers = [];

	foreach ($document->getElementsByTagName('script') as $script) {
		$src = $script->getAttribute('src');

		if ($src !== '' && !preg_match('#^(?:[a-z]+:)?//#i', $src) && !is_file(dirname($root . '/' . $file) . '/' . strtok($src, '?#'))) {
			$missing[] = $src;
		}
	}

	foreach ($document->getElementsByTagName('*') as $element) {
		foreach ($element->attributes as $attribute) {
			if (str_starts_with(strtolower($attribute->name), 'on') && preg_match('/\\b(?:overlib|nd)\\s*\\(/i', $attribute->value)) {
				$handlers[] = $attribute->value;
			}
		}
	}

	expect($missing)->toBe([], "$file loads missing scripts")
		->and($handlers)->toBe([], "$file still calls overLIB");
})->with(fn () => wm_retired_overlib_sources(['docs'], ['html', 'htm']));
