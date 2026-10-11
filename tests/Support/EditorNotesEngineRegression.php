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
$plugin = dirname(__DIR__, 2);
chdir($plugin);
require $plugin . '/setup.php';
require $plugin . '/lib/WeatherMap.class.php';
require $plugin . '/lib/editor.inc.php';
require $plugin . '/lib/editor.actions.php';
function note_assert($value, $message) {
	if (!$value) throw new RuntimeException($message);
}
$directory = sys_get_temp_dir() . '/wm-note-' . bin2hex(random_bytes(6));
mkdir($directory);
try {
	$map = new WeatherMap;
	$map->width = 600;
	$map->height = 300;
	$file = $directory . '/note.conf';
	$map->WriteConfig($file);
	$map->ReadConfig($file);
	$values = ['name' => '', 'text' => "Main site\n10.0.0.0/24", 'font' => 'bundled:Vera.ttf:11', 'colour' => '#0000ff', 'x' => '155', 'y' => '80'];
	note_assert(wm_editor_save_note($map, $values), 'create');
	$name = array_keys(wm_editor_note_data($map))[0];
	$map->WriteConfig($file);
	$saved = new WeatherMap;
	$saved->ReadConfig($file);
	$node = $saved->nodes[$name];
	note_assert($node->label === 'Main site\\n10.0.0.0/24', 'multiline config roundtrip');
	note_assert($node->get_hint('editor_note', '0') === '1', 'marker');
	note_assert($node->labelfontcolour == [0,0,255] && $node->iconfile === '', 'style');
	note_assert($node->labelbgcolour == [-1,-1,-1] && $node->labeloutlinecolour == [-1,-1,-1], 'plain text');
	$saved->DrawMap($directory . '/note.png');
	note_assert(is_file($directory . '/note.png'), 'render');

	$size = getimagesize($directory . '/note.png');
	note_assert($size[0] === 1200 && $size[1] === 600, 'generated viewer image double density');
	note_assert(!$node->render_note_hidpi, 'render flag reset');
	$saved->DrawMap($directory . '/note.png', $directory . '/thumb.png', 250);
	$thumbSize = getimagesize($directory . '/thumb.png');
	note_assert($thumbSize[0] === 250 && $thumbSize[1] === 125, 'thumbnail logical aspect ratio');
	$sharpBounds = $node->boundingboxes;
	$saved->DrawMap('null');
	note_assert(end($node->boundingboxes) == end($sharpBounds), 'clickable note bounds remain logical');
	note_assert(imagesx($saved->image) === 600, 'HTML-only rendering keeps logical dimensions');
	$saved->imageuri = 'note.png';
	note_assert(str_contains($saved->MakeHTML(), "width='600' height='300'"), 'viewer display size remains logical');
	$oldWidth = $saved->width;
	$saved->width = 50000;
	note_assert(wm_sharp_notes($saved) === [], 'large map allocation bounded');
	$saved->width = $oldWidth;
	$node->zorder = 1001;
	note_assert(wm_sharp_notes($saved) === [], 'non-front annotation keeps original layering');
	$node->zorder = 600;


	$later = clone $node;
	$later->name = 'later_node';
	$later->label = 'Ordinary node';
	$later->add_hint('editor_note', '0');
	$later->x = 500;
	$later->y = 250;
	$saved->nodes[$later->name] = $later;
	$saved->seen_zlayers[600][] = $later;
	$saved->DrawMap($directory . '/later.png');
	note_assert(getimagesize($directory . '/later.png')[0] === 1200, 'unrelated later node keeps note sharp');
	$later->x = $node->x;
	$later->y = $node->y;
	$saved->DrawMap($directory . '/overlap.png');
	note_assert(getimagesize($directory . '/overlap.png')[0] === 600, 'overlapping later node preserves stacking');
	array_pop($saved->seen_zlayers[600]);
	unset($saved->nodes[$later->name]);

	$hashes = [];
	foreach (['left', 'centre', 'right'] as $alignment) {
		$aligned = $values;
		$aligned['name'] = $name;
		$aligned['align'] = $alignment;
		note_assert(wm_editor_save_note($saved, $aligned), 'save ' . $alignment);
		$saved->WriteConfig($file);
		$rendered = new WeatherMap;
		$rendered->ReadConfig($file);
		note_assert(wm_editor_note_data($rendered)[$name]['align'] === $alignment, 'alignment roundtrip');
		$png = $directory . '/' . $alignment . '.png';
		$rendered->DrawMap($png);
		$hashes[] = hash_file('sha256', $png);
	}
	note_assert(count(array_unique($hashes)) === 3, 'alignments change rendered pixels');
	$values['name'] = $name;
	$values['text'] = 'Edited note';
	$values['colour'] = '#c00000';
	$values['x'] = '205';
	note_assert(wm_editor_save_note($saved, $values), 'edit');
	$saved->WriteConfig($file);
	$again = new WeatherMap;
	$again->ReadConfig($file);
	note_assert($again->nodes[$name]->label === 'Edited note' && $again->nodes[$name]->x == 205, 'edit persists');
	foreach (['text' => "bad\nNODE injected", 'name' => 'DEFAULT', 'font' => '../../bad.ttf', 'colour' => '#nope', 'x' => '-1', 'y' => '301', 'align' => 'invalid'] as $key => $value) {
		$bad = $values; $bad[$key] = $value;
		if ($key === 'text') {
			note_assert(wm_editor_save_note($again, $bad), 'newline stays text');
			$again->WriteConfig($file);
			note_assert(strpos(file_get_contents($file), "\nNODE injected") === false, 'no config injection');
		} else note_assert(!wm_editor_save_note($again, $bad), 'reject ' . $key);
	}
	$GLOBALS['__test_nfilter_request'] = ['param' => $name];
	deleteNode($file);
	$deleted = new WeatherMap;
	$deleted->ReadConfig($file);
	note_assert(!isset($deleted->nodes[$name]), 'delete persists');
	print "PASS: note create, render, edit, styles, multiline, validation and delete\n";
} finally {
	foreach (glob($directory . '/*') as $file) unlink($file);
	rmdir($directory);
}
