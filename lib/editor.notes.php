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
 * Determine logical dimensions after background sizing and post-processing.
 * @param WeatherMap $map Loaded editor snapshot.
 * @return int[] Effective width and height without changing the saved configuration.
 */
function wm_note_effective_dimensions($map) {
	$preview = clone $map;
	$preview->image = null;
	$preview->imap = new HTML_ImageMap('weathermap');
	foreach ($preview->nodes as $name => $node) {
		$preview->nodes[$name] = clone $node;
		$preview->nodes[$name]->owner = $preview;
		$preview->nodes[$name]->image = null;
	}
	foreach ($preview->links as $name => $link) {
		$preview->links[$name] = clone $link;
		$preview->links[$name]->owner = $preview;
		foreach (['a', 'b'] as $end) if ($link->$end instanceof WeatherMapNode) $preview->links[$name]->$end = $preview->nodes[$link->$end->name];
	}
	foreach ($preview->seen_zlayers as $layer => $items) {
		foreach ($items as $key => $item) {
			if ($item instanceof WeatherMapNode) $preview->seen_zlayers[$layer][$key] = $preview->nodes[$item->name];
			elseif ($item instanceof WeatherMapLink) $preview->seen_zlayers[$layer][$key] = $preview->links[$item->name];
		}
	}
	foreach ($preview->plugins['post'] as $name => $plugin) $preview->plugins['post'][$name] = clone $plugin;
	try {
		if (function_exists('fixMapBackgroundAndImages')) fixMapBackgroundAndImages($preview);
		$preview->DrawMap('null');
		return [(int)$preview->width, (int)$preview->height];
	} finally {
		$preview->CleanUp();
	}
}

/**
 * Create or update a label-only note using existing map node directives.
 *
 * @param WeatherMap $map Map being edited.
 * @param array<string, mixed> $values Submitted note fields.
 * @param int $grid_snap_value Placement grid spacing.
 *
 * @return bool Whether the note was changed.
 */
function wm_editor_save_note($map, $values, $grid_snap_value = 0) {
	$name = $values['name'] ?? '';
	$text = $values['text'] ?? '';
	$colour = $values['colour'] ?? '';
	$alignment = $values['align'] ?? 'left';
	if (!in_array($alignment, ['left', 'centre', 'right'], true)) {
		return false;
	}
	if (!is_string($name) || !is_string($text) || trim($text) === '' || strlen($text) > 2000
		|| preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text)
		|| !is_string($colour) || !preg_match('/^#[0-9a-f]{6}$/iD', $colour)) {
		return false;
	}
	foreach (['x', 'y'] as $axis) {
		if (!isset($values[$axis]) || !is_scalar($values[$axis]) || !preg_match('/^\d{1,6}$/D', (string)$values[$axis])) {
			return false;
		}
	}
	[$width, $height] = wm_note_effective_dimensions($map);
	$x = (int)$values['x'];
	$y = (int)$values['y'];
	if ($x > $width || $y > $height) {
		return false;
	}
	if ($name !== '' && (!isset($map->nodes[$name]) || $map->nodes[$name]->get_hint('editor_note', '0') !== '1')) {
		return false;
	}
	$selection = $values['font'] ?? null;
	$font = (is_string($selection) || is_int($selection)) && preg_match('/^\d+$/D', (string)$selection)
		? wm_note_font_id($map, (string)$selection) : wm_comment_resolve_font($map, $selection);
	if ($font === null) {
		return false;
	}
	$font = wm_note_font_id($map, $font);
	if ($name === '') {
		do {
			$name = 'note_' . bin2hex(random_bytes(6));
		} while (isset($map->nodes[$name]));
		$node = new WeatherMapNode;
		$node->name = $name;
		$node->template = 'DEFAULT';
		$node->Reset($map);
		$node->defined_in = $map->configfile;
		$node->iconfile = '';
		$node->targets = [];
		$node->infourl = [IN => '', OUT => ''];
		$node->overliburl = [IN => [], OUT => []];
		$node->notestext = [IN => '', OUT => ''];
		$node->labelbgcolour = [-1, -1, -1];
		$node->labeloutlinecolour = [-1, -1, -1];
		$node->labelfontshadowcolour = [-1, -1, -1];
		$node->labeloffset = '';
		$node->labeloffsetx = 0;
		$node->labeloffsety = 0;
		$node->labelangle = 0;
		$node->relative_to = '';
		$node->add_hint('editor_note', '1');
		$map->nodes[$name] = $node;
		$map->seen_zlayers[$node->zorder][] = $node;
	}
	$node = $map->nodes[$name];
	$node->label = str_replace(["\r\n", "\r", "\n"], '\n', str_replace('\\', '\\\\', trim($text)));
	$node->add_hint('editor_note_escaped', '1');
	$node->labelfont = $font;
	$node->add_hint('editor_note_align', $alignment);
	$node->labelfontcolour = [hexdec(substr($colour, 1, 2)), hexdec(substr($colour, 3, 2)), hexdec(substr($colour, 5, 2))];
	$node->x = min($width, snap($x, $grid_snap_value));
	$node->y = min($height, snap($y, $grid_snap_value));
	return true;
}
/**
 * Expose note style data alongside the editor's existing node data.
 *
 * @param WeatherMap $map Map being edited.
 *
 * @return array<string, array<string, mixed>> Notes indexed by node name.
 */
function wm_editor_note_data($map) {
	$notes = [];
	foreach ($map->nodes as $name => $node) {
		if ($node->get_hint('editor_note', '0') !== '1' || $name === 'DEFAULT') {
			continue;
		}
		$fontId = wm_note_font_id($map, $node->labelfont);
		$font = $map->fonts[$fontId];
		$rgb = $node->labelfontcolour;
		$notes[$name] = ['text' => $node->get_hint('editor_note_escaped', '0') === '1' ? wm_note_decode_text($node->label) : str_replace('\\n', "\n", $node->label), 'align' => $node->get_hint('editor_note_align', 'left'), 'font' => (string)$fontId, 'fontLabel' => wm_editor_font_label($fontId, $font),
			'colour' => sprintf('#%02x%02x%02x', max(0, $rgb[0]), max(0, $rgb[1]), max(0, $rgb[2]))];
	}
	return $notes;
}
