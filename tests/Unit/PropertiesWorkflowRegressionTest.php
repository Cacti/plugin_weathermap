<?php
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PropertiesWorkflowRegressionTest extends TestCase {
 public function testNodeLabelRenderingAndRoundTrip(): void {
  ob_start();try {require __DIR__.'/../Support/NodeLabelPositionRegression.php';$result=ob_get_contents();}finally{ob_end_clean();}
  self::assertStringContainsString('PASS:', $result);
 }
 public function testCommentColoursAndMapStyleIsolation(): void {
  ob_start();try {require __DIR__.'/../Support/CommentColourLocationRegression.php';$result=ob_get_contents();}finally{ob_end_clean();}
  self::assertStringContainsString('PASS:', $result);
 }
 public function testGraphFilesAreRestrictedToPermittedGraphs(): void {
  function is_graph_allowed($id){return $id===1;}
  require __DIR__.'/../../lib/editor.inc.php';
  $GLOBALS['__test_nfilter_request']=['term'=>'','graph_ids'=>'1,2','include_rrds'=>'1','paged'=>'1'];
  $GLOBALS['__test_db_fetch_assoc_prepared']=static function($sql,$params){
   if(strpos($sql,'SELECT DISTINCT gtg.')!==false)return [['id'=>1,'title'=>'Allowed'],['id'=>2,'title'=>'Denied']];
   self::assertSame([1],$params);
   return [['id'=>1,'path'=>'<path_rra>/router_ping.rrd'],['id'=>1,'path'=>'<path_rra>/router_ping.rrd'],['id'=>1,'path'=>'C:\\rra\\other.rrd'],['id'=>2,'path'=>'denied.rrd'],['id'=>1,'path'=>'']];
  };
  ob_start();display_graphs();$data=json_decode(ob_get_clean(),true);
  self::assertCount(1,$data['items']);self::assertSame(['router_ping.rrd','other.rrd'],$data['items'][0]['rrds']);
 }
}
