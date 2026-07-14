<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('redirect safety in weathermap', function () {
	it('calls exit or die after header Location redirects', function () {
		$files = [
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		];

		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines       = explode("\n", $contents);
			$missingExit = 0;

			for ($i = 0; $i < count($lines); $i++) {
				if (preg_match('/header\s*\(\s*[\'\"]Location/', $lines[$i])) {
					// Next non-empty line should contain exit, die, or return
					$foundExit = false;

					for ($j = $i + 1; $j < min($i + 4, count($lines)); $j++) {
						$next = trim($lines[$j]);

						if ($next === '') {
							continue;
						}

						if (preg_match('/\b(exit|die|return)\b/', $next)) {
							$foundExit = true;
						}

						break;
					}

					if (!$foundExit) {
						$missingExit++;
					}
				}
			}

			expect($missingExit)->toBe(0,
				"File {$relativeFile} has header(Location) without exit/die"
			);
		}
	});
});
