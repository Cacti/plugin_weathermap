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
	it('allows valid config filenames through the sanitizer', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe($filename)
			->and(MapnameValidator::sanitize($filename))->toBe($filename)
			->and(MapnameValidator::isValid($filename))->toBeTrue();
	})->with([
		'simple conf'             => ['my-map.conf'],
		'hyphens and underscores' => ['site_network-01.conf'],
	]);

	it('rejects traversal paths, Windows backslashes, non-.conf names, and controls (WM-PATH-01)', function (string $filename): void {
		expect(wm_editor_sanitize_conffile($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse();
	})->with([
		'non-conf traversal'    => ['../../etc/passwd'],
		'double extension'      => ['map.conf.php'],
		'missing extension'     => ['map'],
		'unix relative path'    => ['../other/map.conf'],
		'unix absolute path'    => ['/absolute/path.conf'],
		'windows relative path' => ['..\\configs\\other.conf'],
		'windows deep traversal' => ['..\\..\\windows\\system32\\evil.conf'],
		'windows subdir path'   => ['sub\\map.conf'],
		'empty string'          => [''],
		'bare extension'        => ['.conf'],
		'nul byte'              => ["map\0.conf"],
		'line feed'             => ["map\n.conf"],
		'carriage return'       => ["map\r.conf"],
	]);
});
