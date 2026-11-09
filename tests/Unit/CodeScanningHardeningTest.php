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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CodeScanningHardeningTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testProcessStringAppendsCSPRNGGraphCacheBuster(): void {
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';

		$map = new WeatherMap();
		$out = $map->ProcessString('graph_image.php?local_graph_id=1', $map);

		self::assertMatchesRegularExpression('/[&?]random=\d+/', $out);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRandomDataFillsLinkBandwidthFromCSPRNG(): void {
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';

		$map                       = new WeatherMap();
		$link                      = new WeatherMapLink();
		$link->name                = 'L1';
		$link->max_bandwidth_in    = 100;
		$link->max_bandwidth_out   = 50;
		$map->links['L1']          = $link;
		$map->RandomData();

		self::assertGreaterThanOrEqual(0, $map->links['L1']->bandwidth_in);
		self::assertLessThanOrEqual(100, $map->links['L1']->bandwidth_in);
		self::assertGreaterThanOrEqual(0, $map->links['L1']->bandwidth_out);
		self::assertLessThanOrEqual(50, $map->links['L1']->bandwidth_out);
	}
}
