<?php
require dirname(__DIR__) . '/bootstrap-unit.php';
$plugin = dirname(__DIR__, 2);
chdir($plugin);
require $plugin . '/setup.php';
require $plugin . '/lib/WeatherMap.class.php';
require $plugin . '/lib/editor.inc.php';
require $plugin . '/lib/editor.actions.php';

function layout_assert($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
}
function layout_submit($file, $request, $action) {
	$map = new WeatherMap();
	$map->ReadConfig($file);
	$request['config_revision'] = \WeathermapConfig\snapshotRevision($map->config_revision, $map->config_dependencies, $map->config_root_identity);
	$GLOBALS['__test_nfilter_request'] = $request;
	$action($file);
	$saved = new WeatherMap();
	layout_assert($saved->ReadConfig($file), 'reload');
	return $saved;
}
$file = sys_get_temp_dir() . '/wm-layout-' . bin2hex(random_bytes(8)) . '.conf';
try {
	file_put_contents($file, "NODE A\n POSITION 10 10\nNODE B\n POSITION 100 100\nLINK DEFAULT\n WIDTH 7\nLINK main\n NODES A B\nLINK other\n NODES A B\n WIDTH 12\n");
	$style = ['mapstyle_htmlstyle'=>'overlib', 'mapstyle_legendfont'=>'4', 'mapstyle_keystyle'=>'classic', 'mapstyle_linklabels'=>'percent', 'mapstyle_arrowstyle'=>'compact', 'mapstyle_nodefont'=>'3', 'mapstyle_linkfont'=>'2', 'mapstyle_commentfont'=>'1', 'mapstyle_nodewidth'=>'0', 'mapstyle_nodeheight'=>'0', 'mapstyle_linkwidth'=>'0', 'mapstyle_linkheight'=>'0', 'mapstyle_defaultwidth'=>'9', 'mapstyle_commentstyle'=>'center', 'mapstyle_labelposin'=>'80', 'mapstyle_labelposout'=>'20'];
	$saved = layout_submit($file, $style, 'setMapStyle');
	layout_assert($saved->links['main']->width == 9 && $saved->links['other']->width == 12, 'width moved to Map Style and preserves custom width');
	layout_assert($saved->links['main']->commentstyle === 'center' && $saved->links['main']->labeloffset_in == 80, 'style defaults applied');
	$link = ['link_name'=>'main', 'link_width'=>'9', 'link_infourl'=>'', 'link_hover'=>'', 'link_commentin'=>'In', 'link_commentout'=>'Out', 'link_commentposin'=>'95', 'link_commentposout'=>'5', 'link_target'=>'', 'link_bandwidth_in'=>'1G', 'link_bandwidth_out'=>'1G', 'viastyle'=>'curved', 'link_commentstyle'=>'edge', 'link_labelposmode'=>'custom', 'link_labelposin'=>'90', 'link_labelposout'=>'10'];
	$saved = layout_submit($file, $link, 'setLinkProperties');
	layout_assert($saved->links['main']->commentstyle === 'edge' && $saved->links['main']->labeloffset_in == 90, 'per-link override applied');
	layout_assert($saved->links['other']->commentstyle === 'center' && $saved->links['other']->labeloffset_in == 80, 'other link unchanged');
	$link['link_commentstyle'] = 'default'; $link['link_labelposmode'] = 'default';
	$saved = layout_submit($file, $link, 'setLinkProperties');
	layout_assert($saved->links['main']->commentstyle === 'center' && $saved->links['main']->labeloffset_in == 80, 'default restored');
	$style['mapstyle_defaultwidth'] = '0'; $style['mapstyle_labelposin'] = '101';
	$saved = layout_submit($file, $style, 'setMapStyle');
	layout_assert($saved->links['DEFAULT']->width == 9 && $saved->links['DEFAULT']->labeloffset_in == 80, 'invalid layout values ignored');
	// Saving Map Properties must no longer reset the width now owned by Map Style.
	$saved = layout_submit($file, ['map_title'=>'Example', 'map_legend'=>'Load', 'map_stamp'=>'', 'map_htmlfile'=>'', 'map_pngfile'=>'', 'map_width'=>'600', 'map_height'=>'300', 'map_bgfile'=>'--NONE--', 'map_linkdefaultbwin'=>'1G', 'map_linkdefaultbwout'=>'1G'], 'setMapProperties');
	layout_assert($saved->links['DEFAULT']->width == 9, 'Map Properties preserves width');
	echo "Link layout controls regression PASS\n";
} finally {
	@unlink($file);
}
