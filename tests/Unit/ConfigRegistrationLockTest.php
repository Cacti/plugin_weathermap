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
	$plugin = dirname(__DIR__, 2);
	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/editor.config-delete.php';
	$source = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
	foreach (['add_config', 'wmap_get_title'] as $name) {
		$start = strpos($source, 'function ' . $name . '(');
		$end = strpos($source, "\n}\n", $start) + 2;
		eval(str_replace('__DIR__', var_export($plugin, true), substr($source, $start, $end - $start)));
	}
	if (!function_exists('db_fetch_insert_id')) {
		function db_fetch_insert_id() { return 1; }
	}
	if (!function_exists('map_resort')) {
		function map_resort() {}
	}
});

it('SerializesTheActualRegistrationAndDeletionPaths', function () {
	global $weathermap_confdir;
	$cwd = getcwd();
	$old_directory = $weathermap_confdir ?? null;
	$directory = sys_get_temp_dir() . '/wm-registration-' . bin2hex(random_bytes(8));
	mkdir($directory);
	$weathermap_confdir = realpath($directory);
	$file = 'Map.conf';
	$path = $directory . '/' . $file;
	file_put_contents($path, "TITLE Lock test\n");
	$registered = false;
	$inserted = 0;
	$GLOBALS['__test_db_fetch_cell_prepared'] = function () use (&$registered) { return $registered ? 1 : 0; };
	$GLOBALS['__test_db_execute_prepared'] = function ($sql, $params) use (&$registered, &$inserted, $directory, $file) {
		if (strpos($sql, 'INSERT INTO weathermap_maps') !== false) {
			expect($params[0])->toBe($file);
			expect(wm_config_delete($directory, $file))->toBe('failed');
			$registered = true;
			$inserted++;
		}
		return true;
	};
	try {
		$lock = wm_config_lock($path);
		expect(is_resource($lock))->toBeTrue();
		add_config($file);
		expect($inserted)->toBe(0)->and(wm_config_delete($directory, $file))->toBe('failed');
		flock($lock, LOCK_UN);
		fclose($lock);
		add_config('./' . $file);
		expect($inserted)->toBe(1)->and(wm_config_delete($directory, $file))->toBe('used');
		$registered = false;
		$GLOBALS['__test_db_fetch_cell_prepared'] = function () use ($file, &$inserted) {
			add_config($file);
			expect($inserted)->toBe(1);
			return 0;
		};
		expect(wm_config_delete($directory, $file))->toBe('deleted');
		// A missing file is an expected failure, including a concurrent unlink.
		set_error_handler(fn () => true);
		try {
			add_config($file);
			expect($inserted)->toBe(1)->and(wm_config_lock($path))->toBeFalse();
		} finally {
			restore_error_handler();
		}
		// Symlinks never yield a usable config lock.
		file_put_contents($path, 'new');
		symlink($path, $directory . '/alias.conf');
		expect(wm_config_lock($directory . '/alias.conf'))->toBeFalse();
		$GLOBALS['__test_db_execute_prepared'] = function () { throw new RuntimeException('query failed'); };
		expect(fn () => add_config($file))->toThrow(RuntimeException::class);
		$lock = wm_config_lock($path);
		expect(is_resource($lock))->toBeTrue();
		flock($lock, LOCK_UN);
		fclose($lock);
	} finally {
		chdir($cwd);
		$weathermap_confdir = $old_directory;
		unset($GLOBALS['__test_db_execute_prepared'], $GLOBALS['__test_db_fetch_cell_prepared']);
		foreach (glob($directory . '/*') as $entry) { unlink($entry); }
		rmdir($directory);
	}
});
