<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2023 The Cacti Group, Inc.                           |
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

/** editor.actions.php
 *
 * All the functions used by the that wrap in request variables
 * and pass control to lower level functions in the weathermap classes
 * and the editor.inc.php file.
 */

function newMap($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->WriteConfig($mapfile);
}

function newMapCopy($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	if (isset_request_var('sourcemap')) {
		$sourcemapname = get_nfilter_request_var('sourcemap');
	}

	$sourcemapname = wm_editor_sanitize_conffile($sourcemapname);

	if ($sourcemapname != '') {
		$sourcemap = $mapdir . '/' . $sourcemapname;

		if (file_exists($sourcemap) && is_readable($sourcemap)) {
			$map->ReadConfig($sourcemap);
			$map->WriteConfig($mapfile);
		}
	}
}

function getMapJavaScript($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	print $map->asJS();
}

function getMapAreaData($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	// we need to draw and throw away a map, to get the
	// dimensions for the imagemap.
	$map->DrawMap('null');

	$map->htmlstyle = 'editor';

	$map->PreloadMapHTML();

	print $map->SortedImagemap('weathermap_imap');
}

function drawMap($mapfile, $selected, $use_overlay, $use_relative_overlay) {
	header('Content-type: image/png');

	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	if ($selected != '') {
		if (substr($selected, 0, 5) == 'NODE:') {
			$nodename = substr($selected, 5);
			$map->nodes[$nodename]->selected = 1;
		}

		if (substr($selected, 0, 5) == 'LINK:') {
			$linkname = substr($selected, 5);
			$map->links[$linkname]->selected = 1;
		}
	}

	$map->sizedebug = true;
	$map->DrawMap('', '', 250, true, $use_overlay, $use_relative_overlay);
}

function showConfig($mapfile) {
	header('Content-type: text/plain');

	$fd = fopen($mapfile,'r');

	while (!feof($fd)) {
		$buffer = fgets($fd, 4096);
		print $buffer;
	}

	fclose($fd);
}

function fetchConfig($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	header('Content-type: text/plain');

	$item_name = get_nfilter_request_var('item_name');
	$item_type = get_nfilter_request_var('item_type');

	$ok = false;

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
}

function setNodeConfig($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$node_name = get_nfilter_request_var('node_name');
	$node_config = fix_gpc_string(get_nfilter_request_var('item_configtext'));

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
}

function setLinkConfig($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$link_name = get_nfilter_request_var('link_name');
	$link_config = fix_gpc_string(get_nfilter_request_var('item_configtext'));

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
}

function setNodeProperties($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$node_name = get_nfilter_request_var('node_name');
	$new_node_name = get_nfilter_request_var('node_new_name');

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
	$map->nodes[$new_node_name]->label       = wm_editor_sanitize_string(get_nfilter_request_var('node_label'));
	$map->nodes[$new_node_name]->infourl[IN] = wm_editor_sanitize_string(get_nfilter_request_var('node_infourl'));

	$urls = preg_split('/\s+/', get_nfilter_request_var('node_hover'), -1, PREG_SPLIT_NO_EMPTY);

	$map->nodes[$new_node_name]->overliburl[IN]  = $urls;
	$map->nodes[$new_node_name]->overliburl[OUT] = $urls;

	$map->nodes[$new_node_name]->x = intval(get_nfilter_request_var('node_x'));
	$map->nodes[$new_node_name]->y = intval(get_nfilter_request_var('node_y'));

	if (get_nfilter_request_var('node_iconfilename') == '--NONE--') {
	    $map->nodes[$new_node_name]->iconfile = '';
	} else {
	    // AICONs mess this up, because they're not fully supported by the editor, but it can still break them
	    if (get_nfilter_request_var('node_iconfilename') != '--AICON--') {
		    $iconfile = stripslashes(get_nfilter_request_var('node_iconfilename'));
		    $map->nodes[$new_node_name]->iconfile = $iconfile;
	    }
	}

	$map->WriteConfig($mapfile);
}

