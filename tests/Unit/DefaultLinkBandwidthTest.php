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
