<?php
// Render published examples with the actual engine; --update refreshes PNG/SVG assets.
$root = dirname(__DIR__, 2);
require $root . '/tests/bootstrap-unit.php';
require $root . '/setup.php';
require $root . '/lib/WeatherMap.class.php';
if (!defined('MESSAGE_LEVEL_INFO')) define('MESSAGE_LEVEL_INFO', 0);
if (!function_exists('cacti_count')) {
	function cacti_count($value) { return is_countable($value) ? count($value) : 0; }
}
chdir($root);
$update = in_array('--update', $argv, true);
$GLOBALS['weathermap_warncount'] = 0;
$variants = new WeatherMap();
$variants->rrdtool = PHP_BINARY;
if (!$variants->ReadConfig('tests/Support/DocumentationReferenceVariants.conf')) throw new RuntimeException('Cannot parse reference variants.');
$variants->ReadData();
$distance = hypot($variants->nodes['remote']->x - $variants->nodes['core']->x, $variants->nodes['remote']->y - $variants->nodes['core']->y);
if (abs($distance - 100) > .001 || $variants->nodes['core']->labeloffset !== 'N20') throw new RuntimeException('Reference polar/label offset syntax differs from the engine.');
if ($variants->nodes['core']->scaletype !== 'absolute' || $variants->nodes['core']->iconscaletype !== 'percent') throw new RuntimeException('Reference scale syntax differs from the engine.');
if (abs($variants->links['calc']->inpercent - 10) > .001 || abs($variants->links['calc']->outpercent - 8) > .001) throw new RuntimeException('Reference signed/scaled source arithmetic is incorrect.');
if (abs($variants->links['defaults']->max_bandwidth_in - 1000000000) > .001 || $variants->links['defaults']->commentcurve != 1) throw new RuntimeException('Reference implicit defaults differ from the engine.');
if ($GLOBALS['weathermap_warncount']) throw new RuntimeException('Warnings in reference variants.');
$examples = [
	'label-positions-2.0' => [660, 210],
	'curved-comments-2.0' => [660, 380],
	'combined-sources-2.0' => [660, 160],
	'traffic-pulse-2.0' => [660, 180],
	'editor-settings-2.0' => [1320, 600], // Notes render at 2× resolution for sharp text.,
];
foreach ($examples as $name => [$width, $height]) {
	$GLOBALS['weathermap_warncount'] = 0;
	$map = new WeatherMap();
	// Static sources do not invoke RRDtool; supply an existing executable for its Init check.
	$map->rrdtool = PHP_BINARY;
	if (!$map->ReadConfig("docs/example/$name.conf")) throw new RuntimeException("Cannot parse $name");
	$map->ReadData();
	if ($name === 'editor-settings-2.0') {
		$note = $map->nodes['site_note'];
		if ($map->get_hint('background_sizing') !== 'fit' || $map->nodes['core']->labeloffset !== 'S60' || $map->nodes['core']->get_hint('editor_label_position') !== 'S60') throw new RuntimeException('Published background sizing or editor label preset is incorrect.');
		if ($note->get_hint('editor_note') !== '1' || $note->get_hint('editor_note_escaped') !== '1' || $note->get_hint('editor_note_align') !== 'centre' || wm_note_decode_text($note->label) !== "Example site\nTraffic overview") throw new RuntimeException('Published note syntax or alignment is incorrect.');
		if (abs($map->links['uplink']->inpercent - 90) > .001 || abs($map->links['uplink']->outpercent - 20) > .001) throw new RuntimeException('Published settings example has incorrect inherited capacity.');
	}
	if ($name === 'combined-sources-2.0') {
		$link = $map->links['combined'];
		if (abs($link->inpercent - 30) > .001 || abs($link->outpercent - 15) > .001) throw new RuntimeException('Combined example has incorrect units or arithmetic.');
	}
	if ($update) {
		$output = "docs/images/reference-$name.png";
	} else {
		$temporary = tempnam(sys_get_temp_dir(), 'wm-doc-');
		$output = $temporary . '.png';
		unlink($temporary);
	}
	try {
		$map->DrawMap($output);
		if ($name === 'traffic-pulse-2.0') {
			require_once $root . '/lib/WeatherMap.animation.php';
			$data = wm_traffic_animation_data($map);
			if (count($data['regions']) !== 1 || abs($map->links['uplink']->inpercent - 90) > .001 || abs($map->links['uplink']->outpercent - 20) > .001) throw new RuntimeException('Pulse example must highlight only the 90% direction.');
			if ($update) file_put_contents('docs/images/reference-traffic-pulse-2.0.svg', documentationPulseSvg($data, file_get_contents($output)));
			$map->add_hint('traffic_pulse', '0');
			if (wm_traffic_animation_data($map)['enabled']) throw new RuntimeException('Pulse disable example differs from the engine.');
		}
		$size = getimagesize($output);
		if ($size[0] !== $width || $size[1] !== $height || $GLOBALS['weathermap_warncount']) throw new RuntimeException("Invalid example render: $name");
	} finally {
		if (!$update && is_file($output)) unlink($output);
	}
}
echo "Five published map examples render without warnings; source totals, pulse eligibility, editor presets and note syntax verified.\n";

