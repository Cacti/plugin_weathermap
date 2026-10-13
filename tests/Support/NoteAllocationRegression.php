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
		require_once 'lib/editor.inc.php'; require_once 'lib/editor.notes.php';
		foreach (['missing', 'invalid', 'unreadable'] as $fontCase) {
			$GLOBALS['note_allocations'] = 0; $GLOBALS['note_fail_at'] = 0;
			$map = new \WeatherMap; $map->ReadConfig($file); $map->stamptext = '';
			$fontPath = $dir . '/font-' . $fontCase . '.ttf';
			if ($fontCase === 'invalid') file_put_contents($fontPath, 'invalid font');
			if ($fontCase === 'unreadable') { copy('docs/example/Vera.ttf', $fontPath); chmod($fontPath, 0000); if (is_readable($fontPath)) { chmod($fontPath, 0600); continue; } }
			$map->fonts[100]->file = $fontPath;
			$output = $dir . '/fallback-' . $fontCase . '.png'; $map->DrawMap($output);
			if ($map->nodes['label']->labelfont !== 3 || getimagesize($output)[0] !== 400) throw new \RuntimeException('loaded invalid font must fall back before drawing');
			$image = imagecreatefrompng($output); $ink = 0;
			for ($x = 95; $x < 205; $x++) for ($y = 85; $y < 115; $y++) if ((imagecolorat($image, $x, $y) & 0xffffff) === 0) $ink++;
			if (!$ink) throw new \RuntimeException('invalid loaded font must retain readable glyphs'); imagedestroy($image);
			$values = ['name'=>'label','text'=>'Saved fallback','font'=>'100','colour'=>'#000000','x'=>150,'y'=>100,'align'=>'left'];
			if (!wm_editor_save_note($map,$values,0) || $map->nodes['label']->labelfont !== 3) throw new \RuntimeException('submitted invalid registered font must fall back');
			if ($fontCase === 'unreadable') chmod($fontPath,0600);
		}
		$values['font'] = '99999';
		if (!wm_editor_save_note($map, $values, 0) || $map->nodes['label']->labelfont !== 3) throw new \RuntimeException('unavailable submitted numeric font falls back');
		$values['font'] = '../bad.ttf';
		if (wm_editor_save_note($map, $values, 0)) throw new \RuntimeException('path-like font still rejected');
		if (wm_note_font_id($map,99999)!==3) throw new \RuntimeException('unknown font fallback');
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
