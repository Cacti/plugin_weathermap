<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/MapnameValidator.class.php';
require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';

/**
 * Path traversal surface tests for plugin_weathermap (WM-PATH-01).
 *
 * Exercises both MapnameValidator::sanitize() and wm_editor_sanitize_conffile()
 * directly to verify .conf extension enforcement, forward-slash rejection,
 * Windows backslash rejection, and control-character rejection.
 */
describe('Weathermap map config path traversal guards (WM-PATH-01)', function (): void {
	it('allows a clean conf filename through the sanitizer', function (): void {
		expect(wm_editor_sanitize_conffile('my-map.conf'))->toBe('my-map.conf')
			->and(MapnameValidator::sanitize('my-map.conf'))->toBe('my-map.conf');
	});

	it('allows filenames with hyphens and underscores', function (): void {
		expect(wm_editor_sanitize_conffile('site_network-01.conf'))->toBe('site_network-01.conf')
			->and(MapnameValidator::isValid('site_network-01.conf'))->toBeTrue();
	});

	it('rejects filenames that do not end in .conf', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		'../../etc/passwd',
		'map.conf.php',
		'map',
	]);

	it('rejects filenames containing forward slash', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		'../other/map.conf',
		'/absolute/path.conf',
	]);

	it('rejects filenames containing Windows backslash (WM-PATH-01 fix)', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		'..\\configs\\other.conf',
		'..\\..\\windows\\system32\\evil.conf',
		'sub\\map.conf',
	]);

	it('rejects empty filename and bare .conf extension', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		'',
		'.conf',
	]);

	it('rejects control characters and NUL bytes', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		"map\0.conf",
		"map\n.conf",
		"map\r.conf",
	]);
});
