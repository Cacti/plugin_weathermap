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

final class InvalidBundledFontTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRejectsUnreadableFontData(): void {
		require_once dirname(__DIR__, 2) . '/lib/editor.comment-style.php';
		$file   = dirname(__DIR__, 2) . '/docs/example/Vera.ttf';
		$backup = $file . '.invalid-test-' . bin2hex(random_bytes(5));
		rename($file, $backup);

		try {
			file_put_contents($file, 'invalid font data');
			set_error_handler(static fn () => true);

			try {
				self::assertNull(wm_comment_resolve_font((object) ['fonts' => []], 'bundled:Vera.ttf:9'));
			} finally {
				restore_error_handler();
			}
		} finally {
			unlink($file);
			rename($backup, $file);
		}
	}
}
