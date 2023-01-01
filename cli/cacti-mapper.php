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

chdir('../../../');
include('./include/cli_check.php');
include_once($config['lib_path'] . '/snmp.php');

$cacti_base = $config['base_path'];
$cacti_url  = $config['url_path'];

$width  = 4000;
$height = 3000;

# figure out which template has interface traffic. This might be wrong for you.
$data_template_hash = 'fd841e8bb822927289b7acbc031f3d7e';

$data_template_id = db_fetch_cell_prepared("SELECT id
	FROM data_template
	WHERE hash = ?",
	array($data_template_hash));

$queryrows = db_fetch_assoc("SELECT h.snmp_version, h.snmp_community, h.snmp_username,
	h.snmp_password, h.snmp_auth_protocol, h.snmp_priv_passphrase, h.snmp_priv_protocol,
	h.snmp_context, h.snmp_port, h.snmp_timeout, h.description, h.hostname,
	h.disabled, hsc.*
	FROM host AS h
	INNER JOIN host_snmp_cache AS hsc
	ON h.id = hsc.host_id
	WHERE (field_name = 'ifDescr' OR field_name = 'ifName' OR field_name = 'ifIP' OR field_name = 'ifAlias')
	AND h.disabled != 'on'
	AND field_value != '127.0.0.1'
	AND field_value != '0.0.0.0'
	AND h.status = 3
	AND h.snmp_version > 0");

if (cacti_sizeof($queryrows)) {
	foreach ($queryrows as $line) {
		$key = sprintf("%06d-%010d", $line['host_id'], $line['snmp_index']);

		$hosts[$line['host_id']]['description'] = $line['description'];
		$hosts[$line['host_id']]['hostname'] = $line['hostname'];

		$hosts[$line['host_id']]['snmp_version'] = $line['snmp_version'];
		$hosts[$line['host_id']]['snmp_username'] = $line['snmp_username'];
		$hosts[$line['host_id']]['snmp_password'] = $line['snmp_password'];
		$hosts[$line['host_id']]['snmp_auth_protocol'] = $line['snmp_auth_protocol'];
		$hosts[$line['host_id']]['snmp_context'] = $line['snmp_context'];
		$hosts[$line['host_id']]['snmp_port'] = $line['snmp_port'];
		$hosts[$line['host_id']]['snmp_timeout'] = $line['snmp_timeout'];
		$hosts[$line['host_id']]['snmp_priv_protocol'] = $line['snmp_priv_protocol'];
		$hosts[$line['host_id']]['snmp_priv_passphrase'] = $line['snmp_priv_passphrase'];
		$hosts[$line['host_id']]['snmp_community'] = $line['snmp_community'];

		$interfaces[$key]['index'] = $line['snmp_index'];
		$interfaces[$key]['host'] = $line['host_id'];

		if ($line['field_name'] == 'ifIP') {
			$interfaces[$key]['ip'] = $line['field_value'];
		}

		if ($line['field_name'] == 'ifName') {
			$interfaces[$key]['name'] = $line['field_value'];
		}

		if ($line['field_name'] == 'ifDescr') {
			$interfaces[$key]['descr'] = $line['field_value'];
		}

		if ($line['field_name'] == 'ifAlias') {
			$interfaces[$key]['alias'] = $line['field_value'];
		}
	}
}

$count = 0;

if (file_exists('mapper-cache.txt')) {
	print 'Reading Netmask cache...' . PHP_EOL;

	$fd = fopen('mapper-cache.txt', 'r');

	while(!feof($fd)) {
		$str = fgets($fd,4096);
		$str = str_replace("\r", '', $str);

		trim($str);

		list($key, $mask) = explode("\t", $str);

		if (preg_match('/^(\d+\.\d+\.\d+\.\d+)$/', $mask, $m) && $mask != '0.0.0.0') {
			$interfaces[$key]['netmask'] = $m[1]; $count++;
		}
	}

	fclose($fd);
}

print "$count netmasks in the cache.\n";

print 'Collected information on ' . sizeof($interfaces) . ' interfaces and ' . sizeof($hosts) . ' hosts.' . PHP_EOL;

$cleaned = 0;

if (cacti_sizeof($interfaces)) {
	foreach($interfaces as $key=>$int) {
		if (!isset($int['ip'])) {
			unset($interfaces[$key]);
			$cleaned++;
		} else {
			$interfaces[$key]['nicename'] = (isset($int['name']) ? $int['name']:(isset($int['descr'])?$int['descr'] : (isset($int['alias']) ? $int['alias'] : 'Interface #' . $int['index'])));
		}
	}
}

print "Removed $cleaned interfaces from search, which have no IP address.\n";

$count = 0;

if (cacti_sizeof($interfaces)) {
	foreach($interfaces as $key=>$int) {
		if (!isset($int['netmask'])) {
			$oid = '.1.3.6.1.2.1.4.20.1.3.' . $int['ip'];

			$hostid = $int['host'];

			if ($count < 100) {
				print 'Fetching Netmask via SNMP for Host ' . $int['host'] . '//' . $int['ip'] . ' from ' . $oid . PHP_EOL;

				$result = cacti_snmp_get(
					$hosts[$hostid]['hostname'],
					$hosts[$hostid]['snmp_community'],
					$oid,
					$hosts[$hostid]['snmp_version'],
					$hosts[$hostid]['snmp_username'],
					$hosts[$hostid]['snmp_password'],
					$hosts[$hostid]['snmp_auth_protocol'],
					$hosts[$hostid]['snmp_priv_passphrase'],
					$hosts[$hostid]['snmp_priv_protocol'],
					$hosts[$hostid]['snmp_context'],
					$hosts[$hostid]['snmp_port'],
					$hosts[$hostid]['snmp_timeout'],
					SNMP_WEBUI
				);

				if ($result != false && preg_match('/^\d+.\d+.\d+.\d+$/', $result)) {
					print $result . '|' . PHP_EOL;
					$interfaces[$key]['netmask'] = $result;
				} else {
					print 'No useful result.' . PHP_EOL;
					unset($interfaces[$key]);
				}

				$count++;
			}
		}
	}
}

$count = 0;

print 'Writing Netmask cache...' . PHP_EOL;

$fd = fopen('mapper-cache.txt', 'w');

if (cacti_sizeof($interfaces)) {
	foreach($interfaces as $key=>$int) {
		if (isset($int['netmask'])) {
			fputs($fd, $key . "\t" . $int['netmask'] . "\n");

			$count++;
		}
	}
}

fclose($fd);

print "Wrote $count cache entries.\n";

# SNMP netmask => .1.3.6.1.2.1.4.20.1.3.10.1.1.254
# SNMP interface index => .1.3.6.1.2.1.4.20.1.2.10.1.1.254

$count = 0;

if (cacti_sizeof($interfaces)) {
	foreach($interfaces as $key=>$int) {
		if (isset($int['netmask'])) {
			$network = get_network($int['ip'], $int['netmask']) . '/' . get_cidr($int['netmask']);

			$interfaces[$key]['network'] = $network;

			$networks[$network][] = $key;
			$count++;
		} else {
			print $int['ip'] . PHP_EOL;
		}
	}
}

print "Assembled $count different network/netmask pairs" . PHP_EOL;

$link_config = '';
$node_config = '';
$nodes_seen  = array();
$count       = 0;
$linkid      = 0;
$lannodeid   = 0;

if (cacti_sizeof($interfaces)) {
	foreach ($networks as $network=>$members) {
		if (cacti_sizeof($members) < 2) {
			unset($networks[$network]);
			$count++;
		}

		if (cacti_sizeof($members) == 2) {
			print 'Create LINK between' . PHP_EOL;

			foreach($members as $int) {
				$h = $interfaces[$int]['host'];

				print '  ' . $interfaces[$int]['nicename'];
				print ' on ' . $hosts[$h]['description'];
				print ' (' . $hosts[$h]['hostname'] . ')' . PHP_EOL;

				$nodes_seen[$h] = 1;
			}

			$linkid++;
			$link_config .= "LINK link_$linkid" . PHP_EOL;
			$link_config .= "WIDTH 4" . PHP_EOL;
			$link_config .= "\tNODES node_" . $interfaces[$members[0]]['host'] . ' node_' . $interfaces[$members[1]]['host']. PHP_EOL;
			$link_config .= "\tSET in_interface "  . $interfaces[$members[1]]['nicename'] . PHP_EOL;
			$link_config .= "\tSET out_interface " . $interfaces[$members[0]]['nicename'] . PHP_EOL;
			$link_config .= PHP_EOL;
		}

		if (cacti_sizeof($members)>2) {
			print "Create LAN NODE called $network and add LINKs from these NODEs to it:" . PHP_EOL;

			$x = rand(0, $width);
			$y = rand(0, $height);

			$lan_key = preg_replace('/[.\/]/', '_', $network);

			$node_config .= "NODE LAN_$lan_key" . PHP_EOL;
			$node_config .= "LABELBGCOLOR 255 240 240" . PHP_EOL;
			$node_config .= "\tPOSITION $x $y" . PHP_EOL;
			$node_config .= "\tLABEL $network" . PHP_EOL;
			$node_config .= "\tICON 96 24 rbox" . PHP_EOL;
			$node_config .= "\tLABELOFFSET C" . PHP_EOL;
			$node_config .= "\tLABELOUTLINECOLOR none" . PHP_EOL;
			$node_config .= "USESCALE none in" . PHP_EOL . PHP_EOL;

			foreach($members as $int) {
				$h = $interfaces[$int]['host'];

				print "  $int:: " . $interfaces[$int]['nicename'];
				print ' on '.$hosts[$h]['description'];
				print ' (' . $hosts[$h]['hostname'] . ')' . PHP_EOL;

				$nodes_seen[$h] = 1;
				$linkid++;
				$link_config .= "LINK link_$linkid" . PHP_EOL;
				$link_config .= "SET out_interface ".$interfaces[$int]['nicename'] . PHP_EOL;
				$link_config .= "\tNODES node_$h LAN_$lan_key" . PHP_EOL;
				$link_config .= "\tWIDTH 2" . PHP_EOL;
				$link_config .= "\tOUTCOMMENT {link:this:out_interface}" . PHP_EOL;
			}

			print PHP_EOL;
		}
	}
}

print "Trimmed $count networks with only one member interface" . PHP_EOL;

if (cacti_sizeof($nodes_seen)) {
	foreach ($nodes_seen as $h => $c) {
		$x = rand(0, $width);
		$y = rand(0, $height);

		$node_config .= "NODE node_$h" . PHP_EOL;
		$node_config .= "\tSET cacti_id $h" . PHP_EOL;
		$node_config .= "\tLABEL ".$hosts[$h]['description'] . PHP_EOL;
		$node_config .= "\tPOSITION $x $y" . PHP_EOL;
		$node_config .= "\tUSESCALE cactiupdown in " . PHP_EOL;
		$node_config .= "\tLABELFONTCOLOR contrast" . PHP_EOL;
		$node_config .= PHP_EOL . PHP_EOL;
	}
}

$fd = fopen('automap.cfg', 'w');

$base_config  = 'HTMLSTYLE overlib' . PHP_EOL;
$base_config .= 'BGCOLOR 92 92 92' . PHP_EOL;
$base_config .= "WIDTH $width" . PHP_EOL;
$base_config .= "HEIGHT $height" . PHP_EOL;
$base_config .= 'FONTDEFINE 30 GillSans 8' . PHP_EOL;
$base_config .= 'FONTDEFINE 20 GillSans 10' . PHP_EOL;
$base_config .= 'FONTDEFINE 10 GillSans 9' . PHP_EOL;
$base_config .= 'SCALE DEFAULT 0 0 255 0 0' . PHP_EOL;
$base_config .= 'SCALE DEFAULT 0 10   32 32 32   0 0 255' . PHP_EOL;
$base_config .= 'SCALE DEFAULT 10 40   0 0 255   0 255 0' . PHP_EOL;
$base_config .= 'SCALE DEFAULT 40 55   0 255 0   255 255 0' . PHP_EOL;
$base_config .= 'SCALE DEFAULT 55 100   240 240 0   255 0 0' . PHP_EOL;
$base_config .= PHP_EOL;
$base_config .= 'SCALE cactiupdown 0 0.5 192 192 192 ' . PHP_EOL;
$base_config .= 'SCALE cactiupdown 0.5 1.5 255 0 0 ' . PHP_EOL;
$base_config .= 'SCALE cactiupdown 1.5 2.5 0 0 255 ' . PHP_EOL;
$base_config .= 'SCALE cactiupdown 2.5 3.5 0 255 0 ' . PHP_EOL;
$base_config .= 'SCALE cactiupdown 3.5 4.5 255 255 0 ' . PHP_EOL;
$base_config .= PHP_EOL;
$base_config .= 'LINK DEFAULT' . PHP_EOL;
$base_config .= 'BWSTYLE angled' . PHP_EOL;
$base_config .= 'BWLABEL bits' . PHP_EOL;
$base_config .= 'BWFONT 30' . PHP_EOL;
$base_config .= 'COMMENTFONT 30' . PHP_EOL;
$base_config .= PHP_EOL;
$base_config .= 'NODE DEFAULT' . PHP_EOL;
$base_config .= 'LABELFONT 10' . PHP_EOL;
$base_config .= PHP_EOL . PHP_EOL;

fputs($fd, $base_config);
fputs($fd, $node_config);
fputs($fd, $link_config);

fclose($fd);

///////////////////////////////////////////////////////////////

function ip_to_int($_ip) {
	if (preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $_ip, $matches)) {
		$_output = 0;

		for($i = 1; $i < 5; $i++) {
			$_output <<= 8;
			$_output += $matches[$i];
		}

		return($_output);
	} else {
		print "Something funny: $_ip\n";
		return(-1);
	}
}