function setLinkProperties($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$link_name = get_nfilter_request_var('link_name');

	if (strpos($link_name, ' ') === false) {
	    $map->links[$link_name]->width        = floatval(get_nfilter_request_var('link_width'));
	    $map->links[$link_name]->infourl[IN]  = wm_editor_sanitize_string(get_nfilter_request_var('link_infourl'));
	    $map->links[$link_name]->infourl[OUT] = wm_editor_sanitize_string(get_nfilter_request_var('link_infourl'));

	    $urls = preg_split('/\s+/', get_nfilter_request_var('link_hover'), -1, PREG_SPLIT_NO_EMPTY);

	    $map->links[$link_name]->overliburl[IN]  = $urls;
	    $map->links[$link_name]->overliburl[OUT] = $urls;

	    $map->links[$link_name]->comments[IN]      = wm_editor_sanitize_string(get_nfilter_request_var('link_commentin'));
	    $map->links[$link_name]->comments[OUT]     = wm_editor_sanitize_string(get_nfilter_request_var('link_commentout'));
	    $map->links[$link_name]->commentoffset_in  = intval(get_nfilter_request_var('link_commentposin'));
	    $map->links[$link_name]->commentoffset_out = intval(get_nfilter_request_var('link_commentposout'));

	    // $map->links[$link_name]->target = get_nfilter_request_var('link_target'];

	    $targets = preg_split('/\s+/', get_nfilter_request_var('link_target'), -1, PREG_SPLIT_NO_EMPTY);
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

		$bwin  = get_nfilter_request_var('link_bandwidth_in');
		$bwout = get_nfilter_request_var('link_bandwidth_out');

		if (isset_request_var('link_bandwidth_out_cb') && get_nfilter_request_var('link_bandwidth_out_cb') == 'symmetric') {
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
}

function setMapProperties($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$map->title              = wm_editor_sanitize_string(get_nfilter_request_var('map_title'));
	$map->keytext['DEFAULT'] = wm_editor_sanitize_string(get_nfilter_request_var('map_legend'));
	$map->stamptext          = wm_editor_sanitize_string(get_nfilter_request_var('map_stamp'));

	$map->htmloutputfile  = wm_editor_sanitize_file(get_nfilter_request_var('map_htmlfile'), array('html') );
	$map->imageoutputfile = wm_editor_sanitize_file(get_nfilter_request_var('map_pngfile'), array('png', 'jpg', 'gif', 'jpeg'));

	$map->width  = intval(get_nfilter_request_var('map_width'));
	$map->height = intval(get_nfilter_request_var('map_height'));

	// XXX sanitise this a bit
	if (get_nfilter_request_var('map_bgfile') == '--NONE--') {
		$map->background = '';
	} else {
		$map->background = wm_editor_sanitize_file(stripslashes(get_nfilter_request_var('map_bgfile')), array('png', 'jpg', 'gif', 'jpeg') );
	}

	db_execute_prepared('UPDATE weathermap_maps
		SET titlecache = ?
		WHERE configfile = ?',
		array($map->title, basename($mapfile)));

	$inheritables = array(
		array('link', 'width', 'map_linkdefaultwidth', 'float')
	);

	handle_inheritance($map, $inheritables);

	$map->links['DEFAULT']->width = intval(get_nfilter_request_var('map_linkdefaultwidth'));

	$map->links['DEFAULT']->add_note('my_width', get_filter_request_var('map_linkdefaultwidth'));

	$bwin  = get_nfilter_request_var('map_linkdefaultbwin');
	$bwout = get_nfilter_request_var('map_linkdefaultbwout');

	$bwin_old  = $map->links['DEFAULT']->max_bandwidth_in_cfg;
	$bwout_old = $map->links['DEFAULT']->max_bandwidth_out_cfg;

	if (!wm_editor_validate_bandwidth($bwin)) {
		$bwin = $bwin_old;
	}

	if (! wm_editor_validate_bandwidth($bwout)) {
		$bwout = $bwout_old;
	}

	if (($bwin_old != $bwin) || ($bwout_old != $bwout)) {
		$map->links['DEFAULT']->max_bandwidth_in_cfg  = $bwin;
		$map->links['DEFAULT']->max_bandwidth_out_cfg = $bwout;
		$map->links['DEFAULT']->max_bandwidth_in      = unformat_number($bwin, $map->kilo);
		$map->links['DEFAULT']->max_bandwidth_out     = unformat_number($bwout, $map->kilo);

		// $map->defaultlink->SetBandwidth($bwin,$bwout);
		foreach ($map->links as $link) {
			if (($link->max_bandwidth_in_cfg == $bwin_old) || ($link->max_bandwidth_out_cfg == $bwout_old)) {
				// $link->SetBandwidth($bwin,$bwout);
				$link_name = $link->name;

				$map->links[$link_name]->max_bandwidth_in_cfg  = $bwin;
				$map->links[$link_name]->max_bandwidth_out_cfg = $bwout;
				$map->links[$link_name]->max_bandwidth_in      = unformat_number($bwin, $map->kilo);
				$map->links[$link_name]->max_bandwidth_out     = unformat_number($bwout, $map->kilo);
			}
		}
	}

	$map->WriteConfig($mapfile);
}

function setMapStyle($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	if (wm_editor_validate_one_of(get_nfilter_request_var('mapstyle_htmlstyle'), array('static', 'overlib'), false)) {
		$map->htmlstyle = strtolower(get_nfilter_request_var('mapstyle_htmlstyle'));
	}

	$map->keyfont = get_filter_request_var('mapstyle_legendfont');

	$inheritables = array(
		array('link', 'labelstyle', 'mapstyle_linklabels', ''),
		array('link', 'bwfont', 'mapstyle_linkfont', 'int'),
		array('link', 'arrowstyle', 'mapstyle_arrowstyle', ''),
		array('node', 'labelfont', 'mapstyle_nodefont', 'int')
	);

	handle_inheritance($map, $inheritables);

	$map->WriteConfig($mapfile);
}

function addLink($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$a = get_nfilter_request_var('param2');
	$b = get_nfilter_request_var('param');

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
}

function placeLegend($mapfile, $grid_snap_value) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$x = snap(intval(get_nfilter_request_var('x')), $grid_snap_value);
	$y = snap(intval(get_nfilter_request_var('y')), $grid_snap_value);

	$scalename = wm_editor_sanitize_name(get_nfilter_request_var('param'));

	$map->ReadConfig($mapfile);

	$map->keyx[$scalename] = $x;
	$map->keyy[$scalename] = $y;

	$map->WriteConfig($mapfile);
}

function placeStamp($mapfile, $grid_snap_value) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$x = snap(intval(get_nfilter_request_var('x')), $grid_snap_value);
	$y = snap(intval(get_nfilter_request_var('y')), $grid_snap_value);

	$map->ReadConfig($mapfile);

	$map->timex = $x;
	$map->timey = $y;

	$map->WriteConfig($mapfile);
}

