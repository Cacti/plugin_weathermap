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

$weathermap_confdir = realpath(dirname(__FILE__) . '/configs');

// include the weathermap class so that we can get the version
include_once(dirname(__FILE__) . '/lib/Weathermap.class.php');
include_once(dirname(__FILE__) . '/lib/compat.php');

$i_understand_file_permissions_and_how_to_fix_them = false;

set_default_action();

switch ($action) {
	case 'enable_poller_output':
		weathermap_setting_save(0, 'rrd_use_poller_output', 1);
		header("Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=0");

		break;
	case 'group_update':
		$id      = -1;
		$newname = "";
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
                $id = intval($_REQUEST['id']);
		}
		if (isset($_REQUEST['gname']) && (strlen($_REQUEST['gname']) > 0)) {
                $newname = $_REQUEST['gname'];
		}

		if ($id >= 0 && $newname != "") {
			weathermap_group_update($id, $newname);
		}

		if ($id < 0 && $newname != "") {
			weathermap_group_create($newname);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin");

		break;
	case 'groupadmin_delete':
		$id = -1;

		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$id = intval($_REQUEST['id']);
		}

		if ($id >= 1) {
			weathermap_group_delete($id);
		}
		header("Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin");

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

		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'chgroup':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
                top_header();
                weathermap_chgroup(intval($_REQUEST['id']));
                bottom_footer();
		} else {
                print "Something got lost back there.";
		}

		break;
	case 'map_settings_delete':
		$mapid     = NULL;
		$settingid = NULL;
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

		header("Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid);

		break;
	case 'save':
		// this is the save option from the map_settings_form
		$mapid     = NULL;
		$settingid = NULL;
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

		header("Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid);

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
			header("Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=" . intval($_REQUEST['mapid']));
		}

		break;
	case 'perms_delete_user':
		if (isset($_REQUEST['mapid']) && is_numeric($_REQUEST['mapid']) && isset($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
			perms_delete_user($_REQUEST['mapid'], $_REQUEST['userid']);
			header("Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=" . $_REQUEST['mapid']);
		}

		break;
	case 'perms_edit':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			top_header();
			perms_list($_REQUEST['id']);
			bottom_footer();
		} else {
			print "Something got lost back there.";
		}

		break;
	case 'delete_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			map_delete($_REQUEST['id']);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'deactivate_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			map_deactivate($_REQUEST['id']);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'activate_map':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) map_activate($_REQUEST['id']);
		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'move_map_up':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			map_move($_REQUEST['id'], $_REQUEST['order'], -1);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'move_map_down':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			map_move($_REQUEST['id'], $_REQUEST['order'], +1);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php");

		break;
	case 'move_group_up':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			weathermap_group_move(intval($_REQUEST['id']), intval($_REQUEST['order']), -1);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin");

		break;
	case 'move_group_down':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			weathermap_group_move(intval($_REQUEST['id']), intval($_REQUEST['order']), 1);
		}

		header("Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin");

		break;
	case 'viewconfig':
		top_graph_header();

		if (isset($_REQUEST['file'])) {
			preview_config($_REQUEST['file']);
		} else {
			print "No such file.";
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
                header("Location: weathermap-cacti-plugin-mgmt.php");
		} else {
                print "No such file.";
		}

		break;
	case 'editor':
		// chdir(dirname(__FILE__));
		// include_once('./weathermap-cacti-plugin-editor.php');

		break;
	case 'rebuildnow':
		top_header();

		print "<h3>REALLY Rebuild all maps?</h3><strong>NOTE: Because your Cacti poller process probably doesn't run as the same user as your webserver, it's possible this will fail with file permission problems even though the normal poller process runs fine. In some situations, it MAY have memory_limit problems, if your mod_php/ISAPI module uses a different php.ini to your command-line PHP.</strong><hr>";

		print "<p>It is recommended that you don't use this feature, unless you understand and accept the problems it may cause.</p>";
		print "<h4><a href=\"weathermap-cacti-plugin-mgmt.php?action=rebuildnow2\">YES</a></h4>";
		print "<h1><a href=\"weathermap-cacti-plugin-mgmt.php\">NO</a></h1>";
		bottom_footer();

		break;
	case 'rebuildnow2':
		include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . "Weathermap.class.php");
		include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "poller-common.php");

		top_header();

		print "<h3>Rebuilding all maps</h3><strong>NOTE: Because your Cacti poller process probably doesn't run as the same user as your webserver, it's possible this will fail with file permission problems even though the normal poller process runs fine. In some situations, it MAY have memory_limit problems, if your mod_php/ISAPI module uses a different php.ini to your command-line PHP.</strong><hr><pre>";

		weathermap_run_maps(dirname(__FILE__));

		print "</pre>";
		print "<hr /><h3>Done.</h3>";

		bottom_footer();

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
	html_start_box("<center><a target=\"_blank\" class=\"linkOverDark\" href=\"docs/\">Local Documentation</a> -- <a target=\"_blank\" class=\"linkOverDark\" href=\"http://www.network-weathermap.com/\">Weathermap Website</a> -- <a target=\"_target\" class=\"linkOverDark\" href=\"weathermap-cacti-plugin-editor.php\">Weathermap Editor</a> -- This is version $WEATHERMAP_VERSION</center>", '100%', '', '3', 'center', '');
        html_end_box();
}

