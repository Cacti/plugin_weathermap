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

final class EditorSettingsCookieTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testSettingsActionPersistsCookieBeforeExit(): void {
		require dirname(__DIR__) . '/bootstrap-unit.php';
		function cacti_cookie_set($name, $value) { $GLOBALS['wm_test_cookies'][$name] = $value; }
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require $plugin . '/setup.php';
		require $plugin . '/lib/WeatherMap.class.php';
		require $plugin . '/lib/editor.inc.php';
		require $plugin . '/lib/editor.actions.php';
		$file = tempnam(sys_get_temp_dir(), 'wmsettings');
		file_put_contents($file, "WIDTH 100\nHEIGHT 100\n");
		try {
			foreach ([[1,1,10,'1:1:10'], [0,1,5,'0:1:5'], [0,0,'NO','0:0:0']] as [$via,$relative,$grid,$expected]) {
				$GLOBALS['__test_nfilter_request'] = ['config_revision'=>hash_file('sha256', $file), 'editorsettings_showvias'=>$via, 'editorsettings_showrelative'=>$relative, 'editorsettings_gridsnap'=>$grid];
				$GLOBALS['__test_request'] = $GLOBALS['__test_nfilter_request'];
				editorSettings($file);
				self::assertSame($expected, $GLOBALS['wm_test_cookies']['wmeditor']);
			}
		} finally { unlink($file); }
	}
}
