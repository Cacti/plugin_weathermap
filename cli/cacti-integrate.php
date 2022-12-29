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

/** cacti-integrate.php
 *
 * Auto-fill a basic map file with as much information as possible from the
 * Cacti database, using interface names and node ip/names as clues.
 *
 * See http://forums.cacti.net/about26544.html for more info
 *
 */
include(__DIR__ . '/../../../include/cli_check.php');

$cacti_root = $config['base_path'];

ini_set('include_path',
	ini_get('include_path') . PATH_SEPARATOR .
	$cacti_root . PATH_SEPARATOR .
	$cacti_root . '/plugins/weathermap' . PATH_SEPARATOR .
	$cacti_root . '/plugins/weathermap/random-bits'
);

require_once 'lib/WeatherMap.class.php';
require_once 'Console/Getopt.php';

include_once 'include/global.php';
include_once 'include/config.php';

$cacti_base = $cacti_root;
$cacti_url  = $config['url_path'];

include_once 'editor-config.php';

// adjust width of link based on bandwidth.
// NOTE: These are bands - the value has to be up to or including the value in the list to match
$width_map = array (
    '1000000' => '1',     // up to 1meg
    '9999999' => '1',     // 1-10meg
    '10000000' => '2',    // 10meg
    '99999999' => '2',    // 10-100meg
    '100000000' => '4',   // 100meg
    '999999999' => '4',   // 100meg-1gig
    '1000000000' => '6',  // 1gig
    '9999999999' => '6',  // 1gig-10gig
    '10000000000' => '8', // 10gig
    '99999999999' => '8'  // 10gig-100gig
);

$config['base_url'] = (isset($config['url_path']) ? $config['url_path'] : $cacti_url);

// the following are defaults. You can change those from the command-line
// options now.

// set this to true to adjust the width of links according to speed
$map_widths = false;

// set this to true to use DSStats targets instead of RRD file targets
$use_dsstats = false;

$overwrite_targets = false;

$outputmapfile = "";
$inputmapfile = "";

// initialize object
$cg = new Console_Getopt();

$short_opts = '';
$long_opts = array (
    'help',
    'input=',
    'output=',
    'debug',
    'target-dsstats',
    'target-rrdtool',
    'overwrite-targets',
    'speed-width-map'
);

$args = $cg->readPHPArgv();

$ret = $cg->getopt($args, $short_opts, $long_opts);

if (PEAR::isError($ret)) {
    die('Error in command line: ' . $ret->getMessage() . "\n (try --help)\n");
}

$gopts = $ret[0];

$options_output = array ();

if (cacti_sizeof($gopts) > 0) {
    foreach ($gopts as $o) {
        switch ($o[0]) {
            case '--debug':
                $weathermap_debugging = true;
                break;

            case '--overwrite-targets':
                $overwrite_targets = true;
                break;

            case '--speed-width-map':
                $map_widths = true;
                break;

            case '--target-dsstats':
                $use_dsstats = true;
                break;

            case '--target-rrdtool':
                $use_dsstats = false;
                break;

            case '--output':
                $outputmapfile = $o[1];
                break;

            case '--input':
                $inputmapfile = $o[1];
                break;

            case '--help':
                print "cacti-integrate.php\n";
                print
                    "Copyright Howard Jones, 2008-2019 howie@thingy.com\nReleased under the MIT License\nhttp://www.network-weathermap.com/\n\n";

                print "Usage: php cacti-integrate.php [options]\n\n";

                print " --input {filename}      -  read config from this file\n";
                print " --output {filename}     -  write new config to this file\n";
                print " --target-rrdtool        -  generate rrd file targets (default)\n";
                print " --target-dsstats        -  generate DSStats targets\n";
                print " --debug                 -  enable debugging\n";
                print " --help                  -  show this help\n";

                exit();
                break;
        }
    }
}

if ($inputmapfile == '' || $outputmapfile == '') {
    print "You MUST specify an input and output file. See --help\n";
    exit();
}

// figure out which template has interface traffic. This might be wrong for you.
$data_template    = "Interface - Traffic";
$data_template_id = db_fetch_cell_prepared("SELECT id FROM data_template WHERE name = ?", array($data_template));

$map = new WeatherMap;

