<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function get_nfilter_request_var($key) { return ['term'=>'','graph_ids'=>'1,2','include_rrds'=>'1','paged'=>'1'][$key] ?? ''; }
function get_filter_request_var($key) { return 0; }
function cacti_sizeof($array) { return count($array); }
function is_graph_allowed($id) { return $id === 1; }
function __($text,$domain) { return $text; }
function db_fetch_assoc_prepared($query,$params) {
 if (strpos($query,'SELECT DISTINCT gtg.') !== false) return [['id'=>1,'title'=>'Allowed'],['id'=>2,'title'=>'Denied']];
 if ($params !== [1]) throw new Exception('RRD query includes unauthorised graph');
 return [['id'=>1,'path'=>'<path_rra>/router_ping.rrd'],['id'=>1,'path'=>'<path_rra>/router_ping.rrd'],['id'=>1,'path'=>'/rra/other.rrd']];
}
require __DIR__ . '/../../lib/editor.inc.php';
ob_start();display_graphs();$data=json_decode(ob_get_clean(),true);
if (count($data['items'])!==1 || $data['items'][0]['rrds']!==['router_ping.rrd','other.rrd']) throw new Exception('Incorrect RRD metadata');
echo "PASS: graph RRD filenames are deduplicated and restricted to permitted graph IDs\n";
