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
$include = $file . '.included';
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
	// An override belongs to the link that chose it, never to its descendants.
	file_put_contents($file, "NODE A\n POSITION 10 10\nNODE B\n POSITION 100 100\nLINK parent\n COMMENTSTYLE center\n BWLABELPOS 90 10\n SET editor_commentstyle_override 1\n SET editor_labelpos_override 1\nLINK child\n TEMPLATE parent\n NODES A B\nLINK fixed_child\n TEMPLATE parent\n NODES A B\n COMMENTSTYLE center\n BWLABELPOS 90 10\n SET editor_commentstyle_override 1\n SET editor_labelpos_override 1\n");
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'template marker fixture');
	layout_assert($map->links['child']->get_hint('editor_commentstyle_override') === null && $map->links['child']->get_hint('editor_labelpos_override') === null, 'template markers are not inherited');
	layout_assert(str_contains($map->links['child']->asJS(), 'commentstylechoice:' . js_escape('default')), 'inherited child is shown as default');
	layout_assert($map->WriteConfig($file), 'save explicit child markers matching template markers');
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'reload matching markers');
	layout_assert($map->links['fixed_child']->get_hint('editor_commentstyle_override') === '1' && $map->links['fixed_child']->get_hint('editor_labelpos_override') === '1', 'explicit matching markers survive serialization');
	wm_link_apply_overrides($map, $map->links['child'], 'edge', 'custom', '75', '25');
	layout_assert($map->WriteConfig($file), 'save child override');
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'reload child override');
	wm_link_apply_overrides($map, $map->links['child'], 'default', 'default', null, null);
	layout_assert($map->WriteConfig($file), 'save reset');
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'reload reset');
	layout_assert($map->links['child']->get_hint('editor_commentstyle_override') === null && $map->links['child']->get_hint('editor_labelpos_override') === null, 'reset survives marked template reload');
	layout_assert($map->links['child']->commentstyle === 'center' && $map->links['child']->labeloffset_in == 90, 'reset inherits the named template values');

	// Default edits follow local template chains, but cannot cross an unchanged INCLUDE.
	$included = "LINK included\n COMMENTSTYLE edge\n BWLABELPOS 75 25\n WIDTH 7\n";
	file_put_contents($include, $included);
	file_put_contents($file, "INCLUDE $include\nNODE A\n POSITION 10 10\nNODE B\n POSITION 100 100\nLINK DEFAULT\n COMMENTSTYLE edge\n BWLABELPOS 75 25\n WIDTH 7\nLINK external_child\n TEMPLATE included\n NODES A B\nLINK external_grandchild\n TEMPLATE external_child\n NODES A B\nLINK local_parent\n SET site_role local\nLINK local_child\n TEMPLATE local_parent\n NODES A B\nLINK local_grandchild\n TEMPLATE local_child\n NODES A B\nLINK fixed_parent\n SET editor_commentstyle_override 1\n SET editor_labelpos_override 1\nLINK fixed_descendant\n TEMPLATE fixed_parent\n NODES A B\n");
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'included template fixture');
	wm_comment_apply_style($map, null, null, null, 'center');
	wm_link_apply_default_positions($map, '85', '15');
	wm_comment_update_default($map, 'width', 9);
	foreach (['included', 'external_child', 'external_grandchild'] as $name) {
		layout_assert($map->links[$name]->commentstyle === 'edge' && $map->links[$name]->labeloffset_in == 75 && $map->links[$name]->width == 7, 'included branch unchanged: ' . $name);
	}
	foreach (['local_parent', 'local_child', 'local_grandchild'] as $name) {
		layout_assert($map->links[$name]->commentstyle === 'center' && $map->links[$name]->labeloffset_in == 85 && $map->links[$name]->labeloffset_out == 15 && $map->links[$name]->width == 9, 'local template chain updated: ' . $name);
	}
	layout_assert($map->links['fixed_descendant']->commentstyle === 'edge' && $map->links['fixed_descendant']->labeloffset_in == 75, 'explicit parent stops propagation to descendants');
	layout_assert($map->WriteConfig($file), 'save inherited default edits');
	$map = new WeatherMap(); layout_assert($map->ReadConfig($file), 'reload included template edits');
	layout_assert(file_get_contents($include) === $included, 'included file is untouched');
	foreach (['external_child', 'external_grandchild'] as $name) {
		layout_assert($map->links[$name]->commentstyle === 'edge' && $map->links[$name]->labeloffset_in == 75 && $map->links[$name]->width == 7, 'included branch stable after reload: ' . $name);
		preg_match('/LINK ' . $name . '\n(.*?)(?=\nLINK |$)/s', file_get_contents($file), $block);
		layout_assert(!preg_match('/COMMENTSTYLE|BWLABELPOS|WIDTH|editor_.*override/', $block[1]), 'no accidental serialized override: ' . $name);
	}

	echo "Link layout controls regression PASS\n";
} finally {
	@unlink($file);
	@unlink($include);
}
