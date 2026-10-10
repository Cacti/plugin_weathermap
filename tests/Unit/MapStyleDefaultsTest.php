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

beforeAll(function () {
	require_once dirname(__DIR__,2) . '/lib/WeatherMap.functions.php';
	require_once dirname(__DIR__,2) . '/lib/editor.comment-style.php';
});
/**
 * Build a map fixture with matching defaults and a distinct custom link.
 *
 * @return object
 */
function wm_style_test_map() {
	$font              = new WMFont();
	$font->type        = 'truetype';
	$font->file        = dirname(__DIR__,2) . '/docs/example/Vera.ttf';
	$font->size        = 9;
	$builtin           = new WMFont();
	$builtin->type     = 'GD builtin';
	$builtin->gdnumber = 2;
	$default           = (object)['bwfont'=> 100, 'commentfont'=> 100, 'commentfontcolour'=>['0', '0', '255']];

	return (object)['fonts'=>[2=>$builtin, 100=>$font], 'links'=>[':: DEFAULT ::'=>clone $default, 'DEFAULT'=>clone $default, 'ordinary'=>clone $default, 'custom'=>(object)['bwfont'=>2, 'commentfont'=>2, 'commentfontcolour'=>[255, 0, 0]]]];
}
it('UpdatesMatchingDefaultsAndPreservesDifferentStyles', function () {
	$map = wm_style_test_map();
	wm_comment_apply_style($map,'bundled:VeraBd.ttf:11','255 255 255');
	expect($map->links['ordinary']->commentfont)->toBe($map->links['DEFAULT']->commentfont)->and($map->links['ordinary']->commentfontcolour)->toBe([255, 255, 255]);
	expect($map->links['custom']->commentfont)->toBe(2)->and($map->links['custom']->commentfontcolour)->toBe([255, 0, 0]);
	expect($map->links[':: DEFAULT ::']->commentfont)->toBe(100);
	$before = serialize($map);
	wm_comment_apply_style($map,'bad','bad');
	expect(serialize($map))->toBe($before);
	wm_comment_update_default($map,'commentfont',$map->links['DEFAULT']->commentfont);
	expect(serialize($map))->toBe($before);
});
it('KeepsTheSelectedFontWithoutDuplicateBundledOptions', function () {
	$map             = wm_style_test_map();
	$map->fonts[101] = clone $map->fonts[100];
	$html            = wm_style_font_options($map,101);
	expect(substr_count($html,'Vera Sans — 9 pt'))->toBe(1)->and($html)->toContain('value="101" selected','Bundled fonts','2 (GD builtin)');
	expect(strpos($html,'Vera Sans — 9 pt') < strpos($html,'Vera Sans — 11 pt'))->toBeTrue();
	expect(wm_style_font_select($map,'unsafe"name',101))->toContain('unsafe&quot;name');
	$map->fonts[102]       = clone $map->fonts[100];
	$map->fonts[102]->file = '/missing/custom.ttf';
	expect(wm_editor_font_label(102,$map->fonts[102]))->toContain('custom.ttf');
	$map->links['DEFAULT']->commentfontcolour = [12, 34, 56];
	expect(wm_comment_style_fields($map))->toContain('Current custom colour (12 34 56)','value="12 34 56" selected');
	$map->links['DEFAULT']->commentfontcolour = [-3, -3, -3];
	expect(wm_comment_style_fields($map))->toContain('value="contrast" selected');
});
it('RejectsInvalidFontSelections', function ($value) {expect(wm_comment_resolve_font(wm_style_test_map(),$value))->toBeNull(); })->with([null, 123, '999', 'bundled:../Vera.ttf:9', 'bundled:Missing.ttf:9', 'bundled:Vera.ttf:99']);
it('ReusesExistingFontsAndAllocatesAnUnusedNumber', function () {
	$map = wm_style_test_map();
	expect(wm_comment_resolve_font($map,'100'))->toBe(100)->and(wm_comment_resolve_font($map,'bundled:Vera.ttf:9'))->toBe(100);
	expect(wm_comment_resolve_font($map,'bundled:VeraBd.ttf:11'))->toBe(101);
});
it('RejectsInvalidColours', function ($value) {expect(wm_comment_resolve_colour($value))->toBeNull(); })->with([null, 123, '256 0 0', '-1 0 0', '0 0', '0 0 255 extra', "0 0 255\n"]);
it('SupportsWhiteBlueAndAutomaticContrast', function () {expect(wm_comment_resolve_colour('255 255 255'))->toBe([255, 255, 255])->and(wm_comment_resolve_colour('0 0 255'))->toBe([0, 0, 255])->and(wm_comment_resolve_colour('contrast'))->toBe([-3, -3, -3]); });
it('OmitsMissingBundledFonts', function () {
	$file   = dirname(__DIR__,2) . '/docs/example/Vera.ttf';
	$backup = $file . '.style-test-' . bin2hex(random_bytes(5));
	rename($file,$backup);

	try {
		$map = wm_style_test_map();
		expect(wm_style_font_options($map,2))->not->toContain('value="bundled:Vera.ttf:9"')->and(wm_comment_resolve_font($map,'bundled:Vera.ttf:9'))->toBeNull();
	} finally {
		if (file_exists($file)) {
			unlink($file);
		}rename($backup,$file);
	}
});

it('UsesReadableFontNamesInTheExistingFontSelector', function () {
	require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';
	$map  = wm_style_test_map();
	$html = get_fontlist($map, 'font"name', 100);
	expect($html)->toContain('Vera Sans — 9 pt', 'selected', 'font&quot;name');
});


it('DeduplicatesAndReusesPortableFontsFromTheCactiWorkingDirectory', function () {
	$cwd = getcwd();
	$map = wm_style_test_map();
	$map->fonts[100]->file = 'docs/example/Vera.ttf';
	try {
		chdir(dirname(__DIR__, 4));
		$html = wm_style_font_options($map, 100);
		expect(substr_count($html, 'Vera Sans — 9 pt'))->toBe(1)
			->and($html)->toContain('value="100" selected');
		expect(wm_comment_resolve_font($map, 'bundled:Vera.ttf:9'))->toBe(100)
			->and(count($map->fonts))->toBe(2);
	} finally {
		chdir($cwd);
	}
});

it('AssociatesCommentStyleLabelsWithTheirSelectors', function () {
	$document = new DOMDocument();
	$document->loadHTML('<table>' . wm_comment_style_fields(wm_style_test_map()) . '</table>', LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	foreach (['mapstyle_commentfont' => 'Link Comment Font', 'mapstyle_commentcolour' => 'Link Comment Colour'] as $id => $text) {
		$label = $xpath->query('//label[@for="' . $id . '"]')->item(0);
		expect($label)->not->toBeNull()->and($label->textContent)->toBe($text)
			->and($xpath->query('//select[@id="' . $id . '"]')->length)->toBe(1);
	}
});
