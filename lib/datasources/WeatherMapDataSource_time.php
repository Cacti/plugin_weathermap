<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2024 The Cacti Group, Inc.                           |
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

class WeatherMapDataSource_time extends WeatherMapDataSource {
	function Recognise($targetstring) {
		if (preg_match("/^time:(.*)$/",$targetstring,$matches)) {
			if (preg_match("/^[234]\./",phpversion())) {
				wm_warn("Time DS Plugin recognised a TARGET, but needs PHP5+ to run. [WMTIME01]\n");

				return false;
			}

			return true;
		} else {
			return false;
		}
	}

	// function ReadData($targetstring, $configline, $itemtype, $itemname, $map)
	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = NULL;
		$data[OUT] = NULL;
		$data_time = 0;
		$itemname  = $item->name;

		$matches   = 0;

		if (preg_match("/^time:(.*)$/",$targetstring,$matches)) {
			$timezone   = $matches[1];
			$timezone_l = strtolower($timezone);

			$timezone_identifiers = DateTimeZone::listIdentifiers();

			foreach ($timezone_identifiers as $tz) {
				if (strtolower($tz) == $timezone_l) {
					wm_debug ("Time ReadData: Timezone exists: $tz");

					$dateTime = new DateTime("now", new DateTimeZone($tz));

					$item->add_note("time_time12", $dateTime->format("h:i"));
					$item->add_note("time_time12ap", $dateTime->format("h:i A"));
					$item->add_note("time_time24", $dateTime->format("H:i"));
					$item->add_note("time_timezone", $tz);

					$data[IN]  = $dateTime->format("H");
					$data_time = time();
					$data[OUT] = $dateTime->format("i");

					$matches++;
				}
			}

			if ($matches == 0) {
				wm_warn ("Time ReadData: Couldn't recognize $timezone as a valid timezone name [WMTIME02]\n");
			}
		} else {
			// some error code to go in here
			wm_warn ("Time ReadData: Couldn't recognize $targetstring \n");
		}

		wm_debug ("Time ReadData: Returning (".($data[IN]===NULL?'NULL':$data[IN]).",".($data[OUT]===NULL?'NULL':$data[OUT]).",$data_time)");

		return(array($data[IN], $data[OUT], $data_time) );
	}
}

