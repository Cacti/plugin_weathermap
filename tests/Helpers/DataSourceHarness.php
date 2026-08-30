<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Environment needed to load and drive the plugin's classes under test.
 *
 * The real WeatherMap.class.php and WeatherMap.functions.php are loaded rather
 * than stubbed.  Both declare their functions and classes unconditionally, so a
 * local stub of WeatherMapDataSource or wm_debug() would collide with any other
 * test that pulls the real file in, and the collision would depend on the order
 * PHPUnit happened to load the test files.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.class.php';

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
