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

/** editor.inc.php
 *
 * All the functions used by the editor.
 */

/** @function fix_gpc_string
  *
  * Take a string (that we got from $_REQUEST) and make it back to how the
  * user TYPED it, regardless of whether magic_quotes_gpc is turned on or off.
  *
  * @param string $input String to fix
  *
  * @returns string Fixed string
  *
  */
function fix_gpc_string($input) {
	if (true == function_exists('get_magic_quotes_gpc') && 1 == get_magic_quotes_gpc()) {
		$input = stripslashes($input);
	}

	return ($input);
}

function display_graphs() {
	$sql_where  = '';
	$sql_params = array();

	if (get_nfilter_request_var('term') != '') {
		$sql_where .= 'WHERE title_cache LIKE ' . db_qstr('%' . get_nfilter_request_var('term') . '%') . ' AND local_graph_id > 0';
	} else {
		$sql_where .= 'WHERE local_graph_id > 0';
	}

	if (get_nfilter_request_var('graph_template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.graph_template_id = ?';

		$sql_params[] = get_request_var('graph_template_id');
	}

	if (get_request_var('target') == 'link_target_picker') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.snmp_query_id = (SELECT id FROM snmp_query WHERE hash = "d75e406fdeca4fcef45b8be3a9a63cbc")';
	}

	$graphs = db_fetch_assoc_prepared("SELECT DISTINCT
		gtg.local_graph_id AS id,
		gtg.title_cache AS title,
		gt.name AS template_name
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON gt.id=gtg.graph_template_id
		LEFT JOIN graph_local AS gl
		ON gtg.local_graph_id = gl.id
		LEFT JOIN host as h
		ON gl.host_id = h.id
		$sql_where
		ORDER BY title_cache
		LIMIT " . read_config_option('autocomplete_rows'),
		$sql_params);

	$return = array();

	if (cacti_sizeof($graphs)) {
		foreach($graphs as $index => $g) {
			if (!is_graph_allowed($g['id'])) {
				unset($graphs[$index]);
			} else {
				$return[] = array('label' => $g['title'], 'value' => $g['title'], 'id' => $g['id']);
			}
		}
	}

	print json_encode($return);
}

