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

require_once(__DIR__ . '/includes/database.php');

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_weathermap_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

if (!defined('WM_COPYRIGHT_YEARS')) {
	define('WM_COPYRIGHT_YEARS', '2008-2026');
}

/**
 * Plugin install hook: registers all of this plugin's Cacti hooks
 * (config arrays/settings, header tabs, navigation text, page title/
 * refresh, poller integration), registers its three admin realms, and
 * creates its database tables. Called by Cacti's plugin architecture
 * when the plugin is installed.
 *
 * @return void
 */
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

/**
 * Plugin uninstall hook: clears the recorded plugin version and drops
 * this plugin's core database tables (auth, data, maps, groups,
 * settings); the weathermap_config_cache table created by
 * create_prime_mapcache() is not dropped here. Called by Cacti's
 * plugin architecture when the plugin is uninstalled.
 *
 * @return void
 */
function plugin_weathermap_uninstall() {
	set_config_option('weathermap_version', '');

	db_execute('DROP TABLE IF EXISTS weathermap_auth');
	db_execute('DROP TABLE IF EXISTS weathermap_data');
	db_execute('DROP TABLE IF EXISTS weathermap_maps');
	db_execute('DROP TABLE IF EXISTS weathermap_groups');
	db_execute('DROP TABLE IF EXISTS weathermap_settings');
}

/**
 * Reads and returns this plugin's version/author/metadata info from its
 * INFO file. Called wherever plugin metadata is needed (e.g.
 * plugin_weathermap_upgrade(), plugin_weathermap_numeric_version()).
 *
 * @return array The plugin's info array, as parsed from the INFO
 *              file's '[info]' section.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_weathermap_version() {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/weathermap/INFO', true) ?: [];

	return $info['info'] ?? [];
}

/**
 * Returns just this plugin's numeric version string, cached statically
 * after the first call. Called wherever only the plugin's version
 * number (not the full INFO array) is needed.
 *
 * @return string The plugin's version string.
 */
function plugin_weathermap_numeric_version() {
	static $current;

	if ($current == null) {
		$current = plugin_weathermap_version();
	}

	return $current['version'];
}

/**
 * Plugin config-check hook: ensures the plugin's schema/hooks are up to
 * date by delegating to plugin_weathermap_upgrade(). Called by Cacti's
 * plugin architecture on relevant page loads.
 *
 * @return bool Always true.
 */
function plugin_weathermap_check_config() {
	plugin_weathermap_upgrade();

	return true;
}

/**
 * Checks whether the plugin's recorded database version differs from
 * its actual (INFO file) version and, if so, updates realm display
 * names/file associations, updates the plugin_config record, clears a
 * stale page_head hook registration, and repairs any weathermap files
 * with missing/incorrect map metadata. Only runs on
 * index.php/plugins.php or weathermap-cacti-prefixed pages. Called
 * from plugin_weathermap_check_config() and directly by Cacti's plugin
 * architecture on upgrade.
 *
 * @return bool|null Null if this isn't a relevant page (skipped early
 *                   via a bare return); otherwise false after
 *                   completing the upgrade checks.
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the poller-common library.
 */
function plugin_weathermap_upgrade() {
	global $config;

	$files = ['index.php', 'plugins.php'];

	if (!in_array(get_current_page(), $files, true) && strpos(get_current_page(), 'weathermap-cacti') === false) {
		return null;
	}

	include_once($config['base_path'] . '/plugins/weathermap/lib/poller-common.php');

	$current = plugin_weathermap_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory = 'weathermap'");

	if ($current != $old) {
		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			['View Weathermaps', 'weathermap-cacti-plugin.php']);

		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			['Edit Weathermaps', 'weathermap-cacti-plugin-editor.php']);

		db_execute_prepared('UPDATE plugin_realms
			SET display = ? WHERE file = ?',
			['Manage Weathermap', 'weathermap-cacti-plugin-mgmt.php']);

		db_execute_prepared('UPDATE plugin_realms
			SET file = ? WHERE file = ?',
			['weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php', 'weathermap-cacti-plugin-mgmt.php']);

		// update the plugin information
		$info = plugin_weathermap_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='weathermap'");

		db_execute_prepared('UPDATE plugin_config
			SET name = ?, author = ?, webpage = ?, version = ?
			WHERE id = ?',
			[
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['version'],
				$id
			]
		);

		db_execute('DELETE FROM plugin_hooks WHERE name = "weathermap" AND hook = "page_head"');

		// Remove files tombstoned in manifest.json plus the dev-only tests/ tree.
		weathermap_prune_files();

		weathermap_repair_maps();
	}

	return false;
}

