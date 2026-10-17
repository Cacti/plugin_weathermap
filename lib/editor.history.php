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

namespace WeathermapEditorHistory;

require_once __DIR__ . '/WeatherMap.config.php';

/**
 * Use private temporary storage, outside the web root. Cacti's database-backed
 * sessions do not lock concurrent requests; storing snapshots there would let
 * an older graph request overwrite a newly recorded action.
 */
function storagePath() {
	if (session_id() === '') throw new \RuntimeException(\__('An active session is required for Undo and Redo.', 'weathermap'));
	$directory = sys_get_temp_dir() . '/weathermap-history-' . hash('sha256', __DIR__);
	if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) throw new \RuntimeException(\__('Could not create private map history storage.', 'weathermap'));
	if (is_link($directory) || (fileperms($directory) & 0077) !== 0 || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) throw new \RuntimeException(\__('Map history storage is not private.', 'weathermap'));
	return $directory . '/' . hash('sha256', session_id()) . '.json';
}

/** Load under the map directory lock, never from a stale PHP session copy. */
function load() {
	$file = storagePath();
	if (is_link($file)) throw new \RuntimeException(\__('Invalid map history storage.', 'weathermap'));
	$data = is_file($file) ? @file_get_contents($file) : '{}';
	$history = is_string($data) ? json_decode($data, true) : null;
	if (!is_array($history)) $history = []; // A damaged history must not block normal map editing.
	$GLOBALS['weathermap_editor_history'] = $history;
}

/** Atomically save private history; callers already hold the map directory lock. */
function persist() {
	$file = storagePath();
	$lock = \WeathermapConfig\acquire(dirname($file));
	if (!$lock) return false;
	$temp = false;
	try {
		foreach (glob(dirname($file) . '/*.json') as $old) {
			if ($old !== $file && filemtime($old) < time() - 14400) unlink($old);
		}
		$temp = @tempnam(dirname($file), '.history-');
		if ($temp === false) return false;
		$data = json_encode($GLOBALS['weathermap_editor_history'], JSON_THROW_ON_ERROR);
		$stream = @fopen($temp, 'wb');
		if (!$stream) return false;
		$written = \WeathermapConfig\writeAll($stream, $data) && fflush($stream);
		$closed = fclose($stream);
		return $written && $closed && @rename($temp, $file);
	} finally {
		if ($temp !== false && is_file($temp)) unlink($temp);
		\WeathermapConfig\release($lock);
	}
}

/** History persistence must not report an already committed map save as failed. */
function persistAfterSave() {
	try { $saved = persist(); } catch (\Throwable $error) { $saved = false; }
	if (!$saved && function_exists('cacti_log')) \cacti_log('Unable to persist Weathermap editor history.', false, 'WEATHERMAP');
}

/** Identify one browser tab and map without accepting client-supplied snapshots. */
function key($mapfile) {
	$id = \get_nfilter_request_var('history_id');
	return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id)
		? hash('sha256', \WeathermapConfig\logicalPath($mapfile) . "\0" . $id) : null;
}

/** Keep session storage bounded and discard abandoned editing histories. */
function prune() {
	$histories = $GLOBALS['weathermap_editor_history'] ?? [];
	foreach ($histories as $key => $entry) {
		if ($entry['time'] < time() - 14400 || !hasAssetMetadata($entry)) unset($histories[$key]);
	}
	while (count($histories) > 8 || strlen(json_encode($histories, JSON_THROW_ON_ERROR)) > 8 * 1024 * 1024) array_shift($histories);
	$GLOBALS['weathermap_editor_history'] = $histories;
}

/** Older snapshots cannot prove which assets their INCLUDE files reintroduce. */
function hasAssetMetadata($entry) {
	foreach (array_merge($entry['undo'] ?? [], $entry['redo'] ?? []) as $step) {
		if (!is_array($step['snapshot']['assets'] ?? null)) return false;
	}
	return true;
}

/** Read exact root bytes and bind them to the loaded includes and path identities. */
function snapshot($map, $mapfile) {
	$contents = @file_get_contents($mapfile);
	if (!is_string($contents) || hash('sha256', $contents) !== $map->config_revision) return null;
	return [
		'contents' => base64_encode($contents),
		'root' => $map->config_revision,
		// This is the complete asset list captured at ReadConfig(), before edits.
		'assets' => $map->config_managed_assets,
		'dependencies' => $map->config_dependencies,
		'identity' => $map->config_root_identity,
		'revision' => \WeathermapConfig\snapshotRevision($map->config_revision, $map->config_dependencies, $map->config_root_identity)
	];
}

