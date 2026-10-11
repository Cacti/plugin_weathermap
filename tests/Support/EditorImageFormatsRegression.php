<?php
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