/**
 * Poller_top hook: records the current time (rounded down to the
 * nearest minute) as this poller cycle's start time, used for
 * crontab-style map refresh scheduling. Called by Cacti's poller via
 * the 'poller_top' hook.
 *
 * @return void
 *
 * @global int $weathermap_poller_start_time Set here to the rounded
 *                                           current Unix timestamp.
 */
function weathermap_poller_top() {
	global $weathermap_poller_start_time;

	$n = time();

	// round to the nearest minute, since that's all we need for the crontab-style stuff
	$weathermap_poller_start_time = $n - ($n % 60);
}

/**
 * Page_title hook: appends the current map's cached title to Cacti's
 * page title when viewing a specific weathermap. Called by Cacti's
 * page rendering via the 'page_title' hook.
 *
 * @param string $t The page title being built up.
 *
 * @return string The (possibly augmented) page title.
 */
function weathermap_page_title($t) {
	if (preg_match('/plugins\/weathermap\//', $_SERVER['REQUEST_URI'], $matches)) {
		if (preg_match('/plugins\/weathermap\/weathermap-cacti-plugin.php\?action=viewmap&id=([^&]+)/', $_SERVER['REQUEST_URI'], $matches)) {
			$mapid = $matches[1];

			if (preg_match('/^\d+$/', $mapid)) {
				$title = db_fetch_cell_prepared('SELECT titlecache FROM weathermap_maps WHERE id = ?', [$mapid]);
			} else {
				$title = db_fetch_cell_prepared('SELECT titlecache FROM weathermap_maps WHERE filehash = ?', [$mapid]);
			}

			if ($title != '') {
				$t .= ' > ' . $title;
			}
		}

		return ($t);
	}

	return ($t);
}

/**
 * Top_graph_refresh hook: overrides Cacti's page auto-refresh interval
 * while viewing a weathermap - disabling Cacti's own reload during
 * map-cycling (self-managed) and otherwise using the user's configured
 * page refresh interval. Called by Cacti's page rendering via the
 * 'top_graph_refresh' hook.
 *
 * @param int $refresh The default refresh interval being overridden.
 *
 * @return int The overridden refresh interval, or the unmodified
 *            $refresh value when not on the weathermap viewer page.
 */
function weathermap_top_graph_refresh($refresh) {
	if (basename($_SERVER['PHP_SELF']) != 'weathermap-cacti-plugin.php') {
		return $refresh;
	}

	// if we're cycling maps, then we want to handle reloads ourselves, thanks
	if (isset_request_var('action') && get_request_var('action') == 'viewmapcycle') {
		return (86400);
	}

	if (get_request_var('action') == '' || get_request_var('action') == 'viewmap') {
		return (read_user_setting('page_refresh'));
	}

	return ($refresh);
}

/**
 * Config_settings hook: registers this plugin's 'Weathermap' settings
 * tab and all of its configuration fields (debug mode and related
 * general options). Called by Cacti's settings framework via the
 * 'config_settings' hook.
 *
 * @return void
 *
 * @global array $tabs     Cacti's settings tabs registry; appended with
 *                        this plugin's tab.
 * @global array $settings Cacti's settings fields registry; appended
 *                        with this plugin's fields.
 */
