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

chdir('../../');
include_once('./include/auth.php');
include_once($config['library_path'] . '/rrd.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WeatherMap.class.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/poller-common.php');

$weathermap_confdir = realpath(__DIR__ . '/configs');

$actions = array(
	'1' => __('Delete', 'weathermap'),
	'2' => __('Duplicate', 'weathermap'),
	'3' => __('Disable', 'weathermap'),
	'4' => __('Enable', 'weathermap'),
//	'5' => __('Change Permissions', 'weathermap'),
//	'6' => __('Change Group', 'weathermap'),
	'7' => __('Rebuild Now', 'weathermap')
);

$perm_actions = array(
	'1' => __('Grant Access', 'weathermap'),
	'2' => __('Revoke Access', 'weathermap')
);

set_default_action();

switch (get_request_var('action')) {
	case 'actions':
		weathermap_form_actions();

		break;
	case 'enable_poller_output':
		weathermap_setting_save(0, 'rrd_use_poller_output', 1);
		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=0');

		break;
	case 'group_update':
		$id      = get_filter_request_var('id');
		$newname = get_request_var('gname');

		if ($id >= 0 && $newname != '') {
			weathermap_group_update($id, $newname);
		}

		if (empty($id) && $newname != '') {
			weathermap_group_create($newname);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'groupadmin_delete':
		$id = get_filter_request_var('id');

		if ($id >= 1) {
			weathermap_group_delete($id);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'group_form':
		$id = -1;

		top_header();

		$id = get_filter_request_var('id');

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

		$mapid   = get_filter_request_var('map_id');
		$groupid = get_filter_request_var('new_group');

		if (($groupid > 0) && ($mapid >= 0)) {
			weathermap_set_group($mapid, $groupid);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'chgroup':
		top_header();
		weathermap_chgroup(get_filter_request_var('id'));
		bottom_footer();

		break;
	case 'map_settings_delete':
		$mapid     = get_filter_request_var('mapid');
		$settingid = get_filter_request_var('id');

		if (!is_null($mapid) && !is_null($settingid)) {
			weathermap_setting_delete($mapid, $settingid);
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid);

		break;
	case 'save':
		$mapid     = get_filter_request_var('mapid');
		$settingid = get_filter_request_var('id');
		$name      = get_nfilter_request_var('name');
		$value     = get_nfilter_request_var('value');

		weathermap_setting_update($mapid, $settingid, $name, $value);

		header('Location: weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid);

		break;
	case 'map_settings_form':
		top_header();

		$mapid = get_filter_request_var('mapid');
		$id    = get_filter_request_var('id');

		if ($id > 0) {
			weathermap_map_settings_form($mapid, $id);
		} else {
			weathermap_map_settings_form($mapid);
		}

		bottom_footer();

		break;
	case 'map_settings':
		top_header();
		weathermap_map_settings(get_filter_request_var('id'));
		bottom_footer();

		break;
	case 'perms_add_user':
		$mapid  = get_filter_request_var('mapid');
		$userid = get_filter_request_var('userid');

		perms_add_user($mapid, $userid);
		header('Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $mapid);

		break;
	case 'perms_delete_user':
		$mapid  = get_filter_request_var('mapid');
		$userid = get_filter_request_var('userid');

		perms_delete_user($mapid, $userid);
		header('Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $mapid);

		break;
	case 'perms_edit':
		top_header();
		perms_list(get_filter_request_var('id'));
		bottom_footer();

		break;
	case 'delete_map':
		map_delete(get_filter_request_var('id'));

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'deactivate_map':
		map_deactivate(get_filter_request_var('id'));

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'activate_map':
		map_activate(get_filter_request_var('id'));

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_map_up':
		$id = get_filter_request_var('id');

		map_move($id, -1);

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_map_down':
		$id = get_filter_request_var('id');

		map_move($id, 1);

		header('Location: weathermap-cacti-plugin-mgmt.php');

		break;
	case 'move_group_up':
		$id = get_filter_request_var('id');

		weathermap_group_move($id, -1);

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'move_group_down':
		$id = get_filter_request_var('id');

		weathermap_group_move($id, 1);

		header('Location: weathermap-cacti-plugin-mgmt.php?action=groupadmin');

		break;
	case 'viewconfig':
		top_graph_header();

		if (isset_request_var('file')) {
			preview_config(get_nfilter_request_var('file'));
		} else {
			print __('No such file.', 'weathermap');
		}

		bottom_footer();

		break;
	case 'addmap_picker':
		top_header();

		if (isset_request_var('show') && get_nfilter_request_var('show') == 'all') {
			addmap_picker(true);
		} else {
			addmap_picker(false);
		}

		bottom_footer();

		break;
	case 'addmap':
		if (isset_request_var('file')) {
			add_config(get_nfilter_request_var('file'));

			header('Location: weathermap-cacti-plugin-mgmt.php');
		} else {
			print __('No such file.', 'weathermap');
		}

		break;
	case 'dupmap':
		if (isset_request_var('mapid') && isset_request_var('file') && isset_request_var('title')) {
			map_duplicate(get_filter_request_var('mapid'), get_nfilter_request_var('title'), get_nfilter_request_var('file'));

			header('Location: weathermap-cacti-plugin-mgmt.php');
		}

		break;
	case 'rebuildnow':
		$start = microtime(true);

		weathermap_run_maps(__DIR__, true);

		$end = microtime(true);

		raise_message('rebuild_all', __('All Maps have been Rebuilt in %0.2f Seconds!', $end - $start, 'weathermap'), MESSAGE_LEVEL_INFO);

		header('Location: ' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?header=false');
		exit;

		break;
	default:
		// by default, just list the map setup
		top_header();

		maplist();

		// Ensure that the map config file directory is writable
		$mapdir = $config['base_path'] . '/plugins/weathermap/configs';

		if (!is_writable($mapdir)) {
			cacti_log("FATAL: The map config directory ($mapdir) is not writable by the web server user. You will not be able to edit any files until this is corrected. [WMEDIT01]", true, 'WEATERMAP');

			raise_message_javascript(__('Weathermap Permission Error', 'weathermap'), __('Editor directory permissions are not correct!', 'weathermap'), __('The Web Service account must have access to the WeatherMap config directory which it does not have.  Correct this issue, and then relaunch the Editor', 'weathermap'));
		}

		bottom_footer();

		break;
}

function weathermap_form_actions() {
	global $actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('associate_perms')) {
		$removed = $added = 0;

		$mapid = get_filter_request_var('mapid');

		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9:a-z]+)$/', $var, $matches)) {
				$parts = explode(':', $matches[1]);

				/* ================= input validation ================= */
				input_validate_input_number($parts[0]);
				/* ==================================================== */

                if (get_nfilter_request_var('drp_action') == '1') {
					$added++;

					if ($parts[1] == 'group') {
						db_execute_prepared('REPLACE INTO weathermap_auth
							(userid, mapid)
							VALUES (?, ?)',
							array(-$parts[0], $mapid));
					} else {
						db_execute_prepared('REPLACE INTO weathermap_auth
							(userid, mapid)
							VALUES (?, ?)',
							array($parts[0], $mapid));
					}
                } else {
					$removed++;

					if ($parts[1] == 'group') {
						db_execute_prepared('DELETE FROM weathermap_auth
							WHERE userid = ? AND mapid = ?',
							array(-$parts[0], $mapid));
					} else {
						db_execute_prepared('DELETE FROM weathermap_auth
							WHERE userid = ? AND mapid = ?',
							array($parts[0], $mapid));
					}
                }
            }
        }

        header('Location: weathermap-cacti-plugin-mgmt.php?action=perms_edit&header=false&id=' . get_nfilter_request_var('mapid'));
        exit;
	} elseif (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') === '1') { // delete
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					map_delete($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') === '2') { // duplicate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					$titlecache = get_nfilter_request_var('title_format');
					$configfile = get_nfilter_request_var('configfile_format');

					map_duplicate($selected_items[$i], $titlecache, $configfile);
				}
			} elseif (get_nfilter_request_var('drp_action') === '3') { // disable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					map_deactivate($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') === '4') { // enable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					map_activate($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') === '7') { // run now
				$maps = array();

				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $item) {
						/* ================= input validation ================= */
						input_validate_input_number($item);
						/* ==================================================== */

						$maps[] = $item;
					}

					$start = microtime(true);

					weathermap_run_maps(__DIR__, true, $maps);

					$end = microtime(true);

					raise_message('rebuild_selected', __('The %d Selected Maps have been Rebuilt in %0.2f Seconds!', cacti_sizeof($maps), $end - $start, 'weathermap'), MESSAGE_LEVEL_INFO);
				}
			}
		}

		header('Location: weathermap-cacti-plugin-mgmt.php?header=false');

		exit;
	}

	/* setup some variables */
	$list = '';

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT titlecache FROM weathermap_maps WHERE id = ?', array($matches[1]))) . '</li>';
			$array[] = $matches[1];
		}
	}

	top_header();

	form_start('weathermap-cacti-plugin-mgmt.php', 'actions');

	html_start_box($actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($array)) {
		if (get_nfilter_request_var('drp_action') === '1') { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following Weather Map.', 'Click \'Continue\' to delete following Weather Maps.', cacti_sizeof($array)) . "</p>
						<div class='itemlist'><ul>$list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Delete Weather Map', 'Delete Weather Maps', cacti_sizeof($array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '2') { // duplicate
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to duplicate the following Weather Map. You can optionally change the title format for the new Weather Map.', 'Click \'Continue\' to duplicate following Weather Maps. You can optionally change the title format for the new Weather Maps.', cacti_sizeof($array)) . "</p>
					<div class='itemlist'><ul>$list</ul></div>
					<p><strong>" . __('Title Format:') . '</strong><br>';

			form_text_box('title_format', '<map_title> (1)', '', '255', '30', 'text');

			print '</p>';

			print '<p><strong>' . __('Config File Format:') . '</strong><br>';

			form_text_box('configfile_format', '<map_config> (1)', '', '255', '30', 'text');

			print "</p>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate Weather Map', 'Duplicate Weather Maps', cacti_sizeof($array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '3') { // disable
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to disable the following Weather Map. You can optionally change the title format for the new Weather Map.', 'Click \'Continue\' to disable following Weather Maps. You can optionally change the title format for the new Weather Maps.', cacti_sizeof($array)) . "</p>
					<div class='itemlist'><ul>$list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Disable Weather Map', 'Disable Weather Maps', cacti_sizeof($array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '4') { // enable
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to enable the following Weather Map. You can optionally change the title format for the new Weather Map.', 'Click \'Continue\' to enable following Weather Maps. You can optionally change the title format for the new Weather Maps.', cacti_sizeof($array)) . "</p>
					<div class='itemlist'><ul>$list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Enable Weather Map', 'Enable Weather Maps', cacti_sizeof($array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '7') { // run now
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Rebuild the following Weather Map. You can optionally change the title format for the new Weather Map.', 'Click \'Continue\' to Rebuild the following Weather Maps. You can optionally change the title format for the new Weather Maps.', cacti_sizeof($array)) . "</p>
					<div class='itemlist'><ul>$list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Rebuild Weather Map', 'Rebuild Weather Maps', cacti_sizeof($array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: weathermap-cacti-plugin-mgmt.php?header=false');
		exit;
	}

    print "<tr>
        <td class='saveRow'>
            <input type='hidden' name='action' value='actions'>
            <input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
            <input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
            $save_html
        </td>
    </tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function weathermap_footer_links() {
	$weathermap_version = plugin_weathermap_numeric_version();

	print '<br />';

	html_start_box('<a target="_blank" class="linkOverDark" href="docs/">' . __('Local Documentation', 'weathermap') . '</a> -- <a target="_blank" class="linkOverDark" href="http://www.network-weathermap.com/">' . __('Weathermap Website', 'weathermap') . '</a> -- <a target="_target" class="linkOverDark" href="weathermap-cacti-plugin-editor.php">' . __('Weathermap Editor', 'weathermap') . '</a> -- ' . __('This is version %s', $weathermap_version), '100%', '', '3', 'center', '');

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
	$last_g = -1;

	if (cacti_sizeof($list)) {
		foreach ($list as $map) {
			if ($last_g != $map['group_id']) {
				$last_g = $map['group_id'];
				$i = 1;
			}

			db_execute_prepared('UPDATE weathermap_maps
				SET sortorder = ?
				WHERE id = ?',
				array($i, $map['id']));

			$i++;
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
		db_execute_prepared("UPDATE weathermap_groups
			SET sortorder = ?
			WHERE id = ?",
			array($i, $group['id']));

		$i++;
	}
}

function map_move($mapid, $direction) {
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
			db_execute_prepared('UPDATE weathermap_maps
				SET sortorder = ?
				WHERE id = ?',
				array($neworder, $mapid));

			// then find the other one with the same sortorder and move that in the opposite direction
			db_execute_prepared('UPDATE weathermap_maps
				SET sortorder = ?
				WHERE id = ?',
				array($oldorder, $otherid));
		}
	}
}