$map->ReadConfig($inputmapfile);

$fmt_cacti_graph     = $cacti_url . 'graph_image.php?local_graph_id=%d&rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300';
$fmt_cacti_graphpage = $cacti_url . 'graph.php?rra_id=all&local_graph_id=%d';

//
// Try and populate all three SET vars for each NODE
// cacti_id (host.id)
// hostname (host.description)
// address (host.hostname) (sorry about that)
//

foreach ($map->nodes as $node) {
	$name = $node->name;

	print "NODE $name\n";

	$host_id  = $node->get_hint('cacti_id');
	$hostname = $node->get_hint('hostname');
	$address  = $node->get_hint('address');

	if ($host_id != '') {
		$res1 = db_fetch_row_prepared('SELECT hostname, description FROM host WHERE id = ?', array(intval($host_id)));

		if ($res1) {
			if ($hostname == '') {
				$hostname = $res1['description'];
				$map->nodes[$node->name]->add_hint('hostname', $hostname);
			}

			if ($address == '') {
				$address = $res1['hostname'];
				$map->nodes[$node->name]->add_hint('address', $address);
			}
		}
	} else {
		// by now, if there was a host_id, all 3 are populated. If not, then we should try one of the others to get a host_id

		if ($address != '') {
			$res2 = db_fetch_row_prepared("SELECT id, description FROM host WHERE hostname = ?", array($address));

			if ($res2) {
				$host_id = $res2['id'];
				$map->nodes[$node->name]->add_hint("cacti_id", $host_id);

				if ($hostname == '') {
					$hostname = $res2['description'];
					$map->nodes[$node->name]->add_hint("hostname", $hostname);
				}
			}
		} elseif ($hostname != '') {
			$res3 = db_fetch_row_prepared("SELECT id, description FROM host WHERE description = ?", array($hostname));

			if ($res3) {
				$host_id = $res3['id'];
				$map->nodes[$node->name]->add_hint("cacti_id", $host_id);

				if ($address == '') {
					$address = $res3['hostname'];
					$map->nodes[$node->name]->add_hint("address", $address);
				}
			}
		}
	}

	if ($host_id != '') {
		$info = $config['base_url'] . "host.php?id=" . $host_id;
		$tgt = "cactimonitor:$host_id";

		$map->nodes[$node->name]->targets = array(
			array(
				$tgt,
				'',
				'',
				0,
				$tgt
			)
		);

		$map->nodes[$node->name]->infourl[IN] = $info;
	}

	print "  $host_id $hostname $address\n";
}

