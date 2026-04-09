<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('auth guard presence in weathermap', function () {
	it('includes auth.php or global.php in all UI entry points', function () {
		$uiFiles = array(
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		);

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			// Files that include setup.php or are library files don't need direct auth
			if (strpos($relativeFile, 'include/') === 0 || strpos($relativeFile, 'lib/') === 0) continue;
			if (strpos($relativeFile, 'poller_') === 0) continue;

			$hasAuth = (
				strpos($contents, 'auth.php') !== false ||
				strpos($contents, 'global.php') !== false ||
				strpos($contents, 'global_arrays.php') !== false
			);

			expect($hasAuth)->toBeTrue(
				"File {$relativeFile} does not include auth.php or global.php"
			);
		}
	});

	it('validates numeric IDs from request variables before DB queries', function () {
		$uiFiles = array(
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
		);

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			// Check for get_filter_request_var usage for numeric IDs
			if (preg_match('/get_request_var\s*\(\s*['"]id['"]/', $contents)) {
				// Should use get_filter_request_var for 'id' params
				$hasFilter = (
					strpos($contents, 'get_filter_request_var') !== false ||
					strpos($contents, 'input_validate_input_number') !== false ||
					strpos($contents, 'form_input_validate') !== false
				);

				expect($hasFilter)->toBeTrue(
					"File {$relativeFile} uses get_request_var for IDs without validation"
				);
			}
		}
	});
});
