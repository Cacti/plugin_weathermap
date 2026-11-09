<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The base classes' default methods are normally overridden by subclasses,
 * so pin the defaults themselves. This runs in-process, unlike the
 * standalone-load checks, so the moved lines are measured for coverage.
 */
beforeAll(function (): void {
	$lib = dirname(__DIR__, 2) . '/lib';

	require_once $lib . '/WeatherMapDataSource.class.php';
	require_once $lib . '/WeatherMapPreProcessor.class.php';
	require_once $lib . '/WeatherMapPostProcessor.class.php';
	require_once $lib . '/WeatherMapBase.class.php';
	require_once $lib . '/WeatherMapItem.class.php';
});

describe('engine base class defaults', function (): void {
	it('lets a base data source recognise nothing and read no data', function (): void {
		$source = new WeatherMapDataSource();
		$map    = null;
		$item   = null;

		expect($source->Recognise('anything'))->toBeFalse();
		expect($source->ReadData('anything', $map, $item))->toBe([-1, -1, 0]);
	});

	it('runs base pre- and post-processors as no-ops', function (): void {
		$map = null;

		expect((new WeatherMapPreProcessor())->run($map))->toBeFalse();
		expect((new WeatherMapPostProcessor())->run($map))->toBeFalse();
	});

	it('returns stored notes and null for missing ones', function (): void {
		$item                  = new WeatherMapItem();
		$item->notes['colour'] = 'red';

		expect($item->get_note('colour'))->toBe('red');
		expect($item->get_note('missing'))->toBeNull();
		expect($item->my_type())->toBe('ITEM');
	});
});