/** Illustrated animation uses the engine's real polygon/mask output and the viewer's glow style. */
function documentationPulseSvg($data, $png) {
	$width = (int)$data['width']; $height = (int)$data['height'];
	$document = new DOMDocument('1.0', 'UTF-8');
	$svg = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
	$svg->setAttribute('viewBox', "0 0 $width $height");
	$svg->setAttribute('width', (string)$width); $svg->setAttribute('height', (string)$height);
	$svg->setAttribute('role', 'img'); $svg->setAttribute('aria-labelledby', 'title description');
	$document->appendChild($svg);
	$add = function ($parent, $name, $attributes = [], $text = null) use ($document) {
		$element = $document->createElementNS('http://www.w3.org/2000/svg', $name);
		foreach ($attributes as $key => $value) $element->setAttribute($key, (string)$value);
		if ($text !== null) $element->appendChild($document->createTextNode($text));
		$parent->appendChild($element); return $element;
	};
	$points = fn($list) => implode(' ', array_map(fn($p) => implode(',', $p), $list));
	$add($svg, 'title', ['id'=>'title'], 'High-load pulse example');
	$add($svg, 'desc', ['id'=>'description'], 'Illustrated map with 90 percent inbound utilisation and 20 percent outbound. Only the 90 percent arrow pulses. Values are fixed demonstration data.');
	$add($svg, 'style', [], '.pulse{opacity:.25;animation:pulse 2.5s ease-in-out infinite}@keyframes pulse{0%,100%{opacity:.2}50%{opacity:1}}@media(prefers-reduced-motion:reduce){.pulse{animation:none;opacity:.65}}');
	$defs = $add($svg, 'defs');
	$mask = $add($defs, 'mask', ['id'=>'labels','maskUnits'=>'userSpaceOnUse','maskContentUnits'=>'userSpaceOnUse','mask-type'=>'luminance','x'=>-30,'y'=>-30,'width'=>$width+60,'height'=>$height+60]);
	$add($mask, 'rect', ['x'=>-30,'y'=>-30,'width'=>$width+60,'height'=>$height+60,'fill'=>'white']);
	foreach ($data['occlusions'] as $polygon) $add($mask, 'polygon', ['points'=>$points($polygon),'fill'=>'black','stroke'=>'black','stroke-width'=>2]);
	$filter = $add($defs, 'filter', ['id'=>'glow','filterUnits'=>'userSpaceOnUse','x'=>-30,'y'=>-30,'width'=>$width+60,'height'=>$height+60,'color-interpolation-filters'=>'sRGB']);
	foreach ([2,5,9] as $radius) $add($filter, 'feGaussianBlur', ['in'=>'SourceGraphic','stdDeviation'=>$radius,'result'=>"glow-$radius"]);
	$merge = $add($filter, 'feMerge');
	foreach ([2,5,9] as $radius) $add($merge, 'feMergeNode', ['in'=>"glow-$radius"]);
	$add($merge, 'feMergeNode', ['in'=>'SourceGraphic']);
	$add($svg, 'image', ['href'=>'data:image/png;base64,'.base64_encode($png),'width'=>$width,'height'=>$height]);
	$group = $add($svg, 'g', ['mask'=>'url(#labels)']);
	foreach ($data['regions'] as $region) $add($group, 'polygon', ['points'=>$points($region['points']),'fill'=>'none','stroke'=>$region['colour'],'stroke-width'=>2,'stroke-linejoin'=>'round','filter'=>'url(#glow)','class'=>'pulse']);
	return $document->saveXML();
}
