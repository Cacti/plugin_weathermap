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
 * the IN/OUT indices and the WeatherMapDataSource base are declared here rather
 * than pulling in the 4,500-line WeatherMap.class.php and its Cacti bindings.
 * wm_debug() and wm_warn() come from the real WeatherMap.functions.php: that
 * file declares them unconditionally, so a local stub would collide with any
 * other test that loads it.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';

if (!defined('IN')) {
	define('IN', 0);
}

if (!defined('OUT')) {
	define('OUT', 1);
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($string, $quote = true) {
		return escapeshellarg($string);
	}
}

if (!class_exists('WeatherMapDataSource')) {
	class WeatherMapDataSource {
		public $local_data_id;

		public $down_cache = [];

		public function Init(&$map) {
			return true;
		}

		public function Recognise($targetstring) {
			return false;
		}

		public function ReadData($targetstring, &$map, &$item) {
			return ([-1, -1, 0]);
		}
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
