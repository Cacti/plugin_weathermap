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

include_once('../../include/auth.php');

global $config;
include_once($config['base_path'] . '/plugins/weathermap/setup.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/editor.inc.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/editor.actions.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WeatherMap.class.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/geometry.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WMPoint.class.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WMVector.class.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WMLine.class.php');

// If we're embedded in the Cacti UI (included from weathermap-cacti-plugin-editor.php), then authentication has happened. Enable the editor.
$editor_name  = 'weathermap-cacti-plugin-editor.php';
$cacti_base   = $config['base_path'];
$cacti_url    = $config['url_path'];

// sensible defaults
$mapdir      = 'configs';

// these are all set via the Editor Settings dialog, in the editor, now.
$use_overlay          = false; // set to true to enable experimental overlay showing VIAs
$use_relative_overlay = false; // set to true to enable experimental overlay showing relative-positioning
$grid_snap_value      = 0; // set non-zero to snap to a grid of that spacing

// Load some saves settings from the editor cookie
if (isset($_COOKIE['wmeditor'])) {
	$parts = explode(':', $_COOKIE['wmeditor']);

	if (intval($parts[0]) == 1) {
		$use_overlay = true;
	}

	if ((isset($parts[1])) && (intval($parts[1]) == 1)) {
		$use_relative_overlay = true;
	}

	if ((isset($parts[2])) && (intval($parts[2]) != 0)) {
		$grid_snap_value = intval($parts[2]);
	}
}

$action   = '';
$mapname  = '';
$selected = '';

set_default_action('');

$editor_return_context = get_nfilter_request_var('return_to') === 'manage' ? 'manage' : 'map';

if (isset_request_var('action')) {
	$action = wm_editor_sanitize_action(get_nfilter_request_var('action'), [
		'graphs', 'datasources', 'newmap', 'newmapcopy', 'font_samples', 'draw',
		'show_config', 'fetch_config', 'set_link_config', 'set_node_config',
		'set_node_properties', 'set_link_properties', 'set_map_properties',
		'set_map_style', 'add_link2', 'place_legend', 'place_stamp', 'via_link',
		'move_node', 'link_tidy', 'retidy', 'retidy_all', 'untidy',
		'delete_link', 'add_node', 'editor_settings', 'delete_node',
		'clone_node', 'load_area_data', 'load_map_javascript', 'nothing'
	]);
}

if (isset_request_var('mapname')) {
	$mapname = get_nfilter_request_var('mapname');
	$mapname = wm_editor_sanitize_conffile($mapname);
}

if (isset_request_var('selected')) {
	$selected = wm_editor_sanitize_selected(get_nfilter_request_var('selected'));
}

$weathermap_debugging = false;

if ($mapname == '') {
	// this is the file-picker/welcome page
	show_editor_startpage();
	exit();
}

// everything else in this file is inside this else
$mapfile  = $mapdir . '/' . $mapname;

// We need to know the image URL for rendering
$imageurl = getImageUrl($mapname, $selected);

wm_debug('==========================================================================================================');
wm_debug("Starting Edit Run: action is $action on $mapname");
wm_debug('==========================================================================================================');

switch($action) {
	case 'graphs':
		display_graphs();
		exit;
	case 'datasources':
		display_datasources();
		exit;
	case 'newmap':
		newMap($mapfile);

		break;
	case 'newmapcopy':
		newMapCopy($mapfile);

		break;
	case 'font_samples':
		displayFontSamples($mapfile);

		exit();
	case 'draw':
		drawMap($mapfile, $selected, $use_overlay, $use_relative_overlay);

		exit();
	case 'show_config':
		showConfig($mapfile);

		exit();
	case 'fetch_config':
		fetchConfig($mapfile);

		exit();
	case 'set_link_config':
		setLinkConfig($mapfile);

		break;
	case 'set_node_config':
		setNodeConfig($mapfile);

		break;
	case 'set_node_properties':
		setNodeProperties($mapfile);
		exit;
	case 'set_link_properties':
		setLinkProperties($mapfile);
		exit;
	case 'set_map_properties':
		setMapProperties($mapfile);
		exit;
	case 'set_map_style':
		setMapStyle($mapfile);
		exit;
	case 'add_link2':
		addLink($mapfile);
		exit;
	case 'place_legend':
		placeLegend($mapfile, $grid_snap_value);
		exit;
	case 'place_stamp':
		placeStamp($mapfile, $grid_snap_value);
		exit;
	case 'via_link':
		viaLink($mapfile);
		exit;
	case 'move_node':
		moveNode($mapfile, $grid_snap_value);
		exit;
	case 'link_tidy':
		linkTidy($mapfile);
		exit;
	case 'retidy':
		reTidy($mapfile);
		exit;
	case 'retidy_all':
		reTidyAll($mapfile);
		exit;
	case 'untidy':
		unTidy($mapfile);
		exit;
	case 'delete_link':
		deleteLink($mapfile);
		exit;
	case 'add_node':
		addNode($mapfile, $grid_snap_value);
		exit;
	case 'editor_settings':
		editorSettings($mapfile);
		exit;
	case 'delete_node':
		deleteNode($mapfile);
		exit;
	case 'clone_node':
		cloneNode($mapfile);
		exit;
	case 'load_area_data':
		getMapAreaData($mapfile);
		exit;
	case 'load_map_javascript':
		getMapJavaScript($mapfile);
		exit;
	case 'nothing':
		break;
	default:
		cacti_log('WARNING: Invalid action ' . $action, false, 'WEATHERMAP');

		break;
}

