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

it('RendersSupportedConfigFilenamesInTheEditorChooser', function () {
	$directory = sys_get_temp_dir() . '/wm-chooser-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$filename = "A +% 'quoted' & # ?.conf";
	$files    = [$filename, '.hidden.conf', 'invalid.txt'];
	$previous = [];

	foreach (['mapdir', 'config_loaded', 'configerror'] as $key) {
		$previous[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}

	try {
		foreach ($files as $file) {
			file_put_contents($directory . '/' . $file, "TITLE Fixture\n");
		}
		$GLOBALS['mapdir']        = $directory;
		$GLOBALS['config_loaded'] = true;
		$GLOBALS['configerror']   = '';
		ob_start();

		try {
			show_editor_startpage();
		} finally {
			$html = ob_get_clean();
		}
		$document = new DOMDocument;
		preg_match('/<select name="sourcemap">.*?<\/select>/s', $html, $select);
		preg_match('/<ul class="filelist">.*?<\/ul>/s', $html, $list);
		$document->loadHTML('<html><body>' . $select[0] . $list[0] . '</body></html>');
		$xpath   = new DOMXPath($document);
		$options = $xpath->query('//select[@name="sourcemap"]/option');
		expect($options->length)->toBe(1)
			->and($options->item(0)->getAttribute('value'))->toBe($filename);
		$links = $xpath->query('//ul[@class="filelist"]//a');
		expect($links->length)->toBe(1)
			->and($links->item(0)->textContent)->toBe($filename);
		parse_str(parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $params);
		expect($params['mapname'])->toBe($filename)
			->and($html)->toContain('filenames must end in .conf and contain no path separators or control characters');
	} finally {
		foreach ($files as $file) {
			unlink($directory . '/' . $file);
		}
		rmdir($directory);

		foreach ($previous as $key => [$existed, $value]) {
			if ($existed) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
	}
});