function weathermap_group_move($id, $direction) {
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
							<input type='button' id='wm_rebuild' value='<?php print __esc('Rebuild All', 'weathermap');?>' title='<?php print __esc('Rebuild all maps now in background', 'weathermap');?>'>
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
		$sql_where = 'WHERE (wm.titlecache LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'wm.configfile LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

    $total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM weathermap_maps AS wm
		INNER JOIN weathermap_groups AS wmg
		ON wm.group_id = wmg.id
		$sql_where");

    $sql_order = 'ORDER BY group_id, sortorder';
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
			'display' => __('Title', 'weathermap'),
		),
		array(
			'display' => __('Config File', 'weathermap'),
		),
		array(
			'display' => __('ID', 'weathermap'),
			'align'   => 'center',
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
			'align'   => 'center',
		),
		array(
			'display' => __('Order', 'weathermap'),
			'align'   => 'center',
		),
		array(
			'display' => __('Accessible By', 'weathermap'),
		),
		array(
			'display' => __('Last Duration', 'weathermap'),
			'align'   => 'right'
		),
		array(
			'display' => __('Last Runtime', 'weathermap'),
			'align'   => 'right'
		)
	);

	html_header_checkbox($display_text, false);

	$query = db_fetch_assoc("SELECT id, username
		FROM user_auth
		WHERE enabled = 'on'");

	$users[0] = __('Anyone', 'weathermap');

	foreach ($query as $user) {
		$users[$user['id']] = $user['username'];
	}

	$had_warnings = 0;

	if (is_array($maps)) {
		foreach ($maps as $map) {
			form_alternate_row('line' . $map['id']);

			$output = '<a class="mapLink linkEditMain" title="' . __esc('Click to start editor with this file', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-editor.php?header=false&action=nothing&mapname=' . $map['configfile']) . '">' .
				html_escape($map['titlecache']) . '
			</a>';

			if ($map['warncount'] > 0) {
				$had_warnings++;

				$output .= '<a class="pic linkEditMain" title="' . __esc('Check cacti.log for this Map', 'weathermap') . '"
					href="' . html_escape('../../clog_user.php?tail_lines=500&message_type=2&action=view_logfile&filter=' . $map['configfile']) . '">
					<i class="deviceRecovering fa fa-exclamation-triangle"></i>
				</a>';
			}

			form_selectable_cell($output, $map['id']);
			form_selectable_cell(html_escape($map['configfile']), $map['id']);
			form_selectable_cell($map['id'], $map['id'], '', 'center');

			form_selectable_cell('<a class="pic linkEditMain" title="' . __esc('Click to change group', 'weathermap') . '"
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

			$url = '<a class="pic linkEditMain" href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $map['id']) . '">';

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
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_map_up&id=' . $map['id']) . '">
				<i class="fa fa-caret-up moveArrow"></i>
			</a>';

			$url .= '<a class="pic" title="' . __esc('Move Map Down', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=move_map_down&id=' . $map['id']) . '">
				<i class="fa fa-caret-down moveArrow"></i>
			</a>';

			form_selectable_cell($url, $map['id'], '', 'center');

			form_selectable_cell($map['sortorder'], $map['id'], '', 'center');

			$ulist = db_fetch_row_prepared('SELECT
				SUM(CASE WHEN userid = 0 THEN 1 ELSE NULL END) AS `special`,
				SUM(CASE WHEN userid > 0 THEN 1 ELSE NULL END) AS `users`,
				SUM(CASE WHEN userid < 0 THEN 1 ELSE NULL END) AS `groups`
				FROM weathermap_auth
				WHERE mapid = ?
				HAVING `special` > 0 OR `users` > 0 OR `groups` > 0',
				array($map['id']));

			$url = '<a class="pic linkEditMain" title="' . __esc('Click to edit permissions', 'weathermap') . '"
				href="' . html_escape('weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $map['id'] . '&header=false') . '">';

			if (cacti_sizeof($ulist) == 0) {
				$url .= __('No Users', 'weathermap');
			} else {
				$found = false;
				if ($ulist['special'] > 0) {
					$found = true;
					$url .= __('All Users', 'weathermap');
				}

				if ($ulist['groups'] > 0) {
					$url .= ($found ? ', ':'') . __n('1 Group', $ulist['groups'] . ' Groups', $ulist['groups'], 'weathermap');
				}

				if ($ulist['users'] > 0) {
					$url .= ($found ? ', ':'') . __n('1 User', $ulist['users'] . ' Users', $ulist['users'], 'weathermap');
				}
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

	?>
	<script type='text/javascript'>
	$(function() {
		$('.mapLink').off('click').on('click', function(event) {
			event.preventDefault();
			event.stopPropagation();
			document.location = $(this).attr('href');
		});
	});
	</script>
	<?php
}

function addmap_picker($show_all = false) {
	global $weathermap_confdir;

	$loaded = array();
	$flags  = array();

	// find out what maps are already in the database, so we can skip those
	$queryrows = db_fetch_assoc('SELECT * FROM weathermap_maps');

	if (is_array($queryrows)) {
		foreach ($queryrows as $map) {
			$loaded[$map['id']] = $map['configfile'];
		}
	}

	html_start_box(__('Available Weathermap Configuration Files', 'weathermap'), '100%', '', '3', 'center', '');

	if (is_dir($weathermap_confdir)) {
		$i       = 0;
		$skipped = 0;

		html_header(array( __('Actions', 'weathermap'), __('Title', 'weathermap'), __('Config File', 'weathermap')));

		$form_files = array();

		$id = 0;

		foreach(glob("$weathermap_confdir/*.conf") as $file) {
			$file     = basename($file);
			$realfile = $weathermap_confdir . '/' . $file;

			// Things about these files
			$used     = array_search($file, $loaded);
			$title    = wmap_get_title($realfile);

			// If it's already used, we can duplicate it
			if ($used) {
				$form_files[$i]['mapid']  = $used;
				$form_files[$i]['action'] = 'duplicate';
				$form_files[$i]['title']  = $title;
				$form_files[$i]['file']   = $file;
			} else {
				$form_files[$i]['action'] = 'add';
				$form_files[$i]['title']  = $title;
				$form_files[$i]['file']   = $file;
			}

			$i++;
		}

		$i = 0;

		if (cacti_sizeof($form_files)) {
			foreach ($form_files as $details) {
				form_alternate_row();

				$action = '';

				if ($details['action'] == 'add') {
					$file   = basename($details['file']);
					$url    = 'weathermap-cacti-plugin-mgmt.php?action=addmap&file=' . $file;
					$tip    = __esc('Add the configuration file %s to Weathermap', $file, 'weathermap');
					$value  = '<i class="fa fa-plus"></i>';
					$action = "<a class='pic deviceUp' href='$url' title='$tip'>$value</a>";
				} else {
					$file    = basename($details['file']);
					$newfile = map_get_next_name($file);

					$url = 'weathermap-cacti-plugin-mgmt.php?' .
						'action=dupmap' .
						'&mapid=' . $details['mapid'] .
						'&file=' . $file .
						'&title=' . $details['title'] . __(' Copy', 'weathermap');

					$tip    = __esc('Duplicate the configuration file %s and add to Weathermap as %s', $file, $newfile, 'weathermap');
					$value  = '<i class="fa fa-copy"></i>';
					$action = "<a class='pic deviceRecovering' href='$url' title='$tip'>$value</a>";
				}

				$tip   = __esc('View the configuration file %s in a new window', $file, 'weathermap');
				$url   = 'weathermap-cacti-plugin-mgmt.php?action=viewconfig&file=' . $file;
				$value = '<i class="fa fa-binoculars"></i>';

				$action .= "<a target='_new' href='$url' title='$tip'>$value</a>";

				form_selectable_cell($action, $i, '1%');

				if ($details['action'] == 'duplicate') {
					$file = $details['file'] . ' [ ' . __('NOTE: Already in use. Click duplicate to create a new file.', 'weathermap') . ' ]';
				} else {
					$file = $details['file'];
				}

				form_selectable_cell($details['title'], $i);

				form_selectable_cell($file, $i);

				form_end_row();

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
		raise_message('directory_missing',  __('There is no directory named %s.  You must create it, and set it to be readable by the webserver. If you want to upload configuration files from inside Cacti, then it must be <i>writable</i> by the webserver too.', $weathermap_confdir, 'weathermap'), MESSAGE_LEVEL_ERROR);

		header('Location: weathermap-cacti-plugin-mgmt.php');
		exit;
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

	chdir($weathermap_confdir);

	$path_parts = pathinfo($file);
	$file_dir   = realpath($path_parts['dirname']);

	if ($file_dir != $weathermap_confdir) {
		raise_message('path_mismatch', __esc('The path %s is not in the config directory.', $file, 'weathermap'), MESSAGE_LEVEL_ERROR);

		header('Location: weathermap-cacti-plugin-mgmt.php?action=addmap_picker');
		exit;
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
		} else {
			raise_message('path_missing', __esc('The path %s does not appear to exist in the config directory', $file, 'weathermap'), MESSAGE_LEVEL_ERROR);
			header('Location: weathermap-cacti-plugin-mgmt.php?action=addmap_picker');
			exit;
		}

		print '</pre>';
		print '</td></tr>';

		html_end_box();
	}
}

function add_config($file) {
	global $weathermap_confdir;

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
			(mapid, userid)
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

function map_get_next_name($basename, $pattern = 'copy') {
	global $weathermap_confdir;

	$file = basename($basename, '.conf');
	$i    = 0;

	if (!file_exists("$weathermap_confdir/$file" . '.conf')) {
		return $file . '.conf';
	}

	while ($i < 5) {
		if (!file_exists("$weathermap_confdir/$file" . '_' . $pattern . '.conf')) {
			$file .= '_' . $pattern;
			break;
		} else {
			$file .= '_' . $pattern;
		}


		$i++;
	}

	return $file . '.conf';
}


function map_duplicate($id, $titlecache, $configfile = null) {
	$map = db_fetch_row_prepared('SELECT * FROM weathermap_maps WHERE id = ?', array($id));

	$neworder = db_fetch_cell('SELECT MAX(sortorder) FROM weathermap_maps') + 1;

	if (cacti_sizeof($map)) {
		if ($configfile == null) {
			$configfile = map_get_next_name($map['configfile']);
		} else {
			$interim_config = basename($map['configfile'], '.conf');

			$newmap     = clean_up_name(str_replace('<map_config>', $interim_config, $configfile));
			$configfile = map_get_next_name($newmap);
		}

		$save = array();
		$save['id']           = 0;
		$save['sortorder']    = $neworder;
		$save['group_id']     = $map['group_id'];
		$save['active']       = $map['active'];
		$save['configfile']   = $configfile;
		$save['titlecache']   = str_replace('<map_title>', $map['titlecache'], $titlecache);
		$save['imagefile']    = '';
		$save['htmlfile']     = '';
		$save['filehash']     = '';
		$save['warncount']    = 0;
		$save['debug']        = 'off';
		$save['config']       = '';
		$save['thumb_height'] = $map['thumb_height'];
		$save['thumb_width']  = $map['thumb_width'];
		$save['schedule']     = $map['schedule'];
		$save['archiving']    = $map['archiving'];
		$save['duration']     = 0;
		$save['last_runtime'] = 0;

		$newid = sql_save($save, 'weathermap_maps');

		if ($newid) {
			db_execute_prepared("INSERT INTO weathermap_auth
				(userid, mapid)
				SELECT userid, '$newid' AS mapid
				FROM weathermap_auth
				WHERE mapid = ?",
				array($id));

			db_execute_prepared("INSERT INTO weathermap_settings
				(mapid, groupid, optname, optvalue)
				SELECT '$newid' AS mapid, groupid, optname, optvalue
				FROM weathermap_settings
				WHERE mapid = ?",
				array($id));

			raise_message('new_map_' . $newid, __('The new Map with the name %s was created using config file %s', $save['titlecache'], $save['configfile'], 'weathermap'), MESSAGE_LEVEL_INFO);

			$confdir = __DIR__ . '/configs';

			$oldfile = $confdir . '/' . $map['configfile'];
			$newfile = $confdir . '/' . $save['configfile'];

			if (file_exists($oldfile)) {
				if (copy($oldfile, $newfile)) {
					$contents = file_get_contents($newfile);

					$contents = str_replace("TITLE {$map['titlecache']}", "TITLE {$save['titlecache']}", $contents);

					file_put_contents($newfile, $contents);
				} else {
					raise_message('copy_fail_' . $newid, __('The new Map with the name %s was unable to create the config file %s', $save['titlecache'], $save['configfile'], 'weathermap'), MESSAGE_LEVEL_ERROR);

				}
			} else {
				raise_message('missing_fail_' . $newid, __('The new Map with the name %s was unable to locate the config file %s to copy', $save['titlecache'], $map['configfile'], 'weathermap'), MESSAGE_LEVEL_ERROR);
			}


			weathermap_run_maps(__DIR__, true, array($newid));
		}
	}
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

function perms_request_validation() {
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
		'type' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'has_perms' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
		)
    );

    validate_store_request_vars($filters, 'sess_wm_perfs');
    /* ================= input validation ================= */
}

function perms_filter($id) {
	global $item_rows;

	$title = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($id));

	html_start_box(__('Weathermap Permissions for Map [ %s ]', $title, 'weathermap'), '100%', '', '3', 'center', '');
	?>
	<tr class='even'>
		<td>
			<form id='form_perms' action='weathermap-cacti-plugin-mgmt.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'weathermap');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter', 'weathermap');?>'>
					</td>
					<td>
						<?php print __('Type', 'weathermap');?>
					</td>
					<td>
						<select id='type' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('type') == '-1' ? ' selected>':'>') . __('All', 'weathermap');?></option>
							<option value='0'<?php print(get_request_var('type') == '0' ? ' selected>':'>') . __('Users', 'weathermap');?></option>
							<option value='1'<?php print(get_request_var('type') == '1' ? ' selected>':'>') . __('User Groups', 'weathermap');?></option>
						</select>
					</td>
					<td>
						<?php print __('Rows', 'weathermap');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'weathermap');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . $value . "</option>\n";
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_perms' <?php print (get_request_var('has_perms') == 'true' ? 'checked':'');?>>
							<label for='has_perms'><?php print __('Has Permissions', 'weathermap');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: use filter settings', 'Go', 'weathermap');?>' id='refresh'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: reset filter settings', 'Clear', 'weathermap');?>' id='clear'>
							<input type='hidden' value='<?php print $id;?>' id='mapid'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'weathermap-cacti-plugin-mgmt.php?';
				strURL += '&action=perms_edit';
				strURL += '&header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&type='+$('#type').val();
				strURL += '&has_perms='+$('#has_perms').is(':checked');
				strURL += '&id='+$('#mapid').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'weathermap-cacti-plugin-mgmt.php?action=perms_edit&header=false&reset=1&id='+$('#mapid').val();
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#has_perms').change(function() {
					applyFilter();
				});

				$('#form_perms').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}

function perms_get_records(&$total_rows, $rows = 30, $apply_limits = true) {
	$sql_where1 = '';
	$sql_where2 = '';

	$guest_user    = read_config_option('guest_user');
	$template_user = read_config_option('user_template');

	if (get_request_var('has_perms') == 'true') {
		$join = 'INNER';
	} else {
		$join = 'LEFT';
	}

	if (get_request_var('filter') != '') {
		if (get_request_var('type') == -1 || get_request_var('type') == 0) {
			$sql_params[] = get_request_var('id');

			$sql_where1   = 'AND (username LIKE ? OR full_name LIKE ?)';
			$sql_params[] = '%' . get_nfilter_request_var('filter') . '%';
			$sql_params[] = '%' . get_nfilter_request_var('filter') . '%';

			if ($guest_user > 0) {
				$sql_where1  .= ' AND id != ?';
				$sql_params[] = $guest_user;
			}

			if ($template_user > 0) {
				$sql_where1  .= ' AND id != ?';
				$sql_params[] = $template_user;
			}
		}

		if (get_request_var('type') == -1) {
			$sql_params[] = get_request_var('id');
		}

		$sql_params[] = get_request_var('id');

		if (get_request_var('type') == -1 || get_request_var('type') == 1) {
			$sql_where2   = 'AND (name LIKE ? OR description LIKE ?)';

			$sql_params[] = get_request_var('id');
			$sql_params[] = '%' . get_nfilter_request_var('filter') . '%';
			$sql_params[] = '%' . get_nfilter_request_var('filter') . '%';
		}
	} else {
		if (get_request_var('type') == -1 || get_request_var('type') == 0) {
			$sql_params[] = get_request_var('id');

			if ($guest_user > 0) {
				$sql_where1  .= ' AND id != ?';
				$sql_params[] = $guest_user;
			}

			if ($template_user > 0) {
				$sql_where1  .= ' AND id != ?';
				$sql_params[] = $template_user;
			}

		}

		if (get_request_var('type') == -1) {
			$sql_params[] = get_request_var('id');
		}

		$sql_params[] = get_request_var('id');

		if (get_request_var('type') == -1 || get_request_var('type') == 1) {
			$sql_params[] = get_request_var('id');
		}
	}

	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	}

	if (get_request_var('type') == -1) {
		$records = db_fetch_assoc_prepared("SELECT *
			FROM (
				SELECT '0' AS id, 'Everyone' AS name, 'All Users in System' AS description, 'special' AS type, '-1' AS allowed, 'N/A' AS realm
				UNION ALL
				SELECT id, username AS name, full_name AS description, 'user' AS type, wa.mapid AS allowed, realm
				FROM user_auth AS ua
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
				ON ua.id = wa.userid
				AND ua.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND ua.enabled = 'on'))
				$sql_where1
				UNION ALL
				SELECT id, name, description, 'group' AS type, wa.mapid AS allowed, 'N/A' AS realm
				FROM user_auth_group AS uag
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
				ON uag.id = -wa.userid
				AND uag.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND uag.enabled = 'on'))
				$sql_where2
			) AS rs
			$sql_limit",
			$sql_params);

		$total_rows = db_fetch_cell_prepared("SELECT SUM(`rows`) + 1
			FROM (
				SELECT COUNT(*) AS `rows`
				FROM user_auth AS ua
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
				ON ua.id = wa.userid
				AND ua.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND ua.enabled = 'on'))
				$sql_where1
				UNION ALL
				SELECT COUNT(*) AS `rows`
				FROM user_auth_group AS uag
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?)  AS wa
				ON uag.id = -wa.userid
				AND uag.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND uag.enabled = 'on'))
				$sql_where2
			) AS rs",
			$sql_params);
	} elseif (get_request_var('type') == 0) {
		$records = db_fetch_assoc_prepared("SELECT *
			FROM (
				SELECT '0' AS id, 'Everyone' AS name, 'All Users in System' AS description, 'special' AS type, '-1' AS allowed, 'N/A' AS realm
				UNION ALL
				SELECT id, username AS name, full_name AS description, 'user' AS type, wa.mapid AS allowed, realm
				FROM user_auth AS ua
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
				ON ua.id = wa.userid
				AND ua.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND ua.enabled = 'on'))
				$sql_where1
			) AS rs
			$sql_limit",
			$sql_params);

		$total_rows = db_fetch_cell_prepared("SELECT COUNT(*) + 1
			FROM user_auth AS ua
			$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
			ON ua.id = wa.userid
			AND ua.enabled = 'on'
			WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND ua.enabled = 'on'))
			$sql_where1",
			$sql_params);
	} else {
		$records = db_fetch_assoc_prepared("SELECT *
			FROM (
				SELECT '0' AS id, 'Everyone' AS name, 'All Users in System' AS description, 'special' AS type, '-1' AS allowed, 'N/A' AS realm
				UNION ALL
				SELECT id, name, description, 'group' AS type, wa.mapid AS allowed, 'N/A' AS realm
				FROM user_auth_group AS uag
				$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
				ON uag.id = -wa.userid
				AND uag.enabled = 'on'
				WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND uag.enabled = 'on'))
				$sql_where2
			) AS rs
			$sql_limit",
			$sql_params);

		$total_rows = db_fetch_cell_prepared("SELECT COUNT(*) + 1
			FROM user_auth_group AS uag
			$join JOIN (SELECT * FROM weathermap_auth WHERE mapid = ?) AS wa
			ON uag.id = -wa.userid
			AND uag.enabled = 'on'
			WHERE (wa.mapid = ? OR (wa.mapid IS NULL AND uag.enabled = 'on'))
			$sql_where2",
			$sql_params);
	}

	return $records;
}

