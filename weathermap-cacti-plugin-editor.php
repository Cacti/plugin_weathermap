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

chdir(__DIR__);
require_once('../../include/auth.php');
require_once('lib/editor.inc.php');
require_once('lib/Weathermap.class.php');
require_once('lib/geometry.php');
require_once('lib/WMPoint.class.php');
require_once('lib/WMVector.class.php');
require_once('lib/WMLine.class.php');

// If we're embedded in the Cacti UI (included from weathermap-cacti-plugin-editor.php), then authentication has happened. Enable the editor.
$editor_name  = 'weathermap-cacti-plugin-editor.php';
$cacti_base   = $config['base_path'];
$cacti_url    = $config['url_path'];

// sensible defaults
$mapdir      = 'configs';

// these are all set via the Editor Settings dialog, in the editor, now.
$use_overlay = false; // set to true to enable experimental overlay showing VIAs
$use_relative_overlay = false; // set to true to enable experimental overlay showing relative-positioning
$grid_snap_value = 0; // set non-zero to snap to a grid of that spacing

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


if (!is_writable($mapdir)) {
	cacti_log("FATAL: The map config directory ($mapdir) is not writable by the web server user. You will not be able to edit any files until this is corrected. [WMEDIT01]", true, 'WEATERMAP');
	exit;
}

$action   = '';
$mapname  = '';
$selected = '';

$newaction = '';
$param     = '';
$param2    = '';
$log       = '';

set_default_action('');