$map = new WeatherMap;
$map->ReadConfig($mapfile);

// by here, there should be a valid $map - either a blank one, the existing one, or the existing one with requested changes
wm_debug('Finished modifying');

// Fix the locations of the background and node images if they are not in the locations that they are
// expected.  This function should re redundant as the images are relocated during upgrade, but
// is left here just in case.
fixMapBackgroundAndImages($map);

// get the list from the images/ folder too
$image_list   = get_imagelist('objects');
$backgd_list  = get_imagelist('backgrounds');

// append any images used in the map that aren't in the images folder
foreach ($map->used_images as $im) {
	if (!in_array($im, $image_list, true)) {
		$image_list[] = $im;
	}
}

sort($image_list);

cacti_cookie_set('wmeditor', ($use_overlay ? '1' : '0') . ':' . ($use_relative_overlay ? '1' : '0') . ':' . intval($grid_snap_value));

// get the users selected theme and the weathermap version
$selectedTheme      = get_selected_theme();
$weathermap_version = plugin_weathermap_numeric_version();

?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' lang='en' xml:lang='en'>
<head>
	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/favicon.ico'?>' rel='shortcut icon'>
	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/cacti_logo.gif'?>' rel='icon' sizes='96x96'>
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/jquery-ui.css'; ?>'>
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/main.css'; ?>'>
	<?php print get_md5_include_css('plugins/weathermap/css/editor.css'); ?>
	<?php if (cacti_version_compare(CACTI_VERSION, '1.2.32', '<')) { ?>
	<?php print get_md5_include_css('plugins/weathermap/css/editor-ui-dialog-legacy.css'); ?>
	<?php } ?>
	<?php getEditorJs(); ?>
	<?php print get_md5_include_js('include/js/jquery.js'); ?>
	<?php print get_md5_include_js('include/js/jquery-ui.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.tablesorter.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.colorpicker.js'); ?>
	<?php print get_md5_include_js('include/js/js.storage.js'); ?>
	<?php print get_md5_include_js('plugins/weathermap/js/editor.js'); ?>
	<?php print get_md5_include_js('plugins/weathermap/js/jquery.ddslick.js'); ?>
	<?php print get_md5_include_js('plugins/weathermap/js/jquery.ui-contextmenu.js'); ?>

	<title><?php print __('PHP Weathermap Editor %s', $weathermap_version, 'flowview'); ?></title>
</head>

<?php
$editor_return_hash  = db_fetch_cell_prepared('SELECT filehash FROM weathermap_maps WHERE configfile = ? LIMIT 1', [$mapname]);
$editor_return_url   = 'weathermap-cacti-plugin.php';
$editor_return_label = __('Return to Map', 'weathermap');

if (!empty($editor_return_hash)) {
	$editor_return_url .= '?action=viewmap&id=' . rawurlencode($editor_return_hash);
}