function perms_list($id) {
	global $perm_actions;

	perms_request_validation();

	perms_filter($id);

	$total_rows = 0;
	$perm_records = array();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$perm_records = perms_get_records($total_rows, $rows);

	$nav = html_nav_bar('weathermap-cacti-plugin-mgmt.php?action=perms_edit&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Users or Groups', 'weathermap'), 'page', 'main');

	form_start('weathermap-cacti-plugin-mgmt.php?action=perms_edit', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		array(
			'display' => __('Account Type', 'weathermap'),
		),
		array(
			'display' => __('User/Group', 'weathermap'),
		),
		array(
			'display' => __('Login Realm', 'weathermap'),
		),
		array(
			'display' => __('UID/GID', 'weathermap'),
			'align'    => 'center'
		),
		array(
			'display' => __('Full Name/Description', 'weathermap'),
		),
		array(
			'display' => __('Allowed', 'weathermap'),
		)
	);

	html_header_checkbox($display_text, false);

	if (cacti_sizeof($perm_records)) {
		foreach ($perm_records as $perm) {
			$rid = $perm['id'] . ':' . $perm['type'];

			if ($perm['allowed'] < 0) {
				$perm['allowed'] = db_fetch_cell_prepared('SELECT mapid
					FROM weathermap_auth
					WHERE userid = 0
					AND mapid = ?',
					array($id));
			}

			form_alternate_row('line' . $rid);

			if ($perm['type'] == 'user') {
				form_selectable_cell(__('User', 'weathermap'), $rid);
			} elseif ($perm['type'] == 'group') {
				form_selectable_cell(__('User Group', 'weathermap'), $rid);
			} else {
				form_selectable_cell(__('All Users', 'weathermap'), $rid);
			}

			form_selectable_cell(filter_value($perm['name'], get_request_var('filter')), $rid);

			if ($perm['realm'] == 'N/A') {
				$realm = __('N/A', 'weathermap');
			} elseif ($perm['realm'] == 0) {
				$realm = __('Local', 'weathermap');
			} elseif ($perm['realm'] == 2) {
				$realm = __('Web Basic', 'weathermap');
			} elseif ($perm['realm'] >= 3) {
				$realm = __('LDAP/AD', 'weathermap');
			}

			form_selectable_cell($realm, $rid);

			form_selectable_cell($perm['id'], $rid, '', 'center');

			form_selectable_cell(filter_value($perm['description'], get_request_var('filter')), $rid);

			if ($perm['allowed'] == '') {
				form_selectable_cell(__('Not Permitted', 'weathermap'), $rid, '', 'deviceDown');
			} else {
				form_selectable_cell(__('Permitted', 'weathermap'), $rid, '', 'deviceUp');
			}

			form_checkbox_cell($perm['name'], $rid);

			form_end_row();
		}
	} else {
		print '<tr><td><em><b>' . __('No Users have Access to this Map', 'weathermap') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($perm_records)) {
		print $nav;
	}

	draw_actions_dropdown($perm_actions);

	form_hidden_box('associate_perms', '1', '');
	form_hidden_box('mapid', $id, '');

	form_end();

	weathermap_back_to();
}

