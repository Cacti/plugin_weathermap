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
 * - return a live ping result
 *
 * TARGET fping:ipaddress
 * TARGET fping:hostname
 */
class WeatherMapDataSource_fping extends WeatherMapDataSource {
	var $addresscache = array();
	var $donepings = false;
	var $results = array();
	var $fping_cmd;

	function Init(&$map) {
		#
		# You may need to change the line below to have something like "/usr/local/bin/fping" or "/usr/bin/fping" instead.
		#
		$this->fping_cmd = "/usr/local/sbin/fping";

		return(true);
	}

	// this function will get called for every datasource, even if we replied false to Init.
	// (so that we can warn the user that it *would* have worked, if only the plugin could run)
	// SO... don't do anything in here that relies on the things that Init looked for, because they might not exist!
	function Recognise($targetstring) {
		if (preg_match("/^fping:(\S+)$/",$targetstring,$matches)) {
			// save the address. This way, we can do ONE fping call for all the pings in the map.
			// fping does it all in parallel, so 10 hosts takes the same time as 1
			$this->addresscache[]=$matches[1];
			return true;
		} else {
			return false;
		}
	}

	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;

		$ping_count = intval($map->get_hint('fping_ping_count'));
		if ($ping_count==0) {
			$ping_count = 5;
		}

		if (preg_match('/^fping:(\S+)$/', $targetstring, $matches)) {
			$target = $matches[1];

			$pattern = "/^$target\s:";

			for($i=0;$i<$ping_count;$i++) {
				$pattern .= '\s(\S+)';
			}

			$pattern .= '/';

			if (is_executable($this->fping_cmd)) {
				$command = $this->fping_cmd . " -t100 -r1 -p20 -u -C $ping_count -i10 -q $target 2>&1";

				wm_debug("Running $command");
				$pipe = popen($command, 'r');

				$count = 0; $hitcount=0;
				if (isset($pipe)) {
					while (!feof($pipe)) {
						$line=fgets($pipe, 4096);
						$count++;

						wm_debug("Output: $line");

						if (preg_match($pattern, $line, $matches)) {
							wm_debug("Found output line for $target");

							$hitcount++;
							$loss  = 0;
							$ave   = 0;
							$total = 0;
							$cnt   = 0;
							$min   = 999999;
							$max   = 0;

							for($i=1;$i<=$ping_count;$i++) {
								if ($matches[$i]=='-') {
									$loss+=(100/$ping_count);
								} else {
									$cnt++;
									$total += $matches[$i];
									$max = max($matches[$i],$max);
									$min = min($matches[$i],$min);
								}
							}

							if ($cnt >0) {
								$ave = $total/$cnt;
							}

							wm_debug("Result: $cnt $min -> $max $ave $loss");
						}
					}

					pclose ($pipe);

					if ($count==0) {
						wm_warn("FPing ReadData: No lines read. Bad hostname? ($target) [WMFPING03]");
					} else {
						if ($hitcount == 0) {
							wm_warn("FPing ReadData: $count lines read. But nothing returned for target??? ($target) Try running with DEBUG to see output.  [WMFPING02]");
						} else {
							$data[IN]  = $ave;
							$data[OUT] = $loss;

							$item->add_note('fping_min', $min);
							$item->add_note('fping_max', $max);
						}
					}
				}
			} else {
				wm_warn("FPing ReadData: Can't find fping executable. Check path at line 19 of WeatherMapDataSource_fping.php [WMFPING01]");
			}
		}

		wm_debug ('FPing ReadData: Returning (' . ($data[IN] === null ? 'NULL':$data[IN]) . ',' . ($data[OUT] === null ? 'NULL':$data[OUT]) . ",$data_time)");

		return(array($data[IN], $data[OUT], $data_time));
	}
}

