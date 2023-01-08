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

chdir('../../../');
include('./include/cli_check.php');
include_once('./plugins/weathermap/lib/WeatherMap.class.php');

$cacti_base = $config['base_path'];

$reverse      = false;
$inputfile    = '';
$outputfile   = '';
$converted    = 0;
$candidates   = 0;
$totaltargets = 0;

$shortopts = 'VvHh';
$longopts  = array(
	'input:',
	'output:',
	'reverse',
	'debug',
	'help',
	'version',
);

$options = getopt($shortopts, $longopts);

if (cacti_sizeof($options)) {
	foreach ($options as $arg => $value) {
		switch ($arg) {
			case 'debug':
				$weathermap_debugging = true;

				break;
			case 'input':
				$inputfile = $value;

				break;
			case 'output':
				$outputfile = $value;

				break;
			case 'reverse':
				$reverse = true;

				break;
			case 'help':
			case 'H':
			case 'h':
				display_help();

				exit();

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();

				exit();

				break;
			default:
                print 'ERROR: Invalid Parameter ' . $arg . PHP_EOL . PHP_EOL;

                display_help();

                exit(1);
		}
	}
}

if ($inputfile == '' || $outputfile == '') {
	print 'FATAL: You must specify an input and output file.' . PHP_EOL;
	display_help();
	exit(1);
}

$map = new WeatherMap;

$map->context = 'cacti';
$map->rrdtool  = read_config_option('path_rrdtool');

print 'Reading config from $inputfile' . PHP_EOL;

$map->ReadConfig($inputfile);

$map->DatasourceInit();
$map->ProcessTargets();

$allitems = $map->buildAllItemsList();

