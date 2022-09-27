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

include_once dirname(__FILE__)."/../ds-common.php";
include_once dirname(__FILE__)."/../database.php";

class WeatherMapDataSource_dsstats extends WeatherMapDataSource {
	function Init(&$map) {
		global $config;

		if ($map->context=='cacti') {
			if ( !function_exists('db_fetch_row') ) {
				wm_debug("ReadData DSStats: Cacti database library not found. [DSSTATS001]");
				return(false);
			}

			if (function_exists("api_plugin_is_enabled")) {
				if (! api_plugin_is_enabled('dsstats')) {
					wm_debug("ReadData DSStats: DSStats plugin not enabled (new-style). [DSSTATS002B]");
					return(false);
				 }
			} else {
				if ( !isset($plugins) || !in_array('dsstats',$plugins)) {
					wm_debug("ReadData DSStats: DSStats plugin not enabled (old-style). [DSSTATS002A]");
					return(false);
				}
			}

			$sql = "show tables";
			$result = db_fetch_assoc($sql);
			$tables = array();

			foreach($result as $index => $arr) {
				foreach ($arr as $t) {
					$tables[] = $t;
				}
			}

			if ( !in_array('data_source_stats_hourly_last', $tables) ) {
				wm_debug('ReadData DSStats: data_source_stats_hourly_last database table not found. [DSSTATS003]');
				return(false);
			}

			return(true);
		}

		return(false);
	}

	# dsstats:<datatype>:<local_data_id>:<rrd_name_in>:<rrd_name_out>
	function Recognise($targetstring) {
		if (preg_match("/^dsstats:([a-z]+):(\d+):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/",$targetstring,$matches)) {
			return true;
		} elseif (preg_match("/^dsstats:(\d+):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/",$targetstring,$matches)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Actually read data from a data source, and return it
	 * returns a 3-part array (invalue, outvalue and datavalid time_t)
	 * invalue and outvalue should be -1,-1 if there is no valid data
	 * data_time is intended to allow more informed graphing in the future
	 */
	function ReadData($targetstring, &$map, &$item) {
		global $config;

		$dsnames[IN]  = "traffic_in";
		$dsnames[OUT] = "traffic_out";
		$data[IN]     = NULL;
		$data[OUT]    = NULL;

		$inbw      = NULL;
		$outbw     = NULL;
		$data_time = 0;

		$table    = "";
		$keyfield = "rrd_name";
		$datatype = "";
		$field    = "";

		$pdo = weathermap_get_pdo();

		if (preg_match("/^dsstats:(\d+):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/",$targetstring,$matches)) {
			$local_data_id = $matches[1];
			$dsnames[IN]   = $matches[2];
			$dsnames[OUT]  = $matches[3];

			$datatype = "last";

			if ($map->get_hint("dsstats_default_type") != '') {
				$datatype = $map->get_hint("dsstats_default_type");

				wm_debug("Default datatype changed to $datatype");
			}
		} elseif (preg_match("/^dsstats:([a-z]+):(\d+):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/",$targetstring,$matches)) {
			$dsnames[IN] = $matches[3];
			$dsnames[OUT] = $matches[4];
			$datatype = $matches[1];
			$local_data_id = $matches[2];
		}

		if (substr($datatype,0,5) == "daily") {
			$table = "data_source_stats_daily";
		}

		if (substr($datatype,0,6) == "weekly") {
			$table = "data_source_stats_weekly";
		}

		if (substr($datatype,0,7) == "monthly") {
			$table = "data_source_stats_monthly";
		}

		if (substr($datatype,0,6) == "hourly") {
			$table = "data_source_stats_hourly";
		}

		if (substr($datatype,0,6) == "yearly") {
			$table = "data_source_stats_yearly";
		}

		if (substr($datatype,-7) == "average") {
			$field = "average";
		}

		if (substr($datatype,-4) == "peak") {
			$field = "peak";
		}

		if ($datatype == "last") {
			$field = "calculated";
			$table = "data_source_stats_hourly_last";
		}

		if ($datatype == "wm") {
			$field = "last_calc";
			$table = "weathermap_data";
			$keyfield = "data_source_name";
		}

		if ($table != "" and $field != "") {
		    // I know... but the field names and table name are variable!
			$SQL = sprintf("select %s as name, %s as result from %s where local_data_id=? and (%s=? or %s=?)",
					$keyfield, $field, $table, $keyfield, $keyfield);

			$stmt = $pdo->prepare($SQL);
			$stmt->execute(array($local_data_id, $dsnames[IN], $dsnames[OUT]));
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

//			$results = db_fetch_assoc($SQL);
			if (cacti_sizeof($results) > 0) {
				foreach ($results as $result) {
					foreach (array(IN,OUT) as $dir) {
						if (($dsnames[$dir] == $result['name']) && ($result['result'] != -90909090909) && ($result['result'] !='U')) {
							$data[$dir] = $result['result'];
						}
					}
				}
			}

			if ($datatype=='wm' && ($data[IN] == NULL || $data[OUT] == NULL) ) {
				wm_debug("Didn't get data for 'wm' source. Inserting new tasks.");

				// insert the required details into weathermap_data, so it will be picked up next time
                $stmt = $pdo->prepare("select data_template_data.data_source_path as path from data_template_data,data_template_rrd where data_template_data.local_data_id=data_template_rrd.local_data_id and data_template_rrd.local_data_id=?");

                $stmt->execute(array($local_data_id));

                $result = $stmt->fetch(PDO::FETCH_ASSOC);

//				$SQL = sprintf("select data_template_data.data_source_path as path from data_template_data,data_template_rrd where data_template_data.local_data_id=data_template_rrd.local_data_id and data_template_rrd.local_data_id=%d",
//						$local_data_id
//						);
//				$result = db_fetch_row($SQL);

				if (cacti_sizeof($result)>0) {
					$db_rrdname = $result['path'];

					wm_debug("Filename is $db_rrdname");

					foreach (array(IN,OUT) as $dir) {
						if ($data[$dir] === NULL) {
						    $stmt = $pdo->prepare("insert into weathermap_data (rrdfile, data_source_name, sequence, local_data_id) values (?,?,0,?)");
						    $stmt->execute(array($db_rrdname, $dsnames[$dir], $local_data_id));
//							$SQLins = "insert into weathermap_data (rrdfile, data_source_name, sequence, local_data_id) values ('" .
//								mysql_real_escape_string($db_rrdname) . "','" .
//								mysql_real_escape_string($dsnames[$dir]) . "', 0," .
//								$local_data_id.")";
							// warn($SQLins);
//							db_execute($SQLins);
						}
					}
				} else {
					wm_warn("DSStats ReadData: Failed to find a filename for DS id $local_data_id [WMDSTATS01]");
				}
			}
		}

		// fill all that other information (ifSpeed, etc)
		if ($local_data_id > 0) {
			UpdateCactiData($item, $local_data_id);
		}

		wm_debug ("DSStats ReadData: Returning (".($data[IN]===NULL?'NULL':$data[IN]).",".($data[OUT]===NULL?'NULL':$data[OUT]).",$data_time)");

		return (array($data[IN], $data[OUT], $data_time));
	}
}

