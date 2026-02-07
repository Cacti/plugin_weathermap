<?php
class WeatherMapDataSource_cacti extends WeatherMapDataSource {
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

	function Recognise($targetstring) {
		if (preg_match('/^cacti:(\d+)$/', $targetstring, $matches) === 1) {
			return true;
		} else {
			return false;
		}
	}

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

		wm_debug(sprintf("cacti ReadData: Returning (%s, %s, %s)\n",
			string_or_null($data[IN]),
			string_or_null($data[OUT]),
			$data_time
		));

		return ([
			$data[IN],
			$data[OUT],
			$data_time
		]);
	}
}

// vim:ts=4:sw=4:
