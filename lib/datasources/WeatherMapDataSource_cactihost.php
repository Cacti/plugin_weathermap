<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
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

class WeatherMapDataSource_cactihost extends WeatherMapDataSource {
	/**
	 * Needs the Cacti database library and a Cacti context.
	 *
	 * @param  WeatherMap $map map being drawn, by reference
	 * @return bool       false to take this datasource out of use for this run
	 */
	function Init(&$map) {
		if ($map->context == 'cacti') {
			if (function_exists('db_fetch_row')) {
				return (true);
			} else {
				wm_debug('ReadData CactiHost: Cacti database library not found.');
			}
		} else {
			wm_debug('ReadData CactiHost: Can only run from Cacti environment.');
		}

		return (false);
	}

	/**
	 * Claim a TARGET of the form cactihost:host_id.
	 *
	 * @param  string $targetstring the TARGET as written in the map config
	 * @return bool   true when this datasource will handle it
	 */
	function Recognise($targetstring) {
		if (preg_match("/^cactihost:(\d+)$/",$targetstring,$matches)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Read the current values for one TARGET.
	 *
	 * @param  string          $targetstring the TARGET as written in the map config
	 * @param  WeatherMap      $map          map being drawn, by reference
	 * @param  WeatherMapNode|WeatherMapLink $item node or link the TARGET belongs to
	 * @return array           [in, out, data_time]; the values are null when
	 *                         nothing could be read
	 */
	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;

		if (preg_match("/^cactihost:(\d+)$/",$targetstring,$matches)) {
			$cacti_id = intval($matches[1]);
			$state    = -1;

			// 0=disabled
			// 1=down
			// 2=recovering
			// 3=up

			$result = db_fetch_row_prepared('SELECT *
				FROM host
				WHERE id = ?',
				[$cacti_id]);

			if (cacti_sizeof($result)) {
				// create a note, which can be used in icon filenames or labels more nicely
				if ($result['status'] == 1) {
					$state     = 1;
					$statename = 'down';
				}

				if ($result['status'] == 2) {
					$state     = 2;
					$statename = 'recovering';
				}

				if ($result['status'] == 3) {
					$state     = 3;
					$statename = 'up';
				}

				if ($result['disabled']) {
					$state     = 0;
					$statename = 'disabled';
				}

				$data[IN]  = $state;
				$data[OUT] = $state;

				$item->add_note('state',$statename);
				$item->add_note('cacti_description',$result['description']);

				$item->add_note('cacti_hostname',$result['hostname']);
				$item->add_note('cacti_curtime',$result['cur_time']);
				$item->add_note('cacti_avgtime',$result['avg_time']);
				$item->add_note('cacti_mintime',$result['min_time']);
				$item->add_note('cacti_maxtime',$result['max_time']);
				$item->add_note('cacti_availability',$result['availability']);

				$item->add_note('cacti_faildate',$result['status_fail_date']);
				$item->add_note('cacti_recdate',$result['status_rec_date']);
			}
		}

		wm_debug('CactiHost ReadData: Returning (' . ($data[IN] === null ? 'NULL' : $data[IN]) . ',' . ($data[OUT] === null ? 'NULL' : $data[OUT]) . ",$data_time)");

		return ([$data[IN], $data[OUT], $data_time]);
	}
}
