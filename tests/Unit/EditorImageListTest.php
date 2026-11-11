<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

beforeAll(function (): void {
	require_once __DIR__ . '/../../lib/editor.assets.php';
	require_once __DIR__ . '/../../lib/editor.inc.php';
	require_once __DIR__ . '/../Helpers/ImageLibraryFixture.php';
});

beforeEach(function (): void {
	$this->root = wm_fixture_plugin_root();
	$this->objects = $this->root . '/images/objects';
});

afterEach(function (): void {
	wm_fixture_remove($this->root);
});

it('returns the kind, name, url and path shape for each raster format', function (): void {
	wm_fixture_raster($this->objects . '/router.png');
	wm_fixture_raster($this->objects . '/switch.gif', 'gif');
	wm_fixture_raster($this->objects . '/server.jpg', 'jpeg');
	wm_fixture_raster($this->objects . '/Firewall.JPEG', 'jpeg');

	$images = wm_asset_library_images('objects', $this->root);

	expect(array_column($images, 'name'))->toBe(['Firewall.JPEG', 'router.png', 'server.jpg', 'switch.gif']);
	expect($images[1])->toBe(['kind' => 'objects', 'name' => 'router.png', 'url' => 'images/objects/router.png', 'path' => 'images/objects/router.png', 'width' => 4, 'height' => 3, 'managed' => true]);
});

it('keeps the raw path and encodes the url for unusual filenames', function (): void {
	$names = ['core router.png' => 'core%20router.png', '100%.png' => '100%25.png', 'site#2.png' => 'site%232.png', 'café-ünï.png' => 'caf%C3%A9-%C3%BCn%C3%AF.png', 'a?b.png' => 'a%3Fb.png'];
	foreach (array_keys($names) as $name) wm_fixture_raster($this->objects . '/' . $name);

	$byName = array_column(wm_asset_library_images('objects', $this->root), null, 'name');

	expect(array_keys($byName))->toEqualCanonicalizing(array_keys($names));
	foreach ($names as $name => $encoded) {
		expect($byName[$name]['path'])->toBe('images/objects/' . $name);
		expect($byName[$name]['url'])->toBe('images/objects/' . $encoded);
	}
});

it('rejects files whose extension is not a supported raster', function (): void {
	foreach (['icon.svg', 'icon.php', 'icon.png.txt', 'icon.bmp', "icon.png\n", 'icon'] as $name) wm_fixture_raster($this->objects . '/' . $name);

	expect(wm_asset_library_images('objects', $this->root))->toBe([]);
});

it('rejects files whose content is not a PNG, JPEG or GIF raster', function (): void {
	file_put_contents($this->objects . '/text.png', 'not an image');
	file_put_contents($this->objects . '/truncated.gif', 'GIF8');
	file_put_contents($this->objects . '/bitmap.png', 'BM' . pack('VvvV', 58, 0, 0, 54) . pack('VllvvVVllVV', 40, 1, 1, 1, 24, 0, 4, 0, 0, 0, 0) . "\0\0\0\0");
	file_put_contents($this->objects . '/vector.png', '<svg xmlns="http://www.w3.org/2000/svg"/>');

	expect(getimagesize($this->objects . '/bitmap.png')[2])->toBe(IMAGETYPE_BMP);
	expect(wm_asset_library_images('objects', $this->root))->toBe([]);
});

it('rejects directories and unreadable files', function (): void {
	mkdir($this->objects . '/folder.png');
	wm_fixture_raster($this->objects . '/locked.png');
	chmod($this->objects . '/locked.png', 0);

	if (is_readable($this->objects . '/locked.png')) {
		$this->markTestSkipped('running with privileges that bypass file permissions');
	}

	expect(wm_asset_library_images('objects', $this->root))->toBe([]);
});

it('lists symlinked rasters as selectable but not managed', function (): void {
	wm_fixture_raster($this->root . '/outside.png');
	symlink($this->root . '/outside.png', $this->objects . '/alias.png');
	symlink($this->root . '/missing.png', $this->objects . '/dangling.png');

	$images = wm_asset_library_images('objects', $this->root);

	expect(cacti_count($images))->toBe(1);
	expect($images[0]['name'])->toBe('alias.png');
	expect($images[0]['managed'])->toBeFalse();
});

