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

namespace WeathermapConfig {
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|false
	 */
	function fopen($path, $mode) {
		$fault = $GLOBALS['config_fault'] ?? '';
		if (($fault === 'lock-open' && str_ends_with($path, '.lock')) || ($fault === 'lock-read' && $mode === 'c+b') || ($fault === 'temp-open' && $mode === 'wb')) return false;
		return \fopen($path, $mode);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param resource $stream
	 * @param int $mode
	 * @return bool
	 */
	function flock($stream, $mode) {
		if (($GLOBALS['config_fault'] ?? '') === 'lock' && $mode === LOCK_EX) return false;
		return \flock($stream, $mode);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param string $dir
	 * @param string $prefix
	 * @return string|false
	 */
	function tempnam($dir, $prefix) {
		return ($GLOBALS['config_fault'] ?? '') === 'temp-name' ? false : \tempnam($dir, $prefix);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param resource $stream
	 * @param string $bytes
	 * @return int|false
	 */
	function fwrite($stream, $bytes) {
		$fault = $GLOBALS['config_fault'] ?? '';
		if ($fault === 'write-false') return false;
		if ($fault === 'write-zero') return 0;
		return \fwrite($stream, $fault === 'short-write' ? substr($bytes, 0, 2) : $bytes);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param resource $stream
	 * @return bool
	 */
	function fflush($stream) {
		return ($GLOBALS['config_fault'] ?? '') !== 'flush' && \fflush($stream);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param resource $stream
	 * @return bool
	 */
	function fclose($stream) {
		$temp = strpos(stream_get_meta_data($stream)['uri'], '.wm-config-') !== false;
		$closed = \fclose($stream);
		return !(($GLOBALS['config_fault'] ?? '') === 'close' && $temp) && $closed;
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param string $path
	 * @param int $group
	 * @return bool
	 */
	function chgrp($path, $group) {
		return ($GLOBALS['config_fault'] ?? '') !== 'group' && \chgrp($path, $group);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param string $path
	 * @param int $mode
	 * @return bool
	 */
	function chmod($path, $mode) {
		return ($GLOBALS['config_fault'] ?? '') !== 'mode' && \chmod($path, $mode);
	}
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	function rename($source, $target) {
		return ($GLOBALS['config_fault'] ?? '') !== 'rename' && \rename($source, $target);
	}
}
namespace {
	require dirname(__DIR__) . '/bootstrap-unit.php';
	chdir(dirname(__DIR__, 2));
	require dirname(__DIR__, 2) . '/setup.php';
	require dirname(__DIR__, 2) . '/lib/WeatherMap.class.php';
	require dirname(__DIR__, 2) . '/lib/WeatherMap.config.php';
	require dirname(__DIR__, 2) . '/lib/editor.inc.php';
	require dirname(__DIR__, 2) . '/lib/editor.actions.php';
	/**
	 * Simulate filesystem faults for an isolated atomic-write regression.
	 *
	 * @param bool $condition
	 * @param string $message
	 * @return void
	 */
	function atomic_assert($condition, $message) {
		if (!$condition) throw new \RuntimeException($message);
	}
	$dir = sys_get_temp_dir() . '/wm-atomic-' . bin2hex(random_bytes(5));
	mkdir($dir);
	$file = $dir . '/map.conf';
	$original = "WIDTH 600\nHEIGHT 300\nNODE note1\n LABEL Original\n POSITION 50 50\n SET editor_note 1\n";
	file_put_contents($file, $original);
	try {
		foreach (['lock-open', 'lock', 'temp-name', 'temp-open', 'write-false', 'write-zero', 'flush', 'close', 'group', 'mode', 'rename'] as $fault) {
			$GLOBALS['config_fault'] = $fault;
			atomic_assert(\WeathermapConfig\atomicWrite($file, 'Replacement') === false, 'failure propagated: ' . $fault);
			atomic_assert(file_get_contents($file) === $original, 'original retained: ' . $fault);
			atomic_assert(glob($dir . '/.wm-config-*') === [], 'temporary removed: ' . $fault);
		}
		$GLOBALS['config_fault'] = 'short-write';
		atomic_assert(\WeathermapConfig\atomicWrite($file, 'Complete replacement'), 'short writes completed');
		atomic_assert(file_get_contents($file) === 'Complete replacement', 'complete bytes retained');
		$GLOBALS['config_fault'] = 'lock-read';
		atomic_assert(\WeathermapConfig\atomicWrite($file, $original), 'readable lock supports another writer account');
		$GLOBALS['config_fault'] = '';
		atomic_assert(!\WeathermapConfig\atomicWrite('notewritefail://map/config', 'Replacement'), 'unsupported stream rejected');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/missing/map.conf', 'Replacement'), 'missing directory rejected');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir, 'Replacement'), 'directory target rejected');
		$link = $dir . '/link.conf'; symlink($file, $link);
		atomic_assert(\WeathermapConfig\atomicWrite($link, $original), 'configuration symlink target preserved');
		atomic_assert(is_link($link), 'link itself retained'); unlink($link);
		if (function_exists('pcntl_fork')) {
			$lock = \WeathermapConfig\acquire();
			$child = pcntl_fork();
			if ($child === 0) {
				$probe = \fopen(dirname(__DIR__, 2) . '/configs/.weathermap-config.lock', 'rb');
				$blocked = !\flock($probe, LOCK_EX | LOCK_NB); \fclose($probe);
				exit($blocked ? 0 : 1);
			}
			pcntl_waitpid($child, $status); \WeathermapConfig\release($lock);
			atomic_assert(pcntl_wexitstatus($status) === 0, 'shared lock excludes concurrent configuration writers');
		}
		$templateFile = $dir . '/templates.conf';
		file_put_contents($templateFile, "NODE templateNode\n LABEL Template\nNODE A\n POSITION 10 10\nNODE B\n POSITION 50 50\nLINK templateLink\n WIDTH 8\nLINK trafficLink\n NODES A B\n");
		$templateMap = new \WeatherMap; $templateMap->ReadConfig($templateFile);
		atomic_assert($templateMap->WriteConfig($templateFile), 'atomic save supports templates and traffic links');
		$reloaded = new \WeatherMap; $reloaded->ReadConfig($templateFile);
		atomic_assert(isset($reloaded->nodes['templateNode'], $reloaded->links['templateLink'], $reloaded->links['trafficLink']), 'template and real objects survive atomic roundtrip');
		$GLOBALS['config_fault'] = 'rename';
		$map = new \WeatherMap; $map->ReadConfig($file); $map->title = 'Changed';
		atomic_assert(!$map->WriteConfig($file) && file_get_contents($file) === $original, 'engine failure preserves original');
		$GLOBALS['__test_nfilter_request'] = ['node_name' => 'note1', 'param' => 'note1', 'x' => '70', 'y' => '80'];
		if (function_exists('wm_editor_save_note')) {
			http_response_code(200); moveNode($file, 0);
			atomic_assert(http_response_code() === 500 && file_get_contents($file) === $original, 'failed move surfaces error without changing file');
			http_response_code(200); deleteNode($file);
			atomic_assert(http_response_code() === 500 && file_get_contents($file) === $original, 'failed delete surfaces error without changing file');
		}
		echo "Atomic configuration write and locking regression PASS\n";
	} finally {
		$GLOBALS['config_fault'] = '';
		foreach (glob($dir . '/*') as $path) unlink($path);
		foreach (glob($dir . '/.wm-config-*') as $path) unlink($path);
		rmdir($dir);
	}
}
