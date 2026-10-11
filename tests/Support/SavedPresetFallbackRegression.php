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

// Collect real execution in the runtime with FreeType disabled when coverage is requested.
$coverageFile = $argv[1] ?? null;
if ($coverageFile !== null && function_exists('xdebug_start_code_coverage')) {
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}

require dirname(__DIR__) . '/bootstrap-unit.php';
$plugin = dirname(__DIR__, 2);
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
		return preg_replace('/[^A-Za-z0-9_\-.]/', '_', $value);
	}
}
$directory = sys_get_temp_dir() . '/wm-fallback-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
	$file = $directory . '/saved.conf';
	file_put_contents($file, "WIDTH 200\nHEIGHT 100\nFONTDEFINE 100 docs/example/Vera.ttf 9\nLINK DEFAULT\n BWFONT 100\n COMMENTFONT 100\nNODE A\n LABEL A\n POSITION 20 50\nNODE B\n LABEL B\n POSITION 180 50\nLINK A-B\n NODES A B\n OUTCOMMENT 1G\n");
	$map = new WeatherMap();
	$map->ReadConfig($file);
	if (isset($map->fonts[100])) {
		throw new RuntimeException('Unavailable FreeType font was registered');
	}
	$map->DrawMap($directory . '/saved.png');
	$image = imagecreatefrompng($directory . '/saved.png');
	if (imagesx($image) !== 200 || imagesy($image) !== 100) {
		throw new RuntimeException('Fallback map did not render');
	}
	imagedestroy($image);
	if ($coverageFile !== null && function_exists('xdebug_get_code_coverage')) {
		file_put_contents($coverageFile, json_encode(xdebug_get_code_coverage(), JSON_THROW_ON_ERROR));
		xdebug_stop_code_coverage();
	}

	print "PASS";
} finally {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}
	rmdir($directory);
}
