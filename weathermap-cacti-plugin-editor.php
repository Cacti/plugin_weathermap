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

	if ((isset($parts[0])) && (intval($parts[0]) == 1)) {
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

if (isset_request_var('action')) {
	$action = get_nfilter_request_var('action');
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

		break;
	case 'datasources':
		display_datasources();
		exit;

		break;
	case 'newmap':
		newMap($mapfile);

		break;
	case 'newmapcopy':
		newMapCopy($mapfile);

		break;
	case 'font_samples':
		displayFontSamples($mapfile);

		exit();

		break;
	case 'draw':
		drawMap($mapfile, $selected, $use_overlay, $use_relative_overlay);

		exit();

		break;
	case 'show_config':
		showConfig($mapfile);

		exit();

		break;
	case 'fetch_config':
		fetchConfig($mapfile);

		exit();

		break;
	case 'set_link_config':
		setLinkConfig($mapfile);

		break;
	case 'set_node_config':
		setNodeConfig($mapfile);

		break;
	case 'set_node_properties':
		setNodeProperties($mapfile);
		exit;

		break;
	case 'set_link_properties':
		setLinkProperties($mapfile);
		exit;

		break;
	case 'set_map_properties':
		setMapProperties($mapfile);
		exit;

		break;
	case 'set_map_style':
		setMapStyle($mapfile);
		exit;

		break;
	case 'add_link2':
		addLink($mapfile);
		exit;

		break;
	case 'place_legend':
		placeLegend($mapfile, $grid_snap_value);
		exit;

		break;
	case 'place_stamp':
		placeStamp($mapfile, $grid_snap_value);
		exit;

		break;
	case 'via_link':
		viaLink($mapfile);
		exit;

		break;
	case 'move_node':
		moveNode($mapfile, $grid_snap_value);
		exit;

		break;
	case 'link_tidy':
		linkTidy($mapfile);
		exit;

		break;
	case 'retidy':
		reTidy($mapfile);
		exit;

		break;
	case 'retidy_all':
		reTidyAll($mapfile);
		exit;

		break;
	case 'untidy':
		unTidy($mapfile);
		exit;

		break;
	case 'delete_link':
		deleteLink($mapfile);
		exit;

		break;
	case 'add_node':
		addNode($mapfile, $grid_snap_value);
		exit;

		break;
	case 'editor_settings':
		editorSettings($mapfile);
		exit;

		break;
	case 'delete_node':
		deleteNode($mapfile);
		exit;

		break;
	case 'clone_node':
		cloneNode($mapfile);
		exit;

		break;
	case 'load_area_data':
		getMapAreaData($mapfile);
		exit;

		break;
	case 'load_map_javascript':
		getMapJavaScript($mapfile);
		exit;

		break;
	case 'nothing':

		break;
	default:
		cacti_log('WARNING: Invalid action ' . $action, false, 'WEATHERMAP');

		break;
}

$map = new WeatherMap;
$map->ReadConfig($mapfile);

//by here, there should be a valid $map - either a blank one, the existing one, or the existing one with requested changes
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
	if (!in_array($im, $image_list)) {
		$image_list[] = $im;
	}
}

sort($image_list);

cacti_cookie_set('wmeditor', ($use_overlay ? '1':'0') . ':' . ($use_relative_overlay ? '1':'0') . ':' . intval($grid_snap_value));

// get the users selected theme and the weathermap version
$selectedTheme      = get_selected_theme();
$weathermap_version = plugin_weathermap_numeric_version();

?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' lang='en' xml:lang='en'>
<head>
	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/favicon.ico'?>' rel='shortcut icon'>
	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/cacti_logo.gif'?>' rel='icon' sizes='96x96'>
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/jquery-ui.css';?>'>
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/main.css';?>'>
	<link rel='stylesheet' type='text/css' media='screen' href='css/editor.css' />
	<?php getEditorJs();?>
	<script src='<?php print $config['url_path'] . 'include/js/jquery.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/jquery-ui.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/jquery.tablesorter.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/jquery.colorpicker.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/js.storage.js';?>' type='text/javascript'></script>
	<script src='js/editor.js' type='text/javascript'></script>
	<script src='js/jquery.ddslick.js' type='text/javascript'></script>
	<script src='js/jquery.ui-contextmenu.js' type='text/javascript'></script>

	<title><?php print __('PHP Weathermap Editor %s', $weathermap_version, 'flowview');?></title>
</head>

