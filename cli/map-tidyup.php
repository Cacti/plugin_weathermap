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

//
// Change the uncommented line to point to your Cacti installation
//
chdir('../../../');
include('./include/cli_check.php');
include_once('./plugins/weathermap/lib/WeatherMap.class.php');

$cacti_base = $config['base_path'];

$reverse      = 0;
$inputfile    = '';
$outputfile   = '';
$converted    = 0;
$candidates   = 0;
$totaltargets = 0;

$shortopts = 'VvHh';
$longopts  = array (
	'input:',
	'output:',
	'debug',
	'help',
	'version'
);

$options = getopt($shortopts, $longopts);

if (cacti_sizeof($options) > 0) {
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

if ($inputfile === '' || $outputfile === '') {
	print 'FATAL: You must specify an input and output file.' . PHP_EOL;

	display_help();

	exit(1);
}

$map = new WeatherMap;

$map->context = 'cacti';
$map->rrdtool = read_config_option('path_rrdtool');

print 'Reading config from ' . $inputfile . PHP_EOL;

$map->ReadConfig($inputfile);

// 'Draw' the map, so that we get dimensions for all the nodes
// and offsets for links are calculated.
$map->DrawMap(null);

// loop through all links
// adjust node offsets so that links come from correct side of nodes, and ideally still
// from underneath them (e.g. NE80 not NE)

$map->WriteConfig($outputfile);

print 'Wrote new config to ' . $outputfile . PHP_EOL;

function display_help() {
	display_version();

	print 'usage: php convert-to-dstats.php --inpude=S --output=S [--debug]' . PHP_EOL . PHP_EOL;
	print ' --input={filename}         - File to read from' . PHP_EOL;
	print ' --output={filename}        - File to write to' . PHP_EOL;
	print ' --debug                    - Enable debugging output' . PHP_EOL;
	print ' --help                     - Show this message' . PHP_EOL;
}

function display_version() {
	global $config;

	if (!function_exists('plugin_weathermap_version')) {
		include_once($config['base_path'] . '/plugins/weathermap/setup.php');
	}

	$copyright_years = '2008-2023';

	$info = plugin_weathermap_version();

	print 'Weathermap Map Tidy Up Tool, Copyright Howard Jones, Version ' . $info['version'] . ', ' . $copyright_years . PHP_EOL;
}

