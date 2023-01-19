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

// Shared code for DSStats and RRD DS plugins
//
function UpdateCactiData(&$item, $local_data_id) {
	$map = $item->owner;

	wm_debug("fetching for $local_data_id");

	if (isset($map->dsinfocache[$local_data_id])) {
		$to_set = $map->dsinfocache[$local_data_id];
	} else {
		$to_set = array();

		$set_speed = intval($item->get_hint('cacti_use_ifspeed'));

		$r3 = db_fetch_assoc_prepared('SELECT dl.host_id, field_name, field_value
			FROM data_local AS dl
			INNER JOIN host_snmp_cache AS hsc USE INDEX (host_id)
			ON dl.host_id = hsc.host_id
			AND dl.snmp_index = hsc.snmp_index
			AND dl.snmp_query_id = hsc.snmp_query_id
			WHERE dl.id = ?',
			array($local_data_id));

		foreach ($r3 as $vv) {
			$vname = 'cacti_' . $vv['field_name'];
			$to_set[$vname] = $vv['field_value'];
		}

		if ($set_speed != 0) {
			$ifSpeed     = intval($to_set['cacti_ifSpeed']);
			$ifHighSpeed = intval($to_set['cacti_ifHighSpeed']);

			$speed = 0;

			if ($ifSpeed > 0) {
				$speed = $ifSpeed;
			}

			# see https://lists.oetiker.ch/pipermail/mrtg/2004-November/029312.html
			if ($ifHighSpeed > 20) {
				// NOTE: this is NOT using $kilo - it's always 1000000 bits/sec according to the MIB
				$speed = $ifHighSpeed * 1000000;
			}

			if ($speed > 0) {
				// might need to dust these off for php4...
				if ($item->my_type() == 'NODE') {
					$map->nodes[$item->name]->max_bandwidth_in = $speed;
					$map->nodes[$item->name]->max_bandwidth_out = $speed;
				}

				if ($item->my_type() == 'LINK') {
					$map->links[$item->name]->max_bandwidth_in = $speed;
					$map->links[$item->name]->max_bandwidth_out = $speed;
				}
			}
		}

		if (isset($vv['host_id'])) {
			$to_set['cacti_host_id'] = intval($vv['host_id']);
		}

		$r4 = db_fetch_row_prepared('SELECT gti.local_graph_id, title_cache
			FROM graph_templates_item AS gti
			INNER JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id = gti.local_graph_id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			AND dtr.local_data_id = ?
			LIMIT 1',
			array($local_data_id));

		if (isset($r4['local_graph_id'])) {
			$to_set['cacti_graph_id'] = intval($r4['local_graph_id']);
		}

		$map->dsinfocache[$local_data_id] = $to_set;
	}

	# By now, we have the values, one way or another.

	foreach ($to_set as $k=>$v) {
		$item->add_note($k, $v);
	}
}