it('marks every entry unmanaged when the library directory is a symlink', function (): void {
	$shared = $this->root . '/shared';
	mkdir($shared);
	wm_fixture_raster($shared . '/linked.png');
	rmdir($this->objects);
	symlink($shared, $this->objects);

	$images = wm_asset_library_images('objects', $this->root);

	expect(array_column($images, 'name'))->toBe(['linked.png']);
	expect($images[0]['managed'])->toBeFalse();
});

it('returns an empty list for empty and missing libraries', function (): void {
	expect(wm_asset_library_images('objects', $this->root))->toBe([]);
	rmdir($this->root . '/images/backgrounds');
	expect(wm_asset_library_images('backgrounds', $this->root))->toBe([]);
	expect(wm_asset_library_images('objects', $this->root . '/absent'))->toBe([]);
});

it('rejects unknown library kinds', function (): void {
	wm_asset_library_images('../configs', $this->root);
})->throws(InvalidArgumentException::class);

it('encodes only relative images paths as preview urls', function (): void {
	expect(wm_asset_image_url('images/objects/custom folder/100%.png'))->toBe('images/objects/custom%20folder/100%25.png');
	expect(wm_asset_image_url('images/objects/%2e%2e/setup.png'))->toBe('images/objects/%252e%252e/setup.png');
	expect(wm_asset_image_url('/var/www/images/objects/a.png'))->toBe('');
	expect(wm_asset_image_url('other/a.png'))->toBe('');
	expect(wm_asset_image_url(null))->toBe('');
});

it('lists the configured plugin library through get_imagelist', function (): void {
	$base = $this->root . '/cacti';
	mkdir($base . '/plugins/weathermap/images/backgrounds', 0777, true);
	wm_fixture_raster($base . '/plugins/weathermap/images/backgrounds/b map.jpg', 'jpeg');
	wm_fixture_raster($base . '/plugins/weathermap/images/backgrounds/a.png');
	$saved = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		expect(get_imagelist('backgrounds'))->toBe(['images/backgrounds/a.png', 'images/backgrounds/b map.jpg']);
		expect(array_column(wm_editor_image_options('backgrounds'), 'path'))->toBe(['images/backgrounds/a.png', 'images/backgrounds/b map.jpg']);
	} finally {
		$GLOBALS['config']['base_path'] = $saved;
	}
});

it('merges used images into the sorted selector list once', function (): void {
	wm_fixture_raster($this->objects . '/router.png');
	wm_fixture_raster($this->objects . '/hub.png');
	$used = ['images/objects/router.png', 'images/objects/zz/site.png', 'images/objects/router.png', '/srv/icons/a.png', 'images/objects/zz/site.png', null];

	$entries = wm_editor_image_options('objects', $used, $this->root);

	expect($entries)->toBe([
		['kind' => 'objects', 'name' => 'a.png', 'url' => '', 'path' => '/srv/icons/a.png'],
		['kind' => 'objects', 'name' => 'hub.png', 'url' => 'images/objects/hub.png', 'path' => 'images/objects/hub.png'],
		['kind' => 'objects', 'name' => 'router.png', 'url' => 'images/objects/router.png', 'path' => 'images/objects/router.png'],
		['kind' => 'objects', 'name' => 'site.png', 'url' => 'images/objects/zz/site.png', 'path' => 'images/objects/zz/site.png'],
	]);
});

it('renders escaped options with the encoded url as preview source', function (): void {
	$quoted = ['kind' => 'backgrounds', 'name' => 'a "b".png', 'url' => 'images/backgrounds/a%20%22b%22.png', 'path' => 'images/backgrounds/a "b".png'];
	$plain  = ['kind' => 'backgrounds', 'name' => 'plain.jpg', 'url' => 'images/backgrounds/plain.jpg', 'path' => 'images/backgrounds/plain.jpg'];

	expect(wm_editor_image_option($quoted, 'images/backgrounds/plain.jpg'))->toBe('<option data-description="A &quot;b&quot;" data-imagesrc="images/backgrounds/a%20%22b%22.png" value="images/backgrounds/a &quot;b&quot;.png">A &quot;b&quot;</option>');
	expect(wm_editor_image_option($plain, 'images/backgrounds/plain.jpg'))->toBe('<option selected data-description="Plain" data-imagesrc="images/backgrounds/plain.jpg" value="images/backgrounds/plain.jpg">Plain</option>');
	expect(wm_editor_image_option($plain))->not->toContain('selected');
});
