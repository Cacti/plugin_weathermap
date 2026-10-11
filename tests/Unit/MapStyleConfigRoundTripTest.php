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

final class MapStyleConfigRoundTripTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testSavesAndReloadsPortableFontAndCommentDefaults(): void {
		if (!function_exists('cacti_count')) {
			/**
			 * Count values using the standalone fixture's Cacti-compatible helper.
			 *
			 * @param  mixed $values
			 * @return int
			 */
			function cacti_count($values) {
				return is_countable($values) ? count($values) : 0;
			}
		}
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';
		require_once $plugin . '/lib/editor.comment-style.php';
		$file = sys_get_temp_dir() . '/wm-style-' . bin2hex(random_bytes(8)) . '.conf';

		try {
			$map = new WeatherMap();
			wm_comment_apply_style($map, 'bundled:Vera.ttf:9', '0 0 255');
			$map->WriteConfig($file);
			$saved = new WeatherMap();
			$saved->ReadConfig($file);
			$font = $saved->fonts[$saved->links['DEFAULT']->commentfont];
			self::assertSame('docs/example/Vera.ttf', $font->file);
			self::assertEquals(9, $font->size);
			self::assertEquals([0, 0, 255], $saved->links['DEFAULT']->commentfontcolour);
			self::assertStringContainsString('FONTDEFINE', file_get_contents($file));
		} finally {
			if (file_exists($file)) {
				unlink($file);
			}
		}
	}
}
