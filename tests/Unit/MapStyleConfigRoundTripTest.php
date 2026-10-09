<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
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
			 * @param mixed $values
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
