<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for weathermap_footer_links() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('prints the version number and documentation links', function () {
	$info = plugin_weathermap_version();

	ob_start();
	weathermap_footer_links();
	$output = ob_get_clean();

	expect($output)->toContain('Local Documentation');
	expect($output)->toContain($info['version']);
});