function int_to_ip($_int) {
	$tmp = $_int;

	for ($i=0; $i < 4; $i++) {
		$IPBit[] = ($tmp & 255);
		$tmp >>= 8;
	}

	$_output = sprintf('%d.%d.%d.%d', $IPBit[3], $IPBit[2], $IPBit[1], $IPBit[0]);

	return ($_output);
}

function get_network($_ip, $_mask) {
	$_int1    = ip_to_int($_ip);
	$_mask1   = ip_to_int($_mask);
	$_network = $_int1 & ($_mask1);

	return (int_to_ip($_network));
}

function get_cidr($mask) {
	$lookup = array(
		'255.255.255.255' => '32',
		'255.255.255.254' => '31',
		'255.255.255.252' => '30',
		'255.255.255.248' => '29',
		'255.255.255.240' => '28',
		'255.255.255.224' => '27',
		'255.255.255.192' => '26',
		'255.255.255.128' => '25',
		'255.255.255.0'   => '24',
		'255.255.254.0'   => '23',
		'255.255.252.0'   => '22',
		'255.255.248.0'   => '21',
		'255.255.240.0'   => '20',
		'255.255.224.0'   => '19',
		'255.255.192.0'   => '18',
		'255.255.128.0'   => '17',
		'255.255.0.0'     => '16',
		'255.254.0.0'     => '15',
		'255.252.0.0'     => '14',
		'0.0.0.0.0'       => '0'
	);

	if ($lookup[$mask]) {
		return ($lookup[$mask]);
	}

	print "HUH: $mask\n";

	return('-1');
}

