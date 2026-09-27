<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

// Unit coverage for plugin_weathermap_csp_nonce() in setup.php.

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

test('csp nonce falls back to an empty string on Cacti without CactiSecureHeaders', function () {
	expect(class_exists('CactiSecureHeaders'))->toBeFalse();
	expect(plugin_weathermap_csp_nonce())->toBe('');
});

test('csp nonce helper is declared to return a string', function () {
	$ref = new ReflectionFunction('plugin_weathermap_csp_nonce');

	expect((string) $ref->getReturnType())->toBe('string');
});

test('csp nonce delegates to CactiSecureHeaders when the class is available', function () {
	$source = file_get_contents(__DIR__ . '/../../setup.php');

	expect($source)->toContain("class_exists('CactiSecureHeaders')");
	expect($source)->toContain('CactiSecureHeaders::getNonceAttribute()');
});
