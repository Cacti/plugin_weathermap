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

/** Browser-only high-traffic glow uses the rendered arrow outlines and colours. */
function wm_traffic_animation_data($map) {
    $regions = [];
    foreach ($map->links as $link) {
        if ($link->width <= 0) continue;
        foreach ([OUT, IN] as $direction) {
            $percent = (float) ($direction === OUT ? $link->outpercent : $link->inpercent);
            if (!is_finite($percent) || $percent < 80 || ($link->linkstyle === 'oneway' && $direction === IN)) continue;
            $colour = $link->colours[$direction] ?? null;
            if (!$colour || !$colour->is_real()) continue;
            $coordinates = $link->traffic_glow_points[$direction] ?? [];
            if (count($coordinates) < 6) continue;
            $points = [];
            for ($i = 0; $i + 1 < count($coordinates); $i += 2) {
                // SVG coordinates describe edges; GD coordinates describe pixel centres.
                $points[] = [$coordinates[$i] + 0.5, $coordinates[$i + 1] + 0.5];
            }
            $regions[] = ['points' => $points, 'colour' => sprintf('#%02x%02x%02x', $colour->r, $colour->g, $colour->b)];
        }
    }
    $occlusions = [];
    foreach ($map->imap->shapes as $shape) {
        if (!preg_match('/^(?:NODE:|LINK:L[0-9]+:[23]$)/', $shape->name)) continue;
        if ($shape instanceof HTML_ImageMap_Area_Polygon) {
            $occlusions[] = $shape->points;
        } elseif ($shape instanceof HTML_ImageMap_Area_Rectangle) {
            $occlusions[] = [[$shape->x1, $shape->y1], [$shape->x2, $shape->y1], [$shape->x2, $shape->y2], [$shape->x1, $shape->y2]];
        }
    }
    return ['enabled' => $map->get_hint('traffic_pulse', '1') === '1', 'width' => $map->width, 'height' => $map->height, 'regions' => $regions, 'occlusions' => $occlusions];
}
