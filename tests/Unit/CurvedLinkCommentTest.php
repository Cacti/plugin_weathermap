<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class CurvedLinkCommentTest extends TestCase {
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
		$link->curvepoints = [];
		for ($i = 0; $i <= 120; $i++) {
			$t = $i / 120 * M_PI / 2;
			$link->curvepoints[] = [100 + 220 * sin($t), 320 - 220 * cos($t), 220 * $t];
		}
		self::assertTrue($link->DrawCurvedComment(null, '5G (Backup)', 0, 7, 20));
		self::assertSame('5G (Backup)', implode('', array_column($map->drawn, 2)));
		$angles = array_column($map->drawn, 3);
		self::assertGreaterThan(20, max($angles) - min($angles));
		foreach ($angles as $angle) {
			self::assertLessThanOrEqual(93, abs($angle));
		}
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
