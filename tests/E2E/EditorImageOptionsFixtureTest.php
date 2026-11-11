<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Server half of the editor image-option contract. The editor page renders its
 * icon and background selectors with wm_editor_image_options() and
 * wm_editor_image_option(); this test drives both against a real images/
 * tree and pins the markup in tests/Browser/fixtures/editor-image-options.html.
 * tests/Browser/EditorImageOptionsTest.cjs loads that same file into jsdom with
 * the real wmEditorImageSrc(). The PHP container has no Node.js, so the file is
 * the hand-off; set WM_UPDATE_FIXTURES=1 to rewrite it after a deliberate change.
 */

beforeAll(function (): void {
	require_once __DIR__ . '/../../lib/editor.assets.php';
	require_once __DIR__ . '/../../lib/editor.inc.php';
	require_once __DIR__ . '/../Helpers/ImageLibraryFixture.php';
});

it('renders the editor image selectors that the browser fixture pins', function (): void {
	$root = wm_fixture_plugin_root();

	try {
		foreach (['router.png', 'core router.png', '100%.png', 'site#2.png', 'café.png'] as $name) {
			wm_fixture_raster($root . '/images/objects/' . $name);
		}
		file_put_contents($root . '/images/objects/broken.png', 'not an image');
		wm_fixture_raster($root . '/images/objects/vector.svg');
		wm_fixture_raster($root . '/images/backgrounds/world map.jpg', 'jpeg');
		wm_fixture_raster($root . '/images/backgrounds/plain.gif', 'gif');

		$used = ['images/objects/router.png', 'images/objects/custom folder/site.png', '/srv/icons/outside.png', 'images/objects/%2e%2e/%2e%2e/setup.png', 'images/../setup.png'];

		$render = function ($entries, $selected = null) {
			return implode('', array_map(fn ($entry) => wm_editor_image_option($entry, $selected), $entries));
		};
		$markup = '<select id="node_iconfilename" aria-label="Icon">' . $render(wm_editor_image_options('objects', $used, $root)) . "</select>\n"
			. '<select id="map_bgfile" aria-label="Background">' . $render(wm_editor_image_options('backgrounds', [], $root), 'images/backgrounds/world map.jpg') . "</select>\n";
	} finally {
		wm_fixture_remove($root);
	}

	$fixture = dirname(__DIR__) . '/Browser/fixtures/editor-image-options.html';

	if (getenv('WM_UPDATE_FIXTURES') === '1') {
		file_put_contents($fixture, $markup);
	}

	expect(file_get_contents($fixture))->toBe($markup);
	expect($markup)->not->toContain('broken.png');
	expect($markup)->not->toContain('vector.svg');
});
