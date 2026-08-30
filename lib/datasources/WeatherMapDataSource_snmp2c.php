<?php

declare(strict_types = 1);
// Pluggable datasource for PHP Weathermap 0.9
// - return a live SNMP value

// doesn't work well with large values like interface counters (I think this is a rounding problem)
// - also it doesn't calculate rates. Just fetches a value.

// useful for absolute GAUGE-style values like DHCP Lease Counts, Wireless AP Associations, Firewall Sessions
// which you want to use to colour a NODE

// You could also fetch interface states from IF-MIB with it.

// TARGET snmp2c:public:hostname:1.3.6.1.4.1.3711.1.1:1.3.6.1.4.1.3711.1.2
// (that is, TARGET snmp:community:host:in_oid:out_oid

class WeatherMapDataSource_snmp2c extends WeatherMapDataSource {
	/**
	 * Needs the PHP SNMP extension built with SNMPv2c support.
	 *
	 * @param  WeatherMap $map map being drawn, by reference
	 * @return bool       false to take this datasource out of use for this run
	 */
	function Init(&$map) {
		// We can keep a list of unresponsive nodes, so we can give up earlier
		$this->down_cache = [];

		return true;
	}

	/**
	 * Claim a TARGET of the form snmp2c:community:host:oid_in:oid_out.
	 *
	 * @param  string $targetstring the TARGET as written in the map config
	 * @return bool   true when this datasource will handle it
	 */
	function Recognise($targetstring) {
		if (preg_match('/^snmp2c:([^:]+):([^:]+):([^:]+):([^:]+)$/', $targetstring, $matches)) {
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
	 * @param  WeatherMapItem  $item         node or link the TARGET belongs to
	 * @return array           [in, out, data_time]; the values are null when
	 *                         nothing could be read
	 */
	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;

		$in_result  = null;
		$out_result = null;

		$timeout     = 1000000;
		$retries     = 2;
		$abort_count = 0;

		if ($map->get_hint('snmp_timeout') != '') {
			$timeout = intval($map->get_hint('snmp_timeout'));
			wm_debug('Timeout changed to ' . $timeout . " microseconds.\n");
		}

		if ($map->get_hint('snmp_abort_count') != '') {
			$abort_count = intval($map->get_hint('snmp_abort_count'));
			wm_debug("Will abort after $abort_count failures for a given host.\n");
		}

		if ($map->get_hint('snmp_retries') != '') {
			$retries = intval($map->get_hint('snmp_retries'));
			wm_debug('Number of retries changed to ' . $retries . ".\n");
		}

		if (preg_match('/^snmp2c:([^:]+):([^:]+):([^:]+):([^:]+)$/', $targetstring, $matches)) {
			$community = $matches[1];
			$host      = $matches[2];
			$in_oid    = $matches[3];
			$out_oid   = $matches[4];

			if (
				($abort_count == 0)
				|| (
					($abort_count > 0)
					&& (!isset($this->down_cache[$host]) || intval($this->down_cache[$host]) < $abort_count)
				)
			) {
				if ($in_oid != '-') {
					$in_result = cacti_snmp_get($host, $community, $in_oid, '2', '', '', '', '', '', '', '', $timeout, $retries);

					if ($in_result !== false) {
						$data[IN] = floatval($in_result);
						$item->add_hint('snmp_in_raw', $in_result);
					} else {
						$this->down_cache[$host]++;
					}
				}

				if ($out_oid != '-') {
					$out_result = cacti_snmp_get($host, $community, $out_oid, '2', '', '', '', '', '', '', '', $timeout, $retries);

					if ($out_result !== false) {
						// use floatval() here to force the output to be *some* kind of number
						// just in case the stupid formatting stuff doesn't stop net-snmp returning 'down' instead of 2
						$data[OUT] = floatval($out_result);
						$item->add_hint('snmp_out_raw', $out_result);
					} else {
						$this->down_cache[$host]++;
					}
				}

				wm_debug("SNMP2c ReadData: Got $in_result and $out_result\n");

				$data_time = time();
			} else {
				wm_warn("SNMP for $host has reached $abort_count failures. Skipping. [WMSNMP01]");
			}
		}

		wm_debug('SNMP2c ReadData: Returning (' . ($data[IN] === null ? 'NULL' : $data[IN]) . ',' . ($data[OUT] === null ? 'NULL' : $data[OUT]) . ",$data_time)\n");

		return ([$data[IN], $data[OUT], $data_time]);
	}
}
