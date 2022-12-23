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

//
// Change the uncommented line to point to your Cacti installation
//
$cacti_base = __DIR__ . '/../../';

// $cacti_base = 'C:/xampp/htdocs/cacti/';
// $cacti_base = '/var/www/html/cacti/';
// $cacti_base = '/Applications/XAMPP/htdocs/cacti/';

// check if the goalposts have moved
if (is_dir($cacti_base) && file_exists($cacti_base . '/include/global.php')) {
	// include the cacti-config, so we know about the database
	require_once $cacti_base . '/include/global.php';
} elseif (is_dir($cacti_base) && file_exists($cacti_base . '/include/config.php')) {
	// include the cacti-config, so we know about the database
	require_once $cacti_base . '/include/config.php';
} else {
	die("Couldn't find a usable Cacti config - check the first few lines of " . __FILE__ . "\n");
}

require_once 'Weathermap.class.php';
require_once 'Console/Getopt.php';

$reverse = 0;
$inputfile = '';
$outputfile = '';
$converted = 0;
$candidates = 0;
$totaltargets = 0;

$cg = new Console_Getopt();
$short_opts = '';
$long_opts = array (
    'help',
    'input=',
    'output=',
    'debug'
);

$args = $cg->readPHPArgv();
$ret = $cg->getopt($args, $short_opts, $long_opts);

if (PEAR::isError($ret)) {
	die('Error in command line: ' . $ret->getMessage() . "\n (try --help)\n");
}

$gopts = $ret[0];

if (count($gopts) > 0) {
	foreach ($gopts as $o) {
		switch ($o[0]) {
			case '--debug':
				$weathermap_debugging = true;

				break;
			case '--input':
				$inputfile = $o[1];

				break;
			case '--output':
				$outputfile = $o[1];

				break;
			case 'help':
			default:
				print "Weathermap DSStats converter. Converts rrd targets to DSStats\n";
				print "-------------------------------------------------------------\n";
				print "Usage: php convert-to-dstats.php [options]\n\n";
				print " --input {filename}         - File to read from\n";
				print " --output {filename}        - File to write to\n";
				print " --debug                    - Enable debugging output\n";
				print " --help                    - Show this message\n";

                exit();
		}
	}
}

if ($inputfile === '' || $outputfile === '') {
	print "You must specify an input and output file. See --help.\n";
	exit();
}

$map = new WeatherMap;

$map->context = 'cacti';
$map->rrdtool = read_config_option('path_rrdtool');

print 'Reading config from '.$inputfile."\n";

$map->ReadConfig($inputfile);

// 'Draw' the map, so that we get dimensions for all the nodes
// and offsets for links are calculated.
$map->DrawMap(null);

// loop through all links
// adjust node offsets so that links come from correct side of nodes, and ideally still
// from underneath them (e.g. NE80 not NE)

$map->WriteConfig($outputfile);

print 'Wrote new config to '.$outputfile."\n";