function viaLink($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$x = intval(get_nfilter_request_var('x'));
	$y = intval(get_nfilter_request_var('y'));

	$link_name = wm_editor_sanitize_name(get_nfilter_request_var('link_name'));

	$map->ReadConfig($mapfile);

	if (isset($map->links[$link_name])) {
	    $map->links[$link_name]->vialist = array(array(0 =>$x, 1=>$y));
	    $map->WriteConfig($mapfile);
	}
}

function moveNode($mapfile, $grid_snap_value) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$x = snap(intval(get_nfilter_request_var('x')), $grid_snap_value);
	$y = snap(intval(get_nfilter_request_var('y')), $grid_snap_value);

	$node_name = wm_editor_sanitize_name(get_nfilter_request_var('node_name'));

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

				    $l_old  = sqrt($dx_old*$dx_old + $dy_old*$dy_old);
				    $l_new  = sqrt($dx_new*$dx_new + $dy_new*$dy_new);

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
				    rotateAboutPoint($points, $pivx, $pivy, deg2rad($angle_old));

				    // do the scaling in here
				    for($i=0; $i<(count($points)/2); $i++) {
					    $basex = ($points[$i*2] - $pivx) * $scalefactor + $pivx;
					    $points[$i*2] = $basex;
				    }

				    // rotate back so that link is along the new direction
				    rotateAboutPoint($points, $pivx, $pivy, deg2rad(-$angle_new));

				    // now put the modified points back into the vialist again
				    $v = 0;
					$i = 0;

				    foreach($points as $p) {
					    // skip a point if it positioned relative to a node. Those shouldn't be rotated (well, IMHO)
					    if (!isset($link->vialist[$v][2])) {
						    $link->vialist[$v][$i] = $p;
					    }

					    $i++;

					    if ($i == 2) {
							$i = 0;
							$v++;
						}
				    }
			    }
		    }
	    }

	    $map->nodes[$node_name]->x = $x;
	    $map->nodes[$node_name]->y = $y;

	    $map->WriteConfig($mapfile);
	}
}

function linkTidy($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$target = wm_editor_sanitize_name(get_nfilter_request_var('param'));

	if (isset($map->links[$target])) {
		// draw a map and throw it away, to calculate all the bounding boxes
		$map->DrawMap('null');

		tidy_link($map, $target);

		$map->WriteConfig($mapfile);
	}
}