function weathermap_map_settings($id) {
	global $config;

	if ($id == 0) {
		$title = __('Global Settings [ All Maps ]', 'weathermap');

		$nonemsg = __('There are no Settings for All Maps yet. You can add some by pressing the plus sign \'+\' in the top-right, or choose a single Map from the management screen to add Settings for that Map.', 'weathermap');

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

		$title = __('Edit Group Settings [ Group: %s ]', $groupname, 'weathermap');

		$nonemsg = __('There are no per Group Settings for this Group yet. You can add some by pressing the plus sign \'+\' in the top-right.', 'weathermap');

		$type = 'group';

		$settingrows = db_fetch_assoc_prepared('SELECT *
			FROM weathermap_settings
			WHERE groupid = ?',
			array($group_id));
	} else {
		$map = db_fetch_row_prepared('SELECT *
			FROM weathermap_maps
			WHERE id = ?',
			array($id));

		$groupname = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array($map['group_id']));

		$title = __('Edit Map Settings [ Weathermap: %s ]', $map['titlecache'], 'weathermap');

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

			print __('All Maps in this Group are also affected by the following Global Settings (Group overrides Global, Map overrides Group, but BOTH override SET commands within the Map Config File)', 'weathermap');

		}

		if ($type == 'map') {
			$do_grp_settings = true;
			$do_map_settings = true;

			print __('This Map is also affected by the following Global and Group Settings (Group overrides Global, Map overrides Group, but BOTH override SET commands within the Map Config File)', 'weathermap');
		}

		print '</td></tr>';

		html_end_box();

		if ($do_grp_settings) {
			weathermap_readonly_settings(0, __('Global Settings [ All Maps ]', 'weathermap'));
		}

		if ($do_map_settings) {
			weathermap_readonly_settings($map['group_id'], __esc('Group Settings [ Group: %s ]', ($groupname != '' ? $groupname:__('No Group', 'weathermap')), 'weathermap'));
		}
	}

	html_start_box($title, '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=map_settings_form&mapid=' . intval($id));

	if (cacti_sizeof($settingrows)) {
		html_header(array(__('Action', 'weathermap'), __('Name', 'weathermap'), __('Value', 'weathermap')), 2);

		$n = 0;

		foreach ($settingrows as $setting) {
			form_alternate_row();

			$tip    = __esc('Edit this definition', 'weathermap');
			$url    = html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings_form&mapid=' . $id . '&id=' . $setting['id']);
			$value  = '<i class="fas fa-wrench"></i>';
			$action = "<a class='pic linkEditMain' href='$url' title='$tip'>$value</a>";

			form_selectable_cell($action, $n);

			form_selectable_cell($setting['optname'], $n);
			form_selectable_cell($setting['optvalue'], $n);

			$tip    = __esc('Remove this definition from this Map', 'weathermap');
			$url    = html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings_delete&mapid=' . $id . '&id=' . $setting['id']);
			$value  = '<i class="delete deleteMarker fa fa-times"></i>';
			$action = "<a class='pic linkEditMain' href='$url' title='$tip'>$value</a>";

			form_selectable_cell($action, $n, '', 'right');

			form_end_row();

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
		print '<a class="pic linkEditMain" href="weathermap-cacti-plugin-mgmt.php?action=groupadmin">' . __('Back to Group Admin', 'weathermap') . '</a>';
	}

	if ($type == 'global' || $type == 'map') {
		print '<a class="pic linkEditMain" href="weathermap-cacti-plugin-mgmt.php?action=">' . __('Back to Map Admin', 'weathermap') . '</a>';
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
	global $config;

	if ($mapid > 0) {
		$name = db_fetch_cell_prepared('SELECT titlecache
			FROM weathermap_maps
			WHERE id = ?',
			array($mapid));
	} else {
		$name = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array(-$mapid));
	}

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
		$title = __('Global Setting for all Maps', 'weathermap');
	} elseif ($mapid < 0) {
		$title = __('Group Settings [ Group: %s ]', $name, 'weathermap');
	} else {
		$title = __('Map Setting [ Weathermap: %s ]', $name, 'weathermap');
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
	if ($mapid > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM weathermap_settings
			WHERE mapid = ?
			AND id = ?',
			array($mapid, $settingid));
	} else {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM weathermap_settings
			WHERE groupid = ?
			AND id = ?',
			array(-$mapid, $settingid));
	}

	if ($exists) {
		db_execute_prepared('UPDATE weathermap_settings
			SET optname = ?, optvalue = ?
			WHERE id = ?',
			array($name, $value, $id));
	} else {
		if ($mapid > 0) {
			db_execute_prepared('INSERT INTO weathermap_settings
				(mapid, optname, optvalue)
				VALUES (?, ?, ?)',
				array($mapid, $name, $value));
		} else {
			db_execute_prepared('INSERT INTO weathermap_settings
				(groupid, optname, optvalue)
				VALUES (?, ?, ?)',
				array(-$mapid, $name, $value));
		}
	}
}

