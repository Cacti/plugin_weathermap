<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/
require dirname(__DIR__) . '/bootstrap-unit.php';
chdir(dirname(__DIR__, 2));
require 'setup.php';
require 'lib/WeatherMap.class.php';
require 'lib/WeatherMap.animation.php';
/**
 * Assert a real-engine regression condition.
 *
 * @param bool $condition Whether the condition holds.
 * @param string $message Failure description.
 * @return void
 */
function animation_assert($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
$file = tempnam(sys_get_temp_dir(), 'wm-glow-');
try {
	file_put_contents($file, "WIDTH 400\nHEIGHT 300\nNODE A\n POSITION 40 40\nNODE B\n POSITION 350 240\nLINK curve\n NODES A B\n VIA 150 200\n SPLITPOS 30\n");
	$map = new WeatherMap;
	$map->ReadConfig($file);
	$link = $map->links['curve'];
	$link->outpercent = 79.99; $link->inpercent = 80;
	$link->colours[OUT] = new Colour(255, 0, 0);
	$link->colours[IN] = new Colour(255, 128, 0);
	$map->DrawMap('null');
	$data = wm_traffic_animation_data($map);
	animation_assert($data['enabled'] === true, 'pulse defaults on');
	foreach (['LEGEND:DEFAULT', 'TITLE', 'TIMESTAMP', 'MINTIMESTAMP', 'MAXTIMESTAMP'] as $foregroundIndex => $foreground) {
		$x = 21 + 10 * $foregroundIndex;
		$map->imap->addArea('Rectangle', $foreground, '', [$x, 22, $x + 10, 32]);
		animation_assert(in_array([[$x,22],[$x+10,22],[$x+10,32],[$x,32]], wm_traffic_animation_data($map)['occlusions'], true), 'foreground rectangle masks glow: ' . $foreground);
	}
	$map->add_hint('traffic_pulse', '0');
	animation_assert(wm_traffic_animation_data($map)['enabled'] === false, 'saved setting disables pulse');
	$map->add_hint('traffic_pulse', '1');
	animation_assert(wm_traffic_animation_data($map)['enabled'] === true, 'saved setting enables pulse');
	animation_assert(count($data['regions']) === 1, '80 percent included; 79.99 excluded');
	animation_assert($data['regions'][0]['colour'] === '#ff8000', 'actual direction colour retained');
	$shape = array_values(array_filter($map->imap->shapes, fn($s) => $s->name === 'LINK:L' . $link->id . ':' . IN))[0];
	animation_assert(count($data['regions'][0]['points']) === count($shape->points), 'full rendered curved polygon retained');
	animation_assert(str_contains($map->MakeHTML(), 'data-wm-traffic='), 'viewer HTML contains safe metadata');
	$map->imageuri = '?action=liveviewimage&id=123';
	$live = new DOMDocument;
	$live->loadHTML($map->MakeHTML('weathermap_live_imap'));
	$liveImage = $live->getElementsByTagName('img')->item(0);
	$liveHolder = $liveImage->parentNode->parentNode;
	$liveData = json_decode($liveHolder->getAttribute('data-wm-traffic'), true);
	animation_assert($liveImage->getAttribute('src') === '?action=liveviewimage&id=123' && $liveImage->getAttribute('usemap') === '#weathermap_live_imap', 'live-view endpoint and imagemap preserved');
	animation_assert(count($liveData['regions']) === 1 && $liveImage->getAttribute('width') === '400' && $liveImage->getAttribute('height') === '300', 'live view carries pulse metadata at logical dimensions');
	foreach ([[255,0,0], [140,0,255], [0,192,255], [35,120,80], [255,180,0]] as $rgb) {
		$link->colours[IN] = new Colour(...$rgb);
		$expected = sprintf('#%02x%02x%02x', ...$rgb);
		animation_assert(wm_traffic_animation_data($map)['regions'][0]['colour'] === $expected, 'glow follows preset colour ' . $expected);
	}
	$link->outpercent = 100;
	animation_assert(count(wm_traffic_animation_data($map)['regions']) === 2, 'both hot directions included');
	$link->linkstyle = 'oneway';
	animation_assert(count(wm_traffic_animation_data($map)['regions']) === 1, 'oneway excludes inbound');
	$link->outpercent = NAN;
	animation_assert(wm_traffic_animation_data($map)['regions'] === [], 'nonfinite load excluded');
	$link->outpercent = 79; $link->inpercent = 79; $link->linkstyle = 'twoway';
	animation_assert(wm_traffic_animation_data($map)['regions'] === [], 'low traffic has no glow');
	foreach (['curved', 'angled'] as $style) {
		foreach (['classic', 'compact'] as $arrow) {
			$link->viastyle = $style; $link->arrowstyle = $arrow;
			$link->outpercent = 90; $link->inpercent = 95;
			$map->DrawMap('null');
			$rendered = wm_traffic_animation_data($map);
			animation_assert(count($rendered['regions']) === 2, 'both VIA directions render once');
			foreach ([OUT, IN] as $index => $direction) {
				$flat = $link->traffic_glow_points[$direction];
				foreach ($rendered['regions'][$index]['points'] as $i => $point) {
					animation_assert($point === [$flat[2*$i] + 0.5, $flat[2*$i+1] + 0.5], 'glow matches GD pixel centres for ' . $style . ' ' . $arrow);
				}
			}
			animation_assert(count($rendered['occlusions']) >= 2, 'traffic labels are protected from glow');
		}
	}
	$map->imap->addArea('Polygon', 'NODE:polygon-label', '', [0, 0, 20, 0, 20, 20]);
	animation_assert(in_array([[0,0],[20,0],[20,20]], wm_traffic_animation_data($map)['occlusions'], true), 'polygon label occlusion retained');
	// A padded transparent icon must not cut off the halo before its visible artwork.
	$node = $map->nodes['A'];
	$node->image = imagecreatetruecolor(20, 20);
	imagealphablending($node->image, false);
	imagefill($node->image, 0, 0, imagecolorallocatealpha($node->image, 0, 0, 0, 127));
	imagefilledrectangle($node->image, 8, 3, 11, 9, imagecolorallocatealpha($node->image, 20, 30, 40, 0));
	imagefilledrectangle($node->image, 4, 10, 15, 15, imagecolorallocatealpha($node->image, 20, 30, 40, 0));
	$node->centre_x = 10; $node->centre_y = 10;
	$map->imap->addArea('Rectangle', 'NODE:N' . $node->id . ':0', '', [30, 30, 50, 50]);
	$masked = wm_traffic_animation_data($map)['occlusions'];
	animation_assert(!in_array([[30,30],[50,30],[50,50],[30,50]], $masked, true), 'transparent canvas rectangle does not mask glow');
	animation_assert(in_array([[38,33],[42,33],[42,40],[46,40],[46,46],[34,46],[34,40],[38,40]], $masked, true), 'mask follows the stepped visible icon silhouette at its drawn origin');
	imagefill($node->image, 0, 0, imagecolorallocatealpha($node->image, 0, 0, 0, 127));
	imagefilledrectangle($node->image, 0, 0, 19, 19, imagecolorallocatealpha($node->image, 0, 0, 0, 127));
	animation_assert(wm_traffic_node_occlusions($node) === [], 'fully transparent images do not mask glow');
	$node->add_hint('editor_note', '1');
	animation_assert(in_array([[30,30],[50,30],[50,50],[30,50]], wm_traffic_animation_data($map)['occlusions'], true), 'transparent sharp-note bitmap keeps the text rectangle protected');
	$node->add_hint('editor_note', '0');
	$node->image = imagecreate(4, 4);
	$clear = imagecolorallocate($node->image, 255, 0, 255);
	imagecolortransparent($node->image, $clear);
	$ink = imagecolorallocate($node->image, 0, 0, 0);
	imagesetpixel($node->image, 1, 0, $ink);
	imagesetpixel($node->image, 2, 3, $ink);
	$node->x = 40.5; $node->y = 40.5; $node->centre_x = 2; $node->centre_y = 2;
	animation_assert(wm_traffic_node_occlusions($node) === [
		[[39,38],[40,38],[40,39],[39,39]],
		[[40,41],[41,41],[41,42],[40,42]],
	], 'palette transparency, blank rows and integer drawing origin are respected');
	$map->add_hint('traffic_pulse', '0');
	animation_assert(wm_traffic_animation_data($map)['occlusions'] === [] && wm_traffic_animation_data($map)['regions'] === [], 'disabled map omits all glow metadata');
	$map->add_hint('traffic_pulse', '1');
	foreach ($map->links as $link) { $link->inpercent = 0; $link->outpercent = 0; }
	animation_assert(wm_traffic_animation_data($map)['occlusions'] === [], 'quiet map omits occlusions');

	echo "Traffic glow regression PASS\n";
} finally { unlink($file); }
