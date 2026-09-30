<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
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

/**
 * Creates (if not already present) all of this plugin's database
 * tables (maps, data, groups, settings, auth) and applies incremental
 * schema updates for existing installations. Called from
 * plugin_weathermap_install() and during upgrade processing.
 *
 * @return void
 */
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
				foreach ($rows as $row) {
					db_execute_prepared('DELETE FROM weathermap_settings
						WHERE mapid = ? AND groupid = ? AND optname = ?
						LIMIT 1',
						[$row['mapid'], $row['groupid'], $row['optname']]);
				}
			} else {
				break;
			}
		}
	}
}
