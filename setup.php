<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2024 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (!defined('WM_COPYRIGHT_YEARS')) {
	define('WM_COPYRIGHT_YEARS', '2008-2024');
}

function plugin_weathermap_install() {
	api_plugin_register_hook('weathermap', 'config_arrays',   'weathermap_config_arrays',   'setup.php');
	api_plugin_register_hook('weathermap', 'config_settings', 'weathermap_config_settings', 'setup.php');

	api_plugin_register_hook('weathermap', 'top_header_tabs',       'weathermap_show_tab', 'setup.php');
	api_plugin_register_hook('weathermap', 'top_graph_header_tabs', 'weathermap_show_tab', 'setup.php');
	api_plugin_register_hook('weathermap', 'draw_navigation_text', 'weathermap_draw_navigation_text', 'setup.php');

	api_plugin_register_hook('weathermap', 'top_graph_refresh', 'weathermap_top_graph_refresh', 'setup.php');
	api_plugin_register_hook('weathermap', 'page_title',        'weathermap_page_title',        'setup.php');

	api_plugin_register_hook('weathermap', 'poller_top',    'weathermap_poller_top',    'setup.php');
	api_plugin_register_hook('weathermap', 'poller_output', 'weathermap_poller_output', 'setup.php');
	api_plugin_register_hook('weathermap', 'poller_bottom', 'weathermap_poller_bottom', 'setup.php');

	api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin.php', 'View Weathermaps', 1);
	api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php', 'Manage Weathermap', 1);
	api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin-editor.php', 'Edit Weathermaps', 1);

	weathermap_setup_table();
}

function plugin_weathermap_uninstall() {
	set_config_option('weathermap_version', '');

	db_execute('DROP TABLE IF EXISTS weathermap_auth');
	db_execute('DROP TABLE IF EXISTS weathermap_data');
	db_execute('DROP TABLE IF EXISTS weathermap_maps');
	db_execute('DROP TABLE IF EXISTS weathermap_groups');
	db_execute('DROP TABLE IF EXISTS weathermap_settings');
}

function plugin_weathermap_version() {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/weathermap/INFO', true);

	return $info['info'];
}

function plugin_weathermap_numeric_version() {
	static $current;

	if ($current == null) {
		$current = plugin_weathermap_version();
	}

	return $current['version'];
}

function plugin_weathermap_check_config() {
	plugin_weathermap_upgrade();

	return true;
}

function plugin_weathermap_upgrade() {
	global $config;

	$files = array('index.php', 'plugins.php');
	if (!in_array(get_current_page(), $files) && strpos(get_current_page(), 'weathermap-cacti') === false) {
		return;
	}

	include_once($config['base_path'] . '/plugins/weathermap/lib/poller-common.php');

	$current = plugin_weathermap_version();
	$current = $current['version'];
	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='weathermap'");

	if ($current != $old) {
		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			array('View Weathermaps', 'weathermap-cacti-plugin.php'));

		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			array('Edit Weathermaps', 'weathermap-cacti-plugin-editor.php'));

		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			array('Manage Weathermap', 'weathermap-cacti-plugin-mgmt.php'));

		db_execute_prepared('UPDATE plugin_realms
			SET file = ? WHERE file = ?',
			array('weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php', 'weathermap-cacti-plugin-mgmt.php'));

		/* update the plugin information */
		$info = plugin_weathermap_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='weathermap'");

		db_execute_prepared('UPDATE plugin_config
			SET name = ?, author = ?, webpage = ?, version = ?
			WHERE id = ?',
			array(
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['version'],
				$id
			)
		);

		db_execute('DELETE FROM plugin_hooks WHERE name = "weathermap" AND hook = "page_head"');

		weathermap_repair_maps();
	}

	return false;
}

function weathermap_poller_top() {
	global $weathermap_poller_start_time;

	$n = time();

	// round to the nearest minute, since that's all we need for the crontab-style stuff
	$weathermap_poller_start_time = $n - ($n % 60);
}

function weathermap_page_title($t) {
	if (preg_match('/plugins\/weathermap\//', $_SERVER['REQUEST_URI'], $matches)) {
		if (preg_match('/plugins\/weathermap\/weathermap-cacti-plugin.php\?action=viewmap&id=([^&]+)/', $_SERVER['REQUEST_URI'], $matches)) {
			$mapid = $matches[1];

			if (preg_match('/^\d+$/', $mapid)) {
				$title = db_fetch_cell_prepared('SELECT titlecache FROM weathermap_maps WHERE id = ?', array($mapid));
			} else {
				$title = db_fetch_cell_prepared('SELECT titlecache FROM weathermap_maps WHERE filehash = ?', array($mapid));
			}

			if ($title != '') {
				$t .= ' > ' . $title;
			}
		}

		return($t);
	}

	return($t);
}


