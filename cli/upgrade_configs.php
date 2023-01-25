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

global $weathermap_debugging;

$weathermap_debugging = false;

$shortopts = 'VvHh';
$longopts  = array (
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

print 'About to repair maps' . PHP_EOL;
weathermap_repair_maps();
print 'Done repairing maps' . PHP_EOL;

function display_help() {
	display_version();

	print 'upgrade_configs.php: [ --debug ] ' . PHP_EOL . PHP_EOL;
	print ' --debug - Enable debugging output' . PHP_EOL;
}

function display_version() {
	global $config;

	if (!function_exists('plugin_weathermap_version')) {
		include_once($config['base_path'] . '/plugins/weathermap/setup.php');
	}

	$info = plugin_weathermap_version();

	print 'Weathermap Config Upgrade Utility, The Cacti Group, Inc., Version ' . $info['version'] . ', ' . WM_COPYRIGHT_YEARS . PHP_EOL;
}

