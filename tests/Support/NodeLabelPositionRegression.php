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

require_once __DIR__.'/../bootstrap-unit.php';
chdir(dirname(__DIR__,2));
function plugin_weathermap_numeric_version(){return '2.0';}
require 'lib/WeatherMap.class.php';
function label_check($ok,$message){if(!$ok)throw new Exception($message);}
$dir=sys_get_temp_dir().'/wm-label-position-'.bin2hex(random_bytes(6));mkdir($dir);
$file=$dir.'/map.conf';
try {
 $cfg="WIDTH 500\nHEIGHT 400\n";
 foreach([96,32] as $row=>$size) foreach(['N60','C','S60'] as $i=>$position) {
  $x=90+$i*150;$y=100+$row*150;$name='n'.$size.'_'.$i;
  $cfg.="NODE $name\n LABEL NAS\n ICON $size $size images/objects/corporate-building.png\n POSITION $x $y\n LABELOFFSET $position\n SET editor_label_position $position\n\n";
 }
 $cfg.="NODE raw\n LABEL NAS\n ICON 32 32 images/objects/corporate-building.png\n POSITION 20 20\n LABELOFFSET N60\n";
 file_put_contents($file,$cfg);$map=new WeatherMap();$map->ReadConfig($file);$map->DrawMap($dir.'/positions.png');
 foreach([96,32] as $row=>$size) foreach(['N60','C','S60'] as $i=>$position) {
  $node=$map->nodes['n'.$size.'_'.$i];$boxes=$node->boundingboxes;$label=end($boxes);$y=($label[1]+$label[3])/2;$center=100+$row*150;
  label_check($i===0?$y<$center:($i===1?abs($y-$center)<2:$y>$center),'Rendered position '.$position.' size '.$size);
  label_check($label[1]>=$boxes[0][1] && $label[3]<=$boxes[0][3],'Label stays over icon size '.$size);
  label_check(strpos($node->asJS(),'labelposition:')!==false,'JS preset metadata');
  label_check(strpos($node->asJSON(),'"labelposition":')!==false,'JSON preset metadata');
 }
 $raw=$map->nodes['raw'];$rawLabel=end($raw->boundingboxes);
 label_check($rawLabel[1]<$raw->boundingboxes[0][1],'Existing raw N60 placement retains its legacy offset');
 $map->WriteConfig($file);$loaded=new WeatherMap();$loaded->ReadConfig($file);
 foreach([96,32] as $size) foreach(['N60','C','S60'] as $i=>$position) {
  $node=$loaded->nodes['n'.$size.'_'.$i];
  label_check($node->labeloffset===$position && $node->get_hint('editor_label_position')===$position,'Preset round trip '.$position);
 }
 label_check($loaded->nodes['raw']->get_hint('editor_label_position','')==='','Raw offset is not converted to a preset');
 echo "PASS: Top/Middle/Bottom render over icons and survive configuration round trip\n";
} finally {foreach(new DirectoryIterator($dir) as $p){if(!$p->isDot())unlink($p->getPathname());}rmdir($dir);}
