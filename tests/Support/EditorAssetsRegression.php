<?php
/** Submit the page revision expected by production mutation routes. */
function wm_fixture_revision($file, $callback) {
	if (!array_key_exists('config_revision', $GLOBALS['__test_nfilter_request'] ?? []) && is_file($file)) {
		$map = new \WeatherMap; $map->ReadConfig($file);
		$GLOBALS['__test_nfilter_request']['config_revision'] = \WeathermapConfig\snapshotRevision($map->config_revision, $map->config_dependencies, $map->config_root_identity);
	}
	return $callback();
}

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
/**
 * Assert a real-engine regression condition.
 *
 * @param bool $value Whether the condition holds.
 * @param string $message Failure description.
 * @return void
 */
function assets_assert($value, $message) {
	if (!$value) {
		throw new RuntimeException($message);
	}
}
$dir = (realpath(sys_get_temp_dir()) ?: sys_get_temp_dir()) . '/wm-assets-' . bin2hex(random_bytes(4));
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
	assets_assert(cacti_count($loaded['rows']) === 2 && $loaded['rows'][0]['end'] === '#ff0000' && $loaded['rows'][0]['tag'] === 'Traffic', 'scales and gradients roundtrip');
	// Each independent edit starts from the latest persisted revision.
	$map = new WeatherMap;
	assets_assert($map->ReadConfig($file), 'reload after another editor saves');
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
	foreach ([1025, 5000] as $nameLength) {
		$oversized = $data; $oversized['rows'][0]['scale'] = str_repeat('A', $nameLength);
		try { wm_asset_save_colours($map, $oversized); throw new RuntimeException('accepted oversized scale name'); } catch (InvalidArgumentException $expected) {}
	}
	$bounded = $data; $bounded['rows'][] = ['scale'=>str_repeat('A',1024), 'bottom'=>-1e15, 'top'=>1e15, 'colour'=>'#008000','end'=>'#ffffff','tag'=>str_repeat('L',200)];
	wm_asset_save_colours($map,$bounded); $map->WriteConfig($file); $boundedMap = new WeatherMap; $boundedMap->ReadConfig($file);
	assets_assert(isset($boundedMap->colours[str_repeat('A',1024)]), 'maximum scale name and directive fields survive parser roundtrip');
	$noDefault = $data; $noDefault['rows'] = [['scale'=>'NamedOnly','bottom'=>0,'top'=>100,'colour'=>'#0000ff']];
	try { wm_asset_save_colours($map, $noDefault); throw new RuntimeException('accepted empty DEFAULT scale'); } catch (InvalidArgumentException $expected) {}
	$longName = 'Scale' . str_repeat('A', 70);
	$longScale = $data;
	$longScale['rows'][] = ['scale' => $longName, 'bottom' => 0, 'top' => 50, 'colour' => '#008000'];
	wm_asset_save_colours($map, $longScale); $map->WriteConfig($file);
	$longMap = new WeatherMap; $longMap->ReadConfig($file);
	assets_assert(isset($longMap->colours[$longName]), 'valid long scale name survives roundtrip');
	wm_asset_save_colours($map, $data);
	$before = $map->colours;
	foreach ([[0, 100, '0.0', '100.0'], [0, 100, '0e0', '1e2'], [0, 100, '-0.0', 100.0000001], [-20, -1, '-20.0000001', '-1.0000001'], [1001, 2001, 1002, 2002]] as $range) {
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
	wm_fixture_revision($file, fn () => setMapProperties($file));
	$props = new WeatherMap; $props->ReadConfig($file);
	assets_assert(wm_asset_colour_data($props)['background'] === '#112233', 'map properties saves background');
	unset($GLOBALS['__test_nfilter_request']['map_background_colour'], $GLOBALS['__test_nfilter_request']['config_revision']);
	$GLOBALS['__test_nfilter_request']['map_title'] = 'Older properties saved';
	http_response_code(200);
	wm_fixture_revision($file, fn () => setMapProperties($file));
	$props = new WeatherMap; $props->ReadConfig($file);
	assets_assert(http_response_code() === 200 && $props->title === 'Older properties saved' && wm_asset_colour_data($props)['background'] === '#112233', 'older properties client saves with fresh revision and preserves background');
	$before_file = file_get_contents($file);
	$GLOBALS['__test_nfilter_request']['map_background_colour'] = '#invalid';
	wm_fixture_revision($file, fn () => setMapProperties($file));
	assets_assert(file_get_contents($file) === $before_file, 'invalid background does not write');
	$GLOBALS['__test_nfilter_request'] = [];
	ob_start(); wm_editor_colours($file, 'colour_data'); $loadedColours = json_decode(ob_get_clean(), true);
	assets_assert($loadedColours['revision'] === hash_file('sha256', $file), 'colour load returns its revision');
	$GLOBALS['__test_nfilter_request'] = ['colours_json' => json_encode($data)];
	$_SERVER['REQUEST_METHOD'] = 'POST';
	ob_start(); wm_editor_colours($file, 'set_map_colours'); $missingRevision = json_decode(ob_get_clean(), true);
	assets_assert(http_response_code() === 409 && isset($missingRevision['error']) && file_get_contents($file) === $before_file, 'colour save requires a revision');
	$GLOBALS['__test_nfilter_request']['config_revision'] = $loadedColours['revision'];
	ob_start(); wm_editor_colours($file, 'set_map_colours'); $savedColours = json_decode(ob_get_clean(), true);
	assets_assert(isset($savedColours['revision']) && $savedColours['revision'] === hash_file('sha256', $file), 'colour save returns its new revision');
	$before_file = file_get_contents($file);
	$GLOBALS['__test_nfilter_request'] = ['config_revision' => str_repeat('0', 64), 'colours_json' => json_encode($data)];
	$_SERVER['REQUEST_METHOD'] = 'POST';
	ob_start(); wm_editor_colours($file, 'set_map_colours'); $staleResponse = json_decode(ob_get_clean(), true);
	assets_assert(http_response_code() === 409 && isset($staleResponse['error']) && file_get_contents($file) === $before_file, 'stale colour form is rejected without changing saved configuration');
	unset($GLOBALS['__test_nfilter_request']['config_revision']);
	$saved->DrawMap($dir . '/map.png');
	assets_assert(is_file($dir . '/map.png'), 'render after save');
	$shared = $dir . '/shared.conf';
	$root = $dir . '/included.conf';
	file_put_contents($shared, "SCALE 0 100 10 20 30 40 50 60 Shared\nSCALE Shared 0 100 0 0 255\n");
	file_put_contents($root, "INCLUDE $shared\nSCALE Local 0 100 255 0 0\n");
	$included = new WeatherMap; $included->ReadConfig($root);
	$inheritedData = wm_asset_colour_data($included);
	assets_assert($inheritedData['locked_scales'] === ['DEFAULT', 'Shared'], 'included scale names exposed as read-only');
	$beforeIncluded = $included->colours;
	foreach (['colour', 'end', 'tag', 'bottom', 'top', 'delete', 'rename', 'preset', 'append'] as $change) {
		$attempt = $inheritedData;
		if ($change === 'delete') array_shift($attempt['rows']);
		elseif ($change === 'rename') $attempt['rows'][0]['scale'] = 'Renamed';
		elseif ($change === 'preset') $attempt['rows'] = [['scale'=>'DEFAULT','bottom'=>0,'top'=>50,'colour'=>'#00ff00']];
		elseif ($change === 'append') $attempt['rows'][] = ['scale'=>'Shared','bottom'=>100,'top'=>200,'colour'=>'#00ff00'];
		else $attempt['rows'][0][$change] = ['colour'=>'#ff0000','end'=>'#0000ff','tag'=>'Edited','bottom'=>1,'top'=>99][$change];
		try { wm_asset_save_colours($included, $attempt); throw new RuntimeException('accepted included scale ' . $change); } catch (InvalidArgumentException $expected) {
			assets_assert(str_contains($expected->getMessage(), 'read-only'), 'shared scale validation explains restriction');
		}
		assets_assert($included->colours === $beforeIncluded, 'rejected shared scale edit leaves map unchanged');
	}
	// Endpoint rejection also leaves both source files byte-for-byte unchanged.
	$rootBytes = file_get_contents($root); $sharedBytes = file_get_contents($shared);
	$GLOBALS['__test_nfilter_request'] = ['config_revision'=>\WeathermapConfig\snapshotRevision($included->config_revision, $included->config_dependencies, $included->config_root_identity), 'colours_json'=>json_encode($attempt)];
	ob_start(); wm_editor_colours($root, 'set_map_colours'); $denied = json_decode(ob_get_clean(), true);
	assets_assert(http_response_code() === 400 && isset($denied['error']) && file_get_contents($root) === $rootBytes && file_get_contents($shared) === $sharedBytes, 'endpoint rejects shared scale changes without writing');
	$editable = $inheritedData;
	foreach ($editable['rows'] as &$row) {
		$row['bottom'] = (string)$row['bottom']; $row['top'] = (string)$row['top'];
		$row['colour'] = strtoupper($row['colour']);
		if ($row['scale'] === 'Local') $row['colour'] = '#008000';
	}
	unset($row); $editable['rows'] = array_reverse($editable['rows']); $editable['pulse'] = false;
	wm_asset_save_colours($included, $editable);
	assets_assert($included->colours['DEFAULT'] === $beforeIncluded['DEFAULT'] && $included->colours['Shared'] === $beforeIncluded['Shared'], 'unchanged shared scales retain exact entries');
	assets_assert($included->WriteConfig($root), 'local edits with shared scales save');
	$reloaded = new WeatherMap; $reloaded->ReadConfig($root);
	assets_assert($reloaded->colours['Local']['0_100']['green1'] === 128 && wm_asset_colour_data($reloaded)['pulse'] === false && file_get_contents($shared) === $sharedBytes, 'local scale and pulse edits survive reload without editing include');
	// A subsequent root read clears inherited provenance.
	$included->ReadConfig($file);
	assets_assert($included->included_scale_names === [], 'root load resets shared scale names');
	// Only the outermost read synthesises Classic, after all explicit scales are known.
	$template = $dir . '/template.conf'; $deep = $dir . '/deep.conf'; $presetFile = $dir . '/preset.conf';
	file_put_contents($deep, "NODE shared_template\n LABEL Template\n");
	file_put_contents($template, "INCLUDE $deep\n");
	file_put_contents($presetFile, "WIDTH 100\nHEIGHT 100\nINCLUDE $template\n");
	$presetMap = new WeatherMap; $presetMap->ReadConfig($presetFile);
	assets_assert($presetMap->numscales['DEFAULT'] === 9 && $presetMap->included_scale_names === [], 'template-only nested includes receive outer Classic fallback without locking it');
	$signals = wm_asset_colour_data($presetMap);
	$signals['rows'] = [['scale'=>'DEFAULT','bottom'=>0,'top'=>0,'colour'=>'#666666'], ['scale'=>'DEFAULT','bottom'=>0,'top'=>80,'colour'=>'#008000'], ['scale'=>'DEFAULT','bottom'=>80,'top'=>100,'colour'=>'#cc0000']];
	wm_asset_save_colours($presetMap, $signals); assets_assert($presetMap->WriteConfig($presetFile), 'preset with template-only includes saves');
	$presetReload = new WeatherMap; $presetReload->ReadConfig($presetFile);
	assets_assert($presetReload->numscales['DEFAULT'] === 3 && array_column(wm_asset_colour_data($presetReload)['rows'], 'colour') === ['#666666', '#008000', '#cc0000'], 'preset survives nested include reload without synthesized Classic ranges');
	// A solid override must also discard a previously parsed gradient of the same range.
	file_put_contents($deep, "SCALE 0 100 1 2 3\n");
	file_put_contents($presetFile, "SCALE 0 100 10 20 30 40 50 60\nINCLUDE $template\n");
	$solidReload = new WeatherMap; $solidReload->ReadConfig($presetFile);
	assets_assert(!isset($solidReload->colours['DEFAULT']['0_100']['red2']) && $solidReload->numscales['DEFAULT'] === 1, 'included solid range clears old gradient and counts effective ranges');
	// Local background/pulse controls override shared values only after includes.
	file_put_contents($deep, "BGCOLOR 10 20 30\nSET traffic_pulse 0\n"); $sharedSettingsBytes = file_get_contents($deep);
	file_put_contents($presetFile, "WIDTH 100\nHEIGHT 100\nINCLUDE $template\n");
	$inheritingMap = new WeatherMap; $inheritingMap->ReadConfig($presetFile);
	wm_asset_save_colours($inheritingMap, wm_asset_colour_data($inheritingMap));
	assets_assert($inheritingMap->WriteConfig($presetFile), 'unchanged inherited settings save');
	file_put_contents($deep, "BGCOLOR 50 60 70\nSET traffic_pulse 1\n");
	$inheritingReload = new WeatherMap; $inheritingReload->ReadConfig($presetFile);
	assets_assert(wm_asset_colour_data($inheritingReload)['background'] === '#323c46' && wm_asset_colour_data($inheritingReload)['pulse'] === true, 'unchanged settings keep inheriting future shared changes');
	file_put_contents($deep, $sharedSettingsBytes);
	$settingsMap = new WeatherMap; $settingsMap->ReadConfig($presetFile);
	$settings = wm_asset_colour_data($settingsMap); $settings['background'] = '#aabbcc'; $settings['pulse'] = true;
	wm_asset_save_colours($settingsMap, $settings); assets_assert($settingsMap->WriteConfig($presetFile), 'shared settings save');
	$settingsReload = new WeatherMap; $settingsReload->ReadConfig($presetFile); $settings = wm_asset_colour_data($settingsReload);
	assets_assert($settings['background'] === '#aabbcc' && $settings['pulse'] === true && file_get_contents($deep) === $sharedSettingsBytes, 'local background and pulse survive include reload without changing shared source');
	$settings['pulse'] = false; wm_asset_save_colours($settingsReload, $settings); assets_assert($settingsReload->WriteConfig($presetFile), 'pulse can be disabled again');
	$GLOBALS['__test_nfilter_request'] = ['map_title'=>'Local background', 'map_legend'=>'Traffic', 'map_stamp'=>'Created', 'map_htmlfile'=>'test.html', 'map_pngfile'=>'test.png', 'map_width'=>'100', 'map_height'=>'100', 'map_bgfile'=>'--NONE--', 'map_background_sizing'=>'fit', 'map_linkdefaultwidth'=>'7', 'map_linkdefaultbwin'=>'100M', 'map_linkdefaultbwout'=>'100M', 'map_background_colour'=>'#ffffff'];
	http_response_code(200); wm_fixture_revision($presetFile, fn () => setMapProperties($presetFile));
	$propertiesReload = new WeatherMap; $propertiesReload->ReadConfig($presetFile);
	assets_assert(http_response_code() === 200 && wm_asset_colour_data($propertiesReload)['background'] === '#ffffff' && wm_asset_colour_data($propertiesReload)['pulse'] === false, 'Map Properties background survives included settings and preserves pulse');
	file_put_contents($presetFile, "WIDTH 100\nHEIGHT 100\nINCLUDE $template\nSET traffic_pulse 1\nNODE Local\n POSITION 20 20\nBGCOLOR 200 210 220\n");
	$explicitMap = new WeatherMap; $explicitMap->ReadConfig($presetFile);
	assets_assert($explicitMap->WriteConfig($presetFile), 'existing explicit overrides save');
	$explicitReload = new WeatherMap; $explicitReload->ReadConfig($presetFile);
	assets_assert(wm_asset_colour_data($explicitReload)['background'] === '#c8d2dc' && wm_asset_colour_data($explicitReload)['pulse'] === true, 'existing local directives retain precedence, including background after node declarations');
	$propertiesReload = $explicitReload;
	// Ambiguous labels must fail before mutation; explicit gradients and none remain valid.
	$labelData = wm_asset_colour_data($propertiesReload); $labelBefore = $propertiesReload->colours;
	foreach (['1 2 3 Mbps', ' 1 2 3Mbps', '123 4 5'] as $ambiguous) {
		$invalidLabel = $labelData; $invalidLabel['rows'][0]['tag'] = $ambiguous; $invalidLabel['rows'][0]['end'] = '';
		try { wm_asset_save_colours($propertiesReload, $invalidLabel); throw new RuntimeException('ambiguous solid label accepted'); } catch (InvalidArgumentException $expected) {}
		assets_assert($propertiesReload->colours === $labelBefore, 'ambiguous label rejection is atomic');
	}
	foreach ([['#0000ff', '', 'Traffic 1 2 3 Mbps'], ['none', '', '1 2 3 Mbps'], ['#0000ff', '#ffffff', '1 2 3 Mbps']] as [$fill, $end, $tag]) {
		$validLabel = $labelData; $validLabel['rows'][0]['colour'] = $fill; $validLabel['rows'][0]['end'] = $end; $validLabel['rows'][0]['tag'] = $tag;
		wm_asset_save_colours($propertiesReload, $validLabel); assets_assert($propertiesReload->WriteConfig($presetFile), 'unambiguous label saves');
		$labelReload = new WeatherMap; $labelReload->ReadConfig($presetFile); $row = wm_asset_colour_data($labelReload)['rows'][0];
		assets_assert($row['colour'] === $fill && $row['end'] === $end && $row['tag'] === $tag, 'label and gradient survive roundtrip');
	}
	// Normal editor controllers use a relative map name: clone ownership must be absolute.
	$cloneRelative = 'configs/clone-test-' . bin2hex(random_bytes(4)) . '.conf';
	try {
		file_put_contents($cloneRelative, "WIDTH 100\nHEIGHT 100\nNODE A\n LABEL Original\n POSITION 10 20\n");
		$GLOBALS['__test_nfilter_request'] = ['param'=>'A']; http_response_code(200);
		wm_fixture_revision($cloneRelative, fn () => cloneNode($cloneRelative));
		$cloneReload = new WeatherMap; $cloneReload->ReadConfig($cloneRelative);
		assets_assert(http_response_code() === 200 && isset($cloneReload->nodes['A_copy']) && (int)$cloneReload->nodes['A_copy']->x === 40 && (int)$cloneReload->nodes['A_copy']->y === 50, 'relative controller clone is serialized and reloads at its offset: ' . json_encode([http_response_code(), array_keys($cloneReload->nodes), $cloneReload->nodes['A_copy']->x ?? null, $cloneReload->nodes['A_copy']->y ?? null]));
	} finally { unlink($cloneRelative); }
	$styleShared = $dir . '/style-shared.conf'; $styleRoot = $dir . '/style-root.conf';
	file_put_contents($styleShared, "NODE A\n POSITION 10 10\nNODE B\n POSITION 90 90\nLINK shared\n NODES A B\n OUTLINECOLOR 0 0 0\n");
	file_put_contents($styleRoot, "WIDTH 100\nHEIGHT 100\nINCLUDE $styleShared\nLINK local\n TEMPLATE shared\n NODES A B\nLINK custom\n NODES A B\n OUTLINECOLOR 255 0 255\n");
	$styleMap = new WeatherMap; $styleMap->ReadConfig($styleRoot);
	$oldSharedOutline = $styleMap->links['shared']->outlinecolour;
	$oldSharedFont = $styleMap->links['shared']->commentfont;
	$oldSharedComment = $styleMap->links['shared']->commentfontcolour;
	$styleData = wm_asset_colour_data($styleMap); $styleData['outline'] = '#0000ff';
	wm_asset_save_colours($styleMap, $styleData);
	$newFont = (int)$oldSharedFont === 3 ? 4 : 3;
	wm_comment_update_default($styleMap, 'commentfont', $newFont);
	wm_comment_update_default($styleMap, 'commentfontcolour', [255, 255, 255]);
	assets_assert($styleMap->links['shared']->outlinecolour === $oldSharedOutline && $styleMap->links['shared']->commentfont === $oldSharedFont && $styleMap->links['shared']->commentfontcolour === $oldSharedComment, 'included link style objects remain unchanged in memory');
	assets_assert($styleMap->WriteConfig($styleRoot), 'root link style overrides save');
	$styleReload = new WeatherMap; $styleReload->ReadConfig($styleRoot);
	assets_assert($styleReload->links['local']->outlinecolour == [0,0,255] && (int)$styleReload->links['local']->commentfont === $newFont && $styleReload->links['local']->commentfontcolour == [255,255,255], 'root-owned link overrides persist against the actual shared template');
	assets_assert($styleReload->links['shared']->outlinecolour == $oldSharedOutline && $styleReload->links['custom']->outlinecolour == [255,0,255], 'shared and custom link styles remain unchanged after reload');
	$precise = wm_asset_colour_data($styleReload); $beforePrecision = $styleReload->colours;
	foreach ([[0.00000001,0.00000002], ['1e-8','2e-8'], [0.1234567,0.2234567], [1001,2001]] as [$from,$to]) {
		$invalid = $precise; $invalid['rows'] = [['scale'=>'DEFAULT','bottom'=>$from,'top'=>$to,'colour'=>'#0000ff']];
		try { wm_asset_save_colours($styleReload, $invalid); throw new RuntimeException('lossy bounds accepted'); } catch (InvalidArgumentException $expected) {
			assets_assert(str_contains($expected->getMessage(), 'rounding'), 'lossy bound validation explains precision limit');
		}
		assets_assert($styleReload->colours === $beforePrecision, 'precision rejection leaves map unchanged');
	}
	$precise['rows'] = [['scale'=>'DEFAULT','bottom'=>0.000001,'top'=>0.123456,'colour'=>'#0000ff'], ['scale'=>'ExactUnits','bottom'=>1500,'top'=>2500,'colour'=>'#ff0000']];
	wm_asset_save_colours($styleReload, $precise); assets_assert($styleReload->WriteConfig($styleRoot), 'representable fractional and bandwidth-unit ranges save');
	$precisionReload = new WeatherMap; $precisionReload->ReadConfig($styleRoot);
	assets_assert($precisionReload->colours['DEFAULT']['0.000001_0.123456']['bottom'] === 0.000001 && $precisionReload->colours['DEFAULT']['0.000001_0.123456']['top'] === 0.123456 && $precisionReload->colours['ExactUnits']['1.5K_2.5K']['bottom'] === 1500.0, 'accepted bounds retain their exact numeric meaning after reload');
	echo "Editor assets regression PASS\n";
} finally {
	foreach (glob($dir . '/*') as $path) unlink($path);
	if (is_file($dir . '/.weathermap-config.lock')) unlink($dir . '/.weathermap-config.lock');
	rmdir($dir);
}