foreach ($allitems as $myobj) {
	$type = $myobj->my_type();

	$name=$myobj->name;
	wm_debug ("ReadData for $type $name:");

	if (($type=='LINK' && isset($myobj->a)) || ($type=='NODE' && !is_null($myobj->x))) {
		if (count($myobj->targets)>0) {
			$totaltargets++;
			$tindex = 0;

			foreach ($myobj->targets as $target) {
				wm_debug ('ReadData: New Target: ' . $target[4]);

				$targetstring = $target[0];
				$multiply = $target[1];

				if ($reverse == false && $target[5] == 'WeatherMapDataSource_rrd') {
					$candidates++;

					# list($in,$out,$datatime) =  $map->plugins['data'][ $target[5] ]->ReadData($targetstring, $map, $myobj);
					wm_debug("ConvertDS: $targetstring is a candidate for conversion.");

					$rrdfile      = $targetstring;
					$multiplier   = 8;
					$dsnames[IN]  = 'traffic_in';
					$dsnames[OUT] = 'traffic_out';

					if (preg_match('/^(.*\.rrd):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/', $targetstring, $matches)) {
						$rrdfile = $matches[1];

						$dsnames[IN]  = $matches[2];
						$dsnames[OUT] = $matches[3];

						wm_debug('ConvertDS: Special DS names seen (' . $dsnames[IN] . ' and ' . $dsnames[OUT] . ')');
					}

					if (preg_match('/^rrd:(.*)/', $rrdfile, $matches)) {
						$rrdfile = $matches[1];
					}

					if (preg_match('/^gauge:(.*)/', $rrdfile, $matches)) {
						$rrdfile    = $matches[1];
						$multiplier = 1;
					}

					if (preg_match('/^scale:([+-]?\d*\.?\d*):(.*)/', $rrdfile, $matches)) {
						$rrdfile    = $matches[2];
						$multiplier = $matches[1];
					}

					$path_rra   = $config['rra_path'];
					$db_rrdname = $rrdfile;
					$db_rrdname = str_replace($path_rra, '<path_rra>', $db_rrdname);

					# special case for relative paths
					$db_rrdname = str_replace('../../rra', '<path_rra>', $db_rrdname);

					if ($db_rrdname != $rrdfile) {
						wm_debug("ConvertDS: Looking for $db_rrdname in the database.");

						$results = db_fetch_row_prepared('SELECT DISTINCT dtd.local_data_id
							FROM data_template_data AS dtd
							INNER JOIN data_template_rrd AS dtr
							ON dtd.local_data_id = dtr.local_data_id
							AND dtd.data_source_path = ?',
							array($db_rrdname));

						if (cacti_sizeof($results)) {
							$new_target = sprintf('dsstats:%d:%s:%s', $results['local_data_id'], $dsnames[IN], $dsnames[OUT]);

							$m = $multiply * $multiplier;

							if ($m != 1) {
								if ($m == -1) {
									$new_target = '-' . $new_target;
								}

								if ($m == intval($m)) {
									$new_target = sprintf('%d*%s', $m, $new_target);
								} else {
									$new_target = sprintf('%f*%s', $m, $new_target);
								}
							}

							wm_debug("ConvertDS: Converting to $new_target");
							$converted++;

							if ($type == 'NODE') {
								$map->nodes[$name]->targets[$tindex][4] = $new_target;
							}

							if ($type == 'LINK') {
								$map->links[$name]->targets[$tindex][4] = $new_target;
							}
						} else {
							wm_warn("ConvertDS: Failed to find a match for $db_rrdname - can't convert to DSStats.");
						}
					} else {
						wm_warn("ConvertDS: $rrdfile doesn't match with $path_rra - not bothering to look in the database.");
					}
				}

				// XXX - not implemented yet!
				if ($reverse == true && $target[5] == 'WeatherMapDataSource_dsstats' && 1 == 0) {
					$candidates++;

					# list($in,$out,$datatime) =  $map->plugins['data'][ $target[5] ]->ReadData($targetstring, $map, $myobj);
					wm_debug("ConvertDS: $targetstring is a candidate for conversion.");

					$multiplier   = 1;
					$dsnames[IN]  = 'traffic_in';
					$dsnames[OUT] = 'traffic_out';

					$path_rra   = $config['rra_path'];
					$db_rrdname = $rrdfile;
					$db_rrdname = str_replace($path_rra, '<path_rra>', $db_rrdname);

					# special case for relative paths
					$db_rrdname = str_replace('../../rra', '<path_rra>', $db_rrdname);

					wm_debug("ConvertDS: Looking for $db_rrdname in the database.");

					$results = db_fetch_row_prepared('SELECT DISTINCT dtd.local_data_id
						FROM data_template_data AS dtr
						INNER JOIN data_template_rrd AS dtr
						WHERE dtd.local_data_id = dtr.local_data_id
						AND dtd.data_source_path = ?',
						array($db_rrdname));

					if (cacti_sizeof($results)) {
						$new_target = sprintf('dsstats:%d:%s:%s', $results['local_data_id'], $dsnames[IN], $dsnames[OUT]);
						$m = $multiply * $multiplier;

						if ( $m != 1) {
							if ($m == -1) {
								$new_target = '-' . $new_target;
							}

							if ($m == intval($m)) {
								$new_target = sprintf('%d*%s', $m, $new_target);
							} else {
								$new_target = sprintf('%f*%s', $m, $new_target);
							}
						}

						wm_debug("ConvertDS: Converting to $new_target");

						$converted++;

						if ($type == 'NODE') {
							$map->nodes[$name]->targets[$tindex][4] = $new_target;
						}

						if ($type == 'LINK') {
							$map->links[$name]->targets[$tindex][4] = $new_target;
						}
					} else {
						wm_warn("ConvertDS: Failed to find a match for $db_rrdname - can't convert back to rrdfile.");
					}
				}

				$tindex++;
			}

			wm_debug ("ReadData complete for $type $name");
		} else {
			wm_debug("ReadData: No targets for $type $name");
		}
	} else {
		wm_debug("ReadData: Skipping $type $name that looks like a template.");
	}
}

$map->WriteConfig($outputfile);

print "Wrote new config to $outputfile" . PHP_EOL;

print "$totaltargets targets, $candidates rrd-based targets, $converted were actually converted." . PHP_EOL;

function display_version() {
	global $config;

	if (!function_exists('plugin_weathermap_version')) {
		include_once($config['base_path'] . '/plugins/weathermap/setup.php');
	}

	$info = plugin_weathermap_version();

	print 'Weathermap Cacti DSStats Conversion Tool, Copyright Howard Jones, Version ' . $info['version'] . ', ' . WM_COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print 'usage: php convert-to-dstats.php --input=S --output=S [--reverse] [--debug]' . PHP_EOL . PHP_EOL;

	print ' --input={filename}         - File to read from' . PHP_EOL;
	print ' --output={filename}        - File to write to' . PHP_EOL;
	print ' --reverse                  - Convert from DSStats to RRDtool instead' . PHP_EOL;
	print ' --debug                    - Enable debugging output' . PHP_EOL;
}

