<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

// Run the actual function without executing the management page dispatcher.
$source = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin-mgmt.php');
$start  = strpos($source, 'function map_duplicate(');
$end    = strpos($source, "\n/**", $start);

if ($start === false || $end === false) {
	throw new RuntimeException('Unable to locate the map duplication function.');
}
eval(substr($source, $start, $end - $start));

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
	if ($table !== 'weathermap_maps' || $column !== 'debug') {
		throw new RuntimeException('Unexpected column lookup.');
	}

	return $GLOBALS['argv'][1] === 'present';
}

/**
 * Capture the actual save payload and stop before file copying/polling.
 *
 * @param array  $payload Map fields to save.
 * @param string $table   Destination table.
 *
 * @return int Zero prevents further side effects in the fixture.
 */
function sql_save($payload, $table) {
	$GLOBALS['duplicate_save'] = ['table' => $table, 'payload' => $payload];

	return 0;
}

map_duplicate(5, '<map_title> Copy');
print json_encode($GLOBALS['duplicate_save'], JSON_THROW_ON_ERROR);
