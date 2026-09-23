<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for weathermap_check_set_boost(), weathermap_top_graph_refresh(),
 * weathermap_page_title(), and weathermap_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']       = [];
	$GLOBALS['__test_request']        = [];
	$GLOBALS['__test_config_options'] = [];
	$GLOBALS['__test_db_fetch_row']   = fn ($sql) => [];
	unset($_SERVER['PHP_SELF'], $_SERVER['REQUEST_URI']);
});

it('does nothing when boost_rrd_update_enable is not on', function () {
	weathermap_check_set_boost();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('inserts the poller-output setting when boost is on and no row exists yet', function () {
	$GLOBALS['__test_config_options']['boost_rrd_update_enable'] = 'on';
	$GLOBALS['__test_db_fetch_row'] = fn ($sql) => [];

	weathermap_check_set_boost();

	$inserts = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'INSERT INTO weathermap_settings') !== false;
	});

	expect($inserts)->toHaveCount(1);
});

it('updates the existing poller-output setting when boost is on and the row is disabled', function () {
	$GLOBALS['__test_config_options']['boost_rrd_update_enable'] = 'on';
	$GLOBALS['__test_db_fetch_row'] = fn ($sql) => ['id' => 5, 'optvalue' => 0];

	weathermap_check_set_boost();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE weathermap_settings') !== false;
	});

	expect($updates)->toHaveCount(1);
	expect(array_values($updates)[0]['params'])->toBe([5]);
});

it('leaves other pages alone regardless of refresh handling', function () {
	$_SERVER['PHP_SELF'] = '/cacti/graphs.php';

	expect(weathermap_top_graph_refresh(300))->toBe(300);
});

it('forces a 24 hour refresh while cycling maps', function () {
	$_SERVER['PHP_SELF']       = '/cacti/plugins/weathermap/weathermap-cacti-plugin.php';
	$GLOBALS['__test_request'] = ['action' => 'viewmapcycle'];

	expect(weathermap_top_graph_refresh(300))->toBe(86400);
});

it('leaves other pages untouched in the page title hook', function () {
	$_SERVER['REQUEST_URI'] = '/cacti/graphs.php';

	expect(weathermap_page_title('Cacti'))->toBe('Cacti');
});

it('adds the weathermap breadcrumb entries without disturbing existing ones', function () {
	$nav = weathermap_draw_navigation_text(['other.php:' => ['title' => 'Other']]);

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('weathermap-cacti-plugin.php:');
	expect($nav['weathermap-cacti-plugin.php:']['url'])->toBe('weathermap-cacti-plugin.php');
});
