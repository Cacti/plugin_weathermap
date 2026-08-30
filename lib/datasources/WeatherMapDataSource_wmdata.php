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

class WeatherMapDataSource_wmdata extends WeatherMapDataSource {
	/**
	 * Claim a TARGET of the form wmdata:file:name.
	 *
	 * @param  string $targetstring the TARGET as written in the map config
	 * @return bool   true when this datasource will handle it
	 */
	function Recognise($targetstring) {
		if (preg_match('/^wmdata:.*$/', $targetstring, $matches)) {
			return true;
		} else {
			return false;
		}
	}

	// function ReadData($targetstring, $configline, $itemtype, $itemname, $map)
	/**
	 * Read the current values for one TARGET.
	 *
	 * @param  string          $targetstring the TARGET as written in the map config
	 * @param  WeatherMap      $map          map being drawn, by reference
	 * @param  WeatherMapItem  $item         node or link the TARGET belongs to
	 * @return array           [in, out, data_time]; the values are null when
	 *                         nothing could be read
	 */
	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;
		$itemname  = $item->name;

		$matches  = [];
		$datafile = '';
		$dataname = '';

		if (preg_match('/^wmdata:([^:]*):(.*)$/', trim($targetstring), $matches)) {
			$datafile = trim($matches[1]);
			$dataname = trim($matches[2]);
		} else {
			wm_warn("WMData ReadData: Couldn't parse target ($targetstring). [WMWMDATA04]");
		}

		if ($datafile !== '' && file_exists($datafile)) {
			$fd = fopen($datafile, 'r');

			if ($fd !== false) {
				$found = false;

				while (($buffer = fgets($fd, 4096)) !== false) {
					// strip out any Windows line-endings that have gotten in here
					$buffer = str_replace("\r", '', $buffer);

					$fields = explode("\t", rtrim($buffer, "\n"));

					if (isset($fields[2]) && $fields[0] == $dataname) {
						$data[IN]  = $fields[1];
						$data[OUT] = $fields[2];

						$found = true;
					}
				}

				fclose($fd);

				if ($found === true) {
					$stats     = stat($datafile);
					$data_time = $stats['mtime'];
				} else {
					wm_warn("WMData ReadData: Data name ($dataname) didn't exist in ($datafile). [WMWMDATA03]");
				}
			} else {
				wm_warn("WMData ReadData: Couldn't open ($datafile). [WMWMDATA02]");
			}
		} else {
			wm_warn("WMData ReadData: $datafile doesn't exist [WMWMDATA01]");
		}

		wm_debug('WMData ReadData: Returning (' . ($data[IN] === null ? 'NULL' : $data[IN]) . ',' . ($data[OUT] === null ? 'NULL' : $data[OUT]) . ",$data_time)");

		return (
			[
				$data[IN],
				$data[OUT],
				$data_time
			]
		);
	}
}
