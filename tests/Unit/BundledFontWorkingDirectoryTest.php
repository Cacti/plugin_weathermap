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

final class BundledFontWorkingDirectoryTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testReadsRendersAndRewritesPortableFontsFromCactiRoot(): void {
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);

		if (!function_exists('cacti_count')) {
			function cacti_count($values) {
				return is_countable($values) ? count($values) : 0;
			}
		}
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';
		$file = tempnam(sys_get_temp_dir(), 'wm-font-cwd-');
		$cwd  = getcwd();

		try {
			file_put_contents($file, "FONTDEFINE 100 docs/example/Vera.ttf 9\nLINK DEFAULT\n BWFONT 100\n COMMENTFONT 100\n");
			$map = new WeatherMap();
			chdir(dirname($plugin, 2));
			self::assertTrue($map->ReadConfig($file));
			self::assertArrayHasKey(100, $map->fonts);
			self::assertSame('docs/example/Vera.ttf', $map->fonts[100]->file);
			self::assertGreaterThan(0, $map->myimagestringsize(100, 'Font from CLI')[0]);
			$image  = imagecreatetruecolor(150, 50);
			$colour = imagecolorallocate($image, 255, 255, 255);
			$map->myimagestring($image, 100, 5, 25, 'Font from CLI', $colour);
			self::assertGreaterThan(0, array_sum(array_map(fn ($x) => imagecolorat($image, $x, 20), range(5, 140))));
			imagedestroy($image);
			$map->WriteConfig($file);
			self::assertStringContainsString('FONTDEFINE 100 docs/example/Vera.ttf 9', file_get_contents($file));
			chdir($plugin);
			$again = new WeatherMap();
			chdir(dirname($plugin, 2));
			$again->ReadConfig($file);
			self::assertArrayHasKey(100, $again->fonts);
			self::assertSame('/custom/font.ttf', wm_font_file('/custom/font.ttf'));
			self::assertSame('../custom.ttf', wm_font_file('../custom.ttf'));
		} finally {
			chdir($cwd);
			unlink($file);
		}
	}
}