if (isset($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
}

if (isset($_REQUEST['mapname'])) {
	$mapname = $_REQUEST['mapname'];
	$mapname = wm_editor_sanitize_conffile($mapname);
}

if (isset($_REQUEST['selected'])) {
	$selected = wm_editor_sanitize_selected($_REQUEST['selected']);
}

$weathermap_debugging = false;

if ($action == 'graphs') {
	display_graphs();
} elseif ($action == 'datasources') {
	display_datasources();
} elseif ($mapname == '') {
	// this is the file-picker/welcome page
	show_editor_startpage();
} else {
	// everything else in this file is inside this else
	$mapfile = $mapdir . '/' . $mapname;

	wm_debug('==========================================================================================================');
	wm_debug("Starting Edit Run: action is $action on $mapname");
	wm_debug('==========================================================================================================');

	# editor_log("\n\n-----------------------------------------------------------------------------\nNEW REQUEST:\n\n");
	# editor_log(var_log($_REQUEST));

	$map = new WeatherMap;
	$map->context = 'editor';

	switch($action) {
		case 'newmap':
			$map->WriteConfig($mapfile);

			break;
		case 'newmapcopy':
			if (isset($_REQUEST['sourcemap'])) {
				$sourcemapname = $_REQUEST['sourcemap'];
			}

			$sourcemapname = wm_editor_sanitize_conffile($sourcemapname);

			if ($sourcemapname != '') {
				$sourcemap = $mapdir.'/'.$sourcemapname;

				if (file_exists($sourcemap) && is_readable($sourcemap)) {
					$map->ReadConfig($sourcemap);
					$map->WriteConfig($mapfile);
				}
			}

			break;
		case 'font_samples':
			$map->ReadConfig($mapfile);
			ksort($map->fonts);
			header('Content-type: image/png');

			$keyfont = 2;
			$keyheight = imagefontheight($keyfont)+2;

			$sampleheight = 32;
			// $im = imagecreate(250,imagefontheight(5)+5);
			$im = imagecreate(2000,$sampleheight);
			$imkey = imagecreate(2000,$keyheight);

			$white = imagecolorallocate($im,255,255,255);
			$black = imagecolorallocate($im,0,0,0);
			$whitekey = imagecolorallocate($imkey,255,255,255);
			$blackkey = imagecolorallocate($imkey,0,0,0);

			$x = 3;
			#for($i=1; $i< 6; $i++)
			foreach ($map->fonts as $fontnumber => $font) {
				$string = 'Abc123%';
				$keystring = "Font $fontnumber";

				list($width,$height) = $map->myimagestringsize($fontnumber,$string);
				list($kwidth,$kheight) = $map->myimagestringsize($keyfont,$keystring);

				if ($kwidth > $width) {
					$width = $kwidth;
				}

				$y = ($sampleheight/2) + $height/2;
				$map->myimagestring($im, $fontnumber, $x, $y, $string, $black);
				$map->myimagestring($imkey, $keyfont,$x,$keyheight,"Font $fontnumber",$blackkey);

				$x = $x + $width + 6;
			}

			$im2 = imagecreate($x,$sampleheight + $keyheight);
			imagecopy($im2,$im, 0,0, 0,0, $x, $sampleheight);
			imagecopy($im2,$imkey, 0,$sampleheight, 0,0,  $x, $keyheight);
			imagedestroy($im);
			imagepng($im2);
			imagedestroy($im2);

			exit();

			break;
		case 'draw':
			header('Content-type: image/png');
			$map->ReadConfig($mapfile);

			if ($selected != '') {
				if (substr($selected,0,5) == 'NODE:') {
					$nodename = substr($selected,5);
					$map->nodes[$nodename]->selected=1;
				}

				if (substr($selected,0,5) == 'LINK:') {
					$linkname = substr($selected,5);
					$map->links[$linkname]->selected=1;
				}
			}

			$map->sizedebug = true;
			$map->DrawMap('','',250,true,$use_overlay,$use_relative_overlay);
			exit();

			break;
		case 'show_config':
			header('Content-type: text/plain');

			$fd = fopen($mapfile,'r');

			while (!feof($fd)) {
				$buffer = fgets($fd, 4096);
				print $buffer;
			}

			fclose($fd);

			exit();

			break;
		case 'fetch_config':
			$map->ReadConfig($mapfile);

			header('Content-type: text/plain');

			$item_name = $_REQUEST['item_name'];
			$item_type = $_REQUEST['item_type'];

			$ok=false;

			if ($item_type == 'node'){
				if (isset($map->nodes[$item_name])) {
					print $map->nodes[$item_name]->WriteConfig();
					$ok = true;
				}
			}

			if ($item_type == 'link') {
				if (isset($map->links[$item_name])) {
					print $map->links[$item_name]->WriteConfig();
					$ok = true;
				}
			}

			if (!$ok) {
				print "# the request item didn't exist. That's probably a bug.\n";
			}

			exit();

			break;
		case 'set_link_config':
			$map->ReadConfig($mapfile);

			$link_name = $_REQUEST['link_name'];
			$link_config = fix_gpc_string($_REQUEST['item_configtext']);

			if (isset($map->links[$link_name])) {
				$map->links[$link_name]->config_override = $link_config;

			    $map->WriteConfig($mapfile);

			    // now clear and reload the map object, because the in-memory one is out of sync
			    // - we don't know what changes the user made here, so we just have to reload.
			    unset($map);

			    $map = new WeatherMap;
			    $map->context = 'editor';
			    $map->ReadConfig($mapfile);
			}

			break;
		case 'set_node_config':
			$map->ReadConfig($mapfile);

			$node_name = $_REQUEST['node_name'];
			$node_config = fix_gpc_string($_REQUEST['item_configtext']);

			if (isset($map->nodes[$node_name])) {
				$map->nodes[$node_name]->config_override = $node_config;

				$map->WriteConfig($mapfile);

				// now clear and reload the map object, because the in-memory one is out of sync
				// - we don't know what changes the user made here, so we just have to reload.
				unset($map);

				$map = new WeatherMap;
				$map->context = 'editor';
				$map->ReadConfig($mapfile);
			}

			break;
		case 'set_node_properties':
			$map->ReadConfig($mapfile);

			$node_name = $_REQUEST['node_name'];
			$new_node_name = $_REQUEST['node_new_name'];

			// first check if there's a rename...
			if ($node_name != $new_node_name && strpos($new_node_name, ' ') === false) {
				if (!isset($map->nodes[$new_node_name])) {
					// we need to rename the node first.
					$newnode = $map->nodes[$node_name];
					$newnode->name = $new_node_name;
					$map->nodes[$new_node_name] = $newnode;

					unset($map->nodes[$node_name]);

					// find the references elsewhere to the old node name.
					// First, relatively-positioned NODEs
					foreach ($map->nodes as $node) {
						if ($node->relative_to == $node_name) {
							$map->nodes[$node->name]->relative_to = $new_node_name;
						}
					}

					// Next, LINKs that use this NODE as an end.
					foreach ($map->links as $link) {
						if (isset($link->a)) {
							if ($link->a->name == $node_name) {
								$map->links[$link->name]->a = $newnode;
							}

							if ($link->b->name == $node_name) {
								$map->links[$link->name]->b = $newnode;
							}

							// while we're here, VIAs can also be relative to a NODE,
							// so check if any of those need to change
							if ((count($link->vialist)>0)) {
								$vv=0;

								foreach($link->vialist as $v) {
									if (isset($v[2]) && $v[2] == $node_name) {
										// die PHP4, die!
										$map->links[$link->name]->vialist[$vv][2] = $new_node_name;
									}

									$vv++;
								}
							}
						}
					}
				} else {
					// silently ignore attempts to rename a node to an existing name
					$new_node_name = $node_name;
				}
			}

			// by this point, and renaming has been done, and new_node_name will always be the right name
			$map->nodes[$new_node_name]->label = wm_editor_sanitize_string($_REQUEST['node_label']);
			$map->nodes[$new_node_name]->infourl[IN] = wm_editor_sanitize_string($_REQUEST['node_infourl']);

			$urls = preg_split('/\s+/', $_REQUEST['node_hover'], -1, PREG_SPLIT_NO_EMPTY);

			$map->nodes[$new_node_name]->overliburl[IN] = $urls;
			$map->nodes[$new_node_name]->overliburl[OUT] = $urls;

			$map->nodes[$new_node_name]->x = intval($_REQUEST['node_x']);
			$map->nodes[$new_node_name]->y = intval($_REQUEST['node_y']);

			if ($_REQUEST['node_iconfilename'] == '--NONE--') {
			    $map->nodes[$new_node_name]->iconfile = '';
			} else {
			    // AICONs mess this up, because they're not fully supported by the editor, but it can still break them
			    if ($_REQUEST['node_iconfilename'] != '--AICON--') {
				    $iconfile = stripslashes($_REQUEST['node_iconfilename']);
				    $map->nodes[$new_node_name]->iconfile = $iconfile;
			    }
			}

			$map->WriteConfig($mapfile);

			break;
		case 'set_link_properties':
			$map->ReadConfig($mapfile);

			$link_name = $_REQUEST['link_name'];

			if (strpos($link_name,' ') === false) {
			    $map->links[$link_name]->width = floatval($_REQUEST['link_width']);
			    $map->links[$link_name]->infourl[IN] = wm_editor_sanitize_string($_REQUEST['link_infourl']);
			    $map->links[$link_name]->infourl[OUT] = wm_editor_sanitize_string($_REQUEST['link_infourl']);
			    $urls = preg_split('/\s+/', $_REQUEST['link_hover'], -1, PREG_SPLIT_NO_EMPTY);
			    $map->links[$link_name]->overliburl[IN] = $urls;
			    $map->links[$link_name]->overliburl[OUT] = $urls;

			    $map->links[$link_name]->comments[IN] =  wm_editor_sanitize_string($_REQUEST['link_commentin']);
			    $map->links[$link_name]->comments[OUT] = wm_editor_sanitize_string($_REQUEST['link_commentout']);
			    $map->links[$link_name]->commentoffset_in =  intval($_REQUEST['link_commentposin']);
			    $map->links[$link_name]->commentoffset_out = intval($_REQUEST['link_commentposout']);

			    // $map->links[$link_name]->target = $_REQUEST['link_target'];

			    $targets = preg_split('/\s+/',$_REQUEST['link_target'],-1,PREG_SPLIT_NO_EMPTY);
			    $new_target_list = array();

			    foreach ($targets as $target) {
					// we store the original TARGET string, and line number, along with the breakdown, to make nicer error messages later
					$newtarget = array($target,'traffic_in','traffic_out',0,$target);

					// if it's an RRD file, then allow for the user to specify the
					// DSs to be used. The default is traffic_in, traffic_out, which is
					// OK for Cacti (most of the time), but if you have other RRDs...
					if (preg_match('/(.*\.rrd):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/i',$target,$matches)) {
						$newtarget[0] = $matches[1];
						$newtarget[1] = $matches[2];
						$newtarget[2] = $matches[3];
					}

					// now we've (maybe) messed with it, we'll store the array of target specs
					$new_target_list[] = $newtarget;
				}

				$map->links[$link_name]->targets = $new_target_list;

				$bwin = $_REQUEST['link_bandwidth_in'];
				$bwout = $_REQUEST['link_bandwidth_out'];

				if (isset($_REQUEST['link_bandwidth_out_cb']) && $_REQUEST['link_bandwidth_out_cb'] == 'symmetric') {
					$bwout = $bwin;
				}

				if (wm_editor_validate_bandwidth($bwin)) {
					$map->links[$link_name]->max_bandwidth_in_cfg = $bwin;
					$map->links[$link_name]->max_bandwidth_in = unformat_number($bwin, $map->kilo);
				}

				if (wm_editor_validate_bandwidth($bwout)) {
					$map->links[$link_name]->max_bandwidth_out_cfg = $bwout;
					$map->links[$link_name]->max_bandwidth_out = unformat_number($bwout, $map->kilo);
				}

				// $map->links[$link_name]->SetBandwidth($bwin,$bwout);

				$map->WriteConfig($mapfile);
			}

			break;
		case 'set_map_properties':
			$map->ReadConfig($mapfile);

			$map->title = wm_editor_sanitize_string($_REQUEST['map_title']);
			$map->keytext['DEFAULT'] = wm_editor_sanitize_string($_REQUEST['map_legend']);
			$map->stamptext = wm_editor_sanitize_string($_REQUEST['map_stamp']);

			$map->htmloutputfile = wm_editor_sanitize_file($_REQUEST['map_htmlfile'], array('html') );
			$map->imageoutputfile = wm_editor_sanitize_file($_REQUEST['map_pngfile'], array('png', 'jpg', 'gif', 'jpeg'));

			$map->width = intval($_REQUEST['map_width']);
			$map->height = intval($_REQUEST['map_height']);

			// XXX sanitise this a bit
			if ($_REQUEST['map_bgfile'] == '--NONE--') {
				$map->background = '';
			} else {
				$map->background = wm_editor_sanitize_file(stripslashes($_REQUEST['map_bgfile']), array('png', 'jpg', 'gif', 'jpeg') );
			}

			db_execute_prepared('UPDATE weathermap_maps
				SET titlecache = ?
				WHERE configfile = ?',
				array($map->title, basename($mapfile)));

			$inheritables = array(
				array('link', 'width', 'map_linkdefaultwidth', 'float')
			);

			handle_inheritance($map, $inheritables);

			$map->links['DEFAULT']->width = intval($_REQUEST['map_linkdefaultwidth']);
			$map->links['DEFAULT']->add_note('my_width', intval($_REQUEST['map_linkdefaultwidth']));

			$bwin = $_REQUEST['map_linkdefaultbwin'];
			$bwout = $_REQUEST['map_linkdefaultbwout'];

			$bwin_old = $map->links['DEFAULT']->max_bandwidth_in_cfg;
			$bwout_old = $map->links['DEFAULT']->max_bandwidth_out_cfg;

			if (!wm_editor_validate_bandwidth($bwin)) {
				$bwin = $bwin_old;
			}

			if (! wm_editor_validate_bandwidth($bwout)) {
				$bwout = $bwout_old;
			}

			if (($bwin_old != $bwin) || ($bwout_old != $bwout)) {
				$map->links['DEFAULT']->max_bandwidth_in_cfg = $bwin;
				$map->links['DEFAULT']->max_bandwidth_out_cfg = $bwout;
				$map->links['DEFAULT']->max_bandwidth_in = unformat_number($bwin, $map->kilo);
				$map->links['DEFAULT']->max_bandwidth_out = unformat_number($bwout, $map->kilo);

				// $map->defaultlink->SetBandwidth($bwin,$bwout);
				foreach ($map->links as $link) {
					if (($link->max_bandwidth_in_cfg == $bwin_old) || ($link->max_bandwidth_out_cfg == $bwout_old)) {
						// $link->SetBandwidth($bwin,$bwout);
						$link_name = $link->name;

						$map->links[$link_name]->max_bandwidth_in_cfg = $bwin;
						$map->links[$link_name]->max_bandwidth_out_cfg = $bwout;
						$map->links[$link_name]->max_bandwidth_in = unformat_number($bwin, $map->kilo);
						$map->links[$link_name]->max_bandwidth_out = unformat_number($bwout, $map->kilo);
					}
				}
			}

			$map->WriteConfig($mapfile);

			break;
		case 'set_map_style':
			$map->ReadConfig($mapfile);

			if (wm_editor_validate_one_of($_REQUEST['mapstyle_htmlstyle'],array('static','overlib'),false)) {
				$map->htmlstyle = strtolower($_REQUEST['mapstyle_htmlstyle']);
			}

			$map->keyfont = intval($_REQUEST['mapstyle_legendfont']);

			$inheritables = array(
				array('link','labelstyle','mapstyle_linklabels',''),
				array('link','bwfont','mapstyle_linkfont','int'),
				array('link','arrowstyle','mapstyle_arrowstyle',''),
				array('node','labelfont','mapstyle_nodefont','int')
			);

			handle_inheritance($map, $inheritables);

			$map->WriteConfig($mapfile);

			break;
		case 'add_link':
			$map->ReadConfig($mapfile);

			$param2 = $_REQUEST['param'];
			# $param2 = substr($param2,0,-2);
			$newaction = 'add_link2';
			#  print $newaction;
			$selected = 'NODE:'.$param2;

			break;
		case 'add_link2':
			$map->ReadConfig($mapfile);

			$a = $_REQUEST['param2'];
			$b = $_REQUEST['param'];
			# $b = substr($b,0,-2);
			$log = "[$a -> $b]";

			if ($a != $b && isset($map->nodes[$a]) && isset($map->nodes[$b])) {
				$newlink = new WeatherMapLink;

				$newlink->Reset($map);

				$newlink->a = $map->nodes[$a];
				$newlink->b = $map->nodes[$b];

				// $newlink->SetBandwidth($map->defaultlink->max_bandwidth_in_cfg, $map->defaultlink->max_bandwidth_out_cfg);

				$newlink->width = $map->links['DEFAULT']->width;

				// make sure the link name is unique. We can have multiple links between
				// the same nodes, these days
				$newlinkname = "$a-$b";
				while(array_key_exists($newlinkname,$map->links)) {
					$newlinkname .= 'a';
				}

				$newlink->name = $newlinkname;
				$newlink->defined_in = $map->configfile;
				$map->links[$newlinkname] = $newlink;
				array_push($map->seen_zlayers[$newlink->zorder], $newlink);

				$map->WriteConfig($mapfile);
			}

			break;
		case 'place_legend':
			$x = snap( intval($_REQUEST['x']) ,$grid_snap_value);
			$y = snap( intval($_REQUEST['y']) ,$grid_snap_value);
			$scalename = wm_editor_sanitize_name($_REQUEST['param']);

			$map->ReadConfig($mapfile);

			$map->keyx[$scalename] = $x;
			$map->keyy[$scalename] = $y;

			$map->WriteConfig($mapfile);

			break;
		case 'place_stamp':
			$x = snap( intval($_REQUEST['x']), $grid_snap_value);
			$y = snap( intval($_REQUEST['y']), $grid_snap_value);

			$map->ReadConfig($mapfile);

			$map->timex = $x;
			$map->timey = $y;

			$map->WriteConfig($mapfile);

			break;
		case 'via_link':
			$x = intval($_REQUEST['x']);
			$y = intval($_REQUEST['y']);
			$link_name = wm_editor_sanitize_name($_REQUEST['link_name']);

			$map->ReadConfig($mapfile);

			if (isset($map->links[$link_name])) {
			    $map->links[$link_name]->vialist = array(array(0 =>$x, 1=>$y));
			    $map->WriteConfig($mapfile);
			}

			break;
		case 'move_node':
			$x = snap(intval($_REQUEST['x']), $grid_snap_value);
			$y = snap(intval($_REQUEST['y']), $grid_snap_value);

			$node_name = wm_editor_sanitize_name($_REQUEST['node_name']);

			$map->ReadConfig($mapfile);

			if (isset($map->nodes[$node_name])) {
			    // This is a complicated bit. Find out if this node is involved in any
			    // links that have VIAs. If it is, we want to rotate those VIA points
			    // about the *other* node in the link
			    foreach ($map->links as $link) {
				    if ((count($link->vialist)>0)  && (($link->a->name == $node_name) || ($link->b->name == $node_name))) {
					    // get the other node from us
					    if ($link->a->name == $node_name) {
							$pivot = $link->b;
						}

					    if ($link->b->name == $node_name) {
							$pivot = $link->a;
						}

					    if (($link->a->name == $node_name) && ($link->b->name == $node_name)) {
						    // this is a wierd special case, but it is possible
						    # $log .= "Special case for node1->node1 links\n";
						    $dx = $link->a->x - $x;
						    $dy = $link->a->y - $y;

						    for($i=0; $i<count($link->vialist); $i++) {
							    $link->vialist[$i][0] = $link->vialist[$i][0]-$dx;
							    $link->vialist[$i][1] = $link->vialist[$i][1]-$dy;
						    }
					    } else {
						    $pivx = $pivot->x;
						    $pivy = $pivot->y;

						    $dx_old = $pivx - $map->nodes[$node_name]->x;
						    $dy_old = $pivy - $map->nodes[$node_name]->y;
						    $dx_new = $pivx - $x;
						    $dy_new = $pivy - $y;
						    $l_old = sqrt($dx_old*$dx_old + $dy_old*$dy_old);
						    $l_new = sqrt($dx_new*$dx_new + $dy_new*$dy_new);

						    $angle_old = rad2deg(atan2(-$dy_old,$dx_old));
						    $angle_new = rad2deg(atan2(-$dy_new,$dx_new));

						    # $log .= "$pivx,$pivy\n$dx_old $dy_old $l_old => $angle_old\n";
						    # $log .= "$dx_new $dy_new $l_new => $angle_new\n";

						    // the geometry stuff uses a different point format, helpfully
						    $points = array();

						    foreach($link->vialist as $via) {
							    $points[] = $via[0];
							    $points[] = $via[1];
						    }

						    $scalefactor = $l_new/$l_old;
						    # $log .= "Scale by $scalefactor along link-line";

						    // rotate so that link is along the axis
						    rotateAboutPoint($points,$pivx, $pivy, deg2rad($angle_old));
						    // do the scaling in here
						    for($i=0; $i<(count($points)/2); $i++) {
							    $basex = ($points[$i*2] - $pivx) * $scalefactor + $pivx;
							    $points[$i*2] = $basex;
						    }

						    // rotate back so that link is along the new direction
						    rotateAboutPoint($points,$pivx, $pivy, deg2rad(-$angle_new));

						    // now put the modified points back into the vialist again
						    $v = 0; $i = 0;
						    foreach($points as $p) {
							    // skip a point if it positioned relative to a node. Those shouldn't be rotated (well, IMHO)
							    if (!isset($link->vialist[$v][2])) {
								    $link->vialist[$v][$i]=$p;
							    }

							    $i++;

							    if ($i==2) {
									$i=0; $v++;
								}
						    }
					    }
				    }
			    }

			    $map->nodes[$node_name]->x = $x;
			    $map->nodes[$node_name]->y = $y;

			    $map->WriteConfig($mapfile);
			}

			break;
		case 'link_tidy':
			$map->ReadConfig($mapfile);

			$target = wm_editor_sanitize_name($_REQUEST['param']);

			if (isset($map->links[$target])) {
				// draw a map and throw it away, to calculate all the bounding boxes
				$map->DrawMap('null');

				tidy_link($map,$target);

				$map->WriteConfig($mapfile);
			}

			break;
		case 'retidy':
			$map->ReadConfig($mapfile);

			// draw a map and throw it away, to calculate all the bounding boxes
			$map->DrawMap('null');
			retidy_links($map);

			$map->WriteConfig($mapfile);

			break;
		case 'retidy_all':
			$map->ReadConfig($mapfile);

			// draw a map and throw it away, to calculate all the bounding boxes
			$map->DrawMap('null');
			retidy_links($map,true);

			$map->WriteConfig($mapfile);

			break;
		case 'untidy':
			$map->ReadConfig($mapfile);

			// draw a map and throw it away, to calculate all the bounding boxes
			$map->DrawMap('null');
			untidy_links($map);

			$map->WriteConfig($mapfile);

			break;
		case 'delete_link':
			$map->ReadConfig($mapfile);

			$target = wm_editor_sanitize_name($_REQUEST['param']);
			$log = 'delete link ' . $target;

			if (isset($map->links[$target])) {
			    unset($map->links[$target]);

			    $map->WriteConfig($mapfile);
			}

			break;
		case 'add_node':
			$x = snap(intval($_REQUEST['x']), $grid_snap_value);
			$y = snap(intval($_REQUEST['y']), $grid_snap_value);

			$map->ReadConfig($mapfile);

			$newnodename = sprintf('node%05d', time() % 10000);
			while(array_key_exists($newnodename,$map->nodes)) {
				$newnodename .= 'a';
			}

			$node = new WeatherMapNode;

			$node->name = $newnodename;
			$node->template = 'DEFAULT';
			$node->Reset($map);

			$node->x = $x;
			$node->y = $y;
			$node->defined_in = $map->configfile;

			array_push($map->seen_zlayers[$node->zorder], $node);

			// only insert a label if there's no LABEL in the DEFAULT node.
			// otherwise, respect the template.
			if ($map->nodes['DEFAULT']->label == $map->nodes[':: DEFAULT ::']->label) {
				$node->label = 'Node';
			}

			$map->nodes[$node->name] = $node;
			$log = "added a node called $newnodename at $x,$y to $mapfile";

			$map->WriteConfig($mapfile);

			break;
		case 'editor_settings':
			// have to do this, otherwise the editor will be unresponsive afterwards - not actually going to change anything!
			$map->ReadConfig($mapfile);

			$use_overlay = (isset($_REQUEST['editorsettings_showvias']) ? intval($_REQUEST['editorsettings_showvias']) : false);
			$use_relative_overlay = (isset($_REQUEST['editorsettings_showrelative']) ? intval($_REQUEST['editorsettings_showrelative']) : false);
			$grid_snap_value = (isset($_REQUEST['editorsettings_gridsnap']) ? intval($_REQUEST['editorsettings_gridsnap']) : 0);

			break;
		case 'delete_node':
			$map->ReadConfig($mapfile);

			$target = wm_editor_sanitize_name($_REQUEST['param']);

			if (isset($map->nodes[$target])) {
				$log = 'delete node ' . $target;

				foreach ($map->links as $link) {
					if (isset($link->a)) {
						if (($target == $link->a->name) || ($target == $link->b->name)) {
							unset($map->links[$link->name]);
						}
					}
				}

				unset($map->nodes[$target]);

				$map->WriteConfig($mapfile);
			}

			break;
		case 'clone_node':
			$map->ReadConfig($mapfile);

			$target = wm_editor_sanitize_name($_REQUEST['param']);

			if (isset($map->nodes[$target])) {
				$log = 'clone node ' . $target;

				$newnodename = $target;

				do {
					$newnodename = $newnodename . '_copy';
				} while(isset($map->nodes[$newnodename]));

				$node = new WeatherMapNode;

				$node->Reset($map);
				$node->CopyFrom($map->nodes[$target]);

				# CopyFrom skips this one, because it's also the function used by template inheritance
				# - but for Clone, we DO want to copy the template too
				$node->template = $map->nodes[$target]->template;

				$node->name = $newnodename;
				$node->x += 30;
				$node->y += 30;
				$node->defined_in = $mapfile;

				$map->nodes[$newnodename] = $node;
				array_push($map->seen_zlayers[$node->zorder], $node);

				$map->WriteConfig($mapfile);
			}

			break;
		default:
			// no action was defined - starting a new map?
			$map->ReadConfig($mapfile);

			break;
	}

	//by here, there should be a valid $map - either a blank one, the existing one, or the existing one with requested changes
	wm_debug('Finished modifying');

	// now we'll just draw the full editor page, with our new knowledge

	$imageurl = '?mapname='. $mapname . '&action=draw';
	if ($selected != '') {
		$imageurl .= '&selected=' . wm_editor_sanitize_selected($selected);
	}

	$imageurl .= '&unique=' . time();

	// build up the editor's list of used images
	if ($map->background != '') {
		// Update the location of the backgrounds
		if (!file_exists($config['base_path'] . '/plugins/weathermap/' . $map->background)) {
			if (file_exists($config['base_path'] . '/plugins/weathermap/images/backgrounds/' . basename($map->background))) {
				$map->background = 'images/backgrounds/' . basename($map->background);
			}
		}
	}

	foreach ($map->nodes as $n) {
		if ($n->iconfile != '' && ! preg_match('/^(none|nink|inpie|outpie|box|rbox|gauge|round)$/', $n->iconfile)) {
			// Update the location of the objects
			if (!file_exists($config['base_path'] . '/plugins/weathermap/' . $n->iconfile)) {
				if (file_exists($config['base_path'] . '/plugins/weathermap/images/objects/' . basename($n->iconfile))) {
					$map->used_images[] = 'images/objects/' . basename($n->iconfile);
				}
			}

			$map->used_images[] = $n->iconfile;
		}
	}

	// get the list from the images/ folder too
	$image_list   = get_imagelist('objects');
	$backgd_list  = get_imagelist('backgrounds');

	$fontlist = array();

	cacti_cookie_set('wmeditor', ($use_overlay ? '1':'0') . ':' . ($use_relative_overlay ? '1':'0') . ':' . intval($grid_snap_value));
//	setcookie("wmeditor", ($use_overlay ? "1":"0") .":". ($use_relative_overlay ? "1":"0") . ":" . intval($grid_snap_value), time()+60*60*24*30 );

$selectedTheme = get_selected_theme();

?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' lang='en' xml:lang='en'>
<head>
	<style type='text/css'>
		<?php
		// if the cacti config was included properly, then
		// this will be non-empty, and we can unhide the cacti links in the Link Properties box
		if (!isset($config['cacti_version'])) {
			print "		.cactilink { display: none; }\n";
			print "		.cactinode { display: none; }\n";
		}
		?>
	</style>

	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/favicon.ico'?>' rel='shortcut icon'>
	<link href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/images/cacti_logo.gif'?>' rel='icon' sizes='96x96'>
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/jquery-ui.css';?>' />
	<link rel='stylesheet' type='text/css' media='screen' href='<?php print $config['url_path'] . 'include/themes/' . $selectedTheme . '/main.css';?>' />
	<link rel='stylesheet' type='text/css' media='screen' href='css/editor.css' />

	<script src='<?php print $config['url_path'] . 'include/js/jquery.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/jquery-ui.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/jquery.tablesorter.js';?>' type='text/javascript'></script>
	<script src='<?php print $config['url_path'] . 'include/js/js.storage.js';?>' type='text/javascript'></script>
	<script src='js/editor.js' type='text/javascript'></script>
	<script src='js/jquery.ddslick.js' type='text/javascript'></script>
	<script src='js/jquery.ui-contextmenu.js' type='text/javascript'></script>

	<title>PHP Weathermap Editor <?php print $WEATHERMAP_VERSION; ?></title>
</head>

<body id='mainView' class='mainView'>
	<div id='toolbar'>
		<ul>
			<li class='tb_active' id='tb_newfile'>Change<br />File</li>
			<li class='tb_active' id='tb_addnode'>Add<br />Node</li>
			<li class='tb_active' id='tb_addlink'>Add<br />Link</li>
			<li class='tb_active' id='tb_poslegend'>Position<br />Legend</li>
			<li class='tb_active' id='tb_postime'>Position<br />Timestamp</li>
			<li class='tb_active' id='tb_mapprops'>Map<br />Properties</li>
			<li class='tb_active' id='tb_mapstyle'>Map<br />Style</li>
			<li class='tb_active' id='tb_colours'>Manage<br />Colors</li>
			<li class='tb_active' id='tb_manageimages'>Manage<br />Images</li>
			<li class='tb_active' id='tb_prefs'>Editor<br />Settings</li>
			<li class='tb_coords' id='tb_coords'>Position<br />---, ---</li>
			<li class='tb_help'>
				<span id='tb_help'>or click a Node or Link to edit it's properties</span>
			</li>
		</ul>
	</div>
	<form id='frmMain' action='<?php print $editor_name ?>' method='post'>
		<script type='text/javascript'>
			var editor_url = '<?php print $editor_name; ?>';

			// the only javascript in here should be the objects representing the map itself
			// all code should be in editor.js
			<?php print $map->asJS() ?>
			<?php
			// append any images used in the map that aren't in the images folder
			foreach ($map->used_images as $im) {
				if (!in_array($im, $image_list)) {
					$image_list[] = $im;
				}
			}

			sort($image_list);
	?></script>
		<div class='mainArea'>
			<input id='xycapture' name='xycapture' style='display:none' type='image' src='<?php print html_escape($imageurl); ?>' />
			<img src='<?php print html_escape($imageurl); ?>' id='existingdata' usemap='#weathermap_imap' />
			<input id='x' name='x' type='hidden' />
			<input id='y' name='y' type='hidden' />
			<div class='debug' style='display:none'><p><strong>Debug</strong>
				<a href='?action=retidy_all&mapname=<?php print html_escape($mapname) ?>'>Re-tidy ALL</a>
				<a href='?action=retidy&mapname=<?php print html_escape($mapname) ?>'>Re-tidy</a>
				<a href='?action=untidy&mapname=<?php print html_escape($mapname) ?>'>Un-tidy</a>
				<a href='?action=nothing&mapname=<?php print html_escape($mapname) ?>'>Do Nothing</a>
				<span>
					<label for='mapname'>mapfile</label>
					<input id='mapname' name='mapname' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($mapname); ?>' />
				</span>
				<span>
					<label for='action'>action</label>
					<input id='action' name='action' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($newaction); ?>' />
				</span>
				<span>
					<label for='param'>param</label>
					<input id='param' name='param' type='text' class='ui-state-default ui-corner-all' value='' />
				</span>
				<span>
					<label for='param2'>param2</label>
					<input id='param2' name='param2' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($param2); ?>' />
				</span>
				<span>
					<label for='debug'>debug</label>
					<input id='debug' name='debug' type='text' class='ui-state-default ui-corner-all' value='' />
				</span>
				<a target='configwindow' href='?action=show_config&mapname=<?php print urlencode($mapname) ?>'>See config</a></p>
				<pre><?php print html_escape($log) ?></pre>
			</div>
			<?php
			// we need to draw and throw away a map, to get the
			// dimensions for the imagemap. Oh well.
			$map->DrawMap('null');
			$map->htmlstyle='editor';
			$map->PreloadMapHTML();

			print $map->SortedImagemap('weathermap_imap');

			#print $map->imap->subHTML('LEGEND:');
			#print $map->imap->subHTML('TIMESTAMP');
			#print $map->imap->subHTML('NODE:');
			#print $map->imap->subHTML('LINK:');

			?>
		</div>

		<!-- Node Properties -->
		<div id='dlgNodeProperties' class='dlgProperties' title='Node Properties'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>
								<input id='node_name' name='node_name' type='hidden' size='6'/>
							</td>
						</tr>
						<tr>
							<td>Position</td>
							<td><input id='node_x' name='node_x' type='text' class='ui-state-default ui-corner-all' size='4' />,<input id='node_y' name='node_y' type='text' class='ui-state-default ui-corner-all' size='4' /></td>
						</tr>
						<tr>
							<td>Internal Name</td>
							<td><input id='node_new_name' name='node_new_name' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td>Label</td>
							<td><input id='node_label' name='node_label' type='text' class='ui-state-default ui-corner-all' /></td>
						</tr>
						<tr>
							<td>Icon Filename</td>
							<td>
								<select id='node_iconfilename' name='node_iconfilename'>
									<?php
									if (count($image_list) == 0) {
										print '<option data-value="--NONE--">(no images are available)</option>';
									} else {
										print '<option data-description="Default Rectangular Icon" data-imagesrc="" value="--NONE--">--NO ICON--</option>';
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
							<td>Info URL(s)</td>
							<td>
								<textarea id='node_infourl' name='node_infourl' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td>'Hover' Graph URL(s)</td>
							<td>
								<textarea id='node_hover' name='node_hover' class='ui-state-default ui-corner-all' rows='2' cols='60'></textarea>
							</td>
						</tr>
						<tr>
							<td>Graph Selector</td>
							<td>
								<input id='node_picker' name='node_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a class='ui-button ui-corner-all ui-widget' id='node_move'>Move</a>
						<a class='ui-button ui-corner-all ui-widget' id='node_delete'>Delete</a>
						<a class='ui-button ui-corner-all ui-widget' id='node_clone'>Clone</a>
						<a class='ui-button ui-corner-all ui-widget' id='node_edit'>Edit</a>
						<a id='tb_node_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_node_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
				</div>
			</div>
		</div>
		<!-- Node Properties -->

		<!-- Link Properties -->
		<div id='dlgLinkProperties' class='dlgProperties' title='Link Properties'>
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
							<td>Data Source(s)</td>
							<td>
								<textarea id='link_target' name='link_target' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td>Data Source Selector</td>
							<td>
								<input id='link_target_picker' name='link_target_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='datasources' />
							</td>
						</tr>
						<tr>
							<td>Link Width</td>
							<td><input id='link_width' name='link_width' type='text' class='ui-state-default ui-corner-all' size='3' /> pixels</td>
						</tr>
						<tr>
							<td>Info URL(s)</td>
							<td>
								<textarea id='link_infourl' name='link_infourl' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td>'Hover' Graph URL(s)</td>
							<td>
								<textarea id='link_hover' name='link_hover' class='ui-state-default ui-corner-all'></textarea>
							</td>
						</tr>
						<tr>
							<td>Graph Selector</td>
							<td>
								<input id='link_picker' name='link_picker' type='text' class='selectmenu-ajax ui-state-default ui-corner-all' data-action='graphs' />
							</td>
						</tr>
						<tr>
							<td>IN Comment</td>
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
							<td>OUT Comment</td>
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
						<a class='ui-button ui-corner-all ui-widget' id='link_delete'>Delete Link</a>
						<a class='ui-button ui-corner-all ui-widget' id='link_edit'>Edit</a>
						<a class='ui-button ui-corner-all ui-widget' id='link_tidy'>Tidy</a>
						<a class='ui-button ui-corner-all ui-widget' id='link_via'>Via</a>
						<a id='tb_link_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_link_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
				</div>
			</div>
		</div>
		<!-- Link Properties -->

		<!-- Map Properties -->
		<div id='dlgMapProperties' class='dlgProperties' title='Map Properties'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>Map Title</td>
							<td><input id='map_title' name='map_title' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->title) ?>'/></td>
						</tr>
						<tr>
							<td>Legend Text</td>
							<td><input id='map_legend' name='map_legend' type='text' class='ui-state-default ui-corner-all' size='25' value='<?php print html_escape($map->keytext['DEFAULT']) ?>' /></td>
						</tr>
						<tr>
							<td>Background Image Filename</td>
							<td>
								<select id='map_bgfile' name='map_bgfile'>
									<?php
									if (count($backgd_list) == 0) {
										print '<option data-value="--NONE--">(no images are available)</option>';
									} else {
										print '<option data-description="Solid White Background" data-imagesrc="" value="--NONE--">--NO ICON--</option>';
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
							<td>Timestamp Text</td>
							<td><input id='map_stamp' name='map_stamp' type='text' class='ui-state-default ui-corner-all' size='40' value='<?php print html_escape($map->stamptext) ?>' /></td>
						</tr>
						<tr>
							<td>Default Link Width</td>
							<td><input id='map_linkdefaultwidth' name='map_linkdefaultwidth' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->width) ?>' /> pixels</td>
						</tr>
						<tr>
							<td>Default Link Bandwidth</td>
							<td>
								<input id='map_linkdefaultbwin' name='map_linkdefaultbwin' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_in_cfg) ?>' /> bit/sec in, <input id='map_linkdefaultbwout' name='map_linkdefaultbwout' type='text' class='ui-state-default ui-corner-all' size='6' value='<?php print html_escape($map->links['DEFAULT']->max_bandwidth_out_cfg) ?>' /> bit/sec out
							</td>
						</tr>
						<tr>
							<td>Map Size</td>
							<td>
								<input id='map_width' name='map_width' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->width) ?>' /> x
								<input id='map_height' name='map_height' type='text' class='ui-state-default ui-corner-all' size='5' value='<?php print html_escape($map->height) ?>' /> pixels
							</td>
						</tr>
						<tr>
							<td>Output Image Filename</td>
							<td><input id='map_pngfile' name='map_pngfile' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($map->imageoutputfile) ?>' /></td>
						</tr>
						<tr>
							<td>Output HTML Filename</td>
							<td><input id='map_htmlfile' name='map_htmlfile' type='text' class='ui-state-default ui-corner-all' value='<?php print html_escape($map->htmloutputfile) ?>' /></td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_map_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_map_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
				</div>
			</div>
		</div>
		<!-- Map Properties -->

		<!-- Map Style -->
		<div id='dlgMapStyle' class='dlgProperties' title='Map Style'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<table class='cactiTable'>
						<tr>
							<td>Link Labels</td>
							<td>
								<select id='mapstyle_linklabels' name='mapstyle_linklabels'>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'bits' ? 'selected' : '') ?> value='bits'>Bits/sec</option>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'percent' ? 'selected' : '') ?> value='percent'>Percentage</option>
									<option <?php print ($map->links['DEFAULT']->labelstyle == 'none' ? 'selected' : '') ?> value='none'>None</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>HTML Style</td>
							<td>
								<select id='mapstyle_htmlstyle' name='mapstyle_htmlstyle'>
									<option <?php print ($map->htmlstyle == 'overlib' ? 'selected' : '') ?> value='overlib'>Overlib (DHTML)</option>
									<option <?php print ($map->htmlstyle == 'static' ? 'selected' : '') ?> value='static'>Static HTML</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>Arrow Style</td>
							<td>
								<select id='mapstyle_arrowstyle' name='mapstyle_arrowstyle'>
									<option <?php print ($map->links['DEFAULT']->arrowstyle == 'classic' ? 'selected' : '') ?> value='classic'>Classic</option>
									<option <?php print ($map->links['DEFAULT']->arrowstyle == 'compact' ? 'selected' : '') ?> value='compact'>Compact</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>Node Font</td>
							<td><?php print get_fontlist($map,'mapstyle_nodefont',$map->nodes['DEFAULT']->labelfont); ?></td>
						</tr>
						<tr>
							<td>Link Label Font</td>
							<td><?php print get_fontlist($map,'mapstyle_linkfont',$map->links['DEFAULT']->bwfont); ?></td>
						</tr>
						<tr>
							<td>Legend Font</td>
							<td><?php print get_fontlist($map,'mapstyle_legendfont',$map->keyfont); ?></td>
						</tr>
						<tr>
							<td>Font Samples:</td>
							<td><div class='fontsamples' ><img id='fontsamples' alt='' src='' /></div><br />(Drawn using your PHP install)</td>
						</tr>
					</table>
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_mapstyle_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_mapstyle_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
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
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
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
					These images are what would appear in the dropdown boxes that don't currently do anything in the Node and Map Properties dialogs. This may end up being a seperate page rather than a dialog box...
				</div>
				<div class='dlgButtons'>
					<div class='dlgSubButtons'>
						<a id='tb_images_cancel' class='wm_cancel ui-button ui-corner-all ui-widget'>Cancel</a>
						<a id='tb_images_submit' class='wm_submit ui-button ui-corner-all ui-widget'>Save</a>
					</div>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
				</div>
			</div>
		</div>
		<!-- Images -->

		<!-- TextEdit -->
        <div id='dlgTextEdit' class='dlgProperties' title='Edit Map Object'>
			<div class='cactiTable'>
				<div class='dlgBody'>
					<p>You can edit the map items directly here.</p>
	   	             <textarea wrap='no' id='item_configtext' name='item_configtext' cols='80' rows='15'></textarea>
				</div>
				<div class='dlgHelp'>
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
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
					Helpful text will appear here, depending on the current
					item selected. It should wrap onto several lines, if it's
					necessary for it to do that.
				</div>
			</div>
		</div>
		<!-- TextEditSettings -->
	</form>
</body>
</html>
<?php
} // if mapname != ''
