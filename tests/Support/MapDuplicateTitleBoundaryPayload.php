<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

/* Exercise the duplicate-map title length guard against ReadConfig()'s
 * fgets($fd, 4096) chunk boundary.  argv[1] selects the title:
 *   max    - 4088 bytes, the longest that keeps "TITLE <title>\n" in one chunk
 *   over   - 4089 bytes, one past the limit
 *   inject - over the limit with an INCLUDE directive in the overflow
 * The harness prints JSON: whether the write path was reached (reached) and,
 * when it was, the byte length of the written TITLE line (titleLineLen). */

chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . '/lib/WeatherMap.class.php';
// map_duplicate() cleans the new title with this helper
require dirname(__DIR__, 2) . '/lib/map-title.php';

$source = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin-mgmt.php');
$start  = strpos($source, 'function map_duplicate(');
$end    = strpos($source, "\n/**", $start);

if ($start === false || $end === false) {
	throw new RuntimeException('Unable to locate the map duplication function.');
}

$fixture = realpath(sys_get_temp_dir()) . '/wm-duplicate-boundary-' . bin2hex(random_bytes(5));
mkdir($fixture); mkdir($fixture . '/configs');
symlink(dirname(__DIR__, 2) . '/lib', $fixture . '/lib');
file_put_contents($fixture . '/configs/Original.conf', "TITLE Original Map\n");

$GLOBALS['__newfile'] = $fixture . '/configs/Original_copy.conf';

eval(str_replace('__DIR__', var_export($fixture, true), substr($source, $start, $end - $start)));

/**
 * Pick the duplicate title for the requested boundary case.
 *
 * @param string $mode One of max, over or inject.
 *
 * @return string The title used in place of the <map_title> placeholder.
 */
function boundary_title($mode) {
	switch ($mode) {
		case 'max':    return str_repeat('T', 4088);
		case 'over':   return str_repeat('T', 4089);
		case 'inject': return str_repeat('T', 4089) . 'INCLUDE /etc/passwd';
		default:       throw new RuntimeException('Unknown boundary mode: ' . $mode);
	}
}

/**
 * Return the source map fixture.
 *
 * @param string $sql    Query.
 * @param array  $params Query parameters.
 *
 * @return array Source map row.
 */
function db_fetch_row_prepared($sql, $params) {
	return ['group_id' => 2, 'active' => 1, 'configfile' => 'Original.conf', 'titlecache' => 'Original Map',
		'thumb_height'    => 150, 'thumb_width' => 200, 'schedule' => '* * * * *', 'archiving' => 0];
}

/**
 * Return the existing maximum map sort order.
 *
 * @param string $sql Query.
 *
 * @return int Maximum sort order.
 */
function db_fetch_cell($sql) {
	return 9;
}

/**
 * Count a fixture array.
 *
 * @param array $value Array to count.
 *
 * @return int Array size.
 */
function cacti_sizeof($value) {
	return count($value);
}

/**
 * Count effective entries for the combined engine fixture.
 *
 * @param array $value Values to count.
 *
 * @return int Number of entries.
 */
function cacti_count($value) { return count($value); }

/**
 * Supply a destination filename without touching the filesystem.
 *
 * @param string $file Source filename.
 *
 * @return string Destination filename.
 */
function map_get_next_name($file) {
	return 'Original_copy.conf';
}

/**
 * Report the selected schema shape.
 *
 * @param string $table  Table name.
 * @param string $column Column name.
 *
 * @return bool Whether the debug column exists.
 */
function db_column_exists($table, $column) {
	return false;
}

/**
 * Capture that the write path was reached and the written TITLE line length.
 *
 * atomicWrite() has already run by the time sql_save() is called, so the new
 * config is on disk here; record its TITLE line length, then return 0 to take
 * the documented rollback path and leave no fixture behind.
 *
 * @param array  $payload Map fields to save.
 * @param string $table   Destination table.
 *
 * @return int Zero prevents further side effects in the fixture.
 */
function sql_save($payload, $table) {
	$GLOBALS['reached']  = true;
	$written             = @file_get_contents($GLOBALS['__newfile']);
	$GLOBALS['titleLen'] = null;

	if (is_string($written)) {
		foreach (explode("\n", $written) as $line) {
			if (strpos($line, 'TITLE ') === 0) {
				$GLOBALS['titleLen'] = strlen($line);
				break;
			}
		}
	}

	return 0;
}

/** Ignore expected registration failure notices in this schema-only fixture. */
function raise_message($id, $text = '', $level = 0) {}
/** Capture server-only clone diagnostics in this fixture. */
function cacti_log($text, ...$args) { $GLOBALS['clone_log'][] = $text; }
/** Return the untranslated fixture string. */
function __($text, ...$args) { return $text; }
/** Return the untranslated, escaped fixture string. */
function __esc($text, ...$args) { return $text; }
function db_execute_prepared($sql, $params = []) { return true; }
function weathermap_run_maps(...$args) {}
define('MESSAGE_LEVEL_ERROR', 3);
define('MESSAGE_LEVEL_INFO', 0);

$GLOBALS['reached']  = false;
$GLOBALS['titleLen'] = null;

try {
	map_duplicate(5, boundary_title($GLOBALS['argv'][1]));
	print json_encode(['reached' => $GLOBALS['reached'], 'titleLineLen' => $GLOBALS['titleLen']], JSON_THROW_ON_ERROR);
} finally {
	foreach (glob($fixture . '/configs/*') as $file) unlink($file);
	if (is_file($fixture . '/configs/.weathermap-config.lock')) unlink($fixture . '/configs/.weathermap-config.lock');
	unlink($fixture . '/lib'); rmdir($fixture . '/configs'); rmdir($fixture);
}
