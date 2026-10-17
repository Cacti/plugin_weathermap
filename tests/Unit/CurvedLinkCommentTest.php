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

final class CurvedLinkCommentTest extends TestCase {
	private function quarterCircle(float $radius, float $x = 0, float $y = 0, int $direction = 1): array {
		$points = [];
		for ($i = 0; $i <= 120; $i++) {
			$t = $i / 120 * M_PI / 2;
			$points[] = [$x + $radius * sin($t), $y + $direction * $radius * cos($t), $radius * $t];
		}
		return $points;
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testCurvedRenderingAndConfigRoundTrip(): void {
		chdir(dirname(__DIR__, 2));
		// Keep the rendering fixture independent of a surrounding Cacti checkout.
		function plugin_weathermap_numeric_version() { return '1.7'; }
		require_once 'lib/WeatherMap.class.php';
		require_once 'lib/editor.comment-style.php';
		$map = new class extends WeatherMap {
			public array $drawn = [];
			function myimagestring($image, $fontnumber, $x, $y, $string, $colour, $angle = 0) {
				$this->drawn[] = [$x, $y, $string, $angle];
			}
		};
		self::assertEquals(1, $map->links['DEFAULT']->commentcurve);
		self::assertStringContainsString('value="1" checked', wm_comment_style_fields($map));
		wm_comment_apply_style($map, 'bundled:Vera.ttf:13', '0 0 255', '1');
		$link = new WeatherMapLink();
		$link->Reset($map);
		$link->curvepoints = $this->quarterCircle(220, 100, 320, -1);
		self::assertTrue($link->DrawCurvedComment(null, '5G (Backup)', 0, 7, 20));
		self::assertSame('5G (Backup)', implode('', array_column($map->drawn, 2)));
		$angles = array_column($map->drawn, 3);
		self::assertGreaterThan(20, max($angles) - min($angles));
		foreach ($angles as $angle) {
			self::assertLessThanOrEqual(93, abs($angle));
		}
		// Independently drawn glyphs must retain TrueType pair kerning.
		$link->curvepoints = [[0, 0, 0], [1000, 0, 1000]];
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, 'AV', 0, 7, 20));
		$font = $map->fonts[$link->commentfont];
		$file = wm_font_file($font->file);
		$pair = imagettfbbox($font->size, 0, $file, 'AV|');
		$single = imagettfbbox($font->size, 0, $file, 'V|');
		self::assertEqualsWithDelta($pair[2] - $single[2], $map->drawn[1][0] - $map->drawn[0][0], 0.01);
		// A moderate inward offset can overlap wide glyphs even without reversing them.
		$link->curvepoints = $this->quarterCircle(40, 0, 0, 1);
		$link->add_hint('comment_nudgeout', 2); // width 7 + gap 4 + nudge 2 = 13 inward.
		$map->drawn = [];
		self::assertFalse($link->DrawCurvedComment(null, 'MM', 0, 7, 20));
		self::assertSame([], $map->drawn, 'Compressed glyph spacing falls back atomically');
		$link->add_hint('comment_nudgeout', 0);
		// An inward offset beyond the bend radius reverses glyph progress.
		$link->curvepoints = $this->quarterCircle(20, 0, 0, 1);
		$link->add_hint('comment_nudgeout', 40);
		$map->drawn = [];
		self::assertFalse($link->DrawCurvedComment(null, 'III', 0, 7, 10));
		self::assertSame([], $map->drawn, 'Reverse progress falls back before drawing');
		$link->add_hint('comment_nudgeout', 0);
		$link->curvepoints = $this->quarterCircle(220, 100, 320, -1);
		// Reversing the spine must still produce an upright, complete label.
		$link->curvepoints = array_reverse($link->curvepoints);
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, '5G (Backup)', 0, 7, 60));
		self::assertSame('5G (Backup)', implode('', array_column($map->drawn, 2)));
		self::assertLessThanOrEqual(93, max(array_map('abs', array_column($map->drawn, 3))));
		// Preserve the straight renderer's endpoint alignment and original A-to-B nudge direction.
		$link->curvepoints = [[0, 0, 0], [1000, 0, 1000]];
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, '5G Backup', 0, 7, 95));
		$end = $map->drawn;
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, '5G Backup', 0, 7, 50));
		$font = $map->fonts[$link->commentfont];
		$marker = imagettfbbox($font->size, 0, wm_font_file($font->file), '|');
		$box = imagettfbbox($font->size, 0, wm_font_file($font->file), '5G Backup|');
		self::assertEqualsWithDelta(950 - ($box[2] - $marker[2]), $end[0][0], 0.01);
		// The 95% anchor is the right edge, so the entire word must end there.
		self::assertLessThan(950, $end[0][0]);
		self::assertGreaterThan(800, $end[0][0]);
		$link->curvepoints = [[1000, 0, 0], [0, 0, 1000]];
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, '5G Backup', 0, 7, 50));
		$before = $map->drawn[0][0];
		$link->add_hint('comment_nudgealong', 10);
		$map->drawn = [];
		self::assertTrue($link->DrawCurvedComment(null, '5G Backup', 0, 7, 50));
		self::assertEqualsWithDelta($before - 10, $map->drawn[0][0], 0.01);
		$link->add_hint('comment_nudgealong', 2000);
		$map->drawn = [];
		self::assertFalse($link->DrawCurvedComment(null, '5G Backup', 0, 7, 50));
		self::assertSame([], $map->drawn, 'Unfittable placement falls back before any glyph is drawn');
		$link->add_hint('comment_nudgealong', 0);
		// Unsupported fonts, multiline and short geometry fall back atomically.
		$map->drawn = [];
		self::assertFalse($link->DrawCurvedComment(null, "two\nlines", 0, 7, 30));
		$link->commentfont = 2;
		self::assertFalse($link->DrawCurvedComment(null, '5G', 0, 7, 30));
		$link->commentfont = $map->links['DEFAULT']->commentfont;
		$link->curvepoints = [[0, 0, 0], [0, 0, 0], [1, 0, 1]];
		self::assertFalse($link->DrawCurvedComment(null, 'too long', 0, 7, 0));
		self::assertSame([], $map->drawn);
		$link->curvepoints = [[0, 0, 0], [50, 0, 50], [50, 100, 150]];
		self::assertFalse($link->DrawCurvedComment(null, '5G (Backup)', 0, 7, 0));
		self::assertSame([], $map->drawn);
		wm_comment_apply_style($map, null, null, 'invalid');
		self::assertSame(1, $map->links['DEFAULT']->commentcurve);
		$file = sys_get_temp_dir() . '/wm-curve-' . bin2hex(random_bytes(8)) . '.conf';
		try {
			$map->WriteConfig($file);
			self::assertStringNotContainsString('COMMENTCURVE', file_get_contents($file));
			$loaded = new WeatherMap();
			$loaded->ReadConfig($file);
			self::assertEquals(1, $loaded->links['DEFAULT']->commentcurve);
			self::assertStringContainsString('value="1" checked', wm_comment_style_fields($loaded));
			wm_comment_apply_style($loaded, null, null, '0');
			self::assertSame(0, $loaded->links['DEFAULT']->commentcurve);
			$loaded->WriteConfig($file);
			self::assertStringContainsString('COMMENTCURVE 0', file_get_contents($file));
			$disabled = new WeatherMap();
			$disabled->ReadConfig($file);
			self::assertEquals(0, $disabled->links['DEFAULT']->commentcurve);
			self::assertStringNotContainsString('value="1" checked', wm_comment_style_fields($disabled));
		} finally {
			@unlink($file);
		}
	}
}