/**
 * Repair the sort order column (for when something is deleted or inserted,
 * or moved between groups) our primary concern is to make the sort order
 * consistent, rather than any special 'correctness'
 */
function map_resort() {
	$list = db_fetch_assoc("select * from weathermap_maps order by group_id, sortorder");

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
	$list = db_fetch_assoc("SELECT * FROM weathermap_groups ORDER BY sortorder;");
	$i    = 1;

	foreach ($list as $group) {
		$sql[] = "update weathermap_groups set sortorder = $i where id = " . $group['id'];
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
	$source = db_fetch_row_prepared("SELECT *
		FROM weathermap_groups
		WHERE id = ?",
		array($id));

	if (cacti_sizeof($source)) {
		$oldorder = $source['sortorder'];

		$neworder = $oldorder + $direction;

		$target = db_fetch_row_prepared("SELECT *
			FROM weathermap_groups
			WHERE sortorder = ?",
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

	html_start_box( __('Weather Maps [ Last Completed Run: %s ]', $last_stats), '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=addmap_picker');
	?>
	<tr class='even'>
		<td>
			<form id='form_wm' action='weathermap-cacti-plugin-mgmt.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Maps');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' id='refresh'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' id='clear'>
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

				$('#wm_group_settings').click(function() {
					loadPageNoHeader(urlPath + 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=rebuildnow&header=false');
				});

				$('#wm_map_settings').click(function() {
					loadPageNoHeader(urlPath + 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=map_settings&id=0&header=false');
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
	global $colors, $i_understand_file_permissions_and_how_to_fix_them, $config;

	$last_started     = read_config_option('weathermap_last_started_file', true);
	$last_finished    = read_config_option('weathermap_last_finished_file', true);
	$last_start_time  = intval(read_config_option('weathermap_last_start_time', true));
	$last_finish_time = intval(read_config_option('weathermap_last_finish_time', true));
	$poller_interval  = intval(read_config_option('poller_interval'));

	if (!wm_module_checks()) {
		print '<div align="center" class="wm_warning"><p>';

		print "<b>Required PHP extensions are not present in your mod_php/ISAPI PHP module. Please check your PHP setup to ensure you have the GD extension installed and enabled.</b><p>";
		print "If you find that the weathermap tool itself is working, from the command-line or Cacti poller, then it is possible that you have two different PHP installations. The Editor uses the same PHP that webpages on your server use, but the main weathermap tool uses the command-line PHP interpreter.<p>";
		print "<p>You should also run <a href=\"check.php\">check.php</a> to help make sure that there are no problems.</p><hr/>";


		print '</p></div>';
		exit();
	}

	if (!db_table_exists('weathermap_maps')) {
		print '<div align="center" class="wm_warning"><p>';
		print 'The weathermap_maps table is missing completely from the database. Something went wrong with the installation process.';
		print '</p></div>';
	}

	$boost_enabled = read_config_option('boost_rrd_update_enable', 'off');
	$has_global_poller_output = false;

	if ($boost_enabled == 'on') {
		$sql    = "select optvalue from weathermap_settings where optname='rrd_use_poller_output' and mapid=0";

		$result = db_fetch_row($sql);

		if (isset($result['optvalue'])) {
			$has_global_poller_output = $result['optvalue'];
		} else {
			$has_global_poller_output = false;
		}

		if (!$has_global_poller_output) {
			print '<div align="center" class="wm_warning"><p>';
			print "You are using the Boost plugin to update RRD files. Because this delays data being written to the files, it causes issues with Weathermap updates. You can resolve this by using Weathermap's 'poller_output' support, which grabs data directly from the poller. <a href=\"?action=enable_poller_output\">You can enable that globally by clicking here</a>";
			print '</p></div>';
		}
	}

	if (($last_finish_time - $last_start_time) > $poller_interval) {
		if (($last_started != $last_finished) && ($last_started != "")) {
			print '<div align="center" class="wm_warning"><p>';
			print "Last time it ran, Weathermap did NOT complete it's run. It failed during processing for '$last_started'. ";
			print "This <strong>may</strong> have affected other plugins that run during the poller process. </p><p>";
			print "You should either disable this map, or fault-find. Possible causes include memory_limit issues. The log may have more information.";
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

	$nav = html_nav_bar('weathermap-cacti-plugin-editor.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Maps'), 'page', 'main');

    form_start('weathermap-cacti-plugin-editor.php', 'chk');

    print $nav;

    html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		__('Config File', 'weathermap'),
		__('Title', 'weathermap'),
		__('Group', 'weathermap'),
		__('Active', 'weathermap'),
		__('Settings', 'weathermap'),
		__('Sort Order', 'weathermap'),
		__('Accessible By', 'weathermap')
	);

	html_header_checkbox($display_text);

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

			print '<td><a class="pic" title="Click to start editor with this file" href="weathermap-cacti-plugin-editor.php?action=nothing&mapname=' . html_escape($map['configfile']) . '">' . html_escape($map['configfile']) . '</a>';

			if ($map['warncount'] > 0) {
				$had_warnings++;

				print '<a class="pic" href="' . html_escape('../../utilities.php?tail_lines=500&message_type=2&action=view_logfile&filter=' . $map['configfile']) . '" title="Check cacti.log for this map"><i class="deviceRecovering fa fa-exclamation-triangle"></i></a>';
			}

			print "</td>";

			print '<td>' . html_escape($map['titlecache']) . '</td>';
			print '<td><a class="pic" title="Click to change group" href="?action=chgroup&id=' . $map['id'] . '">' . html_escape($map['groupname']) . '</a></td>';

			if ($map['active'] == 'on') {
				print '<td class="wm_enabled"><a class="pic" title="Click to Deactivate" href="' . html_escape('?action=deactivate_map&id=' . $map['id']) . '"><font color="green">Yes</font></a>';
			} else {
				print '<td class="wm_disabled"><a class="pic" title="Click to Activate" href="' . html_escape('?action=activate_map&id=' . $map['id']) . '"><font color="red">No</font></a>';
			}
			print "<td>";

			print "<a class='pic' href='?action=map_settings&id=" . $map['id'] . "'>";

			$setting_count = db_fetch_cell("select count(*) from weathermap_settings where mapid=" . $map['id']);

			if ($setting_count > 0) {
				print $setting_count . " special";

				if ($setting_count > 1) {
					print "s";
				}
			} else {
				print "standard";
			}

			print "</a>";
			print "</td>";
			print '</td>';
			print '<td>';
			print '<a href="?action=move_map_up&order=' . $map['sortorder'] . '&id=' . $map['id'] . '"><img src="../../images/move_up.gif" width="14" height="10" border="0" alt="Move Map Up" title="Move Map Up"></a>';
			print '<a href="?action=move_map_down&order=' . $map['sortorder'] . '&id=' . $map['id'] . '"><img src="../../images/move_down.gif" width="14" height="10" border="0" alt="Move Map Down" title="Move Map Down"></a>';
			// print $map['sortorder'];

			print "</td>";

			print '<td>';

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

			print '<a title="Click to edit permissions" href="?action=perms_edit&id=' . $map['id'] . '&header=false">';

			if (count($mapusers) == 0) {
				print "(no users)";
			} else {
				print join(", ", $mapusers);
			}

			print '</a>';

			print '</td>';

			form_checkbox_cell($map['titlecache'], $map['id']);

			form_end_row();
		}
	}

	if (!cacti_sizeof($maps)) {
		print '<tr><td><em>' . __esc('No Weathermaps Found', 'weathermap') . '</em></td></tr>';
	}

	html_end_box();

    html_start_box('', '100%', '', '3', 'center', '');

	if ($had_warnings > 0) {
		print '<div align="center" class="wm_warning">' . __('One or more of your maps had warnings last time they ran. You can try to find these in your Cacti log file or by clicking on the warning sign next to that map.  The number of maps with issues was %s.', $bad_wranings) . '</div>';
	}

	html_end_box();
}

function addmap_picker($show_all = false) {
	global $weathermap_confdir;
	global $colors;

	$loaded = array();
	$flags  = array();

	// find out what maps are already in the database, so we can skip those
	$queryrows = db_fetch_assoc("SELECT * FROM weathermap_maps");

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

			html_header(array('', '', 'Config File', 'Title', ''), 2);

			while ($file = readdir($dh)) {
				$realfile = $weathermap_confdir . '/' . $file;

				// skip .-prefixed files like .htaccess, since it seems
				// that otherwise people will add them as map config files.
				// and the index.php too - for the same reason
				if (substr($file, 0, 1) != '.' && $file != 'index.php') {
					$used           = in_array($file, $loaded);
					$flags[$file] = '';

					if ($used) {
						$flags[$file] = 'USED';
					}

					if (is_file($realfile)) {
						if ($used && !$show_all) {
							$skipped++;
						} else {
							$title           = wmap_get_title($realfile);
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

					form_alternate_row_color($colors["alternate"], $colors["light"], $i);

					print '<td><a href="?action=addmap&amp;file=' . $file . '" title="Add the configuration file">Add</a></td>';
					print '<td><a href="?action=viewconfig&amp;file=' . $file . '" title="View the configuration file in a new window" target="_blank">View</a></td>';
					print '<td>' . html_escape($file);

					if ($flags[$file] == 'USED') {
						print ' <b>(USED)</b>';
					}

					print '</td>';
					print '<td><em>' . html_escape($title) . '</em></td>';
					print '</tr>';

					$i++;
				}
			}

			if (($i + $skipped) == 0) {
				print "<tr><td>No files were found in the configs directory.</td></tr>";
			}

			if (($i == 0) && $skipped > 0) {
				print "<tr><td>($skipped files weren't shown because they are already in the database</td></tr>";
			}
		} else {
			print "<tr><td>Can't open $weathermap_confdir to read - you should set it to be readable by the webserver.</td></tr>";
		}
	} else {
		print "<tr><td>There is no directory named $weathermap_confdir - you will need to create it, and set it to be readable by the webserver. If you want to upload configuration files from inside Cacti, then it should be <i>writable</i> by the webserver too.</td></tr>";
	}

	html_end_box();

	if ($skipped > 0) {
		print "<p align=center>Some files are not shown because they have already been added. You can <a href='?action=addmap_picker&show=all'>show these files too</a>, if you need to.</p>";
	}

	if ($show_all) {
		print "<p align=center>Some files are shown even though they have already been added. You can <a href='?action=addmap_picker'>hide those files too</a>, if you need to.</p>";
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
		print "<h3>Path mismatch</h3>";
	} else {
		html_start_box("<strong>Preview of $file</strong>", "98%", $colors["header"], "3", "center", "");

		print '<tr><td valign="top" bgcolor="#' . $colors["light"] . '" class="textArea">';
		print '<pre>';

		$realfile = $weathermap_confdir . '/' . $file;

		if (is_file($realfile)) {
			$fd = fopen($realfile, "r");

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
		print "<h3>Path mismatch</h3>";
	} else {
		$realfile = $weathermap_confdir . DIRECTORY_SEPARATOR . $file;
		$title    = wmap_get_title($realfile);

		db_execute_prepared("INSERT INTO weathermap_maps
			(configfile, titlecache, active, imagefile, htmlfile, filehash, config)
			VALUES (?, ?, 'on', '', '', '', '')",
			array($file, $title));

		$last_id = db_fetch_insert_id();
		$myuid   = (isset($_SESSION["sess_user_id"]) ? intval($_SESSION["sess_user_id"]) : 1);

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
	$title = "(no title)";

	$fd    = fopen($filename, "r");

	if (is_resource($fd)) {
		while (!feof($fd)) {
			$buffer = fgets($fd, 4096);
			if (preg_match("/^\s*TITLE\s+(.*)/i", $buffer, $matches)) {
				$title = $matches[1];
			}

			// this regexp is tweaked from the ReadConfig version, to only match TITLEPOS lines *with* a title appended
			if (preg_match("/^\s*TITLEPOS\s+\d+\s+\d+\s+(.+)/i", $buffer, $matches)) {
				$title = $matches[1];
			}

			// strip out any DOS line endings that got through
			$title = str_replace("\r", "", $title);
		}

		fclose($fd);
	}

	return ($title);
}

function map_deactivate($id) {
	$SQL = "update weathermap_maps set active='off' where id=" . $id;
	db_execute($SQL);
}

function map_activate($id) {
	$SQL = "update weathermap_maps set active='on' where id=" . $id;
	db_execute($SQL);
}

function map_delete($id) {
	$SQL = "delete from weathermap_maps where id=" . $id;
	db_execute($SQL);

	$SQL = "delete from weathermap_auth where mapid=" . $id;
	db_execute($SQL);

	$SQL = "delete from weathermap_settings where mapid=" . $id;
	db_execute($SQL);

	map_resort();
}

function weathermap_set_group($mapid, $groupid) {
	# print "UPDATING";
	$SQL = sprintf("update weathermap_maps set group_id=%d where id=%d", $groupid, $mapid);
	db_execute($SQL);
	map_resort();
}

function perms_add_user($mapid, $userid) {
	$SQL = "insert into weathermap_auth (mapid,userid) values($mapid,$userid)";
	db_execute($SQL);
}

function perms_delete_user($mapid, $userid) {
	$SQL = "delete from weathermap_auth where mapid=$mapid and userid=$userid";
	db_execute($SQL);
}

function perms_list($id) {
	global $colors;

	// $title_sql = "select titlecache from weathermap_maps where id=$id";
	$title = db_fetch_cell("select titlecache from weathermap_maps where id=" . intval($id));
	// $title = $results[0]['titlecache'];

	$auth_sql = "select * from weathermap_auth where mapid=$id order by userid";

	$query      = db_fetch_assoc("select id,username from user_auth order by username");

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

	$userselect = "";
	foreach ($users as $uid => $name) {
		if (!in_array($uid, $mapuserids)) $userselect .= "<option value=\"$uid\">$name</option>\n";
	}

	html_start_box("<strong>Edit permissions for Weathermap $id: $title</strong>", "70%", $colors["header"], "2", "center", "");

	html_header(array("Username", ""));

	$n = 0;
	foreach ($mapuserids as $user) {
		form_alternate_row_color($colors["alternate"], $colors["light"], $n);
		print "<td>" . $users[$user] . "</td>";
		print '<td><a href="?action=perms_delete_user&mapid=' . $id . '&userid=' . $user . '"><img src="../../images/delete_icon.gif" width="10" height="10" border="0" alt="Remove permissions for this user to see this map"></a></td>';

		print "</tr>";
		$n++;
	}

	if ($n == 0) {
		print "<tr><td><em><strong>nobody</strong> can see this map</em></td></tr>";
	}

	html_end_box();

	html_start_box("", "70%", $colors["header"], "3", "center", "");

	print "<tr>";

	if ($userselect == '') {
		print "<td><em>There aren't any users left to add!</em></td></tr>";
	} else {
		print "<td><form action=\"\">Allow <input type=\"hidden\" name=\"action\" value=\"perms_add_user\"><input type=\"hidden\" name=\"mapid\" value=\"$id\"><select name=\"userid\">";
		print $userselect;
		print "</select> to see this map <input type=\"submit\" value=\"Update\"></form></td>";
		print "</tr>";
	}

	html_end_box();
}

function weathermap_map_settings($id) {
	global $colors, $config;

	if ($id == 0) {
		$title       = "Additional settings for ALL maps";
		$nonemsg     = "There are no settings for all maps yet. You can add some by clicking Add up in the top-right, or choose a single map from the management screen to add settings for that map.";
		$type        = "global";
		$settingrows = db_fetch_assoc("select * from weathermap_settings where mapid=0 and groupid=0");
	} elseif ($id < 0) {
		$group_id    = -intval($id);
		$groupname   = db_fetch_cell("select name from weathermap_groups where id=" . $group_id);
		$title       = "Edit per-map settings for Group " . $group_id . ": " . $groupname;
		$nonemsg     = "There are no per-group settings for this group yet. You can add some by clicking Add up in the top-right.";
		$type        = "group";
		$settingrows = db_fetch_assoc("select * from weathermap_settings where groupid=" . $group_id);
	} else {
		// print "Per-map settings for map $id";
		$map = db_fetch_row("select * from weathermap_maps where id=" . intval($id));

		$groupname   = db_fetch_cell("select name from weathermap_groups where id=" . intval($map['group_id']));
		$title       = "Edit per-map settings for Weathermap $id: " . $map['titlecache'];
		$nonemsg     = "There are no per-map settings for this map yet. You can add some by clicking Add up in the top-right.";
		$type        = "map";
		$settingrows = db_fetch_assoc("select * from weathermap_settings where mapid=" . intval($id));
	}

	if ($type == "group") {
		print "<p>All maps in this group are also affected by the following GLOBAL settings (group overrides global, map overrides group, but BOTH override SET commands within the map config file):</p>";
		weathermap_readonly_settings(0, "Global Settings");
	}

	if ($type == "map") {
		print "<p>This map is also affected by the following GLOBAL and GROUP settings (group overrides global, map overrides group, but BOTH override SET commands within the map config file):</p>";

		weathermap_readonly_settings(0, "Global Settings");

		weathermap_readonly_settings(-$map['group_id'], "Group Settings (" . html_escape($groupname) . ")");
	}

	html_start_box("<strong>$title</strong>", "70%", $colors["header"], "2", "center", "weathermap-cacti-plugin-mgmt.php?action=map_settings_form&mapid=" . intval($id));

	html_header(array("", "Name", "Value", ""));

	$n = 0;

	if (is_array($settingrows)) {
		if (cacti_sizeof($settingrows) > 0) {
			foreach ($settingrows as $setting) {
				form_alternate_row_color($colors["alternate"], $colors["light"], $n);

				print '<td><a href="?action=map_settings_form&mapid=' . $id . '&id=' . intval($setting['id']) . '"><img src="../../images/graph_properties.gif" width="16" height="16" border="0" alt="Edit this definition">Edit</a></td>';
				print "<td>" . html_escape($setting['optname']) . "</td>";
				print "<td>" . html_escape($setting['optvalue']) . "</td>";
				print '<td><a href="?action=map_settings_delete&mapid=' . $id . '&id=' . intval($setting['id']) . '"><img src="../../images/delete_icon_large.gif" width="12" height="12" border="0" alt="Remove this definition from this map"></a></td>';
				print "</tr>";

				$n++;
			}
		} else {
			print "<tr>";
			print "<td colspan=2>$nonemsg</td>";
			print "</tr>";
		}
	}

	html_end_box();

	print "<div align=center>";

	if ($type == "group") {
		print "<a href='?action=groupadmin'>Back to Group Admin</a>";
	}

	if ($type == "global") {
		print "<a href='?action='>Back to Map Admin</a>";
	}

	print "</div>";
}

function weathermap_readonly_settings($id, $title = "Settings") {
	global $colors, $config;

	if ($id == 0) $query = "select * from weathermap_settings where mapid=0 and groupid=0";
	if ($id < 0) $query = "select * from weathermap_settings where mapid=0 and groupid=" . (-intval($id));
	if ($id > 0) $query = "select * from weathermap_settings where mapid=" . intval($id);

	$settings = db_fetch_assoc($query);

	html_start_box("<strong>$title</strong>", "70%", $colors["header"], "2", "center", "");
	html_header(array("", "Name", "Value", ""));

	$n = 0;

	if (cacti_sizeof($settings) > 0) {
		foreach ($settings as $setting) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $n);

			print "<td></td>";
			print "<td>" . html_escape($setting['optname']) . "</td><td>" . html_escape($setting['optvalue']) . "</td>";
			print "<td></td>";
			print "</tr>";

			$n++;
		}
	} else {
		form_alternate_row_color($colors["alternate"], $colors["light"], $n);

		print "<td colspan=4><em>No Settings</em></td>";
		print "</tr>";
	}

	html_end_box();

}

function weathermap_map_settings_form($mapid = 0, $settingid = 0) {
	global $colors, $config;

	// print "Per-map settings for map $id";

	if ($mapid > 0) {
		$title = db_fetch_cell("select titlecache from weathermap_maps where id=" . intval($mapid));
	} elseif ($mapid < 0) {
		$title = db_fetch_cell("select name from weathermap_groups where id=" . intval(-$mapid));
	}

	// print "Settings edit/add form.";

	$name  = "";
	$value = "";

	if ($settingid != 0) {
		$result = db_fetch_assoc("select * from weathermap_settings where id=" . intval($settingid));

		if (is_array($result) && sizeof($result) > 0) {
			$name  = $result[0]['optname'];
			$value = $result[0]['optvalue'];
		}
	}

	# print "$mapid $settingid |$name| |$value|";

	$values_ar = array();

	$field_ar = array(
		"mapid" => array("friendly_name" => "Map ID", "method" => "hidden_zero", "value" => $mapid),
		"id"    => array("friendly_name" => "Setting ID", "method" => "hidden_zero", "value" => $settingid),
		"name"  => array("friendly_name" => "Name", "method" => "textbox", "max_length" => 128, "description" => "The name of the map-global SET variable", "value" => $name),
		"value" => array("friendly_name" => "Value", "method" => "textbox", "max_length" => 128, "description" => "What to set it to", "value" => $value)
	);

	$action = "Edit";

	if ($settingid == 0) {
		$action = "Create";
	}

	if ($mapid == 0) {
		$title = "setting for ALL maps";
	} elseif ($mapid < 0) {
		$grpid = -$mapid;
		$title = "per-group setting for Group $grpid: $title";
	} else {
		$title = "per-map setting for Weathermap $mapid: $title";
	}

	html_start_box("<strong>$action $title</strong>", "98%", $colors["header"], "3", "center", "");
	draw_edit_form(array("config" => $values_ar, "fields" => $field_ar));
	html_end_box();

	form_save_button("weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid);
}

function weathermap_setting_save($mapid, $name, $value) {
	if ($mapid > 0) {
		db_execute_prepared("REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)",
			array($mapid, 0, $name, $value));
	} elseif ($mapid < 0) {
		db_execute_prepared("REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)",
			array(0, -$mapid, $name, $value));
	} else {
		db_execute_prepared("REPLACE INFO weathermap_settings
			(mapid, groupid, optname, optvalue)
			VALUES (?, ?, ?, ?)",
			array(0, 0, $name, $value));
	}
}

function weathermap_setting_update($mapid, $settingid, $name, $value) {
	db_execute_prepared("UPDATE weathermap_settings
		SET optname = ?, optvalue = ?
		WHERE id = ?",
		array($name, $value, intval($settingid)));
}

function weathermap_setting_delete($mapid, $settingid) {
	db_execute_preapred("DELECT FROM weathermap_settings
		WHERE id = ?
		AND mapid = ?",
		array(intval($settingid), intval($mapid)));
}

function weathermap_chgroup($id) {
	global $colors;

	$title    = db_fetch_cell("select titlecache from weathermap_maps where id=" . intval($id));
	$curgroup = db_fetch_cell("select group_id from weathermap_maps where id=" . intval($id));

	$n = 0;

	print "<form>";
	print "<input type=hidden name='map_id' value='" . $id . "'>";
	print "<input type=hidden name='action' value='chgroup_update'>";

	html_start_box("<strong>Edit map group for Weathermap $id: $title</strong>", "70%", $colors["header"], "2", "center", "");

	# html_header(array("Group Name", ""));
	form_alternate_row_color($colors["alternate"], $colors["light"], $n++);
	print "<td><strong>Choose an existing Group:</strong><select name='new_group'>";
	$SQL     = "select * from weathermap_groups order by sortorder";
	$results = db_fetch_assoc($SQL);

	foreach ($results as $grp) {
		print "<option ";

		if ($grp['id'] == $curgroup) {
			print " selected ";
		}

		print "value=" . $grp['id'] . ">" . html_escape($grp['name']) . "</option>";
	}

	print "</select>";
	print '<input type="image" src="../../images/button_save.gif"  border="0" alt="Change Group" title="Change Group" />';
	print "</td>";
	print "</tr>\n";
	print "<tr><td></td></tr>";

	print "<tr><td><p>or create a new group in the <strong><a href='?action=groupadmin'>group management screen</a></strong></p></td></tr>";

	html_end_box();

	print "</form>\n";
}

function weathermap_group_form($id = 0) {
	global $colors, $config;

	$grouptext = "";
	// if id==0, it's an Add, otherwise it's an editor.
	if ($id == 0) {
		print "Adding a group...";
	} else {
		print "Editing group $id\n";
		$grouptext = db_fetch_cell("select name from weathermap_groups where id=" . $id);
	}

	print "<form action=weathermap-cacti-plugin-mgmt.php>\n<input type=hidden name=action value=group_update />\n";

	print "Group Name: <input name=gname value='" . html_escape($grouptext) . "'/>\n";

	if ($id > 0) {
		print "<input type=hidden name=id value=$id />\n";
		print "Group Name: <input type=submit value='Update' />\n";
	} else {
		# print "<input type=hidden name=id value=$id />\n";
		print "Group Name: <input type=submit value='Add' />\n";
	}

	print "</form>\n";
}

function weathermap_group_editor() {
	global $colors, $config;

	html_start_box("<strong>Edit Map Groups</strong>", "70%", $colors["header"], "2", "center", "weathermap-cacti-plugin-mgmt.php?action=group_form&id=0");
	html_header(array("", "Group Name", "Settings", "Sort Order", ""));

	$groups = db_fetch_assoc("select * from weathermap_groups order by sortorder");

	$n = 0;

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $n);

			print '<td><a href="weathermap-cacti-plugin-mgmt.php?action=group_form&id=' . intval($group['id']) . '"><img src="../../images/graph_properties.gif" width="16" height="16" border="0" alt="Rename This Group" title="Rename This Group">Rename</a></td>';
			print "<td>" . html_escape($group['name']) . "</td>";

			print "<td>";

			print "<a href='?action=map_settings&id=-" . $group['id'] . "'>";

			$setting_count = db_fetch_cell("select count(*) from weathermap_settings where mapid=0 and groupid=" . $group['id']);

			if ($setting_count > 0) {
				print $setting_count . " special";

				if ($setting_count > 1) {
					print "s";
				}
			} else {
				print "standard";
			}

			print "</a>";
			print "</td>";
			print '<td>';
			print '<a href="weathermap-cacti-plugin-mgmt.php?action=move_group_up&order=' . $group['sortorder'] . '&id=' . $group['id'] . '"><img src="../../images/move_up.gif" width="14" height="10" border="0" alt="Move Group Up" title="Move Group Up"></a>';
			print '<a href="weathermap-cacti-plugin-mgmt.php?action=move_group_down&order=' . $group['sortorder'] . '&id=' . $group['id'] . '"><img src="../../images/move_down.gif" width="14" height="10" border="0" alt="Move Group Down" title="Move Group Down"></a>';
			// print $map['sortorder'];

			print "</td>";
			print '<td>';

			if ($group['id'] > 1) {
				print '<a href="weathermap-cacti-plugin-mgmt.php?action=groupadmin_delete&id=' . intval($group['id']) . '"><img src="../../images/delete_icon.gif" width="10" height="10" border="0" alt="Remove this definition from this map"></a>';
			}

			print '</td>';
			print "</tr>";

			$n++;
		}
	} else {
		print "<tr>";
		print "<td colspan=2>No groups are defined.</td>";
		print "</tr>";
	}

	html_end_box();
}

function weathermap_group_create($newname) {
	$sortorder = db_fetch_cell_prepared('SELECT MAX(sortorder)+1
		FROM weathermap_groups');

	db_execute_prepared("INSERT INFO weathermap_groups
		(name, sortorder)
		VALUES (?, ?)",
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
	db_execute_prepared("UPDATE weathermap_maps
		SET group_id = ?
		WHERE group_id= ?",
		array($newid, $id));

	# then delete the group
	db_execute_prepared("DELETE FROM weathermap_groups
		WHERE id = ?",
		array($id));
}

