<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../lib/editor.inc.php';
	require_once __DIR__ . '/../../lib/editor.actions.php';
});

afterEach(function () {
	$GLOBALS['__test_request']         = [];
	$GLOBALS['__test_nfilter_request'] = [];
});

it('PreservesDecodedBasenamesThroughTheEditorRequestBoundary', function (string $filename) {
	parse_str('mapname=' . rawurlencode($filename), $request);
	$GLOBALS['__test_request']         = $request;
	$GLOBALS['__test_nfilter_request'] = [];
	$source                            = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin-editor.php');
	$start                             = strpos($source, "if (isset_request_var('mapname')) {");
	$end                               = strpos($source, "if (isset_request_var('selected'))", $start);
	$mapname                           = '';
	eval(substr($source, $start, $end - $start));
	expect($mapname)->toBe($filename);
	$directory = sys_get_temp_dir() . '/wm-config-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);

	try {
		file_put_contents($directory . '/' . $filename, 'TITLE Fixture');
		expect(file_get_contents($directory . '/' . $mapname))->toBe('TITLE Fixture');
		parse_str(parse_url(getImageURL($mapname, ''), PHP_URL_QUERY), $image);
		expect($image['mapname'])->toBe($filename);
	} finally {
		unlink($directory . '/' . $filename);
		rmdir($directory);
	}
})->with(['ordinary.conf', 'A +%.conf', "Name 'quoted' & # ? é.conf", '%2F.conf']);

it('RejectsInvalidConfigBasenamesWithoutRewritingThem', function (mixed $filename) {
	expect(wm_editor_sanitize_conffile($filename))->toBe('');
})->with(['../outside.conf', '/absolute.conf', 'sub/map.conf', '..\\outside.conf', "nul\0.conf", "line\n.conf", 'map.conf.php', '.conf', '', [['map.conf']]]);

it('PreservesValidatedReturnContextOnEditorReloadLinks', function (string $return, string $expected) {
	$GLOBALS['__test_request']         = ['return_to' => $return];
	$GLOBALS['__test_nfilter_request'] = [];
	$source                            = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin-editor.php');
	preg_match('/^\$editor_return_context = .*;$/m', $source, $context);
	eval($context[0]);
	$mapname = 'A +% & #.conf';
	preg_match_all("/<a href='\?action=(?:retidy_all|retidy|untidy|nothing)&mapname=.*?<\/a>/", $source, $links);
	expect($links[0])->toHaveCount(4);

	foreach ($links[0] as $link) {
		ob_start();
		eval('?>' . $link);
		$html = ob_get_clean();
		preg_match("/href='([^']+)'/", $html, $attribute);
		parse_str(parse_url(html_entity_decode($attribute[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY), $params);
		expect($params['mapname'])->toBe($mapname)
			->and($params['return_to'])->toBe($expected);
	}
})->with([['manage', 'manage'], ['map', 'map'], ['https://example.test/', 'map']]);
