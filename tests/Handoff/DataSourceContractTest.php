<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

describe('data source ReadData return contract', function (): void {
	it('base WeatherMapDataSource::ReadData() returns a 3-element [-1,-1,0] array', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

		expect($source)->toContain('return ([-1, -1, 0]);');
	});

	it('does not contain the old 2-element [-1,-1] return in ReadData', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

		// The commented-out old signature is allowed, but no live return with only two elements.
		$lines = explode("\n", $source);

		foreach ($lines as $line) {
			$trimmed = ltrim($line);

			if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
				continue;
			}

			// A live return with exactly [-1,-1] (no third element) is a regression.
			expect($line)->not->toMatch('/\breturn\s*\(\s*\[\s*-1\s*,\s*-1\s*\]\s*\)/');
		}
	});

	it('WeatherMapDataSource_fping::ReadData() returns a 3-element array', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_fping.php');

		// The fping implementation must end its ReadData with a 3-element return.
		expect($source)->toContain('return ([$data[IN], $data[OUT], $data_time]);');
	});

	it('concrete datasource ReadData() returns produce at least 3 elements', function (): void {
		$files = [
			'lib/datasources/WeatherMapDataSource_fping.php',
			'lib/datasources/WeatherMapDataSource_rrd.php',
		];

		$root = dirname(__DIR__, 2);

		foreach ($files as $relfile) {
			$path   = $root . '/' . $relfile;
			$source = file_get_contents($path);

			if ($source === false) {
				continue;
			}

			$lines = explode("\n", $source);

			$returnCount    = 0;
			$twoElementOnly = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				// Detect any return of a 2-element array (the old contract).
				if (preg_match('/\breturn\s*[\(\[]?\s*-1\s*,\s*-1\s*[\)\]]?\s*;/', $line)) {
					$twoElementOnly++;
				}
			}

			expect($twoElementOnly)->toBe(
				0,
				"File {$relfile} contains a 2-element ReadData return (old contract)"
			);
		}
	});
});
