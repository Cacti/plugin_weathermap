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

beforeAll(function () {
	require_once dirname(__DIR__,2) . '/setup.php';
	require_once dirname(__DIR__,2) . '/lib/editor.config-delete.php';
});
it('DeletesOnlyUnusedRegularConfigurationFiles',function () {
	$dir = sys_get_temp_dir() . '/wm-delete-' . bin2hex(random_bytes(8));
	mkdir($dir);
	$file = 'Name & "quoted" + %.conf';
	file_put_contents($dir . '/' . $file,'original');

	try {
		foreach ([null, [], '', '../outside.conf', '/absolute.conf', 'a\\b.conf', "bad\n.conf", 'bad.txt', 'missing.conf'] as $bad) {
			expect(wm_config_delete($dir,$bad))->toBe('invalid');
		}
		expect(wm_config_delete($dir . '/missing',$file))->toBe('invalid');
		symlink($dir . '/' . $file,$dir . '/symlink.conf');
		expect(wm_config_delete($dir,'symlink.conf'))->toBe('invalid');
		unlink($dir . '/symlink.conf');
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn () =>1;
		expect(wm_config_delete($dir,$file))->toBe('used')->and(file_get_contents($dir . '/' . $file))->toBe('original');
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn () =>0;
		expect(wm_config_delete($dir,$file))->toBe('deleted')->and(file_exists($dir . '/' . $file))->toBeFalse();
	} finally {
		unset($GLOBALS['__test_db_fetch_cell_prepared']);

		foreach (glob($dir . '/*') as $p) {
			unlink($p);
		}rmdir($dir);
	}
});
it('ReportsADeletionFailureWithoutClaimingSuccess',function () {
	$dir = sys_get_temp_dir() . '/wm-delete-denied-' . bin2hex(random_bytes(8));
	mkdir($dir);
	file_put_contents($dir . '/test.conf','test');
	chmod($dir,0500);
	$GLOBALS['__test_db_fetch_cell_prepared'] = fn () =>0;
	set_error_handler(fn () =>true);

	try {
		expect(wm_config_delete($dir,'test.conf'))->toBe('failed')->and(file_exists($dir . '/test.conf'))->toBeTrue();
	} finally {
		restore_error_handler();
		chmod($dir,0700);
		unlink($dir . '/test.conf');
		rmdir($dir);
		unset($GLOBALS['__test_db_fetch_cell_prepared']);
	}
});
it('EscapesNamesAndEmitsTheCactiNonceAndCsrfProtectedPost',function () {
	$button = wm_config_delete_button('Test <map> "quoted".conf');
	expect($button)->toContain('Test &lt;map&gt; &quot;quoted&quot;.conf','data-confirm=','aria-label=','permanently delete');
	ob_start();
	wm_config_delete_script();
	$script = ob_get_clean();
	expect($script)->toContain('<script','window.confirm','form.method = \'post\'','__csrf_magic: csrfMagicToken',plugin_weathermap_csp_nonce());
});
