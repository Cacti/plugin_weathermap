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
	require_once __DIR__ . '/../../lib/editor.inc.php';
});

/**
 * Parse one rendered element and return its attributes and text.
 *
 * @param string $html Markup containing exactly one element.
 *
 * @return array{tag: string, attributes: array<string, string>, text: string, count: int}
 */
function wm_attributes_test_parse($html) {
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<meta charset="utf-8"><select>' . $html . '</select>');
	libxml_clear_errors();
	$select     = $doc->getElementsByTagName('select')->item(0);
	$element    = $select->firstChild;
	$attributes = [];

	foreach ($element->attributes as $attribute) {
		$attributes[$attribute->name] = $attribute->value;
	}

	return ['tag' => $element->nodeName, 'attributes' => $attributes, 'text' => $element->textContent, 'count' => $select->childNodes->length];
}

it('EscapesEverySpecialCharacterInAttributeValues', function (string $char, string $encoded) {
	expect(wm_editor_attributes(['value' => 'a' . $char . 'b']))->toBe(' value="a' . $encoded . 'b"');
})->with([
	'double quote' => ['"', '&quot;'],
	'single quote' => ["'", '&apos;'],
	'less than'    => ['<', '&lt;'],
	'greater than' => ['>', '&gt;'],
	'ampersand'    => ['&', '&amp;'],
	'backtick'     => ['`', '&#96;'],
]);

it('DoubleEncodesExistingEntitiesSoValuesRoundTrip', function () {
	expect(wm_editor_attributes(['value' => '&amp; &quot; &#39;']))->toBe(' value="&amp;amp; &amp;quot; &amp;#39;"');
});

it('RoundTripsHostileAndUnicodeValuesThroughAnHtmlParser', function (string $value) {
	$parsed = wm_attributes_test_parse(wm_editor_option($value, ['data-description' => $value, 'value' => $value]));
	expect($parsed['count'])->toBe(1)
		->and($parsed['tag'])->toBe('option')
		->and($parsed['attributes'])->toBe(['data-description' => $value, 'value' => $value])
		->and($parsed['text'])->toBe($value);
})->with([
	'attribute breakout' => ['x" onmouseover="alert(1)'],
	'single breakout'    => ["x' onmouseover='alert(1)"],
	'element injection'  => ['"></option><script>alert(1)</script>'],
	'entity text'        => ['A&amp;B &lt;tag&gt;'],
	'unicode'            => ['Ünïcødé — 東京 🚀'],
	'whitespace'         => ["tab\tand space"],
]);

it('RendersEmptyStringsAsEmptyAttributesAndLabels', function () {
	expect(wm_editor_attributes(['data-imagesrc' => '']))->toBe(' data-imagesrc=""')
		->and(wm_editor_option('', ['value' => '']))->toBe('<option value=""></option>');
});

it('RendersTrueAsABooleanAttributeAndOmitsFalseAndNull', function () {
	expect(wm_editor_attributes(['selected' => true]))->toBe(' selected')
		->and(wm_editor_attributes(['selected' => false]))->toBe('')
		->and(wm_editor_attributes(['hidden' => null]))->toBe('')
		->and(wm_editor_attributes(['value' => 'x', 'disabled' => true, 'hidden' => false, 'selected' => null]))->toBe(' value="x" disabled');
});

it('RendersIntegerAndFloatValuesAsStrings', function () {
	expect(wm_editor_attributes(['value' => 0]))->toBe(' value="0"')
		->and(wm_editor_attributes(['value' => -1]))->toBe(' value="-1"')
		->and(wm_editor_attributes(['value' => 42]))->toBe(' value="42"')
		->and(wm_editor_attributes(['value' => 1.5]))->toBe(' value="1.5"');
});

it('KeepsTheCallerAttributeOrder', function () {
	expect(wm_editor_attributes(['selected' => true, 'data-description' => 'd', 'data-imagesrc' => 'i', 'value' => 'v']))
		->toBe(' selected data-description="d" data-imagesrc="i" value="v"')
		->and(wm_editor_attributes(['value' => 'v', 'selected' => true]))->toBe(' value="v" selected');
});

it('ReturnsAnEmptyStringForNoAttributes', function () {
	expect(wm_editor_attributes([]))->toBe('')
		->and(wm_editor_option('Label'))->toBe('<option>Label</option>');
});

it('AcceptsNamespacedAndMixedCaseNames', function () {
	expect(wm_editor_attributes(['xlink:href' => 'a', 'Data-X1' => 'b']))->toBe(' xlink:href="a" Data-X1="b"');
});

it('RejectsInvalidAttributeNames', function (mixed $name) {
	expect(fn () => wm_editor_attributes([$name => 'x']))->toThrow(InvalidArgumentException::class);
})->with([
	'empty'            => [''],
	'leading digit'    => ['1value'],
	'leading dash'     => ['-value'],
	'space'            => ['value onclick'],
	'equals'           => ['value='],
	'quote'            => ['value"'],
	'angle bracket'    => ['value>'],
	'slash'            => ['data/x'],
	'underscore'       => ['data_x'],
	'non-ascii'        => ['dätä'],
	'trailing newline' => ["value\n"],
	'integer key'      => [0],
]);

it('RejectsNonScalarValues', function (mixed $value) {
	expect(fn () => wm_editor_attributes(['value' => $value]))->toThrow(InvalidArgumentException::class);
})->with([
	'array'  => [[1]],
	'object' => [new stdClass()],
]);

it('RejectsInvalidNamesInOptions', function () {
	expect(fn () => wm_editor_option('x', ['on click' => 'x']))->toThrow(InvalidArgumentException::class);
});
