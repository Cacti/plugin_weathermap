<?php
function get_current_page() { return $GLOBALS['page']; }
function get_nfilter_request_var($key) { return $GLOBALS['request'][$key] ?? null; }
function get_filter_request_var($key) { return filter_var($GLOBALS['request'][$key] ?? null,FILTER_VALIDATE_INT); }
define('RRD_NL',"\n");
$source=file_get_contents(__DIR__ . '/../../setup.php');$start=strpos($source,'function weathermap_hover_graph_options(');$end=strpos($source,"\n/**",$start);eval(substr($source,$start,$end-$start));
$graph=['graph_opts'=>"--width=300\n",'graph_id'=>108,'graph_defs'=>'keep'];
$GLOBALS['page']='graph_image.php';$GLOBALS['request']=['wm_hidpi'=>'2','graph_width'=>'300','graph_height'=>'100'];
$result=weathermap_hover_graph_options($graph);if($result['graph_opts']!==$graph['graph_opts']."--zoom 2\n" || $result['graph_defs']!=='keep')throw new Exception('Density request failed');
if(weathermap_hover_graph_options($result)!==$result)throw new Exception('Double zoom');
foreach([['wm_hidpi'=>'3'],['wm_hidpi'=>['2']],['graph_width'=>3000],['graph_height'=>0]] as $change){$GLOBALS['request']=array_merge(['wm_hidpi'=>'2','graph_width'=>'300','graph_height'=>'100'],$change);if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Invalid request changed options');}
$GLOBALS['request']=[];if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Normal graph changed');
$GLOBALS['page']='graph.php';$GLOBALS['request']=['wm_hidpi'=>'2','graph_width'=>300,'graph_height'=>100];if(weathermap_hover_graph_options($graph)!==$graph)throw new Exception('Other page changed');
echo "PASS: marked compact requests only, bounded scaling, no duplicate zoom and unchanged normal graphs\n";
