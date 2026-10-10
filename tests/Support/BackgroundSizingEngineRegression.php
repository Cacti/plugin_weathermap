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
$plugin = dirname(__DIR__,2);
chdir($plugin);
require $plugin . '/setup.php';
require $plugin . '/lib/WeatherMap.class.php';
require $plugin . '/lib/editor.inc.php';
require $plugin . '/lib/editor.actions.php';

if (!function_exists('cacti_count')) {
	function cacti_count($value) {
		return is_countable($value) ? count($value) : 0;
	}
}

if (!function_exists('clean_up_name')) {
	function clean_up_name($value) {
		return preg_replace('/[^A-Za-z0-9_\-.]/','_',$value);
	}
}

if (!defined('MESSAGE_LEVEL_INFO')) {
	define('MESSAGE_LEVEL_INFO',0);
}
function wm_engine_assert($value,$message) {
	if (!$value) {
		throw new RuntimeException($message);
	}
}
$directory = sys_get_temp_dir() . '/wm-engine-' . bin2hex(random_bytes(8));
mkdir($directory);

try {
	$bg = imagecreatetruecolor(200,100);
	imagefill($bg,0,0,imagecolorallocate($bg,255,0,0));
	imagepng($bg,$directory . '/background.png');

	foreach (['fit'=>[120, 120], 'stretch'=>[120, 120], 'image'=>[200, 100], 'invalid'=>[200, 100]] as $mode=>$size) {
		$map             = new WeatherMap();
		$map->width      = 120;
		$map->height     = 120;
		$map->background = $directory . '/background.png';
		$map->add_hint('background_sizing', 'image');
		$map->WriteConfig($directory . '/' . $mode . '.conf');
		$GLOBALS['__test_nfilter_request'] = [
			'map_title'            => 'Background test', 'map_legend' => 'Traffic', 'map_stamp' => '',
			'map_htmlfile'         => '', 'map_pngfile' => '', 'map_width' => '120', 'map_height' => '120',
			'map_bgfile'           => $directory . '/background.png', 'map_background_sizing' => $mode,
			'map_linkdefaultwidth' => '7', 'map_linkdefaultbwin' => '100M', 'map_linkdefaultbwout' => '100M',
		];
		setMapProperties($directory . '/' . $mode . '.conf');
		$saved = new WeatherMap();
		$saved->ReadConfig($directory . '/' . $mode . '.conf');
		wm_engine_assert($saved->get_hint('background_sizing') === ($mode === 'invalid' ? 'image' : $mode), $mode . ' editor persistence');
		if ($mode === 'invalid') {
			$saved->add_hint('background_sizing', 'invalid');
		}
		$saved->DrawMap($directory . '/' . $mode . '.png');
		$image = imagecreatefrompng($directory . '/' . $mode . '.png');
		wm_engine_assert([imagesx($image), imagesy($image)] === $size,$mode . ' canvas');
		$pixel = imagecolorsforindex($image,imagecolorat($image,60,1));
		wm_engine_assert(($pixel['red'] === 255 && $pixel['green'] === 0) === ($mode !== 'fit'),$mode . ' fit proportions');
		imagedestroy($image);
	}

	foreach (['fit' => [120, 120], 'stretch' => [120, 120], 'image' => [200, 100], 'invalid' => [200, 100]] as $mode => $size) {
		$map = new WeatherMap();
		$map->width = 120;
		$map->height = 120;
		$map->background = $directory . '/background.png';
		$map->add_hint('background_sizing', 'stretch');
		$map->postprocessclasses = ['SizingOverride'];
		$map->plugins['post']['SizingOverride'] = new class($mode) {
			public function __construct(private string $mode) {}
			public function run($map) {
				$map->add_hint('background_sizing', $this->mode);
			}
		};
		$file = $directory . '/post-' . $mode . '.png';
		$map->DrawMap($file);
		$image = imagecreatefrompng($file);
		wm_engine_assert([imagesx($image), imagesy($image)] === $size, $mode . ' post-processor canvas');
		$pixel = imagecolorsforindex($image, imagecolorat($image, 60, 1));
		wm_engine_assert(($pixel['red'] === 255 && $pixel['green'] === 0) === ($mode !== 'fit'), $mode . ' post-processor proportions');
		imagedestroy($image);
	}

	print "PASS\n";
} finally {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}rmdir($directory);
}
