<?php
/*
 +-------------------------------------------------------------------------+
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

chdir('../../');
include_once('./include/auth.php');
include_once('./include/config.php');

$weathermap_confdir = realpath(__DIR__ . '/configs');

// include the weathermap class so that we can get the version
include_once(__DIR__ . '/lib/Weathermap.class.php');
include_once(__DIR__ . '/lib/compat.php');

$actions = array(
	'1' => __('Delete', 'weathermap'),
	'2' => __('Duplicate', 'weathermap'),
	'3' => __('Disable', 'weathermap'),
	'4' => __('Enable', 'weathermap'),
	'5' => __('Change Permissions', 'weathermap'),
	'6' => __('Change Group', 'weathermap'),
	'7' => __('Rebuild Now', 'weathermap')
);

set_default_action();

switch ($action) {
	case 'enable_poller_output':
		weathermap_setting_save(0, 'rrd_use_poller_output', 1);
		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=0');

		break;
	case 'group_update':
		$id      = -1;
		$newname = '';

		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$id = intval($_REQUEST['id']);
		}

		if (isset($_REQUEST['gname']) && (strlen($_REQUEST['gname']) > 0)) {
			$newname = $_REQUEST['gname'];
		}

		if ($id >= 0 && $newname != '') {
			weathermap_group_update($id, $newname);
		}

		if ($id < 0 && $newname != '') {
			weathermap_group_create($newname);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'groupadmin_delete':
		$id = -1;

		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$id = intval($_REQUEST['id']);
		}

		if ($id >= 1) {
			weathermap_group_delete($id);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'group_form':
		$id = -1;

		top_header();
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$id = intval($_REQUEST['id']);
		}

		if ($id >= 0) {
			weathermap_group_form($id);
		}

		bottom_footer();

		break;
	case 'groupadmin':
		top_header();

		weathermap_group_editor();

		bottom_footer();

		break;
	case 'chgroup_update':
		$mapid   = -1;
		$groupid = -1;

		if (isset($_REQUEST['map_id']) && is_numeric($_REQUEST['map_id'])) {
			$mapid = intval($_REQUEST['map_id']);
		}

		if (isset($_REQUEST['new_group']) && is_numeric($_REQUEST['new_group'])) {
			$groupid = intval($_REQUEST['new_group']);
		}

		if (($groupid > 0) && ($mapid >= 0)) {
			weathermap_set_group($mapid, $groupid);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'chgroup':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			top_header();
			weathermap_chgroup(intval($_REQUEST['id']));
			bottom_footer();
		} else {
			print 'Something got lost back there.';
		}

		break;
	case 'map_settings_delete':
		$mapid     = null;
		$settingid = null;

		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid'])) {
			$mapid = intval($_REQUEST['mapid']);
		}

		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$settingid = intval($_REQUEST['id']);
		}

		if (!is_null($mapid) && !is_null($settingid)) {
			// create setting
			weathermap_setting_delete($mapid, $settingid);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid);

		break;
	case 'save':
		// this is the save option from the map_settings_form
		$mapid     = null;
		$settingid = null;
		$name      = '';
		$value     = '';

		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid'])) {
			$mapid = intval($_REQUEST['mapid']);
		}

		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$settingid = intval($_REQUEST['id']);
		}

		if (isset($_REQUEST['name']) && $_REQUEST['name']) {
			$name = $_REQUEST['name'];
		}

		if (isset($_REQUEST['value']) && $_REQUEST['value']) {
			$value = $_REQUEST['value'];
		}

		if (!is_null($mapid) && $settingid == 0) {
			// create setting
			weathermap_setting_save($mapid, $name, $value);
		} elseif (!is_null($mapid) && !is_null($settingid)) {
			// update setting
			weathermap_setting_update($mapid, $settingid, $name, $value);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid);

		break;
	case 'map_settings_form':
		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid'])) {
			top_header();

			if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
				weathermap_map_settings_form(intval($_REQUEST['mapid']), intval($_REQUEST['id']));
			} else {
				weathermap_map_settings_form(intval($_REQUEST['mapid']));
			}

			bottom_footer();
		}

		break;
	case 'map_settings':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			top_header();
			weathermap_map_settings(intval($_REQUEST['id']));
			bottom_footer();
		}

		break;
	case 'perms_add_user':
		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid']) && isset($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
			perms_add_user(intval($_REQUEST['mapid']), intval($_REQUEST['userid']));
			header('Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . intval($_REQUEST['mapid']));
		}

		break;
	case 'perms_delete_user':
		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid']) && isset($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
			perms_delete_user($_REQUEST['mapid'], $_REQUEST['userid']);
			header('Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $_REQUEST['mapid']);
		}

		break;
	case 'perms_edit':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			top_header();
			perms_list($_REQUEST['id']);
			bottom_footer();
		} else {
			print __('Something got lost back there.', 'weathermap');
		}

		break;
	case 'delete_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			map_delete($_REQUEST['id']);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'deactivate_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			map_deactivate($_REQUEST['id']);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'activate_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			map_activate($_REQUEST['id']);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_map_up':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			map_move($_REQUEST['id'], $_REQUEST['order'], -1);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_map_down':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			map_move($_REQUEST['id'], $_REQUEST['order'], +1);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_group_up':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			weathermap_group_move(intval($_REQUEST['id']), intval($_REQUEST['order']), -1);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'move_group_down':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			weathermap_group_move(intval($_REQUEST['id']), intval($_REQUEST['order']), 1);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'viewconfig':
		top_graph_header();

		if (isset($_REQUEST['file'])) {
			preview_config($_REQUEST['file']);
		} else {
			print __('No such file.', 'weathermap');
		}

		bottom_footer();

		break;
	case 'addmap_picker':
		top_header();

		if (isset($_REQUEST['show']) && $_REQUEST['show'] == 'all') {
			addmap_picker(true);
		} else {
			addmap_picker(false);
		}

		bottom_footer();

		break;
	case 'addmap':
		if (isset($_REQUEST['file'])) {
			add_config($_REQUEST['file']);
			header('Location: weathermap-cacti-plugin-mgmt.php');
		} else {
			print __('No such file.', 'weathermap');
		}

		break;
	case 'editor':
		// chdir(__DIR__);
		// include_once('./weathermap-cacti-plugin-editor.php');

		break;
	case 'rebuildnow':
		include_once(__DIR__ . '/Weathermap.class.php');
		include_once(__DIR__ . '/lib/poller-common.php');

		weathermap_run_maps(__DIR__);

		raise_message('rebuild_all', __('All Maps have been Rebuilt!', 'weathermap'), MESSAGE_LEVEL_INFO);

		header('Location: ' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?header=false');
		exit;

		break;
	default:
		// by default, just list the map setup
		top_header();
		maplist();
		bottom_footer();
		break;
}

function weathermap_footer_links() {
	global $WEATHERMAP_VERSION;

	print '<br />';

	html_start_box('<a target="_blank" class="linkOverDark" href="docs/">' . __('Local Documentation', 'weathermap') . '</a> -- <a target="_blank" class="linkOverDark" href="http://www.network-weathermap.com/">' . __('Weathermap Website', 'weathermap') . '</a> -- <a target="_target" class="linkOverDark" href="weathermap-cacti-plugin-editor.php">' . __('Weathermap Editor', 'weathermap') . '</a> -- ' . __('This is version %s', $WEATHERMAP_VERSION), '100%', '', '3', 'center', '');

	html_end_box();
}

/**
 * Repair the sort order column (for when something is deleted or inserted,
 * or moved between groups) our primary concern is to make the sort order
 * consistent, rather than any special 'correctness'
 */
