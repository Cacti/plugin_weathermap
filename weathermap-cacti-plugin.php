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

$guest_account  = true;

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/weathermap/lib/WeatherMap.class.php');

$showversionbox = read_config_option('weathermap_showversion');

set_default_action();

switch (get_request_var('action')) {
	case 'viewthumb': // FALL THROUGH
	case 'viewimage':
		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			$imageformat = strtolower(read_config_option('weathermap_output_format'));

			$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

			$map = db_fetch_row_prepared("SELECT wm.*
				FROM weathermap_auth AS wa
				INNER JOIN weathermap_maps AS wm
				ON wm.id = wa.mapid
				WHERE active = 'on'
				AND (userid = ? OR userid = 0)
				AND wm.id = ?
				LIMIT 1",
				array($userid, $id));

			if (cacti_sizeof($map)) {
				$imagefile = __DIR__ . '/output/' . $map['filehash'] . '.' . $imageformat;

				if ($action == 'viewthumb') {
					$imagefile = __DIR__ . '/output/' . $map['filehash'] . '.thumb.' . $imageformat;
				}

				$orig_cwd = getcwd();
				chdir(__DIR__);

				header('Content-type: image/png');

				// readfile_chunked($imagefile);
				readfile($imagefile);

				dir($orig_cwd);
			} else {
				// no permission to view this map
			}
		}

		break;
	case 'liveviewimage':
		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

			$map = db_fetch_row_prepared("SELECT wm.*
				FROM weathermap_auth AS wa
				INNER JOIN weathermap_maps AS wm
				ON wm.id = wa.mapid
				WHERE active = 'on'
				AND (userid = ? OR userid = 0)
				AND wm.id = ?
				LIMIT 1",
				array($userid, $id));

			if (cacti_sizeof($map)) {
				$mapfile  = __DIR__ . '/configs/' . $map['configfile'];
				$orig_cwd = getcwd();

				chdir(__DIR__);

				header('Content-type: image/png');

				$map = new WeatherMap;

				$map->context = '';
				$map->rrdtool = read_config_option('path_rrdtool');

				$map->ReadConfig($mapfile);
				$map->ReadData();
				$map->DrawMap('', '', 250, true, false);

				dir($orig_cwd);
			}
		}

		break;
	case 'liveview':
		top_graph_header();

		print '<link rel="stylesheet" type="text/css" media="screen" href="css/weathermap.css"/>';
		print '<script type="text/javascript" src="' . $config['url_path'] . 'plugins/weathermap/js/weathermap.js"></script> ';

		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

			$map = db_fetch_row_prepared("SELECT wm.*
				FROM weathermap_auth AS wa
				INNER JOIN weathermap_maps AS wm
				ON wm.id = wa.mapid
				WHERE active = 'on'
				AND (userid = ? OR userid = 0)
				AND wm.id = ?
				LIMIT 1",
				array($userid, $id));

			if (cacti_sizeof($map)) {
				$maptitle = $map['titlecache'];

				print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";

				?>
				<tr class='even noprint'>
					<td>
						<table class='filterTable'>
							<tr>
								<td class='textHeader nowrap'><?php print html_escape($maptitle); ?></td>
							</tr>
						</table>
					</td>
				</tr>
				<?php
				print '<tr><td>';

				# print "Generating map $id here now from ".$map[0]['configfile'];

				$confdir = __DIR__ . '/configs/';

				// everything else in this file is inside this else
				$mapname = $map[0]['configfile'];
				$mapfile = $confdir . '/' . $mapname;

				$orig_cwd = getcwd();
				chdir(__DIR__);

				$map = new WeatherMap;
				// $map->context = 'cacti';
				$map->rrdtool = read_config_option('path_rrdtool');

				print '<pre>';

				$map->ReadConfig($mapfile);
				$map->ReadData();
				$map->DrawMap('null');
				$map->PreloadMapHTML();

				print '</pre>';

				print '';

				print "<img src='?action=liveviewimage&id=$id' />\n";
				print $map->imap->subHTML('LEGEND:');
				print $map->imap->subHTML('TIMESTAMP');
				print $map->imap->subHTML('NODE:');
				print $map->imap->subHTML('LINK:');

				chdir($orig_cwd);

				print '</td></tr>';
				print '</table>';
			} else {
				print 'Map unavailable.';
			}
		} else {
			print 'No ID, or unknown map name.';
		}

		weathermap_versionbox();

		bottom_footer();

		break;
	case 'mrss':
		header('Content-type: application/rss+xml');

		print '<?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
		print '<rss xmlns:media="http://search.yahoo.com/mrss" version="2.0"><channel><title>My Network Weathermaps</title>';

		$userid  = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

		$maplist = db_fetch_assoc_prepared("SELECT wm.*
			FROM weathermap_auth AS wa
			INNER JOIN weathermap_maps AS wm
			ON wm.id = wa.mapid
			WHERE active = 'on'
			AND (userid = ? OR userid = 0)
			ORDER BY sortorder, id
			LIMIT 1",
			array($userid));

		if (cacti_sizeof($maplist)) {
			foreach ($maplist as $map) {
				$thumburl = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewthumb&id=' . $map['filehash'] . '&time=' . time();
				$bigurl   = $config['url_path'] . 'weathermap-cacti-plugin.php?action=viewimage&id=' . $map['filehash'] . '&time=' . time();
				$linkurl  = $config['url_path'] . 'weathermap-cacti-plugin.php?action=viewmap&id=' . $map['filehash'];
				$maptitle = $map['titlecache'];
				$guid     = $map['filehash'];

				if ($maptitle == '') {
					$maptitle = 'Map for config file: ' . $map['configfile'];
				}

				printf('<item><title>%s</title><description>Network Weathermap named "%s"</description><link>%s</link><media:thumbnail url="%s"/><media:content url="%s"/><guid isPermaLink="false">%s%s</guid></item>',
                    $maptitle, $maptitle, $linkurl, $thumburl, $bigurl, $config['url_path'], $guid);
				print "\n";
			}
		}

		print '</channel></rss>';

		break;
	case 'viewmapcycle':
		$fullscreen = 0;

		if (isset_request_var('fullscreen')) {
			$fullscreen = get_filter_request_var('fullscreen');
		}

		if ($fullscreen == 1) {
			print '<!DOCTYPE html>' . PHP_EOL;
			print '<html><head>';
			print '<link rel="stylesheet" type="text/css" media="screen" href="css/weathermap.css"/>';
			print '<link rel="stylesheet" type="text/css" media="screen" href="' . $config['url_path'] . 'include/fa/css/all.css' . '"/>';
			print '<script type="text/javascript" src="' . $config['url_path'] . 'include/js/jquery.js"></script>';
			print '</head><body id="wm_fullscreen">';
		} else {
			top_graph_header();
		}

		print '<link rel="stylesheet" type="text/css" media="screen" href="css/weathermap.css"/>';
		print '<script type="text/javascript" src="' . $config['url_path'] . 'plugins/weathermap/js/weathermap.js"></script> ';

		$groupid = -1;

		if (isset_request_var('group')) {
			$groupid = get_filter_request_var('group');
		}

		weathermap_fullview(true, false, $groupid, $fullscreen);

		if ($fullscreen == 0) {
			weathermap_versionbox();
		}

		if ($fullscreen == 0) {
			bottom_footer();
		}

		break;
	case 'viewmap':
		top_graph_header();

		print '<link rel="stylesheet" type="text/css" media="screen" href="css/weathermap.css"/>';
		print '<script type="text/javascript" src="' . $config['url_path'] . 'plugins/weathermap/js/weathermap.js"></script> ';

		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			weathermap_singleview($id);
		}

		weathermap_versionbox();

		bottom_footer();

		break;
	default:
		top_graph_header();

		print '<link rel="stylesheet" type="text/css" media="screen" href="css/weathermap.css"/>';
		print '<script type="text/javascript" src="' . $config['url_path'] . 'plugins/weathermap/js/weathermap.js"></script> ';

		$group_id = -1;

		if (isset_request_var('group_id')) {
			$group_id = get_filter_request_var('group_id');
			$_SESSION['wm_last_group'] = $group_id;
		} elseif (isset($_SESSION['wm_last_group'])) {
			$group_id = intval($_SESSION['wm_last_group']);
		}

		$tabs    = weathermap_get_valid_tabs();
		$tab_ids = array_keys($tabs);

		if (($group_id == -1) && (cacti_sizeof($tab_ids) > 0)) {
			$group_id = $tab_ids[0];
		}

		if (read_config_option('weathermap_pagestyle') == 0) {
			weathermap_thumbview($group_id);
		}

		if (read_config_option('weathermap_pagestyle') == 1) {
			weathermap_fullview(false, false, $group_id);
		}

		if (read_config_option('weathermap_pagestyle') == 2) {
			weathermap_fullview(false, true, $group_id);
		}

		weathermap_versionbox();
		bottom_footer();

		break;
}

