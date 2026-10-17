<?php
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
final class HoverDensityOptionsTest extends TestCase {
 public function testDensityCapabilityMatchesCactiLocalRenderingPath(): void {
  require_once __DIR__.'/../../setup.php';
  $oldConfig=$GLOBALS['config'];$oldOptions=$GLOBALS['__test_config_options'] ?? [];
  try {
   foreach([[1,0,true],[2,0,false],[2,1,true],[2,2,true]] as [$poller,$storage,$expected]) {
    $GLOBALS['config']['poller_id']=$poller;$GLOBALS['__test_config_options']['storage_location']=$storage;
    $html='<map name="test"><area href="/cacti/graph.php?local_graph_id=1"></map>';
    $marked=weathermap_hover_density_metadata($html);
    self::assertStringContainsString('data-wm-hover-density="'.($expected?'2':'1').'"',$marked);
    self::assertSame($marked,weathermap_hover_density_metadata($marked));
    self::assertStringContainsString('data-wm-hover-density="'.($expected?'2':'1').'"',weathermap_map_graph_links($html,'abc'));
   }
  } finally {$GLOBALS['config']=$oldConfig;$GLOBALS['__test_config_options']=$oldOptions;}
 }
 #[RunInSeparateProcess]
 #[PreserveGlobalState(false)]
 public function testOnlyMarkedCompactGraphRequestsAreScaled(): void {
  require __DIR__.'/../../setup.php';
  if(!defined('RRD_NL'))define('RRD_NL',"\n");
  $graph=['graph_opts'=>"--width=300\n",'graph_defs'=>'keep'];
  $request=['wm_hidpi'=>'2','graph_width'=>300,'graph_height'=>100];
  test_set_current_page('graph_image.php');$GLOBALS['__test_nfilter_request']=$request;
  $scaled=weathermap_hover_graph_options($graph);self::assertSame($graph['graph_opts']."--zoom 2\n",$scaled['graph_opts']);self::assertSame('keep',$scaled['graph_defs']);
  self::assertSame($scaled,weathermap_hover_graph_options($scaled));
  foreach([['wm_hidpi'=>'3'],['wm_hidpi'=>['2']],['graph_width'=>0],['graph_width'=>1001],['graph_height'=>0],['graph_height'=>401]] as $override){$GLOBALS['__test_nfilter_request']=array_merge($request,$override);self::assertSame($graph,weathermap_hover_graph_options($graph));}
  $GLOBALS['__test_nfilter_request']=$request;test_set_current_page('graph.php');self::assertSame($graph,weathermap_hover_graph_options($graph));
  test_set_current_page('graph_image.php');self::assertSame([],weathermap_hover_graph_options([]));self::assertSame(null,weathermap_hover_graph_options(null));
 }
 public function testCactiContinuationsPreserveZoomDetectionAndQuotedValues(): void {
  require_once __DIR__.'/../../setup.php';
  foreach ([" \\\n", " \\\r\n"] as $separator) {
   foreach (['--zoom 2', '--zoom=2'] as $flag) {
    self::assertTrue(weathermap_graph_has_zoom('--width=300' . $separator . $flag . $separator));
   }
   self::assertFalse(weathermap_graph_has_zoom('--title="Network' . $separator . '--zoom report"'));
  }
  self::assertFalse(weathermap_graph_has_zoom("--title=Network\\ report"));
  self::assertSame(['tail\\'], weathermap_graph_unquoted_tokens('tail' . chr(92)));
  self::assertFalse(weathermap_graph_has_zoom('--width=300' . chr(92) . "\r"));
 }

}