function map_resort() {
	$list = db_fetch_assoc('SELECT *
		FROM weathermap_maps
		ORDER BY group_id, sortorder');

	$i = 1;
	$last_group = -1020.5;

	if (cacti_sizeof($list)) {
		foreach ($list as $map) {
			if ($last_group != $map['group_id']) {
				$last_group = $map['group_id'];
				$i = 1;
			}

			$sql[] = "UPDATE weathermap_maps SET sortorder = $i WHERE id = " . $map['id'];

			$i++;
		}

		if (!empty($sql)) {
			for ($a = 0; $a < count($sql); $a++) {
				$result = db_execute($sql[$a]);
			}
		}
	}
}

/**
 * Repair the sort order column (for when something is deleted or inserted)
 */
function weathermap_group_resort() {
	$list = db_fetch_assoc('SELECT *
		FROM weathermap_groups
		ORDER BY sortorder');

	$i = 1;

	foreach ($list as $group) {
		$sql[] = "UPDATE weathermap_groups SET sortorder = $i WHERE id = " . $group['id'];
		$i++;
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}
}

function map_move($mapid, $junk, $direction) {
	$source = db_fetch_row_prepared('SELECT *
		FROM weathermap_maps
		WHERE id = ?',
		array($mapid));

	if (cacti_sizeof($source)) {
		$oldorder = $source['sortorder'];
		$group    = $source['group_id'];

		$neworder = $oldorder + $direction;

		$target = db_fetch_row_prepared('SELECT *
			FROM weathermap_maps
			WHERE group_id = ?
			AND sortorder = ?',
			array($group, $neworder));

		if (cacti_sizeof($target)) {
			$otherid = $target['id'];

			// move $mapid in direction $direction
			$sql[] = "UPDATE weathermap_maps SET sortorder = $neworder WHERE id = $mapid";

			// then find the other one with the same sortorder and move that in the opposite direction
			$sql[] = "UPDATE weathermap_maps SET sortorder = $oldorder WHERE id = $otherid";
		}

		if (!empty($sql)) {
			for ($a = 0; $a < count($sql); $a++) {
				$result = db_execute($sql[$a]);
			}
		}
	}
}

function weathermap_group_move($id, $junk, $direction) {
	$source = db_fetch_row_prepared('SELECT *
		FROM weathermap_groups
		WHERE id = ?',
		array($id));

	if (cacti_sizeof($source)) {
		$oldorder = $source['sortorder'];

		$neworder = $oldorder + $direction;

		$target = db_fetch_row_prepared('SELECT *
			FROM weathermap_groups
			WHERE sortorder = ?',
			array($neworder));

		if (cacti_sizeof($target)) {
			$otherid = $target['id'];

			// move $mapid in direction $direction
			$sql[] = "UPDATE weathermap_groups SET sortorder = $neworder WHERE id = $id";

			// then find the other one with the same sortorder and move that in the opposite direction
			$sql[] = "UPDATE weathermap_groups SET sortorder = $oldorder WHERE id = $otherid";
		}

		if (!empty($sql)) {
			for ($a = 0; $a < count($sql); $a++) {
				$result = db_execute($sql[$a]);
			}
		}
	}
}

function wm_filter() {
	global $item_rows;

	$last_stats = read_config_option('weathermap_last_stats', true);

	html_start_box(__('Weather Maps [ Run Details: %s ]', $last_stats, 'weathermap'), '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=addmap_picker');
	?>
	<tr class='even'>
		<td>
			<form id='form_wm' action='weathermap-cacti-plugin-mgmt.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'weathermap');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Maps', 'weathermap');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'weathermap');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: use filter settings', 'Go', 'weathermap');?>' id='refresh'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: reset filter settings', 'Clear', 'weathermap');?>' id='clear'>
							<input type='button' id='wm_group_settings' value='<?php print __esc('Group Settings', 'weathermap');?>'>
							<input type='button' id='wm_map_settings' value='<?php print __esc('Map Settings', 'weathermap');?>' title='<?php print __esc('Cacti Weathermap Map Settings', 'weathermap');?>'>
							<input type='button' id='wm_settings' value='<?php print __esc('Settings', 'weathermap');?>' title='<?php print __esc('Cacti Weathermap Settings', 'weathermap');?>'>
							<input type='button' id='wm_rebuild' value='<?php print __esc('Rebuild All Maps', 'weathermap');?>' title='<?php print __esc('Rebuild all maps now in background', 'weathermap');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'weathermap-cacti-plugin-mgmt.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'weathermap-cacti-plugin-mgmt.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_wm').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#wm_group_settings').click(function() {
					loadPageNoHeader(urlPath + 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=groupadmin&header=false');
				});

				$('#wm_map_settings').click(function() {
					loadPageNoHeader(urlPath + 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=map_settings&id=0&header=false');
				});

				$('#wm_rebuild').click(function() {
					loadPageNoHeader(urlPath + 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=rebuildnow&header=false');
				});

				$('#wm_settings').click(function() {
					loadPageNoHeader(urlPath + 'settings.php?tab=misc&header=false');
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}

function get_map_records(&$total_rows, &$rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE rs.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

    $total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM weathermap_maps AS wm
		INNER JOIN weathermap_groups AS wmg
		ON wm.group_id = wmg.id
		$sql_where");

    $sql_order = get_order_string();
    $sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	return db_fetch_assoc("SELECT wm.*, wmg.name AS groupname
		FROM weathermap_maps AS wm
		INNER JOIN weathermap_groups AS wmg
		ON wm.group_id = wmg.id
		$sql_where
		$sql_order
		$sql_limit");
}

function maplist() {
	global $actions;

	$last_started     = read_config_option('weathermap_last_started_file', true);
	$last_finished    = read_config_option('weathermap_last_finished_file', true);
	$last_start_time  = intval(read_config_option('weathermap_last_start_time', true));
	$last_finish_time = intval(read_config_option('weathermap_last_finish_time', true));
	$poller_interval  = intval(read_config_option('poller_interval'));
	$boost_enabled    = read_config_option('boost_rrd_update_enable', 'off');

	$has_global_poller_output = false;

	if ($boost_enabled == 'on') {
		$result = db_fetch_row("SELECT optvalue
			FROM weathermap_settings
			WHERE optname = 'rrd_use_poller_output'
			AND mapid = 0");

		if (isset($result['optvalue'])) {
			$has_global_poller_output = $result['optvalue'];
		} else {
			$has_global_poller_output = false;
		}

		if (!$has_global_poller_output) {
			print '<div align="center" class="wm_warning"><p>';

			print __('You are using the Boost plugin to update RRD files. Because this delays data being written to the files, it causes issues with Weathermap updates. You can resolve this by using Weathermap\'s \'poller_output\' support, which grabs data directly from the poller. %s You can enable that globally by clicking here %s', '<a href="?action=enable_poller_output">', '</a>', 'weathermap');

			print '</p></div>';
		}
	}

	if (($last_finish_time - $last_start_time) > $poller_interval) {
		if (($last_started != $last_finished) && ($last_started != "")) {
			print '<div align="center" class="wm_warning"><p>';

			print __('Last time it ran, Weathermap did NOT complete it\'s run. It failed during processing for \'%s\'', $last_started);

			print __('This <strong>may</strong> have affected other plugins that run during the poller process.', 'weathermap') . '</p><p>';

			print __('You should either disable this Map, or fault-find. Possible causes include memory_limit issues. The log may have more information.', 'weathermap');

			print '</p></div>';
		}
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_wmm');
	/* ================= input validation ================= */

	wm_filter();

	$total_rows = 0;
	$vdefs = array();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$maps = get_map_records($total_rows, $rows);

	$nav = html_nav_bar('weathermap-cacti-plugin-mgmt.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Maps'), 'page', 'main');

    form_start('weathermap-cacti-plugin-mgmt.php', 'chk');

    print $nav;

    html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		array(
			'display' => __('Config File', 'weathermap'),
		),
		array(
			'display' => __('Title', 'weathermap'),
		),
		array(
			'display' => __('ID', 'weathermap'),
		),
		array(
			'display' => __('Group', 'weathermap'),
		),
		array(
			'display' => __('Enabled', 'weathermap'),
		),
		array(
			'display' => __('Settings', 'weathermap'),
		),
		array(
			'display' => __('Sort Order', 'weathermap'),
		),
		array(
			'display' => __('Accessible By', 'weathermap'),
		),
		array(
			'display' => __('Last Duration', 'weathermap'),
			'align' => 'right'
		),
		array(
			'display' => __('Last Runtime', 'weathermap'),
			'align' => 'right'
		)
	);

	html_header_checkbox($display_text, false);

	$query = db_fetch_assoc("SELECT id, username
		FROM user_auth
		WHERE enabled = 'on'");

	$users[0] = 'Anyone';

	foreach ($query as $user) {
		$users[$user['id']] = $user['username'];
	}

	$had_warnings = 0;

	if (is_array($maps)) {
		foreach ($maps as $map) {
			form_alternate_row('line' . $map['id']);

			$output = '<a target="_new" title="' . __esc('Click to start editor with this file', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-editor.php?header=false&action=nothing&mapname=' . $map['configfile']) . '">' .
				html_escape($map['configfile']) . '
			</a>';

			if ($map['warncount'] > 0) {
				$had_warnings++;

				$output .= '<a class="pic" title="' . __esc('Check cacti.log for this Map', 'weathermap') . '"
					href="' . html_escape('../../clog_user.php?tail_lines=500&message_type=2&action=view_logfile&filter=' . $map['configfile']) . '">
					<i class="deviceRecovering fa fa-exclamation-triangle"></i>
				</a>';
			}

			form_selectable_cell($output, $map['id']);
			form_selectable_cell(html_escape($map['titlecache']), $map['id']);
			form_selectable_cell($map['id'], $map['id']);

			form_selectable_cell('<a class="pic" title="' . __esc('Click to change group', 'weathermap') . '"
					href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=chgroup&id=' . $map['id']) . '">' .
					html_escape($map['groupname']) .
				'</a>', $map['id']);

			if ($map['active'] == 'on') {
				$url = '<a class="pic deviceUp" title="' . __esc('Click to Deactivate', 'weathermap') . '"
                        href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=deactivate_map&id=' . $map['id']) . '">' .
                        __('Yes', 'weathermap') . '
                    </a>';

				form_selectable_cell($url, $map['id'], '', 'wm_enabled');
			} else {
				$url = '<a class="pic deviceDown" title="' . __esc('Click to Activate', 'weathermap') . '"
						href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=activate_map&id=' . $map['id']) . '">' .
						__('No', 'weathermap') . '
					</a>';

				form_selectable_cell($url, $map['id'], '', 'wm_disabled');
			}

			$url = '<a class="pic" href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $map['id']) . '">';

			$setting_count = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM weathermap_settings
				WHERE mapid = ?',
				array($map['id']));

			if ($setting_count > 0) {
				$url .= __('%d Specials', $setting_count, 'weathermap');
			} else {
				$url .= __('Standard', 'weathermap');
			}

			$url .= '</a>';

			form_selectable_cell($url, $map['id']);

			$url  = '<a class="pic" title="' . __esc('Move Map Up', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_map_up&order=' . $map['sortorder'] . '&id=' . $map['id']) . '">
				<i class="fa fa-caret-up moveArrow"></i>
			</a>';

			$url .= '<a class="pic" title="' . __esc('Move Map Down', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_map_down&order=' . $map['sortorder'] . '&id=' . $map['id']) . '">
				<i class="fa fa-caret-down moveArrow"></i>
			</a>';

			form_selectable_cell($url, $map['id']);

			// Get the current users for this map
			$userlist = db_fetch_assoc_prepared('SELECT *
				FROM weathermap_auth
				WHERE mapid = ?
				ORDER BY userid',
				array($map['id']));

			$mapusers = array();
			foreach ($userlist as $user) {
				if (array_key_exists($user['userid'], $users)) {
					$mapusers[] = $users[$user['userid']];
				}
			}

			$url = '<a class="pic" title="' . __esc('Click to edit permissions', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $map['id'] . '&header=false') . '">';

			if (count($mapusers) == 0) {
				$url .= __('(no users)', 'weathermap');
			} else {
				$url .= join(', ', $mapusers);
			}

			$url .= '</a>';

			form_selectable_cell($url, $map['id']);

			form_selectable_cell(round($map['duration'], 2), $map['id'], '', 'right');
			form_selectable_cell(date('m-d H:i:s', $map['last_runtime']), $map['id'], '', 'right');

			form_checkbox_cell($map['titlecache'], $map['id']);

			form_end_row();
		}
	}

	if (!cacti_sizeof($maps)) {
		print '<tr><td><em>' . __esc('No Weathermaps Found', 'weathermap') . '</em></td></tr>';
	}

	html_end_box();

    draw_actions_dropdown($actions);

    form_end();
}

function addmap_picker($show_all = false) {
	global $weathermap_confdir;
	global $colors;

	$loaded = array();
	$flags  = array();

	// find out what maps are already in the database, so we can skip those
	$queryrows = db_fetch_assoc('SELECT * FROM weathermap_maps');

	if (is_array($queryrows)) {
		foreach ($queryrows as $map) {
			$loaded[] = $map['configfile'];
		}
	}

	# $loaded[]='index.php';

	html_start_box(__('Available Weathermap Configuration Files', 'weathermap'), '100%', '', '3', 'center', '');

	if (is_dir($weathermap_confdir)) {
		$n  = 0;
		$dh = opendir($weathermap_confdir);

		if ($dh) {
			$i       = 0;
			$skipped = 0;

			html_header(
				array(
					__('Actions', 'weathermap'),
					'',
					__('Config File', 'weathermap'),
					__('Title', 'weathermap'),
					''
				), 2
			);

			while ($file = readdir($dh)) {
				$realfile = $weathermap_confdir . '/' . $file;

				// skip .-prefixed files like .htaccess, since it seems
				// that otherwise people will add them as map config files.
				// and the index.php too - for the same reason
				if (substr($file, 0, 1) != '.' && $file != 'index.php') {
					$used = in_array($file, $loaded);

					$flags[$file] = '';

					if ($used) {
						$flags[$file] = 'USED';
					}

					if (is_file($realfile)) {
						if ($used && !$show_all) {
							$skipped++;
						} else {
							$title = wmap_get_title($realfile);

							$titles[$file] = $title;

							$i++;
						}
					}
				}
			}

			closedir($dh);

			if ($i > 0) {
				ksort($titles);

				$i = 0;

				foreach ($titles as $file => $title) {
					$title = $titles[$file];

					form_alternate_row();

					print '<td>
						<a class="pic" title="' . __esc('Add the configuration file', 'weathermap') . '"
							href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=addmap&file=' . $file) . '">Add
						</a>
					</td>';

					print '<td>
						<a class="pic" title="' . __esc('View the configuration file in a new window', 'weathermap') . '"
							href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=viewconfig&file=' . $file) . '">' .
							__('View', 'weathermap') . '
						</a>
					</td>';

					print '<td>' . html_escape($file);

					if ($flags[$file] == 'USED') {
						print ' <b>' . __('(USED)', 'weathermap') . '</b>';
					}

					print '</td>';
					print '<td><em>' . html_escape($title) . '</em></td>';
					print '</tr>';

					$i++;
				}
			}

			if (($i + $skipped) == 0) {
				print '<tr><td>' . __esc('No files were found in the configs directory.', 'weathermap') . '</td></tr>';
			}

			if (($i == 0) && $skipped > 0) {
				print '<tr><td>' . __('(%s files weren\'t shown because they are already in the database', $skipped, 'weathermp') . '</td></tr>';
			}
		} else {
			print '<tr><td>' . __('Can\'t open %s to read.  You must set it to be readable by the webserver.', $weathermap_confdir, 'weathermap') . '</td></tr>';
		}
	} else {
		print '<tr><td>' . __('There is no directory named %s.  You must create it, and set it to be readable by the webserver. If you want to upload configuration files from inside Cacti, then it must be <i>writable</i> by the webserver too.', $weathermap_confdir, 'weathermap') . '</td></tr>';
	}

	html_end_box();

	if ($skipped > 0) {
		print '<p align=center>' . __('Some files are not shown because they have already been added. You can %s show these files too %s, if you need to.', '<a href="weathermap-cacti-plugin-mgmt.php?action=addmap_picker&show=all">', '</a>', 'weathermap') . '</p>';
	}

	if ($show_all) {
		print '<p align=center>' . __('Some files are shown even though they have already been added. You can %s hide those files too %s, if you need to.', '<a href="weathermap-cacti-plugin-mgmt.php?action=addmap_picker">', '</a>', 'weathermap') . '</p>';
	}
}

function preview_config($file) {
	global $weathermap_confdir;
	global $colors;

	chdir($weathermap_confdir);

	$path_parts = pathinfo($file);
	$file_dir   = realpath($path_parts['dirname']);

	if ($file_dir != $weathermap_confdir) {
		// someone is trying to read arbitrary files?
		// print "$file_dir != $weathermap_confdir";
		print '<h3>' . __('Path mismatch', 'weathermap') . '</h3>';
	} else {
		html_start_box(__('Preview of %s', $file, 'weathermap'), '100%', '', '3', 'center', '');

		print '<tr><td class="textArea">';
		print '<pre>';

		$realfile = $weathermap_confdir . '/' . $file;

		if (is_file($realfile)) {
			$fd = fopen($realfile, 'r');

			while (!feof($fd)) {
				$buffer = fgets($fd, 4096);
				print $buffer;
			}

			fclose($fd);
		}

		print '</pre>';
		print '</td></tr>';

		html_end_box();
	}
}

function add_config($file) {
	global $weathermap_confdir;
	global $colors;

	chdir($weathermap_confdir);

	$path_parts = pathinfo($file);
	$file_dir   = realpath($path_parts['dirname']);

	if ($file_dir != $weathermap_confdir) {
		// someone is trying to read arbitrary files?
		// print "$file_dir != $weathermap_confdir";
		print '<h3>' . __('Path mismatch', 'weathermap') . '</h3>';
	} else {
		$realfile = $weathermap_confdir . '/' . $file;
		$title    = wmap_get_title($realfile);

		db_execute_prepared("INSERT INTO weathermap_maps
			(configfile, titlecache, active, imagefile, htmlfile, filehash, config)
			VALUES (?, ?, 'on', '', '', '', '')",
			array($file, $title));

		$last_id = db_fetch_insert_id();
		$myuid   = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

		db_execute_prepared('INSERT INTO weathermap_auth
			(mapid,userid)
			VALUES (?, ?)',
			array($last_id, $myuid));

		db_execute_prepared('UPDATE weathermap_maps
			SET filehash = LEFT(MD5(CONCAT(id, configfile, rand())), 20)
			WHERE id = ?',
			array($last_id));

		map_resort();
	}
}

function wmap_get_title($filename) {
	$title = '(no title)';

	$fd    = fopen($filename, 'r');

	if (is_resource($fd)) {
		while (!feof($fd)) {
			$buffer = fgets($fd, 4096);
			if (preg_match('/^\s*TITLE\s+(.*)/i', $buffer, $matches)) {
				$title = $matches[1];
			}

			// this regexp is tweaked from the ReadConfig version, to only match TITLEPOS lines *with* a title appended
			if (preg_match('/^\s*TITLEPOS\s+\d+\s+\d+\s+(.+)/i', $buffer, $matches)) {
				$title = $matches[1];
			}

			// strip out any DOS line endings that got through
			$title = str_replace("\r", '', $title);
		}

		fclose($fd);
	}

	return ($title);
}

function map_deactivate($id) {
	db_execute_prepared('UPDATE weathermap_maps
		SET active = "off"
		WHERE id = ?',
		array($id));
}

function map_activate($id) {
	db_execute_prepared('UPDATE weathermap_maps
		SET active = "on"
		WHERE id = ?',
		array($id));
}

function map_delete($id) {
	db_execute_prepared('DELETE FROM weathermap_maps WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM weathermap_auth WHERE mapid = ?', array($id));
	db_execute_prepared('DELETE FROM weathermap_settings WHERE mapid = ?', array($id));

	map_resort();
}

function weathermap_set_group($mapid, $groupid) {
	db_execute_prepared('UPDATE weathermap_maps
		SET group_id = ?
		WHERE id = ?',
		array($groupid, $mapid));

	map_resort();
}

function perms_add_user($mapid, $userid) {
	db_execute_prepared('INSERT INTO weathermap_auth
		(mapid, userid)
		VALUES(?, ?)',
		array($mapid, $userid));
}

function perms_delete_user($mapid, $userid) {
	db_execute_prepared('DELETE FROM weathermap_auth
		WHERE mapid = ?
		AND userid = ?',
		array($mapid, $userid));
}

function perms_list($id) {
	global $colors;

	$title = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($id));

	$auth_sql = "SELECT * FROM weathermap_auth WHERE mapid = $id ORDER BY userid";

	$query = db_fetch_assoc('SELECT id, username FROM user_auth ORDER BY username');

	$users[0] = 'Anyone';

	foreach ($query as $user) {
		$users[$user['id']] = $user['username'];
	}

	$auth_results = db_fetch_assoc($auth_sql);
	$mapusers     = array();
	$mapuserids   = array();

	foreach ($auth_results as $user) {
		if (isset($users[$user['userid']])) {
			$mapusers[]   = $users[$user['userid']];
			$mapuserids[] = $user['userid'];
		}
	}

	$userselect = '';
	foreach ($users as $uid => $name) {
		if (!in_array($uid, $mapuserids)) $userselect .= "<option value=\"$uid\">$name</option>\n";
	}

	html_start_box(__('Edit permissions for Weathermap %s: %s', $id, $title, 'weathermap'), '100%', '', '3', 'center', '');

	html_header(array(__('Username', 'weathermap'), ''));

	$n = 0;

	foreach ($mapuserids as $user) {
		form_alternate_row();

		print '<td>' . html_escape($users[$user]) . '</td>';
		print '<td class="right">
			<a class="delete deleteMarker fa fa-times" title="' . __esc('Remove permissions for this user to see this Map', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=perms_delete_user&mapid=' . $id . '&userid=' . $user) . '">
			</a>
		</td>';

		print '</tr>';

		$n++;
	}

	if ($n == 0) {
		print '<tr><td><em><b>' . __('Nobody can see this Map', 'weathermap') . '</em></td></tr>';
	}

	html_end_box();

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr>';

	if ($userselect == '') {
		print '<td><em>' . __('There aren\'t any users left to add!', 'weathermap') . '</em></td></tr>';
	} else {
		print '<td><form action="">' . __('Allow', 'weathermap') . ' <input type="hidden" name="action" value="perms_add_user"><input type="hidden" name="mapid" value="' . $id . '"><select name="userid">';
		print $userselect;
		print '</select> ' . __('to see this Map', 'weathermap') . ' <input type="submit" value="' . __esc('Update', 'weathermap') . '"></form></td>';
		print '</tr>';
	}

	html_end_box();
}

function weathermap_map_settings($id) {
	global $config;

	if ($id == 0) {
		$title = __('Additional settings for all Maps', 'weathermap');

		$nonemsg = __('There are no settings for all Maps yet. You can add some by pressing the plus sign \'+\' in the top-right, or choose a single Map from the management screen to add settings for that Map.', 'weathermap');

		$type = 'global';

		$settingrows = db_fetch_assoc('SELECT *
			FROM weathermap_settings
			WHERE mapid = 0
			AND groupid = 0');
	} elseif ($id < 0) {
		$group_id = -$id;

		$groupname = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array($group_id));

		$title = __('Edit per Map settings for Group %s: %s', $group_id, $groupname, 'weathermap');

		$nonemsg = __('There are no per Group settings for this group yet. You can add some by pressing the plus sign \'+\' in the top-right.', 'weathermap');

		$type = 'group';

		$settingrows = db_fetch_assoc_prepared('SELECT * FROM weathermap_settings WHERE groupid = ?', array($group_id));
	} else {
		$map = db_fetch_row_prepared('SELECT *
			FROM weathermap_maps
			WHERE id = ?',
			array($id));

		$groupname = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array($map['group_id']));

		$title = __('Edit per Map settings for Weathermap %d: %s', $id, $map['titlecache'], 'weathermap');

		$nonemsg = __('There are no per Map settings for this Map yet. You can add some by pressing the plus sign \'+\' in the top-right.', 'weathermap');

		$type = 'map';

		$settingrows = db_fetch_assoc_prepared('SELECT *
			FROM weathermap_settings
			WHERE mapid = ?',
			array($id));
	}

	$do_grp_settings = false;
	$do_map_settings = false;

	if ($type == 'group' || $type == 'map') {
		html_start_box(__('Usage Notes', 'weathermap'), '100%', '', '3', 'center', '');

		print '<tr class="even"><td>';

		if ($type == 'group') {
			$do_grp_settings = true;

			print __('All Maps in this Group are also affected by the following GLOBAL settings (Group overrides Global, Map overrides Group, but BOTH override SET commands within the Map Config File)', 'weathermap');

		}

		if ($type == 'map') {
			$do_grp_settings = true;
			$do_map_settings = true;

			print __('This Map is also affected by the following Global and Group settings (Group overrides Global, Map overrides Group, but BOTH override SET commands within the Map Config File)', 'weathermap');
		}

		print '</td></tr>';

		html_end_box();

		if ($do_grp_settings) {
			weathermap_readonly_settings(0, __('Global Settings [ All Maps ]', 'weathermap'));
		}

		if ($do_map_settings) {
			weathermap_readonly_settings($map['group_id'], __esc('Group Settings [ %s ]', ($groupname != '' ? $groupname:__('No Group', 'weathermap')), 'weathermap'));
		}
	}

	html_start_box($title, '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=map_settings_form&mapid=' . intval($id));

	if (cacti_sizeof($settingrows)) {
		html_header(array(__('Action', 'weathermap'), __('Name', 'weathermap'), __('Value', 'weathermap')), 2);

		$n = 0;

		foreach ($settingrows as $setting) {
			form_alternate_row();

			print '<td>
				<a class="pic title="' . __('Edit this definition', 'weathermap') . '"
					href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings_form&mapid=' . $id . '&id=' . $setting['id']) . '">
					<i class="fas fa-wrench"></i>
				</a>
			</td>';

			print '<td>' . html_escape($setting['optname']) . '</td>';
			print '<td>' . html_escape($setting['optvalue']) . '</td>';

			print '<td class="right">
				<a class="pic" title="' . __('Remove this definition from this Map', 'weathermap') . '"
					href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings_delete&mapid=' . $id . '&id=' . $setting['id']) . '">
					<i class="delete deleteMarker fa fa-times"></i>
				</a>
			</td>';

			print '</tr>';

			$n++;
		}
	} else {
		print '<tr class="even tableRow">';
		print '<td colspan="4"><em>' . html_escape($nonemsg) . '</em></td>';
		print '</tr>';
	}

	html_end_box();

	weathermap_back_to($type);
}

function weathermap_back_to($type = 'global') {
	print '<div align=center>';

	if ($type == 'group') {
		print '<a href="weathermap-cacti-plugin-mgmt.php?action=groupadmin">' . __('Back to Group Admin', 'weathermap') . '</a>';
	}

	if ($type == 'global' || $type == 'map') {
		print '<a class="pic" href="weathermap-cacti-plugin-mgmt.php?action=">' . __('Back to Map Admin', 'weathermap') . '</a>';
	}

	print '</div>';
}

function weathermap_readonly_settings($id, $title = 'Settings') {
	global $config;

	if ($id == 0) {
		$settings = db_fetch_assoc('SELECT *
			FROM weathermap_settings
			WHERE mapid = 0
			AND groupid = 0');
	} else {
		$settings = db_fetch_assoc_prepared('SELECT *
			FROM weathermap_settings
			WHERE mapid = 0
			AND groupid = ?',
			array($id));
	}

	html_start_box($title, '100%', '', '3', 'center', '');

	html_header(array(__('Name', 'weathermap'), __('Value', 'weathermap')));

	$n = 0;

	if (cacti_sizeof($settings) > 0) {
		foreach ($settings as $setting) {
			form_alternate_row();

			form_selectable_cell(html_escape($setting['optname']), $n);
			form_selectable_cell(html_escape($setting['optvalue']), $n);

			form_end_row();

			$n++;
		}
	} else {
		form_alternate_row();

		print '<td colspan=4><em>' . __('No Settings Found', 'weathermap') . '</em></td>';

		form_end_row();
	}

	html_end_box();

}

function weathermap_map_settings_form($mapid = 0, $settingid = 0) {
	global $colors, $config;

	$title = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($mapid));

	$name  = '';
	$value = '';

	if ($settingid != 0) {
		$result = db_fetch_assoc_prepared('SELECT *
			FROM weathermap_settings
			WHERE id = ?',
			array($settingid));

		if (is_array($result) && sizeof($result) > 0) {
			$name  = $result[0]['optname'];
			$value = $result[0]['optvalue'];
		}
	}

	$values_ar = array();

	$field_ar = array(
		'mapid' => array(
			'friendly_name' => __('Map ID', 'weathermap'),
			'method' => 'hidden_zero',
			'value' => $mapid
		),
		'id' => array(
			'friendly_name' => __('Setting ID', 'weathermap'),
			'method' => 'hidden_zero',
			'value' => $settingid
		),
		'name' => array(
			'friendly_name' => __('Name', 'weathermap'),
			'method' => 'textbox',
			'max_length' => 128,
			'description' => __('The name of the map-global SET variable', 'weathermap'),
			'value' => $name
		),
		'value' => array(
			'friendly_name' => __('Value', 'weathermap'),
			'method' => 'textbox',
			'max_length' => 128,
			'description' => __('What to set it to', 'weathermap'),
			'value' => $value
		)
	);

	$action = __('Edit', 'weathermap');

	if ($settingid == 0) {
		$action = __('Create', 'weathermap');
	}

	if ($mapid == 0) {
		$title = __('Setting for all Maps', 'weathermap');
	} elseif ($mapid < 0) {
		$grpid = -$mapid;
		$title = __('Per Group setting for Group %s: %s', $grpid, $title, 'weathermap');
	} else {
		$title = __('Per Map setting for Weathermap %s: %s', $mapid, $title, 'weathermap');
	}

	form_start('weathermap-cacti-plugin-mgmt.php');

	html_start_box("$action $title", '100%', '', '3', 'center', '');

	draw_edit_form(array('config' => $values_ar, 'fields' => $field_ar));

	html_end_box();

	form_save_button('weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid);
}

function weathermap_setting_save($mapid, $name, $value) {
	if ($mapid > 0) {
		db_execute_prepared('REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)',
			array($mapid, 0, $name, $value));
	} elseif ($mapid < 0) {
		db_execute_prepared('REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)',
			array(0, -$mapid, $name, $value));
	} else {
		db_execute_prepared('REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)',
			array(0, 0, $name, $value));
	}
}

function weathermap_setting_update($mapid, $settingid, $name, $value) {
	db_execute_prepared('UPDATE weathermap_settings
		SET optname = ?, optvalue = ?
		WHERE id = ?',
		array($name, $value, intval($settingid)));
}

function weathermap_setting_delete($mapid, $settingid) {
	db_execute_preapred('DELECT FROM weathermap_settings
		WHERE id = ?
		AND mapid = ?',
		array(intval($settingid), intval($mapid)));
}

function weathermap_chgroup($id) {
	global $colors;

	$title = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($id));

	$curgroup = db_fetch_cell_prepared('SELECT group_id
		FROM weathermap_maps
		WHERE id = ?' .
		array($id));

	$n = 0;

	form_start('weathermap-cacti-plugin-mgmt.php');

	print "<input type=hidden name='map_id' value='" . $id . "'>";
	print "<input type=hidden name='action' value='chgroup_update'>";

	html_start_box(__('Edit Map Group for Weathermap %s: %s', $id, $title, 'weathermap'), '100%', '', '3', 'center', '');

	# html_header(array("Group Name", ""));
	form_alternate_row();

	print '<td><b>' . __('Choose an existing Group', 'wethermap') . '</b>&nbsp;&nbsp;<select name="new_group">';

	$results = db_fetch_assoc("SELECT *
		FROM weathermap_groups
		ORDER BY sortorder");

	foreach ($results as $grp) {
		print '<option ';

		if ($grp['id'] == $curgroup) {
			print ' selected ';
		}

		print 'value=' . $grp['id'] . '>' . html_escape($grp['name']) . '</option>';
	}

	print '</select>';
	print '<input type="submit" value="' . __esc('Change Group', 'weathermap') . '"/>';
	print '</td>';
	print '</tr>';
	print '<tr><td></td></tr>';

	print '<tr><td><p>' . __('Or Create a New Group using the %s Group Management interface %s', '<a class="pic" href="weathermap-cacti-plugin-mgmt.php?action=groupadmin">' , '</a>', 'weathermap') . '</p></td></tr>';

	html_end_box();

	form_end();
}

function weathermap_group_form($id = 0) {
	global $colors, $config;

	$grouptext = '';
	// if id==0, it's an Add, otherwise it's an editor.
	if ($id == 0) {
		$header = __('Adding a Group', 'weathermap');
	} else {
		$header = __esc('Editing Group %s', $id, 'weathermap');

		$grouptext = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array($id));
	}

	html_start_box($header, '100%', '', '3', 'center', '');

	print '<tr><td>';

	form_start('weathermap-cacti-plugin-mgmt.php');

	print '<input type="hidden" name="action" value="group_update">';

	print __('Group Name:', 'weathermap') . '<input class="ui-state-default ui-corner-all" size="40" name="gname" value="' . html_escape($grouptext) . '"/>';

	if ($id > 0) {
		print '<input type="hidden" name="id" value="' . $id . ' />';
		print __('Group Name:', 'weathermap') . '<input type=submit value="' . __esc('Update', 'weathermap') . '"/>';
	} else {
		print '<input type="submit" value="' . __esc('Add', 'weathermap') . '"/>';
	}

	form_end();

	print '</td></tr>';

	html_end_box();
}

function weathermap_group_editor() {
	global $colors, $config;

	html_start_box(__('Edit Map Groups', 'weathermap'), '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=group_form&id=0');

	html_header(array(__('Actions', 'weathermap'), __('Group Name', 'weathermap'), __('Settings', 'weathermap'), __('Sort Order', 'weathermap')), 2);

	$groups = db_fetch_assoc('SELECT * FROM weathermap_groups ORDER BY sortorder');

	$n = 0;

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			form_alternate_row();

			print '<td style="width:4%">
				<a title="' . __('Rename this Group', 'weathermap') . '"
					href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=group_form&id=' . $group['id']) . '">
					<i class="fas fa-wrench"></i>
				</a>
			</td>';

			form_selectable_cell(html_escape($group['name']), $group['id']);

			print '<td>';

			print '<a class="pic" title="' . __('Edit Group Settings', 'weathermap') . '
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings&id=-' . $group['id']) . '">';

			$setting_count = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM weathermap_settings
				WHERE mapid = 0
				AND groupid = ?',
				array($group['id']));

			if ($setting_count > 0) {
				print __('%d Specials', $setting_count, 'weathermap');
			} else {
				print __('Standard', 'weathermap');
			}

			print '</a>';
			print '</td>';
			print '<td>';

			print '<a class="pic" title="' . __('Move Group Up', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_group_up&order=' . $group['sortorder'] . '&id=' . $group['id']) . '">
				<i class="fa fa-caret-up moveArrow"></i>
			</a>';

			print '<a class="pic" title="' . __('Move Group Down', 'weathermap'). '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_group_down&order=' . $group['sortorder'] . '&id=' . $group['id']) . '">
				<i class="fa fa-caret-down moveArrow"></i>
			</a>';

			// print $map['sortorder'];

			print '</td>';
			print '<td class="right">';

			if ($group['id'] > 1) {
				print '<a class="pic delete deleteMarker fa fa-times" title="' . __('Remove this definition from this Map', 'weathermap'). '"
					href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=groupadmin_delete&id=' . $group['id']) . '">
				</a>';
			}

			print '</td>';
			print '</tr>';

			$n++;
		}
	} else {
		print '<tr>';
		print '<td colspan="2">' . __('No Groups are defined', 'weathermap') . '</td>';
		print '</tr>';
	}

	html_end_box();

	weathermap_back_to();
}

function weathermap_group_create($newname) {
	$sortorder = db_fetch_cell_prepared('SELECT MAX(sortorder)+1
		FROM weathermap_groups');

	db_execute_prepared('INSERT INTO weathermap_groups
		(name, sortorder)
		VALUES (?, ?)',
		array($newname, $sortorder));
}

function weathermap_group_update($id, $newname) {
	db_execute_prepared('UPDATE weathermap_groups
		SET name = ?
		WHERE id = ?',
		array($newname, $id));
}

function weathermap_group_delete($id) {
	$newid = db_fetch_cell_prepared('SELECT MIN(id)
		FROM weathermap_groups
		WHERE id != ?',
		array($id));

	# move any maps out of this group into a still-existing one
	db_execute_prepared('UPDATE weathermap_maps
		SET group_id = ?
		WHERE group_id= ?',
		array($newid, $id));

	# then delete the group
	db_execute_prepared('DELETE FROM weathermap_groups
		WHERE id = ?',
		array($id));
}

