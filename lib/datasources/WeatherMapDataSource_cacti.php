<?php

declare(strict_types = 1);
class WeatherMapDataSource_cacti extends WeatherMapDataSource {
	/**
	 * Needs the Cacti database library and a Cacti context.
	 *
	 * @param  WeatherMap $map map being drawn, by reference
	 * @return bool       false to take this datasource out of use for this run
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
	 * Claim a TARGET of the form cacti:local_data_id.
	 *
	 * @param  string $targetstring the TARGET as written in the map config
	 * @return bool   true when this datasource will handle it
	 */
	function Recognise($targetstring) {
		if (preg_match('/^cacti:(\d+)$/', $targetstring, $matches) === 1) {
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
