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

it('SavesAndReloadsPortableFontAndCommentDefaults', function (): void {
	$plugin = dirname(__DIR__, 2);
	$cwd    = getcwd();
	chdir($plugin);
	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
	require_once $plugin . '/lib/editor.comment-style.php';
	$file = sys_get_temp_dir() . '/wm-style-' . bin2hex(random_bytes(8)) . '.conf';

	try {
		$map = new WeatherMap();
		wm_comment_apply_style($map, 'bundled:Vera.ttf:9', '0 0 255', null, 'center');
		$map->WriteConfig($file);
		$saved = new WeatherMap();
		$saved->ReadConfig($file);
		$font = $saved->fonts[$saved->links['DEFAULT']->commentfont];
		expect($font->file)->toBe('docs/example/Vera.ttf');
		expect($font->size)->toEqual(9);
		expect($saved->links['DEFAULT']->commentfontcolour)->toEqual([0, 0, 255]);
		expect(file_get_contents($file))->toContain('FONTDEFINE');
		expect($saved->links['DEFAULT']->commentstyle)->toBe('center');
		expect(file_get_contents($file))->toContain('COMMENTSTYLE center');
		wm_comment_apply_style($saved, null, null, null, 'edge');
		expect($saved->WriteConfig($file))->toBeTrue();
		$edge = new WeatherMap();
		expect($edge->ReadConfig($file))->toBeTrue();
		expect($edge->links['DEFAULT']->commentstyle)->toBe('edge');

		// Explicit overrides equal to the default must survive save/reload and later default edits.
		file_put_contents($file, "NODE A\n POSITION 10 10\nNODE B\n POSITION 100 100\nLINK DEFAULT\n COMMENTSTYLE edge\n BWLABELPOS 75 25\nLINK ordinary\n NODES A B\nLINK fixed\n NODES A B\nLINK rawcustom\n NODES A B\n COMMENTSTYLE center\n BWLABELPOS 90 10\nLINK named\n COMMENTSTYLE center\n BWLABELPOS 80 20\nLINK templated\n TEMPLATE named\n NODES A B\n");
		$layout = new WeatherMap();
		expect($layout->ReadConfig($file))->toBeTrue();
		wm_link_apply_overrides($layout, $layout->links['fixed'], 'edge', 'custom', '75', '25');
		expect($layout->WriteConfig($file))->toBeTrue();
		$layout = new WeatherMap();
		expect($layout->ReadConfig($file))->toBeTrue();
		expect($layout->links['fixed']->get_hint('editor_commentstyle_override'))->toBe('1');
		expect($layout->links['fixed']->asJS())->toContain('commentstylechoice:' . js_escape('edge'));
		expect($layout->links['fixed']->asJS())->toContain('labelposmode:' . js_escape('custom'));
		wm_comment_apply_style($layout, null, null, null, 'center');
		wm_link_apply_default_positions($layout, '85', '15');
		expect($layout->links['fixed']->commentstyle)->toBe('edge');
		expect($layout->links['fixed']->labeloffset_in)->toEqual(75);
		expect($layout->links['ordinary']->commentstyle)->toBe('center');
		expect($layout->links['ordinary']->labeloffset_in)->toEqual(85);
		expect($layout->links['rawcustom']->labeloffset_in)->toEqual(90);
		expect($layout->links['templated']->labeloffset_in)->toEqual(80);
		$before = serialize($layout->links['fixed']);
		wm_link_apply_overrides($layout, $layout->links['fixed'], 'invalid', 'custom', '-1', '101');
		expect(serialize($layout->links['fixed']))->toBe($before);
		wm_link_apply_default_positions($layout, '10.5', '25');
		expect($layout->links['DEFAULT']->labeloffset_in)->toEqual(85);
		wm_link_apply_overrides($layout, $layout->links['fixed'], 'default', 'default', null, null);
		expect($layout->links['fixed']->commentstyle)->toBe('center');
		expect($layout->links['fixed']->labeloffset_in)->toEqual(85);
		expect($layout->links['fixed']->get_hint('editor_commentstyle_override'))->toBeNull();
		expect($layout->WriteConfig($file))->toBeTrue();
		$restored = new WeatherMap();
		expect($restored->ReadConfig($file))->toBeTrue();
		wm_comment_apply_style($restored, null, null, null, 'edge');
		wm_link_apply_default_positions($restored, '70', '30');
		expect($restored->links['fixed']->commentstyle)->toBe('edge');
		expect($restored->links['fixed']->labeloffset_in)->toEqual(70);
		$html = wm_link_override_fields() . wm_link_position_fields($restored);
		expect($html)->toContain('Use map default');
		expect($html)->toContain('id="mapstyle_labelposin"');
		expect($html)->toContain('id="link_commentstyle"');

	} finally {
		if (file_exists($file)) {
			unlink($file);
		}

		chdir($cwd);
	}
});
