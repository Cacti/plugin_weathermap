<?php
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
$source=file_get_contents(__DIR__.'/../../lib/editor.inc.php');$start=strpos($source,'function display_graphs()');$end=strpos($source,'function display_datasources()');
// Evaluate just this declaration, excluding the following function's docblock.
eval(substr($source,$start,$end-$start));
ob_start();display_graphs();$data=json_decode(ob_get_clean(),true);
if (count($data['items'])!==1 || $data['items'][0]['rrds']!==['router_ping.rrd','other.rrd']) throw new Exception('Incorrect RRD metadata');
echo "PASS: graph RRD filenames are deduplicated and restricted to permitted graph IDs\n";
