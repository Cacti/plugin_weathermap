<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

$request = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
	'CREATE TABLE snmp_query (id INTEGER, hash TEXT)',
	'CREATE TABLE graph_templates_graph (local_graph_id INTEGER, title_cache TEXT)',
	'CREATE TABLE graph_local (id INTEGER, graph_template_id INTEGER, snmp_query_id INTEGER)',
	'CREATE TABLE data_template_data (local_data_id INTEGER, name_cache TEXT, data_source_path TEXT)',
	'CREATE TABLE data_local (id INTEGER, host_id INTEGER, snmp_query_id INTEGER, snmp_index TEXT)',
	'CREATE TABLE data_template_rrd (id INTEGER, local_data_id INTEGER)',
	'CREATE TABLE graph_templates_item (local_graph_id INTEGER, task_item_id INTEGER)',
	'CREATE TABLE host_snmp_cache (host_id INTEGER, snmp_query_id INTEGER, snmp_index TEXT, field_name TEXT, field_value TEXT)',
] as $sql) {
	$db->exec($sql);
}
$db->exec("INSERT INTO snmp_query VALUES (1, 'd75e406fdeca4fcef45b8be3a9a63cbc')");
for ($id = 1; $id <= 125; $id++) {
	$title = sprintf('Interface %03d', $id);
	foreach ([
		['INSERT INTO graph_templates_graph VALUES (?, ?)', [$id, $title]],
		['INSERT INTO graph_local VALUES (?, 1, 1)', [$id]],
		['INSERT INTO data_template_data VALUES (?, ?, ?)', [$id, $title, "<path_rra>/interface_$id.rrd"]],
		['INSERT INTO data_local VALUES (?, 1, 1, ?)', [$id, (string) $id]],
		['INSERT INTO data_template_rrd VALUES (?, ?)', [$id, $id]],
		['INSERT INTO graph_templates_item VALUES (?, ?)', [$id, $id]],
	] as [$sql, $params]) {
		$db->prepare($sql)->execute($params);
	}
}
$db->exec("INSERT INTO host_snmp_cache VALUES (1, 1, '115', 'ifAlias', 'Unique uplink')");

/**
 * Read a fixture request value.
 *
 * @param string $name Request key.
 *
 * @return mixed Fixture value.
 */
function get_nfilter_request_var($name) {
	return $GLOBALS['request'][$name] ?? '';
}

/**
 * Read a numeric fixture request value.
 *
 * @param string $name Request key.
 *
 * @return int Numeric value.
 */
function get_filter_request_var($name) {
	return (int) get_nfilter_request_var($name);
}

/**
 * Execute the actual endpoint SQL against an isolated catalog.
 *
 * @param string $sql Query.
 * @param array $params Bound values.
 *
 * @return array Query rows.
 */
function db_fetch_assoc_prepared($sql, $params = []) {
	$statement = $GLOBALS['db']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Apply the fixture's graph permissions.
 *
 * @param int $id Graph identifier.
 *
 * @return bool Whether the graph is permitted.
 */
function is_graph_allowed($id) {
	return !in_array((int) $id, $GLOBALS['request']['denied'] ?? [], true);
}

/**
 * Count fixture rows.
 *
 * @param array $items Rows.
 *
 * @return int Row count.
 */
function cacti_sizeof($items) {
	return count($items);
}

/**
 * Preserve a translated fixture label.
 *
 * @param string $text Label.
 * @param string $domain Translation domain.
 *
 * @return string Label.
 */
function __($text, $domain) {
	return $text;
}

require dirname(__DIR__, 2) . '/lib/editor.inc.php';
if (($request['endpoint'] ?? 'graphs') === 'datasources') {
	display_datasources();
} else {
	display_graphs();
}
