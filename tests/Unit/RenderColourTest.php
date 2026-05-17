<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

// Define inline to avoid Cacti DB calls at the top of WeatherMap.functions.php.
if (!function_exists('render_colour')) {
	function render_colour($col) {
		if (($col[0] == -1) && ($col[1] == -1) && ($col[2] == -1)) {
			return 'none';
		}

		if (($col[0] == -2) && ($col[1] == -2) && ($col[2] == -2)) {
			return 'copy';
		}

		if (($col[0] == -3) && ($col[1] == -3) && ($col[2] == -3)) {
			return 'contrast';
		} else {
			return sprintf('%d %d %d', $col[0], $col[1], $col[2]);
		}
	}
}

describe('render_colour sentinel values', function (): void {
	it('returns none for [-1,-1,-1]', function (): void {
		expect(render_colour([-1, -1, -1]))->toBe('none');
	});

	it('returns copy for [-2,-2,-2]', function (): void {
		expect(render_colour([-2, -2, -2]))->toBe('copy');
	});

	it('returns contrast for [-3,-3,-3]', function (): void {
		expect(render_colour([-3, -3, -3]))->toBe('contrast');
	});

	it('formats normal RGB as space-separated integers', function (): void {
		expect(render_colour([255, 128, 0]))->toBe('255 128 0');
	});

	it('formats black as 0 0 0', function (): void {
		expect(render_colour([0, 0, 0]))->toBe('0 0 0');
	});
});

describe('render_colour mutation guards (third-element checks)', function (): void {
	// These three tests catch the historical bug where $col[2] was written as
	// $col[1] in the sentinel conditions, making the third element irrelevant.

	it('does not return none when third element is not -1', function (): void {
		expect(render_colour([-1, -1, 0]))->not->toBe('none');
	});

	it('does not return copy when third element is not -2', function (): void {
		expect(render_colour([-2, -2, 0]))->not->toBe('copy');
	});

	it('does not return contrast when third element is not -3', function (): void {
		expect(render_colour([-3, -3, 0]))->not->toBe('contrast');
	});
});

describe('render_colour source correctness', function (): void {
	it('uses $col[2] in all three sentinel conditions', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php');

		// All three sentinels must check $col[2], not repeat $col[1].
		expect($source)->toContain('($col[0] == -1) && ($col[1] == -1) && ($col[2] == -1)');
		expect($source)->toContain('($col[0] == -2) && ($col[1] == -2) && ($col[2] == -2)');
		expect($source)->toContain('($col[0] == -3) && ($col[1] == -3) && ($col[2] == -3)');
	});
});
