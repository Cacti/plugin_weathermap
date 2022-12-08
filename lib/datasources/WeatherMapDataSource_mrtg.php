<?php
/*
 +-------------------------------------------------------------------------+
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
 * Sample Pluggable datasource for PHP Weathermap 0.9
 * - read a pair of values from a database, and return it
 *
 * TARGET dbplug:databasename:username:pass:hostkey
 */
class WeatherMapDataSource_mrtg extends WeatherMapDataSource {
	function Recognise($targetstring) {
		if (preg_match("/\.(htm|html)$/",$targetstring,$matches)) {
			return true;
		} else {
			return false;
		}
	}

	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = NULL;
		$data[OUT] = NULL;
		$data_time = 0;

		$matchvalue  = $item->get_hint('mrtg_value');
		$matchperiod = $item->get_hint('mrtg_period');

		$swap   = intval($item->get_hint('mrtg_swap'));
		$negate = intval($item->get_hint('mrtg_negate'));

		if ($matchvalue =='') {
			$matchvalue = "cu";
		}

		if ($matchperiod =='') {
			$matchperiod = "d";
		}

		$fd = fopen($targetstring, "r");

		if ($fd) {
			while (!feof($fd)) {
				$buffer=fgets($fd, 4096);
				wm_debug("MRTG ReadData: Matching on '{$matchvalue}in $matchperiod' and '{$matchvalue}out $matchperiod'");

				if (preg_match("/<\!-- {$matchvalue}in $matchperiod ([-+]?\d+\.?\d*) -->/", $buffer, $matches)) { $data[IN] = $matches[1] * 8; }
				if (preg_match("/<\!-- {$matchvalue}out $matchperiod ([-+]?\d+\.?\d*) -->/", $buffer, $matches)) { $data[OUT] = $matches[1] * 8; }
			}

			fclose($fd);

			# don't bother with the modified time if the target is a URL
			if (! preg_match('/^[a-z]+:\/\//',$targetstring) ) {
				$data_time = filemtime($targetstring);
			}
		} else {
			// some error code to go in here
			wm_debug ("MRTG ReadData: Couldn't open ($targetstring)");
		}

		if ($swap==1) {
			wm_debug("MRTG ReadData: Swapping IN and OUT");

			$t = $data[OUT];

			$data[OUT] = $data[IN];
			$data[IN] = $t;
		}

		if ($negate) {
			wm_debug("MRTG ReadData: Negating values");

			$data[OUT] = -$data[OUT];
			$data[IN] = -$data[IN];
		}

		wm_debug ("MRTG ReadData: Returning (".($data[IN]===NULL?'NULL':$data[IN]).",".($data[OUT]===NULL?'NULL':$data[OUT]).",$data_time)");

		return( array($data[IN], $data[OUT], $data_time) );
	}
}