/** Describe a completed map action in toolbar tooltips. */
function actionLabel() {
	$labels = [
		'add_node' => \__('Add node', 'weathermap'), 'clone_node' => \__('Clone node', 'weathermap'),
		'delete_node' => \__('Delete node', 'weathermap'), 'move_node' => \__('Move node or note', 'weathermap'),
		'add_link2' => \__('Add link', 'weathermap'), 'delete_link' => \__('Delete link', 'weathermap'),
		'via_link' => \__('Move link via point', 'weathermap'), 'link_tidy' => \__('Tidy link', 'weathermap'),
		'retidy' => \__('Tidy links', 'weathermap'), 'retidy_all' => \__('Tidy all links', 'weathermap'),
		'untidy' => \__('Untidy links', 'weathermap'), 'set_note_properties' => \__('Edit note', 'weathermap'),
		'set_node_properties' => \__('Edit node', 'weathermap'), 'set_link_properties' => \__('Edit link', 'weathermap'),
		'set_node_config' => \__('Edit raw node configuration', 'weathermap'), 'set_link_config' => \__('Edit raw link configuration', 'weathermap'),
		'set_map_properties' => \__('Edit map properties', 'weathermap'), 'set_map_style' => \__('Edit map style', 'weathermap'),
		'set_map_colours' => \__('Edit map colours', 'weathermap'), 'place_legend' => \__('Move legend', 'weathermap'),
		'place_stamp' => \__('Move timestamp', 'weathermap')
	];
	return $labels[\get_nfilter_request_var('action')] ?? \__('Edit map', 'weathermap');
}

/** Record only successful changes; a new action discards the redo branch. */
function remember($key, $before, $after) {
	prune();
	if ($before['revision'] === $after['revision']) return;
	$entry = $GLOBALS['weathermap_editor_history'][$key] ?? [];
	if (($entry['current'] ?? null) !== $before['revision']) $entry = ['undo' => [], 'redo' => []];
	$entry['undo'][] = ['snapshot' => $before, 'label' => actionLabel()];
	$entry['undo'] = array_slice($entry['undo'], -2);
	$entry['redo'] = [];
	$entry['current'] = $after['revision'];
	$entry['time'] = time();
	// Reinsert so pruning removes the least recently changed map first.
	unset($GLOBALS['weathermap_editor_history'][$key]);
	$GLOBALS['weathermap_editor_history'][$key] = $entry;
	prune();
}

/** Compare structured output before rewriting hand-formatted root bytes. */
function unchanged($map, $mapfile, $expectedRevision) {
	$loaded = new \WeatherMap;
	$loaded->context = 'editor';
	if (!$loaded->ReadConfig($mapfile)
		|| ($expectedRevision !== null && $expectedRevision !== $loaded->config_revision)
		|| !\WeathermapConfig\dependenciesMatch(array_merge($map->config_dependencies, $map->config_root_identity))) return false;
	$action = \get_nfilter_request_var('action');
	if ($action === 'set_map_colours') return effectiveColours($loaded) === effectiveColours($map);
	if (in_array($action, ['set_node_config', 'set_link_config'], true)) return unchangedRaw($map, $loaded, $action);
	$original = $loaded->ConfigText();
	return is_string($original) && $original === $map->ConfigText();
}

/** Compare effective settings; numeric scale bounds are the only coerced values. */
function effectiveColours($map) {
	require_once __DIR__ . '/editor.colours.php';
	$data = \wm_asset_colour_data($map);
	foreach ($data['rows'] as &$row) {
		$row['bottom'] = (float) $row['bottom'];
		$row['top'] = (float) $row['top'];
	}
	unset($row);
	return $data;
}

/** Compare exactly what fetchConfig() displayed, allowing only transport newlines. */
function unchangedRaw($map, $loaded, $action) {
	$isNode = $action === 'set_node_config';
	$name = \get_nfilter_request_var($isNode ? 'node_name' : 'link_name');
	$items = $isNode ? 'nodes' : 'links';
	$original = $loaded->{$items}[$name] ?? null;
	$edited = $map->{$items}[$name] ?? null;
	if ($original === null || $edited === null || !is_string($edited->config_override)) return false;
	$normalize = static fn($text) => str_replace(["\r\n", "\r"], "\n", $text);
	return $normalize($original->WriteConfig()) === $normalize($edited->config_override);
}

/** Capture history under the same directory lock as the normal atomic save. */
function write($map, $mapfile, $createOnly, $expectedRevision, $heldLock = null) {
	$key = key($mapfile);
	if ($key === null || $createOnly) return $map->WriteConfig($mapfile, $createOnly, $expectedRevision, $heldLock);
	$lock = $heldLock ?? \WeathermapConfig\acquire(dirname($mapfile));
	if (!$lock) return false;
	try {
		try { load(); } catch (\Throwable $error) {
			if (function_exists('cacti_log')) \cacti_log('Weathermap Undo/Redo storage is unavailable; saving without history.', false, 'WEATHERMAP');
			return $map->WriteConfig($mapfile, false, $expectedRevision, $lock);
		}
		$before = snapshot($map, $mapfile);
		if ($before !== null && unchanged($map, $mapfile, $expectedRevision)) return true;
		if (!$map->WriteConfig($mapfile, false, $expectedRevision, $lock)) return false;
		// Raw configuration edits may change INCLUDE dependencies. Read the saved result.
		$saved = new \WeatherMap;
		$saved->context = 'editor';
		$after = $saved->ReadConfig($mapfile) ? snapshot($saved, $mapfile) : null;
		if ($before !== null && $after !== null) remember($key, $before, $after);
		else unset($GLOBALS['weathermap_editor_history'][$key]);
		persistAfterSave();
		return true;
	} finally {
		if ($heldLock === null) \WeathermapConfig\release($lock);
	}
}

