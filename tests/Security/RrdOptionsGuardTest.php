<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Helpers/DataSourceHarness.php';
require_once dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_rrd.php';

describe('rrd_options quote and backslash guard', function () {
	/* The guard previously used the pattern '/["\'\\]/', which PCRE read as an
	 * unterminated character class.  preg_match() returned false, the else
	 * branch ran every time, and the check never rejected anything.  These
	 * cases pin the behaviour the guard is meant to have. */
	it('rejects a double quote', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe('--start "-1h"'))->toBeFalse();
	});

	it('rejects a single quote', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe("--start '-1h'"))->toBeFalse();
	});

	it('rejects a backslash', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe('--start \\-1h'))->toBeFalse();
	});

	it('accepts plain space separated flags', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe('--start -1h --end now'))->toBeTrue();
	});

	it('accepts an empty value and a null value', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe(''))->toBeTrue();
		expect($ds->wmrrd_options_are_safe(null))->toBeTrue();
	});

	it('is not fooled by a quote at the very end of the string', function () {
		$ds = new WeatherMapDataSource_rrd();

		expect($ds->wmrrd_options_are_safe('--start -1h"'))->toBeFalse();
	});
});
