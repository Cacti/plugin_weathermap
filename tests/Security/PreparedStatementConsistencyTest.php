<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('prepared statement consistency in weathermap', function () {
	it('uses prepared DB helpers in all plugin files', function () {
		$targetFiles = array(
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		);

		$rawPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/';
		$preparedPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/';

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			$lines = explode("\n", $contents);
			$rawCalls = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);
				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0) continue;
				if (preg_match($rawPattern, $line) && !preg_match($preparedPattern, $line)) {
					$rawCalls++;
				}
			}

			expect($rawCalls)->toBe(0, "File {$relativeFile} contains raw DB calls");
		}
	});

	it('uses parameterized placeholders not string interpolation in SQL', function () {
		$targetFiles = array(
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		);

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			$lines = explode("\n", $contents);
			$interpolatedSql = 0;

			foreach ($lines as $num => $line) {
				$trimmed = ltrim($line);
				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) continue;

				// Detect _prepared calls with $ interpolation instead of ? placeholders
				if (preg_match('/_prepared\s*\(/', $line) && preg_match('/\$[a-zA-Z_]/', $line)) {
					// Allow array($var) param binding but flag "WHERE id = $var"
					if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE|WHERE|SET|FROM|JOIN).*\$/', $line)) {
						$interpolatedSql++;
					}
				}
			}

			// This is a heuristic; some false positives expected for complex queries
			expect($interpolatedSql)->toBeLessThanOrEqual(2,
				"File {$relativeFile} may have SQL interpolation in prepared calls"
			);
		}
	});
});
