<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Minimum environment needed to load and drive a datasource class.
 *
 * The datasource files are the only part of the plugin these tests exercise, so
 * only the engine constants and the real WeatherMapDataSource base are loaded,
 * not WeatherMap.class.php and its Cacti bindings. Both are safe to load again
 * later in the same process, so a test that also needs the engine can require
 * WeatherMap.class.php after this file. wm_debug() and wm_warn() come from the
 * real WeatherMap.functions.php: that file declares them unconditionally, so a
 * local stub would collide with any other test that loads it.
 */

require_once __DIR__ . '/../bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.constants.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMapDataSource.class.php';

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($string, $quote = true) {
		return escapeshellarg($string);
	}
}

/**
 * Build the $item stand-in a datasource ReadData() expects.
 *
 * @param  string   $name value for the name property the datasources read
 * @return stdClass
 */
function wm_test_item($name = 'testitem') {
	$item       = new stdClass();
	$item->name = $name;

	return $item;
}
