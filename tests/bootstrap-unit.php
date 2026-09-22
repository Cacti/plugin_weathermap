<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * When this plugin is checked out alongside a real Cacti tree (as the CI
 * workflow does, under cacti/plugins/weathermap), tests/.cacti-version
 * records which Cacti ref the workflow's CACTI environment variable pointed
 * at. Confirm the two agree so a stale checkout doesn't pass silently; skip
 * the check when no Cacti tree is present, since these tests also run
 * standalone against the stub functions below.
 */
$cacti_version_file    = dirname(__DIR__, 3) . '/include/cacti_version';
$expected_version_file = __DIR__ . '/.cacti-version';

if (is_readable($cacti_version_file) && is_readable($expected_version_file)) {
	$actual_version   = trim((string) file_get_contents($cacti_version_file));
	$expected_version = trim((string) file_get_contents($expected_version_file));

	if (!in_array($expected_version, ['1.2.x', 'develop'], true) && $actual_version !== $expected_version) {
		throw new RuntimeException("Expected Cacti $expected_version, found $actual_version in $cacti_version_file");
	}
}

$GLOBALS['__test_db_calls'] = [];

$GLOBALS['config'] = [
	'base_path' => dirname(__DIR__, 3),
	'url_path'  => '/cacti/',
];

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute', 'sql' => $sql, 'params' => []];

		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params];

		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql) {
		return [];
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $p = []) {
		return [];
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql) {
		return [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $p = []) {
		return [];
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql) {
		return '';
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $p = []) {
		return '';
	}
}

if (!function_exists('db_index_exists')) {
	function db_index_exists($t, $i) {
		return false;
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($t, $c) {
		return false;
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	function api_plugin_db_add_column($p, $t, $d) {
		return true;
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	function api_plugin_db_table_create($p, $t, $d) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'api_plugin_db_table_create', 'plugin' => $p, 'table' => $t, 'data' => $d];
		return true;
	}
}

$GLOBALS['__test_registered_hooks']  = [];
$GLOBALS['__test_registered_realms'] = [];

if (!function_exists('api_plugin_register_hook')) {
	function api_plugin_register_hook($plugin, $hook, $function, $file, $enabled = 1) {
		$GLOBALS['__test_registered_hooks'][] = [
			'plugin'   => $plugin,
			'hook'     => $hook,
			'function' => $function,
			'file'     => $file,
			'enabled'  => $enabled,
		];
		return true;
	}
}

if (!function_exists('api_plugin_register_realm')) {
	function api_plugin_register_realm($plugin, $file, $description, $enabled = 1) {
		$GLOBALS['__test_registered_realms'][] = [
			'plugin'      => $plugin,
			'file'        => $file,
			'description' => $description,
			'enabled'     => $enabled,
		];
		return true;
	}
}

$GLOBALS['__test_current_page'] = '';

if (!function_exists('get_current_page')) {
	function get_current_page() {
		return $GLOBALS['__test_current_page'];
	}
}

if (!function_exists('test_set_current_page')) {
	function test_set_current_page($page) {
		$GLOBALS['__test_current_page'] = $page;
	}
}

if (!function_exists('isset_request_var')) {
	function isset_request_var($n) {
		return isset($GLOBALS['__test_request'][$n]);
	}
}

if (!function_exists('read_user_setting')) {
	function read_user_setting($n, $d = false) {
		return $d;
	}
}

if (!function_exists('db_table_exists')) {
	function db_table_exists($t) {
		return false;
	}
}

if (!function_exists('html_start_box')) {
	function html_start_box($title, $width = '100%', $div = false, $colspan = 3, $align = 'center', $link = '') {
		print $title;
	}
}

if (!function_exists('html_end_box')) {
	function html_end_box() {
		print '<!-- html_end_box -->';
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($n, $f = false) {
		return '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($n, $v) {
	}
}

if (!function_exists('html_escape')) {
	function html_escape($s) {
		return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('__')) {
	function __(...$args) {
		if (count($args) <= 1) {
			return (string) $args[0];
		}

		$text = array_shift($args);
		array_pop($args); // trailing text-domain argument

		return count($args) ? vsprintf((string) $text, $args) : (string) $text;
	}
}

if (!function_exists('__esc')) {
	function __esc($t, $d = '') {
		return htmlspecialchars($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($m, $p = false, $t = '', $l = 0) {
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a) {
		return is_array($a) ? count($a) : 0;
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($r) {
		return true;
	}
}

if (!function_exists('raise_message')) {
	function raise_message($i, $t = '', $l = 0) {
	}
}

$GLOBALS['__test_request'] = [];

if (!function_exists('get_request_var')) {
	function get_request_var($n) {
		return isset($GLOBALS['__test_request'][$n]) ? $GLOBALS['__test_request'][$n] : '';
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($n) {
		return '';
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($n) {
		return '';
	}
}

if (!function_exists('form_input_validate')) {
	function form_input_validate($v, $n, $r, $o, $e) {
		return $v;
	}
}

if (!function_exists('is_error_message')) {
	function is_error_message() {
		return false;
	}
}

if (!function_exists('sql_save')) {
	function sql_save($a, $t, $k = 'id') {
		return isset($a['id']) ? $a['id'] : 1;
	}
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', '/var/www/html/cacti');
}

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 2);
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

if (!defined('POLLER_VERBOSITY_DEBUG')) {
	define('POLLER_VERBOSITY_DEBUG', 5);
}

if (!defined('POLLER_VERBOSITY_NONE')) {
	define('POLLER_VERBOSITY_NONE', 6);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

// Pest v1 has no global describe(); provide a passthrough so test files load.
if (!function_exists('describe')) {
	function describe(string $description, Closure $tests): void {
		$tests();
	}
}
