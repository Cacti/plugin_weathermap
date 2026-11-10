<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/setup.php';

/* The graph tree shows a map's cached title as its header, and the title comes
 * from the map config's TITLE line, which Manage and Edit Weathermap users write. */
describe('weathermap_tree_item_render', function (): void {
	afterEach(function (): void {
		unset($GLOBALS['__test_db_fetch_row_prepared']);
	});

	it('escapes the map title in the tree header', function (): void {
		$_SESSION['sess_user_id']                  = 1;
		$GLOBALS['__test_db_fetch_row_prepared']   = fn () => [
			'id'         => 0,
			'titlecache' => '<img src=x onerror=alert(1)> Core',
			'configfile' => 'core.conf',
		];

		ob_start();
		weathermap_tree_item_render(['item_id' => 0]);
		$html = ob_get_clean();

		expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt; Core')
			->and($html)->not->toContain('<img src=x');
	});
});
