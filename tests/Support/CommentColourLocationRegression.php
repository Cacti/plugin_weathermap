<?php
require_once __DIR__.'/../bootstrap-unit.php';chdir(dirname(__DIR__,2));
function plugin_weathermap_numeric_version(){return '2.0';}
require 'lib/WeatherMap.class.php';require 'lib/editor.comment-style.php';require 'lib/editor.colours.php';
function colour_check($ok,$msg){if(!$ok)throw new Exception($msg);}
$file=sys_get_temp_dir().'/wm-comment-colour-'.bin2hex(random_bytes(6)).'.conf';
try {
 file_put_contents($file,"NODE a\n POSITION 10 10\nNODE b\n POSITION 100 100\nLINK normal\n NODES a b\nLINK custom\n NODES a b\n COMMENTFONTCOLOR 255 0 0\n");
 $m=new WeatherMap();$m->ReadConfig($file);$data=wm_asset_colour_data($m);$data['commentcolour']='12 34 56';wm_asset_save_colours($m,$data);
 colour_check($m->links['DEFAULT']->commentfontcolour===[12,34,56],'Default changes');
 colour_check($m->links['normal']->commentfontcolour===[12,34,56],'Matching links change');
 colour_check($m->links['custom']->commentfontcolour==[255,0,0],'Custom link retained');
 wm_comment_apply_style($m,null,null,'1');colour_check($m->links['DEFAULT']->commentfontcolour===[12,34,56],'Map Style retains colour');
 $m->WriteConfig($file);$n=new WeatherMap();$n->ReadConfig($file);colour_check(wm_asset_colour_data($n)['commentcolour']==='12 34 56','Custom round trip');
 $data=wm_asset_colour_data($n);$data['commentcolour']='contrast';wm_asset_save_colours($n,$data);colour_check($n->links['DEFAULT']->commentfontcolour===[-3,-3,-3],'Contrast supported');
 unset($data['commentcolour']);wm_asset_save_colours($n,$data);colour_check($n->links['DEFAULT']->commentfontcolour===[-3,-3,-3],'Omitted value retained');
 $data['commentcolour']='999 0 0';try{wm_asset_save_colours($n,$data);throw new Exception('Invalid colour accepted');}catch(InvalidArgumentException $e){}
 colour_check(strpos(wm_comment_style_fields($n),'mapstyle_commentcolour')===false,'Removed from Map Style');
 echo "PASS: comment colour in Manage Colours, custom/contrast persistence and Map Style isolation\n";
} finally {if(is_file($file))unlink($file);}
