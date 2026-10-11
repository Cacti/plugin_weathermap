<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require dirname(__DIR__) . '/bootstrap-unit.php';
$plugin = dirname(__DIR__,2);
chdir($plugin);
require $plugin . '/setup.php';
require $plugin . '/lib/WeatherMap.class.php';

if (!function_exists('cacti_count')) {
	function cacti_count($value) {
		return is_countable($value) ? count($value) : 0;
	}
}

if (!function_exists('clean_up_name')) {
	function clean_up_name($value) {
		return preg_replace('/[^A-Za-z0-9_\-.]/','_',$value);
	}
}

if (!defined('MESSAGE_LEVEL_INFO')) {
	define('MESSAGE_LEVEL_INFO',0);
}
function wm_engine_assert($value,$message) {
	if (!$value) {
		throw new RuntimeException($message);
	}
}
$directory = sys_get_temp_dir() . '/wm-engine-' . bin2hex(random_bytes(8));
mkdir($directory);

try {
	$source = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
	$start  = strpos($source,'function newMap(');
	eval(str_replace('__DIR__', var_export($plugin, true), substr($source, $start)));
	$weathermap_confdir = $directory;
	newMap('new.conf','',"My <network> & 'title'\nTITLE injected");
	$saved = new WeatherMap();
	$saved->ReadConfig($directory . '/new.conf');
	wm_engine_assert($saved->title === 'My &lt;network&gt; &amp; &apos;title&apos; TITLE injected','title round trip and line-break normalization');
	$map        = new WeatherMap();
	$map->width = 910;
	$map->title = 'Source title';
	$map->WriteConfig($directory . '/source.conf');
	$before = file_get_contents($directory . '/source.conf');
	newMap('copy.conf','source.conf','');
	$copy = new WeatherMap();
	$copy->ReadConfig($directory . '/copy.conf');
	wm_engine_assert($copy->title === 'Source title' && $copy->width == 910,'blank title retains source');
	foreach (["\x01", "\x7f", " \r\n\x02 ", null, []] as $index => $empty_title) {
		$filename = 'empty-' . $index . '.conf';
		newMap($filename, 'source.conf', $empty_title);
		$empty_copy = new WeatherMap();
		$empty_copy->ReadConfig($directory . '/' . $filename);
		wm_engine_assert($empty_copy->title === 'Source title' && $empty_copy->width == 910, 'normalized empty override preserves source title and layout');
	}
	newMap('blank-control.conf', '', "\x01");
	$blank = new WeatherMap();
	$blank->ReadConfig($directory . '/blank-control.conf');
	$defaults = new WeatherMap();
	wm_engine_assert($blank->title === $defaults->title, 'normalized empty title preserves a new map default');
	newMap('mixed.conf', 'source.conf', " \x01New\x7ftitle ");
	$mixed = new WeatherMap();
	$mixed->ReadConfig($directory . '/mixed.conf');
	wm_engine_assert($mixed->title === 'New title', 'mixed control characters normalize before applying a real override');
	newMap('renamed.conf','source.conf','New <title> & copy');
	$renamed = new WeatherMap();
	$renamed->ReadConfig($directory . '/renamed.conf');
	wm_engine_assert($renamed->title === 'New &lt;title&gt; &amp; copy' && $renamed->width == 910,'override title preserves layout');
	wm_engine_assert(file_get_contents($directory . '/source.conf') === $before,'source unchanged');

	foreach ([str_repeat('T', 4088), str_repeat('é', 2044)] as $index => $boundary_title) {
		newMap('boundary-' . $index . '.conf', '', $boundary_title);
		$boundary = new WeatherMap();
		$boundary->ReadConfig($directory . '/boundary-' . $index . '.conf');
		wm_engine_assert($boundary->title === $boundary_title, 'maximum byte-length title round-trips intact');
	}
	$include = $directory . '/injected.conf';
	file_put_contents($include, "WIDTH 9999\n");
	foreach ([str_repeat('T', 4089), str_repeat('é', 2045), str_repeat('&', 818), str_repeat('T', 4089) . 'INCLUDE ' . $include] as $index => $oversized_title) {
		foreach (['', 'source.conf'] as $source_map) {
			$filename = 'oversized-' . $index . '-' . ($source_map === '' ? 'blank' : 'copy') . '.conf';
			newMap($filename, $source_map, $oversized_title);
			wm_engine_assert(!file_exists($directory . '/' . $filename), 'oversized title cannot create a config or inject directives');
		}
	}
	wm_engine_assert(file_get_contents($directory . '/source.conf') === $before, 'oversized copied-map override leaves its source untouched');

	print "PASS\n";
} finally {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}rmdir($directory);
}
