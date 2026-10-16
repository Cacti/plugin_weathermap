<?php
require_once __DIR__.'/../bootstrap-unit.php';
chdir(dirname(__DIR__,2));
function plugin_weathermap_numeric_version(){return '2.0';}
require 'lib/WeatherMap.class.php';
function label_check($ok,$message){if(!$ok)throw new Exception($message);}
$dir=sys_get_temp_dir().'/wm-label-position-'.bin2hex(random_bytes(6));mkdir($dir);
$file=$dir.'/map.conf';
try {
 $cfg="WIDTH 500\nHEIGHT 200\n";
 foreach(['N60','C','S60'] as $i=>$position){$x=90+$i*150;$cfg.="NODE n$i\n LABEL NAS\n ICON images/objects/corporate-building.png\n POSITION $x 100\n LABELOFFSET $position\n\n";}
 file_put_contents($file,$cfg);$map=new WeatherMap();$map->ReadConfig($file);$map->DrawMap($dir.'/positions.png');
 foreach(['N60','C','S60'] as $i=>$position){
  $node=$map->nodes['n'.$i];$boxes=$node->boundingboxes;$label=end($boxes);$y=($label[1]+$label[3])/2;
  label_check($i===0?$y<90:($i===1?abs($y-100)<2:$y>110),'Rendered position '.$position);
  label_check($label[1]>=$boxes[0][1] && $label[3]<=$boxes[0][3],'Label stays over icon');
  label_check(strpos($node->asJS(),'labeloffset:')!==false,'JS metadata');
  label_check(strpos($node->asJSON(),'"labeloffset":')!==false,'JSON metadata');
 }
 $map->WriteConfig($file);$loaded=new WeatherMap();$loaded->ReadConfig($file);
 foreach(['N60','C','S60'] as $i=>$position)label_check($loaded->nodes['n'.$i]->labeloffset===$position,'Round trip '.$position);
 echo "PASS: Top/Middle/Bottom render over icons and survive configuration round trip\n";
} finally {foreach(new DirectoryIterator($dir) as $p){if(!$p->isDot())unlink($p->getPathname());}rmdir($dir);}
