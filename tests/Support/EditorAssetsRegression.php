<?php
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
	$before = $map->colours;
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
