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

describe('MapnameValidator', function (): void {
	it('is instantiable without Cacti bootstrap and accepts valid basenames', function (string $filename): void {
		$validator = new MapnameValidator();

		expect($validator::sanitize($filename))->toBe($filename)
			->and($validator::isValid($filename))->toBeTrue()
			->and(wm_editor_sanitize_conffile($filename))->toBe($filename);
	})->with([
		'simple.conf',
		'site_network-01.conf',
		'A +%.conf',
		"Name 'quoted' & # ? e.conf",
		'%2F.conf',
	]);

	it('rejects invalid basenames, path separators, controls, and non-strings', function (mixed $filename): void {
		expect(MapnameValidator::sanitize($filename))->toBe('')
			->and(MapnameValidator::isValid($filename))->toBeFalse()
			->and(wm_editor_sanitize_conffile($filename))->toBe('');
	})->with([
		'',
		'.conf',
		'map.txt',
		'map.conf.php',
		'../escape.conf',
		'/etc/map.conf',
		'sub/map.conf',
		'..\\escape.conf',
		'sub\\map.conf',
		"null\0byte.conf",
		"newline\n.conf",
		"del\x7f.conf",
		null,
		123,
		[['map.conf']],
	]);
});
