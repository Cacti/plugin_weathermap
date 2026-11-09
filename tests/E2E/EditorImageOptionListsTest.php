<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                  |
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
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// Render the editor's icon and background lists from real image files
// through the editor page's own code, then parse the markup. The eval()
// calls run only sections of this repository's editor page.

beforeAll(function () {
	require_once __DIR__ . '/../../lib/editor.inc.php';
});

/**
 * Return the PHP code of one section of the editor page.
 *
 * @param string $start Text that begins the section.
 * @param string $end   Text that ends the section.
 * @param bool   $block True to return the first PHP block after $start.
 *
 * @return string The code, ready for eval().
 */
function wm_option_lists_code($start, $end, $block = false) {
	$source = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin-editor.php');
	$from   = strpos($source, $start);
	expect($from)->not->toBeFalse();

	if ($block) {
		$from = strpos($source, '<?php', $from) + strlen('<?php');
		$end  = '?>';
	}
	$to = strpos($source, $end, $from);
	expect($to)->not->toBeFalse();

	return substr($source, $from, $block ? $to - $from : $to - $from + strlen($end));
}

/**
 * Build the icon and background lists the way the editor page does.
 *
 * @param array<int, string> $objects     Object image file names.
 * @param array<int, string> $backgrounds Background image file names.
 * @param array<int, string> $used        Image paths the map already uses.
 *
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function wm_option_lists_build(array $objects, array $backgrounds, array $used) {
	global $config;

	$png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
	$base = sys_get_temp_dir() . '/wm-options-' . bin2hex(random_bytes(8));

	foreach (['objects' => $objects, 'backgrounds' => $backgrounds] as $dir => $files) {
		mkdir($base . '/plugins/weathermap/images/' . $dir, 0700, true);

		foreach ($files as $file) {
			file_put_contents($base . '/plugins/weathermap/images/' . $dir . '/' . $file, $png);
		}
	}

	$saved               = $config['base_path'] ?? null;
	$config['base_path'] = $base;
	$map                 = (object) ['used_images' => $used];

	try {
		eval(wm_option_lists_code("\$image_list   = get_imagelist('objects');", 'sort($image_list);'));
	} finally {
		$config['base_path'] = $saved;
		$iterator            = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($iterator as $entry) {
			$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}
		rmdir($base);
	}

	return [$image_list, $backgd_list];
}

/**
 * Run one of the editor page's option list blocks and parse its output.
 *
 * @param string               $select    The select element that holds the list.
 * @param array<string, mixed> $variables Variables the block reads.
 *
 * @return array<int, array{attributes: array<string, string>, text: string}>
 */
function wm_option_lists_render($select, array $variables) {
	extract($variables);
	ob_start();
	eval(wm_option_lists_code("<select id='$select' name='$select'>", '', true));
	$html = ob_get_clean();

	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><select>' . $html . '</select></body></html>');
	libxml_clear_errors();

	// Only the wrapper elements and the options may exist.
	$tags = [];

	foreach ($doc->getElementsByTagName('*') as $element) {
		$tags[] = $element->nodeName;
	}
	expect(array_values(array_diff($tags, ['html', 'head', 'meta', 'body', 'select', 'option'])))->toBe([]);

	$select  = $doc->getElementsByTagName('select')->item(0);
	$options = [];

	foreach ($select->childNodes as $node) {
		expect($node->nodeName)->toBe('option');
		$attributes = [];

		foreach ($node->attributes as $attribute) {
			$attributes[$attribute->name] = $attribute->value;
		}
		$options[] = ['attributes' => $attributes, 'text' => $node->textContent];
	}

	return $options;
}

/**
 * Return the label the editor shows for an image path.
 *
 * @param string $path Image path.
 *
 * @return string The display label.
 */
function wm_option_lists_label($path) {
	return ucfirst(str_replace(['.png', '.gif', '.jpg'], '', basename($path)));
}

dataset('hostile image names', [[[
	'a"b\'c<d>&e.png',
	'x" onmouseover="alert(1)".png',
	'"><img src=x onerror=alert(1)>.png',
	'A&amp;B &lt;i&gt;.png',
	'Ünïcødé 東京 🚀.png',
	'`tick` \' selected=\'1.png',
]]]);

it('RendersHostileIconFilenamesAsInertOptions', function (array $files) {
	$used = ['images/objects/used" data-x="1.png', 'images/objects/</option><script>alert(1)</script>.png'];
	[$image_list] = wm_option_lists_build($files, [], $used);
	expect($image_list)->toHaveCount(count($files) + count($used));

	$options = wm_option_lists_render('node_iconfilename', ['image_list' => $image_list]);
	expect($options)->toHaveCount(count($image_list) + 2)
		->and($options[0])->toBe(['attributes' => ['data-description' => 'Label Only Icon', 'data-imagesrc' => '', 'value' => '--NONE--'], 'text' => 'Label Only'])
		->and($options[1])->toBe(['attributes' => ['data-description' => 'Special Purpose Icon', 'data-imagesrc' => '', 'value' => '--AICON--'], 'text' => 'Special Icon (AICON)']);

	foreach ($image_list as $index => $im) {
		$label = wm_option_lists_label($im);
		expect($options[$index + 2])->toBe(['attributes' => ['data-description' => $label, 'data-imagesrc' => $im, 'value' => $im], 'text' => $label]);
	}

	foreach ($files as $file) {
		expect($image_list)->toContain('images/objects/' . $file);
	}
})->with('hostile image names');

it('RendersHostileBackgroundFilenamesAndSelectsOnlyTheCurrentOne', function (array $files) {
	[, $backgd_list] = wm_option_lists_build([], $files, []);
	expect($backgd_list)->toHaveCount(count($files));
	$current = 'images/backgrounds/' . $files[1];
	$map     = (object) ['background' => $current];

	$options = wm_option_lists_render('map_bgfile', ['backgd_list' => $backgd_list, 'map' => $map]);
	expect($options)->toHaveCount(count($backgd_list) + 1)
		->and($options[0])->toBe(['attributes' => ['data-description' => 'Use Map Background Colour', 'data-imagesrc' => '', 'value' => '--NONE--'], 'text' => '--NO BACKGROUND--']);

	foreach ($backgd_list as $index => $im) {
		$label    = wm_option_lists_label($im);
		$expected = ['data-description' => $label, 'data-imagesrc' => $im, 'value' => $im];

		if ($im === $current) {
			$expected = ['selected' => 'selected'] + $expected;
		}
		expect($options[$index + 1])->toBe(['attributes' => $expected, 'text' => $label]);
	}

	expect(array_filter($options, fn ($option) => isset($option['attributes']['selected'])))->toHaveCount(1);
})->with('hostile image names');

it('RendersThePlaceholderOptionsWhenNoImagesExist', function () {
	[$image_list, $backgd_list] = wm_option_lists_build([], [], []);

	expect(wm_option_lists_render('node_iconfilename', ['image_list' => $image_list]))->toBe([
		['attributes' => ['data-value' => '--NONE--'], 'text' => 'Label Only'],
		['attributes' => ['data-value' => '--AICON--'], 'text' => 'Special Icon (AICON)'],
	])->and(wm_option_lists_render('map_bgfile', ['backgd_list' => $backgd_list, 'map' => (object) ['background' => '']]))->toBe([
		['attributes' => ['data-value' => '--NONE--'], 'text' => '(no images are available)'],
	]);
});
