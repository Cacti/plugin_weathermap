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
			wm_comment_apply_style($map, 'bundled:Vera.ttf:9', '0 0 255', null, 'center');
			$map->WriteConfig($file);
			$saved = new WeatherMap();
			$saved->ReadConfig($file);
			$font = $saved->fonts[$saved->links['DEFAULT']->commentfont];
			self::assertSame('docs/example/Vera.ttf', $font->file);
			self::assertEquals(9, $font->size);
			self::assertEquals([0, 0, 255], $saved->links['DEFAULT']->commentfontcolour);
			self::assertStringContainsString('FONTDEFINE', file_get_contents($file));
			self::assertSame('center', $saved->links['DEFAULT']->commentstyle);
			self::assertStringContainsString('COMMENTSTYLE center', file_get_contents($file));
			wm_comment_apply_style($saved, null, null, null, 'edge');
			self::assertTrue($saved->WriteConfig($file));
			$edge = new WeatherMap();
			self::assertTrue($edge->ReadConfig($file));
			self::assertSame('edge', $edge->links['DEFAULT']->commentstyle);

			// Explicit overrides equal to the default must survive save/reload and later default edits.
			file_put_contents($file, "NODE A\n POSITION 10 10\nNODE B\n POSITION 100 100\nLINK DEFAULT\n COMMENTSTYLE edge\n BWLABELPOS 75 25\nLINK ordinary\n NODES A B\nLINK fixed\n NODES A B\nLINK rawcustom\n NODES A B\n COMMENTSTYLE center\n BWLABELPOS 90 10\nLINK named\n COMMENTSTYLE center\n BWLABELPOS 80 20\nLINK templated\n TEMPLATE named\n NODES A B\n");
			$layout = new WeatherMap();
			self::assertTrue($layout->ReadConfig($file));
			wm_link_apply_overrides($layout, $layout->links['fixed'], 'edge', 'custom', '75', '25');
			self::assertTrue($layout->WriteConfig($file));
			$layout = new WeatherMap();
			self::assertTrue($layout->ReadConfig($file));
			self::assertSame('1', $layout->links['fixed']->get_hint('editor_commentstyle_override'));
			self::assertStringContainsString('commentstylechoice:' . js_escape('edge'), $layout->links['fixed']->asJS());
			self::assertStringContainsString('labelposmode:' . js_escape('custom'), $layout->links['fixed']->asJS());
			wm_comment_apply_style($layout, null, null, null, 'center');
			wm_link_apply_default_positions($layout, '85', '15');
			self::assertSame('edge', $layout->links['fixed']->commentstyle);
			self::assertEquals(75, $layout->links['fixed']->labeloffset_in);
			self::assertSame('center', $layout->links['ordinary']->commentstyle);
			self::assertEquals(85, $layout->links['ordinary']->labeloffset_in);
			self::assertEquals(90, $layout->links['rawcustom']->labeloffset_in);
			self::assertEquals(80, $layout->links['templated']->labeloffset_in);
			$before = serialize($layout->links['fixed']);
			wm_link_apply_overrides($layout, $layout->links['fixed'], 'invalid', 'custom', '-1', '101');
			self::assertSame($before, serialize($layout->links['fixed']));
			wm_link_apply_default_positions($layout, '10.5', '25');
			self::assertEquals(85, $layout->links['DEFAULT']->labeloffset_in);
			wm_link_apply_overrides($layout, $layout->links['fixed'], 'default', 'default', null, null);
			self::assertSame('center', $layout->links['fixed']->commentstyle);
			self::assertEquals(85, $layout->links['fixed']->labeloffset_in);
			self::assertNull($layout->links['fixed']->get_hint('editor_commentstyle_override'));
			self::assertTrue($layout->WriteConfig($file));
			$restored = new WeatherMap();
			self::assertTrue($restored->ReadConfig($file));
			wm_comment_apply_style($restored, null, null, null, 'edge');
			wm_link_apply_default_positions($restored, '70', '30');
			self::assertSame('edge', $restored->links['fixed']->commentstyle);
			self::assertEquals(70, $restored->links['fixed']->labeloffset_in);
			$html = wm_link_override_fields() . wm_link_position_fields($restored);
			self::assertStringContainsString('Use map default', $html);
			self::assertStringContainsString('id="mapstyle_labelposin"', $html);
			self::assertStringContainsString('id="link_commentstyle"', $html);

		} finally {
			if (file_exists($file)) {
				unlink($file);
			}
		}
	}
}
