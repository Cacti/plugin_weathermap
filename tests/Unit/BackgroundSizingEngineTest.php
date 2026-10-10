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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class BackgroundSizingEngineTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testPersistsAndRendersBackgroundSizing(): void {
		ob_start();

		try {
			require dirname(__DIR__) . '/Support/BackgroundSizingEngineRegression.php';
			$result = ob_get_contents();
		} finally {
			ob_end_clean();
		}
		self::assertSame("PASS\n", $result);
	}
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testThumbnailMaximumNeverEnlargesSourceMaps(): void {
		require dirname(__DIR__) . '/bootstrap-unit.php';
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require $plugin . '/lib/WeatherMap.class.php';
		$thumbnail = tempnam(sys_get_temp_dir(), 'wm-thumb-');
		ob_start();
		try {
			foreach ([[400, 200, 400, 200], [200, 400, 200, 400], [1400, 700, 1000, 500], [700, 1400, 500, 1000]] as [$width, $height, $expected_width, $expected_height]) {
				$map = new WeatherMap();
				$map->width = $width;
				$map->height = $height;
				$map->DrawMap('', $thumbnail, 1000);
				$image = imagecreatefrompng($thumbnail);
				self::assertSame([$expected_width, $expected_height], [imagesx($image), imagesy($image)]);
				self::assertSame([$expected_width, $expected_height], [$map->thumb_width, $map->thumb_height]);
				imagedestroy($image);
			}
		} finally {
			ob_end_clean();
			unlink($thumbnail);
		}
	}

}