// Now lets go through the links
//  we want links where at least one of the nodes has a cacti_id, and where either interface_in or interface_out is set
foreach ($map->links as $link) {
	if (isset($link->a)) {
		$name = $link->name;

		$a = $link->a->name;
		$b = $link->b->name;
		$int_in = $link->get_hint('in_interface');
		$int_out = $link->get_hint('out_interface');
		$a_id = intval($map->nodes[$a]->get_hint('cacti_id'));
		$b_id = intval($map->nodes[$b]->get_hint('cacti_id'));

		print "LINK $name\n";

		if (count($link->targets) == 0 || $overwrite_targets ) {
			if ((($a_id + $b_id) > 0) && ($int_out . $int_in == '')) {
				print "  (could do if there were interfaces)\n";
			}

			if ((($a_id + $b_id) == 0) && ($int_out . $int_in != '')) {
				print "  (could do if there were host_ids)\n";
			}

			$tgt_interface = "";
			$tgt_host = "";

			if ($a_id > 0 && $int_out != '') {
				print "  We'll use the A end.\n";

				$tgt_interface = $int_out;
				$tgt_host = $a_id;
				$ds_names = ":traffic_in:traffic_out";
			} elseif ($b_id > 0 && $int_in != '') {
				print "  We'll use the B end and reverse it.\n";

				$tgt_interface = $int_in;
				$tgt_host = $b_id;
				$ds_names = ":traffic_out:traffic_in";
			} else {
				print "  No useful ends on this link - fill in more detail (host id, IP) on either NODE $a or $b\n";
			}

			if ($tgt_host != "") {
				$int_list = explode(":::", $tgt_interface);
				$total_speed = 0;
				$total_target = array ();

				foreach ($int_list as $interface) {
					print "  Interface: $interface\n";

					$res4 = db_fetch_row_prepared("SELECT dl.id, data_source_path, dl.snmp_index
						FROM data_template_data AS dtd
						INNER JOIN data_local AS dl
						ON dl.id = dtd.local_data_id
						INNER JOIN snmp_query AS sq
						ON dl.snmp_query_id = sq.id
						INNER JOIN host_snmp_cache AS hsc
						ON dl.snmp_query_id = hsc.snmp_query_id
						AND dl.host_id = hsc.host_id
						AND dl.snmp_index = hsc.snmp_index
						WHERE dl.host_id = ?
						AND hsc.field_name IN (?, ?, ?)
						AND hsc.field_value = ?
						AND dl.data_template_id = ?
						ORDER BY dtd.id DESC
						LIMIT 1",
						array($tgt_host, 'ifName', 'ifDescr', 'ifAlias', $interface, $data_template_id));

					// if we found one, add the interface to the targets for this link
					if ($res4) {
						$target        = $res4['data_source_path'];
						$local_data_id = $res4['id'];
						$snmp_index    = $res4['snmp_index'];
						$tgt           = str_replace("<path_rra>", $config["rra_path"], $target);
						$tgt           = $tgt . $ds_names;

						if ($use_dsstats) {
							$map->links[$link->name]->targets[] = array (
								$tgt,
								'',
								'',
								0,
								$tgt
							);
						} else {
							$tgt = "8*dsstats:$local_data_id" . $ds_names;

							$map->links[$link->name]->targets[] = array (
								$tgt,
								'',
								'',
								0,
								$tgt
							);
						}

						$speed = db_fetch_cell_prepared("SELECT field_value
							FROM host_snmp_cache
							WHERE field_name = 'ifSpeed'
							AND host_id = ?
							AND snmp_index = ?",
							array($tgt_host, $snmp_index));

						$hspeed = db_fetch_cell_prepared("SELECT field_value
							FROM host_snmp_cache
							WHERE field_name = 'ifHighSpeed'
							AND host_id = ?
							AND snmp_index = ?",
							array($tgt_host, $snmp_index));

						if ($hspeed && intval($hspeed) > 20) {
							$total_speed += ($hspeed * 1000000);
						} else if ($speed) {
							$total_speed += intval($speed);
						}

						$graph_id = db_fetch_cell_prepared("SELECT gti.local_graph_id
							FROM graph_templates_item
							INNER JOIN data_template_rrd AS dtr
							ON gti.task_item_id = dtr.id
							WHERE local_data_id = ?
							LIMIT 1",
							array($local_data_id));

						if ($graph_id) {
							$overlib = sprintf($fmt_cacti_graph, $graph_id);
							$infourl = sprintf($fmt_cacti_graphpage, $graph_id);

							print "    INFO $infourl\n";
							print "    OVER $overlib\n";

							$map->links[$name]->overliburl[IN][] = $overlib;
							$map->links[$name]->overliburl[OUT][] = $overlib;
							$map->links[$name]->infourl[IN] = $infourl;
							$map->links[$name]->infourl[OUT] = $infourl;
						} else {
							print " Couldn't find a graph that uses this rrd??\n";
						}
					} else {
						print "  Failed to find RRD file for $tgt_host/$interface\n";
					}
				}

				print "    SPEED $total_speed\n";

				$map->links[$name]->max_bandwidth_in = $total_speed;
				$map->links[$name]->max_bandwidth_out = $total_speed;
				$map->links[$name]->max_bandwidth_in_cfg = nice_bandwidth($total_speed);
				$map->links[$name]->max_bandwidth_out_cfg = nice_bandwidth($total_speed);

				if ($map_widths) {
					foreach ($width_map as $map_speed => $map_width) {
						if ($total_speed <= $map_speed) {
							$map->links[$name]->width = $width_map[$map_speed];

							print "    WIDTH " . $width_map[$map_speed] . "\n";

							continue 2;
						}
					}
				}
			}
		} else {
			print "Skipping link with targets\n";
		}
	}
}

$map->WriteConfig($outputmapfile);

print "Wrote config to $outputmapfile\n";
