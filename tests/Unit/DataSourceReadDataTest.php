<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Helpers/DataSourceHarness.php';
require_once dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_cacti.php';
require_once dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_wmdata.php';

describe('datasource ReadData runs without a fatal error', function () {
	it('cacti datasource returns a three element array', function () {
		$ds   = new WeatherMapDataSource_cacti();
		$map  = new stdClass();
		$item = wm_test_item();

		$result = $ds->ReadData('cacti:123', $map, $item);

		expect($result)->toBeArray()->toHaveCount(3);
	});

	it('wmdata datasource reads a value out of its tab separated file', function () {
		$datafile = tempnam(sys_get_temp_dir(), 'wmdata');
		file_put_contents($datafile, "othername\t1\t2\nmyname\t100\t200\n");

		$ds   = new WeatherMapDataSource_wmdata();
		$map  = new stdClass();
		$item = wm_test_item();

		$result = $ds->ReadData('wmdata:' . $datafile . ':myname', $map, $item);

		unlink($datafile);

		expect($result)->toBeArray()->toHaveCount(3);
		expect($result[IN])->toEqual('100');
		expect($result[OUT])->toEqual('200');
		expect($result[2])->toBeGreaterThan(0);
	});

	it('wmdata datasource returns nulls when the named value is absent', function () {
		$datafile = tempnam(sys_get_temp_dir(), 'wmdata');
		file_put_contents($datafile, "othername\t1\t2\n");

		$ds   = new WeatherMapDataSource_wmdata();
		$map  = new stdClass();
		$item = wm_test_item();

		$result = $ds->ReadData('wmdata:' . $datafile . ':missing', $map, $item);

		unlink($datafile);

		expect($result[IN])->toBeNull();
		expect($result[OUT])->toBeNull();
	});

	it('wmdata datasource survives a target naming a file that is not there', function () {
		$ds   = new WeatherMapDataSource_wmdata();
		$map  = new stdClass();
		$item = wm_test_item();

		$result = $ds->ReadData('wmdata:/nonexistent/path/file.txt:name', $map, $item);

		expect($result)->toBeArray()->toHaveCount(3);
		expect($result[IN])->toBeNull();
	});

	it('wmdata Recognise accepts a wmdata target and rejects others', function () {
		$ds = new WeatherMapDataSource_wmdata();

		expect($ds->Recognise('wmdata:/tmp/file.txt:name'))->toBeTrue();
		expect($ds->Recognise('cacti:123'))->toBeFalse();
	});
});
