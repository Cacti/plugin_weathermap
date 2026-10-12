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
require dirname(__DIR__, 2) . '/lib/editor.inc.php';
/**
 * Assert an image-format regression condition.
 *
 * @param bool $value Whether the condition holds.
 * @param string $message The failure description.
 *
 * @return void
 */
function image_format_assert($value, $message) {
	if (!$value) {
		throw new RuntimeException($message);
	}
}
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
			image_format_assert(cacti_count($listed) === 1 && $listed[0]['deletable'], 'each format listed and deletable');
			file_put_contents($config, 'BACKGROUND images/' . $kind . '/' . $name . "\n");
			image_format_assert(in_array(basename($config), wm_asset_image_users($name), true), 'reference protection applies to every format');
		}
	}
	file_put_contents($config, "ICON images/objects/core-router.png\n# router.png is merely a comment\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'substring and comment do not count as references');
	file_put_contents($config, "ICON images/objects/ROUTER.PNG\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') !== [], 'case-insensitive paths protect in-use images');
	image_format_assert(wm_asset_normalize_path('images\\objects\\Router.PNG', 'C:\\Cacti\\plugins\\weathermap') === 'C:/Cacti/plugins/weathermap/images/objects/Router.PNG', 'Windows relative path canonicalised');
	image_format_assert(wm_asset_normalize_path('D:\\assets\\router.png', 'C:/Cacti') === 'D:/assets/router.png', 'Windows absolute drive path retained');
	image_format_assert(wm_asset_normalize_path('\\\\server\\share\\router.png', 'C:/Cacti') === '//server/share/router.png', 'UNC absolute path retained');
	$jpegPath = $root . '/images/backgrounds/format-reload-' . bin2hex(random_bytes(4)) . '.jpeg';
	copy($created[3], $jpegPath); $created[] = $jpegPath;
	image_format_assert(in_array('images/backgrounds/' . basename($jpegPath), get_imagelist('backgrounds'), true), 'existing JPEG background survives selector reload');
	file_put_contents($config, "ICON /outside/router.png\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'complete paths distinguish external images');
	file_put_contents($config, "ICON 32 32 images/objects/updown_{node:this:bandwidth_in}.png\n");
	image_format_assert(in_array(basename($config), wm_asset_image_users('updown_0.png', 'objects'), true), 'templated icon cannot be deleted');
	image_format_assert(wm_asset_image_users('updown_0.png', 'backgrounds') === [], 'library kinds are distinct');
	file_put_contents($config, "ICON images/objects/" . basename($created[0]) . "\n");
	$listed = array_values(array_filter(wm_asset_image_list(), function($item) use ($created) { return $item['kind'] === 'objects' && $item['name'] === basename($created[0]); }));
	image_format_assert($listed[0]['width'] === 12 && $listed[0]['height'] === 8 && !$listed[0]['deletable'] && $listed[0]['users'], 'dimensions and usage disable referenced image deletion');
	image_format_assert(wm_editor_sanitize_file('images/backgrounds/legacy.JPG', ['png', 'jpg', 'gif', 'jpeg']) === 'images/backgrounds/legacy.JPG', 'uppercase background save retains path');
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
	$backup = $root . '/configs/ignored-backup-' . bin2hex(random_bytes(4)) . '.bak';
	file_put_contents($backup, "ICON images/objects/backup-only.png\n");
	try { image_format_assert(wm_asset_image_users('backup-only.png') === [], 'backup files are not map configurations'); } finally { unlink($backup); }
	// Missing optional includes must not disable the library, and one index serves all names.
	file_put_contents($config, "INCLUDE " . $temporary . "/missing-*.conf\nINCLUDE " . $included . "\n");
	$index = wm_asset_image_reference_index();
	file_put_contents($included, "# changed after indexing\n");
	image_format_assert(in_array(basename($included), wm_asset_image_users('included-image.png', 'backgrounds', $index), true), 'reference snapshot reused without rescanning configurations');
	image_format_assert(wm_asset_image_users('included-image.png', 'backgrounds') === [], 'fresh request reflects changed configuration');
	image_format_assert(is_array(wm_asset_image_list()), 'unmatched optional include permits listing');
	// Test directory rejection without renaming any shared checkout assets.
	$fixtureRoot = $temporary . '/fixture'; mkdir($fixtureRoot);
	$library = $fixtureRoot . '/images';
	image_format_assert(symlink($root . '/images', $library), 'create linked fixture library');
	try {
		foreach (['objects', 'backgrounds'] as $kind) {
			try { wm_asset_directory($kind, $fixtureRoot); throw new RuntimeException('accepted symlinked library parent'); } catch (InvalidArgumentException $expected) {}
		}
	} finally { unlink($library); rmdir($fixtureRoot); }
	$literal = $temporary . '/site[1].conf';
	file_put_contents($literal, "BACKGROUND images/backgrounds/literal-image.png\n");
	file_put_contents($config, "INCLUDE " . $literal . "\n");
	image_format_assert(in_array(basename($literal), wm_asset_image_users('literal-image.png'), true), 'literal brackets in INCLUDE protect referenced images');
	file_put_contents($config, "INCLUDE " . $temporary . "/site*.conf\n");
	image_format_assert(wm_asset_image_users('literal-image.png') === [], 'wildcard text is not expanded beyond renderer semantics');
	// A reference-scan failure must be reported before upload validation or persistence.
	$broken = $temporary . '/broken.conf';
	symlink($temporary . '/missing-target.conf', $broken);
	file_put_contents($config, "INCLUDE " . $broken . "\n");
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_FILES['image_file'] = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $source, 'name' => 'must-not-persist.png'];
	$GLOBALS['__test_nfilter_request'] = ['image_kind' => 'objects'];
	ob_start(); wm_editor_assets($config, 'upload_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(str_contains($response['error'], 'Cannot check configuration references'), 'configuration failure precedes upload persistence');
	image_format_assert(glob($root . '/images/objects/must-not-persist-*') === [], 'reference error cannot create an uploaded image');
	unlink($broken); unset($_FILES['image_file']);
	file_put_contents($config, "# no image references\n");
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
