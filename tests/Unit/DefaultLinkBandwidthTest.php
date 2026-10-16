<?php
use PHPUnit\Framework\TestCase;
final class DefaultLinkBandwidthTest extends TestCase {
 #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
 #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
 public function testNewMapsAndInheritedLinksUseGigabitCapacity(): void {
  chdir(dirname(__DIR__,2));
  require_once dirname(__DIR__,2).'/lib/WeatherMap.class.php';
  $map=new WeatherMap();$link=new WeatherMapLink();$link->Reset($map);
  foreach([$map->links['DEFAULT'],$link] as $item){
   self::assertSame(1000000000,$item->max_bandwidth_in);self::assertSame(1000000000,$item->max_bandwidth_out);
   self::assertSame('1000M',$item->max_bandwidth_in_cfg);self::assertSame('1000M',$item->max_bandwidth_out_cfg);
  }
 }
}
