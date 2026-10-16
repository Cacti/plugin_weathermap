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
 * Trace row silhouettes of the rendered node, excluding transparent canvas padding.
 *
 * @param WeatherMapNode $node Node with its final icon, label and drawing origin.
 *
 * @return array|null Occlusion polygons, or null when no rendered image is available.
 */
function wm_traffic_node_occlusions($node) {
	$image = $node->image ?? null;
	if (!($image instanceof GdImage) && !(is_resource($image) && get_resource_type($image) === 'gd')) return null;
	$width = imagesx($image);
	$height = imagesy($image);
	$x = (int) ($node->x - $node->centre_x);
	$y = (int) ($node->y - $node->centre_y);
	$transparent = imagecolortransparent($image);
	$polygons = [];
	$runs = [];
	$flush = function() use (&$runs, &$polygons, $x, $y) {
		if (!$runs) return;
		$left = []; $right = [];
		foreach ($runs as $run) {
			$left[] = [$x + $run[0], $y + $run[2]];
			$left[] = [$x + $run[0], $y + $run[3]];
			$right[] = [$x + $run[1], $y + $run[2]];
			$right[] = [$x + $run[1], $y + $run[3]];
		}
		$reverse = array_reverse($left);
		array_pop($reverse);
		$polygons[] = array_merge([$left[0]], $right, $reverse);
		$runs = [];
	};
	$visible = function($column, $row) use ($image, $transparent) {
		$index = imagecolorat($image, $column, $row);
		return $index !== $transparent && imagecolorsforindex($image, $index)['alpha'] < 127;
	};
	for ($row = 0; $row < $height; $row++) {
		$first = 0;
		while ($first < $width && !$visible($first, $row)) $first++;
		if ($first === $width) { $flush(); continue; }
		$last = $width - 1;
		while ($last > $first && !$visible($last, $row)) $last--;
		$previous = cacti_count($runs) - 1;
		if ($previous >= 0 && $runs[$previous][0] === $first && $runs[$previous][1] === $last + 1) {
			$runs[$previous][3] = $row + 1;
		} else {
			$runs[] = [$first, $last + 1, $row, $row + 1];
		}
	}
	$flush();
	return $polygons;
}

/**
 * Build browser glow metadata from rendered link geometry and colours.
 *
 * @param WeatherMap $map The rendered map supplying traffic and occlusion geometry.
 *
 * @return array{enabled: bool, width: int, height: int, regions: array, occlusions: array} Viewer metadata.
 */
function wm_traffic_animation_data($map) {
	$empty = ['enabled' => $map->get_hint('traffic_pulse', '1') === '1', 'width' => $map->width, 'height' => $map->height, 'regions' => [], 'occlusions' => []];
	if (!$empty['enabled']) return $empty;
	$regions = [];
	foreach ($map->links as $link) {
		if ($link->width <= 0) continue;
		foreach ([OUT, IN] as $direction) {
			$percent = (float) ($direction === OUT ? $link->outpercent : $link->inpercent);
			if (!is_finite($percent) || $percent < 80 || ($link->linkstyle === 'oneway' && $direction === IN)) continue;
			$colour = $link->colours[$direction] ?? null;
			if (!$colour || !$colour->is_real()) continue;
			$coordinates = $link->traffic_glow_points[$direction] ?? [];
			$coordinateCount = cacti_count($coordinates);
			if ($coordinateCount < 6) continue;
			$points = [];
			for ($i = 0; $i + 1 < $coordinateCount; $i += 2) {
				// SVG coordinates describe edges; GD coordinates describe pixel centres.
				$points[] = [$coordinates[$i] + 0.5, $coordinates[$i + 1] + 0.5];
			}
			$regions[] = ['points' => $points, 'colour' => sprintf('#%02x%02x%02x', $colour->r, $colour->g, $colour->b)];
		}
	}
	if (!$regions) return $empty;
	$occlusions = [];
	$nodes = [];
	foreach ($map->nodes as $node) $nodes['N' . $node->id] = $node;
	$nodeMasks = [];
	foreach ($map->imap->shapes as $shape) {
		if (preg_match('/^NODE:(N[0-9]+):[0-9]+$/', $shape->name, $match) && isset($nodes[$match[1]])) {
			$id = $match[1];
			if (!array_key_exists($id, $nodeMasks)) {
				$nodeMasks[$id] = wm_traffic_node_occlusions($nodes[$id]);
				if ($nodeMasks[$id] !== null) $occlusions = array_merge($occlusions, $nodeMasks[$id]);
			}
			if ($nodeMasks[$id] !== null) continue;
		}

		if (!preg_match('/^(?:NODE:|LINK:L[0-9]+:[23]$|LEGEND:|TITLE$|(?:MIN|MAX)?TIMESTAMP$)/', $shape->name)) continue;
		if ($shape instanceof HTML_ImageMap_Area_Polygon) {
			$occlusions[] = $shape->points;
		} elseif ($shape instanceof HTML_ImageMap_Area_Rectangle) {
			$occlusions[] = [[$shape->x1, $shape->y1], [$shape->x2, $shape->y1], [$shape->x2, $shape->y2], [$shape->x1, $shape->y2]];
		}
	}
	return ['enabled' => $map->get_hint('traffic_pulse', '1') === '1', 'width' => $map->width, 'height' => $map->height, 'regions' => $regions, 'occlusions' => $occlusions];
}
