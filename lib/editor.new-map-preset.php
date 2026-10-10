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

	if ($file !== false && is_readable($file) && function_exists('imagettfbbox') && function_exists('imagettftext') && is_array(imagettfbbox(9, 0, $file, '1G'))) {
		$number = 100;

		while (isset($map->fonts[$number])) {
			$number++;
		}
		$font                               = new WMFont();
		$font->type                         = 'truetype';
		$font->file                         = 'docs/example/Vera.ttf';
		$font->size                         = 9;
		$map->fonts[$number]                = $font;
		$map->links['DEFAULT']->bwfont      = $number;
		$map->links['DEFAULT']->commentfont = $number;
	}
}