function weathermap_config_settings() {
	global $tabs, $settings;

	$tabs['wmap'] = __('Weathermap', 'weathermap');

	$temp = [
		'weathermap_header' => [
			'friendly_name' => __('Network Weathermap', 'weathermap'),
			'method'        => 'spacer',
		],
		'weathermap_debug' => [
			'friendly_name' => __('Debug Mode', 'weathermap'),
			'description'   => __('If you wish to see detailed status information in your Cacti log about the weathermap generation process, check this box.', 'weathermap'),
			'method'        => 'checkbox',
			'default'       => ''
		],
		'weathermap_infourl_style' => [
			'friendly_name' => __('Info URL Style', 'weathermap'),
			'description'   => __('When adding Graphs for a Node or Link, what click through do you wish to redirect to using the Info URL?  If using the Standard Graph View, you can have multiple Graphs.  For the Time Graph View, you may only have one Graph.', 'weathermap'),
			'method'        => 'drop_array',
			'default'       => 0,
			'array'         => [
				0 => __('Standard Graph View', 'weathermap'),
				1 => __('Time Graph View', 'weathermap')
			]
		],
		'weathermap_pagestyle' => [
			'friendly_name' => __('Page style', 'weathermap'),
			'description'   => __('How to display multiple maps.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				0 => __('Thumbnail Overview', 'weathermap'),
				1 => __('Full Images', 'weathermap'),
				2 => __('Show Only First', 'weathermap'),
			]
		],
		'weathermap_thumbsize' => [
			'friendly_name' => __('Thumbnail Maximum Size', 'weathermap'),
			'default'       => 1000,
			'description'   => __('The maximum width or height for thumbnails in thumbnail view, in pixels. Takes effect after the next poller run.', 'weathermap'),
			'method'        => 'textbox',
			'size'          => 3,
			'max_length'    => 4,
		],
		'weathermap_width' => [
			'friendly_name' => __('Hover Graph Default Width', 'weathermap'),
			'description'   => __('The default width of the RRDtool Graphs that appear when you hover on a Link.', 'weathermap'),
			'method'        => 'textbox',
			'default'       => 400,
			'size'          => 3,
			'max_length'    => 4,
		],
		'weathermap_height' => [
			'friendly_name' => __('Hover Graph Default Height', 'weathermap'),
			'description'   => __('The default height of the RRDtool Graphs that appear when you hover on a Link.', 'weathermap'),
			'method'        => 'textbox',
			'default'       => 125,
			'size'          => 3,
			'max_length'    => 4,
		],
		'weathermap_nolegend' => [
			'friendly_name' => __('Hover Graph Style', 'weathermap'),
			'description'   => __('When hovering over the Links or Nodes, what style of Graph is to be displayed?', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				'thumb' => __('Thumbnail Graphs', 'weathermap'),
				'full'  => __('Full Graphs', 'weathermap')
			]
		],
		'weathermap_timeout' => [
			'friendly_name' => __('Map Processing Timeout', 'weathermap'),
			'description'   => __('How much time should be allowed before timing out the periodic map generation process.', 'weathermap'),
			'method'        => 'drop_array',
			'default'       => 300,
			'array'         => [
				'300'  => __('%d Minutes', 5, 'weathermap'),
				'600'  => __('%d Minutes', 10, 'weathermap'),
				'900'  => __('%d Minutes', 15, 'weathermap'),
				'1200' => __('%d Minutes', 20, 'weathermap')
			]
		],
		'weathermap_cycle_refresh' => [
			'friendly_name' => __('Refresh Time', 'weathermap'),
			'description'   => __('How often to refresh the page in Cycle mode. Automatic makes all available maps fit into 5 minutes.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				0   => __('Automatic', 'weathermap'),
				5   => __('%d Seconds', 5,  'weathermap'),
				15  => __('%d Seconds', 15, 'weathermap'),
				30  => __('%d Seconds', 30, 'weathermap'),
				60  => __('%d Minute',  1,  'weathermap'),
				120 => __('%d Minutes', 2,  'weathermap'),
				300 => __('%d Minutes', 3,  'weathermap'),
			]
		],
		'weathermap_output_format' => [
			'friendly_name' => __('Output Format', 'weathermap'),
			'description'   => __('What format do you prefer for the generated map images and thumbnails?', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				'png' => __('PNG (default)', 'weathermap'),
				'jpg' => __('JPEG', 'weathermap'),
				'gif' => __('GIF', 'weathermap'),
			]
		],
		'weathermap_render_period' => [
			'friendly_name' => __('Map Rendering Interval', 'weathermap'),
			'description'   => __('How often do you want Weathermap to recalculate it\'s maps? You should not touch this unless you know what you are doing! It is mainly needed for people with non-standard polling setups.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
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
			],
		],
		'weathermap_showversion' => [
			'friendly_name' => __('Show Weathermap Help Links', 'weathermap'),
			'description'   => __('If checked, all Weathermap pages will include a link to documentation.', 'weathermap'),
			'method'        => 'checkbox',
			'default'       => ''
		],
		'weathermap_all_tab' => [
			'friendly_name' => __('Show \'All\' Tab', 'weathermap'),
			'description'   => __('When using groups, add an \'All Maps\' tab to the tab bar.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				0 => __('No (default)', 'weathermap'),
				1 => __('Yes', 'weathermap'),
			]
		],
		'weathermap_map_selector' => [
			'friendly_name' => __('Show Map Selector', 'weathermap'),
			'description'   => __('Show a combo-box map selector on the full-screen map view.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				0 => __('No', 'weathermap'),
				1 => __('Yes (default)', 'weathermap'),
			]
		],
		'weathermap_quiet_logging' => [
			'friendly_name' => __('Quiet Logging', 'weathermap'),
			'description'   => __('By default, even in LOW level logging, Weathermap logs normal activity. This makes it REALLY log only errors in LOW mode.', 'weathermap'),
			'method'        => 'drop_array',
			'array'         => [
				0 => __('Chatty (default)', 'weathermap'),
				1 => __('Quiet', 'weathermap'),
			]
		]
	];

	if (isset($settings['wmap'])) {
		$settings['wmap'] = array_merge($settings['wmap'], $temp);
	} else {
		$settings['wmap'] = $temp;
	}
}