function weathermap_top_graph_refresh($refresh) {
	if (basename($_SERVER['PHP_SELF']) != 'weathermap-cacti-plugin.php') {
		return $refresh;
	}

	// if we're cycling maps, then we want to handle reloads ourselves, thanks
	if (isset_request_var('action') && get_request_var('action') == 'viewmapcycle') {
		return (86400);
	} elseif (get_request_var('action') == '' || get_request_var('action') == 'viewmap') {
		return (read_user_setting('page_refresh'));
	}

	return ($refresh);
}

function weathermap_config_settings() {
	global $tabs, $settings;

	$tabs['wmap'] = __('Weathermap', 'weathermap');

	$temp = array(
		'weathermap_header' => array(
			'friendly_name' => __('Network Weathermap', 'weathermap'),
			'method'        => 'spacer',
		),
		'weathermap_pagestyle' => array(
			'friendly_name' => __('Page style', 'weathermap'),
			'description'   => __('How to display multiple maps.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				0 => __('Thumbnail Overview', 'weathermap'),
				1 => __('Full Images', 'weathermap'),
				2 => __('Show Only First', 'weathermap'),
			)
		),
		'weathermap_thumbsize' => array(
			'friendly_name' => __('Thumbnail Maximum Size', 'weathermap'),
			'description'   => __('The maximum width or height for thumbnails in thumbnail view, in pixels. Takes effect after the next poller run.', 'weathermap'),
			'method'        => 'textbox',
			'size'          => 3,
			'max_length'    => 4,
		),
		'weathermap_width' => array(
			'friendly_name' => __('Hover Graph Default Width', 'weathermap'),
			'description'   => __('The default width of the RRDtool Graphs that appear when you over on a Link.', 'weathermap'),
			'method'        => 'textbox',
			'default'       => 400,
			'size'          => 3,
			'max_length'    => 4,
		),
		'weathermap_height' => array(
			'friendly_name' => __('Hover Graph Default Height', 'weathermap'),
			'description'   => __('The default height of the RRDtool Graphs that appear when you over on a Link.', 'weathermap'),
			'method'        => 'textbox',
			'default'       => 125,
			'size'          => 3,
			'max_length'    => 4,
		),
		'weathermap_nolegend' => array(
			'friendly_name' => __('Hover Graph Style', 'weathermap'),
			'description'   => __('When hovering over the Links or Nodes, what style of Graph would you like displayed?', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				'thumb' => __('Thumbnail Graphs', 'weathermap'),
				'full'  => __('Full Graphs', 'weathermap')
			)
		),
		'weathermap_cycle_refresh' => array(
			'friendly_name' => __('Refresh Time', 'weathermap'),
			'description'   => __('How often to refresh the page in Cycle mode. Automatic makes all available maps fit into 5 minutes.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				0   => __('Automatic', 'weathermap'),
				5   => __('%d Seconds', 5,  'weathermap'),
				15  => __('%d Seconds', 15, 'weathermap'),
				30  => __('%d Seconds', 30, 'weathermap'),
				60  => __('%d Minute',  1,  'weathermap'),
				120 => __('%d Minutes', 2,  'weathermap'),
				300 => __('%d Minutes', 3,  'weathermap'),
			)
		),
		'weathermap_output_format' => array(
			'friendly_name' => __('Output Format', 'weathermap'),
			'description'   => __('What format do you prefer for the generated map images and thumbnails?', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				'png' => __('PNG (default)', 'weathermap'),
				'jpg' => __('JPEG', 'weathermap'),
				'gif' => __('GIF', 'weathermap'),
			)
		),
		'weathermap_render_period' => array(
			'friendly_name' => __('Map Rendering Interval', 'weathermap'),
			'description'   => __('How often do you want Weathermap to recalculate it\'s maps? You should not touch this unless you know what you are doing! It is mainly needed for people with non-standard polling setups.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				-1  => __('Never (manual updates)',       'weathermap'),
				0   => __('Every Poller Cycle (default)', 'weathermap'),
				2   => __('Every %d Poller Cycles', 2,    'weathermap'),
				3   => __('Every %d Poller Cycles', 3,    'weathermap'),
				4   => __('Every %d Poller Cycles', 4,    'weathermap'),
				5   => __('Every %d Poller Cycles', 5,    'weathermap'),
				10  => __('Every %d Poller Cycles', 10,   'weathermap'),
				12  => __('Every %d Poller Cycles', 12,   'weathermap'),
				24  => __('Every %d Poller Cycles', 24,   'weathermap'),
				36  => __('Every %d Poller Cycles', 36,   'weathermap'),
				48  => __('Every %d Poller Cycles', 48,   'weathermap'),
				72  => __('Every %d Poller Cycles', 72,   'weathermap'),
				288 => __('Every %d Poller Cycles', 288,  'weathermap'),
			),
		),
		'weathermap_showversion' => array(
			'friendly_name' => __('Show Weathermap Help Links', 'weathermap'),
			'description' => __('If checked, all Weathermap pages will include a link to documentation.', 'weathermap'),
			'method' => 'checkbox',
			'default' => ''
		),
		'weathermap_all_tab' => array(
			'friendly_name' => __('Show \'All\' Tab', 'weathermap'),
			'description'   => __('When using groups, add an \'All Maps\' tab to the tab bar.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				0 => __('No (default)', 'weathermap'),
				1 => __('Yes', 'weathermap'),
			)
		),
		'weathermap_map_selector' => array(
			'friendly_name' => __('Show Map Selector', 'weathermap'),
			'description'   => __('Show a combo-box map selector on the full-screen map view.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				0 => __('No', 'weathermap'),
				1 => __('Yes (default)', 'weathermap'),
			)
		),
		'weathermap_quiet_logging' => array(
			'friendly_name' => __('Quiet Logging', 'weathermap'),
			'description'   => __('By default, even in LOW level logging, Weathermap logs normal activity. This makes it REALLY log only errors in LOW mode.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => array(
				0 => __('Chatty (default)', 'weathermap'),
				1 => __('Quiet', 'weathermap'),
			)
		)
	);

	if (isset($settings['wmap'])) {
		$settings['wmap'] = array_merge($settings['wmap'], $temp);
	} else {
		$settings['wmap'] = $temp;
	}
}