function weathermap_cycleview() {

}

function weathermap_singleview($mapid) {
	if (api_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		$is_wm_admin = true;
	} else {
		$is_wm_admin = false;
	}

	$outdir  = __DIR__ . '/output/';
	$confdir = __DIR__ . '/configs/';

	$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

	$map = db_fetch_row_prepared("SELECT wm.*
		FROM weathermap_auth AS wa
		INNER JOIN weathermap_maps AS wm
		ON wm.id = wa.mapid
		WHERE active = 'on'
		AND (userid = ? OR userid = 0)
		AND wm.id = ?
		LIMIT 1",
		array($userid, $mapid));

	if (cacti_sizeof($map)) {
		# print do_hook_function ('weathermap_page_top', array($map[0]['id'], $map[0]['titlecache']));

		print do_hook_function('weathermap_page_top', '');

		$htmlfile = $outdir . $map['filehash'] . '.html';
		$maptitle = $map['titlecache'];

		if ($maptitle == '') {
			$maptitle = __esc('Map for config file: %s', $map['configfile']);
		}

		weathermap_mapselector($mapid);

		if ($is_wm_admin) {
			$maptitle .= '<span> [ ';
			$maptitle .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin.php">' . __esc('Return to Main Page', 'weathermap') . '</a> | ';
			$maptitle .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $mapid . '">' . __esc('Map Settings', 'weathermap') . '</a> | ';
			$maptitle .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $mapid . '">' . __esc('Map Permissions', 'weathermap') . '</a> | ';
			$maptitle .= "<a class='editMap linkOverDark' href='" . html_escape('weathermap-cacti-plugin-editor.php?action=nothing&mapname=' . $map['configfile']) . "'>" . __esc('Edit Map', 'weathermaps') . "</a>";
			$maptitle .= ' ] </span>';
		} else {
			$maptitle .= '<span> [ ';
			$maptitle .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin.php">' . __esc('Return to Main Page', 'weathermap') . '</a>';
			$maptitle .= ' ] </span>';
		}

		print '<div class="cactiTable">';
		print '<div class="cactiTableTitle">' . $maptitle . '</div>';
		print '<div class="cactiTableButton"></div>';
		print '</div>';

		print '<table class="cactiTable">';
		print '<tr><td>';

		if (file_exists($htmlfile)) {
			print '<div class="fixscroll" style="overflow:auto">';

			include($htmlfile);

			print '</div>';
		} else {
			print '<div align="center" style="padding:20px"><em>' . __('This map hasn\'t been created yet.', 'weathermap');

			global $config;

			if (!api_plugin_user_realm_auth('weathermap-cacti-plugin.php')) {
				print ' (If this message stays here for more than one poller cycle, then check your cacti.log file for errors!)';
			}

			print '</em></div>';
		}

		print '</td></tr>';
		print '</table>';

		?>
		<script type='text/javascript'>
		$(function() {
			$('.editMap').click(function(event) {
				event.preventDefault();
				document.location = $(this).attr('href');
			});
		});
		</script>
		<?php
	}
}

