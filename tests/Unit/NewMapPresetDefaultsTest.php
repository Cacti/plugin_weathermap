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

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';
	require_once dirname(__DIR__, 2) . '/lib/editor.new-map-preset.php';
});

it('UsesReadableLandscapeDefaultsAndAllocatesAnUnusedFontId', function () {
	$map = new class {
		public $width;
		public $height;
		public $htmlstyle;
		public $links;
		public $fonts = [100 => null];
		public $hints = [];
		public function add_hint($name, $value) {
			$this->hints[$name] = $value;
		}
	};
	$map->links      = ['DEFAULT' => (object) ['arrowstyle' => 'classic', 'commentfontcolour' => [0, 0, 0], 'bwfont' => 2, 'commentfont' => 1]];
	$map->fonts[100] = new WMFont();
	wm_new_map_preset($map);
	expect([$map->width, $map->height])->toBe([1400, 750])
		->and($map->htmlstyle)->toBe('overlib')
		->and($map->hints['background_sizing'])->toBe('fit')
		->and($map->links['DEFAULT']->arrowstyle)->toBe('compact')
		->and($map->links['DEFAULT']->commentfontcolour)->toBe([0, 0, 255])
		->and($map->links['DEFAULT']->bwfont)->toBe(101)
		->and($map->links['DEFAULT']->commentfont)->toBe(101)
		->and($map->fonts[101]->file)->toBe('docs/example/Vera.ttf')
		->and($map->fonts[101]->size)->toBe(9)
		->and(is_readable($map->fonts[101]->file))->toBeTrue();
});