function weathermap_setting_delete($mapid, $settingid) {
	if ($mapid > 0) {
		db_execute_prepared('DELETE FROM weathermap_settings
			WHERE id = ?
			AND mapid = ?',
			array($settingid, $mapid));
	} else {
		db_execute_prepared('DELETE FROM weathermap_settings
			WHERE id = ?
			AND groupid = ?',
			array($settingid, -$mapid));
	}
}

function weathermap_chgroup($id) {
	$title = db_fetch_cell_prepared('SELECT titlecache
		FROM weathermap_maps
		WHERE id = ?',
		array($id));

	$curgroup = db_fetch_cell_prepared('SELECT group_id
		FROM weathermap_maps
		WHERE id = ?',
		array($id));

	$n = 0;

	form_start('weathermap-cacti-plugin-mgmt.php');

	print "<input type=hidden name='map_id' value='" . $id . "'>";
	print "<input type=hidden name='action' value='chgroup_update'>";

	html_start_box(__('Edit Map Group for Weathermap [ %s ]', $title, 'weathermap'), '100%', '', '3', 'center', '');

	# html_header(array("Group Name", ""));
	form_alternate_row();

	print '<td><b>' . __('Choose an existing Group', 'weathermap') . '</b>&nbsp;&nbsp;<select name="new_group">';

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

	print '<tr><td><p>' . __('Or Create a New Group using the %s Group Management interface %s', '<a class="pic linkEditMain" href="weathermap-cacti-plugin-mgmt.php?action=groupadmin">' , '</a>', 'weathermap') . '</p></td></tr>';

	html_end_box();

	weathermap_back_to();

	form_end();
}

