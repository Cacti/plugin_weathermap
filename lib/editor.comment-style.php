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
 * Return the font families bundled with the plugin.
 *
 * @return array<string, string>
 */
function wm_comment_font_names() {
	return [
		'Vera.ttf'     => 'Vera Sans', 'VeraBd.ttf' => 'Vera Sans Bold',
		'VeraIt.ttf'   => 'Vera Sans Italic', 'VeraBI.ttf' => 'Vera Sans Bold Italic',
		'VeraMono.ttf' => 'Vera Sans Mono', 'VeraMoBd.ttf' => 'Vera Sans Mono Bold',
		'VeraMoIt.ttf' => 'Vera Sans Mono Italic', 'VeraMoBI.ttf' => 'Vera Sans Mono Bold Italic',
		'VeraSe.ttf'   => 'Vera Serif', 'VeraSeBd.ttf' => 'Vera Serif Bold',
	];
}
/**
 * Describe a registered font for the editor.
 *
 * @param int    $number
 * @param WMFont $font
 *
 * @return string
 */
function wm_editor_font_label($number, $font) {
	if ($font->type === 'truetype') {
		$names = wm_comment_font_names();
		$file  = basename($font->file);

		return ($names[$file] ?? $file) . ' — ' . $font->size . ' pt (font ' . $number . ')';
	}

	return $number . ' (' . $font->type . ')';
}
/**
 * Return the predefined link comment colours.
 *
 * @return array<string, string>
 */
function wm_comment_colour_choices() {
	return [
		'0 0 255'  => __('Blue (#0000FF)', 'weathermap'), '0 51 102' => __('Dark blue (#003366)', 'weathermap'),
		'0 64 192' => __('Royal blue (#0040C0)', 'weathermap'), '0 112 112' => __('Teal (#007070)', 'weathermap'),
		'0 128 0'  => __('Green (#008000)', 'weathermap'), '128 0 192' => __('Purple (#8000C0)', 'weathermap'),
		'192 0 0'  => __('Red (#C00000)', 'weathermap'), '51 51 51' => __('Charcoal (#333333)', 'weathermap'),
		'0 0 0'    => __('Black (#000000)', 'weathermap'), '255 255 255' => __('White (#FFFFFF)', 'weathermap'),
		'contrast' => __('Automatic contrast with link colour', 'weathermap'),
	];
}
/**
 * Render registered and bundled font choices without duplicates.
 *
 * @param WeatherMap $map
 * @param int        $current
 *
 * @return string
 */
function wm_style_font_options($map, $current) {
	$out     = '';
	$bundled = [];
	$matches = [];

	if (function_exists('imagettfbbox')) {
		foreach (wm_comment_font_names() as $file => $name) {
			$path = __DIR__ . '/../docs/example/' . $file;

			if (!is_readable($path)) {
				continue;
			}
			$hash = sha1_file($path);

			foreach ([9, 11, 13] as $size) {
				$key           = $hash . ':' . $size;
				$bundled[$key] = ['value' => 'bundled:' . $file . ':' . $size, 'label' => $name . ' — ' . $size . ' pt'];
			}
		}
	}

	foreach ($map->fonts as $number => $font) {
		$key = $font->type === 'truetype' && is_readable(wm_font_file($font->file)) ? sha1_file(wm_font_file($font->file)) . ':' . $font->size : '';

		if (isset($bundled[$key])) {
			if (!isset($matches[$key]) || $current == $number) {
				$matches[$key] = $number;
			}

			continue;
		}
		$out .= '<option value="' . (int)$number . '"' . ($current == $number ? ' selected' : '') . '>' . html_escape(wm_editor_font_label($number, $font)) . '</option>';
	}

	if ($bundled) {
		$out .= '<optgroup label="' . html_escape(__('Bundled fonts', 'weathermap')) . '">';

		foreach ($bundled as $key => $choice) {
			$value    = $matches[$key] ?? $choice['value'];
			$selected = isset($matches[$key]) && $current == $matches[$key];
			$out .= '<option value="' . html_escape((string)$value) . '"' . ($selected ? ' selected' : '') . '>' . html_escape($choice['label']) . '</option>';
		}
		$out .= '</optgroup>';
	}

	return $out;
}
/**
 * Render a font selector for map style defaults.
 *
 * @param WeatherMap $map
 * @param string     $name
 * @param int        $current
 *
 * @return string
 */
