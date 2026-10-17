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

function get_current_page() { return $GLOBALS['page']; }
function get_nfilter_request_var($key) { return $GLOBALS['request'][$key] ?? null; }
function get_filter_request_var($key) { return filter_var($GLOBALS['request'][$key] ?? null,FILTER_VALIDATE_INT); }
define('RRD_NL',"\n");
require __DIR__ . '/../../setup.php';
$graph=['graph_opts'=>"--width=300\n",'graph_id'=>108,'graph_defs'=>'keep'];
$GLOBALS['page']='graph_image.php';$GLOBALS['request']=['wm_hidpi'=>'2','graph_width'=>'300','graph_height'=>'100'];
$result=weathermap_hover_graph_options($graph);if($result['graph_opts']!==$graph['graph_opts']."--zoom 2\n" || $result['graph_defs']!=='keep')throw new Exception('Density request failed');
if(weathermap_hover_graph_options($result)!==$result)throw new Exception('Double zoom');
foreach([['wm_hidpi'=>'3'],['wm_hidpi'=>['2']],['graph_width'=>3000],['graph_height'=>0]] as $change){$GLOBALS['request']=array_merge(['wm_hidpi'=>'2','graph_width'=>'300','graph_height'=>'100'],$change);if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Invalid request changed options');}
$GLOBALS['request']=[];if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Normal graph changed');
$GLOBALS['page']='graph.php';$GLOBALS['request']=['wm_hidpi'=>'2','graph_width'=>300,'graph_height'=>100];if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Other page changed');
echo "PASS: marked compact requests only, bounded scaling, no duplicate zoom and unchanged normal graphs\n";
