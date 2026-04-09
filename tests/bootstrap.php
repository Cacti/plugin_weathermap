<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$GLOBALS['__test_db_calls'] = array();

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array());
		return true;
	}
}
if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params);
		return true;
	}
}
if (!function_exists('db_fetch_assoc')) { function db_fetch_assoc($sql) { return array(); } }
if (!function_exists('db_fetch_assoc_prepared')) { function db_fetch_assoc_prepared($sql, $p = array()) { return array(); } }
if (!function_exists('db_fetch_row')) { function db_fetch_row($sql) { return array(); } }
if (!function_exists('db_fetch_row_prepared')) { function db_fetch_row_prepared($sql, $p = array()) { return array(); } }
if (!function_exists('db_fetch_cell')) { function db_fetch_cell($sql) { return ''; } }
if (!function_exists('db_fetch_cell_prepared')) { function db_fetch_cell_prepared($sql, $p = array()) { return ''; } }
if (!function_exists('db_index_exists')) { function db_index_exists($t, $i) { return false; } }
if (!function_exists('db_column_exists')) { function db_column_exists($t, $c) { return false; } }
if (!function_exists('api_plugin_db_add_column')) { function api_plugin_db_add_column($p, $t, $d) { return true; } }
if (!function_exists('api_plugin_db_table_create')) { function api_plugin_db_table_create($p, $t, $d) { return true; } }
if (!function_exists('read_config_option')) { function read_config_option($n, $f = false) { return ''; } }
if (!function_exists('set_config_option')) { function set_config_option($n, $v) {} }
if (!function_exists('html_escape')) { function html_escape($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); } }
if (!function_exists('__')) { function __($t, $d = '') { return $t; } }
if (!function_exists('__esc')) { function __esc($t, $d = '') { return htmlspecialchars($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'); } }
if (!function_exists('cacti_log')) { function cacti_log($m, $p = false, $t = '', $l = 0) {} }
if (!function_exists('cacti_sizeof')) { function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; } }
if (!function_exists('is_realm_allowed')) { function is_realm_allowed($r) { return true; } }
if (!function_exists('raise_message')) { function raise_message($i, $t = '', $l = 0) {} }
if (!function_exists('get_request_var')) { function get_request_var($n) { return ''; } }
if (!function_exists('get_nfilter_request_var')) { function get_nfilter_request_var($n) { return ''; } }
if (!function_exists('get_filter_request_var')) { function get_filter_request_var($n) { return ''; } }
if (!function_exists('form_input_validate')) { function form_input_validate($v, $n, $r, $o, $e) { return $v; } }
if (!function_exists('is_error_message')) { function is_error_message() { return false; } }
if (!function_exists('sql_save')) { function sql_save($a, $t, $k = 'id') { return isset($a['id']) ? $a['id'] : 1; } }
if (!defined('CACTI_PATH_BASE')) { define('CACTI_PATH_BASE', '/var/www/html/cacti'); }
if (!defined('POLLER_VERBOSITY_LOW')) { define('POLLER_VERBOSITY_LOW', 2); }
if (!defined('POLLER_VERBOSITY_MEDIUM')) { define('POLLER_VERBOSITY_MEDIUM', 3); }
if (!defined('POLLER_VERBOSITY_DEBUG')) { define('POLLER_VERBOSITY_DEBUG', 5); }
if (!defined('POLLER_VERBOSITY_NONE')) { define('POLLER_VERBOSITY_NONE', 6); }
if (!defined('MESSAGE_LEVEL_ERROR')) { define('MESSAGE_LEVEL_ERROR', 1); }