function weathermap_group_form($id = 0) {
	global $config;

	$grouptext = '';
	// if id==0, it's an Add, otherwise it's an editor.
	if ($id == 0) {
		$header = __('Adding a Group', 'weathermap');
	} else {
		$grouptext = db_fetch_cell_prepared('SELECT name
			FROM weathermap_groups
			WHERE id = ?',
			array($id));

		$header = __esc('Editing Group: %s', $grouptext, 'weathermap');
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
	global $config;

	html_start_box(__('Edit Map Groups', 'weathermap'), '100%', '', '3', 'center', 'weathermap-cacti-plugin-mgmt.php?action=group_form&id=0');

	html_header(array(__('Actions', 'weathermap'), __('Group Name', 'weathermap'), __('Settings', 'weathermap'), __('Sort Order', 'weathermap')), 2);

	$groups = db_fetch_assoc('SELECT * FROM weathermap_groups ORDER BY sortorder');

	$n = 0;

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			form_alternate_row();

			$tip    = __esc('Rename this Group', 'weathermap');
			$url    = html_escape('weathermap-cacti-plugin-mgmt.php?action=group_form&id=' . $group['id']);
			$value  = '<i class="fas fa-wrench"></i>';
			$action = "<a class='pic' href='#' data-id='{$group['id']}' data-href='$url' title='$tip' data-name='" . html_escape($group['name']) . "'>$value</a>";

			form_selectable_cell($action, $n);

			form_selectable_cell(html_escape($group['name']), $group['id']);

			$tip    = __('Edit Group Settings', 'weathermap');
			$url    = html_escape('weathermap-cacti-plugin-mgmt.php?action=map_settings&id=-' . $group['id']);

			$setting_count = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM weathermap_settings
				WHERE mapid = 0
				AND groupid = ?',
				array($group['id']));

			if ($setting_count > 0) {
				$value = __('%d Specials', $setting_count, 'weathermap');
			} else {
				$value = __('Standard', 'weathermap');
			}

			$action = "<a class='pic linkEditMain' href='$url' title='$tip'>$value</a>";

			form_selectable_cell($action, $n);

			$tip     = __esc('Move Group Up', 'weathermap');
			$url     = html_escape('weathermap-cacti-plugin-mgmt.php?action=move_group_up&id=' . $group['id']);
			$value   = '<i class="fa fa-caret-up moveArrow"></i>';

			$action  = "<a class='pic' href='$url' title='$tip'>$value</a>";

			$tip     = __esc('Move Group Down', 'weathermap');
			$url     = html_escape('weathermap-cacti-plugin-mgmt.php?action=move_group_down&id=' . $group['id']);
			$value   = '<i class="fa fa-caret-down moveArrow"></i>';

			$action .= "<a class='pic' href='$url' title='$tip'>$value</a>";

			form_selectable_cell($action, $n);

			$tip    = __esc('Remove this definition from this Map', 'weathermap');
			$url    = html_escape('weathermap-cacti-plugin-mgmt.php?action=groupadmin_delete&id=' . $group['id']);
			$value  = '';

			$action = "<a class='pic delete deleteMarker fa fa-times' href='$url' title='$tip'>$value</a>";

			form_selectable_cell($action, $n, '', 'right');

			form_end_row();

			$n++;
		}
	} else {
		print '<tr>';
		print '<td colspan="2">' . __('No Groups are defined', 'weathermap') . '</td>';
		print '</tr>';
	}

	html_end_box();

	weathermap_back_to();

	print "<div id='rename_dialog' title='" . __esc('Rename Weathermap Group', 'weathermap') . "' style='display:none;'>
		<p class='validateTips'>" . __('Enter a new name for the Weathermap Group.', 'weathermap') . "</p>
		<form id='renameform'>
			<fieldset>
				<label for='name'>" . __('New Group Name', 'weathermap') . "</label><br><br>
				<input type='text' name='rname' id='rname' value='' size='30' class='text ui-widget-content ui-corner-all'>
				<br><br>
				<div class='right'>
      				<input type='hidden' name='rid' id='rid' value=''>
					<input type='button' id='cancel' value='" . __esc('Cancel', 'weathermap') . "'>
					<input type='submit' id='rename' value='" . __esc('Rename', 'weathermap') . "'>
				</div>
			</fieldset>
		</form>
	</div>";

	?>
	<script type='text/javascript'>
	$(function() {
		$('.fa-wrench').click(function(event) {
			event.preventDefault();
			event.stopPropagation();

			var id    = $(this).closest('a').attr('data-id');
			var title = $(this).closest('a').attr('data-name');

			$('#renameform').submit(function(event) {
				event.preventDefault();
				event.stopPropagation();

				if ($('#rname').val() != '') {
					$('#rename_dialog').dialog('close');

					$.get('weathermap-cacti-plugin-mgmt.php?action=group_update&id='+$('#rid').val()+'&gname='+$('#rname').val(), function() {
						loadPageNoHeader('weathermap-cacti-plugin-mgmt.php?action=groupadmin&header=false');
					});
				} else {
					loadPageNoHeader('weathermap-cacti-plugin-mgmt.php?action=groupadmin&header=false');
				}
			});

			$('#rname').val(title);
			$('#rid').val(id);

			$('#rename_dialog').dialog({
				autoOpen: true,
				width: 350,
				height: 'auto',
				resizeable: false,
				open: function(event, ui) {
					$('#rename').focus();
				}
			});
		});
	});
	</script>
	<?php
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

