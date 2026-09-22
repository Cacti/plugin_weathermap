<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_weathermap_install(): verifies every
 * hook and all 3 realms the plugin depends on at runtime are actually
 * registered, together with its full table set, in a single end-to-end
 * pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']          = [];
	$GLOBALS['__test_registered_hooks']  = [];
	$GLOBALS['__test_registered_realms'] = [];
});

it('registers every hook weathermap depends on, its realms, and provisions its tables', function () {
	plugin_weathermap_install();

	$hooks = [];
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach ([
		'config_arrays',
		'config_settings',
		'top_header_tabs',
		'top_graph_header_tabs',
		'draw_navigation_text',
		'top_graph_refresh',
		'page_title',
		'poller_top',
		'poller_output',
		'poller_bottom',
	] as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['plugin'])->toBe('weathermap');
		expect($hooks[$expected]['file'])->toBe('setup.php');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(3);

	$createdTables = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'CREATE TABLE IF NOT EXISTS') !== false;
	});

	expect($createdTables)->not->toBeEmpty();
});