/**
 * Ensures the global 'rrd_use_poller_output' weathermap setting is
 * enabled whenever Cacti's Boost RRD update feature is enabled
 * (inserting or updating the setting row as needed), since Boost
 * changes how RRD data becomes available to the poller. Called during
 * poller/config initialization to keep the two features in sync.
 *
 * @return void
 */
function weathermap_check_set_boost() {
	$boost = read_config_option('boost_rrd_update_enable') == 'on' ? true : false;

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
				[$exists['id']]);
		}
	}
}

/**
 * Config_arrays hook: triggers an upgrade check, registers this plugin
 * as a custom graph-tree item type (when Cacti's tree handler API
 * supports it), adds its Management menu entries, declares its realm
 * names for i18n, and augments Cacti's role system to grant the
 * appropriate weathermap realms to the Normal User/General
 * Administration roles. Called by Cacti's plugin framework via the
 * 'config_arrays' hook on every page load.
 *
 * @return void
 *
 * @global array $menu                Cacti's admin menu registry;
 *                                    appended with this plugin's
 *                                    Management entries.
 * @global array $tree_item_types     Map of tree item type id => display
 *                                    label; registered here for
 *                                    weathermap type 10 when supported.
 * @global array $tree_item_handlers  Map of tree item type id => handler
 *                                    callback names; registered here for
 *                                    weathermap type 10 when supported.
 */
function weathermap_config_arrays() {
	global $menu;
	global $tree_item_types, $tree_item_handlers;

	plugin_weathermap_upgrade();

	// if there is support for custom graph tree types, then register ourselves
	if (isset($tree_item_handlers)) {
		$tree_item_types[10] = __('Weathermap', 'weathermap');

		$tree_item_handlers[10] = [
			'render' => 'weathermap_tree_item_render',
			'name'   => 'weathermap_tree_item_name',
			'edit'   => 'weathermap_tree_item_edit'
		];
	}

	$wm_menu = [
		'plugins/weathermap/weathermap-cacti-plugin-mgmt.php'        => __('Weathermaps', 'weathermap'),
		'plugins/weathermap/weathermap-cacti-plugin-mgmt-groups.php' => __('Weathermap Groups', 'weathermap')
	];

	$menu[__('Management')]['plugins/weathermap/weathermap-cacti-plugin-mgmt.php'] = $wm_menu;

	// These simply need to be declared for i18n the realm names
	$realm_array = [
		__('View Weathermaps', 'weathermap'),
		__('Edit Weathermaps', 'weathermap'),
		__('Manage Weathermap', 'weathermap')
	];

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles_byname(__('General Administration'), 'Manage Weathermap');
		auth_augment_roles_byname(__('General Administration'), 'Edit Weathermaps');
		auth_augment_roles_byname(__('Normal User'), 'View Weathermaps');
	}
}

/**
 * Graph-tree render handler for a weathermap tree leaf: renders the
 * map's pre-generated HTML output file (if it exists) inside a titled
 * box, for users authorized to view that specific map. Called by
 * Cacti's graph tree rendering for tree items of this plugin's
 * registered type (10).
 *
 * @param array $leaf The tree leaf item data, including 'item_id' (the
 *                    weathermap_maps id).
 *
 * @return void
 */
