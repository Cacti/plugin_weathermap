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

	it('returns version array with version key', function () use ($source) {
		expect($source)->toMatch('/[\'\""]version[\'\""]\s*=>/');
	});

	it('registers hooks in install function', function () use ($source) {
		expect($source)->toContain('api_plugin_register_hook');
	});
});
