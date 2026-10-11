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
 * Notes eligible for high-density map rendering. Restrict the allocation to
 * bounded maps and plain TrueType annotations drawn above the other objects.
 *
 * @param WeatherMap $map The rendered map and its object layers.
 * @return WeatherMapNode[] Notes eligible for high-density rendering.
 */
function wm_sharp_notes($map) {
	if ($map->width < 1 || $map->height < 1 || $map->width * $map->height > 4000000) {
		return [];
	}
	$notes = [];
	$lastLayer = 0;
	foreach (array_merge($map->nodes, $map->links) as $item) {
		if ($item->name !== 'DEFAULT') $lastLayer = max($lastLayer, $item->zorder);
	}
	// Later nodes only block annotations they overlap. Their pre-rendered
	// rectangles preserve stacking without degrading unrelated notes.
	foreach ($map->seen_zlayers[$lastLayer] ?? [] as $node) {
		if ($node instanceof WeatherMapNode && $node->name !== 'DEFAULT' && $node->get_hint('editor_note', '0') === '1' && $node->labelangle == 0 && $node->iconfile === '' && $node->zorder < 1000 && $node->labelbgcolour == [-1,-1,-1] && $node->labeloutlinecolour == [-1,-1,-1] && $node->labelfontshadowcolour == [-1,-1,-1] && isset($map->fonts[$node->labelfont]) && $map->fonts[$node->labelfont]->type === 'truetype') {
			$notes[] = $node;
		} elseif ($node instanceof WeatherMapNode && !is_null($node->x)) {
			$notes = array_values(array_filter($notes, function ($note) use ($node) {
				if (!$node->image || !$note->image) return false;
				$x = (int)($node->x - $node->centre_x);
				$y = (int)($node->y - $node->centre_y);
				$nx = (int)($note->x - $note->centre_x);
				$ny = (int)($note->y - $note->centre_y);
				return $x >= $nx + imagesx($note->image) || $nx >= $x + imagesx($node->image)
					|| $y >= $ny + imagesy($note->image) || $ny >= $y + imagesy($node->image);
			}));
		} elseif (!($node instanceof WeatherMapNode) || !is_null($node->x)) {
			$notes = [];
		}
	}
	return $notes;
}

/**
 * Render glyphs at twice the density; do not enlarge an existing text bitmap.
 *
 * @param WeatherMap $map The composed logical-resolution map.
 * @param WeatherMapNode[] $notes Notes to draw over the composed image.
 * @return GdImage The high-density display image.
 */
function wm_note_display_image($map, $notes) {
	$image = imagecreatetruecolor($map->width * 2, $map->height * 2);
	imagecopyresampled($image, $map->image, 0, 0, 0, 0, $map->width * 2, $map->height * 2, $map->width, $map->height);
	foreach ($notes as $node) {
		$lines = explode("\n", $node->proclabel);
		$width = 0;
		foreach ($lines as $line) {
			[$lineWidth] = $map->myimagestringsize($node->labelfont, $line);
			$width = max($width, $lineWidth);
		}
		[, $height] = $map->myimagestringsize($node->labelfont, 'Ag');
		$height = max(1, ceil($height));
		$font = $map->fonts[$node->labelfont];
		$colour = imagecolorallocate($image, ...$node->labelfontcolour);
		$originX = (int)($node->x - $node->centre_x);
		$originY = (int)($node->y - $node->centre_y);
		$lineY = $node->centre_y - count($lines) * $height / 2 + $height;
		foreach ($lines as $line) {
			[$lineWidth] = $map->myimagestringsize($node->labelfont, $line);
			$align = $node->get_hint('editor_note_align', 'left');
			$offset = $align === 'right' ? $width - $lineWidth : ($align === 'centre' ? ($width - $lineWidth) / 2 : 0);
			$x = $originX + round($node->centre_x - $width / 2 + $offset);
			$y = $originY + round($lineY);
			wimagettftext($image, $font->size * 2, 0, $x * 2, $y * 2, $colour, wm_font_file($font->file), $line);
			$lineY += $height;
		}
	}
	return $image;
}

/**
 * Size the live-view bitmap in logical map coordinates, including high-density output.
 *
 * @param WeatherMap $map The map supplying logical display dimensions.
 * @param string|int $id The registered map identifier.
 * @return string Escaped live-view image markup.
 */
function wm_liveview_image_html($map, $id) {
	$url = htmlspecialchars('?action=liveviewimage&id=' . rawurlencode((string)$id), ENT_QUOTES, 'UTF-8');
	return '<img src="' . $url . '" width="' . (int)$map->width . '" height="' . (int)$map->height . '" />' . "\n";
}
