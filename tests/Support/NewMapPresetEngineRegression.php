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
	require $plugin . '/lib/editor.new-map-preset.php';
	$map = new WeatherMap();
	wm_new_map_preset($map);
	$map->WriteConfig($directory . '/new.conf');
	$saved = new WeatherMap();
	$saved->ReadConfig($directory . '/new.conf');
	wm_engine_assert($saved->width == 1400 && $saved->height == 750,'canvas');
	wm_engine_assert($saved->htmlstyle === 'overlib','dynamic');
	wm_engine_assert($saved->links['DEFAULT']->arrowstyle === 'compact','arrows');
	wm_engine_assert($saved->links['DEFAULT']->commentfontcolour == [0, 0, 255],'blue');
	$font = $saved->fonts[$saved->links['DEFAULT']->commentfont];
	wm_engine_assert($font->size == 9 && is_readable($font->file),'font');
	$source = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
	$start  = strpos($source,'function newMap(');
	eval(str_replace('__DIR__',var_export($plugin,true),substr($source,$start)));
	$weathermap_confdir = $directory;
	newMap('blank.conf');
	$blank = new WeatherMap();
	$blank->ReadConfig($directory . '/blank.conf');
	wm_engine_assert($blank->width == 1400,'management blank');
	$original            = new WeatherMap();
	$original->width     = 900;
	$original->height    = 500;
	$original->htmlstyle = 'static';
	$original->WriteConfig($directory . '/source.conf');
	newMap('copy.conf','source.conf');
	$copy = new WeatherMap();
	$copy->ReadConfig($directory . '/copy.conf');
	wm_engine_assert($copy->width == 900 && $copy->height == 500 && $copy->htmlstyle === 'static','copy retains source');

	print "PASS\n";
} finally {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}rmdir($directory);
}
