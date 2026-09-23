<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_weathermap_upgrade() in setup.php: the
 * page-guard.
 *
 * The rest of the function (reached on any allowed page) is intentionally
 * NOT covered here: it include_once()s lib/poller-common.php, which itself
 * include_once()s lib/WeatherMap.class.php - a file this plugin's own
 * other test suites (e.g. tests/Handoff) already load directly by a
 * different path, and loading it a second time via a different resolved
 * path fatals with "Cannot declare class WeatherMapDataSource, because
 * the name is already in use". The version-drift branch also calls
 * weathermap_repair_maps(), which does a real chdir() and reads/writes
 * map config files on disk - a much larger and riskier surface than this
 * suite stubs.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']     = [];
	$GLOBALS['__test_current_page'] = '';
});

it('does nothing on a page that does not need the version check', function () {
	test_set_current_page('graphs.php');

	plugin_weathermap_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});