function reTidy($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	// draw a map and throw it away, to calculate all the bounding boxes
	$map->DrawMap('null');
	retidy_links($map);

	$map->WriteConfig($mapfile);
}

function reTidyAll($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	// draw a map and throw it away, to calculate all the bounding boxes
	$map->DrawMap('null');
	retidy_links($map,true);

	$map->WriteConfig($mapfile);
}

function unTidy($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	// draw a map and throw it away, to calculate all the bounding boxes
	$map->DrawMap('null');
	untidy_links($map);

	$map->WriteConfig($mapfile);
}

function deleteLink($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$target = wm_editor_sanitize_name(get_nfilter_request_var('param'));
	$log = 'delete link ' . $target;

	if (isset($map->links[$target])) {
	    unset($map->links[$target]);

	    $map->WriteConfig($mapfile);
	}
}

function addNode($mapfile, $grid_snap_value) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$x = snap(intval(get_nfilter_request_var('x')), $grid_snap_value);
	$y = snap(intval(get_nfilter_request_var('y')), $grid_snap_value);

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
}

function editorSettings($mapfile) {
	global $use_overlay, $use_relative_overlay, $grid_snap_value;

	$map = new WeatherMap;

	$map->context = 'editor';

	// have to do this, otherwise the editor will be unresponsive afterwards - not actually going to change anything!
	$map->ReadConfig($mapfile);

	$use_overlay          = (isset_request_var('editorsettings_showvias') ? intval(get_nfilter_request_var('editorsettings_showvias')) : false);
	$use_relative_overlay = (isset_request_var('editorsettings_showrelative') ? intval(get_nfilter_request_var('editorsettings_showrelative')) : false);
	$grid_snap_value      = (isset_request_var('editorsettings_gridsnap') ? intval(get_nfilter_request_var('editorsettings_gridsnap')) : 0);
}

function deleteNode($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$target = wm_editor_sanitize_name(get_nfilter_request_var('param'));

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
}

function cloneNode($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	$target = wm_editor_sanitize_name(get_nfilter_request_var('param'));

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
}

function displayFontSamples($mapfile) {
	$map = new WeatherMap;

	$map->context = 'editor';

	$map->ReadConfig($mapfile);

	ksort($map->fonts);
	header('Content-type: image/png');

	$keyfont = 2;
	$keyheight = imagefontheight($keyfont)+2;

	$sampleheight = 32;
	// $im = imagecreate(250,imagefontheight(5)+5);
	$im    = imagecreate(2000,$sampleheight);
	$imkey = imagecreate(2000,$keyheight);

	$white    = imagecolorallocate($im,255,255,255);
	$black    = imagecolorallocate($im,0,0,0);
	$whitekey = imagecolorallocate($imkey,255,255,255);
	$blackkey = imagecolorallocate($imkey,0,0,0);

	$x = 3;

	foreach ($map->fonts as $fontnumber => $font) {
		$string = 'Abc123%';
		$keystring = "Font $fontnumber";

		list($width,$height)   = $map->myimagestringsize($fontnumber, $string);
		list($kwidth,$kheight) = $map->myimagestringsize($keyfont, $keystring);

		if ($kwidth > $width) {
			$width = $kwidth;
		}

		$y = ($sampleheight / 2) + ($height / 2);

		$map->myimagestring($im, $fontnumber, $x, $y, $string, $black);
		$map->myimagestring($imkey, $keyfont, $x, $keyheight, "Font $fontnumber", $blackkey);

		$x = $x + $width + 6;
	}

	$im2 = imagecreate($x,$sampleheight + $keyheight);

	imagecopy($im2, $im, 0, 0, 0, 0, $x, $sampleheight);
	imagecopy($im2,$imkey, 0, $sampleheight, 0, 0, $x, $keyheight);

	imagedestroy($im);

	imagepng($im2);

	imagedestroy($im2);
}

function fixMapBackgroundAndImages(&$map) {
	global $config;

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
}

function getImageURL($mapname, $selected) {
	// now we'll just draw the full editor page, with our new knowledge
	$imageurl = 'weathermap-cacti-plugin-editor.php?mapname='. $mapname . '&action=draw';

	if ($selected != '') {
	    $imageurl .= '&selected=' . wm_editor_sanitize_selected($selected);
	}

	$imageurl .= '&unique=' . time();

	return $imageurl;
}
