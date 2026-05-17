<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('weathermap setup.php structure', function () {
	$source = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

	it('defines plugin_weathermap_install function', function () use ($source) {
		expect($source)->toContain('function plugin_weathermap_install');
	});

	it('defines plugin_weathermap_version function', function () use ($source) {
		expect($source)->toContain('function plugin_weathermap_version');
	});

	it('defines plugin_weathermap_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_weathermap_uninstall');
	});

	it('returns version array with name key', function () use ($source) {
		expect($source)->toMatch('/[\'\""]name[\'\""]\s*=>/');
	});

	it('reads version from INFO ini file', function () use ($source) {
		// setup.php reads version info via parse_ini_file, not a literal array.
		expect($source)->toContain('parse_ini_file');
	});

	it('registers hooks in install function', function () use ($source) {
		expect($source)->toContain('api_plugin_register_hook');
	});
});