function weathermap_show_manage_tab() {
	global $config;

	if (!api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		print '<a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php">' . __('Manage Maps', 'weathermap') . '</a>';
	}
}

function weathermap_thumbview($limit_to_group = -1) {
	global $config;

	$total_map_count_SQL = "select count(*) as total from weathermap_maps";
	$total_map_count     = db_fetch_cell($total_map_count_SQL);

	$userid      = (isset($_SESSION["sess_user_id"]) ? intval($_SESSION["sess_user_id"]) : 1);

	$maplist_sql = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";

	if ($limit_to_group > 0) {
		$maplist_sql .= " weathermap_maps.group_id=" . $limit_to_group . " and ";
	}

	$maplist_sql .= " (userid=" . $userid . " or userid=0) order by sortorder, id";

	$maplist = db_fetch_assoc($maplist_sql);

	// if there's only one map, ignore the thumbnail setting and show it fullsize
	if (cacti_sizeof($maplist) == 1) {
		$pagetitle = __esc('Network Weathermap', 'weathermap');

		weathermap_fullview(false, false, $limit_to_group);
	} else {
		$pagetitle = __('Network Weathermaps [ %sAutomatically Cycle%s ]', '<a class="pic linkOverDark" style="text-decoration:none" href="' . html_escape($config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle') . '">', '</a>', 'weathermap');

		?>
		<div class="cactiTable">
			<div class="cactiTableTitle"><?php print $pagetitle;?></div>
			<div class="cactiTableButton"></div>
		</div>
		<?php

		$showlivelinks = intval(read_config_option('weathermap_live_view'));

		weathermap_tabs($limit_to_group);

		$i = 0;

		if (cacti_sizeof($maplist)) {
			$outdir  = __DIR__ . '/output/';
			$confdir = __DIR__ . '/configs/';

			$imageformat = strtolower(read_config_option('weathermap_output_format'));

			print '<table class="cactiTable">';
			print '<tr><td class="wm_gallery">';

			foreach ($maplist as $map) {
				$i++;

				$imgsize = '';

				# $thumbfile = $outdir."weathermap_thumb_".$map['id'].".".$imageformat;
				# $thumburl = "output/weathermap_thumb_".$map['id'].".".$imageformat."?time=".time();

				$thumbfile = $outdir . $map['filehash'] . '.thumb.' . $imageformat;
				$thumburl  = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewthumb&id=' . $map['filehash'] . '&time=' . time();
				if ($map['thumb_width'] > 0) {
					$imgsize = ' WIDTH="' . $map['thumb_width'] . '" HEIGHT="' . $map['thumb_height'] . '" ';
				}

				$maptitle = $map['titlecache'];

				if ($maptitle == '') {
					$maptitle = __esc('Map for config file: %s', $map['configfile'], 'weathermap');
				}

				print '<div class="cactiTable" style="margin-right:2px;float:left;max-width:' . $map['thumb_width'] . 'px">';

				if (file_exists($thumbfile)) {
					print '<div class="tableHeader"><div class="textSubHeaderDark" style="padding:3px 0px 0px 5px">' . html_escape($maptitle) . '</div></div><div><a href=' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&id=' . $map['filehash'] . '><img class="wm_thumb" ' . $imgsize . 'src="' . $thumburl . '" alt="" hspace="5" vspace="5" style="margin:0px" title="' . html_escape($maptitle) . '"/></a></div>';
				} else {
					print __('(thumbnail for map not created yet)', 'weathermap');
				}

				if ($showlivelinks == 1) {
					print '<a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=liveview&id=' . $map['filehash'] . '">' . __('(Live View)', 'weathermap') . '</a>';
				}

				print '</div> ';
			}

			print '</td></tr>';
			print '</table>';
		} else {
			print '<div align="center" style="padding:20px"><em>' . __('You Have No Maps', 'weathermap') . '</em>';

			if ($total_map_count == 0) {
				print '<p>' . __('To add a map to the schedule, go to the %s Manage...Weathermaps page %s and add one.', '<a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php">', '</a>', 'weathermap') . '</p>';
			}

			print '</div>';
		}
	}
}

function weathermap_fullview($cycle = false, $firstonly = false, $limit_to_group = -1, $fullscreen = 0) {
	global $config;

	$_SESSION['custom'] = false;

	$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

	$maplist_sql = "SELECT DISTINCT wm.*
		FROM weathermap_auth AS wa
		INNER JOIN weathermap_maps AS wm
		ON wm.id = wa.mapid
		WHERE active = 'on' AND ";

	if ($limit_to_group > 0) {
		$maplist_sql .= ' wm.group_id = ? AND ';
		$maplist_params[] = $limit_to_group;
	}

	$maplist_sql .= ' (userid = ? OR userid = 0) ORDER BY sortorder, id';
	$maplist_params[] = $userid;

	if ($firstonly) {
		$maplist_sql .= ' LIMIT 1';
	}

	$maplist = db_fetch_assoc_prepared($maplist_sql, $maplist_params);

	if (cacti_sizeof($maplist) == 1) {
		$pagetitle = __('Network Weathermap', 'weathermap');
	} else {
		$pagetitle = __('Network Weathermaps', 'weathermap');
	}

	$class = '';
	if ($cycle) {
		$class = 'inplace';
	}

	if ($fullscreen) {
		$class = 'fullscreen';
	}

	if ($cycle) {
		if ($fullscreen) {
			print '<script src="' . $config['url_path'] . 'include/js/jquery.js"></script>';
		}

		print '<script src="' . $config['url_path'] . 'plugins/weathermap/js/idle-timer.min.js"></script>';

		if ($limit_to_group > 0) {
			$html = __('Showing %s1%s of %s1%s. Cycling all available maps in this group.', '<span id="wm_current_map">', '</span>', '<span id="wm_total_map">', '</span>', 'weathermaps');
		} else {
			$html = __('Showing %s1%s of %s1%s. Cycling all available maps.', '<span id="wm_current_map">', '</span>', '<span id="wm_total_map">', '</span>', 'weathermaps');
		}

		if ($fullscreen == 0) {
			$pagetitle .= ' <span class="linkOverDark"> [ ' . "
				<a id='cycle_stop' class='pic fas fa-stop' style='font-size:11px' href='?action='></a>
				<a id='cycle_prev' class='fas fa-backward' style='font-size:11px' href='#'></a>
				<a id='cycle_pause' class='fas fa-pause' style='font-size:11px' href='#'></a>
				<a id='cycle_next' class='fas fa-forward' style='font-size:11px' href='#'></a>
				<a target='_new' class='fas fa-expand-arrows-alt' style='font-size:11px' id='cycle_fullscreen' href='" . $config['url_path'] . "plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&fullscreen=1&group=" . $limit_to_group . "'></a> ]
				[ " . $html . ' ] </span>';
			?>
			<div class="cactiTable">
				<div class="cactiTableTitle"><?php print $pagetitle;?></div>
				<div class="cactiTableButton"></div>
			</div>
			<?php
		} else {
			?>
			<div id='wmcyclecontrolbox' class='<?php print $class ?>'>
				<div id='wm_progress'></div>
				<div id='wm_cyclecontrols'>
					<a id='cycle_stop' class='fas fa-stop' href='?action='></a>
					<a id='cycle_prev' class='fas fa-backward' href='#'></a>
	                <a id='cycle_pause' class='fas fa-pause' href='#'></a>
              		 	<a id='cycle_next' class='fas fa-forward' href='#'></a>
      		         	<a target='_new' class='fas fa-expand-arrows-alt' id='cycle_fullscreen' href='<?php print $config['url_path']; ?>plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&fullscreen=1&group=<?php print $limit_to_group; ?>'></a>
					<?php print $html;?>
				</div>
			</div>
			<?php
		}
	}

	// only draw the whole screen if we're not cycling, or we're cycling without fullscreen mode
	//if ($cycle == false || $fullscreen == 0) {
	if (1 == 0) {
		print '<table class="cactiTable">';

		?>
		<tr class='even'>
			<td>
				<table class='filterTable'>
					<tr>
						<td class='textHeader nowrap'> <?php print $pagetitle; ?> </td>
						<td align='right'>
							<?php if (!$cycle) { ?>
								(automatically cycle between full-size maps (<?php

								if ($limit_to_group > 0) {
									print '<a href = "' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&group=' . intval($limit_to_group) . '">within this group</a>, or ';
								}
								print ' <a href = "' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle">all maps</a>';
								?>)
							<?php } ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
		print '</table>';

		weathermap_tabs($limit_to_group);
	}

	$i = 0;

	if (cacti_sizeof($maplist)) {
		print "<div class='all_map_holder $class'>";

		$outdir  = __DIR__ . '/output/';
		$confdir = __DIR__ . '/configs/';

		foreach ($maplist as $map) {
			$i++;

			$htmlfile = $outdir . $map['filehash'] . '.html';
			$maptitle = $map['titlecache'];

			if ($maptitle == '') {
				$maptitle = __esc('Map for config file: %s', $map['configfile'], 'weathermap');
			}

			print '<div class="weathermapholder" id="mapholder_' . $map['filehash'] . '">';

			if ($cycle == false || $fullscreen == 0) {
				print '<table class="cactiTable">';

				?>
				<tr class='tableHeader'>
					<td class='left'>
						<a name='map_<?php print $map['filehash']; ?>'></a>
						<?php print $maptitle; ?>
					</td>
				</tr>
				<tr>
					<td>
				<?php
			}

			if (file_exists($htmlfile)) {
				print '<div class="fixscroll" style="overflow:auto;padding-top:5px">';

				include($htmlfile);

				print '</div>';
			} else {
				print '<div align="center" style="padding:20px"><em>' . __('This map hasn\'t been created yet.', 'weathermap') . '</em></div>';
			}

			if ($cycle == false || $fullscreen == 0) {
				print '</td></tr>';
				print '</table>';
			}

			print '</div>';
		}

		print '</div>';

		if ($cycle) {
			$refreshtime  = read_config_option('weathermap_cycle_refresh');
			$poller_cycle = read_config_option('poller_interval');

			?>
			<script type='text/javascript' src='<?php print $config['url_path']; ?>plugins/weathermap/js/map-cycle.js'></script>
			<script type='text/javascript'>
			$(function() {
				WMcycler.start({
					fullscreen: <?php print($fullscreen ? '1' : '0'); ?>,
					poller_cycle: <?php print $poller_cycle * 1000; ?>,
					period: <?php print $refreshtime * 1000; ?>});
				});
			</script>
			<?php
		}
	} else {
		print '<div align="center" style="padding:20px"><em>' . __('You Have No Maps', 'weathermap') . '</em></div>';
	}
}

function weathermap_translate_id($idname) {
	$map = db_fetch_cell_prepared('SELECT id
		FROM weathermap_maps
		WHERE configfile = ?
		OR filehash = ?
		LIMIT 1',
		array($idname, $idname));

	return $map;
}

function weathermap_versionbox() {
	global $config, $showversionbox;

	$weathermap_version = plugin_weathermap_numeric_version();

	if ($showversionbox) {
		$pagefoot = __('Powered by %s PHP Weathermap Version %s %s', '<a href="http://www.network-weathermap.com/?v=' . $weathermap_version . '">', $weathermap_version, '</a>', 'weathermap');

		if (api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
			$pagefoot .= ' --- <a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php" title="' . __esc('Go to the map management page', 'weathermap') . '">' . __('Weathermap Management', 'weathermap') . '</a>';
			$pagefoot .= ' | <a target="_blank" href="docs/">' . __('Local Documentation', 'weathermap') . '</a>';
			$pagefoot .= ' | <a target="_blank" href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-editor.php">' . __('Editor', 'weathermap') . '</a>';
		}

		print '<br/><table width="100%" style="background-color: #f5f5f5; border: 1px solid #bbbbbb;" align="center" cellpadding="1">';

		?>
		<tr class='even'>
			<td>
				<table class='filterTable'>
					<tr>
						<td class='textHeader' nowrap> <?php print $pagefoot; ?> </td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
		print '</table>';
	}
}

function readfile_chunked($filename) {
	$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
	$buffer    = '';
	$cnt       = 0;

	$handle = fopen($filename, 'rb');

	if ($handle === false) {
		return false;
	}

	while (!feof($handle)) {
		$buffer = fread($handle, $chunksize);

		print $buffer;
	}

	$status = fclose($handle);

	return $status;
}

function weathermap_footer_links() {
	$weathermap_version = plugin_weathermap_numeric_version();

	print '<br />';

	html_start_box("<center><a target=\"_blank\" class=\"linkOverDark\" href=\"docs/\">Local Documentation</a> -- <a target=\"_blank\" class=\"linkOverDark\" href=\"http://www.network-weathermap.com/\">Weathermap Website</a> -- <a target=\"_target\" class=\"linkOverDark\" href=\"weathermap-cacti-plugin-editor.php?plug=1\">Weathermap Editor</a> -- This is version $weathermap_version</center>", '100%', '', '3', 'center', '');
	html_end_box();
}

function weathermap_mapselector($current_id = 0) {
	$show_selector = intval(read_config_option('weathermap_map_selector'));

	if ($show_selector == 0) {
		return false;
	}

	$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

	$maps = db_fetch_assoc_prepared("SELECT DISTINCT wm.*, wmg.name, wmg.sortorder AS gsort
		FROM weathermap_maps AS wm
		INNER JOIN weathermap_auth AS wa
		ON wm.id = wa.mapid
		INNER JOIN weathermap_groups AS wmg
		ON wm.group_id = wmg.id
		WHERE active = 'on'
		AND (userid = ? OR userid = 0)
		ORDER BY gsort, sortorder",
		array($userid));

	if (cacti_sizeof($maps) > 1) {
		/* include graph view filter selector */

		html_start_box(__('Weathermap Filter', 'weathermap'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td class='noprint'>
				<form name='weathermap_select'>
					<input name='action' value='viewmap' type='hidden'>
					<table class='filterTable'>
						<tr class='noprint'>
							<td>
								<?php print __('Map to View', 'weathermap');?>
							</td>
							<td>
								<select id='id'>
									<?php

									$ngroups   = 0;
									$lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
									foreach ($maps as $map) {
										if ($current_id == $map['id']) {
											$nullhash = $map['filehash'];
										}

										if ($map['name'] != $lastgroup) {
											$ngroups++;

											$lastgroup = $map['name'];
										}
									}

									$lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";

									foreach ($maps as $map) {
										if ($ngroups > 1 && $map['name'] != $lastgroup) {
											print "<option disabled style='font-weight: bold; font-style: italic' value='$nullhash'>" . html_escape($map['name']) . '</option>';
											$lastgroup = $map['name'];
										}

										print '<option ';

										if ($current_id == $map['id']) {
											print 'selected ';
										}

										print 'value="' . $map['filehash'] . '">';

										print html_escape($map['titlecache']) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
					</table>
					<script type='text/javascript'>
					function applyFilter() {
						var strURL = urlPath + 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&header=false';
						strURL += '&id=' + $('#id').val();

						loadPageNoHeader(strURL);
					}

					$(function() {
						$('#id').change(function() {
							applyFilter();
						});
					});
					</script>
				</td>
			</form>
		</tr>
		<?php

		html_end_box();
	}
}

function weathermap_get_valid_tabs() {
	$tabs = array();

	$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

	$maps = db_fetch_assoc_prepared("SELECT wm.*, wmg.name AS group_name
		FROM weathermap_auth AS wa
		INNER JOIN weathermap_maps AS wm
		ON wm.id = wa.mapid
		INNER JOIN weathermap_groups AS wmg
		ON wmg.id = wm.group_id
		WHERE active = 'on'
		AND (userid = ? OR userid = 0)
		ORDER BY wmg.sortorder",
		array($userid));

	foreach ($maps as $map) {
		$tabs[$map['group_id']] = $map['group_name'];
	}

	return $tabs;
}

function weathermap_tabs($current_tab) {
	global $config;

	// $current_tab=2;

	$tabs = weathermap_get_valid_tabs();

	if (cacti_sizeof($tabs) > 1) {
		/* draw the categories tabs on the top of the page */
		print '<div>' . PHP_EOL;
		print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>" . PHP_EOL;

		if (cacti_sizeof($tabs) > 0) {
			$show_all = intval(read_config_option('weathermap_all_tab'));

			if ($show_all == 1) {
				$tabs['-2'] = __('All Maps', 'weathermaps');
			}

			foreach (array_keys($tabs) as $tab_short_name) {
				print "<li class='subTab'><a " . (($tab_short_name == $current_tab) ? "class='selected pic'" : "class='pic'") . " href='" . html_escape($config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?group_id=' . $tab_short_name) . "'>" . $tabs[$tab_short_name] . '</a></li>' . PHP_EOL;
			}
		}

		print '</ul></nav></div>' . PHP_EOL;
		print '</div>' . PHP_EOL;

		return true;
	} else {
		return false;
	}
}

