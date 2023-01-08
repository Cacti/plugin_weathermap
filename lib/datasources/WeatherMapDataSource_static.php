<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2023 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Pluggable datasource for PHP Weathermap 0.9
 * return a static value
 *
 * TARGET static:10M
 * TARGET static:2M:256K
 */
class WeatherMapDataSource_static extends WeatherMapDataSource {
	function Recognise($targetstring) {
		if (preg_match("/^static:(\-?\d+\.?\d*[KMGT]?):(\-?\d+\.?\d*[KMGT]?)$/", $targetstring,$matches) ||
			preg_match("/^static:(\-?\d+\.?\d*[KMGT]?)$/",$targetstring,$matches)) {
			return true;
		} else {
			return false;
		}
	}

	function ReadData($targetstring, &$map, &$item) {
		$inbw      = NULL;
		$outbw     = NULL;
		$data_time = 0;

		if (preg_match("/^static:(\-?\d+\.?\d*[KMGT]*):(\-?\d+\.?\d*[KMGT]*)$/", $targetstring, $matches)) {
			$inbw      = unformat_number($matches[1], $map->kilo);
			$outbw     = unformat_number($matches[2], $map->kilo);
			$data_time = time();
		}

		if (preg_match("/^static:(\-?\d+\.?\d*[KMGT]*)$/", $targetstring, $matches)) {
			$inbw      = unformat_number($matches[1], $map->kilo);
			$outbw     = $inbw;
			$data_time = time();
		}

		wm_debug("Static ReadData: Returning ($inbw, $outbw, $data_time)");

		return (array($inbw, $outbw, $data_time));
	}
}

