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

it('runs the realm/version updates and prune on a version drift', function () {
test_set_current_page('plugins.php');

// Sandbox base_path with an empty poller-common stub + minimal INFO so the
// upgrade-time prune runs against a throwaway tree, never the real checkout,
// and the heavy lib include is a no-op.
$restore = $GLOBALS['config']['base_path'];
$base    = sys_get_temp_dir() . '/weathermap-upg-' . uniqid();
mkdir($base . '/plugins/weathermap/lib', 0777, true);
file_put_contents($base . '/plugins/weathermap/lib/poller-common.php', "<?php\n");
file_put_contents($base . '/plugins/weathermap/INFO', "[info]\nversion = 9.9.9\nname = weathermap\nlongname = Weathermap\nauthor = x\nhomepage = x\n");
$GLOBALS['config']['base_path'] = $base;

try {
plugin_weathermap_upgrade();
} finally {
$GLOBALS['config']['base_path'] = $restore;
}

$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
}));

expect($updates)->not->toBeEmpty();
});
