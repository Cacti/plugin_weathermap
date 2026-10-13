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
	require dirname(__DIR__) . '/bootstrap-unit.php';
	$plugin = dirname(__DIR__, 2);
	chdir($plugin);
	require $plugin . '/setup.php';
	require $plugin . '/lib/WeatherMap.class.php';
	require $plugin . '/lib/WeatherMap.config.php';
	require $plugin . '/lib/editor.inc.php';
	require $plugin . '/lib/editor.colours.php';
	require $plugin . '/lib/editor.actions.php';

	/** Assert a persistence or concurrent endpoint result in this isolated process. */
	function colour_atomic_assert($condition, $message) {
		if (!$condition) throw new \RuntimeException($message);
	}
	$dir = sys_get_temp_dir() . '/wm-colour-atomic-' . bin2hex(random_bytes(5));
	mkdir($dir);
	$file = $dir . '/map.conf';
	$initial = "WIDTH 600\nHEIGHT 300\nNODE A\n POSITION 10 10\nNODE B\n POSITION 50 50\nLINK AB\n NODES A B\n";
	file_put_contents($file, $initial);
	try {
		foreach (array_filter(['lock-open', 'lock', 'temp-name', 'temp-open', 'write-false', 'write-zero', 'flush', 'close', 'group', 'mode', 'rename'], fn ($fault) => PHP_OS_FAMILY !== 'Windows' || $fault !== 'group') as $fault) {
			$GLOBALS['config_fault'] = $fault;
			colour_atomic_assert(!\WeathermapConfig\atomicWrite($file, 'Replacement'), 'I/O failure reported');
			colour_atomic_assert(file_get_contents($file) === $initial, 'I/O failure retains original');
		}
		$GLOBALS['config_fault'] = 'short-write';
		colour_atomic_assert(\WeathermapConfig\atomicWrite($file, $initial), 'partial chunks completed');
		$GLOBALS['config_fault'] = PHP_OS_FAMILY === 'Windows' ? '' : 'lock-read';
		colour_atomic_assert(\WeathermapConfig\atomicWrite($file, $initial), 'read-only lock stream supported');
		$GLOBALS['config_fault'] = '';
		colour_atomic_assert(!\WeathermapConfig\atomicWrite('invalid://map', 'x'), 'unsupported stream rejected');
		colour_atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/missing/map', 'x'), 'missing destination directory rejected');
		colour_atomic_assert(!\WeathermapConfig\atomicWrite($dir, 'x'), 'directory target rejected');
		colour_atomic_assert(!\WeathermapConfig\acquire($dir . '/missing'), 'missing lock directory rejected');
		colour_atomic_assert(!\WeathermapConfig\atomicWrite($dir . '/absent.conf', 'x', true), 'deleted map not recreated');
		colour_atomic_assert(!\WeathermapConfig\atomicWrite($file, 'x', false, null, true), 'create-only existing file rejected');
		$link = $dir . '/link.conf';
		if (@symlink($file, $link)) {
			colour_atomic_assert(\WeathermapConfig\atomicWrite($link, $initial), 'symlink target maintained');
			unlink($link);
		}
		$wrong = \fopen($file, 'rb');
		colour_atomic_assert(!\WeathermapConfig\atomicWrite($file, $initial, true, null, false, $wrong), 'unrelated held mutex rejected');
		\fclose($wrong);
		$templates = $dir . '/templates.conf';
		file_put_contents($templates, "NODE templateNode\n LABEL Template\nNODE A\n POSITION 10 10\nNODE B\n POSITION 50 50\nLINK templateLink\n WIDTH 8\nLINK trafficLink\n NODES A B\n");
		$templateMap = new \WeatherMap;
		$templateMap->ReadConfig($templates);
		colour_atomic_assert($templateMap->WriteConfig($templates), 'atomic save serializes template nodes and links');
		$templateReload = new \WeatherMap;
		$templateReload->ReadConfig($templates);
		colour_atomic_assert(isset($templateReload->nodes['templateNode'], $templateReload->links['templateLink'], $templateReload->links['trafficLink']), 'templates and traffic objects survive atomic persistence');
		$first = new \WeatherMap;
		$second = new \WeatherMap;
		$first->ReadConfig($file);
		$second->ReadConfig($file);
		$first->title = 'Winner';
		$second->title = 'Stale';
		colour_atomic_assert($first->WriteConfig($file), 'engine atomic write');
		$winner = file_get_contents($file);
		colour_atomic_assert(!$second->WriteConfig($file) && file_get_contents($file) === $winner, 'engine CAS rejects stale readers');
		$map = new \WeatherMap;
		$map->ReadConfig($file);
		$data = wm_asset_colour_data($map);
		$revision = hash_file('sha256', $file);
		$_SERVER['REQUEST_METHOD'] = 'POST';
		if (function_exists('pcntl_fork')) {
			$lock = \WeathermapConfig\acquire($dir);
			$children = [];
			for ($i = 0; $i < 2; $i++) {
				$child = pcntl_fork();
				if ($child === 0) {
					\fclose($lock);
					$GLOBALS['config_before_lock'] = function () use ($dir, $i) {
						file_put_contents($dir . '/ready-' . $i, 'ready');
					};
					$payload = $data;
					$payload['outline'] = $i === 0 ? '#0000ff' : '#cc0000';
					$GLOBALS['__test_nfilter_request'] = ['config_revision' => $revision, 'colours_json' => json_encode($payload)];
					http_response_code(200);
					ob_start();
					wm_editor_colours($file, 'set_map_colours');
					$response = json_decode(ob_get_clean(), true);
					file_put_contents($dir . '/result-' . $i, json_encode(['status' => http_response_code(), 'response' => $response]));
					exit(0);
				}
				$children[] = $child;
			}
			for ($wait = 0; $wait < 200 && cacti_count(glob($dir . '/ready-*')) < 2; $wait++) usleep(10000);
			\WeathermapConfig\release($lock);
			foreach ($children as $child) pcntl_waitpid($child, $status);
			$results = [json_decode(file_get_contents($dir . '/result-0'), true), json_decode(file_get_contents($dir . '/result-1'), true)];
			$statuses = array_column($results, 'status');
			sort($statuses);
			colour_atomic_assert($statuses === [200, 409], 'two concurrent colour requests produce exactly one save and one stale response');
			$saved = new \WeatherMap;
			$saved->ReadConfig($file);
			$outline = wm_asset_colour_data($saved)['outline'];
			$winning = $results[0]['status'] === 200 ? 0 : 1;
			colour_atomic_assert($outline === ($winning === 0 ? '#0000ff' : '#cc0000'), 'second request cannot overwrite winning colour');
		} else {
			$GLOBALS['__test_nfilter_request'] = ['config_revision' => $revision, 'colours_json' => json_encode($data)];
			ob_start();
			wm_editor_colours($file, 'set_map_colours');
			ob_end_clean();
			colour_atomic_assert(http_response_code() === 200, 'locked endpoint succeeds');
		}

		// Real raw node/link saves must not re-check their own pre-save page token.
		$rawFile = $dir . '/raw.conf';
		$rawConfig = "WIDTH 300\nHEIGHT 200\nNODE A\n LABEL A\n POSITION 10 10\nNODE B\n LABEL B\n POSITION 50 50\nLINK AB\n NODES A B\n";
		foreach (['node', 'link'] as $kind) {
			file_put_contents($rawFile, $rawConfig);
			$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash_file('sha256', $rawFile), 'node_name' => 'A', 'link_name' => 'AB', 'item_configtext' => $kind === 'node' ? "NODE A\n LABEL Updated\n POSITION 10 10\n" : "LINK AB\n NODES A B\n WIDTH 12\n"];
			http_response_code(200);
			if ($kind === 'node') setNodeConfig($rawFile);
			else setLinkConfig($rawFile);
			colour_atomic_assert(http_response_code() === 200 && file_get_contents($rawFile) !== $rawConfig, 'raw ' . $kind . ' edit saves without false conflict');
			file_put_contents($rawFile, $rawConfig);
			http_response_code(200);
			$controller = file_get_contents($plugin . '/weathermap-cacti-plugin-editor.php');
			$caseStart = strpos($controller, "case 'set_" . $kind . "_config':");
			$caseEnd = strpos($controller, "case '", $caseStart + 5);
			$dispatch = substr($controller, strpos($controller, ':', $caseStart) + 1, $caseEnd - strpos($controller, ':', $caseStart) - 1);
			$mapfile = $rawFile;
			$terminated = false;
			try {
				eval(str_replace('exit();', 'throw new \\RuntimeException("request-finished");', $dispatch));
			} catch (\RuntimeException $error) {
				if ($error->getMessage() !== 'request-finished') throw $error;
				$terminated = true;
			}
			colour_atomic_assert($terminated && http_response_code() === 200 && file_get_contents($rawFile) !== $rawConfig, 'controller ends successful raw ' . $kind . ' save before rendering with old token');
		}
		$GLOBALS['__test_nfilter_request'] = ['map_background_colour' => '#bad'];
		$beforeInvalid = file_get_contents($file);
		http_response_code(200);
		setMapProperties($file);
		colour_atomic_assert(http_response_code() === 400 && file_get_contents($file) === $beforeInvalid, 'invalid background is a client error without persistence');
		$management = file_get_contents($plugin . '/weathermap-cacti-plugin-mgmt.php');
		eval(str_replace(['__DIR__', 'function newMap('], [var_export($plugin, true), 'function colourManagementCreate('], substr($management, strpos($management, 'function newMap('))));
		$GLOBALS['weathermap_confdir'] = $dir;
		$messages = [];
		$GLOBALS['__test_raise_message'] = function ($id, $text, $level) use (&$messages) {
			$messages[] = [$text, $level];
		};
		$GLOBALS['config_fault'] = 'rename';
		colourManagementCreate('failed-create.conf');
		colour_atomic_assert(!is_file($dir . '/failed-create.conf') && end($messages)[1] === MESSAGE_LEVEL_ERROR, 'management reports failed persistence instead of creation success');
		$GLOBALS['config_fault'] = '';
		unset($GLOBALS['__test_raise_message']);

		// Run the actual CLI programs in an isolated Cacti tree with stubbed services.
		$cliRoot = $dir . '/cacti';
		mkdir($cliRoot);
		mkdir($cliRoot . '/include');
		mkdir($cliRoot . '/plugins');
		mkdir($cliRoot . '/plugins/weathermap');
		mkdir($cliRoot . '/plugins/weathermap/cli');
		symlink($plugin . '/lib', $cliRoot . '/plugins/weathermap/lib');
		foreach (['map-tidyup.php', 'cacti-integrate.php', 'convert-to-dsstats.php'] as $cli) copy($plugin . '/cli/' . $cli, $cliRoot . '/plugins/weathermap/cli/' . $cli);
		$bootstrap = '<?php require ' . var_export($plugin . '/tests/bootstrap-unit.php', true) . '; require ' . var_export($plugin . '/setup.php', true) . '; $config["base_path"] = ' . var_export($cliRoot, true) . ';';
		file_put_contents($cliRoot . '/include/cli_check.php', $bootstrap);
		$prepend = $dir . '/coverage-prepend.php';
		file_put_contents($prepend, '<?php if (function_exists("xdebug_start_code_coverage")) { xdebug_start_code_coverage(); register_shutdown_function(function () { file_put_contents(getenv("WM_CLI_COVERAGE_FILE"), json_encode(xdebug_get_code_coverage())); }); }');
		file_put_contents($dir . '/cli-input.conf', "WIDTH 100\nHEIGHT 100\n");
		$GLOBALS['colour_cli_coverage'] = [];
		foreach (['map-tidyup.php', 'cacti-integrate.php', 'convert-to-dsstats.php'] as $cli) {
			$coverageFile = $dir . '/' . $cli . '.json';
			putenv('WM_CLI_COVERAGE_FILE=' . $coverageFile);
			$command = escapeshellarg(PHP_BINARY) . ' -d auto_prepend_file=' . escapeshellarg($prepend) . ' ' . escapeshellarg($cliRoot . '/plugins/weathermap/cli/' . $cli) . ' --input=' . escapeshellarg($dir . '/cli-input.conf') . ' --output=' . escapeshellarg($dir . '/missing/output.conf') . ' 2>&1';
			chdir($cliRoot . '/plugins/weathermap/cli');
			$cliOutput = [];
			exec($command, $cliOutput, $exitCode);
			chdir($plugin);
			colour_atomic_assert($exitCode === 1 && !str_contains(implode("\n", $cliOutput), 'Wrote') && str_contains(implode("\n", $cliOutput), 'Could not write configuration'), 'actual CLI reports failed persistence: ' . $cli . ' ' . implode("\n", $cliOutput));
			if (is_file($coverageFile)) {
				$raw = json_decode(file_get_contents($coverageFile), true);
				$raw[$plugin . '/cli/' . $cli] = $raw[$cliRoot . '/plugins/weathermap/cli/' . $cli] ?? [];
				unset($raw[$cliRoot . '/plugins/weathermap/cli/' . $cli]);
				$GLOBALS['colour_cli_coverage'][] = $raw;
			}
		}
		foreach (glob($cliRoot . '/plugins/weathermap/cli/*') as $cliFile) unlink($cliFile);
		rmdir($cliRoot . '/plugins/weathermap/cli');
		unlink($cliRoot . '/plugins/weathermap/lib');
		rmdir($cliRoot . '/plugins/weathermap');
		rmdir($cliRoot . '/plugins');
		unlink($cliRoot . '/include/cli_check.php');
		rmdir($cliRoot . '/include');
		rmdir($cliRoot);

		// Normal editor mutations also expose persistence conflicts and suppress title-cache side effects.
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash_file('sha256', $file), 'map_title' => 'Attempted'];
		$GLOBALS['__test_db_calls'] = [];
		$GLOBALS['config_before_lock'] = function () use ($file) {
			file_put_contents($file, "TITLE Concurrent\n");
			unset($GLOBALS['config_before_lock']);
		};
		http_response_code(200);
		setMapProperties($file);
		colour_atomic_assert(http_response_code() === 409 && file_get_contents($file) === "TITLE Concurrent\n" && $GLOBALS['__test_db_calls'] === [], 'normal editor CAS conflict is visible and does not update title cache');
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => hash_file('sha256', $file), 'colours_json' => json_encode($data)];
		foreach (['lock', 'rename'] as $fault) {
			$GLOBALS['config_fault'] = $fault;
			http_response_code(200);
			ob_start();
			wm_editor_colours($file, 'set_map_colours');
			ob_end_clean();
			colour_atomic_assert(http_response_code() === 500, 'operational endpoint failures return server error');
		}
		$GLOBALS['config_fault'] = '';
		$GLOBALS['__test_nfilter_request']['colours_json'] = 'invalid';
		http_response_code(200);
		ob_start();
		wm_editor_colours($file, 'set_map_colours');
		ob_end_clean();
		colour_atomic_assert(http_response_code() === 400, 'invalid payload remains client error');

		$includeFile = $dir . '/included.conf'; $includeRoot = $dir . '/include-root.conf';
		file_put_contents($includeFile, "TITLE Included old\n");
		$includeConfig = "WIDTH 600\nHEIGHT 300\nINCLUDE " . $includeFile . "\n";
		file_put_contents($includeRoot, $includeConfig);
		$dependent = new \WeatherMap; $dependent->ReadConfig($includeRoot);
		$token = \WeathermapConfig\snapshotRevision($dependent->config_revision, $dependent->config_dependencies);
		file_put_contents($includeFile, "TITLE Included winner\n"); $dependent->title = 'Stale edit';
		colour_atomic_assert(!$dependent->WriteConfig($includeRoot) && file_get_contents($includeRoot) === $includeConfig, 'included file changes reject stale root persistence');
		$GLOBALS['__test_nfilter_request'] = ['config_revision' => $token]; http_response_code(200);
		colour_atomic_assert(!wm_editor_read_config(new \WeatherMap, $includeRoot) && http_response_code() === 409, 'included-file changes invalidate loaded editor page token');
		$GLOBALS['__test_nfilter_request'] = []; $fresh = new \WeatherMap; $fresh->ReadConfig($includeRoot);
		colour_atomic_assert($fresh->WriteConfig($includeRoot), 'unchanged included dependency can be saved');
		colour_atomic_assert(!\WeathermapConfig\dependenciesMatch([$includeFile => null]), 'unread included dependency rejected');
		$outside = $dir . '/dependency-directory'; mkdir($outside); $outsideFile = $outside . '/outside.conf'; file_put_contents($outsideFile, "TITLE Outside\n");
		file_put_contents($includeRoot, "WIDTH 600\nHEIGHT 300\nINCLUDE " . $outsideFile . "\n");
		$externalMap = new \WeatherMap; $externalMap->ReadConfig($includeRoot); $busy = \WeathermapConfig\acquire($outside);
		colour_atomic_assert(!$externalMap->WriteConfig($includeRoot), 'busy dependency directory rejects save without deadlock');
		\WeathermapConfig\release($busy);
		colour_atomic_assert($externalMap->WriteConfig($includeRoot), 'external dependency save succeeds after mutex release');
		unlink($outsideFile); unlink($outside . '/.weathermap-config.lock'); rmdir($outside);

		class UnreadColourMap extends \WeatherMap { function ReadConfig($input, $is_include = false) { $this->config_revision = null; return true; } }
		$actions = file_get_contents($plugin . '/lib/editor.actions.php'); $start = strpos($actions, 'function wm_editor_colours('); $end = strpos($actions, "\n/**", $start); if ($end === false) $end = strlen($actions);
		eval(str_replace(['__DIR__', 'function wm_editor_colours(', 'new WeatherMap'], [var_export($plugin . '/lib', true), 'function unreadColourEndpoint(', 'new UnreadColourMap'], substr($actions, $start, $end - $start)));
		$GLOBALS['__test_nfilter_request'] = []; http_response_code(200); ob_start(); unreadColourEndpoint($file, 'colour_data'); $error = json_decode(ob_get_clean(), true);
		colour_atomic_assert(http_response_code() === 500 && isset($error['error']) && !isset($error['colours']), 'unread colour config returns an error instead of default colours');
		$probe = \WeathermapConfig\acquire($dir, false);
		colour_atomic_assert(is_resource($probe), 'endpoint always releases mutex');
		\WeathermapConfig\release($probe);
		echo "Colour concurrent configuration regression PASS\n";
	} finally {
		$GLOBALS['config_fault'] = '';
		foreach (glob($dir . '/*') as $path) unlink($path);
		foreach (glob($dir . '/.wm-config-*') as $path) unlink($path);
		if (is_file($dir . '/.weathermap-config.lock')) unlink($dir . '/.weathermap-config.lock');
		rmdir($dir);
	}
}
