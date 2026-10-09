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

$guest_account  = true;

include_once('../../include/auth.php');

global $config;
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

			$userid = $_SESSION['sess_user_id'];

			if (is_weathermap_allowed($id, $userid)) {
				$map = db_fetch_row_prepared("SELECT wm.*
					FROM weathermap_maps AS wm
					WHERE active = 'on'
					AND wm.id = ?",
					[$id]);

				if (is_array($map) && cacti_sizeof($map)) {
					$imagefile = __DIR__ . '/output/' . $map['filehash'] . '.' . $imageformat;

					if (get_request_var('action') == 'viewthumb') {
						$imagefile = __DIR__ . '/output/' . $map['filehash'] . '.thumb.' . $imageformat;
					}

					$orig_cwd = (string) getcwd();
					chdir(__DIR__);

					$mime_map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
					header('Content-type: ' . ($mime_map[$imageformat] ?? 'image/png'));

					// readfile_chunked($imagefile);
					readfile($imagefile);

					chdir($orig_cwd);
				} else {
					// no permission to view this map
				}
			}
		}

		break;
	case 'liveviewimage':
		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			$userid = $_SESSION['sess_user_id'];

			if (is_weathermap_allowed($id, $userid)) {
				$map = db_fetch_row_prepared("SELECT wm.*
					FROM weathermap_maps AS wm
					WHERE active = 'on'
					AND wm.id = ?",
					[$id]);

				if (is_array($map) && cacti_sizeof($map)) {
					$mapfile  = __DIR__ . '/configs/' . $map['configfile'];
					$orig_cwd = (string) getcwd();

					chdir(__DIR__);

					header('Content-type: image/png');

					$map = new WeatherMap;

					$map->context = '';
					$map->rrdtool = read_config_option('path_rrdtool');

					$map->ReadConfig($mapfile);
					$map->ReadData();
					$map->DrawMap('', '', 250, true, false);

					chdir($orig_cwd);
				}
			}
		}

		break;
	case 'liveview':
		top_graph_header();

		print get_md5_include_css('plugins/weathermap/css/weathermap.css');
		print get_md5_include_js('plugins/weathermap/js/weathermap.js');

		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			$userid = $_SESSION['sess_user_id'];

			if (is_weathermap_allowed($id, $userid)) {
				$map = db_fetch_row_prepared("SELECT wm.*
					FROM weathermap_maps AS wm
					WHERE active = 'on'
					AND wm.id = ?",
					[$id]);

				if (is_array($map) && cacti_sizeof($map)) {
					$maptitle = $map['titlecache'];

					print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";

					?>
					<tr class='even noprint'>
						<td>
							<table class='filterTable'>
								<tr>
									<td class='textHeader nowrap'><?php print weathermap_map_title_controls(html_escape($maptitle), $map); ?></td>
								</tr>
							</table>
						</td>
					</tr>
					<?php
					print '<tr><td>';

					// print "Generating map $id here now from ".$map[0]['configfile'];

					$confdir = __DIR__ . '/configs/';

					// everything else in this file is inside this else
					$mapname = $map['configfile'];
					$mapfile = $confdir . '/' . $mapname;

					$orig_cwd = (string) getcwd();
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

		$userid  = $_SESSION['sess_user_id'];

		$maplist = get_allowed_weathermaps($userid);

		if (cacti_sizeof($maplist)) {
			foreach ($maplist as $map) {
				$thumburl = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewthumb&id=' . $map['filehash'] . '&time=' . time();
				$bigurl   = $config['url_path'] . 'weathermap-cacti-plugin.php?action=viewimage&id=' . $map['filehash'] . '&time=' . time();
				$linkurl  = $config['url_path'] . 'weathermap-cacti-plugin.php?action=viewmap&id=' . $map['filehash'];
				$maptitle = $map['titlecache'];
				$guid     = $map['filehash'];

				if ($maptitle == '') {
					$maptitle = __esc('Map for config file: %s', $map['configfile']);
				}

				print '<item>';

				printf('<title>%s</title>', $maptitle);

				printf('<description>' . __('Network Weathermap named "%s"', $maptitle) . '</description>
					<link>%s</link>
					<media:thumbnail url="%s"/>
					<media:content url="%s"/>
					<guid isPermaLink="false">%s%s</guid>
					</item>',
					$linkurl, $thumburl, $bigurl, $config['url_path'], $guid);

				print PHP_EOL;
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
			print get_md5_include_css('plugins/weathermap/css/weathermap.css');
			print '<link rel="stylesheet" type="text/css" media="screen" href="' . $config['url_path'] . 'include/fa/css/all.css' . '"/>';
			print get_md5_include_js('include/js/jquery.js');
			print '</head><body id="wm_fullscreen">';
		} else {
			top_graph_header();
		}

		print get_md5_include_css('plugins/weathermap/css/weathermap.css');
		print get_md5_include_js('plugins/weathermap/js/weathermap.js');

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

		print get_md5_include_css('plugins/weathermap/css/weathermap.css');
		print get_md5_include_js('plugins/weathermap/js/weathermap.js');

		$id = -1;

		if (isset_request_var('id') && (!is_numeric(get_nfilter_request_var('id')) || strlen(get_request_var('id')) == 20)) {
			$id = weathermap_translate_id(get_nfilter_request_var('id'));
		}

		if ($id >= 0) {
			weathermap_singleview((int) $id);
		}

		weathermap_versionbox();

		bottom_footer();

		break;
	default:
		top_graph_header();

		print get_md5_include_css('plugins/weathermap/css/weathermap.css');
		print get_md5_include_js('plugins/weathermap/js/weathermap.js');

		$group_id = -1;

		if (isset_request_var('group_id')) {
			$group_id                  = get_filter_request_var('group_id');
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

/**
 * Renders the single-map view: the map selector, a titled box with
 * quick links (settings/permissions/edit for admins, or just a return
 * link for regular users), and the map's pre-generated HTML output (or
 * a 'not created yet' notice), for a user authorized to view it.
 * Called from this script's main request-dispatch switch when
 * action=viewmap.
 *
 * @param int $mapid The weathermap_maps id to display.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build links and check permissions.
 */
function weathermap_singleview($mapid) {
	global $config;

	if (api_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		$is_wm_admin = true;
	} else {
		$is_wm_admin = false;
	}

	$outdir  = __DIR__ . '/output/';
	$confdir = __DIR__ . '/configs/';

	$userid = $_SESSION['sess_user_id'];

	if (is_weathermap_allowed($mapid, $userid)) {
		$map = db_fetch_row_prepared("SELECT wm.*
			FROM weathermap_maps AS wm
			WHERE active = 'on'
			AND wm.id = ?",
			[$mapid]);

		if (is_array($map) && cacti_sizeof($map)) {
			// print do_hook_function ('weathermap_page_top', [$map[0]['id'], $map[0]['titlecache']]);

			print do_hook_function('weathermap_page_top', '');

			$htmlfile = $outdir . $map['filehash'] . '.html';
			$maptitle = html_escape($map['titlecache']);

			if ($maptitle == '') {
				$maptitle = __esc('Map for config file: %s', $map['configfile']);
			}

			weathermap_mapselector($mapid);

			print '<table class="cactiTable wm-map-title"><tr class="tableHeader"><td class="textHeaderDark">' . weathermap_map_title_controls($maptitle, $map) . '</td></tr></table>';

			print '<table class="cactiTable">';
			print '<tr><td>';

			if (file_exists($htmlfile)) {
				print weathermap_map_graph_links((string) file_get_contents($htmlfile), $map['filehash']);
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

			<?php
		}
	}
}

/**
 * Prints a 'Manage Maps' link for users who are NOT already authorized
 * for the management page (used as a secondary/limited-access
 * shortcut). Called from view rendering to surface a link to the
 * management tool for non-admin contexts.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build the link URL.
 */
function weathermap_show_manage_tab() {
	global $config;

	if (!api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		print '<a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php">' . __('Manage Maps', 'weathermap') . '</a>';
	}
}

/**
 * Renders the main Weathermap gallery view: a grid of thumbnails for
 * every map the current user is permitted to see (optionally restricted
 * to a single group), each linking to its full-size/live view, falling
 * back to displaying the single map in full size when the user only
 * has access to exactly one. Called from this script's main
 * request-dispatch switch as the default view.
 *
 * @param int $limit_to_group Optional weathermap_groups id to restrict
 *                           the displayed maps to; -1 shows all
 *                           allowed maps.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build links and image paths.
 */
function weathermap_thumbview($limit_to_group = -1) {
	global $config;

	$total_map_count = db_fetch_cell("SELECT COUNT(*) AS total
		FROM weathermap_maps
		WHERE active = 'on'");

	$userid = $_SESSION['sess_user_id'];

	$allmaps = get_allowed_weathermaps($userid);
	$groups  = [];

	if (cacti_sizeof($allmaps)) {
		foreach ($allmaps as $m) {
			$groups[$m['group_id']] = true;
		}
	}

	if ($limit_to_group > 0) {
		$maplist = get_allowed_weathermaps($userid, $limit_to_group);
	} else {
		$maplist = get_allowed_weathermaps($userid);
	}

	// if there's only one map, ignore the thumbnail setting and show it fullsize
	if (cacti_sizeof($groups) == 1 && cacti_sizeof($maplist) == 1) {
		$pagetitle = __esc('Network Weathermap', 'weathermap');

		weathermap_fullview(false, false, $limit_to_group);
	} else {
		$pagetitle = __('Network Weathermaps [ %sAutomatically Cycle%s ]', '<a class="pic linkOverDark" style="text-decoration:none" href="' . html_escape($config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle') . '">', '</a>', 'weathermap');

		?>
		<div class="cactiTable">
			<div class="cactiTableTitleRow"><?php print $pagetitle; ?></div>
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

				// $thumbfile = $outdir."weathermap_thumb_".$map['id'].".".$imageformat;
				// $thumburl = "output/weathermap_thumb_".$map['id'].".".$imageformat."?time=".time();

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

/**
 * Renders a single map (or the first of a cycling set) at full size,
 * optionally auto-cycling through every map the user is permitted to
 * view and/or in fullscreen mode. Called from this script's main
 * request-dispatch switch when action=viewmap/viewmapcycle, and
 * internally from weathermap_thumbview() when the user has access to
 * exactly one map.
 *
 * @param bool|int $cycle          Whether to auto-cycle through all
 *                                allowed maps.
 * @param bool     $firstonly      Whether to display only the first map
 *                                in the allowed list (used for the
 *                                initial cycle frame).
 * @param int      $limit_to_group Optional weathermap_groups id to
 *                                restrict the displayed maps to.
 * @param int      $fullscreen     Whether to render in fullscreen mode
 *                                (chromeless).
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build links and image paths.
 */
function weathermap_fullview($cycle = false, $firstonly = false, $limit_to_group = -1, $fullscreen = 0) {
	global $config;

	$_SESSION['custom'] = false;

	$userid = $_SESSION['sess_user_id'];

	if ($limit_to_group > 0) {
		$maplist = get_allowed_weathermaps($userid, $limit_to_group);
	} else {
		$maplist = get_allowed_weathermaps($userid);
	}

	if ($firstonly && cacti_sizeof($maplist)) {
		$maplist = [$maplist[0]];
	}

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
			print get_md5_include_js('include/js/jquery.js');
		}

		if ($limit_to_group > 0) {
			$html = __('Showing %s %s of %s %s. Cycling all available maps in this group.', '<span id="wm_current_map">', '</span>', '<span id="wm_total_map">', '</span>', 'weathermaps');
		} else {
			$html = __('Showing %s %s of %s %s. Cycling all available maps.', '<span id="wm_current_map">', '</span>', '<span id="wm_total_map">', '</span>', 'weathermaps');
		}

		$controls = weathermap_cycle_controls($fullscreen, $limit_to_group);
		if ($fullscreen == 0) {
			print '<div class="cactiTable"><div class="cactiTableTitleRow wm-cycle-toolbar">' . $pagetitle . ' [ ' . $controls . ' ] [ ' . $html . ' ]</div></div>';
		} else {
			print '<div id="wmcyclecontrolbox" class="fullscreen">' . $controls . '<span class="wm-cycle-status">' . $html . '</span></div>';
		}
	}

	if (cacti_sizeof($maplist)) {
		print "<div class='all_map_holder $class'>";

		$outdir  = __DIR__ . '/output/';
		$confdir = __DIR__ . '/configs/';

		foreach ($maplist as $map) {
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
						<?php print weathermap_map_title_controls(html_escape($maptitle), $map); ?>
					</td>
				</tr>
				<tr>
					<td>
				<?php
			}

			if (file_exists($htmlfile)) {
				print weathermap_map_graph_links((string) file_get_contents($htmlfile), $map['filehash']);
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
			<?php print get_md5_include_js('plugins/weathermap/js/map-cycle.js'); ?>
			<script type='text/javascript' <?php print plugin_weathermap_csp_nonce(); ?>>
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

/**
 * Resolves a map identifier (either its config filename or file hash)
 * to its weathermap_maps id. Called throughout this script wherever a
 * map needs to be looked up by either identifier form.
 *
 * @param string $idname The map's config filename or file hash.
 *
 * @return int|null The matching weathermap_maps id, or null if not
 *                  found.
 */
function weathermap_translate_id($idname) {
	$map = db_fetch_cell_prepared('SELECT id
		FROM weathermap_maps
		WHERE configfile = ?
		OR filehash = ?
		LIMIT 1',
		[$idname, $idname]);

	return $map;
}

/**
 * Renders the page footer's version/attribution box (with quick links
 * to Management/documentation/new-map for admins), when the version box
 * is enabled. Called from view-rendering code at the bottom of the
 * Weathermap viewer pages.
 *
 * @return void
 *
 * @global array $config          Cacti global configuration array;
 *                                used to build links.
 * @global bool  $showversionbox  Whether the version box should be
 *                                rendered at all.
 */
function weathermap_versionbox() {
	global $config, $showversionbox;

	$weathermap_version = plugin_weathermap_numeric_version();

	if ($showversionbox) {
		$pagefoot = __('Powered by %s PHP Weathermap Version %s %s', '<a href="https://github.com/cacti/plugin_weathermap/releases">', $weathermap_version, '</a>', 'weathermap');

		if (api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
			$pagefoot .= ' | <a href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php" title="' . __esc('Go to the Map Management page', 'weathermap') . '">' . __('Weathermap Management', 'weathermap') . '</a>';
			$pagefoot .= ' | <a target="_blank" href="docs/">' . __('Local Documentation', 'weathermap') . '</a>';
			$pagefoot .= ' | <a class="pic" href="' . $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php?action=addmap_picker">' . __('New Map', 'weathermap') . '</a>';
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

/**
 * Streams a file's contents to output in 1MB chunks, avoiding loading
 * the entire file into memory at once. Called when serving a large map
 * image/output file for direct download/display.
 *
 * @param string $filename The path to the file to stream.
 *
 * @return bool True on success, false if the file couldn't be opened.
 */
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

/**
 * Renders a map-selection dropdown (when enabled via the
 * 'weathermap_map_selector' setting) letting the user quickly jump
 * between maps they're permitted to view. Called from
 * weathermap_singleview() before rendering the selected map.
 *
 * @param int $current_id The currently displayed map's id, to
 *                        pre-select in the dropdown.
 *
 * @return void
 */
function weathermap_mapselector($current_id = 0) {
	$show_selector = intval(read_config_option('weathermap_map_selector'));

	if ($show_selector == 0) {
		return;
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
		ORDER BY wmg.sortorder, wm.sortorder",
		[$userid]);

	if (cacti_sizeof($maps) > 1) {
		// include graph view filter selector

		html_start_box(__('Weathermap Filter', 'weathermap'), '100%', false, 3, 'center', '');
		?>
		<tr class='even noprint'>
			<td class='noprint'>
				<form name='weathermap_select'>
					<input name='action' value='viewmap' type='hidden'>
					<table class='filterTable'>
						<tr class='noprint'>
							<td>
								<?php print __('Map to View', 'weathermap'); ?>
							</td>
							<td>
								<select id='id'>
									<?php

									$ngroups   = 0;
									$nullhash  = '';
									$lastgroup = '------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd';

									foreach ($maps as $map) {
										if ($current_id == $map['id']) {
											$nullhash = $map['filehash'];
										}

										if ($map['name'] != $lastgroup) {
											$ngroups++;

											$lastgroup = $map['name'];
										}
									}

									$lastgroup = '------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd';

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
					<script type='text/javascript' <?php print plugin_weathermap_csp_nonce(); ?>>
					function applyFilter() {
						var strURL = urlPath + 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&header=false';
						strURL += '&id=' + $('#id').val();

						loadPageNoHeader(strURL);
					}

					$(function() {
						$('#id').on('change', function() {
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

/**
 * Builds the map group => group name list to render as tabs, based on
 * which groups contain maps the current session user is authorized to
 * view. Called from weathermap_tabs() to determine which group tabs to
 * display.
 *
 * @return array Map of group_id => group name, in display order.
 */
function weathermap_get_valid_tabs() {
	$tabs = [];

	$userid = (isset($_SESSION['sess_user_id']) ? intval($_SESSION['sess_user_id']) : 1);

	$maps = db_fetch_assoc_prepared("SELECT wm.*, wmg.name AS group_name
		FROM weathermap_auth AS wa
		INNER JOIN weathermap_maps AS wm
		ON wm.id = wa.mapid
		INNER JOIN weathermap_groups AS wmg
		ON wmg.id = wm.group_id
		WHERE active = 'on'
		AND (userid = ? OR userid = 0)
		ORDER BY wmg.sortorder, wm.sortorder",
		[$userid]);

	foreach ($maps as $map) {
		$tabs[$map['group_id']] = $map['group_name'];
	}

	return $tabs;
}

/**
 * Renders the map-group tabbed navigation bar (when the user has access
 * to more than one group), highlighting the currently selected group
 * tab. Called from weathermap_thumbview() before rendering the map
 * gallery.
 *
 * @param int $current_tab The currently selected group_id tab.
 *
 * @return bool True if more than one group tab was rendered, false
 *              otherwise (nothing rendered).
 *
 * @global array $config Cacti global configuration array; used to
 *                       build tab URLs.
 */
function weathermap_tabs($current_tab) {
	global $config;

	// $current_tab=2;

	$tabs = weathermap_get_valid_tabs();

	if (cacti_sizeof($tabs) > 1) {
		// draw the categories tabs on the top of the page
		print '<div>' . PHP_EOL;
		print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>" . PHP_EOL;

		$show_all = intval(read_config_option('weathermap_all_tab'));

		if ($show_all == 1) {
			$tabs['-2'] = __('All Maps', 'weathermaps');
		}

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a " . (($tab_short_name == $current_tab) ? "class='selected pic'" : "class='pic'") . " href='" . html_escape($config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?group_id=' . $tab_short_name) . "'>" . $tabs[$tab_short_name] . '</a></li>' . PHP_EOL;
		}

		print '</ul></nav></div>' . PHP_EOL;
		print '</div>' . PHP_EOL;

		return true;
	} else {
		return false;
	}
}
/**
 * Build the same escaped map heading and authorized shortcuts in each view.
 *
 * @param string $maptitle HTML-escaped heading.
 * @param array<string, mixed>|bool $map Map metadata, or false when unavailable.
 *
 * @return string The map heading with management shortcuts when authorized.
 */
function weathermap_map_title_controls($maptitle, $map) {
	$heading = '<strong>' . $maptitle . '</strong>';

	if (!is_array($map) || !isset($map['id'], $map['configfile']) || !is_string($map['configfile']) || !api_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		return $heading;
	}
	$id = (int) $map['id'];
	$heading .= ' [ <a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php">' . __esc('Manage Weathermaps', 'weathermap') . '</a> | ';
	$heading .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $id . '">' . __esc('Map Settings', 'weathermap') . '</a> | ';
	$heading .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $id . '">' . __esc('Map Permissions', 'weathermap') . '</a> | ';
	$heading .= '<a class="wm-edit-map" href="' . html_escape('weathermap-cacti-plugin-editor.php?action=nothing&mapname=' . rawurlencode($map['configfile'])) . '">' . __esc('Edit Map', 'weathermap') . '</a> ]';

	return $heading;
}


/**
 * Render translated, keyboard-accessible cycle controls.
 *
 * @param int|string $fullscreen Whether Cacti chrome is hidden.
 * @param int|string $group_id   Group filter to retain when toggling full screen.
 *
 * @return string
 */
function weathermap_cycle_controls($fullscreen, $group_id) {
	global $config;
	$controls = '';
	$actions  = [
		'cycle_stop'  => ['fa-stop', __('Stop cycling', 'weathermap'), '?action='],
		'cycle_prev'  => ['fa-backward', __('Previous', 'weathermap')],
		'cycle_pause' => ['fa-pause', __('Pause / resume', 'weathermap')],
		'cycle_next'  => ['fa-forward', __('Next', 'weathermap')]
	];

	foreach ($actions as $id => $action) {
		$attributes = ' id="' . $id . '" class="wm-cycle-control fas ' . $action[0] . '" title="' . html_escape($action[1]) . '" aria-label="' . html_escape($action[1]) . '"';

		if ($id === 'cycle_stop') {
			$controls .= '<a' . $attributes . ' href="' . html_escape($action[2]) . '"></a>';
		} else {
			$controls .= '<button type="button"' . $attributes . ($id === 'cycle_pause' ? ' aria-pressed="false"' : '') . '></button>';
		}
	}
	$label = $fullscreen ? __('Exit full screen', 'weathermap') : __('Full screen', 'weathermap');
	$url   = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&fullscreen=' . ($fullscreen ? '0' : '1') . '&group=' . $group_id;
	$controls .= '<a id="' . ($fullscreen ? 'cycle_exit_fullscreen' : 'cycle_fullscreen') . '" class="wm-cycle-control wm-fullscreen-link fas ' . ($fullscreen ? 'fa-compress-arrows-alt' : 'fa-expand-arrows-alt') . '" href="' . html_escape($url) . '" title="' . html_escape($label) . '" aria-label="' . html_escape($label) . '">' . ($fullscreen ? '<span class="wm-cycle-exit-label">' . html_escape($label) . '</span>' : '') . '</a>';
	$controls .= '<span id="wm_countdown" data-paused-label="' . __esc('Paused', 'weathermap') . '" data-next-label="' . __esc('Next map in %ss', 'weathermap') . '"></span><span class="wm-progress-track"><span id="wm_progress"></span></span>';

	return $controls;
}
