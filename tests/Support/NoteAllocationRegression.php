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

namespace WeathermapNotes {
	/**
	 * Simulate an unavailable note canvas in an isolated rendering process.
	 *
	 * @param int $width Canvas width.
	 * @param int $height Canvas height.
	 * @return \GdImage|false
	 */
	function imagecreatetruecolor($width, $height) {
		$GLOBALS['note_allocations']++;
		if ($GLOBALS['note_allocations'] === ($GLOBALS['note_fail_at'] ?? 0)) return false;
		if (($GLOBALS['note_fail_at'] ?? 0) === -1) throw new \ValueError('allocation unavailable');
		return \imagecreatetruecolor($width, $height);
	}
}
namespace {
	require dirname(__DIR__) . '/bootstrap-unit.php';
	chdir(dirname(__DIR__, 2));
	require 'setup.php'; require 'lib/WeatherMap.class.php';
	$dir = sys_get_temp_dir() . '/wm-note-allocation-' . bin2hex(random_bytes(4)); mkdir($dir);
	try {
		$file = $dir . '/map.conf';
		file_put_contents($file, "WIDTH 400\nHEIGHT 200\nFONTDEFINE 100 docs/example/Vera.ttf 9\nNODE label\n LABEL Readable note\n LABELFONT 100\n LABELBGCOLOR none\n LABELOUTLINECOLOR none\n LABELFONTSHADOWCOLOR none\n POSITION 150 100\n SET editor_note 1\n");
		foreach ([1, 2, 0] as $failure) {
			$GLOBALS['note_allocations'] = 0; $GLOBALS['note_fail_at'] = $failure;
			$map = new \WeatherMap; $map->ReadConfig($file); $map->stamptext = '';
			$output = $dir . '/render-' . $failure . '.png'; $map->DrawMap($output);
			if (getimagesize($output)[0] !== ($failure ? 400 : 800)) throw new \RuntimeException('allocation fallback dimensions');
			$image = imagecreatefrompng($output); $ink = 0; $scale = $failure ? 1 : 2;
			for ($x = 110 * $scale; $x < 190 * $scale; $x++) {
				for ($y = 90 * $scale; $y < 110 * $scale; $y++) if ((imagecolorat($image, $x, $y) & 0xffffff) === 0) $ink++;
			}
			if (!$ink) throw new \RuntimeException('fallback must retain readable note glyphs');
			imagedestroy($image);
		}
		$GLOBALS['note_allocations'] = 0; $GLOBALS['note_fail_at'] = 1;
		if (wm_note_display_image($map, []) !== false) throw new \RuntimeException('direct display allocation failure');
		$GLOBALS['note_fail_at'] = -1;
		if (\WeathermapNotes\allocate(1, 1) !== false) throw new \RuntimeException('allocator exception fallback');
		echo "Note canvas allocation fallback PASS\n";
	} finally {
		foreach (glob($dir . '/*') as $path) unlink($path);
		rmdir($dir);
	}
}
