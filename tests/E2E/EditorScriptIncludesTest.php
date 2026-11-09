<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Render the script includes of both editor pages through their real PHP code
 * and check that every split editor script is included once, in dependency
 * order, carrying Cacti's CSP nonce. Each page renders in a child process that
 * defines CactiSecureHeaders and get_md5_include_js before the unit bootstrap,
 * so the nonce path is exercised without leaking those doubles into the suite.
 * The start page runs show_editor_startpage(); the full editor page is a
 * top-level script that needs a Cacti session, so only its <head> include
 * block is executed.
 */

const WM_EDITOR_SCRIPTS = ['editor.js', 'editor.dialogs.js', 'editor.picker.js', 'editor.compact.js', 'editor.assets.js'];

/**
 * Render one editor page in a child process and return its script tags.
 *
 * @param string $page 'start' for the start page, 'editor' for the editor head.
 *
 * @return array<int, array{src: string, tag: string}> Script tags in output order.
 */
function wm_render_editor_scripts(string $page): array {
	$root   = dirname(__DIR__, 2);
	$mapdir = sys_get_temp_dir() . '/wm-e2e-maps-' . getmypid();
	@mkdir($mapdir);

	$script = <<<'CHILD'
<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 'stderr');

class CactiSecureHeaders {
	public static function getNonceAttribute(): string {
		return 'nonce="wm-e2e-nonce"';
	}
}

// Mirror Cacti's include helper, which adds the nonce and a cache-busting hash.
function get_md5_include_js($path, $async = false) {
	global $config;

	return "<script type='text/javascript' " . CactiSecureHeaders::getNonceAttribute() . " src='" . $config['url_path'] . $path . "?0'></script>" . PHP_EOL;
}

[, $root, $page, $mapdir] = $argv;

require $root . '/tests/bootstrap-unit.php';
require_once $root . '/setup.php';
require_once $root . '/lib/editor.inc.php';

if ($page === 'start') {
	$GLOBALS['mapdir']        = $mapdir;
	$GLOBALS['config_loaded'] = false;
	$GLOBALS['configerror']   = '';
	show_editor_startpage();
} else {
	$source = file_get_contents($root . '/weathermap-cacti-plugin-editor.php');
	$start  = strpos($source, '<?php getEditorJs(); ?>');
	$end    = strpos($source, '<title>', (int) $start);

	if ($start === false || $end === false) {
		fwrite(STDERR, 'editor page head not found');
		exit(1);
	}

	// Runs this repository's own template block, the code the page executes.
	eval('?>' . substr($source, $start, $end - $start));
}
CHILD;

	$tmp = tempnam(sys_get_temp_dir(), 'wm_e2e_');

	try {
		file_put_contents($tmp, $script);
		$command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $tmp, $root, $page, $mapdir])) . ' 2>/dev/null';
		$output  = (string) shell_exec($command);
	} finally {
		@unlink($tmp);
		@rmdir($mapdir);
	}

	preg_match_all('/<script\b[^>]*>/', $output, $matches);

	$tags = [];

	foreach ($matches[0] as $tag) {
		$src    = preg_match("/src='([^']*)'/", $tag, $m) ? $m[1] : '';
		$tags[] = ['src' => $src, 'tag' => $tag];
	}

	return $tags;
}

/**
 * Map script tags to editor script file names, ignoring other includes.
 *
 * @param array<int, array{src: string, tag: string}> $tags Script tags.
 *
 * @return array<int, array{name: string, tag: string}> Editor script includes in order.
 */
function wm_editor_script_includes(array $tags): array {
	$found = [];

	foreach ($tags as $tag) {
		if (preg_match('#^/cacti/plugins/weathermap/js/([^/?]+)\?#', $tag['src'], $m) && in_array($m[1], WM_EDITOR_SCRIPTS, true)) {
			$found[] = ['name' => $m[1], 'tag' => $tag['tag']];
		}
	}

	return $found;
}

describe('editor script includes', function (): void {
	it('covers every split editor script on disk', function (): void {
		$onDisk = array_values(array_filter(
			array_map('basename', glob(dirname(__DIR__, 2) . '/js/editor*.js')),
			fn (string $name): bool => !str_starts_with($name, 'editor.history')
		));
		sort($onDisk);
		$expected = WM_EDITOR_SCRIPTS;
		sort($expected);

		expect($onDisk)->toBe($expected);
	});

	foreach (['start' => 'start page', 'editor' => 'editor page'] as $page => $label) {
		it("includes each editor script once, in order, with the CSP nonce on the $label", function () use ($page): void {
			$tags     = wm_render_editor_scripts($page);
			$includes = wm_editor_script_includes($tags);

			expect($tags)->not->toBeEmpty();
			expect(array_column($includes, 'name'))->toBe(WM_EDITOR_SCRIPTS);

			foreach ($includes as $include) {
				expect($include['tag'])->toContain('nonce="wm-e2e-nonce"');
			}
		});
	}

	it('renders the inline editor strings before the editor scripts on the editor page', function (): void {
		$tags  = wm_render_editor_scripts('editor');
		$first = array_search('', array_column($tags, 'src'), true);
		$core  = array_search('/cacti/plugins/weathermap/js/editor.js?0', array_column($tags, 'src'), true);

		expect($first)->not->toBeFalse();
		expect($core)->not->toBeFalse();
		expect($first)->toBeLessThan($core);
		expect($tags[$first]['tag'])->toContain('nonce="wm-e2e-nonce"');
	});
});
