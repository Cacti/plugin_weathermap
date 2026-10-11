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
require dirname(__DIR__) . "/bootstrap-unit.php";
require dirname(__DIR__, 2) . '/lib/editor.assets.php';
require dirname(__DIR__, 2) . '/lib/editor.actions.php';
function image_format_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
$root = dirname(__DIR__, 2);
$temporary = $root . '/tests/wm-image-formats-' . bin2hex(random_bytes(4));
mkdir($temporary);
$created = [];
$config = $root . '/configs/image-formats-test-' . bin2hex(random_bytes(4)) . '.conf';
try {
	foreach (['png' => IMAGETYPE_PNG, 'jpeg' => IMAGETYPE_JPEG, 'gif' => IMAGETYPE_GIF] as $extension => $type) {
		$source = $temporary . '/source.' . $extension;
		$image = imagecreatetruecolor(12, 8);
		imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
		if ($extension === 'png') imagepng($image, $source);
		elseif ($extension === 'jpeg') imagejpeg($image, $source);
		else imagegif($image, $source);
		unset($image);
		file_put_contents($source, 'UNTRUSTED_TRAILING_BYTES', FILE_APPEND);
		foreach (['objects', 'backgrounds'] as $kind) {
			$name = wm_asset_store_image($kind, $source, 'misleading.php');
			$path = wm_asset_image_path($kind, $name);
			$created[] = $path;
			$size = getimagesize($path);
			image_format_assert($size[0] === 12 && $size[1] === 8 && $size[2] === $type, 'decoded format and dimensions preserved');
			image_format_assert(strpos(file_get_contents($path), 'UNTRUSTED_TRAILING_BYTES') === false, 'uploaded trailing bytes removed');
			$listed = array_values(array_filter(wm_asset_image_list(), function($item) use ($kind, $name) { return $item['kind'] === $kind && $item['name'] === $name; }));
			image_format_assert(count($listed) === 1 && $listed[0]['deletable'], 'each format listed and deletable');
			file_put_contents($config, 'BACKGROUND images/' . $kind . '/' . $name . "\n");
			image_format_assert(in_array(basename($config), wm_asset_image_users($name), true), 'reference protection applies to every format');
		}
	}
	file_put_contents($config, "ICON images/objects/core-router.png\n# router.png is merely a comment\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'substring and comment do not count as references');
	file_put_contents($config, "ICON /outside/router.png\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'complete paths distinguish external images');
	file_put_contents($config, "ICON 32 32 images/objects/updown_{node:this:bandwidth_in}.png\n");
	image_format_assert(in_array(basename($config), wm_asset_image_users('updown_0.png', 'objects'), true), 'templated icon cannot be deleted');
	image_format_assert(wm_asset_image_users('updown_0.png', 'backgrounds') === [], 'library kinds are distinct');
	file_put_contents($config, "ICON images/objects/" . basename($created[0]) . "\n");
	$listed = array_values(array_filter(wm_asset_image_list(), function($item) use ($created) { return $item['kind'] === 'objects' && $item['name'] === basename($created[0]); }));
	image_format_assert($listed[0]['width'] === 12 && $listed[0]['height'] === 8 && !$listed[0]['deletable'] && $listed[0]['users'], 'dimensions and usage disable referenced image deletion');
	foreach (['safe.png', 'safe.jpg', 'safe.jpeg', 'safe.gif', 'safe.JPG'] as $name) image_format_assert(str_ends_with(wm_asset_image_path('backgrounds', $name), $name), 'accepted filename');
	foreach (['../outside.jpg', 'bad.php', 'bad.svg', 'bad.jpg.php', 'bad..gif'] as $name) {
		try { wm_asset_image_path('objects', $name); throw new RuntimeException('accepted invalid filename'); } catch (InvalidArgumentException $expected) {}
	}
	$invalid = $temporary . '/fake.png'; file_put_contents($invalid, 'not an image');
	try { wm_asset_store_image('objects', $invalid, 'fake.png'); throw new RuntimeException('accepted fake image'); } catch (InvalidArgumentException $expected) {}
	try { wm_asset_upload_image('objects', ['error' => UPLOAD_ERR_OK, 'tmp_name' => $invalid, 'name' => 'fake.png']); throw new RuntimeException('accepted non-upload'); } catch (InvalidArgumentException $expected) {}
	$large = $temporary . '/large.png'; $handle = fopen($large, 'w'); ftruncate($handle, 5 * 1024 * 1024 + 1); fclose($handle);
	try { wm_asset_store_image('objects', $large, 'large.png'); throw new RuntimeException('accepted oversized file'); } catch (InvalidArgumentException $expected) {}
	$image = imagecreatetruecolor(2001, 2000); imagepng($image, $large); unset($image);
	try { wm_asset_store_image('backgrounds', $large, 'large.png'); throw new RuntimeException('accepted excessive dimensions'); } catch (InvalidArgumentException $expected) {}
	$included = $temporary . '/included.conf';
	file_put_contents($included, "BACKGROUND images/backgrounds/included-image.png\n");
	file_put_contents($config, "INCLUDE " . $included . "\n");
	image_format_assert(in_array(basename($included), wm_asset_image_users('included-image.png'), true), 'included configurations protect images from deletion');
	// Read-only listings, reference protection and the final server unlink.
	$_SERVER['REQUEST_METHOD'] = 'GET';
	ob_start(); wm_editor_assets($config, 'image_data'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['images']), 'library JSON returned');
	$deletePath = $created[0]; $deleteName = basename($deletePath);
	$GLOBALS['__test_nfilter_request'] = ['image_kind' => 'objects', 'image_name' => $deleteName];
	ob_start(); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['error']) && is_file($deletePath), 'GET cannot delete an image');
	$_SERVER['REQUEST_METHOD'] = 'POST';
	file_put_contents($config, "ICON images/objects/" . $deleteName . "\n");
	ob_start(); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['error']) && is_file($deletePath), 'referenced image cannot be deleted');
	file_put_contents($config, "# no image reference\n");
	ob_start(); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['images']) && !is_file($deletePath), 'delete removes the actual server file');
	echo "Editor image formats regression PASS\n";
} finally {
	foreach ($created as $path) if (is_file($path)) unlink($path);
	if (is_file($config)) unlink($config);
	foreach (glob($temporary . '/*') as $path) unlink($path);
	rmdir($temporary);
}
