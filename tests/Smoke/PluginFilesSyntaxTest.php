<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

/**
 * Parse-check a PHP file entirely in-process using token_get_all().
 *
 * token_get_all() triggers an E_COMPILE_WARNING (converted to a ParseError
 * via a temporary error handler) when the source contains a syntax error.
 * No child process or shell is involved.
 *
 * Returns null on success, or an error message string on failure.
 */
function wm_smoke_check_syntax(string $path): ?string
{
	$source = file_get_contents($path);

	if ($source === false) {
		return "Could not read file: {$path}";
	}

	$error = null;

	set_error_handler(static function (int $errno, string $msg) use (&$error): bool {
		$error = $msg;
		return true;
	});

	try {
		token_get_all($source, TOKEN_PARSE);
	} catch (\ParseError $e) {
		$error = $e->getMessage();
	} finally {
		restore_error_handler();
	}

	return $error;
}

describe('plugin file syntax smoke tests', function (): void {
	it('main plugin files parse without syntax errors', function (): void {
		$root = dirname(__DIR__, 2);

		$files = [
			'cli/cacti-mapper.php',
			'lib/WeatherMap.class.php',
			'lib/WeatherMap.functions.php',
			'lib/datasources/WeatherMapDataSource_fping.php',
			'lib/datasources/WeatherMapDataSource_rrd.php',
			'lib/poller-common.php',
			'weathermap-cacti-plugin.php',
			'weathermap-cacti-plugin-mgmt.php',
		];

		foreach ($files as $relfile) {
			$path = $root . '/' . $relfile;

			if (!file_exists($path)) {
				continue;
			}

			$parseError = wm_smoke_check_syntax($path);

			expect($parseError)->toBeNull("Syntax error in {$relfile}: {$parseError}");
		}
	});

	it('setup.php declares the required Cacti plugin hook functions', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

		expect($source)->toContain('function plugin_weathermap_install(');
		expect($source)->toContain('function plugin_weathermap_uninstall(');
		expect($source)->toContain('function plugin_weathermap_check_config(');
	});

	it('lib/datasources/ contains at least one WeatherMapDataSource_*.php file', function (): void {
		$dir   = dirname(__DIR__, 2) . '/lib/datasources';
		$found = glob($dir . '/WeatherMapDataSource_*.php');

		expect($found)->not->toBeEmpty('No WeatherMapDataSource_*.php files found in lib/datasources/');
	});

	it('each active datasource file declares a class that extends WeatherMapDataSource', function (): void {
		$dir   = dirname(__DIR__, 2) . '/lib/datasources';
		$files = glob($dir . '/WeatherMapDataSource_*.php');

		// Filter out disabled stubs (.php.disabled, .php.txt, etc.).
		$active = array_filter((array) $files, fn (string $f): bool => str_ends_with($f, '.php'));

		foreach ($active as $path) {
			$source = file_get_contents($path);

			if ($source === false) {
				continue;
			}

			$relfile = 'lib/datasources/' . basename($path);

			expect($source)->toContain('extends WeatherMapDataSource');
		}
	});
});
