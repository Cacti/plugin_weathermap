<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_request'] = [];
	unset($GLOBALS['__test_db_fetch_row_prepared'], $GLOBALS['__test_db_fetch_cell_prepared']);
	unset($_SERVER['SCRIPT_NAME'], $_SESSION['sess_config_settings_tab']);
});

afterEach(function () {
	$GLOBALS['__test_request'] = [];
	unset($GLOBALS['__test_db_fetch_row_prepared'], $GLOBALS['__test_db_fetch_cell_prepared']);
	unset($_SERVER['SCRIPT_NAME'], $_SESSION['sess_config_settings_tab']);
});

it('DecoratesLocalGraphLinksWithoutChangingGraphSelectionOrFragments', function (string $query) {
	$html   = '<area href="/cacti/graph.php?' . htmlspecialchars($query, ENT_QUOTES) . '#range">';
	$output = weathermap_map_graph_links($html, 'new-map');
	preg_match('/href="([^"]+)"/', $output, $matches);
	$url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	parse_str(parse_url($url, PHP_URL_QUERY), $params);
	expect($params['wm_map'])->toBe('new-map')
		->and($params['local_graph_id'])->toBe('7')
		->and($params['graph_list'])->toBe('7,9')
		->and($params['rra_id'])->toBe('0')
		->and(parse_url($url, PHP_URL_FRAGMENT))->toBe('range')
		->and(substr_count($url, 'wm_map='))->toBe(1)
		->and(weathermap_map_graph_links($output, 'new-map'))->toBe($output);
})->with([
	'local_graph_id=7&graph_list=7,9&rra_id=0',
	'wm_map=old&local_graph_id=7&graph_list=7,9&rra_id=0&wm_map=older',
	'%77m_map=old&local_graph_id=7&graph_list=7,9&rra_id=0',
]);

it('LeavesExternalAndNonGraphLinksUnchanged', function (string $url) {
	$html = "<area href='" . $url . "'>";
	expect(weathermap_map_graph_links($html, 'new-map'))->toBe($html);
})->with([
	'https://example.test/cacti/graph.php?local_graph_id=7',
	'//example.test/cacti/graph.php?local_graph_id=7',
	'/cacti/graphs.php?graph_list=7',
	'/other/graph.php?local_graph_id=7',
	'#graph.php',
]);

it('ChecksMapPermissionBeforeAddingViewerOrGraphBreadcrumbs', function (string $page, bool $allowed, bool $exists) {
	$hash                                    = str_repeat('a', 32);
	$_SERVER['SCRIPT_NAME']                  = '/cacti/' . $page;
	$_SESSION['sess_user_id']                = 23;
	$GLOBALS['__test_request']               = ['action' => 'viewmap', 'id' => $hash, 'wm_map' => $hash];
	$GLOBALS['__test_db_fetch_row_prepared'] = function ($sql, $params) use ($hash, $exists) {
		expect($sql)->toContain("active = 'on'")
			->and($params)->toBe([$hash]);

		return $exists ? ['id' => 5, 'filehash' => $hash, 'titlecache' => 'Permitted Map', 'configfile' => 'map.conf'] : false;
	};
	$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql, $params) use ($allowed) {
		expect($sql)->toContain('weathermap_auth')
			->and($params[0])->toBe(5);

		return $allowed && str_contains($sql, 'AND userid = ?') && $params[1] === 23 ? 5 : '';
	};
	$original  = ['graph.php:view' => ['title' => 'Graph', 'mapping' => 'graphs.php:', 'url' => 'graph.php', 'level' => '1']];
	$nav       = weathermap_draw_navigation_text($original);
	$permitted = $allowed && $exists;

	if ($page === 'graph.php') {
		expect(isset($nav['wm-return-map:']))->toBe($permitted);

		if ($permitted) {
			expect($nav['wm-return-map:']['title'])->toBe('Permitted Map')
				->and($nav['graph.php:view']['mapping'])->toBe('wm-return-list:,wm-return-map:');
		} else {
			expect($nav['graph.php:view'])->toBe($original['graph.php:view']);
		}
	} else {
		expect($nav['weathermap-cacti-plugin.php:viewmap']['title'])->toBe($permitted ? 'Permitted Map' : 'Weathermap');
	}
})->with([
	['graph.php', true, true], ['graph.php', false, true], ['graph.php', true, false],
	['weathermap-cacti-plugin.php', true, true], ['weathermap-cacti-plugin.php', false, true], ['weathermap-cacti-plugin.php', true, false],
]);

it('IgnoresInvalidMapContextWithoutQueryingTheDatabase', function (string $page, mixed $hash) {
	$_SERVER['SCRIPT_NAME']                  = '/cacti/' . $page;
	$GLOBALS['__test_request']               = ['action' => 'viewmap', 'id' => $hash, 'wm_map' => $hash];
	$GLOBALS['__test_db_fetch_row_prepared'] = function () {
		throw new RuntimeException('Invalid map context must not query the database.');
	};
	$nav = weathermap_draw_navigation_text([]);
	expect($nav)->not->toHaveKey('wm-return-map:')
		->and($nav['weathermap-cacti-plugin.php:viewmap']['title'])->toBe('Weathermap');
})->with([['graph.php', 'invalid'], ['graph.php', ['a']], ['weathermap-cacti-plugin.php', 'invalid']]);

it('UsesNormalizedSettingsTabAndSessionFallback', function () {
	$_SERVER['SCRIPT_NAME']               = '/cacti/settings.php';
	$_SESSION['sess_config_settings_tab'] = 'wmap';
	expect(weathermap_draw_navigation_text([])['settings.php:']['title'])->toBe('Settings');
	$GLOBALS['__test_request']['tab'] = 'other';
	expect(weathermap_draw_navigation_text([]))->not->toHaveKey('settings.php:');
	$GLOBALS['__test_request']['tab'] = 'wmap';
	expect(weathermap_draw_navigation_text([])['settings.php:']['title'])->toBe('Settings');
});

it('EscapesAttributeQuotesAndEncodedEntitiesWithoutChangingTheirValue', function () {
	$value   = "Name 'quoted' &quot; <map> `";
	$escaped = plugin_weathermap_escape_attr($value);
	expect($escaped)->not->toContain("'")
		->not->toContain('<map>')
		->toContain('&amp;quot;')
		->and(html_entity_decode($escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($value);
});
