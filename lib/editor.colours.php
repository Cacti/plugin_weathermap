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
 * Validate a colour string and convert it to RGB; malformed or non-string input is rejected.
 *
 * @param mixed $value A hexadecimal colour or the special value none.
 *
 * @return int[] The RGB components, or -1 components for none.
 */
function wm_asset_rgb($value) {
	if ($value === 'none') return [-1, -1, -1];
	if (!is_string($value) || !preg_match('/^#[0-9a-f]{6}$/iD', $value)) throw new InvalidArgumentException(__('Choose a valid colour.', 'weathermap'));
	return [hexdec(substr($value, 1, 2)), hexdec(substr($value, 3, 2)), hexdec(substr($value, 5, 2))];
}
/**
 * Convert RGB components to a hexadecimal colour or none.
 *
 * @param int[] $rgb RGB components, with a negative red component representing none.
 *
 * @return string The colour accepted by the editor.
 */
function wm_asset_hex($rgb) {
	return $rgb[0] < 0 ? 'none' : sprintf('#%02x%02x%02x', ...$rgb);
}
/**
 * Read the editable background, outline, pulse and scale settings.
 *
 * @param WeatherMap $map The map to inspect.
 *
 * @return array The editable settings and scale rows.
 */
function wm_asset_colour_data($map) {
	$rows = [];
	foreach ($map->colours as $scale => $colours) foreach ($colours as $row) {
		if (!empty($row['special'])) continue;
		$rows[] = ['scale' => $scale, 'bottom' => $row['bottom'], 'top' => $row['top'],
			'colour' => wm_asset_hex([$row['red1'], $row['green1'], $row['blue1']]),
			'end' => isset($row['red2']) ? wm_asset_hex([$row['red2'], $row['green2'], $row['blue2']]) : '', 'tag' => $row['tag'] ?? ''];
	}
	$bg = $map->colours['DEFAULT']['BG'];
	return ['background' => wm_asset_hex([$bg['red1'], $bg['green1'], $bg['blue1']]),
		'outline' => wm_asset_hex($map->links['DEFAULT']->outlinecolour), 'pulse' => $map->get_hint('traffic_pulse', '1') === '1', 'rows' => $rows];
}
/**
 * Validate all submitted settings before applying them to the map.
 *
 * @param WeatherMap $map The map receiving the validated settings.
 * @param array $data The submitted settings; malformed values are rejected.
 *
 * @return void Invalid settings raise InvalidArgumentException.
 */
function wm_asset_save_colours($map, $data) {
	if (array_key_exists('pulse', $data) && !is_bool($data['pulse'])) throw new InvalidArgumentException(__('Invalid pulse setting.', 'weathermap'));
	$background = array_key_exists('background', $data) ? wm_asset_rgb($data['background']) : [$map->colours['DEFAULT']['BG']['red1'], $map->colours['DEFAULT']['BG']['green1'], $map->colours['DEFAULT']['BG']['blue1']];
	$outline = wm_asset_rgb($data['outline'] ?? null);
	if ($background[0] < 0) throw new InvalidArgumentException(__('The background needs a solid colour.', 'weathermap'));
	$rows = $data['rows'] ?? null;
	if (!is_array($rows) || cacti_count($rows) < 1 || cacti_count($rows) > 200) throw new InvalidArgumentException(__('Keep between 1 and 200 scale rows.', 'weathermap'));
	$scales = [];
	foreach ($rows as $row) {
		$name = $row['scale'] ?? '';
		if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name)) throw new InvalidArgumentException(__('Invalid scale name.', 'weathermap'));
		foreach (['bottom', 'top'] as $key) if (!isset($row[$key]) || !is_numeric($row[$key]) || !is_finite((float)$row[$key]) || abs((float)$row[$key]) > 1e15) throw new InvalidArgumentException(__('Invalid scale range.', 'weathermap'));
		if ($row['bottom'] > $row['top']) throw new InvalidArgumentException(__('Range start must be no greater than its end.', 'weathermap'));
		$rgb = wm_asset_rgb($row['colour'] ?? null);
		$tag = $row['tag'] ?? '';
		if (!is_string($tag) || strlen($tag) > 200 || preg_match('/[\x00-\x1f\x7f]/', $tag)) throw new InvalidArgumentException(__('Invalid scale label.', 'weathermap'));
		$bottom = round((float)$row['bottom'], 6);
		$top = round((float)$row['top'], 6);
		$serialize = function($value) use ($map) {
			$decimal = localeconv()['decimal_point'];
			$number = rtrim(rtrim(sprintf('%f', $value == 0 ? 0 : $value), '0'), $decimal);
			return $number > 1000 ? nice_bandwidth($value, $map->kilo) : $number;
		};
		$key = $serialize($bottom) . '_' . $serialize($top);
		if (isset($scales[$name][$key])) throw new InvalidArgumentException(__('Duplicate range in a scale.', 'weathermap'));
		$entry = ['key' => $key, 'bottom' => $bottom, 'top' => $top, 'special' => 0, 'red1' => $rgb[0], 'green1' => $rgb[1], 'blue1' => $rgb[2], 'tag' => $tag];
		if (array_key_exists('end', $row) && $row['end'] !== '') {
			$end = wm_asset_rgb($row['end']);
			if ($rgb[0] < 0 || $end[0] < 0) throw new InvalidArgumentException(__('Gradients need two colours.', 'weathermap'));
			$entry += ['red2' => $end[0], 'green2' => $end[1], 'blue2' => $end[2]];
		}
		$scales[$name][$key] = $entry;
	}
	foreach ($map->colours as $name => $colours) foreach ($colours as $key => $row) if (!empty($row['special'])) $scales[$name][$key] = $row;
	// Keep scales referenced by map objects rather than silently replacing them with defaults.
	foreach (array_merge(array_values($map->nodes), array_values($map->links)) as $object) {
		foreach (['usescale', 'useiconscale'] as $field) {
			$name = $object->$field ?? 'none';
			if ($name !== 'none' && isset($map->colours[$name]) && empty(array_filter($scales[$name] ?? [], function($r) { return empty($r['special']); }))) throw new InvalidArgumentException(__('A map object still uses scale %s.', $name, 'weathermap'));
		}
	}
	$scales['DEFAULT']['BG']['red1'] = $background[0];
	$scales['DEFAULT']['BG']['green1'] = $background[1];
	$scales['DEFAULT']['BG']['blue1'] = $background[2];
	$map->colours = $scales;
	foreach ($scales as $name => $colours) $map->numscales[$name] = cacti_count(array_filter($colours, function($r) { return empty($r['special']); }));
	wm_comment_update_default($map, 'outlinecolour', $outline);
	if (array_key_exists('pulse', $data)) $map->add_hint('traffic_pulse', $data['pulse'] ? '1' : '0');
}