function wm_style_font_select($map, $name, $current) {
	return '<select class="fontcombo" name="' . html_escape($name) . '" id="' . html_escape($name) . '">' . wm_style_font_options($map, $current) . '</select>';
}
/**
 * Render the link comment font and colour controls.
 *
 * @param WeatherMap $map
 *
 * @return string
 */
function wm_comment_style_fields($map) {
	$current = $map->links['DEFAULT']->commentfont;
	$out     = '<tr><td>' . __('Link Comment Font', 'weathermap') . '</td><td><select class="fontcombo" name="mapstyle_commentfont" id="mapstyle_commentfont">';
	$out .= wm_style_font_options($map, $current);
	$out .= '</select></td></tr>';
	$rgb           = $map->links['DEFAULT']->commentfontcolour;
	$currentColour = $rgb == [-3, -3, -3] ? 'contrast' : implode(' ', $rgb);
	$colours       = wm_comment_colour_choices();

	if (!isset($colours[$currentColour])) {
		$colours[$currentColour] = __('Current custom colour (%s)', $currentColour, 'weathermap');
	}
	$out .= '<tr><td>' . __('Link Comment Colour', 'weathermap') . '</td><td><select name="mapstyle_commentcolour" id="mapstyle_commentcolour">';

	foreach ($colours as $value => $label) {
		$out .= '<option value="' . html_escape($value) . '"' . ($value === $currentColour ? ' selected' : '') . '>' . html_escape($label) . '</option>';
	}
	$out .= '</select></td></tr>';

	return $out;
}
/**
 * Validate a font selection and register a bundled font when needed.
 *
 * @param WeatherMap $map
 * @param mixed      $value
 *
 * @return int|null
 */
function wm_comment_resolve_font($map, $value) {
	if (!is_string($value)) {
		return null;
	}

	if (ctype_digit($value) && isset($map->fonts[(int)$value])) {
		return (int)$value;
	}

	if (!preg_match('/^bundled:([A-Za-z0-9]+\.ttf):(9|11|13)$/D', $value, $m) || !isset(wm_comment_font_names()[$m[1]]) || !function_exists('imagettfbbox')) {
		return null;
	}
	$file = realpath(__DIR__ . '/../docs/example/' . $m[1]);

	if ($file === false || !is_readable($file)) {
		return null;
	}
	$bounds = imagettfbbox((int)$m[2], 0, $file, '1G');

	if (!is_array($bounds)) {
		return null;
	}

	foreach ($map->fonts as $number => $font) {
		if ($font->type === 'truetype' && realpath(wm_font_file($font->file)) === $file && (int)$font->size === (int)$m[2]) {
			return (int)$number;
		}
	}
	$number = 100;

	while (isset($map->fonts[$number])) {
		$number++;
	}
	$font                = new WMFont();
	$font->type          = 'truetype';
	$font->file          = 'docs/example/' . $m[1];
	$font->size          = (int)$m[2];
	$map->fonts[$number] = $font;

	return $number;
}
/**
 * Validate an RGB colour or the contrast sentinel.
 *
 * @param mixed $value
 *
 * @return array<int, int>|null
 */
function wm_comment_resolve_colour($value) {
	if ($value === 'contrast') {
		return [-3, -3, -3];
	}

	if (!is_string($value) || !preg_match('/^(\d{1,3}) (\d{1,3}) (\d{1,3})$/D', $value, $m)) {
		return null;
	}
	$rgb = [(int)$m[1], (int)$m[2], (int)$m[3]];

	return max($rgb) <= 255 ? $rgb : null;
}
/**
 * Update links matching the previous default while preserving different styles.
 *
 * @param WeatherMap $map
 * @param string     $field
 * @param mixed      $new
 *
 * @return void
 */
function wm_comment_update_default($map, $field, $new) {
	if ($new === null) {
		return;
	}
	$old = $map->links['DEFAULT']->$field;

	if ($new == $old) {
		return;
	}

	foreach ($map->links as $name => $link) {
		if ($name === ':: DEFAULT ::') {
			continue;
		}

		if ($old == $link->$field) {
			$link->$field = $new;
		}
	}
}
/**
 * Apply validated link comment style defaults.
 *
 * @param WeatherMap $map
 * @param mixed      $font
 * @param mixed      $colour
 *
 * @return void
 */
function wm_comment_apply_style($map, $font, $colour) {
	wm_comment_update_default($map, 'commentfont', wm_comment_resolve_font($map, $font));
	wm_comment_update_default($map, 'commentfontcolour', wm_comment_resolve_colour($colour));
}