function weathermap_tree_item_render($leaf) {
	$outdir  = __DIR__ . '/output/';
	$confdir = __DIR__ . '/configs/';

	$map = db_fetch_row_prepared('SELECT weathermap_maps.*
		FROM weathermap_auth, weathermap_maps
		WHERE weathermap_maps.id = weathermap_auth.mapid
		AND active = "on"
		AND (userid = ? OR userid = 0)
		AND weathermap_maps.id = ?',
		[$_SESSION['sess_user_id'], $leaf['item_id']]);

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
/**
 * Resolves the display name Cacti's tree views should use for a
 * weathermap tree item, falling back to a description of the map's
 * config file if it has no cached title. Called by Cacti's graph tree
 * rendering for tree items of this plugin's registered type.
 *
 * @param int $item_id The weathermap_maps id to resolve a name for.
 *
 * @return string The map's display name.
 */
function weathermap_tree_item_name($item_id) {
	$description = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		[$item_id]);

	if ($description == '') {
		$configfile  = db_fetch_cell_prepared('SELECT configfile
			FROM weathermap_maps
			WHERE id = ?',
			[$item_id]);

		$description = __('Map for config file: %s', $configfile, 'weathermap');
	}

	return $description;
}

// the edit form, for when you add or edit a map in a graph tree
/**
 * Renders the add/edit form fields for a weathermap graph-tree item:
 * a map selection dropdown and a display style (thumbnail/full size)
 * selector. Called by Cacti's graph tree item edit page for items of
 * this plugin's registered type.
 *
 * @param array $tree_item The tree item's current data, including
 *                         'item_id' for pre-selecting the current map.
 *
 * @return void
 */
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

/**
 * Top_header_tabs/top_graph_header_tabs hook: prints the Weathermap tab
 * icon/link in Cacti's page header for authorized users, honoring the
 * configured superlinks tab style and using the 'active' icon variant
 * when currently viewing the weathermap page, then ensures the
 * plugin's database tables exist. Called by Cacti's header rendering
 * via the 'top_header_tabs'/'top_graph_header_tabs' hooks.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build the tab's URL and image paths.
 */
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

/**
 * Draw_navigation_text hook: registers the breadcrumb/navigation title
 * entries for this plugin's viewer/management/editor pages and their
 * sub-views. Called by Cacti's navigation framework via the
 * 'draw_navigation_text' hook.
 *
 * @param array $nav The navigation entries array being built up.
 *
 * @return array The $nav array with this plugin's entries added.
 */
