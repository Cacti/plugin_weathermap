<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
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
		->and($map->fonts[101]->size)->toBe(9)
		->and(is_readable($map->fonts[101]->file))->toBeTrue();
});
