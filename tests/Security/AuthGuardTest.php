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

describe('check.php web access', function () {
	/* The report names the host, the kernel, the PHP build and the ini paths.
	 * Over the web it sits behind the Manage Weathermap realm; the CLI run stays
	 * open, because comparing the two PHP configurations is the point of it. */
	it('pulls in Cacti auth on the web path', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		expect($source)->toContain("include_once(__DIR__ . '/../../include/auth.php');");
	});

	it('leaves the command line run ungated', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		expect($source)->toContain("if (PHP_SAPI !== 'cli') {");
	});

	it('gates before it reads any server detail', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		$guard  = strpos($source, "PHP_SAPI !== 'cli'");
		$firstReport = strpos($source, 'phpversion()');

		expect($guard)->not->toBeFalse();
		expect($firstReport)->not->toBeFalse();
		expect($guard)->toBeLessThan($firstReport);
	});

	it('registers check.php against the Manage Weathermap realm on install', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

		expect($source)->toContain("'weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php,check.php', 'Manage Weathermap'");
	});

	it('widens the realm for installs that already carry the older value', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

		// The upgrade runs after the two-file widening, so an old single-file
		// realm is migrated in two steps rather than being missed.
		$twoFile   = strpos($source, "['weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php', 'weathermap-cacti-plugin-mgmt.php']");
		$threeFile = strpos($source, "['weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php,check.php', 'weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php']");

		expect($twoFile)->not->toBeFalse();
		expect($threeFile)->not->toBeFalse();
		expect($twoFile)->toBeLessThan($threeFile);
	});
});
