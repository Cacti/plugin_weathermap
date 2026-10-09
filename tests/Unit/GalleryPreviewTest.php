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

it('UsesExistingGeneratedThumbnailsAndVersionsOnlyChangedPreviews', function (string $mode) {
	$lines = [];
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Support/GalleryPreviewRender.php') . ' ' . escapeshellarg($mode), $lines, $status);
	expect($status)->toBe(0);
	$renders = json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
	expect($renders[0])->toBe($renders[1]);
	$urls = [];

	foreach ($renders as $html) {
		preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $matches);
		expect($matches[1])->toHaveCount(2)
			->and(substr_count($html, '(thumbnail for map not created yet)'))->toBe(2)
			->and($html)->toContain('thumb-only &lt;map&gt;', 'full-only &lt;map&gt;', 'both &lt;map&gt;', 'missing &lt;map&gt;');
		preg_match_all('/<img[^>]+alt="([^"]*)" title="([^"]*)"/', $html, $attributes);
		expect($attributes[1])->toHaveCount(2);

		foreach (['thumb-only', 'both'] as $index => $kind) {
			$title = $kind . ' <map> "quoted" &quot; ` &';
			expect(html_entity_decode($attributes[1][$index], ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($title)
				->and(html_entity_decode($attributes[2][$index], ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($title);
		}
		$parameters = [];

		foreach ($matches[1] as $url) {
			parse_str(parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY), $query);
			expect($query['action'])->toBe('viewthumb')
				->and($query)->not->toHaveKey('time');
			$parameters[$query['id']] = $query['v'];
		}
		$urls[] = $parameters;
	}
	expect($urls[0])->toBe([str_repeat('1', 32) => '1700000000', str_repeat('3', 32) => '1700000000'])
		->and($urls[2])->toBe([str_repeat('1', 32) => '1700000100', str_repeat('3', 32) => '1700000000']);
})->with(['fallback', 'native']);

it('EscapesGalleryAttributesOnOlderCactiVersions', function () {
	require_once __DIR__ . '/../../setup.php';
	$value   = "Map 'quoted' \"double\" &quot; <tag> `";
	$escaped = plugin_weathermap_escape_attr($value);
	expect(html_entity_decode($escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($value)
		->and($escaped)->not->toContain("'", '"', '<tag>', '`')
		->and($escaped)->toContain('&amp;quot;');
});

// Keep delegation last: the fixture helper remains defined in this process.
it('DelegatesGalleryAttributeEscapingToCactiWhenAvailable', function () {
	if (!function_exists('html_escape_attr')) {
		/**
		 * Emulate Cacti's native helper for the in-process delegation check.
		 *
		 * @param string $value The unescaped attribute value.
		 *
		 * @return string The escaped HTML attribute value.
		 */
		function html_escape_attr($value) {
			$GLOBALS['gallery_attribute_delegations'] = ($GLOBALS['gallery_attribute_delegations'] ?? 0) + 1;

			return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
		}
	}
	$GLOBALS['gallery_attribute_delegations'] = 0;
	$value                                    = 'Map "quoted" <tag> &quot;';
	expect(plugin_weathermap_escape_attr($value))->toBe(htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true))
		->and($GLOBALS['gallery_attribute_delegations'])->toBe(1);
	unset($GLOBALS['gallery_attribute_delegations']);
});
