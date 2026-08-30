<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('output escaping in weathermap', function () {
	it('does not interpolate raw variables into HTML attributes', function () {
		$uiFiles = [
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		];

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines     = explode("\n", $contents);
			$dangerous = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
					continue;
				}

				// value="$row[...] without html_escape wrapping
				if (preg_match('/value\s*=\s*["\'"]\s*<\?php\s+echo\s+\$/', $line)) {
					$dangerous++;
				}

				// title="<?php print $something without escaping
				if (preg_match('/(?:title|alt|placeholder)\s*=.*print\s+\$(?!_|config)/', $line)) {
					if (strpos($line, 'html_escape') === false && strpos($line, '__esc') === false && strpos($line, 'htmlspecialchars') === false) {
						$dangerous++;
					}
				}
			}

			expect($dangerous)->toBe(0,
				"File {$relativeFile} has unescaped variables in HTML attributes"
			);
		}
	});

	it('uses html_escape or __esc for user-controlled output', function () {
		$uiFiles = [
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		];

		$totalEscapeCalls = 0;

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$totalEscapeCalls += preg_match_all('/html_escape|__esc\(|htmlspecialchars/', $contents);
		}

		// At least some escaping should be present in UI files
		expect($totalEscapeCalls)->toBeGreaterThan(0,
			'UI files should contain at least one html_escape/__esc call'
		);
	});
});

describe('titles interpolated into messages', function () {
	/* map_clean_title() only strips control characters, so an HTML payload in a
	 * map title survives into weathermap_maps.titlecache and is then reflected
	 * by the duplicate-map messages.  Those use __esc() rather than __(). */
	it('escapes the duplicate map messages', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		expect(substr_count($source, "__esc('The new Map with the name %s"))->toBe(3);
		expect($source)->not->toContain("__('The new Map with the name %s");
	});

	it('escapes the map title before it reaches the RSS feed', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

		expect($source)->toContain('$maptitle = html_escape($maptitle);');
	});

	it('leaves no unescaped title interpolation in the duplicate path', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');
		$lines  = explode("\n", $source);
		$bare   = [];

		foreach ($lines as $num => $line) {
			if (preg_match("/__\\('[^']*%s[^']*',\\s*\\\$save\\[/", $line)) {
				$bare[] = 'line ' . ($num + 1) . ': ' . trim($line);
			}
		}

		expect($bare)->toBe([]);
	});
});