<body id='mainView' class='mainView'>
	<div id='toolbar'>
		<ul>
			<li class='tb_active' id='tb_newfile'><?php print __('Change<br>File', 'weathermap');?></li>
			<li class='tb_active' id='tb_addnode'><?php print __('Add<br>Node', 'weathermap');?></li>
			<li class='tb_active' id='tb_addlink'><?php print __('Add<br>Link', 'weathermap');?></li>
			<li class='tb_active' id='tb_poslegend'><?php print __('Position<br>Legend', 'weathermap');?></li>
			<li class='tb_active' id='tb_postime'><?php print __('Position<br>Timestamp', 'weathermap');?></li>
			<li class='tb_active' id='tb_mapprops'><?php print __('Map<br>Properties', 'weathermap');?></li>
			<li class='tb_active' id='tb_mapstyle'><?php print __('Map<br>Style', 'weathermap');?></li>
			<li class='tb_active' id='tb_colours'><?php print __('Manage<br>Colors', 'weathermap');?></li>
			<li class='tb_active' id='tb_manageimages'><?php print __('Manage<br>Images', 'weathermap');?></li>
			<li class='tb_active' id='tb_prefs'><?php print __('Editor<br>Settings', 'weathermap');?></li>
			<li class='tb_coords' id='tb_coords'><?php print __('Position<br>---, ---', 'weathermap');?></li>
			<li class='tb_help'>
				<span id='tb_help'><?php print __('Select a menu item or either right-click or click on a Node or Link to edit it\'s properties', 'weathermap');?></span>
			</li>
		</ul>
	</div>
	<form id='frmMain' action='<?php print $editor_name ?>' method='post'>
		<div class='mainArea'>
			<input id='xycapture' name='xycapture' data-width='<?php print html_escape($map->width);?>' data-height='<?php print html_escape($map->height);?>' style='display:none' type='image' src='<?php print html_escape($imageurl);?>' />
			<img id='existingdata' name='existingdata' data-width='<?php print html_escape($map->width);?>' data-height='<?php print html_escape($map->height);?>' src='<?php print html_escape($imageurl);?>' usemap='#weathermap_imap' />
			<input id='x' name='x' type='hidden' />
			<input id='y' name='y' type='hidden' />
			<div class='debug' style='display:none'><p><strong><?php print __('Debug', 'weathermap');?></strong>
				<a href='?action=retidy_all&mapname=<?php print html_escape($mapname);?>'><?php print __('Re-tidy ALL', 'weathermap');?></a>
				<a href='?action=retidy&mapname=<?php print html_escape($mapname);?>'><?php print __('Re-tidy', 'weathermap');?></a>
				<a href='?action=untidy&mapname=<?php print html_escape($mapname);?>'><?php print __('Un-tidy', 'weathermap');?></a>
				<a href='?action=nothing&mapname=<?php print html_escape($mapname);?>'><?php print __('Do Nothing', 'weathermap');?></a>
				<span>
					<label for='mapname'><?php print __('mapfile', 'weathermap');?></label>
					<input id='mapname' name='mapname' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($mapname);?>'>
				</span>
				<span>
					<label for='action'><?php print __('action', 'weathermap');?></label>
					<input id='action' name='action' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='param'><?php print __('param', 'weathermap');?></label>
					<input id='param' name='param' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='param2'><?php print __('param2', 'weathermap');?></label>
					<input id='param2' name='param2' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<span>
					<label for='debug'><?php print __('debug', 'weathermap');?></label>
					<input id='debug' name='debug' type='text' class='ui-state-default ui-corner-all' value=''>
				</span>
				<a target='configwindow' href='?action=show_config&mapname=<?php print urlencode($mapname) ?>'><?php print __('See config', 'weathermap');?></a>
			</div>
		</div>

		<!-- Data for overlay and selection -->
		<div class='scriptData'>
			<script type='text/javascript'>
			<?php getMapJavaScript($mapfile);?>
			</script>
		</div>
		<div class='mapData'>
			<?php getMapAreaData($mapfile);?>
		</div>
		<!-- End DAta for overlay and selection -->

		<!-- Node Properties -->
		<div id='dlgNodeProperties' class='dlgProperties' title='<?php print __('Node Properties', 'weathermap');?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>
								<input id='node_name' name='node_name' type='hidden' size='6'/>
							</td>
						</tr>
						<tr>
							<td><?php print __('Position', 'weathermap');?></td>
							<td><input id='node_x' name='node_x' type='text' class='ui-state-default ui-corner-all' size='4' />,<input id='node_y' name='node_y' type='text' class='ui-state-default ui-corner-all' size='4' /></td>
						</tr>
						<tr>
							<td><?php print __('Internal Name', 'weathermap');?></td>
							<td><input id='node_new_name' name='node_new_name' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td><?php print __('Label', 'weathermap');?></td>
							<td><input id='node_label' name='node_label' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td><?php print __('Icon Filename', 'weathermap');?></td>
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
											$display = ucfirst(str_replace(array('.png', '.gif', '.jpg'), '', basename($im)));

											print '<option ';
											print 'data-description="' . $display . '" ';
											print 'data-imagesrc="' . $im . '" ';
											print 'value="' . html_escape($im) .'">' . $display . '</option>';
										}
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Info URL(s)', 'weathermap');?></td>
							<td>
								<textarea id='node_infourl' name='node_infourl' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Hover Graph URL(s)', 'weathermap');?></td>
							<td>
								<textarea id='node_hover' name='node_hover' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Template', 'weathermap');?></td>
							<td>
								<select id='node_template' name='node_template'>
									<?php
									print '<option value="-1"' . (get_request_var('node_template') == -1 ? 'selected':'') . '>' . __('All', 'weathermap') . '</option>';
									$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN graph_local AS gl
										ON gt.id = gl.graph_template_id
										ORDER BY gt.name');

									foreach ($graph_templates as $gt) {
										print '<option ' . (get_request_var('node_template') == $gt['id'] ? 'selected':'');
										print ' value="' . $gt['id'] . '">' . html_escape($gt['name']) . '</option>';
									}
									?>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Selector', 'weathermap');?></td>
							<td>
								<input id='node_picker' name='node_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a class='ui-button ui-corner-all ui-widget' id='node_move'><?php print __('Move', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_delete'><?php print __('Delete', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_clone'><?php print __('Clone', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='node_edit'><?php print __('Edit', 'weathermap');?></a>
						<a id='tb_node_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap');?></a>
						<a id='tb_node_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap');?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('You can modify the Weathermap Node from here.  The Position columns are the X,Y position on the Map.  The Internal Name is the unique ID given to the Node.  The Label is the external name of the Node that you provide to users.  The Icon Filename is the Graphic that you want to represent the Node Object.  The INFO URL is a link that you can provide when clicking on the active Map Node.  The Hover Graph URL\'s are Cacti or other Graphs URL\'s that can will appear when hovering over the Node.  The Graph Selector is a helper for selecting Cacti Graphs for the Graph URL\'s.   There are several other Node properties possible.  However, today we are only supporting those included above.', 'flowview');?>
				</div>
			</div>
		</div>
		<!-- Node Properties -->

		<!-- Link Properties -->
		<div id='dlgLinkProperties' class='dlgProperties' title='<?php print __('Link Properties', 'weathermap');?>'>
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
							<td>Maximum Bandwidth<br />Into '<span id='link_nodename1a'>%NODE1%</span>'</td>
							<td><input id='link_bandwidth_in' name='link_bandwidth_in' type='text' class='ui-state-default ui-corner-all' size='8'/> bits/sec</td>
						</tr>
						<tr>
							<td>Maximum Bandwidth<br /> Out of '<span id='link_nodename1b'>%NODE1%</span>'</td>
							<td>
								<input id='link_bandwidth_out_cb' name='link_bandwidth_out_cb' type='checkbox' value='symmetric' />Same As 'In' or <input id='link_bandwidth_out' name='link_bandwidth_out' type='text' class='ui-state-default ui-corner-all' size='8' /> bits/sec</td>
						</tr>
						<tr>
							<td><?php print __('Data Source(s)', 'weathermap');?></td>
							<td>
								<textarea id='link_target' name='link_target' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Data Source Selector', 'weathermap');?></td>
							<td>
								<input id='link_target_picker' name='link_target_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='datasources' />
							</td>
						</tr>
						<tr>
							<td><?php print __('Link Width', 'weathermap');?></td>
							<td><input id='link_width' name='link_width' type='text' class='ui-state-default ui-corner-all' size='3' /> pixels</td>
						</tr>
						<tr>
							<td><?php print __('Info URL(s)', 'weathermap');?></td>
							<td>
								<textarea id='link_infourl' name='link_infourl' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Hover Graph URL(s)', 'weathermap');?></td>
							<td>
								<textarea id='link_hover' name='link_hover' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Template', 'weathermap');?></td>
							<td>
								<select id='link_template' name='link_template'>
									<?php
									print '<option value="-1"' . (get_request_var('node_template') == -1 ? 'selected':'') . '>' . __('All', 'weathermap') . '</option>';
									$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN graph_local AS gl
										ON gt.id = gl.graph_template_id
										ORDER BY gt.name');

									foreach ($graph_templates as $gt) {
										print '<option ' . (get_request_var('node_template') == $gt['id'] ? 'selected':'');
										print ' value="' . $gt['id'] . '">' . html_escape($gt['name']) . '</option>';
									}
									?>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Selector', 'weathermap');?></td>
							<td>
								<input id='link_picker' name='link_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
						<tr>
							<td><?php print __('IN Comment', 'weathermap');?></td>
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
							<td><?php print __('OUT Comment', 'weathermap');?></td>
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
						<a class='ui-button ui-corner-all ui-widget' id='link_delete'><?php print __('Delete Link', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_edit'><?php print __('Edit', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_tidy'><?php print __('Tidy', 'weathermap');?></a>
						<a class='ui-button ui-corner-all ui-widget' id='link_via'><?php print __('Via', 'weathermap');?></a>
						<a id='tb_link_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap');?></a>
						<a id='tb_link_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap');?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('You can modify the Weathermap Link from here.  The Maximum Bandwidth refers to the Maximum Bandwidth attainable.  Supported suffixes are K, M, G, and T.  Data Sources is a location where you can add RRDfile locations including some RRDfile math.  See the documentation for that.  The Data Source Selector is a helper for picking Cacti Graphs to represent the target or targets.  Link width is the Width of the link arrow.  The INFO URL is a link that you can provide when clicking on the active Map Link.  The Hover Graph URL\'s are Cacti or other Graphs URL\'s that can will appear when hovering over the Link.  The Graph Selector is a helper for selecting Cacti Graphs for the Graph URL\'s. The IN and OUT Comments appear on the Links with the Links.  There are several other Link properties possible.  However, today we are only supporting those included above.', 'flowview');?>
				</div>
			</div>
		</div>
		<!-- Link Properties -->

		<!-- Map Properties -->
		<div id='dlgMapProperties' class='dlgProperties' title='<?php print __('Map Properties', 'weathermap');?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td><?php print __('Map Title', 'weathermap');?></td>
							<td><input id='map_title' name='map_title' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->title) ?>'/></td>
						</tr>
						<tr>
							<td><?php print __('Legend Text', 'weathermap');?></td>
							<td><input id='map_legend' name='map_legend' type='text' class='ui-state-default ui-corner-all' size='25' value='<?php print html_escape($map->keytext['DEFAULT']) ?>' /></td>
						</tr>
						<tr>
							<td><?php print __('Background Image Filename', 'weathermap');?></td>
							<td>
								<select id='map_bgfile' name='map_bgfile'>
									<?php
									if (count($backgd_list) == 0) {
										print '<option data-value="--NONE--">(no images are available)</option>';
									} else {
										print '<option data-description="Solid White Background" data-imagesrc="" value="--NONE--">--NO BACKGROUND--</option>';
										foreach ($backgd_list as $im) {
											$display = ucfirst(str_replace(array('.png', '.gif', '.jpg'), '', basename($im)));

											print '<option ' . ($im == $map->background ? 'selected ':'');
											print 'data-description="' . $display . '" ';
											print 'data-imagesrc="' . $im . '" ';
											print 'value="' . html_escape($im) .'">' . $display . '</option>';
										}
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Timestamp Text', 'weathermap');?></td>
							<td><input id='map_stamp' name='map_stamp' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->stamptext) ?>' /></td>
						</tr>
						<tr>
							<td><?php print __('Default Link Width', 'weathermap');?></td>
							<td><input id='map_linkdefaultwidth' name='map_linkdefaultwidth' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->width) ?>' /> <?php print __('pixels', 'weathermap');?></td>
						</tr>
						<tr>
							<td><?php print __('Default Link Bandwidth', 'weathermap');?></td>
							<td>
								<input id='map_linkdefaultbwin' name='map_linkdefaultbwin' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_in_cfg) ?>' /> <?php print __('bit/sec in', 'weathermap');?>, <input id='map_linkdefaultbwout' name='map_linkdefaultbwout' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_out_cfg) ?>' /> <?php print __('bit/sec out', 'weathermap');?>
							</td>
						</tr>
						<tr>
							<td><?php print __('Map Size', 'weathermap');?></td>
							<td>
								<input id='map_width' name='map_width' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->width) ?>' /> x
								<input id='map_height' name='map_height' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->height) ?>' /> <?php print __('pixels', 'weathermap');?>
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_map_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap');?></a>
						<a id='tb_map_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap');?></a>
					</div>
				</div>
				<div class='dlgHelp'>
					<?php print __('This dialog controls overall Map Properties.  Supported by the Editor are the following: Title, Legend Text, Background Image, Timestamp Text, Default Link Width, Default Link Bandwidth, and Map Size.  There are several additional Map defaults possible.  But in this version of the Editor, we are only supporting those included.', 'weathermap');?>
				</div>
			</div>
		</div>
		<!-- Map Properties -->

		<!-- Map Style -->
		<div id='dlgMapStyle' class='dlgProperties' title='<?php print __('Map Style', 'flowview');?>'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Map Defaults', 'weathermap');?></div>
						</td></tr>
						<tr>
							<td><?php print __('HTML Style', 'weathermap');?></td>
							<td>
								<select id='mapstyle_htmlstyle' name='mapstyle_htmlstyle'>
									<option <?php print ($map->htmlstyle == 'overlib' ? 'selected' : '') ?> value='overlib'><?php print __('Dynamic HTML', 'flowview');?></option>
									<option <?php print ($map->htmlstyle == 'static' ? 'selected' : '') ?> value='static'><?php print __('Static HTML', 'flowview');?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Legend Font', 'weathermap');?></td>
							<td><?php print get_fontlist($map, 'mapstyle_legendfont', $map->keyfont);?></td>
						</tr>
						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Node Defaults', 'weathermap');?></div>
						</td></tr>
						<tr>
							<td><?php print __('Node Font', 'weathermap');?></td>
							<td><?php print get_fontlist($map, 'mapstyle_nodefont', $map->nodes['DEFAULT']->labelfont);?></td>
						</tr>
						<tr>
							<td><?php print __('Graph Width', 'weathermap');?></td>
							<td>
								<input id='mapstyle_nodewidth' name='mapstyle_nodewidth' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->nodes['DEFAULT']->overlibwidth;?>'>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Height', 'weathermap');?></td>
							<td>
								<input id='mapstyle_nodeheight' name='mapstyle_nodeheight' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->nodes['DEFAULT']->overlibheight;?>'>
							</td>
						</tr>
						<tr><td colspan='2'>
						<div class='cactiTableTitleRow' style='display:table'><?php print __('Link Defaults', 'weathermap');?></div>
						</td></tr>
						<tr>
							<td><?php print __('Link Labels', 'weathermap');?></td>
							<td>
								<select id='mapstyle_linklabels' name='mapstyle_linklabels'>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'bits' ? 'selected' : '');?> value='bits'><?php print __('Bits/sec', 'weathermap');?></option>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'percent' ? 'selected' : '');?> value='percent'><?php print __('Percentage', 'weathermap');?></option>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'none' ? 'selected' : '');?> value='none'><?php print __('None', 'weathermap');?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Arrow Style', 'weathermap');?></td>
							<td>
								<select id='mapstyle_arrowstyle' name='mapstyle_arrowstyle'>
									<option <?php print ($map->links['DEFAULT']->arrowstyle == 'classic' ? 'selected' : '');?> value='classic'><?php print __('Classic', 'weathermap');?></option>
									<option <?php print ($map->links['DEFAULT']->arrowstyle == 'compact' ? 'selected' : '');?> value='compact'><?php print __('Compact', 'weathermap');?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><?php print __('Link Label Font', 'weathermap');?></td>
							<td><?php print get_fontlist($map, 'mapstyle_linkfont', $map->links['DEFAULT']->bwfont);?></td>
						</tr>
						<tr>
							<td><?php print __('Graph Width', 'weathermap');?></td>
							<td>
								<input id='mapstyle_linkwidth' name='mapstyle_linkwidth' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->links['DEFAULT']->overlibwidth;?>'>
							</td>
						</tr>
						<tr>
							<td><?php print __('Graph Height', 'weathermap');?></td>
							<td>
								<input id='mapstyle_linkheight' name='mapstyle_linkheight' type='text' size='6' class='ui-state-default ui-corner-all' value='<?php print $map->links['DEFAULT']->overlibheight;?>'>
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_mapstyle_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'><?php print __('Cancel', 'weathermap');?></a>
						<a id='tb_mapstyle_submit' class='wm_submit ui-button ui-corner-all ui-widget'><?php print __('Save', 'weathermap');?></a>
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
</body>
</html>

