<?php

declare(strict_types = 1);
class WeatherMapDataSource_cacti extends WeatherMapDataSource {
	/**
	 * Verifies this data source can run: requires the map to be
	 * operating in the 'cacti' context with Cacti's database library
	 * available. Called by the WeatherMap engine before using this data
	 * source.
	 *
	 * @param object $map Reference, the WeatherMap object being
	 *                    initialized.
	 *
	 * @return bool True if this data source can be used, false
	 *             otherwise.
	 */
	function Init(&$map) {
		if ($map->context === 'cacti') {
			if (function_exists('db_fetch_row') === true) {
				return (true);
			} else {
				wm_debug('ReadData cacti: Cacti database library not found.\n');
			}
		} else {
			wm_debug("ReadData cacti: Can only run from Cacti environment.\n");
		}

		return (false);
	}

	/**
	 * Determines whether a target string uses this data source's
	 * 'cacti:N' syntax. Called by the WeatherMap engine to select which
	 * data source handles a given link/node target.
	 *
	 * @param string $targetstring The target string to check.
	 *
	 * @return bool True if the target string matches the 'cacti:N'
	 *             pattern, false otherwise.
	 */
	function Recognise($targetstring) {
		if (preg_match('/^cacti:(\d+)$/', $targetstring, $matches) === 1) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reads the pre-computed IN/OUT values for a 'cacti:N' target from
	 * this plugin's weathermap_data cache table (populated by the
	 * poller_output hook), which holds already-rate-converted values for
	 * the given local data id. Called by the WeatherMap engine to
	 * collect data for a link/node using this data source.
	 *
	 * @param string $targetstring The 'cacti:N' target string to read.
	 * @param object $map          Reference, the WeatherMap object
	 *                            being updated.
	 * @param object $item         Reference, the link/node item this
	 *                            data is being read for.
	 *
	 * @return array A two-element array: [0] the IN/OUT data values
	 *              (array with IN/OUT keys) and [1] the data's
	 *              timestamp.
	 */
	function ReadData($targetstring, &$map, &$item) {
		$data[IN]  = null;
		$data[OUT] = null;
		$data_time = 0;
		$result    = [];

		if (preg_match('/^cacti:(\d+)$/', $targetstring, $matches) === 1) {
			$local_data_id = intval($matches[1]);

			$sql = 'SELECT * FROM weathermap_data WHERE local_data_id = ? LIMIT 1';

			$result = db_fetch_row_prepared($sql, [$local_data_id]);
		}

		if (cacti_sizeof($result)) {
			$data[IN]  = $result['last_calc'];
			$data[OUT] = $result['last_value'];
			$data_time = $result['last_time'];
		}

		wm_debug('cacti ReadData: Returning (' . ($data[IN] === null ? 'NULL' : $data[IN]) . ',' . ($data[OUT] === null ? 'NULL' : $data[OUT]) . ",$data_time)");

		return ([
			$data[IN],
			$data[OUT],
			$data_time
		]);
	}
}
