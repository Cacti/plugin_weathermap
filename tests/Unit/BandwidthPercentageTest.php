<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

// Mirrors the guarded formula used in WeatherMap.class.php lines 1138-1146.
// Defined inline to avoid pulling in class-level dependencies.
if (!function_exists('calc_bandwidth_percent')) {
	function calc_bandwidth_percent(float $traffic, float $max): float {
		return ($max != 0) ? (($traffic / $max) * 100) : 0;
	}
}

describe('calc_bandwidth_percent formula', function (): void {
	it('returns 50.0 for half utilization', function (): void {
		expect(calc_bandwidth_percent(500, 1000))->toBe(50.0);
	});

	it('returns 100.0 at full utilization', function (): void {
		expect(calc_bandwidth_percent(1000, 1000))->toBe(100.0);
	});

	it('returns values above 100 when traffic exceeds max', function (): void {
		// Clipping is the caller's responsibility; the formula itself is uncapped.
		// Use round() to avoid float representation noise (e.g. 110.00000000000001).
		expect(round(calc_bandwidth_percent(1100, 1000), 6))->toBe(110.0);
	});

	it('returns 0.0 when max bandwidth is zero', function (): void {
		expect(calc_bandwidth_percent(500, 0))->toBe(0.0);
	});

	it('returns 0.0 when traffic is zero', function (): void {
		expect(calc_bandwidth_percent(0, 1000))->toBe(0.0);
	});
});

describe('calc_bandwidth_percent half-duplex cases', function (): void {
	// In half-duplex mode WeatherMap sums in+out before dividing, so the
	// combined traffic is passed as a single value.

	it('returns 100.0 when combined in+out equals max', function (): void {
		expect(calc_bandwidth_percent(300 + 700, 1000))->toBe(100.0);
	});

	it('returns 0.0 for half-duplex combined traffic when max is zero', function (): void {
		expect(calc_bandwidth_percent(300 + 700, 0))->toBe(0.0);
	});
});

describe('bandwidth percentage zero guard in source', function (): void {
	it('guards against zero max_bandwidth_out before dividing', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

		expect($source)->toContain('($myobj->max_bandwidth_out != 0)');
		expect($source)->toContain('($myobj->max_bandwidth_in != 0)');
	});
});
