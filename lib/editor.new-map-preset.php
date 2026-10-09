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

/**
 * Apply readable initial defaults only to a newly created blank map.
 *
 * @param WeatherMap $map
 *
 * @return void
 */
function wm_new_map_preset($map) {
	$map->add_hint('background_sizing', 'fit');
	$map->width                               = 1400;
	$map->height                              = 750;
	$map->htmlstyle                           = 'overlib';
	$map->links['DEFAULT']->arrowstyle        = 'compact';
	$map->links['DEFAULT']->commentfontcolour = [0, 0, 255];
	$file                                     = realpath(__DIR__ . '/../docs/example/Vera.ttf');

	if ($file !== false && is_readable($file) && function_exists('imagettfbbox') && is_array(imagettfbbox(9, 0, $file, '1G'))) {
		$number = 100;

		while (isset($map->fonts[$number])) {
			$number++;
		}
		$font                               = new WMFont();
		$font->type                         = 'truetype';
		$font->file                         = $file;
		$font->size                         = 9;
		$map->fonts[$number]                = $font;
		$map->links['DEFAULT']->bwfont      = $number;
		$map->links['DEFAULT']->commentfont = $number;
	}
}
