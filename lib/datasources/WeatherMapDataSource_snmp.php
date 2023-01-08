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
 * - return a live SNMP value
 *
 * doesn't work well with large values like interface counters (I think this is a rounding problem)
 * - also it doesn't calculate rates. Just fetches a value.
 *
 *
 * useful for absolute GAUGE-style values like DHCP Lease Counts, Wireless AP Associations, Firewall Sessions
 * which you want to use to colour a NODE
 *
 * You could also fetch interface states from IF-MIB with it.
 *
 * TARGET snmp:public:hostname:1.3.6.1.4.1.3711.1.1:1.3.6.1.4.1.3711.1.2
 * (that is, TARGET snmp:community:host:in_oid:out_oid
 */
class WeatherMapDataSource_snmp extends WeatherMapDataSource {
	var $down_cache;

	function Init(&$map) {
		// We can keep a list of unresponsive nodes, so we can give up earlier
		$this->down_cache = array();

		if (function_exists('snmpget')) {
			return(true);
		}

		wm_debug("SNMP DS: snmpget() not found. Do you have the PHP SNMP module?");

		return(false);
	}

	function Recognise($targetstring) {
		if (preg_match("/^snmp:([^:]+):([^:]+):([^:]+):([^:]+)$/",$targetstring,$matches)) {
			return true;
		} else {
			return false;
		}
	}

	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;

		$timeout = 1000000;
		$retries = 2;

		$abort_count = 0;

		$in_result  = null;
		$out_result = null;

		if ($map->get_hint('snmp_timeout') != '') {
			$timeout = intval($map->get_hint('snmp_timeout'));
			wm_debug('Timeout changed to ' . $timeout . ' microseconds.');
		}

		if ($map->get_hint('snmp_abort_count') != '') {
			$abort_count = intval($map->get_hint('snmp_abort_count'));
			wm_debug("Will abort after $abort_count failures for a given host.");
		}

		if ($map->get_hint('snmp_retries') != '') {
			$retries = intval($map->get_hint('snmp_retries'));
			wm_debug("Number of retries changed to ".$retries);
		}

		if (preg_match('/^snmp:([^:]+):([^:]+):([^:]+):([^:]+)$/', $targetstring, $matches)) {
			$community = $matches[1];
			$host      = $matches[2];
			$in_oid    = $matches[3];
			$out_oid   = $matches[4];

			if ($abort_count == 0 ||
				($abort_count > 0 && (!isset($this->down_cache[$host]) || intval($this->down_cache[$host]) < $abort_count ))) {

				if (function_exists('snmp_get_quick_print')) {
					$was = snmp_get_quick_print();
					snmp_set_quick_print(1);
				}

				if (function_exists('snmp_get_valueretrieval')) {
					$was2 = snmp_get_valueretrieval();
				}

				if (function_exists('snmp_set_oid_output_format')) {
					snmp_set_oid_output_format  ( SNMP_OID_OUTPUT_NUMERIC  );
				}

				if (function_exists('snmp_set_valueretrieval')) {
					snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
				}

				if ($in_oid != '-') {
					$in_result = snmpget($host,$community,$in_oid,$timeout,$retries);

					if ($in_result !== false) {
						$data[IN] = floatval($in_result);
						$item->add_hint('snmp_in_raw', $in_result);
					} else {
						$this->down_cache[$host]++;
					}
				}

				if ($out_oid != '-') {
					$out_result = snmpget($host,$community,$out_oid,$timeout,$retries);

					if ($out_result !== false) {
						// use floatval() here to force the output to be *some* kind of number
						// just in case the stupid formatting stuff doesn't stop net-snmp returning 'down' instead of 2
						$data[OUT] = floatval($out_result);
						$item->add_hint('snmp_out_raw', $out_result);
					} else {
						$this->down_cache[$host]++;
					}
				}

				wm_debug("SNMP ReadData: Got $in_result and $out_result");

				$data_time = time();

				if (function_exists('snmp_set_quick_print')) {
					snmp_set_quick_print($was);
				}
			} else {
				wm_warn("SNMP for $host has reached $abort_count failures. Skipping. [WMSNMP01]");
			}
		}

		wm_debug ('SNMP ReadData: Returning (' . ($data[IN] === null ? 'NULL':$data[IN]) . ',' . ($data[OUT]=== null ? 'NULL':$data[OUT]) . ",$data_time)");

		return(array($data[IN], $data[OUT], $data_time));
	}
}