function weathermap_setup_table() {
	global $config, $database_default;

	$dbversion = read_config_option('weathermap_db_version');
	$myversion = plugin_weathermap_numeric_version();

	// only bother with all this if it's a new install, a new version, or we're in a development version
	// - saves a handful of db hits per request!
	if (($dbversion == '') || (preg_match('/dev$/', $myversion)) || ($dbversion != $myversion) || !db_table_exists('weathermap_maps')) {
		db_execute('CREATE TABLE IF NOT EXISTS weathermap_maps (
			`id` int(11) NOT NULL auto_increment,
			`sortorder` int(11) NOT NULL default 0,
			`group_id` int(11) NOT NULL default 1,
			`active` set("on","off") NOT NULL default "on",
			`configfile` varchar(255) NOT NULL,
			`imagefile` varchar(255) NOT NULL,
			`htmlfile` varchar(255) NOT NULL,
			`titlecache` varchar(60) NOT NULL,
			`filehash` varchar (40) NOT NULL default "",
			`warncount` int(11) NOT NULL default 0,
			`debug` set("on","off","once") NOT NULL DEFAULT "off",
			`config` text NOT NULL,
			`thumb_width` int(11) NOT NULL default 0,
			`thumb_height` int(11) NOT NULL default 0,
			`schedule` varchar(32) NOT NULL default "*",
			`archiving` set("on","off") NOT NULL default "off",
			`duration` double NOT NULL default "0",
			`last_runtime` int unsigned not null default "0",
			PRIMARY KEY  (id),
			UNIQUE KEY configfile(configfile))
			ENGINE = InnoDB
			ROW_FORMAT=Dynamic');

		db_execute('CREATE TABLE IF NOT EXISTS weathermap_auth (
			`userid` mediumint(9) NOT NULL default "0",
			`mapid` int(11) NOT NULL default "0")
			ENGINE=InnoDB
			ROW_FORMAT=Dynamic');

		db_execute('CREATE TABLE IF NOT EXISTS weathermap_settings (
			`id` int(11) NOT NULL auto_increment,
			`mapid` int(11) NOT NULL default "0",
			`groupid` int(11) NOT NULL default "0",
			`optname` varchar(128) NOT NULL default "",
			`optvalue` varchar(128) NOT NULL default "",
			PRIMARY KEY  (id),
			UNIQUE INDEX mapid_groupid_optname(mapid, groupid, optname))
			ENGINE=InnoDB
			ROW_FORMAT=Dynamic');

		db_execute('CREATE TABLE IF NOT EXISTS weathermap_data (
			`id` int(11) NOT NULL auto_increment,
			`rrdfile` varchar(255) NOT NULL,
			`data_source_name` varchar(19) NOT NULL,
			`last_time` int(11) NOT NULL DEFAULT -1,
			`last_value` varchar(255) NOT NULL DEFAULT "",
			`last_calc` varchar(255) NOT NULL DEFAULT "",
			`sequence` int(11) NOT NULL DEFAULT 0,
			`local_data_id` int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY rrdfile (rrdfile(250)),
			KEY local_data_id (local_data_id),
			KEY data_source_name (data_source_name))
			ENGINE=InnoDB
			ROW_FORMAT=Dynamic');

		if (!db_table_exists('weathermap_groups')) {
			db_execute('CREATE TABLE IF NOT EXISTS weathermap_groups (
				`id` INT(11) NOT NULL auto_increment,
				`name` VARCHAR(128) NOT NULL default "",
				`sortorder` INT(11) NOT NULL default 0,
				PRIMARY KEY (id))
				ENGINE=InnoDB
				ROW_FORMAT=Dynamic');

			db_execute('INSERT INTO weathermap_groups (id, name, sortorder) VALUES (1, "Weathermaps", 1)');
		}

		db_execute('DELETE FROM weathermap_data WHERE local_data_id = 0');

		if (db_column_exists('weathermap_maps', 'sortorder')) {
			db_execute('UPDATE weathermap_maps SET sortorder = id WHERE sortorder IS NULL');
		}

		if (!db_column_exists('weathermap_maps', 'sortorder')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN sortorder int(11) NOT NULL default 0 AFTER id');
		}

		if (!db_column_exists('weathermap_maps', 'filehash')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN filehash varchar(40) NOT NULL default "" AFTER titlecache');
		}

		if (!db_column_exists('weathermap_maps', 'warncount')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN warncount int(11) NOT NULL default 0 AFTER filehash');
		}

		if (!db_column_exists('weathermap_maps', 'debug')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN debug `debug` set("on","off","once") NOT NULL DEFAULT "off" AFTER warncount');
		}

		if (!db_column_exists('weathermap_maps', 'config')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN config text NOT NULL  default "" AFTER warncount');
		}

		if (!db_column_exists('weathermap_maps', 'thumb_width')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN thumb_width int(11) NOT NULL default 0 AFTER config');
		}

		if (!db_column_exists('weathermap_maps', 'thumb_height')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN thumb_height int(11) NOT NULL default 0 AFTER thumb_width');
		}

		if (!db_column_exists('weathermap_maps', 'schedule')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN schedule varchar(32) NOT NULL default "*" AFTER thumb_height');
		}

		if (!db_column_exists('weathermap_maps', 'archiving')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN archiving set("on","off") NOT NULL default "off" AFTER schedule');
		}

		if (!db_column_exists('weathermap_maps', 'group_id')) {
			db_execute('ALTER TABLE weathermap_maps ADD COLUMN group_id int(11) NOT NULL default 1 AFTER sortorder');
		}

		if (!db_column_exists('weathermap_settings', 'groupid')) {
			db_execute('ALTER TABLE `weathermap_settings` ADD COLUMN `groupid` INT NOT NULL DEFAULT "0" AFTER `mapid`');
		}

		if (!db_column_exists('weathermap_maps', 'duration')) {
			db_execute('ALTER TABLE `weathermap_maps` ADD COLUMN `duration` double NOT NULL DEFAULT "0" AFTER `archiving`');
		}

		if (!db_column_exists('weathermap_maps', 'last_runtime')) {
			db_execute('ALTER TABLE `weathermap_maps` ADD COLUMN `last_runtime` INT UNSIGNED NOT NULL DEFAULT "0" AFTER `duration`');
		}

		if (!db_index_exists('weathermap_maps', 'configfile')) {
			db_execute('ALTER TABLE `weathermap_maps` ADD UNIQUE INDEX `configfile`(`configfile`)');
		}

		db_execute('UPDATE weathermap_maps SET `filehash` = LEFT(MD5(concat(id,configfile,rand())),20) WHERE `filehash` = ""');

		if (!db_column_exists('weathermap_data', 'local_data_id')) {
			db_execute('ALTER TABLE weathermap_data
				ADD COLUMN local_data_id int(11) NOT NULL default 0 AFTER sequence,
				ADD INDEX (`local_data_id`)');
		}

		// create the settings entries, if necessary
		$pagestyle = read_config_option('weathermap_pagestyle');
		if ($pagestyle == '' || $pagestyle < 0 || $pagestyle > 2) {
			set_config_option('weathermap_pagestyle', '0');
		}

		$cycledelay = read_config_option('weathermap_cycle_refresh');
		if ($cycledelay == '' || $cycledelay < 0) {
			set_config_option('weathermap_cycle_refresh', '0');
		}

		$renderperiod = read_config_option('weathermap_render_period');
		if ($renderperiod == '' || $renderperiod < -1) {
			set_config_option('weathermap_render_period', '0');
		}

		$quietlogging = read_config_option('weathermap_quiet_logging');
		if ($quietlogging == '' || $quietlogging < -1) {
			set_config_option('weathermap_quiet_logging', '0');
		}

		$rendercounter = read_config_option('weathermap_render_counter');
		if ($rendercounter == '' || $rendercounter < 0) {
			set_config_option('weathermap_render_counter', '0');
		}

		$outputformat = read_config_option('weathermap_output_format');
		if ($outputformat == '') {
			set_config_option('weathermap_output_format', 'png');
		}

		$tsize = read_config_option('weathermap_thumbsize');
		if ($tsize == '' || $tsize < 1) {
			set_config_option('weathermap_thumbsize', '250');
		}

		$ms = read_config_option('weathermap_map_selector');
		if ($ms == '' || $ms < 0 || $ms > 1) {
			set_config_option('weathermap_map_selector', '1');
		}

		$at = read_config_option('weathermap_all_tab');
		if ($at == '' || $at < 0 || $at > 1) {
			set_config_option('weathermap_all_tab', '0');
		}

		// update the version, so we can skip this next time
		set_config_option('weathermap_db_version', $myversion);

		// patch up the sortorder for any maps that don't have one.
		db_execute('UPDATE weathermap_maps SET sortorder = id WHERE sortorder IS NULL OR sortorder = 0');

		// make sure Weathermaps uses a sane width for columns
		db_execute('ALTER TABLE weathermap_maps MODIFY COLUMN `configfile` varchar(255) NOT NULL');
		db_execute('ALTER TABLE weathermap_maps MODIFY COLUMN `imagefile` varchar(255) NOT NULL');
		db_execute('ALTER TABLE weathermap_maps MODIFY COLUMN `htmlfile` varchar(255) NOT NULL');
		db_execute('ALTER TABLE weathermap_maps MODIFY COLUMN `titlecache` varchar(60) NOT NULL');

		// Check and enable boost support if it's enabled
		weathermap_check_set_boost();

		// Correct weathermap settings table of duplicate entries
		while (true) {
			$rows = db_fetch_assoc('SELECT mapid, groupid, optname, COUNT(*) AS totals
				FROM weathermap_settings
				GROUP BY mapid, groupid, optname
				HAVING totals > 1');

			if (cacti_sizeof($rows)) {
				foreach($rows as $row) {
					db_execcute_prepared('DELETE FROM weathermap_settings
						WHERE mapid = ? AND groupid = ? AND optname = ?
						LIMIT 1',
						array($row['mapid'], $row['groupid'], $row['optname']));
				}
			} else {
				break;
			}
		}
	}
}

function weathermap_check_set_boost() {
	$boost = read_config_option('boost_rrd_update_enable') == 'on' ? true:false;
	if ($boost) {
		$exists = db_fetch_row('SELECT id, optvalue
			FROM weathermap_settings
			WHERE mapid = 0
			AND groupid = 0
			AND optname = "rrd_use_poller_output"');

		if (!cacti_sizeof($exists)) {
			db_execute('INSERT INTO weathermap_settings (mapid, groupid, optname, optvalue)
				VALUES (0, 0, "rrd_use_poller_output", 1)');
		} elseif ($exists['optvalue'] == 0) {
			db_execute_prepared('UPDATE weathermap_settings
				SET optvalue = 1
				WHERE id = ?',
				array($exists['id']));
		}
	}
}

function weathermap_config_arrays() {
	global $menu;
	global $tree_item_types, $tree_item_handlers;

	plugin_weathermap_upgrade();

	// if there is support for custom graph tree types, then register ourselves
	if (isset($tree_item_handlers)) {
		$tree_item_types[10] = __('Weathermap', 'weathermap');

		$tree_item_handlers[10] = array(
			'render' => 'weathermap_tree_item_render',
			'name'   => 'weathermap_tree_item_name',
			'edit'   => 'weathermap_tree_item_edit'
		);
	}

	$wm_menu = array(
		'plugins/weathermap/weathermap-cacti-plugin-mgmt.php'        => __('Weathermaps', 'weathermap'),
		'plugins/weathermap/weathermap-cacti-plugin-mgmt-groups.php' => __('Weathermap Groups', 'weathermap')
	);

	$menu[__('Management')]['plugins/weathermap/weathermap-cacti-plugin-mgmt.php'] = $wm_menu;

	// These simply need to be declared for i18n the realm names
	$realm_array = array(
		__('View Weathermaps', 'weathermap'),
		__('Edit Weathermaps', 'weathermap'),
		__('Manage Weathermap', 'weathermap')
	);

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles_byname(__('General Administration'), 'Manage Weathermap');
		auth_augment_roles_byname(__('General Administration'), 'Edit Weathermaps');
		auth_augment_roles_byname(__('Normal User'), 'View Weathermaps');
    }
}

function weathermap_tree_item_render($leaf) {
	$outdir  = __DIR__ . '/output/';
	$confdir = __DIR__ . '/configs/';

	$map = db_fetch_row_prepared('SELECT weathermap_maps.*
		FROM weathermap_auth, weathermap_maps
		WHERE weathermap_maps.id = weathermap_auth.mapid
		AND active = "on"
		AND (userid = ? OR userid = 0)
		AND weathermap_maps.id = ?',
		array($_SESSION['sess_user_id'], $leaf['item_id']));

	if (cacti_sizeof($map)) {
		$htmlfile = $outdir . 'weathermap_' . $map['id'] . '.html';
		$maptitle = $map['titlecache'];

		if ($maptitle == '') {
			$maptitle = __('Map for config file: %s', $map['configfile'], 'weathermap');
		}

		print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>";

		?>
		<tr class='even'>
			<td>
				<table width='100%' cellpadding='0' cellspacing='0'>
					<tr>
						<td class='textHeader' nowrap><?php print $maptitle; ?></td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
		print '<tr><td>';

		if (file_exists($htmlfile)) {
			include($htmlfile);
		}

		print '</td></tr>';
		print '</table>';
	}
}

// calculate the name that cacti will use for this item in the tree views
function weathermap_tree_item_name($item_id) {
	$description = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($item_id));

	if ($description == '') {
		$configfile  = db_fetch_cell_prepared('SELECT configfile
			FROM weathermap_maps
			WHERE id = ?',
			array($item_id));

		$description = __('Map for config file: %s', $configfile, 'weathermap');
	}


	return $description;
}

// the edit form, for when you add or edit a map in a graph tree
function weathermap_tree_item_edit($tree_item) {
	form_alternate_row();

	$titles = db_fetch_assoc("SELECT id, CONCAT_WS('',titlecache,' (', configfile, ')') AS name
		FROM weathermap_maps
		WHERE active = 'on'
		ORDER BY titlecache, configfile");

	print "<td width='50%'><font class='textEditTitle'>" . __('Map', 'weathermap') . '</font><br />' . __('Choose which weathermap to add to the tree.', 'weathermap') . '</td><td>';

	form_dropdown('item_id', $titles, 'name', 'id', $tree_item['item_id'], '', '0');

	print '</td></tr>';

	form_alternate_row();

	print '<td width="50%"><font class="textEditTitle">' . __('Style', 'weathermap') . '</font><br />' . __('How should the map be displayed?', 'weathermap') . '</td><td>';

	print '<select name="item_options">
		<option value="1">' . __('Thumbnail', 'weathermap') . '</option>
		<option value="2">' . __('Full Size', 'weathermap') . '</option></select>';

	print '</td></tr>';
}

function weathermap_show_tab() {
	global $config;

	$tabstyle = read_config_option('superlinks_tabstyle');

	if (api_plugin_user_realm_auth('weathermap-cacti-plugin.php')) {
		if ($tabstyle > 0) {
			$prefix = 's_';
		} else {
			$prefix = '';
		}

		print '<a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php"><img src="' . $config['url_path'] . 'plugins/weathermap/images/' . $prefix . 'tab_weathermap';

		if (preg_match('/plugins\/weathermap\/weathermap-cacti-plugin.php/', $_SERVER['REQUEST_URI'], $matches)) {
			print '_red';
		}

		print '.gif" alt="weathermap" align="absmiddle" border="0"></a>';
	}

	weathermap_setup_table();
}

function weathermap_draw_navigation_text($nav) {
	$nav['weathermap-cacti-plugin.php:'] = array(
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:viewmap'] = array(
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:liveview'] = array(
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:liveviewimage'] = array(
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:viewmapcycle'] = array(
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:mrss'] = array(
		'title'   => __('Weathermaps', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:viewimage'] = array(
		'title'   => __('View Map Image', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin.php:viewthumb'] = array(
		'title'   => __('View Map Thumbnail', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:'] = array(
		'title'   => __('Weathermaps', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:addmap_picker'] = array(
		'title'   => __('Add Map', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:viewconfig'] = array(
		'title'   => __('View Configuration', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:,weathermap-cacti-plugin-mgmt.php:addmap_picker',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '3'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:addmap'] = array(
		'title'   => __('Add Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:editmap'] = array(
		'title'   => __('Edit Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:editor'] = array(
		'title'   => __('Weathermap Editor', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:perms_edit'] = array(
		'title'   => __('Edit Permissions', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings'] = array(
		'title'   => __('Map Settings', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_form'] = array(
		'title'   => __('Map Settings', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_delete'] = array(
		'title'   => __('Map Settings Delete', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_update'] = array(
		'title'   => __('Map Settings Update', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_add'] = array(
		'title'   => __('Map Settings Add', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:perms_edit'] = array(
		'title'   => __('Permissions Edit', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:perms_add_user'] = array(
		'title'   => __('Add User', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:perms_delete_user'] = array(
		'title'   => __('Delete User', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:delete_map'] = array(
		'title'   => __('Delete Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:move_map_down'] = array(
		'title'   => __('Move Map Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:move_map_up'] = array(
		'title'   => __('Move Map Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:move_group_down'] = array(
		'title'   => __('Move Group Down', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:move_group_up'] = array(
		'title'   => __('Move Group Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:group_form'] = array(
		'title'   => __('Group Edit', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:group_update'] = array(
		'title'   => __('Group Update', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:activate_map'] = array(
		'title'   => __('Activate Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:deactivate_map'] = array(
		'title'   => __('Deactivate Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:rebuildnow'] = array(
		'title'   => __('Rebuild Now', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:chgroup'] = array(
		'title'   => __('Change Group', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:chgroup_update'] = array(
		'title'   => __('Group Update', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:groupadmin'] = array(
		'title'   => __('Group Admin', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	$nav['weathermap-cacti-plugin-mgmt.php:groupadmin_delete'] = array(
		'title'   => __('Group Admin Delete', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	);

	return $nav;
}

function weathermap_poller_output(&$rrd_update_array) {
	global $config;

	cacti_log('WM poller_output: STARTING', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

	$requiredlist = db_fetch_assoc('SELECT DISTINCT wmd.id, wmd.last_value, wmd.last_time, wmd.data_source_name,
		dtd.data_source_path, dtd.local_data_id, dtr.data_source_type_id
		FROM weathermap_data AS wmd
		INNER JOIN data_template_data AS dtd
		ON wmd.local_data_id = dtd.local_data_id
		INNER JOIN data_template_rrd AS dtr
		ON wmd.local_data_id = dtr.local_data_id
		WHERE wmd.local_data_id > 0');

	$path_rra = $config['rra_path'];

	/**
	 * especially on Windows, it seems that filenames are not reliable
	 * (sometimes \ and sometimes / even though path_rra is always /) .
	 * let's make an index from local_data_id to filename, and then
	 * use local_data_id as the key...
	 */
	foreach (array_keys($rrd_update_array) as $key) {
		if (isset($rrd_update_array[$key]['times']) && is_array($rrd_update_array[$key]['times'])) {
			cacti_log("WM poller_output: Adding $key", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

			$knownfiles[$rrd_update_array[$key]['local_data_id']] = $key;
		}
	}

	foreach ($requiredlist as $required) {
		$file          = str_replace('<path_rra>', $path_rra, $required['data_source_path'] );
		$dsname        = $required['data_source_name'];
		$local_data_id = $required['local_data_id'];

		if (isset($knownfiles[$local_data_id])) {
			$file2 = $knownfiles[$local_data_id];

			if ($file2 != '') {
				$file = $file2;
			}
		}

		cacti_log("WM poller_output: Looking for $file ($local_data_id) ({$required['data_source_path']})", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

		if (isset($rrd_update_array[$file]) &&
			is_array($rrd_update_array[$file]) &&
			isset($rrd_update_array[$file]['times']) &&
			is_array($rrd_update_array[$file]['times']) &&
			isset($rrd_update_array[$file]['times'][key($rrd_update_array[$file]['times'])][$dsname])) {

			$value = $rrd_update_array[$file]['times'][key($rrd_update_array[$file]['times'])][$dsname];
			$time  = key($rrd_update_array[$file]['times']);

			cacti_log("WM poller_output: Got one! $file:$dsname -> $time $value", true, 'WEATHERMAP', POLLER_VERBOSITY_MEDIUM);

			$period  = $time - $required['last_time'];
			$lastval = $required['last_value'];

			if (empty($period)) {
				$period = 60;
			}

			// if the new value is a NaN, we'll give 0 instead, and pretend it didn't happen from the point
			// of view of the counter etc. That way, we don't get those enormous spikes. Still doesn't deal with
			// reboots very well, but it should improve it for drops.
			if ($value == 'U') {
				$newvalue     = 0;
				$newlastvalue = $lastval;
				$newtime      = $required['last_time'];
			} else {
				$newlastvalue = $value;
				$newtime      = $time;

				switch ($required['data_source_type_id']) {
					case 1: //GAUGE
						$newvalue = $value;

						break;
					case 2: //COUNTER
						if ($value >= $lastval) {
							// Everything is normal
							$newvalue = $value - $lastval;
						} else {
							// Possible overflow, see if its 32bit or 64bit
							if ($lastval > 4294967295) {
								$newvalue = (18446744073709551615 - $lastval) + $value;
							} else {
								$newvalue = (4294967295 - $lastval) + $value;
							}
						}

						$newvalue = $newvalue / $period;

						break;
					case 3: //DERIVE
						$newvalue = ($value - $lastval) / $period;

						break;
					case 4: //ABSOLUTE
						$newvalue = $value / $period;

						break;
					default: // do something somewhat sensible in case something odd happens
						$newvalue = $value;

						wm_warn("poller_output found an unknown data_source_type_id for $file:$dsname");

						break;
				}
			}

			db_execute_prepared('UPDATE weathermap_data
				SET `last_time` = ?, `last_calc` = ?, `last_value` = ?,`sequence`=`sequence`+1
				WHERE `id` = ?',
				array($newtime, $newvalue, $newlastvalue, $required['id']));

			cacti_log("WM poller_output: Final value is $newvalue (was $lastval, period was $period)", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
		} else {
			cacti_log('WM poller_output: Didn\'t find it.', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
			cacti_log('WM poller_output: DID find these:', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

			foreach (array_keys($rrd_update_array) as $key) {
				$local_data_id = $rrd_update_array[$key]['local_data_id'];

				cacti_log("WM poller_output:    $key ($local_data_id)", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
			}
		}
	}

	cacti_log('WM poller_output: ENDING', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

	return $rrd_update_array;
}

function weathermap_poller_bottom() {
	global $config;
	global $weathermap_debugging;

	$weathermap_version = plugin_weathermap_numeric_version();

	include_once(__DIR__ . '/lib/poller-common.php');

	weathermap_setup_table();

	$renderperiod  = read_config_option('weathermap_render_period', true);
	$rendercounter = read_config_option('weathermap_render_counter', true);
	$quietlogging  = read_config_option('weathermap_quiet_logging', true);

	cacti_log("WM Counter is $rendercounter. period is $renderperiod.", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);

	if ($renderperiod < 0) {
		// manual updates only
		if ($quietlogging == 0) {
			cacti_log("WM Version: $weathermap_version - Manual Updates Only", true, 'WEATHERMAP');
		}

		return;
	} else {
		if ($renderperiod == 0 || $rendercounter == '' || $rendercounter % $renderperiod == 0 || $rendercounter > $renderperiod) {
			weathermap_run_maps(__DIR__);

			$newcount = 1;
		} else {
			if ($quietlogging == 0) {
				cacti_log("WM Version: $weathermap_version - No Updates this Cycle ($rendercounter)", true, 'WEATHERMAP');
			}

			$newcount = $rendercounter + 1;
		}

		set_config_option('weathermap_render_counter', $newcount);

		// Delete old users
		db_execute('DELETE FROM weathermap_auth WHERE userid > 0 AND userid NOT IN (SELECT id FROM user_auth)');
	}
}

