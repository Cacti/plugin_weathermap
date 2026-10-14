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
chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . "/setup.php";
require dirname(__DIR__, 2) . "/lib/WeatherMap.class.php";
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
/**
 * Supply the real loaded snapshot for valid image mutation fixtures.
 *
 * @param string $file Configuration path.
 * @return void
 */
function image_format_revision($file) {
	$map = new WeatherMap; $map->ReadConfig($file);
	$GLOBALS['__test_nfilter_request']['config_revision'] = \WeathermapConfig\snapshotRevision($map->config_revision, $map->config_dependencies, $map->config_root_identity);
}
/**
 * Inject creation of an optional include between reference scans.
 *
 * @param array|null $directories Parent directories discovered by the real scanner.
 *
 * @return array Reference index preceding the injected writer.
 */
function image_format_scan(&$directories = null) {
	$result = wm_asset_image_reference_index($directories);
	if (++$GLOBALS['image_format_scans'] === 1) {
		image_format_assert(\WeathermapConfig\atomicWrite($GLOBALS['image_format_optional'], "BACKGROUND " . $GLOBALS['image_format_reference'] . "\n", false, null, true), 'cooperating writer creates missing include');
	}
	return $result;
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
	$uploadSource = $temporary . '/publication.png'; $bitmap = imagecreatetruecolor(12, 8); imagepng($bitmap, $uploadSource); imagedestroy($bitmap);
	$storeSource = file_get_contents($root . '/lib/editor.assets.php'); $start = strpos($storeSource, 'function wm_asset_store_image(');
	$instrumented = substr($storeSource, $start);
	$instrumented = str_replace(['function wm_asset_store_image(', "'imagepng'"], ['function publication_store_image(', "'publication_encode'"], $instrumented);
	eval($instrumented);
	function publication_encode($image, $handle) {
		$visible = array_filter(wm_asset_image_list(), fn ($item) => str_starts_with($item['name'], 'publication-'));
		image_format_assert($visible === [], 'encoding image is not selectable before publication');
		if ($GLOBALS['publication_fail']) { fwrite($handle, 'partial bytes'); return false; }
		return imagepng($image, $handle);
	}
	$GLOBALS['publication_fail'] = true;
	try { publication_store_image('objects', $uploadSource, 'publication.png'); throw new RuntimeException('accepted failed encoding'); } catch (RuntimeException $expected) { image_format_assert(str_contains($expected->getMessage(), 'Could not save'), 'encoding failure reported'); }
	image_format_assert(glob($root . '/images/objects/publication-*.png') === [] && glob($root . '/images/objects/.wm-upload-*') === [], 'failed encoding leaves no library file or staging file');
	$GLOBALS['publication_fail'] = false;
	$published = publication_store_image('objects', $uploadSource, 'publication.png'); $created[] = wm_asset_image_path('objects', $published);
	image_format_assert(getimagesize(end($created))[0] === 12 && (fileperms(end($created)) & 0777) === 0644 && glob($root . '/images/objects/.wm-upload-*') === [], 'successful upload publishes complete permitted image and removes staging file');
	file_put_contents($config, "NODE Fixture\n ICON images/objects/core-router.png\n# router.png is merely a comment\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'substring and comment do not count as references');
	file_put_contents($config, "NODE Fixture\n ICON images/objects/ROUTER.PNG\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') !== [], 'case-insensitive paths protect in-use images');
	image_format_assert(wm_asset_normalize_path('images\\objects\\Router.PNG', 'C:\\Cacti\\plugins\\weathermap') === 'C:/Cacti/plugins/weathermap/images/objects/Router.PNG', 'Windows relative path canonicalised');
	image_format_assert(wm_asset_normalize_path('D:\\assets\\router.png', 'C:/Cacti') === 'D:/assets/router.png', 'Windows absolute drive path retained');
	image_format_assert(wm_asset_normalize_path('\\\\server\\share\\router.png', 'C:/Cacti') === '//server/share/router.png', 'UNC absolute path retained');
	$jpegPath = $root . '/images/backgrounds/format-reload-' . bin2hex(random_bytes(4)) . '.jpeg';
	copy($created[3], $jpegPath); $created[] = $jpegPath;
	image_format_assert(in_array('images/backgrounds/' . basename($jpegPath), get_imagelist('backgrounds'), true), 'existing JPEG background survives selector reload');
	file_put_contents($config, "NODE Fixture\n ICON /outside/router.png\n");
	image_format_assert(wm_asset_image_users('router.png', 'objects') === [], 'complete paths distinguish external images');
	file_put_contents($config, "NODE Fixture\n ICON 32 32 images/objects/updown_{node:this:bandwidth_in}.png\n");
	image_format_assert(in_array(basename($config), wm_asset_image_users('updown_0.png', 'objects'), true), 'templated icon cannot be deleted');
	image_format_assert(wm_asset_image_users('updown_0.png', 'backgrounds') === [], 'library kinds are distinct');
	file_put_contents($config, "NODE Fixture\n ICON images/objects/" . basename($created[0]) . "\n");
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
	$oldSuppress = $GLOBALS['weathermap_error_suppress'] ?? [];
	$GLOBALS['weathermap_error_suppress'] = ['']; // The deliberately broken INCLUDE emits an untagged warning.
	ob_start(); image_format_revision($config); wm_editor_assets($config, 'upload_image'); $response = json_decode(ob_get_clean(), true);
	$GLOBALS['weathermap_error_suppress'] = $oldSuppress;
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
	ob_start(); image_format_revision($config); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['error']) && is_file($deletePath), 'GET cannot delete an image');
	$_SERVER['REQUEST_METHOD'] = 'POST';
	file_put_contents($config, "NODE Fixture\n ICON images/objects/" . $deleteName . "\n");
	ob_start(); image_format_revision($config); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['error']) && is_file($deletePath), 'referenced image cannot be deleted');
	file_put_contents($config, "# no image reference\n");

	$externalDir = $temporary . '/reference-source'; mkdir($externalDir); $externalFile = $externalDir . '/map.conf'; file_put_contents($externalFile, "# External source\n");
	$externalAlias = $root . '/configs/alias-' . bin2hex(random_bytes(4)) . '.conf'; symlink($externalFile, $externalAlias); $created[] = $externalAlias;
	$externalLock = \WeathermapConfig\acquire($externalDir);
	ob_start(); image_format_revision($config); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(http_response_code() === 500 && isset($response['error']) && is_file($deletePath), 'image deletion cannot bypass a physical configuration mutex');
	\WeathermapConfig\release($externalLock);
	file_put_contents($externalFile, "ICON images/objects/" . $deleteName . "\n");
	ob_start(); image_format_revision($config); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['error']) && is_file($deletePath), 'external symlink references remain protected after acquiring shared locks');
	unlink($externalAlias); unlink($externalFile); unlink($externalDir . '/.weathermap-config.lock'); rmdir($externalDir);

	ob_start(); image_format_revision($config); wm_editor_assets($config, 'delete_image'); $response = json_decode(ob_get_clean(), true);
	image_format_assert(isset($response['images']) && !is_file($deletePath), 'delete removes the actual server file');
	// Content detection, not a filename suffix, decides what selectors can render.
	foreach (['objects', 'backgrounds'] as $kind) {
		foreach (['broken'=>'not an image', 'unsupported'=>"BM" . pack('VvvV', 58, 0, 0, 54) . pack('VllvvVVllVV', 40, 1, 1, 1, 24, 0, 4, 0, 0, 0, 0) . "\0\0\0\0"] as $name => $bytes) {
			$fake = $root . '/images/' . $kind . '/test-' . $name . '-' . bin2hex(random_bytes(4)) . '.png';
			file_put_contents($fake, $bytes); $created[] = $fake;
			if ($name === 'unsupported') image_format_assert(getimagesize($fake)[2] === IMAGETYPE_BMP, 'fixture really detects an unsupported raster');
			image_format_assert(!array_filter(wm_asset_image_list(), fn ($item) => $item['kind'] === $kind && $item['name'] === basename($fake)), 'unsupported or invalid image omitted from library');
			image_format_assert(!in_array('images/' . $kind . '/' . basename($fake), get_imagelist($kind), true), 'initial selector also excludes unsupported or invalid images');
		}
	}
	// Stale, missing and nonexistent map tokens fail before upload validation or deletion.
	$protected = $created[1];
	$protectedBytes = file_get_contents($protected);
	foreach (['upload_image', 'delete_image'] as $action) {
		foreach (['missing', 'stale', 'absent-map'] as $failure) {
			$GLOBALS['__test_nfilter_request'] = ['image_kind'=>'backgrounds','image_name'=>basename($protected)];
			if ($failure !== 'missing') $GLOBALS['__test_nfilter_request']['config_revision'] = str_repeat('0',64);
			http_response_code(200); ob_start(); wm_editor_assets($failure === 'absent-map' ? $config . '.missing' : $config, $action); $blocked = json_decode(ob_get_clean(), true);
			image_format_assert(http_response_code() === ($failure === 'absent-map' ? 404 : 409) && isset($blocked['error']), 'image mutation rejects ' . $failure);
			image_format_assert(file_get_contents($protected) === $protectedBytes, 'rejected mutation preserves library file');
		}
	}
	// Existing raster symlinks remain selectable but never manageable.
	$backgroundAlias = $root . '/images/backgrounds/alias-' . bin2hex(random_bytes(4)) . '.png';
	symlink($protected, $backgroundAlias); $created[] = $backgroundAlias;
	image_format_assert(in_array('images/backgrounds/' . basename($backgroundAlias), get_imagelist('backgrounds'), true), 'readable raster symlink remains in initial background selector');
	image_format_assert(!array_filter(wm_asset_image_list(), fn ($item) => $item['name'] === basename($backgroundAlias)), 'symlink stays outside managed library operations');
	try { wm_asset_image_path('backgrounds', basename($backgroundAlias)); throw new RuntimeException('managed symlink accepted'); } catch (InvalidArgumentException $expected) {}
	// Missing include destinations contribute parent locks before the final rescan.
	$optionalDir = $temporary . '/optional'; mkdir($optionalDir); $optional = $optionalDir . '/optional.inc';
	file_put_contents($config, "INCLUDE $optional\n");
	wm_asset_image_reference_index($directories);
	image_format_assert(isset($directories[$optionalDir]), 'missing literal INCLUDE parent indexed');
	$parentLock = \WeathermapConfig\acquire($optionalDir); $locks = [];
	try { wm_asset_locked_reference_index($root . '/configs', $locks); throw new RuntimeException('missing include mutex bypassed'); } catch (RuntimeException $expected) {
		image_format_assert(str_contains($expected->getMessage(), 'Could not lock'), 'held missing include destination lock blocks deletion');
	} finally { foreach ($locks as $held) \WeathermapConfig\release($held); \WeathermapConfig\release($parentLock); }
	// Inject a cooperating writer after the first index: the subsequent locked scan must see it.
	$assetSource = file_get_contents($root . '/lib/editor.assets.php');
	$begin = strpos($assetSource, 'function wm_asset_locked_reference_index(');
	$end = strpos($assetSource, "\n/**", $begin);
	$instrumentedLockIndex = substr($assetSource, $begin, $end - $begin);
	eval(str_replace(['wm_asset_locked_reference_index(', 'wm_asset_image_reference_index('], ['image_format_locked_index(', 'image_format_scan('], $instrumentedLockIndex));
	$GLOBALS['image_format_optional'] = $optional; $GLOBALS['image_format_reference'] = 'images/backgrounds/' . basename($protected); $GLOBALS['image_format_scans'] = 0;
	$locks = [];
	try {
		$lockedReferences = image_format_locked_index($root . '/configs', $locks);
		image_format_assert($GLOBALS['image_format_scans'] >= 2 && wm_asset_image_users(basename($protected), 'backgrounds', $lockedReferences), 'new include references are detected by locked rescan');
		image_format_assert(\WeathermapConfig\acquire($optionalDir, false) === false, 'deletion retains missing include parent mutex through unlink decision');
	} finally { foreach ($locks as $held) \WeathermapConfig\release($held); }
	file_put_contents($config, "INCLUDE " . $optionalDir . "/absent-parent/file.inc\n"); $locks = [];
	try { wm_asset_locked_reference_index($root . '/configs', $locks); throw new RuntimeException('unstable include parent accepted'); } catch (RuntimeException $expected) {
		image_format_assert(str_contains($expected->getMessage(), 'Could not lock'), 'unresolvable include destination fails safely');
	} finally { foreach ($locks as $held) \WeathermapConfig\release($held); }
	// Filesystem symlink/.. semantics must match the renderer, not lexical path collapse.
	$target = $temporary . '/target'; mkdir($target); mkdir($target . '/sub');
	$linked = $temporary . '/linked'; symlink($target . '/sub', $linked);
	file_put_contents($target . '/include.conf', "BACKGROUND images/backgrounds/" . basename($protected) . "\n");
	file_put_contents($config, "INCLUDE $linked/../include.conf\n");
	image_format_assert(wm_asset_image_users(basename($protected), 'backgrounds') !== [], 'symlink-parent INCLUDE references protected');
	unlink($linked); unlink($target . '/include.conf'); rmdir($target . '/sub'); rmdir($target);
	unlink($optional); unlink($optionalDir . '/.weathermap-config.lock'); rmdir($optionalDir);
	// Both collectors canonicalise alternate renderer spellings, including missing paths.
	file_put_contents($config, "WIDTH 100\nHEIGHT 100\nNODE A\n POSITION 20 20\n");
	$missingName = 'missing-' . bin2hex(random_bytes(4)) . '.png';
	foreach (['./images/objects/' . $missingName, 'images/./objects/' . $missingName, $root . '/images/objects/' . $missingName] as $spelling) {
		$assetMap = new WeatherMap; $assetMap->ReadConfig($config); $assetMap->nodes['A']->iconfile = $spelling;
		image_format_assert($assetMap->ManagedImagePaths() === ['images/objects/' . $missingName], 'object collector normalizes missing path');
		image_format_assert(WeatherMap::ManagedImagePathsFromConfig("ICON 32 32 $spelling\n") === ['images/objects/' . $missingName], 'raw collector normalizes missing path');
		$before = file_get_contents($config);
		image_format_assert(!$assetMap->WriteConfig($config) && $assetMap->config_asset_conflict && file_get_contents($config) === $before, 'alternate missing-image spelling cannot commit');
		$assetMap->nodes['A']->iconfile = '';
		$assetMap->nodes['A']->config_override = "NODE A\n POSITION 20 20\n ICON $spelling\n";
		image_format_assert(!$assetMap->WriteConfig($config), 'raw override cannot bypass missing-image validation');
	}
	$missingInclude = $temporary . '/missing-asset.inc';
	file_put_contents($missingInclude, "BACKGROUND ./images/backgrounds/$missingName\n");
	file_put_contents($config, "INCLUDE $missingInclude\n");
	$copyMap = new WeatherMap; $copyMap->ReadConfig($config);
	image_format_assert(!$copyMap->WriteConfig($temporary . '/copy.conf', true) && !is_file($temporary . '/copy.conf'), 'copy rejects inherited missing image in normalized spelling');
	file_put_contents($config, "WIDTH 100\nHEIGHT 100\nNODE A\n POSITION 20 20\n");
	$baseline = new WeatherMap; $baseline->ReadConfig($config);
	$baseline->background = './images/backgrounds/' . basename($protected);
	image_format_assert($baseline->WriteConfig($config), 'existing alternate image spelling saves');
	$baseline = new WeatherMap; $baseline->ReadConfig($config);
	image_format_assert($baseline->config_managed_assets === ['images/backgrounds/' . basename($protected)], 'loaded image baseline uses same canonical identity');
	image_format_assert(wm_asset_managed_path('outside.png', $root) === null && wm_asset_managed_path(null, $root) === null, 'unmanaged image paths excluded');
	image_format_assert(str_contains(wm_asset_managed_path('images/objects/dynamic_{node:this:name}.png', $root), '{node:this:name}'), 'renderer tokens preserved in identity');
	echo "Editor image formats regression PASS\n";
} finally {
	foreach ($created as $path) if (is_file($path)) unlink($path);
	if (is_file($config)) unlink($config);
	$cleanup = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($cleanup as $entry) {
		if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
	}
	rmdir($temporary);
}
