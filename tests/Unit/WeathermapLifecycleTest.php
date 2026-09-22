<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_weathermap_version()/numeric_version() and the
 * plugin lifecycle contract wrappers (plugin_weathermap_uninstall,
 * plugin_weathermap_check_config) in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']                = [];
	$GLOBALS['__test_current_page']            = 'graphs.php';
	$GLOBALS['__test_set_config_option_calls'] = [];
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_weathermap_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('weathermap');
});

it('caches and returns the numeric version string', function () {
	$info = plugin_weathermap_version();

	expect(plugin_weathermap_numeric_version())->toBe($info['version']);
});

it('clears the stored version option and drops every table on uninstall', function () {
	plugin_weathermap_uninstall();

	expect($GLOBALS['__test_set_config_option_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_set_config_option_calls'][0])->toBe(['name' => 'weathermap_version', 'value' => '']);

	$drops = array_values(array_map(function ($call) {
		preg_match('/DROP TABLE IF EXISTS (\S+)/i', $call['sql'], $matches);
		return $matches[1] ?? null;
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	})));

	expect($drops)->toEqualCanonicalizing([
		'weathermap_auth',
		'weathermap_data',
		'weathermap_maps',
		'weathermap_groups',
		'weathermap_settings',
	]);
});

it('always reports the config as valid', function () {
	test_set_current_page('graphs.php');

	expect(plugin_weathermap_check_config())->toBeTrue();
});