if ($editor_return_context === 'manage') {
	$editor_return_url   = 'weathermap-cacti-plugin-mgmt.php';
	$editor_return_label = __('Return to Manage', 'weathermap');
}
?>
<body id='mainView' class='mainView' data-return-label='<?php print plugin_weathermap_escape_attr($editor_return_label); ?>' data-return-map='<?php print plugin_weathermap_escape_attr($editor_return_url); ?>'>
	<div id='toolbar'>
		<ul>
			<li class='tb_active' id='tb_newfile'><?php print __('Change<br>File', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_addnode'><?php print __('Add<br>Node', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_addlink'><?php print __('Add<br>Link', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_poslegend'><?php print __('Position<br>Legend', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_postime'><?php print __('Position<br>Timestamp', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_mapprops'><?php print __('Map<br>Properties', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_mapstyle'><?php print __('Map<br>Style', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_colours'><?php print __('Manage<br>Colors', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_manageimages'><?php print __('Manage<br>Images', 'weathermap'); ?></li>
			<li class='tb_active' id='tb_prefs'><?php print __('Editor<br>Settings', 'weathermap'); ?></li>
			<li class='tb_coords' id='tb_coords'><?php print __('Position<br>---, ---', 'weathermap'); ?></li>
			<li class='tb_help'>
				<span id='tb_help'><?php print __('Select a menu item or either right-click or click on a Node or Link to edit it\'s properties', 'weathermap'); ?></span>
			</li>
		</ul>
	</div>
	<form id='frmMain' action='<?php print $editor_name ?>' method='post'>
		<div class='mainArea'>
			<input id='xycapture' name='xycapture' data-width='<?php print html_escape($map->width); ?>' data-height='<?php print html_escape($map->height); ?>' style='display:none' type='image' src='<?php print html_escape($imageurl); ?>' />
			<img id='existingdata' name='existingdata' data-width='<?php print html_escape($map->width); ?>' data-height='<?php print html_escape($map->height); ?>' src='<?php print html_escape($imageurl); ?>' usemap='#weathermap_imap' />
			<input id='x' name='x' type='hidden' />
			<input id='y' name='y' type='hidden' />
			<div class='debug' style='display:none'><p><strong><?php print __('Debug', 'weathermap'); ?></strong>
				<a href='?action=retidy_all&mapname=<?php print plugin_weathermap_escape_attr(rawurlencode($mapname) . '&return_to=' . $editor_return_context); ?>'><?php print __('Re-tidy ALL', 'weathermap'); ?></a>
				<a href='?action=retidy&mapname=<?php print plugin_weathermap_escape_attr(rawurlencode($mapname) . '&return_to=' . $editor_return_context); ?>'><?php print __('Re-tidy', 'weathermap'); ?></a>
				<a href='?action=untidy&mapname=<?php print plugin_weathermap_escape_attr(rawurlencode($mapname) . '&return_to=' . $editor_return_context); ?>'><?php print __('Un-tidy', 'weathermap'); ?></a>
				<a href='?action=nothing&mapname=<?php print plugin_weathermap_escape_attr(rawurlencode($mapname) . '&return_to=' . $editor_return_context); ?>'><?php print __('Do Nothing', 'weathermap'); ?></a>
				<span>
					<label for='mapname'><?php print __('mapfile', 'weathermap'); ?></label>
					<input name='return_to' type='hidden' value='<?php print plugin_weathermap_escape_attr($editor_return_context); ?>'>
					<input id='mapname' name='mapname' type='text' class='ui-state-default ui-corner-all' value='<?php print plugin_weathermap_escape_attr($mapname); ?>'>
				</span>
				<span>
					<label for='action'><?php print __('action', 'weathermap'); ?></label>
					<input id='action' name='action' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='param'><?php print __('param', 'weathermap'); ?></label>
					<input id='param' name='param' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='param2'><?php print __('param2', 'weathermap'); ?></label>
					<input id='param2' name='param2' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='debug'><?php print __('debug', 'weathermap'); ?></label>
					<input id='debug' name='debug' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<a target='configwindow' href='?action=show_config&mapname=<?php print plugin_weathermap_escape_attr(rawurlencode($mapname) . '&return_to=' . $editor_return_context); ?>'><?php print __('See config', 'weathermap'); ?></a>
			</div>
		</div>

		<!-- Data for overlay and selection -->
		<div class='scriptData'>
			<script type='text/javascript' <?php print plugin_weathermap_csp_nonce(); ?>>
			<?php getMapJavaScript($mapfile); ?>
			<?php print 'var infoUrlStyle=' . intval(read_config_option('weathermap_infourl_style')) . ';'; ?>
			</script>
		</div>
		<div class='mapData'>
			<?php getMapAreaData($mapfile); ?>
		</div>
		<!-- End Data for overlay and selection -->

		<!-- Node Properties -->
		<div id='dlgNodeProperties' class='dlgProperties' title='<?php print __('Node Properties', 'weathermap'); ?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>
								<input id='node_name' name='node_name' type='hidden' size='6'/>
							</td>
						</tr>
						<tr>
							<td><?php print __('Position', 'weathermap'); ?></td>
							<td><input id='node_x' name='node_x' type='text' class='ui-state-default ui-corner-all' size='4' />,<input id='node_y' name='node_y' type='text' class='ui-state-default ui-corner-all' size='4' /></td>
						</tr>
						<tr>
							<td><?php print __('Internal Name', 'weathermap'); ?></td>
							<td><input id='node_new_name' name='node_new_name' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td><?php print __('Label', 'weathermap'); ?></td>
							<td><input id='node_label' name='node_label' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td><?php print __('Icon Filename', 'weathermap'); ?></td>
							<td>
								<select id='node_iconfilename' name='node_iconfilename'>
									<?php
									if (count($image_list) == 0) {
										print '<option data-value="--NONE--">Label Only</option>';
										print '<option data-value="--AICON--">Special Icon (AICON)></option>';
									} else {
										print '<option data-description="Label Only Icon" data-imagesrc="" value="--NONE--">Label Only</option>';
										print '<option data-description="Special Purpose Icon" data-imagesrc="" value="--AICON--">Special Icon (AICON)</option>';

										foreach ($image_list as $im) {
											$display = ucfirst(str_replace(['.png', '.gif', '.jpg'], '', basename($im)));

											print '<option ';
											print 'data-description="' . $display . '" ';
											print 'data-imagesrc="' . $im . '" ';
											print 'value="' . html_escape($im) . '">' . $display . '</option>';
										}
									}
?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Info URL(s)', 'weathermap'); ?></td>
							<td>
								<textarea id='node_infourl' name='node_infourl' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Hover Graph URL(s)', 'weathermap'); ?></td>
							<td>
								<textarea id='node_hover' name='node_hover' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Template', 'weathermap'); ?></td>
							<td>
								<select id='node_template' name='node_template'>
									<?php
									print '<option value="-1"' . (get_request_var('node_template') == -1 ? 'selected' : '') . '>' . __('All', 'weathermap') . '</option>';
$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN graph_local AS gl
										ON gt.id = gl.graph_template_id
										ORDER BY gt.name');

foreach ($graph_templates as $gt) {
	print '<option ' . (get_request_var('node_template') == $gt['id'] ? 'selected' : '');
	print ' value="' . $gt['id'] . '">' . html_escape($gt['name']) . '</option>';
}
?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Selector', 'weathermap'); ?></td>
							<td>
								<input id='node_picker' name='node_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a class='ui-button ui-corner-all ui-widget' id='node_move'><?php print __('Move', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_delete'><?php print __('Delete', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_clone'><?php print __('Clone', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_edit'><?php print __('Edit', 'weathermap'); ?></a>
						<a id='tb_node_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap'); ?></a>
						<a id='tb_node_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap'); ?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('You can modify the Weathermap Node from here.  The Position columns are the X,Y position on the Map.  The Internal Name is the unique ID given to the Node.  The Label is the external name of the Node that you provide to users.  The Icon Filename is the Graphic that you want to represent the Node Object.  The INFO URL is a link that you can provide when clicking on the active Map Node.  The Hover Graph URL\'s are Cacti or other Graphs URL\'s that can will appear when hovering over the Node.  The Graph Selector is a helper for selecting Cacti Graphs for the Graph URL\'s.   There are several other Node properties possible.  However, today we are only supporting those included above.', 'flowview'); ?>
				</div>
			</div>
		</div>
		<!-- Node Properties -->

		<!-- Link Properties -->
		<div id='dlgLinkProperties' class='dlgProperties' title='<?php print __('Link Properties', 'weathermap'); ?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<div class='dlgComment'>
						Link from '<span id='link_nodename1'>%NODE1%</span>' to '<span id='link_nodename2'>%NODE2%</span>'
					</div>
					<table class='cactiTable'>
						<tr>
							<td>
								<input id='link_name' name='link_name' type='hidden' size='6' />
							</td>
						</tr>
						<tr>
							<td><?php print __('Maximum Bandwidth', 'weathermap'); ?><br /><?php print __('Into', 'weathermap'); ?><span id='link_nodename1a'>%NODE1%</span>'</td>
							<td><input id='link_bandwidth_in' name='link_bandwidth_in' type='text' class='ui-state-default ui-corner-all' size='8'/> bits/sec</td>
						</tr>
						<tr>
							<td><?php print __('Maximum Bandwidth', 'weathermap'); ?><br /><?php print __('Out of', 'weathermap'); ?><span id='link_nodename1b'>%NODE1%</span>'</td>
							<td>
								<input id='link_bandwidth_out_cb' name='link_bandwidth_out_cb' type='checkbox' value='symmetric' />Same As 'In' or <input id='link_bandwidth_out' name='link_bandwidth_out' type='text' class='ui-state-default ui-corner-all' size='8' /> bits/sec
							</td>
						</tr>
						<tr>
							<td><?php print __('Via Style', 'weathermap'); ?></td>
							<td>
								<select id='viastyle' name='viastyle'>
									<option value='curved'><?php print __('Curved', 'weathermap'); ?></option>
									<option value='angled'><?php print __('Angled', 'weathermap'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Data Source(s)', 'weathermap'); ?></td>
							<td>
								<textarea id='link_target' name='link_target' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Data Source Selector', 'weathermap'); ?></td>
							<td>
								<input id='link_target_picker' name='link_target_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='datasources' />
							</td>
						</tr>
						<tr>
							<td><?php print __('Link Width', 'weathermap'); ?></td>
							<td><input id='link_width' name='link_width' type='text' class='ui-state-default ui-corner-all' size='3' /> pixels</td>
						</tr>
						<tr>
							<td><?php print __('Info URL(s)', 'weathermap'); ?></td>
							<td>
								<textarea id='link_infourl' name='link_infourl' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Hover Graph URL(s)', 'weathermap'); ?></td>
							<td>
								<textarea id='link_hover' name='link_hover' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Template', 'weathermap'); ?></td>
							<td>
								<select id='link_template' name='link_template'>
									<?php
print '<option value="-1"' . (get_request_var('node_template') == -1 ? 'selected' : '') . '>' . __('All', 'weathermap') . '</option>';
$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN graph_local AS gl
										ON gt.id = gl.graph_template_id
										ORDER BY gt.name');

foreach ($graph_templates as $gt) {
	print '<option ' . (get_request_var('node_template') == $gt['id'] ? 'selected' : '');
	print ' value="' . $gt['id'] . '">' . html_escape($gt['name']) . '</option>';
}
?>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Selector', 'weathermap'); ?></td>
							<td>
								<input id='link_picker' name='link_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
						<tr>
							<td><?php print __('IN Comment', 'weathermap'); ?></td>
							<td>
								<input id='link_commentin' name='link_commentin' type='text' class='ui-state-default ui-corner-all' size='25' />
								<select id='link_commentposin' name='link_commentposin'>
									<option value=95>95%</option>
									<option value=90>90%</option>
									<option value=80>80%</option>
									<option value=70>70%</option>
									<option value=60>60%</option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('OUT Comment', 'weathermap'); ?></td>
							<td>
								<input id='link_commentout' name='link_commentout' type='text' class='ui-state-default ui-corner-all' size='25' />
								<select id='link_commentposout' name='link_commentposout'>
									<option value=5>5%</option>
									<option value=10>10%</option>
									<option value=20>20%</option>
									<option value=30>30%</option>
									<option value=40>40%</option>
									<option value=50>50%</option>
								</select>
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a class='ui-button ui-corner-all ui-widget' id='link_delete'><?php print __('Delete Link', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_edit'><?php print __('Edit', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_tidy'><?php print __('Tidy', 'weathermap'); ?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_via'><?php print __('Via', 'weathermap'); ?></a>
						<a id='tb_link_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap'); ?></a>
						<a id='tb_link_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap'); ?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('<p><strong>Bandwidth:</strong> Set the capacity used to calculate link utilisation, for example 100M or 1G. K, M, G and T are supported. Use the same value for both directions unless their capacities differ.</p>
<p><strong>Interface:</strong> Search by device, port or description, then select Use interface to fill the traffic source, click destination and hover graph together. Save applies the changes.</p>
<p><strong>Comments:</strong> Add text to display along each direction of the link. The percentage controls its position along the line.</p>
<p><strong>Advanced:</strong> Use custom data sources or combine multiple sources, choose different graphs, change the click destination, or adjust line shape. Data Source(s) controls the measured traffic; Info URL(s) controls where a click goes; Hover Graph URL(s) controls the images shown on hover.</p>', 'weathermap'); ?>
				</div>
			</div>
		</div>
		<!-- Link Properties -->

		<!-- Map Properties -->
		<div id='dlgMapProperties' class='dlgProperties' title='<?php print __('Map Properties', 'weathermap'); ?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td><?php print __('Map Title', 'weathermap'); ?></td>
							<td><input id='map_title' name='map_title' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->title) ?>'/></td>
						</tr>
						<tr>
							<td><?php print __('Legend Text', 'weathermap'); ?></td>
							<td><input id='map_legend' name='map_legend' type='text' class='ui-state-default ui-corner-all' size='25' value='<?php print html_escape($map->keytext['DEFAULT']) ?>' /></td>
						</tr>
						<tr>
							<td><?php print __('Background Image Filename', 'weathermap'); ?></td>
							<td>
								<select id='map_bgfile' name='map_bgfile'>
									<?php
if (count($backgd_list) == 0) {
	print '<option data-value="--NONE--">(no images are available)</option>';
} else {
	print '<option data-description="Solid White Background" data-imagesrc="" value="--NONE--">--NO BACKGROUND--</option>';

	foreach ($backgd_list as $im) {
		$display = ucfirst(str_replace(['.png', '.gif', '.jpg'], '', basename($im)));

		print '<option ' . ($im == $map->background ? 'selected ' : '');
		print 'data-description="' . $display . '" ';
		print 'data-imagesrc="' . $im . '" ';
		print 'value="' . html_escape($im) . '">' . $display . '</option>';
	}
}
?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Timestamp Text', 'weathermap'); ?></td>
							<td><input id='map_stamp' name='map_stamp' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->stamptext) ?>' /></td>
						</tr>
						<tr>
							<td><?php print __('Default Link Width', 'weathermap'); ?></td>
							<td><input id='map_linkdefaultwidth' name='map_linkdefaultwidth' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->width) ?>' /> <?php print __('pixels', 'weathermap'); ?></td>
						</tr>
						<tr>
							<td><?php print __('Default Link Bandwidth', 'weathermap'); ?></td>
							<td>
								<input id='map_linkdefaultbwin' name='map_linkdefaultbwin' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_in_cfg) ?>' /> <?php print __('bit/sec in', 'weathermap'); ?>, <input id='map_linkdefaultbwout' name='map_linkdefaultbwout' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_out_cfg) ?>' /> <?php print __('bit/sec out', 'weathermap'); ?>
							</td>
						</tr>
						<tr>
							<td><?php print __('Map Size', 'weathermap'); ?></td>
							<td>
								<input id='map_width' name='map_width' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->width) ?>' /> x
								<input id='map_height' name='map_height' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->height) ?>' /> <?php print __('pixels', 'weathermap'); ?>
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_map_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap'); ?></a>
						<a id='tb_map_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap'); ?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('This dialog controls overall Map Properties.  Supported by the Editor are the following: Title, Legend Text, Background Image, Timestamp Text, Default Link Width, Default Link Bandwidth, and Map Size.  There are several additional Map defaults possible.  But in this version of the Editor, we are only supporting those included.', 'weathermap'); ?>
				</div>
			</div>
		</div>
		<!-- Map Properties -->

		<!-- Map Style -->
		<div id='dlgMapStyle' class='dlgProperties' title='<?php print __('Map Style', 'flowview'); ?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Map Defaults', 'weathermap'); ?></div>
						</td></tr>
						<tr>
							<td><?php print __('HTML Style', 'weathermap'); ?></td>
							<td>
								<select id='mapstyle_htmlstyle' name='mapstyle_htmlstyle'>
									<option <?php print ($map->htmlstyle == 'overlib' ? 'selected' : '') ?> value='overlib'><?php print __('Dynamic HTML', 'flowview'); ?></option>
									<option <?php print ($map->htmlstyle == 'static' ? 'selected' : '') ?> value='static'><?php print __('Static HTML', 'flowview'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Key Style', 'weathermap'); ?></td>
							<td>
								<select id='mapstyle_keystyle' name='mapstyle_keystyle' class='ui-state-default ui-corner-all'>
									<?php
$styles = [
	'classic'    => __('Classic', 'weathermap'),
	'horizontal' => __('Horizontal', 'weathermap'),
	'vertical'   => __('Vertical', 'weathermap'),
	'inverted'   => __('Inverted', 'weathermap'),
	'tags'       => __('Tags', 'weathermap')
];

foreach ($styles as $id => $name) {
	print "<option value='$id' " . ($map->keystyle['DEFAULT'] == $id ? 'selected' : '') . '>' . $name . '</option>';
}
?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Legend Font', 'weathermap'); ?></td>
							<td><?php print get_fontlist($map, 'mapstyle_legendfont', $map->keyfont); ?></td>
						</tr>
						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Node Defaults', 'weathermap'); ?></div>
						</td></tr>
						<tr>
							<td><?php print __('Node Font', 'weathermap'); ?></td>
							<td><?php print get_fontlist($map, 'mapstyle_nodefont', $map->nodes['DEFAULT']->labelfont); ?></td>
						</tr>


						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Link Defaults', 'weathermap'); ?></div>
						</td></tr>
						<tr>
							<td><?php print __('Link Labels', 'weathermap'); ?></td>
							<td>
								<select id='mapstyle_linklabels' name='mapstyle_linklabels'>
									<option <?php print($map->links['DEFAULT']->labelstyle == 'bits' ? 'selected' : ''); ?> value='bits'><?php print __('Bits/sec', 'weathermap'); ?></option>
									<option <?php print($map->links['DEFAULT']->labelstyle == 'percent' ? 'selected' : ''); ?> value='percent'><?php print __('Percentage', 'weathermap'); ?></option>
									<option <?php print($map->links['DEFAULT']->labelstyle == 'none' ? 'selected' : ''); ?> value='none'><?php print __('None', 'weathermap'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Arrow Style', 'weathermap'); ?></td>
							<td>
								<select id='mapstyle_arrowstyle' name='mapstyle_arrowstyle'>
									<option <?php print($map->links['DEFAULT']->arrowstyle == 'classic' ? 'selected' : ''); ?> value='classic'><?php print __('Classic', 'weathermap'); ?></option>
									<option <?php print($map->links['DEFAULT']->arrowstyle == 'compact' ? 'selected' : ''); ?> value='compact'><?php print __('Compact', 'weathermap'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><label for='mapstyle_linkfont'><?php print __esc('Traffic Label Font', 'weathermap'); ?></label></td>
							<td><?php print wm_style_font_select($map, 'mapstyle_linkfont', $map->links['DEFAULT']->bwfont); ?></td>
						</tr>
						<?php print wm_comment_style_fields($map); ?>


					</table>
<details class='wm-mapstyle-advanced' style='margin-top: 12px;'>
<summary style='cursor: pointer; padding: 8px 0;'><?php print __('Advanced settings', 'weathermap'); ?></summary>
<p><?php print __('Hover dimensions use pixels. Set 0 for automatic sizing. Width limits the preview size; height is a legacy hint and may not change the displayed height. Graph URLs can specify their own image dimensions.', 'weathermap'); ?></p>
<table style='width: 100%;'>
<tr>
							<td><label for='mapstyle_nodewidth'><?php print __esc('Node Hover Width (pixels)', 'weathermap'); ?></label></td>
							<td>
								<input id='mapstyle_nodewidth' name='mapstyle_nodewidth' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->nodes['DEFAULT']->overlibwidth; ?>'>
							</td>
						</tr>
<tr>
							<td><label for='mapstyle_nodeheight'><?php print __esc('Node Hover Height (pixels)', 'weathermap'); ?></label></td>
							<td>
								<input id='mapstyle_nodeheight' name='mapstyle_nodeheight' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->nodes['DEFAULT']->overlibheight; ?>'>
							</td>
						</tr>
<tr>
							<td><label for='mapstyle_linkwidth'><?php print __esc('Link Hover Width (pixels)', 'weathermap'); ?></label></td>
							<td>
								<input id='mapstyle_linkwidth' name='mapstyle_linkwidth' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->links['DEFAULT']->overlibwidth; ?>'>
							</td>
						</tr>
<tr>
							<td><label for='mapstyle_linkheight'><?php print __esc('Link Hover Height (pixels)', 'weathermap'); ?></label></td>
							<td>
								<input id='mapstyle_linkheight' name='mapstyle_linkheight' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->links['DEFAULT']->overlibheight; ?>'>
							</td>
						</tr>
</table>
</details>

				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_mapstyle_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap'); ?></a>
						<a id='tb_mapstyle_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap'); ?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					The Map Style form allows you to alter some, but not all Map style settings.  See the Documentation for additional style options.
				</div>
			</div>
		</div>

		<!-- Map Style -->

		<!-- Colours -->
		<div id='dlgColours' class='dlgProperties' title='Manage Colors'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<div class='dlgComment'>
						Nothing in here works yet. The aim is to have a nice color picker somehow.
					</div>
					<table class='cactiTable'>
						<tr>
							<td>Background Color</td>
							<td></td>
						</tr>

						<tr>
							<td>Link Outline Color</td>
							<td></td>
						</tr>
						<tr>
							<td>Scale Colors</td>
							<td>Some pleasant way to design the bandwidth color scale goes in here???</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_colours_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_colours_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					In the future, this form will allow you to set the various color variables at the Map level.  For now, you can control these through a direct modification of your Weathermap configuration files.
				</div>
			</div>
		</div>
		<!-- Colours -->

		<!-- Images -->
		<div id='dlgImages' class='dlgProperties' title='Manage Images'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<p>Nothing in here works yet. </p>
					The aim is to have some nice way to upload images which can be used as icons or backgrounds.
					These images are what would appear in the dropdown boxes that don't currently do anything in the Node and Map Properties dialogs. This may end up being a separate page rather than a dialog box...
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_images_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_images_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					In the future, this form will allow you to manage adding additional icons to the Icon library.  For now, you can copy your png files to either &lt;path_cacti&gt;/plugins/weathermap/images/backgrounds/ for Background Images and &lt;path_cacti&gt;/plugins/weathermap/images/objects/ for Icon files.
				</div>
			</div>
		</div>
		<!-- Images -->

		<!-- TextEdit -->
        <div id='dlgTextEdit' class='dlgProperties' title='Edit Map Object'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<p>You can edit the map items directly here.</p>
	   	             <textarea id='item_configtext' name='item_configtext' cols='80' rows='15'></textarea>
				</div>
				<div class='dlgHelp'>
					From this form, you can edit the Mapfile component directly.  No syntax checking is done.  So, make changes with care.
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_textedit_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_textedit_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
			</div>
		</div>
		<!-- TextEdit -->

		<!-- TextEditSettings -->
		<div id='dlgEditorSettings' class='dlgProperties' title='Editor Settings'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>Show VIAs overlay</td>
							<td>
								<select id='editorsettings_showvias' name='editorsettings_showvias'>
									<option <?php print ($use_overlay ? 'selected' : '') ?> value='1'>Yes</option>
									<option <?php print ($use_overlay ? '' : 'selected') ?> value='0'>No</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>Show Relative Positions overlay</td>
							<td>
								<select id='editorsettings_showrelative' name='editorsettings_showrelative'>
									<option <?php print ($use_relative_overlay ? 'selected' : '') ?> value='1'>Yes</option>
									<option <?php print ($use_relative_overlay ? '' : 'selected') ?> value='0'>No</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>Snap To Grid</td>
							<td>
								<select id='editorsettings_gridsnap' name='editorsettings_gridsnap'>
									<option <?php print ($grid_snap_value == 0 ? 'selected' : '') ?> value='NO'>No</option>
									<option <?php print ($grid_snap_value == 5 ? 'selected' : '') ?> value='5'>5 pixels</option>
									<option <?php print ($grid_snap_value == 10 ? 'selected' : '') ?> value='10'>10 pixels</option>
									<option <?php print ($grid_snap_value == 15 ? 'selected' : '') ?> value='15'>15 pixels</option>
									<option <?php print ($grid_snap_value == 20 ? 'selected' : '') ?> value='20'>20 pixels</option>
									<option <?php print ($grid_snap_value == 50 ? 'selected' : '') ?> value='50'>50 pixels</option>
									<option <?php print ($grid_snap_value == 100 ? 'selected' : '') ?> value='100'>100 pixels</option>
								</select>
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_editorsettings_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_editorsettings_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					This form allows you to control a select number of Editor Settings including both Overlay Style and Snap Settings in pixels.
				</div>
			</div>
		</div>
		<!-- TextEditSettings -->
	</form>
	<?php
	$showversionbox = read_config_option('weathermap_showversion');

if ($showversionbox == 'on') {
	weathermap_footer_links();
}
?>
</body>
</html>
