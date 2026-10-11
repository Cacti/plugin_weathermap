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
$plugin = dirname(__DIR__, 2);
chdir($plugin);
require $plugin . '/setup.php';
require $plugin . '/lib/WeatherMap.class.php';
require $plugin . '/lib/editor.inc.php';
require $plugin . '/lib/editor.colours.php';
require $plugin . '/lib/editor.actions.php';
function assets_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
$dir = sys_get_temp_dir() . '/wm-assets-' . bin2hex(random_bytes(4));
mkdir($dir);
try {
	$file = $dir . '/map.conf';
	file_put_contents($file, "WIDTH 600\nHEIGHT 300\nSCALE 0 100 0 255 0 255 0 0 Traffic\nSCALE custom 0 100 0 0 255\nNODE A\n POSITION 50 50\nNODE B\n POSITION 250 50\nLINK inherited\n NODES A B\nLINK custom\n NODES A B\n OUTLINECOLOR 255 0 255\n");
	$map = new WeatherMap;
	assets_assert($map->ReadConfig($file), 'read');
	$data = wm_asset_colour_data($map);
	assets_assert($data['pulse'] === true, 'pulse enabled by default');
	$data['pulse'] = true;
	assets_assert($data['rows'][0]['end'] === '#ff0000', 'gradient loaded');
	$data['background'] = '#eeeeee'; $data['outline'] = '#0000ff';
	$data['rows'][0]['colour'] = '#008000';
	wm_asset_save_colours($map, $data);
	assets_assert($map->links['inherited']->outlinecolour === [0,0,255], 'default propagates');
	assets_assert($map->links['custom']->outlinecolour == [255,0,255], 'override preserved');
	assets_assert($map->WriteConfig($file), 'save');
	$saved = new WeatherMap; $saved->ReadConfig($file);
	$loaded = wm_asset_colour_data($saved);
	assets_assert($loaded['pulse'] === true, 'pulse enabled survives config roundtrip');
	$loaded['pulse'] = false; wm_asset_save_colours($saved, $loaded);
	$saved->WriteConfig($file);
	$disabled = new WeatherMap; $disabled->ReadConfig($file);
	assets_assert(wm_asset_colour_data($disabled)['pulse'] === false, 'pulse disabled survives config roundtrip');
	assets_assert($loaded['background'] === '#eeeeee' && $loaded['outline'] === '#0000ff', 'colour roundtrip');
	assets_assert(count($loaded['rows']) === 2 && $loaded['rows'][0]['end'] === '#ff0000' && $loaded['rows'][0]['tag'] === 'Traffic', 'scales and gradients roundtrip');
	$traffic_only = $data; unset($traffic_only['background']);
	wm_asset_save_colours($map, $traffic_only);
	assets_assert(wm_asset_colour_data($map)['background'] === '#eeeeee', 'traffic save preserves background');
	$shared = clone $map->nodes['A'];
	$shared->usescale = 'custom';
	$map->nodes['custom'] = $shared;
	$missing = $data;
	$missing['rows'] = array_values(array_filter($data['rows'], function($row) { return $row['scale'] !== 'custom'; }));
	try { wm_asset_save_colours($map, $missing); throw new RuntimeException('accepted removal of same-name node scale'); } catch (InvalidArgumentException $expected) {}
	unset($map->nodes['custom']);
	$map->nodes['A']->useiconscale = 'custom';
	try { wm_asset_save_colours($map, $missing); throw new RuntimeException('accepted removal of icon scale'); } catch (InvalidArgumentException $expected) {}
	$map->nodes['A']->useiconscale = 'none';
	foreach (['1_hour', 'one-hour', '_hour'] as $badName) {
		$invalid = $data; $invalid['rows'][1]['scale'] = $badName;
		try { wm_asset_save_colours($map, $invalid); throw new RuntimeException('accepted parser-incompatible scale name'); } catch (InvalidArgumentException $expected) {}
	}
	$negative = $data;
	$negative['rows'][] = ['scale' => 'Temperature_1', 'bottom' => -20, 'top' => -1, 'colour' => '#0000ff', 'end' => '', 'tag' => 'Cold'];
	wm_asset_save_colours($map, $negative);
	$map->WriteConfig($file);
	$negativeMap = new WeatherMap; $negativeMap->ReadConfig($file);
	assets_assert($negativeMap->colours['Temperature_1']['-20_-1']['bottom'] === -20.0, 'negative custom scale survives engine reload');
	wm_asset_save_colours($map, $data);
	$before = $map->colours;
	foreach ([[0, 100, '0.0', '100.0'], [0, 100, '0e0', '1e2'], [0, 100, '-0.0', 100.0000001], [-20, -1, '-20.0000001', '-1.0000001']] as $range) {
		$duplicate = $data;
		$duplicate['rows'][] = ['scale' => 'Duplicates', 'bottom' => $range[0], 'top' => $range[1], 'colour' => '#ff0000'];
		$duplicate['rows'][] = ['scale' => 'Duplicates', 'bottom' => $range[2], 'top' => $range[3], 'colour' => '#0000ff'];
		try { wm_asset_save_colours($map, $duplicate); throw new RuntimeException('accepted equivalent serialized range'); } catch (InvalidArgumentException $expected) {}
		assets_assert($map->colours === $before, 'duplicate range validation leaves map unchanged');
	}
	foreach (['bad range', 'bad colour', 'config injection'] as $bad) {
		$invalid = $data;
		if ($bad === 'bad range') $invalid['rows'][0]['top'] = -1;
		if ($bad === 'bad colour') $invalid['outline'] = '#nope';
		if ($bad === 'config injection') $invalid['rows'][0]['tag'] = "bad\nTITLE injected";
		try { wm_asset_save_colours($map, $invalid); throw new RuntimeException('accepted ' . $bad); } catch (InvalidArgumentException $expected) {}
		assets_assert($map->colours === $before, 'invalid request is atomic');
	}
	$GLOBALS['__test_nfilter_request'] = ['map_title' => 'Background test', 'map_legend' => 'Traffic', 'map_stamp' => 'Created', 'map_htmlfile' => 'test.html', 'map_pngfile' => 'test.png', 'map_width' => '600', 'map_height' => '300', 'map_bgfile' => '--NONE--', 'map_background_sizing' => 'fit', 'map_linkdefaultwidth' => '7', 'map_linkdefaultbwin' => '100M', 'map_linkdefaultbwout' => '100M', 'map_background_colour' => '#112233'];
	setMapProperties($file);
	$props = new WeatherMap; $props->ReadConfig($file);
	assets_assert(wm_asset_colour_data($props)['background'] === '#112233', 'map properties saves background');
	unset($GLOBALS['__test_nfilter_request']['map_background_colour']);
	setMapProperties($file);
	$props = new WeatherMap; $props->ReadConfig($file);
	assets_assert(wm_asset_colour_data($props)['background'] === '#112233', 'older properties client preserves background');
	$before_file = file_get_contents($file);
	$GLOBALS['__test_nfilter_request']['map_background_colour'] = '#invalid';
	setMapProperties($file);
	assets_assert(file_get_contents($file) === $before_file, 'invalid background does not write');
	$saved->DrawMap($dir . '/map.png');
	assets_assert(is_file($dir . '/map.png'), 'render after save');
	echo "Editor assets regression PASS\n";
} finally {
	foreach (glob($dir . '/*') as $path) unlink($path);
	rmdir($dir);
}
