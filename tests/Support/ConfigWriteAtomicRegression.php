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
		if (($mode & LOCK_EX) && isset($GLOBALS['config_before_lock'])) ($GLOBALS['config_before_lock'])();
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
	require dirname(__DIR__, 2) . '/lib/poller-common.php';
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
		foreach (array_filter(['lock-open', 'lock', 'temp-name', 'temp-open', 'write-false', 'write-zero', 'flush', 'close', 'group', 'mode', 'rename'], function($fault) { return PHP_OS_FAMILY !== 'Windows' || $fault !== 'group'; }) as $fault) {
			$GLOBALS['config_fault'] = $fault;
			atomic_assert(\WeathermapConfig\atomicWrite($file, 'Replacement') === false, 'failure propagated: ' . $fault);
			atomic_assert(file_get_contents($file) === $original, 'original retained: ' . $fault);
			atomic_assert(glob($dir . '/.wm-config-*') === [], 'temporary removed: ' . $fault);
		}
		$GLOBALS['config_fault'] = 'short-write';
		atomic_assert(\WeathermapConfig\atomicWrite($file, 'Complete replacement'), 'short writes completed');
		atomic_assert(file_get_contents($file) === 'Complete replacement', 'complete bytes retained');
		$GLOBALS['config_fault'] = PHP_OS_FAMILY === 'Windows' ? '' : 'lock-read';
		atomic_assert(\WeathermapConfig\atomicWrite($file, $original), 'readable lock supports another writer account');
		$GLOBALS['config_fault'] = '';
		atomic_assert(!\WeathermapConfig\atomicWrite('notewritefail://map/config', 'Replacement'), 'unsupported stream rejected');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/missing/map.conf', 'Replacement'), 'missing directory rejected');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir, 'Replacement'), 'directory target rejected');
		$link = $dir . '/link.conf';
		if (@symlink($file, $link)) {
			atomic_assert(\WeathermapConfig\atomicWrite($link, $original), 'configuration symlink target preserved');
			atomic_assert(is_link($link), 'link itself retained'); unlink($link);
		}
		if (function_exists('pcntl_fork')) {
			$lock = \WeathermapConfig\acquire($dir);
			$child = pcntl_fork();
			if ($child === 0) {
				$probe = \fopen($dir . '/.weathermap-config.lock', 'rb');
				$blocked = !\flock($probe, LOCK_EX | LOCK_NB); \fclose($probe);
				exit($blocked ? 0 : 1);
			}
			pcntl_waitpid($child, $status); \WeathermapConfig\release($lock);
			atomic_assert(pcntl_wexitstatus($status) === 0, 'shared lock excludes concurrent configuration writers');
		}
		// Deletion refuses a busy stable directory mutex rather than blocking the UI.
		if (function_exists('pcntl_fork')) {
			require_once dirname(__DIR__, 2) . '/lib/editor.config-delete.php';
			$GLOBALS['__test_db_fetch_cell_prepared'] = fn () => 0;
			$GLOBALS['__test_db_fetch_assoc_prepared'] = fn () => [];
			$lock = \WeathermapConfig\acquire($dir);
			$ready = $dir . '/ready'; $done = $dir . '/done';
			$child = pcntl_fork();
			if ($child === 0) {
				\fclose($lock);
				file_put_contents($ready, 'ready');
				$result = wm_config_delete($dir, 'map.conf');
				file_put_contents($done, $result);
				exit($result === 'failed' ? 0 : 1);
			}
			for ($wait = 0; $wait < 100 && !is_file($done); $wait++) usleep(10000);
			$blocked = is_file($done) && file_get_contents($done) === 'failed' && is_file($file);
			\WeathermapConfig\release($lock);
			pcntl_waitpid($child, $status);
			atomic_assert($blocked && pcntl_wexitstatus($status) === 0, 'config deletion cannot unlink during a directory-locked write');
			atomic_assert(wm_config_delete($dir, 'map.conf') === 'deleted', 'deletion succeeds after the writer releases its lock');
			unlink($ready); unlink($done);
			unset($GLOBALS['__test_db_fetch_cell_prepared'], $GLOBALS['__test_db_fetch_assoc_prepared']);
			file_put_contents($file, $original);
		}
		atomic_assert(!\WeathermapConfig\acquire($dir . '/missing'), 'missing lock directory rejected');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/deleted.conf', 'Replacement', true), 'deleted existing maps are not recreated');
		atomic_assert(is_file($dir . '/.weathermap-config.lock'), 'arbitrary output uses its destination directory lock');
		atomic_assert(!\WeathermapConfig\atomicWrite($file, 'Overwrite', false, null, true), 'create-only destination refuses existing files');
		$first = new \WeatherMap; $second = new \WeatherMap;
		$first->ReadConfig($file); $second->ReadConfig($file);
		$first->title = 'First edit'; $second->title = 'Stale edit';
		atomic_assert($first->WriteConfig($file), 'first revision saves');
		$current = file_get_contents($file);
		atomic_assert(!$second->WriteConfig($file) && file_get_contents($file) === $current, 'stale reader cannot overwrite the first edit');
		$first->title = 'Next edit';
		atomic_assert($first->WriteConfig($file), 'successful save updates its revision for subsequent writes');
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash('sha256', $original), 'node_name' => 'note1', 'x' => 90, 'y' => 90];
		http_response_code(200); moveNode($file, 0);
		atomic_assert(http_response_code() === 409 && file_get_contents($file) !== $original, 'stale loaded page cannot overwrite another user save');
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash_file('sha256', $file)];
		$matching = new \WeatherMap;
		atomic_assert(wm_editor_read_config($matching, $file), 'matching loaded page revision accepted');
		$GLOBALS['__test_nfilter_request'] = [];
		$raw = new \WeatherMap; wm_editor_read_config($raw, $file);
		$raw->ReadConfig("TITLE Fragment edit\n");
		file_put_contents($file, $original);
		http_response_code(200);
		atomic_assert(!wm_editor_write_config($raw, $file) && http_response_code() === 409 && file_get_contents($file) === $original, 'raw fragment edits retain the original request revision');
		if (function_exists('pcntl_fork')) {
			$createFile = $dir . '/create.conf';
			$lock = \WeathermapConfig\acquire($dir); $children = [];
			for ($index = 0; $index < 2; $index++) {
				$child = pcntl_fork();
				if ($child === 0) {
					\fclose($lock);
					$GLOBALS['config_before_lock'] = function() use ($dir, $index) { file_put_contents($dir . '/create-ready-' . $index, 'ready'); };
					$saved = \WeathermapConfig\atomicWrite($createFile, 'Creator ' . $index, false, null, true);
					file_put_contents($dir . '/create-result-' . $index, $saved ? '1' : '0');
					exit(0);
				}
				$children[] = $child;
			}
			for ($wait = 0; $wait < 100 && cacti_count(glob($dir . '/create-ready-*')) < 2; $wait++) usleep(10000);
			\WeathermapConfig\release($lock);
			foreach ($children as $child) pcntl_waitpid($child, $status);
			atomic_assert((int)file_get_contents($dir . '/create-result-0') + (int)file_get_contents($dir . '/create-result-1') === 1, 'exactly one concurrent creator commits');
			atomic_assert(in_array(file_get_contents($createFile), ['Creator 0', 'Creator 1'], true), 'winning creator content remains intact');
		}
		$held = \WeathermapConfig\acquire($dir);
		atomic_assert(\WeathermapConfig\atomicWrite($dir . '/held.conf', 'Held write', false, null, true, $held), 'caller-owned mutex supports atomic creation');
		if (function_exists('pcntl_fork')) {
			$child = pcntl_fork();
			if ($child === 0) { $probe = \fopen($dir . '/.weathermap-config.lock', 'rb'); $busy = !\flock($probe, LOCK_EX | LOCK_NB); exit($busy ? 0 : 1); }
			pcntl_waitpid($child, $status);
			atomic_assert(pcntl_wexitstatus($status) === 0, 'atomicWrite leaves caller mutex held');
		}
		\WeathermapConfig\release($held);
		$wrong = \fopen($file, 'rb');
		atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/wrong-lock.conf', 'Wrong', false, null, true, $wrong), 'different lock file rejected'); \fclose($wrong);
		// Revision metadata must describe the same map snapshot as the controls.
		file_put_contents($file, $original); $snapshot = new \WeatherMap; $snapshot->ReadConfig($file);
		$loadedRevision = $snapshot->config_revision;
		$updatedSnapshot = str_replace('Original', 'NewSnapshot', $original); file_put_contents($file, $updatedSnapshot);
		$GLOBALS['__test_nfilter_request'] = [];
		ob_start(); getMapJavaScript($file, $snapshot); $javascript = ob_get_clean();
		atomic_assert(str_contains($javascript, 'Original') && !str_contains($javascript, 'NewSnapshot') && str_contains($javascript, $loadedRevision), 'rendered values and revision come from one loaded snapshot');
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => $loadedRevision];
		http_response_code(200); atomic_assert(!wm_editor_read_config(new \WeatherMap, $file) && http_response_code() === 409, 'concurrent save cannot be hidden by a refreshed token');
		// Simulate deletion between the existence check and the engine file read.
		class AtomicDeletedDuringReadMap extends \WeatherMap {
			/** Simulate an engine load that loses its file before opening it. */
			function ReadConfig($input, $is_include = false) { unlink($input); $this->config_revision = null; return true; }
		}
		foreach ([[], ['config_revision' => $loadedRevision]] as $request) {
			file_put_contents($file, $original); $GLOBALS['__test_nfilter_request'] = $request;
			http_response_code(200); atomic_assert(!wm_editor_read_config(new AtomicDeletedDuringReadMap, $file) && http_response_code() === 404, 'deletion race cannot proceed with a null revision');
		}

		$plugin = dirname(__DIR__, 2);
		// Exercise both copy entry points with an engine that loses the source during loading.
		$actions = file_get_contents($plugin . '/lib/editor.actions.php');
		$copyStart = strpos($actions, 'function newMapCopy('); $copyEnd = strpos($actions, "\n/**", $copyStart);
		eval(str_replace(['function newMapCopy(', 'new WeatherMap'], ['function atomicLostSourceCopy(', 'new AtomicDeletedDuringReadMap'], substr($actions, $copyStart, $copyEnd - $copyStart)));
		$GLOBALS['mapdir'] = $dir; $GLOBALS['__test_request']['sourcemap'] = basename($file); $GLOBALS['__test_nfilter_request'] = ['sourcemap' => basename($file)];
		file_put_contents($file, $original); http_response_code(200); atomicLostSourceCopy($dir . '/lost-copy.conf');
		atomic_assert(http_response_code() === 404 && !is_file($dir . '/lost-copy.conf'), 'editor copy rejects source lost during load');
		$managementCopy = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
		eval(str_replace(['__DIR__', 'function newMap(', 'new WeatherMap'], [var_export($plugin, true), 'function atomicLostManagementCopy(', 'new AtomicDeletedDuringReadMap'], substr($managementCopy, strpos($managementCopy, 'function newMap('))));
		$GLOBALS['weathermap_confdir'] = $dir; file_put_contents($file, $original);
		atomicLostManagementCopy('lost-management-copy.conf', basename($file));
		atomic_assert(!is_file($dir . '/lost-management-copy.conf'), 'management copy rejects source lost during load');
		unset($GLOBALS['__test_request']['sourcemap']);
		$GLOBALS['__test_nfilter_request'] = []; file_put_contents($file, $original);
		$crlfFile = $dir . '/crlf.conf';
		$crlf = "WIDTH 600\r\nHEIGHT 300\r\n"; file_put_contents($crlfFile, $crlf);
		$crlfMap = new \WeatherMap; $crlfMap->ReadConfig($crlfFile);
		atomic_assert($crlfMap->config_revision === hash('sha256', $crlf) && $crlfMap->WriteConfig($crlfFile), 'revision hashes preserve raw CRLF bytes');
		$templateFile = $dir . '/templates.conf';
		file_put_contents($templateFile, "NODE templateNode\n LABEL Template\nNODE A\n POSITION 10 10\nNODE B\n POSITION 50 50\nLINK templateLink\n WIDTH 8\nLINK trafficLink\n NODES A B\n");
		$templateMap = new \WeatherMap; $templateMap->ReadConfig($templateFile);
		atomic_assert($templateMap->WriteConfig($templateFile), 'atomic save supports templates and traffic links');
		$reloaded = new \WeatherMap; $reloaded->ReadConfig($templateFile);
		atomic_assert(isset($reloaded->nodes['templateNode'], $reloaded->links['templateLink'], $reloaded->links['trafficLink']), 'template and real objects survive atomic roundtrip');
		$plugin = dirname(__DIR__, 2);
		$repairName = 'atomic-repair-' . bin2hex(random_bytes(4)) . '.conf';
		$repairFile = $plugin . '/configs/' . $repairName;
		$repairImage = $plugin . '/images/backgrounds/' . $repairName . '.png';
		$legacy = 'BACKGROUND ' . basename($repairImage) . "\n";
		file_put_contents($repairFile, $legacy); file_put_contents($repairImage, 'fixture');
		$oldConfig = $GLOBALS['config'];
		$GLOBALS['config']['base_path'] = dirname($plugin, 2);
		$GLOBALS['__test_db_fetch_assoc'] = fn ($sql) => [['configfile' => $repairName]];
		try {
			$GLOBALS['config_fault'] = 'rename';
			$oldSuppress = $GLOBALS['weathermap_error_suppress'] ?? [];
			$GLOBALS['weathermap_error_suppress'] = ['WMPOLL13'];
			try { weathermap_repair_maps(); } finally { $GLOBALS['weathermap_error_suppress'] = $oldSuppress; }
			atomic_assert(file_get_contents($repairFile) === $legacy, 'failed repair preserves original config');
			$GLOBALS['config_fault'] = '';
			weathermap_repair_maps();
			atomic_assert(file_get_contents($repairFile) === 'BACKGROUND images/backgrounds/' . basename($repairImage), 'repair uses complete atomic replacement');
			file_put_contents($repairFile, $legacy);
			$newerRepair = 'TITLE Concurrent editor save' . "\n" . $legacy;
			$GLOBALS['config_before_lock'] = function() use ($repairFile, $newerRepair) {
				unset($GLOBALS['config_before_lock']);
				file_put_contents($repairFile, $newerRepair);
			};
			$GLOBALS['weathermap_error_suppress'] = ['WMPOLL13'];
			try { weathermap_repair_maps(); } finally { $GLOBALS['weathermap_error_suppress'] = $oldSuppress; }
			atomic_assert(file_get_contents($repairFile) === $newerRepair, 'repair CAS preserves a concurrent editor save');
		} finally {
			$GLOBALS['config'] = $oldConfig;
			unset($GLOBALS['__test_db_fetch_assoc']);
			unlink($repairFile); unlink($repairImage);
		}
		$stageSource = $plugin . '/legacy-stage-' . bin2hex(random_bytes(4)) . '.png';
		$stageTarget = $plugin . '/images/backgrounds/' . basename($stageSource);
		file_put_contents($stageSource, 'Legacy asset bytes');
		$stageConfig = $plugin . '/configs/' . basename($stageSource) . '.conf';
		$stageLegacy = 'BACKGROUND ' . basename($stageSource) . "\n";
		file_put_contents($stageConfig, $stageLegacy);
		$GLOBALS['__test_db_fetch_assoc'] = fn () => [['configfile' => basename($stageConfig)]];
		$oldConfig = $GLOBALS['config']; $GLOBALS['config']['base_path'] = dirname($plugin, 2);
		$oldSuppress = $GLOBALS['weathermap_error_suppress'] ?? [];
		try {
			// A staged copy succeeds, but the config CAS must fail after a concurrent save.
			$staged = weathermap_stage_asset_copy($stageSource, $stageTarget);
			atomic_assert($staged && is_file($stageSource) && file_get_contents($stageTarget) === 'Legacy asset bytes', 'staging keeps original and complete destination');
			$stageNewer = "TITLE Concurrent repair edit\n" . $stageLegacy;
			$GLOBALS['config_before_lock'] = function() use ($stageConfig, $stageNewer) { unset($GLOBALS['config_before_lock']); file_put_contents($stageConfig, $stageNewer); };
			$GLOBALS['weathermap_error_suppress'] = ['WMPOLL13']; weathermap_repair_maps();
			atomic_assert(file_get_contents($stageConfig) === $stageNewer && is_file($stageSource), 'rejected repair leaves original asset usable');
			$GLOBALS['config_fault'] = 'rename'; weathermap_repair_maps();
			atomic_assert(file_get_contents($stageConfig) === $stageNewer && is_file($stageSource), 'I/O failure leaves original asset usable');
			$GLOBALS['config_fault'] = ''; unlink($stageTarget); file_put_contents($stageConfig, $stageLegacy);
			weathermap_repair_maps();
			atomic_assert(str_contains(file_get_contents($stageConfig), 'images/backgrounds/') && is_file($stageSource) && is_file($stageTarget), 'successful relocation copies rather than removes legacy asset');
		} finally {
			$GLOBALS['config'] = $oldConfig; $GLOBALS['weathermap_error_suppress'] = $oldSuppress; $GLOBALS['config_fault'] = '';
			unset($GLOBALS['__test_db_fetch_assoc'], $GLOBALS['config_before_lock']);
			foreach ([$stageSource, $stageTarget, $stageConfig] as $path) if (is_file($path)) unlink($path);
		}
		// Exercise legacy background and icon relocation in a separate filesystem tree.
		$repairRoot = $dir . '/repair-fixture';
		$repairPlugin = $repairRoot . '/cacti/plugins/weathermap';
		mkdir($repairPlugin . '/configs', 0777, true); mkdir($repairPlugin . '/images');
		symlink($plugin . '/lib', $repairPlugin . '/lib');
		file_put_contents($repairPlugin . '/legacy-background.png', 'Background bytes');
		file_put_contents($repairPlugin . '/legacy-icon.png', 'Icon bytes');
		$legacyFixture = "BACKGROUND legacy-background.png\nNODE Legacy\n ICON legacy-icon.png\n";
		$fixtureConfig = $repairPlugin . '/configs/legacy.conf';
		$oldConfig = $GLOBALS['config']; $oldSuppress = $GLOBALS['weathermap_error_suppress'] ?? [];
		$GLOBALS['config']['base_path'] = $repairRoot . '/cacti';
		$GLOBALS['__test_db_fetch_assoc'] = fn () => [['configfile' => 'legacy.conf']];
		$GLOBALS['weathermap_error_suppress'] = ['WMPOLL09', 'WMPOLL10', 'WMPOLL11', 'WMPOLL12', 'WMPOLL13'];
		try {
			file_put_contents($fixtureConfig, $legacyFixture); weathermap_repair_maps();
			$repairedFixture = file_get_contents($fixtureConfig);
			atomic_assert(str_contains($repairedFixture, 'BACKGROUND images/backgrounds/legacy-background.png') && str_contains($repairedFixture, 'ICON images/objects/legacy-icon.png'), 'repair creates libraries and copies both legacy asset kinds');
			atomic_assert(is_file($repairPlugin . '/legacy-background.png') && is_file($repairPlugin . '/legacy-icon.png'), 'successful repair preserves both original assets');
			file_put_contents($fixtureConfig, $legacyFixture); weathermap_repair_maps();
			atomic_assert(file_get_contents($fixtureConfig) === $repairedFixture, 'existing complete copies support path-only repair');
			weathermap_repair_maps(); atomic_assert(file_get_contents($fixtureConfig) === $repairedFixture, 'correct paths require no rewrite');
			unlink($repairPlugin . '/images/backgrounds/legacy-background.png'); unlink($repairPlugin . '/images/objects/legacy-icon.png');
			file_put_contents($fixtureConfig, $legacyFixture); $GLOBALS['config_fault'] = 'rename'; weathermap_repair_maps();
			atomic_assert(file_get_contents($fixtureConfig) === $legacyFixture && is_file($repairPlugin . '/legacy-background.png') && is_file($repairPlugin . '/legacy-icon.png'), 'failed background and icon staging preserves usable original references');
			$GLOBALS['config_fault'] = ''; file_put_contents($fixtureConfig, "BACKGROUND absent-background.png\nNODE Missing\n ICON absent-icon.png\n");
			$missingFixture = file_get_contents($fixtureConfig); weathermap_repair_maps();
			atomic_assert(file_get_contents($fixtureConfig) === $missingFixture, 'missing assets do not change config paths');
		} finally {
			$GLOBALS['config'] = $oldConfig; $GLOBALS['weathermap_error_suppress'] = $oldSuppress; $GLOBALS['config_fault'] = '';
			unset($GLOBALS['__test_db_fetch_assoc']); chdir($plugin);
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($repairRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($iterator as $entry) { if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname()); }
			rmdir($repairRoot);
		}
		$management = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
		$start = strpos($management, 'function newMap(');
		eval(str_replace(['__DIR__', 'function newMap('], [var_export($plugin, true), 'function wm_management_newMap('], substr($management, $start)));
		$GLOBALS['weathermap_confdir'] = $dir;
		foreach (['', 'map.conf'] as $source) {
			$destination = $dir . '/management-create.conf';
			$GLOBALS['config_before_lock'] = function() use ($destination) { file_put_contents($destination, 'Concurrent creator'); unset($GLOBALS['config_before_lock']); };
			wm_management_newMap('management-create.conf', $source);
			atomic_assert(file_get_contents($destination) === 'Concurrent creator', 'management create-only preserves concurrent destination');
			unlink($destination);
		}
		$start = strpos($management, 'function map_get_next_name('); $end = strpos($management, "\n/**", $start);
		eval(str_replace('__DIR__', var_export($plugin, true), substr($management, $start, $end - $start)));
		$start = strpos($management, 'function map_duplicate('); $end = strpos($management, "\n/**", $start);
		eval(str_replace('__DIR__', var_export($plugin, true), substr($management, $start, $end - $start)));
		$duplicateSource = 'atomic-duplicate-' . bin2hex(random_bytes(4)) . '.conf';
		file_put_contents($plugin . '/configs/' . $duplicateSource, "TITLE Source\n");
		$GLOBALS['__test_db_fetch_row_prepared'] = fn () => ['configfile' => $duplicateSource, 'titlecache' => 'Source', 'group_id' => 1, 'active' => 'on', 'thumb_height' => 10, 'thumb_width' => 10, 'schedule' => '', 'archiving' => '',];
		$GLOBALS['__test_db_calls'] = []; $GLOBALS['atomic_sql_saves'] = [];
		$GLOBALS['__test_db_fetch_cell'] = fn () => 0;
		$GLOBALS['__test_sql_save'] = function($record) { $GLOBALS['atomic_sql_saves'][] = $record; return 1; }; $GLOBALS['config_fault'] = 'rename';
		try {
			map_duplicate(1, 'Clone', 'clone');
			atomic_assert($GLOBALS['__test_db_calls'] === [] && $GLOBALS['atomic_sql_saves'] === [], 'failed clone never registers auth or settings or invokes runner');
		} finally { unset($GLOBALS['__test_db_fetch_row_prepared'], $GLOBALS['__test_sql_save'], $GLOBALS['__test_db_fetch_cell']); unlink($plugin . '/configs/' . $duplicateSource); $GLOBALS['config_fault'] = ''; }
		// Real raw node/link saves must not re-check their own pre-save page token.
		$rawFile = $dir . '/raw.conf';
		$rawConfig = "WIDTH 300\nHEIGHT 200\nNODE A\n LABEL A\n POSITION 10 10\nNODE B\n LABEL B\n POSITION 50 50\nLINK AB\n NODES A B\n";
		foreach (['node', 'link'] as $kind) {
			file_put_contents($rawFile, $rawConfig);
			$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash_file('sha256', $rawFile), 'node_name' => 'A', 'link_name' => 'AB', 'item_configtext' => $kind === 'node' ? "NODE A\n LABEL Updated\n POSITION 10 10\n" : "LINK AB\n NODES A B\n WIDTH 12\n"];
			http_response_code(200); if ($kind === 'node') setNodeConfig($rawFile); else setLinkConfig($rawFile);
			atomic_assert(http_response_code() === 200 && file_get_contents($rawFile) !== $rawConfig, 'raw ' . $kind . ' edit saves without false conflict');
			file_put_contents($rawFile, $rawConfig); http_response_code(200);
			$controller = file_get_contents($plugin . '/weathermap-cacti-plugin-editor.php');
			$caseStart = strpos($controller, "case 'set_" . $kind . "_config':");
			$caseEnd = strpos($controller, "case '", $caseStart + 5);
			$dispatch = substr($controller, strpos($controller, ':', $caseStart) + 1, $caseEnd - strpos($controller, ':', $caseStart) - 1);
			$mapfile = $rawFile; $terminated = false;
			try { eval(str_replace('exit();', 'throw new \\RuntimeException("request-finished");', $dispatch)); }
			catch (\RuntimeException $error) { if ($error->getMessage() !== 'request-finished') throw $error; $terminated = true; }
			atomic_assert($terminated && http_response_code() === 200 && file_get_contents($rawFile) !== $rawConfig, 'controller ends successful raw ' . $kind . ' save before rendering with old token');

		}
		// Registration failure must retain a mutex through the callback and remove its reservation.
		file_put_contents($plugin . '/configs/' . $duplicateSource, "TITLE Source\n");
		$GLOBALS['__test_db_fetch_row_prepared'] = fn () => ['configfile' => $duplicateSource, 'titlecache' => 'Source', 'group_id' => 1, 'active' => 'on', 'thumb_height' => 10, 'thumb_width' => 10, 'schedule' => '', 'archiving' => ''];
		$GLOBALS['__test_db_fetch_cell'] = fn () => 0;
		$GLOBALS['weathermap_confdir'] = $plugin . '/configs';
		try {
			foreach (['false', 'throw', 'auth-false', 'settings-false', 'auth-throw', 'settings-throw'] as $failure) {
				$GLOBALS['__test_sql_save'] = function($record) use ($plugin, $failure) {
					atomic_assert(is_file($plugin . '/configs/' . $record['configfile']), 'reserved file exists during registration');
					if (function_exists('pcntl_fork')) {
						$child = pcntl_fork();
						if ($child === 0) { $probe = \fopen($plugin . '/configs/.weathermap-config.lock', 'rb'); exit(!\flock($probe, LOCK_EX | LOCK_NB) ? 0 : 1); }
						pcntl_waitpid($child, $status); atomic_assert(pcntl_wexitstatus($status) === 0, 'registration retains directory mutex');
					}
					if ($failure === 'throw') throw new RuntimeException('registration failed');
					return $failure === 'false' ? false : 987;
				};
				$GLOBALS['__test_db_calls'] = [];
				$GLOBALS['__test_db_execute_prepared'] = function($sql, $params) use ($failure) {
					$GLOBALS['__test_db_calls'][] = ['sql' => $sql, 'params' => $params];
					if (str_starts_with($sql,'INSERT')) atomic_assert(!str_contains($sql, '987') && $params === [987,1], 'clone IDs are bound as prepared parameters');
					if ((str_contains($sql, 'INSERT INTO weathermap_auth') && $failure === 'auth-throw') || (str_contains($sql, 'INSERT INTO weathermap_settings') && $failure === 'settings-throw')) throw new RuntimeException('insert failed');
					return !((str_contains($sql, 'INSERT INTO weathermap_auth') && $failure === 'auth-false') || (str_contains($sql, 'INSERT INTO weathermap_settings') && $failure === 'settings-false'));
				};
				map_duplicate(1, 'Clone', 'clone');
				atomic_assert(!is_file($plugin . '/configs/clone.conf'), 'failed registration removes its own reserved configuration');
				if (str_ends_with($failure, '-false') || str_ends_with($failure, '-throw')) atomic_assert(cacti_count(array_filter($GLOBALS['__test_db_calls'], fn ($call) => str_starts_with($call['sql'], 'DELETE'))) === 3, 'partial registration removes map/auth/settings records');
			}
		} finally { unset($GLOBALS['__test_db_fetch_row_prepared'], $GLOBALS['__test_sql_save'], $GLOBALS['__test_db_fetch_cell'], $GLOBALS['__test_db_execute_prepared']); unlink($plugin . '/configs/' . $duplicateSource); }
		$deletedMap = new \WeatherMap; $deletedMap->ReadConfig($file);
		unlink($file);
		atomic_assert(!$deletedMap->WriteConfig($file) && !file_exists($file), 'saving a previously loaded deleted map fails without resurrection');
		file_put_contents($file, $original);
		$GLOBALS['config_fault'] = 'rename';
		$map = new \WeatherMap; $map->ReadConfig($file); $map->title = 'Changed';
		atomic_assert(!$map->WriteConfig($file) && file_get_contents($file) === $original, 'engine failure preserves original');
		$GLOBALS['__test_nfilter_request'] = ['map_title' => 'Failed title', 'map_legend' => 'Traffic', 'map_stamp' => 'Created', 'map_width' => 600, 'map_height' => 300, 'map_bgfile' => '--NONE--', 'map_linkdefaultwidth' => 7, 'map_linkdefaultbwin' => '100M', 'map_linkdefaultbwout' => '100M'];
		$GLOBALS['__test_db_calls'] = [];
		http_response_code(200); setMapProperties($file);
		atomic_assert(http_response_code() === 500 && $GLOBALS['__test_db_calls'] === [], 'failed properties save cannot change titlecache');
		$GLOBALS['config_fault'] = ''; http_response_code(200); setMapProperties($file);
		atomic_assert(http_response_code() === 200 && cacti_count($GLOBALS['__test_db_calls']) === 1, 'successful properties save updates titlecache');
		file_put_contents($file, $original); $GLOBALS['config_fault'] = 'rename';
		$GLOBALS['__test_nfilter_request'] = ['node_name' => 'note1', 'param' => 'note1', 'x' => '70', 'y' => '80'];
		{
			http_response_code(200); moveNode($file, 0);
			atomic_assert(http_response_code() === 500 && file_get_contents($file) === $original, 'failed move surfaces error without changing file');
			http_response_code(200); deleteNode($file);
			atomic_assert(http_response_code() === 500 && file_get_contents($file) === $original, 'failed delete surfaces error without changing file');
		}
		$GLOBALS['config_fault'] = '';
		$GLOBALS['__test_nfilter_request'] = ['node_name' => 'missing', 'param' => 'missing', 'x' => '70', 'y' => '80'];
		http_response_code(200); moveNode($file, 0);
		atomic_assert(http_response_code() === 404 && file_get_contents($file) === $original, 'missing move target is an error');
		http_response_code(200); deleteNode($file);
		atomic_assert(http_response_code() === 404 && file_get_contents($file) === $original, 'missing delete target is an error');
		unlink($file); http_response_code(200); moveNode($file, 0);
		atomic_assert(http_response_code() === 404 && !file_exists($file), 'deleted map cannot report a successful edit');
		echo "Atomic configuration write and locking regression PASS\n";
	} finally {
		$GLOBALS['config_fault'] = '';
		foreach (glob($dir . '/*') as $path) unlink($path);
		foreach (glob($dir . '/.wm-config-*') as $path) unlink($path);
		if (is_file($dir . '/.weathermap-config.lock')) unlink($dir . '/.weathermap-config.lock');
		rmdir($dir);
	}
}