function weathermap_draw_navigation_text($nav) {
	$nav['weathermap-cacti-plugin.php:'] = [
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:viewmap'] = [
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:liveview'] = [
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:liveviewimage'] = [
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:viewmapcycle'] = [
		'title'   => __('Weathermap', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:mrss'] = [
		'title'   => __('Weathermaps', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:viewimage'] = [
		'title'   => __('View Map Image', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin.php:viewthumb'] = [
		'title'   => __('View Map Thumbnail', 'weathermap'),
		'mapping' => '',
		'url'     => 'weathermap-cacti-plugin.php',
		'level'   => '0'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:'] = [
		'title'   => __('Weathermaps', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:addmap_picker'] = [
		'title'   => __('Add Map', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:viewconfig'] = [
		'title'   => __('View Configuration', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:,weathermap-cacti-plugin-mgmt.php:addmap_picker',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '3'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:addmap'] = [
		'title'   => __('Add Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:editmap'] = [
		'title'   => __('Edit Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:editor'] = [
		'title'   => __('Weathermap Editor', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:perms_edit'] = [
		'title'   => __('Edit Permissions', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings'] = [
		'title'   => __('Map Settings', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_form'] = [
		'title'   => __('Map Settings', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_delete'] = [
		'title'   => __('Map Settings Delete', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_update'] = [
		'title'   => __('Map Settings Update', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:map_settings_add'] = [
		'title'   => __('Map Settings Add', 'weathermap'),
		'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:',
		'url'     => '',
		'level'   => '2'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:perms_edit'] = [
		'title'   => __('Permissions Edit', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:perms_add_user'] = [
		'title'   => __('Add User', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:perms_delete_user'] = [
		'title'   => __('Delete User', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:delete_map'] = [
		'title'   => __('Delete Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:move_map_down'] = [
		'title'   => __('Move Map Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:move_map_up'] = [
		'title'   => __('Move Map Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:move_group_down'] = [
		'title'   => __('Move Group Down', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:move_group_up'] = [
		'title'   => __('Move Group Up', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:group_form'] = [
		'title'   => __('Group Edit', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:group_update'] = [
		'title'   => __('Group Update', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:activate_map'] = [
		'title'   => __('Activate Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:deactivate_map'] = [
		'title'   => __('Deactivate Map', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:rebuildnow'] = [
		'title'   => __('Rebuild Now', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:chgroup'] = [
		'title'   => __('Change Group', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:chgroup_update'] = [
		'title'   => __('Group Update', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:groupadmin'] = [
		'title'   => __('Group Admin', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	$nav['weathermap-cacti-plugin-mgmt.php:groupadmin_delete'] = [
		'title'   => __('Group Admin Delete', 'weathermap'),
		'mapping' => 'index.php:',
		'url'     => 'weathermap-cacti-plugin-mgmt.php',
		'level'   => '1'
	];

	global $config;
	$management                                   = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php';
	$nav['weathermap-cacti-plugin-mgmt.php:root'] = ['title' => __('Weathermaps', 'weathermap'), 'mapping' => 'index.php:', 'url' => $management, 'level' => '1'];
	$nav['weathermap-cacti-plugin-mgmt.php:']     = ['title' => __('Manage', 'weathermap'), 'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:root', 'url' => $management, 'level' => '2'];

	foreach (['map_settings', 'map_settings_form', 'perms_edit'] as $action) {
		$key                  = 'weathermap-cacti-plugin-mgmt.php:' . $action;
		$nav[$key]['mapping'] = 'index.php:,weathermap-cacti-plugin-mgmt.php:root,weathermap-cacti-plugin-mgmt.php:';
		$nav[$key]['url']     = $management;
		$nav[$key]['level']   = '3';
	}

	if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'settings.php' && (isset_request_var('tab') ? get_nfilter_request_var('tab') : ($_SESSION['sess_config_settings_tab'] ?? '')) === 'wmap') {
		$management = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php';

		foreach (['settings.php:', 'settings.php:edit'] as $key) {
			$nav[$key] = ['title' => __('Settings', 'weathermap'), 'mapping' => 'index.php:,weathermap-cacti-plugin-mgmt.php:root', 'url' => $management, 'level' => '2'];
		}
	}

	$viewer                                          = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php';
	$nav['wm-viewer-root:']                          = ['title' => __('Weathermap', 'weathermap'), 'mapping' => '', 'url' => $viewer, 'level' => '0'];
	$nav['weathermap-cacti-plugin.php:viewmapcycle'] = ['title' => __('Automatically cycle', 'weathermap'), 'mapping' => 'wm-viewer-root:', 'url' => $viewer . '?action=viewmapcycle', 'level' => '1'];

	if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'weathermap-cacti-plugin.php' && get_nfilter_request_var('action') === 'viewmap') {
		$hash = get_nfilter_request_var('id');

		if (is_string($hash) && preg_match('/^[a-f0-9]{20,64}$/i', $hash)) {
			$map = db_fetch_row_prepared("SELECT id, titlecache, configfile FROM weathermap_maps WHERE filehash = ? AND active = 'on'", [$hash]);
			require_once __DIR__ . '/lib/WeatherMap.functions.php';

			if (is_array($map) && $map && is_weathermap_allowed($map['id'], $_SESSION['sess_user_id'] ?? 0)) {
				$nav['weathermap-cacti-plugin.php:viewmap'] = ['title' => $map['titlecache'] ?: $map['configfile'], 'mapping' => 'wm-viewer-root:', 'url' => $viewer . '?action=viewmap&id=' . rawurlencode($hash), 'level' => '1'];
			}
		}
	}
	$hash = get_nfilter_request_var('wm_map');

	$graph_page = basename($_SERVER['SCRIPT_NAME'] ?? '');

	if (in_array($graph_page, ['graph.php', 'graph_view.php'], true) && is_string($hash) && preg_match('/^[a-f0-9]{20,64}$/i', $hash)) {
		$map = db_fetch_row_prepared("SELECT id, filehash, titlecache, configfile FROM weathermap_maps WHERE filehash = ? AND active = 'on'", [$hash]);
		require_once __DIR__ . '/lib/WeatherMap.functions.php';

		if (is_array($map) && $map && is_weathermap_allowed($map['id'], $_SESSION['sess_user_id'] ?? 0)) {
			$base                   = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php';
			$nav['wm-return-list:'] = ['title' => __('Weathermap', 'weathermap'), 'mapping' => '', 'url' => $base, 'level' => '0'];
			$nav['wm-return-map:']  = ['title' => $map['titlecache'] ?: $map['configfile'], 'mapping' => 'wm-return-list:', 'url' => $base . '?action=viewmap&id=' . rawurlencode($map['filehash']), 'level' => '1'];

			foreach (array_keys($nav) as $key) {
				if (str_starts_with($key, $graph_page . ':')) {
					$nav[$key]['mapping'] = 'wm-return-list:,wm-return-map:';
					$nav[$key]['level']   = '2';

					if ($graph_page === 'graph.php') {
						$nav[$key]['url'] = $base . '?action=viewmap&id=' . rawurlencode($map['filehash']);
					}
				}
			}
		}
	}

	return $nav;
}

/**
 * Poller_output hook: intercepts the poller's just-collected RRD update
 * values before they're written to disk, matching them against this
 * plugin's tracked data-item definitions by data source name/path,
 * computing the delta/rate value appropriate to each data source's
 * type (GAUGE/COUNTER/DERIVE/ABSOLUTE, including 32/64-bit counter
 * overflow handling), and storing the computed value into
 * weathermap_data for use by map rendering - all without altering the
 * data actually passed on to Cacti's own RRD update. Called by Cacti's
 * poller via the 'poller_output' hook on every polling cycle.
 *
 * @param array $rrd_update_array Reference, the poller's collected RRD
 *                                update data for this cycle.
 *
 * @return array The unmodified $rrd_update_array (this hook only reads
 *              it to compute Weathermap's own derived values).
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the RRA storage path.
 */
function weathermap_poller_output(&$rrd_update_array) {
	global $config;

	static $debug = null;

	if ($debug === null) {
		$debug = intval(read_config_option('log_verbosity')) >= 5 ? true : false;
	}

	if ($debug) {
		cacti_log('WM poller_output: STARTING', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
	}

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
			if ($debug) {
				cacti_log("WM poller_output: Adding $key", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
			}

			$knownfiles[$rrd_update_array[$key]['local_data_id']] = $key;
		}
	}

	foreach ($requiredlist as $required) {
		$file          = str_replace('<path_rra>', $path_rra, $required['data_source_path']);
		$dsname        = $required['data_source_name'];
		$local_data_id = $required['local_data_id'];

		if (isset($knownfiles[$local_data_id])) {
			$file2 = $knownfiles[$local_data_id];

			if ($file2 != '') {
				$file = $file2;
			}
		}

		if ($debug) {
			cacti_log("WM poller_output: Looking for $file ($local_data_id) ({$required['data_source_path']})", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
		}

		if (isset($rrd_update_array[$file]) && is_array($rrd_update_array[$file]) && isset($rrd_update_array[$file]['times']) &&
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
					case 1: // GAUGE
						$newvalue = $value;

						break;
					case 2: // COUNTER
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
					case 3: // DERIVE
						$newvalue = ($value - $lastval) / $period;

						break;
					case 4: // ABSOLUTE
						$newvalue = $value / $period;

						break;
					default: // do something somewhat sensible in case something odd happens
						$newvalue = $value;

						wm_warn("poller_output found an unknown data_source_type_id for $file:$dsname");

						break;
				}
			}

			db_execute_prepared('UPDATE weathermap_data
				SET `last_time` = ?, `last_calc` = ?, `last_value` = ?,`sequence` = `sequence` + 1
				WHERE `id` = ?',
				[$newtime, $newvalue, $newlastvalue, $required['id']]);

			if ($debug) {
				cacti_log("WM poller_output: Final value is $newvalue (was $lastval, period was $period)", true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
			}
		} elseif ($debug) {
			cacti_log(sprintf('WARNING: FILE[%s] DS[%s] Didn\'t find data sources for processing.', $file, $local_data_id), true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
		}
	}

	if ($debug) {
		cacti_log('WM poller_output: ENDING', true, 'WEATHERMAP', POLLER_VERBOSITY_DEBUG);
	}

	return $rrd_update_array;
}

/**
 * Poller_bottom hook: on the configured render schedule (a
 * crontab-style counter/period), acquires a process lock and
 * regenerates all weathermap map images/output for the current cycle,
 * otherwise skips rendering and just advances the render counter;
 * also purges auth records for deleted users. Called by Cacti's poller
 * via the 'poller_bottom' hook.
 *
 * @return void
 *
 * @global array $config                Cacti global configuration
 *                                      array (declared but not
 *                                      directly used here).
 * @global bool  $weathermap_debugging  Reserved/declared for parity
 *                                      with other functions in this
 *                                      file; not used directly here.
 */
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
			$timeout = read_config_option('weathermap_timeout');

			if (empty($timeout)) {
				set_config_option('weathermap_timeout', 300);
				$timeout = 300;
			}

			if (!register_process_start('weathermap', 'master', 0, $timeout)) {
				cacti_log('WARNING: Weathermap map generation process timed out.  Consider increasing the timeout in Console > Configuration > Settings > Weathermap', false, 'WEATHERMAP');
				exit(1);
			}

			weathermap_run_maps(__DIR__);

			unregister_process('weathermap', 'master', 0);

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

/**
 * Renders a footer box with links to the local documentation, the
 * Weathermap project website, the map editor, and the plugin's current
 * version. Called from the plugin's viewer/management pages to render
 * a consistent footer.
 *
 * @return void
 */
function weathermap_footer_links() {
	$weathermap_version = plugin_weathermap_numeric_version();

	print '<br />';

	html_start_box('<a target="_blank" class="linkOverDark" href="docs/">' . __('Local Documentation', 'weathermap') . '</a> -- <a target="_blank" class="linkOverDark" href="http://www.network-weathermap.com/">' . __('Weathermap Website', 'weathermap') . '</a> -- <a target="_target" class="linkOverDark" href="weathermap-cacti-plugin-editor.php">' . __('Weathermap Editor', 'weathermap') . '</a> -- ' . __('This is version %s', $weathermap_version, 'weathermap'), '100%', false, 3, 'center', '');

	html_end_box();
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function weathermap_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/weathermap';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: weathermap manifest.json could not be parsed; skipping file prune', false, 'WEATHERMAP');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: weathermap prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'WEATHERMAP');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: weathermap prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'WEATHERMAP');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = weathermap_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: weathermap upgrade could not remove %s (check file/directory permissions)', $rel), false, 'WEATHERMAP');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: weathermap upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'WEATHERMAP');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for weathermap_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function weathermap_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!weathermap_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}

/**
 * Add the originating map to local graph links without changing graph selection.
 *
 * @param string $html    Generated map HTML.
 * @param string $maphash Originating map's file hash.
 *
 * @return string Map HTML with local graph links carrying the originating map.
 */
function weathermap_map_graph_links($html, $maphash) {
	global $config;

	return preg_replace_callback('/href=([\"\'])(.*?)\1/i', function ($match) use ($config, $maphash) {
		$url   = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
		$parts = parse_url($url);

		if (!$parts || isset($parts['host']) || isset($parts['scheme']) || !in_array($parts['path'] ?? '', [$config['url_path'] . 'graph.php', $config['url_path'] . 'graph_view.php'], true)) {
			return $match[0];
		}
		$query = array_filter(explode('&', $parts['query'] ?? ''), function ($part) {
			return $part !== '' && rawurldecode(explode('=', $part, 2)[0]) !== 'wm_map';
		});
		$query[] = 'wm_map=' . rawurlencode($maphash);
		$url     = $parts['path'] . '?' . implode('&', $query) . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

		return 'href=' . $match[1] . plugin_weathermap_escape_attr($url) . $match[1];
	}, $html) ?? $html;
}

/**
 * Escape an HTML attribute using Cacti's helper when available.
 *
 * @param string $value The attribute value before escaping.
 *
 * @return string The attribute value with quotes and entities safely encoded.
 */
function plugin_weathermap_escape_attr($value) {
	if (function_exists('html_escape_attr')) {
		return html_escape_attr($value);
	}

	return str_replace('`', '&#96;', htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true));
}
