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

require_once __DIR__.'/../bootstrap-unit.php';chdir(dirname(__DIR__,2));
function plugin_weathermap_numeric_version(){return '2.0';}
require 'lib/WeatherMap.class.php';require 'lib/editor.comment-style.php';require 'lib/editor.colours.php';
function colour_check($ok,$msg){if(!$ok)throw new Exception($msg);}
$file=sys_get_temp_dir().'/wm-comment-colour-'.bin2hex(random_bytes(6)).'.conf';
try {
 file_put_contents($file,"NODE a\n POSITION 10 10\nNODE b\n POSITION 100 100\nLINK normal\n NODES a b\nLINK custom\n NODES a b\n COMMENTFONTCOLOR 255 0 0\n");
 $m=new WeatherMap();$m->ReadConfig($file);
 $m->add_hint('rrd_default_in_ds','octets_in');$m->add_hint('rrd_default_out_ds','octets_out');$m->add_hint('rrd_default_path','/rrd');
 $GLOBALS['config']['rra_path']='/var/lib/cacti/rra';
 preg_match('/var wmRrdSourceDefaults = (.*);/', $m->asJS(), $defaults);
 colour_check(json_decode($defaults[1],true) === ['input'=>'octets_in','output'=>'octets_out','path'=>'/rrd','rraPath'=>'/var/lib/cacti/rra'], 'Map RRD defaults reach source identity matching');$data=wm_asset_colour_data($m);$data['commentcolour']='12 34 56';wm_asset_save_colours($m,$data);
 colour_check($m->links['DEFAULT']->commentfontcolour===[12,34,56],'Default changes');
 colour_check($m->links['normal']->commentfontcolour===[12,34,56],'Matching links change');
 colour_check($m->links['custom']->commentfontcolour==[255,0,0],'Custom link retained');
 wm_comment_apply_style($m,null,null);colour_check($m->links['DEFAULT']->commentfontcolour===[12,34,56],'Map Style retains colour');
 $m->WriteConfig($file);$n=new WeatherMap();$n->ReadConfig($file);colour_check(wm_asset_colour_data($n)['commentcolour']==='12 34 56','Custom round trip');
 $data=wm_asset_colour_data($n);$data['commentcolour']='contrast';wm_asset_save_colours($n,$data);colour_check($n->links['DEFAULT']->commentfontcolour===[-3,-3,-3],'Contrast supported');
 unset($data['commentcolour']);wm_asset_save_colours($n,$data);colour_check($n->links['DEFAULT']->commentfontcolour===[-3,-3,-3],'Omitted value retained');
 $data['commentcolour']='999 0 0';try{wm_asset_save_colours($n,$data);throw new Exception('Invalid colour accepted');}catch(InvalidArgumentException $e){}
 colour_check(strpos(wm_comment_style_fields($n),'mapstyle_commentcolour')===false,'Removed from Map Style');
 echo "PASS: comment colour in Manage Colours, custom/contrast persistence and Map Style isolation\n";
} finally {if(is_file($file))unlink($file);}