function display_datasources() {
	$sql_where = '';

	if (get_nfilter_request_var('term') != '') {
		$sql_where .= 'WHERE name_cache LIKE ' . db_qstr('%' . get_nfilter_request_var('term') . '%') . ' AND local_graph_id > 0';
	} else {
		$sql_where .= 'WHERE local_graph_id > 0';
	}

	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'dl.snmp_query_id = (SELECT id FROM snmp_query WHERE hash = "d75e406fdeca4fcef45b8be3a9a63cbc")';

	$graphs = db_fetch_assoc("SELECT DISTINCT
		gti.local_graph_id AS id,
		dtd.name_cache AS title,
		dtd.data_source_path AS path
		FROM data_template_data AS dtd
		INNER JOIN data_local AS dl
		ON dl.id = dtd.local_data_id
		INNER JOIN data_template_rrd AS dtr
		ON dtd.local_data_id = dtr.local_data_id
		INNER JOIN (
			SELECT DISTINCT graph_template_id, local_graph_id, task_item_id
			FROM graph_templates_item
			WHERE local_graph_id > 0
		) AS gti
		ON gti.task_item_id = dtr.id
		INNER JOIN graph_templates AS gt
		ON gt.id = gti.graph_template_id
		$sql_where
		ORDER BY name_cache
		LIMIT " . read_config_option('autocomplete_rows'));

	$return = array();

	if (cacti_sizeof($graphs)) {
		foreach($graphs as $index => $g) {
			if (!is_graph_allowed($g['id'])) {
				unset($graphs[$index]);
			} else {
				$return[] = array('label' => $g['title'], 'value' => $g['title'], 'id' => trim(str_replace('<path_rra>', '', $g['path']), '/'), 'local_graph_id' => $g['id']);
			}
		}
	}

	print json_encode($return, true);
}

/**
 * Clean up URI (function taken from Cacti) to protect against XSS
 */
function wm_editor_sanitize_uri($str) {
	static $drop_char_match   =   array(' ','^', '$', '<', '>', '`', '\'', '"', '|', '+', '[', ']', '{', '}', ';', '!', '%');
	static $drop_char_replace = array('', '', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '',  '', '');

	return str_replace($drop_char_match, $drop_char_replace, urldecode($str));
}

// much looser sanitise for general strings that shouldn't have HTML in them
function wm_editor_sanitize_string($str) {
	static $drop_char_match   = array('<', '>' );
	static $drop_char_replace = array('', '');

	return str_replace($drop_char_match, $drop_char_replace, html_escape($str));
}

function wm_editor_validate_bandwidth($bw) {
	if (preg_match( '/^(\d+\.?\d*[KMGT]?)$/', $bw) ) {
		return true;
	}

	return false;
}

function wm_editor_validate_one_of($input,$valid=array(),$case_sensitive=false) {
	if (!$case_sensitive) {
		$input = strtolower($input);
	}

	foreach ($valid as $v) {
		if (!$case_sensitive) {
			$v = strtolower($v);
		}

		if ($v == $input) {
			return true;
		}
	}

	return false;
}

// Labels for Nodes, Links and Scales shouldn't have spaces in
function wm_editor_sanitize_name($str) {
	return str_replace(array(' '), '', $str);
}

function wm_editor_sanitize_selected($str) {
	$res = urldecode($str);

	if ( ! preg_match("/^(LINK|NODE):/",$res)) {
	    return "";
	}

	return wm_editor_sanitize_name($res);
}

function wm_editor_sanitize_file($filename,$allowed_exts=array()) {
	$filename = wm_editor_sanitize_uri($filename);

	if ($filename == "") {
		return "";
	}

	$ok = false;
	foreach ($allowed_exts as $ext) {
		$match = "." . $ext;

		if ( substr($filename, -strlen($match),strlen($match)) == $match) {
			$ok = true;
		}
	}

    if (!$ok) {
		return "";
	}

	return $filename;
}

function wm_editor_sanitize_conffile($filename) {
	$filename = wm_editor_sanitize_uri($filename);

	# If we've been fed something other than a .conf filename, just pretend it didn't happen
	if ( substr($filename,-5,5) != ".conf" ) {
		$filename = "";
	}

	# on top of the url stuff, we don't ever need to see a / in a config filename (CVE-2013-3739)
	if (strstr($filename,"/") !== false ) {
		$filename = "";
	}

	return $filename;
}

function show_editor_startpage() {
	global $mapdir, $config_loaded, $configerror;

	$matches = 0;

	print '<script src="js/editor.js" type="text/javascript"></script>';

	$errormessage = '';

	$weathermap_version = plugin_weathermap_numeric_version();

	if ($configerror != '') {
		$errormessage .= $configerror . '<p>';
	}

	html_start_box(__('Welcome to the PHP Weathermap %s Editor', $weathermap_version, 'weathermap'), '100%', '', '3', 'center', '');
	print '<tr>';
	print '<td>';
	print '<div><b>NOTE:</b> This editor is not finished! There are many features of Weathermap that you will be missing out on if you choose to use the editor only.  These include: curves, node offsets, font definitions, colour changing, per-node/per-link settings and image uploading. You CAN use the editor without damaging these features if you added them by hand, however.</div>';
	print '</td>';
	print '</tr>';
	html_end_box();

	print '<tr>';
	print 'Do you want to:<p>';
	print 'Create A New Map:<br>';
	print '<form method="GET">';
	print 'Named: <input type="text" name="mapname" size="20">';

	print '<input name="action" type="hidden" value="newmap">';

	print '<input type="submit" value="Create">';

	print '<p><small>Note: filenames must contain no spaces and end in .conf</small></p>';
	print '</form>';

	$titles = array();

	$errorstring="";

	if (is_dir($mapdir)) {
		$n=0;
		$dh=opendir($mapdir);

		if ($dh) {
		    while (false !== ($file = readdir($dh))) {
				$realfile = $mapdir . '/' . $file;
				$note     = "";

				// skip directories, unreadable files, .files and anything that doesn't come through the sanitiser unchanged
				if ((is_file($realfile)) && (is_readable($realfile)) && (!preg_match("/^\./",$file)) && (wm_editor_sanitize_conffile($file) == $file)) {
					if (!is_writable($realfile)) {
						$note .= "(read-only)";
					}

					$title='(no title)';
					$fd=fopen($realfile, "r");

					if ($fd) {
						while (!feof($fd)) {
							$buffer=fgets($fd, 4096);

							if (preg_match('/^\s*TITLE\s+(.*)/i', $buffer, $matches)) {
							    $title= wm_editor_sanitize_string($matches[1]);
							}
						}

						fclose ($fd);

						$titles[$file] = $title;
						$notes[$file]  = $note;

						$n++;
					}
				}
		    }

			closedir ($dh);
		} else {
			$errorstring = "Can't open mapdir to read.";
		}

		ksort($titles);

		if ($n == 0) {
			$errorstring = "No files in mapdir";
		}
	} else {
	    $errorstring = "NO DIRECTORY named $mapdir";
	}

	print 'OR<br />Create A New Map as a copy of an existing map:<br>';
	print '<form method="GET">';
	print 'Named: <input type="text" name="mapname" size="20"> based on ';

	print '<input name="action" type="hidden" value="newmapcopy">';
	print '<select name="sourcemap">';

	if ($errorstring == '') {
		foreach ($titles as $file=>$title) {
			$nicefile = html_escape($file);
			print "<option value=\"$nicefile\">$nicefile</option>\n";
		}
	} else {
		print '<option value="">'.html_escape($errorstring).'</option>';
	}

	print '</select>';
	print '<input type="submit" value="Create Copy">';
	print '</form>';
	print 'OR<br />';
	print 'Open An Existing Map (looking in ' . html_escape($mapdir) . '):<ul class="filelist">';

	if ($errorstring == '') {
		foreach ($titles as $file=>$title) {
			# $title = $titles[$file];
			$note      = $notes[$file];
			$nicefile  = html_escape($file);
			$nicetitle = html_escape($title);

			print "<li>$note<a href='?mapname=$nicefile'>$nicefile</a> - <span class='comment'>$nicetitle</span></li>";
		}
	} else {
		print '<li>' . html_escape($errorstring) . '</li>';
	}

	print '</ul>';

	print "</div>"; // dlgbody
	print '<div class="dlgHelp" id="start_help">PHP Weathermap ' . $weathermap_version
		. ' Copyright &copy; 2005-2019 Howard Jones - howie@thingy.com<br />The current version should always be <a href="http://www.network-weathermap.com/">available here</a>, along with other related software. PHP Weathermap is licensed under the GNU Public License, version 2. See COPYING for details. This distribution also includes the Overlib library by Erik Bosrup.</div>';

	print "</div>"; // dlgStart
	print "</div>"; // withjs
	print "</body></html>";
}

function snap($coord, $gridsnap = 0) {
	if ($gridsnap == 0) {
		return ($coord);
	} else {
		$rest = $coord % $gridsnap;

		return ($coord - $rest + round($rest/$gridsnap) * $gridsnap );
	}
}

function extract_with_validation($array, $paramarray, $prefix = "") {
	$all_present = true;
	$candidates  = array();

	foreach ($paramarray as $var) {
		$varname=$var[0];
		$vartype=$var[1];
		$varreqd=$var[2];

		if ($varreqd == 'req' && !array_key_exists($varname, $array)) {
	            $all_present=false;
        }

		if (array_key_exists($varname, $array)) {
			$varvalue=$array[$varname];

			$waspresent=$all_present;

			switch ($vartype) {
				case 'int':
					if (!preg_match('/^\-*\d+$/', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'float':
					if (!preg_match('/^\d+\.\d+$/', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'yesno':
					if (!preg_match('/^(y|n|yes|no)$/i', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'sqldate':
					if (!preg_match('/^\d\d\d\d\-\d\d\-\d\d$/i', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'any':
					// we don't care at all

					break;
				case 'ip':
					if (!preg_match( '/^((\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)(?:\.(\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)){3})$/', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'alpha':
					if (!preg_match('/^[A-Za-z]+$/', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'alphanum':
					if (!preg_match('/^[A-Za-z0-9]+$/', $varvalue)) {
						$all_present=false;
					}

					break;
				case 'bandwidth':
					if (!preg_match('/^\d+\.?\d*[KMGT]*$/i', $varvalue)) {
						$all_present=false;
					}

					break;
				default:
					// an unknown type counts as an error, really
					$all_present=false;

					break;
			}

			if ($all_present) {
				$candidates["{$prefix}{$varname}"]=$varvalue;
			}
		}
	}

	if ($all_present) {
	    foreach ($candidates as $key => $value) {
			$GLOBALS[$key]=$value;
	    }
	}

	return array($all_present,$candidates);
}

function get_imagelist($imagedir) {
	global $config;

	$imagelist = array();

	$imdir = $config['base_path'] . '/plugins/weathermap/images/' . $imagedir;

	if (is_dir($imdir)) {
		$n  = 0;
		$dh = opendir($imdir);

		if ($dh) {
			while ($file = readdir($dh)) {
				$realfile = $imdir . '/' . $file;
				$uri      = "images/$imagedir/$file";

				if (is_readable($realfile) && (preg_match('/\.(gif|jpg|png)$/i', $file))) {
					$imagelist[] = $uri;
					$n++;
				}
			}

			closedir ($dh);
		}
	}

	return ($imagelist);
}

function handle_inheritance(&$map, &$inheritables) {
	foreach ($inheritables as $inheritable) {
		$fieldname = $inheritable[1];
		$formname = $inheritable[2];
		$validation = $inheritable[3];

		$new = get_nfilter_request_var($formname);

		if ($validation != "") {
		    switch($validation) {
				case "int":
				    $new = intval($new);

				    break;
				case "float":
				    $new = floatval($new);

				    break;
		    }
		}

		$old = ($inheritable[0]=='node' ? $map->nodes['DEFAULT']->$fieldname : $map->links['DEFAULT']->$fieldname);

		if ($old != $new) {
			if ($inheritable[0]=='node') {
				$map->nodes['DEFAULT']->$fieldname = $new;

				foreach ($map->nodes as $node) {
					if ($node->name != ":: DEFAULT ::" && $node->$fieldname == $old) {
						$map->nodes[$node->name]->$fieldname = $new;
					}
				}
			}

			if ($inheritable[0]=='link') {
				$map->links['DEFAULT']->$fieldname = $new;

				foreach ($map->links as $link) {
					if ($link->name != ":: DEFAULT ::" && $link->$fieldname == $old) {
						$map->links[$link->name]->$fieldname = $new;
					}
				}
			}
		}
	}
}

function get_fontlist(&$map,$name,$current) {
	$output = '<select class="fontcombo" name="'.$name.'">';

	ksort($map->fonts);

	foreach ($map->fonts as $fontnumber => $font) {
		$output .= '<option ';

		if ($current == $fontnumber) {
			$output .= 'SELECTED';
		}

		$output .= ' value="'.$fontnumber.'">'.$fontnumber.' ('.$font->type.')</option>';
	}

	$output .= "</select>";

	return($output);
}

function range_overlaps($a_min, $a_max, $b_min, $b_max) {
	if ($a_min > $b_max) {
		return false;
	}

	if ($b_min > $a_max) {
		return false;
	}

	return true;
}

function common_range ($a_min,$a_max, $b_min, $b_max) {
	$min_overlap = max($a_min, $b_min);
	$max_overlap = min($a_max, $b_max);

	return array($min_overlap,$max_overlap);
}

/**
 * distance - find the distance between two points
 *
 */
function distance($ax, $ay, $bx, $by) {
	$dx = $bx - $ax;
	$dy = $by - $ay;

	return sqrt($dx * $dx + $dy * $dy );
}

function tidy_links(&$map, $targets, $ignore_tidied=false) {
	// not very efficient, but it saves looking for special cases (a->b & b->a together)
	$ntargets = count($targets);
	$i = 1;

	foreach ($targets as $target) {
		tidy_link($map, $target, $i, $ntargets, $ignore_tidied);
		$i++;
	}
}

/**
 * tidy_link - change link offsets so that link is horizonal or vertical, if possible.
 *             if not possible, change offsets to the closest facing compass points
 */
function tidy_link(&$map,$target, $linknumber=1, $linktotal=1, $ignore_tidied=false) {
	// print "\n-----------------------------------\nTidying $target...\n";
	if (isset($map->links[$target]) && isset($map->links[$target]->a)) {
		$node_a = $map->links[$target]->a;
		$node_b = $map->links[$target]->b;

		$new_a_offset = "0:0";
		$new_b_offset = "0:0";

		// Update TODO: if the nodes are already directly left/right or up/down, then use compass-points, not pixel offsets
		// (e.g. N90) so if the label changes, they won't need to be re-tidied

		// First bounding box in the node's boundingbox array is the icon, if there is one, or the label if not.
		$bb_a = $node_a->boundingboxes[0];
		$bb_b = $node_b->boundingboxes[0];

		// figure out if they share any x or y coordinates
		$x_overlap = range_overlaps($bb_a[0], $bb_a[2], $bb_b[0], $bb_b[2]);
		$y_overlap = range_overlaps($bb_a[1], $bb_a[3], $bb_b[1], $bb_b[3]);

		$a_x_offset = 0; $a_y_offset = 0;
		$b_x_offset = 0; $b_y_offset = 0;

		// if they are side by side, and there's some common y coords, make link horizontal
		if (!$x_overlap && $y_overlap) {
			// print "SIDE BY SIDE\n";

			// snap the X coord to the appropriate edge of the node
			if ($bb_a[2] < $bb_b[0]) {
				$a_x_offset = $bb_a[2] - $node_a->x;
				$b_x_offset = $bb_b[0] - $node_b->x;
			}

			if ($bb_b[2] < $bb_a[0]) {
				$a_x_offset = $bb_a[0] - $node_a->x;
				$b_x_offset = $bb_b[2] - $node_b->x;
			}

			// this should be true whichever way around they are
			list($min_overlap,$max_overlap) = common_range($bb_a[1],$bb_a[3],$bb_b[1],$bb_b[3]);

			$overlap = $max_overlap - $min_overlap;
			$n = $overlap/($linktotal+1);

			$a_y_offset = $min_overlap + ($linknumber*$n) - $node_a->y;
			$b_y_offset = $min_overlap + ($linknumber*$n) - $node_b->y;

			$new_a_offset = sprintf("%d:%d", $a_x_offset,$a_y_offset);
			$new_b_offset = sprintf("%d:%d", $b_x_offset,$b_y_offset);
		}

		// if they are above and below, and there's some common x coords, make link vertical
		if ( !$y_overlap && $x_overlap ) {
			// print "ABOVE/BELOW\n";

			// snap the Y coord to the appropriate edge of the node
			if ($bb_a[3] < $bb_b[1]) {
				$a_y_offset = $bb_a[3] - $node_a->y;
				$b_y_offset = $bb_b[1] - $node_b->y;
			}

			if ($bb_b[3] < $bb_a[1]) {
				$a_y_offset = $bb_a[1] - $node_a->y;
				$b_y_offset = $bb_b[3] - $node_b->y;
			}

			list($min_overlap,$max_overlap) = common_range($bb_a[0],$bb_a[2],$bb_b[0],$bb_b[2]);

			$overlap = $max_overlap - $min_overlap;
			$n = $overlap/($linktotal+1);

			// move the X coord to the centre of the overlapping area
			$a_x_offset = $min_overlap + ($linknumber*$n) - $node_a->x;
			$b_x_offset = $min_overlap + ($linknumber*$n) - $node_b->x;

			$new_a_offset = sprintf("%d:%d", $a_x_offset,$a_y_offset);
			$new_b_offset = sprintf("%d:%d", $b_x_offset,$b_y_offset);
		}

		// if no common coordinates, figure out the best diagonal...
		if (!$y_overlap && !$x_overlap) {
			$pt_a = new WMPoint($node_a->x, $node_a->y);
			$pt_b = new WMPoint($node_b->x, $node_b->y);

			$line = new WMLineSegment($pt_a, $pt_b);

			$tangent = $line->vector;
			$tangent->normalise();

			$normal = $tangent->getNormal();

			$pt_a->AddVector( $normal, 15 * ($linknumber-1) );
			$pt_b->AddVector( $normal, 15 * ($linknumber-1) );

			$a_x_offset = $pt_a->x - $node_a->x;
			$a_y_offset = $pt_a->y - $node_a->y;

			$b_x_offset = $pt_b->x - $node_b->x;
			$b_y_offset = $pt_b->y - $node_b->y;

			$new_a_offset = sprintf("%d:%d", $a_x_offset,$a_y_offset);
			$new_b_offset = sprintf("%d:%d", $b_x_offset,$b_y_offset);
		}

		// if no common coordinates, figure out the best diagonal...
		// currently - brute force search the compass points for the shortest distance
		// potentially - intersect link line with rectangles to get exact crossing point
		if (1==0 && !$y_overlap && !$x_overlap) {
			// print "DIAGONAL\n";

			$corners = array("NE","E","SE","S","SW","W","NW","N");

			// start with what we have now
			$best_distance = distance( $node_a->x, $node_a->y, $node_b->x, $node_b->y );
			$best_offset_a = "C";
			$best_offset_b = "C";

			foreach ($corners as $corner1) {
				list ($ax,$ay) = calc_offset($corner1, $bb_a[2] - $bb_a[0], $bb_a[3] - $bb_a[1]);

				$axx = $node_a->x + $ax;
				$ayy = $node_a->y + $ay;

				foreach ($corners as $corner2) {
					list($bx,$by) = calc_offset($corner2, $bb_b[2] - $bb_b[0], $bb_b[3] - $bb_b[1]);

					$bxx = $node_b->x + $bx;
					$byy = $node_b->y + $by;

					$d = distance($axx,$ayy, $bxx, $byy);

					if ($d < $best_distance) {
						// print "from $corner1 ($axx, $ayy) to $corner2 ($bxx, $byy): ";
						// print "NEW BEST $d\n";
						$best_distance = $d;
						$best_offset_a = $corner1;
						$best_offset_b = $corner2;
					}
				}
			}

			// Step back a bit from the edge, to hide the corners of the link
			$new_a_offset = $best_offset_a . '85';
			$new_b_offset = $best_offset_b . '85';
		}

		// unwritten/implied - if both overlap, you're doing something weird and you're on your own
		// finally, update the offsets
		$map->links[$target]->a_offset = $new_a_offset;
		$map->links[$target]->b_offset = $new_b_offset;

		// and also add a note that this link was tidied, and is eligible for automatic tidying
		$map->links[$target]->add_hint('_tidied', 1);
	}
}

function untidy_links(&$map) {
	foreach ($map->links as $link) {
		$link->a_offset = 'C';
		$link->b_offset = 'C';
	}
}

function retidy_links(&$map, $ignore_tidied=false) {
	$routes = array();
	$done = array();

	foreach ($map->links as $link) {
		if (isset($link->a)) {
			$route = $link->a->name . ' ' . $link->b->name;

			if (strcmp( $link->a->name, $link->b->name) > 0) {
				$route = $link->b->name . ' ' . $link->a->name;
			}

			$routes[$route][] = $link->name;
		}
	}

	foreach ($map->links as $link) {
		if (isset($link->a)) {
			$route = $link->a->name . ' ' . $link->b->name;

			if (strcmp($link->a->name, $link->b->name) > 0) {
				$route = $link->b->name . ' ' . $link->a->name;
			}

			if (($ignore_tidied || $link->get_hint('_tidied') == 1) && !isset($done[$route]) && isset($routes[$route])) {
				if ( sizeof($routes[$route]) == 1) {
					tidy_link($map, $link->name);

					$done[$route] = 1;
				} else {
					# handle multi-links specially...
					tidy_links($map, $routes[$route]);

					// mark it so we don't do it again when the other links come by
					$done[$route] = 1;
				}
			}
		}
	}
}

function editor_log($str) {
    // $f = fopen('editor.log','a');
    // fputs($f, $str);
    // fclose($f);
}

