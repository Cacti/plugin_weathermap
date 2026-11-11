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

beforeAll(function (): void {
	$plugin = dirname(__DIR__, 2);
	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
});

/**
 * Build an engine instance from the plugin directory, which it scans for plugins.
 *
 * @return WeatherMap
 */
function wm_hardening_new_map(): WeatherMap {
	$cwd = getcwd();
	chdir(dirname(__DIR__, 2));

	try {
		return new WeatherMap();
	} finally {
		chdir($cwd);
	}
}

it('ProcessStringAppendsCSPRNGGraphCacheBuster', function (): void {
	$map = wm_hardening_new_map();
	$out = $map->ProcessString('graph_image.php?local_graph_id=1', $map);

	expect($out)->toMatch('/[&?]random=\d+/');
});

it('RandomDataFillsLinkBandwidthFromCSPRNG', function (): void {
	$map                     = wm_hardening_new_map();
	$link                    = new WeatherMapLink();
	$link->name              = 'L1';
	$link->max_bandwidth_in  = 100;
	$link->max_bandwidth_out = 50;
	$map->links['L1']        = $link;
	$map->RandomData();

	expect($map->links['L1']->bandwidth_in)->toBeGreaterThanOrEqual(0);
	expect($map->links['L1']->bandwidth_in)->toBeLessThanOrEqual(100);
	expect($map->links['L1']->bandwidth_out)->toBeGreaterThanOrEqual(0);
	expect($map->links['L1']->bandwidth_out)->toBeLessThanOrEqual(50);
});