/** Return safe toolbar data, never configuration bytes. */
function status($entry) {
	return [
		'undo' => count($entry['undo'] ?? []), 'redo' => count($entry['redo'] ?? []),
		'undoLabel' => empty($entry['undo']) ? '' : end($entry['undo'])['label'],
		'redoLabel' => empty($entry['redo']) ? '' : end($entry['redo'])['label']
	];
}

/** Restore exact map bytes while checking includes, aliases, assets and stale writes. */
function restore($mapfile, $current, $target, $lock) {
	if (!\WeathermapConfig\dependenciesMatch($target['dependencies'])) return false;
	$contents = base64_decode($target['contents'], true);
	if ($contents === false || hash('sha256', $contents) !== $target['root']) return false;
	if (!is_array($target['assets'] ?? null) || !is_array($current['assets'] ?? null)) return false;
	$assets = array_diff($target['assets'], $current['assets']);
	$validate = function() use ($assets) {
		foreach ($assets as $asset) {
			if (!preg_match('/\{[^}]+\}/', $asset) && !is_file(dirname(__DIR__) . '/' . $asset)) return false;
		}
		return true;
	};
	$dependencies = array_merge($current['dependencies'], $current['identity'], $target['dependencies']);
	return \WeathermapConfig\atomicWrite($mapfile, $contents, true, $current['root'], false, $lock, $dependencies, $validate);
}

/** Apply one step, leaving both stacks untouched if the atomic restore fails. */
function step($mapfile, $current, $entry, $action, $lock) {
	$direction = $action === 'undo' ? 'undo' : 'redo';
	if (empty($entry[$direction])) throw new \DomainException(\__('No matching history remains. Reload the editor.', 'weathermap'));
	$step = end($entry[$direction]);
	if (!restore($mapfile, $current, $step['snapshot'], $lock)) throw new \RuntimeException(\__('Unable to restore this map. A configuration or image may have changed. Reload the editor.', 'weathermap'));
	array_pop($entry[$direction]);
	$opposite = $direction === 'undo' ? 'redo' : 'undo';
	$entry[$opposite][] = ['snapshot' => $current, 'label' => $step['label']];
	$entry[$opposite] = array_slice($entry[$opposite], -2);
	$entry['current'] = $step['snapshot']['revision'];
	$entry['time'] = time();
	return $entry;
}

/** Authenticated editor endpoint: POST plus Cacti's existing CSRF verification. */
function handle($mapfile, $action) {
	header('Content-Type: application/json');
	$lock = false;
	try {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || key($mapfile) === null) {
			http_response_code(400);
			echo json_encode(['error' => \__('Invalid history request.', 'weathermap')]);
			return;
		}
		$lock = \WeathermapConfig\acquire(dirname($mapfile));
		if (!$lock) throw new \RuntimeException(\__('Could not lock map configurations.', 'weathermap'));
		$map = new \WeatherMap;
		$map->context = 'editor';
		if (!\wm_editor_read_config($map, $mapfile, true)) {
			echo json_encode(['error' => \__('This map has changed. Reload the editor before using Undo or Redo.', 'weathermap')]);
			return;
		}
		$key = key($mapfile);
		load();
		prune();
		$current = snapshot($map, $mapfile);
		$entry = $GLOBALS['weathermap_editor_history'][$key] ?? [];
		if ($current === null || ($entry['current'] ?? null) !== $current['revision']) {
			unset($GLOBALS['weathermap_editor_history'][$key]);
			$entry = [];
		}
		if ($action !== 'history_status') {
			$entry = step($mapfile, $current, $entry, $action, $lock);
			unset($GLOBALS['weathermap_editor_history'][$key]);
			$GLOBALS['weathermap_editor_history'][$key] = $entry;
			prune();
			$entry = $GLOBALS['weathermap_editor_history'][$key] ?? [];
		}
		persistAfterSave();
		echo json_encode(status($entry));
	} catch (\Throwable $error) {
		http_response_code($error instanceof \DomainException ? 409 : 500);
		echo json_encode(['error' => $error->getMessage()]);
	} finally {
		if ($lock) \WeathermapConfig\release($lock);
	}
}
