<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('auth guard presence in weathermap', function () {
	it('includes auth.php or global.php in all web UI entry points', function () {
		$uiFiles = [
			'weathermap-cacti-plugin.php',
			'weathermap-cacti-plugin-mgmt.php',
			'weathermap-cacti-plugin-editor.php',
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
		$uiFiles = [
			'weathermap-cacti-plugin.php',
			'weathermap-cacti-plugin-mgmt.php',
			'weathermap-cacti-plugin-editor.php',
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

			if (preg_match('/get_request_var\s*\(\s*[\'\"]id[\'\"]/', $contents)) {
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

describe('check.php command line carve-out', function () {
	/* The web path is covered behaviourally in CheckPageAccessTest, which drives
	 * the page under a CGI SAPI with the realm denied.  Only the CLI carve-out is
	 * asserted here, because it is a deliberate decision rather than a mechanism:
	 * running the report both ways is how the two PHP configurations get
	 * compared. */
	it('leaves the command line run ungated', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		expect($source)->toContain("if (PHP_SAPI !== 'cli') {");
	});
});
