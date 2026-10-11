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

beforeAll(function () {
	require_once dirname(__DIR__,2) . '/setup.php';
	require_once dirname(__DIR__,2) . '/lib/editor.config-delete.php';
});
it('DeletesOnlyUnusedRegularConfigurationFiles', function () {
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
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => 1;
		expect(wm_config_delete($dir,$file))->toBe('used')->and(file_get_contents($dir . '/' . $file))->toBe('original');

		foreach ([false, null] as $failure) {
			$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => $failure;
			expect(wm_config_delete($dir, $file))->toBe('failed')->and(file_get_contents($dir . '/' . $file))->toBe('original');
		}
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => 0;
		expect(wm_config_delete($dir,$file))->toBe('deleted')->and(file_exists($dir . '/' . $file))->toBeFalse();
	} finally {
		unset($GLOBALS['__test_db_fetch_cell_prepared']);

		foreach (glob($dir . '/*') as $p) {
			unlink($p);
		}rmdir($dir);
	}
});
it('ReportsADeletionFailureWithoutClaimingSuccess', function () {
	$dir = sys_get_temp_dir() . '/wm-delete-denied-' . bin2hex(random_bytes(8));
	mkdir($dir);
	file_put_contents($dir . '/test.conf','test');
	chmod($dir,0500);
	$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => 0;
	set_error_handler(fn () => true);

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
it('EscapesNamesAndEmitsTheCactiNonceAndCsrfProtectedPost', function () {
	$button = wm_config_delete_button('Test <map> "quoted".conf');
	expect($button)->toContain('Test &lt;map&gt; &quot;quoted&quot;.conf','data-confirm=','aria-label=','permanently delete');
	ob_start();
	wm_config_delete_script();
	$script = ob_get_clean();
	expect($script)->toContain('<script','window.confirm','form.method = \'post\'','__csrf_magic: csrfMagicToken',plugin_weathermap_csp_nonce());
});

it('EscapesFilenameMessagesFromTheActualDeleteAction', function () {
	$plugin             = dirname(__DIR__, 2);
	$source             = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
	$start              = strpos($source, "case 'delete_config':");
	$end                = strpos($source, "header('Location:", $start);
	$action             = substr($source, $start + strlen("case 'delete_config':"), $end - $start - strlen("case 'delete_config':"));
	$action             = str_replace('__DIR__', var_export($plugin, true), $action);
	$weathermap_confdir = sys_get_temp_dir() . '/wm-delete-message-' . bin2hex(random_bytes(8));
	mkdir($weathermap_confdir);
	$file                                      = 'Map <img src=x onerror=alert(1)>.conf';
	$old_method                                = $_SERVER['REQUEST_METHOD'] ?? null;
	$_SERVER['REQUEST_METHOD']                 = 'POST';
	$GLOBALS['__test_nfilter_request']['file'] = $file;
	$GLOBALS['__test_raise_message']           = function ($id, $text) {
		$GLOBALS['__test_delete_message'] = $text;
	};

	try {
		foreach ([1, 0] as $used) {
			file_put_contents($weathermap_confdir . '/' . $file, 'test');
			$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => $used;
			eval('switch (true) { case true: ' . $action . ' }');
			expect($GLOBALS['__test_delete_message'])->toContain('&lt;img')->not->toContain('<img');
		}
	} finally {
		foreach (glob($weathermap_confdir . '/*') as $path) {
			unlink($path);
		}
		rmdir($weathermap_confdir);
		unset($GLOBALS['__test_nfilter_request']['file'], $GLOBALS['__test_raise_message'], $GLOBALS['__test_delete_message'], $GLOBALS['__test_db_fetch_cell_prepared']);

		if ($old_method === null) {
			unset($_SERVER['REQUEST_METHOD']);
		} else {
			$_SERVER['REQUEST_METHOD'] = $old_method;
		}
	}
});

it('ProtectsRegisteredCanonicalPathAliasesAndFailsClosedOnLookupFailure', function () {
	$directory = sys_get_temp_dir() . '/wm-alias-' . bin2hex(random_bytes(8));
	mkdir($directory);
	mkdir($directory . '/sub');
	file_put_contents($directory . '/Map.conf', 'original');
	file_put_contents($directory . '/Other.conf', 'other');
	$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => 0;
	try {
		foreach (['./Map.conf', 'sub/../Map.conf', $directory . '/./Map.conf'] as $alias) {
			$GLOBALS['__test_db_fetch_assoc_prepared'] = fn () => [['configfile' => $alias]];
			expect(wm_config_delete($directory, 'Map.conf'))->toBe('used')->and(file_get_contents($directory . '/Map.conf'))->toBe('original');
		}
		foreach ([false, null] as $failure) {
			$GLOBALS['__test_db_fetch_assoc_prepared'] = fn () => $failure;
			expect(wm_config_delete($directory, 'Map.conf'))->toBe('failed');
		}
		$GLOBALS['__test_db_fetch_assoc_prepared'] = fn () => [['configfile' => './Other.conf']];
		expect(wm_config_delete($directory, 'Map.conf'))->toBe('deleted')->and(file_exists($directory . '/Map.conf'))->toBeFalse();
	} finally {
		unset($GLOBALS['__test_db_fetch_cell_prepared'], $GLOBALS['__test_db_fetch_assoc_prepared']);
		unlink($directory . '/Other.conf');
		if (file_exists($directory . '/Map.conf')) { unlink($directory . '/Map.conf'); }
		rmdir($directory . '/sub');
		rmdir($directory);
	}
});

it('PreservesAbsoluteRegisteredPathsOnBothPlatforms', function ($file, $expected) {
	expect(wm_config_registered_path('/configs', $file))->toBe($expected);
})->with([
	'unix' => ['/cacti/configs/Map.conf', '/cacti/configs/Map.conf'],
	'drive-slash' => ['C:/cacti/configs/Map.conf', 'C:/cacti/configs/Map.conf'],
	'drive-backslash' => ['C:\\cacti\\configs\\Map.conf', 'C:\\cacti\\configs\\Map.conf'],
	'unc' => ['\\\\server\\share\\Map.conf', '\\\\server\\share\\Map.conf'],
	'unc-slash' => ['//server/share/Map.conf', '//server/share/Map.conf'],
	'rooted-backslash' => ['\\cacti\\configs\\Map.conf', '\\cacti\\configs\\Map.conf'],
	'relative' => ['Map.conf', '/configs/Map.conf'],
	'dot-relative' => ['./Map.conf', '/configs/./Map.conf'],
	'drive-relative' => ['C:Map.conf', '/configs/C:Map.conf'],
	'not-drive' => ['1:/Map.conf', '/configs/1:/Map.conf'],
]);
