<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
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
