<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

beforeAll(function (): void {
	$plugin = dirname(__DIR__, 2);
	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
});

it('selects the narrowest matching scale range regardless of its position', function (): void {
	// new WeatherMap() loads its plugin directories relative to the working directory.
	$cwd = getcwd();
	chdir(dirname(__DIR__, 2));

	try {
		$map = new WeatherMap();
	} finally {
		chdir($cwd);
	}

	$broad  = ['bottom' => 0, 'top' => 100, 'red1' => 10, 'green1' => 20, 'blue1' => 30, 'tag' => 'broad'];
	$narrow = ['bottom' => 40, 'top' => 60, 'red1' => 200, 'green1' => 210, 'blue1' => 220, 'tag' => 'narrow'];

	foreach ([[$broad, $narrow], [$narrow, $broad]] as $ranges) {
		$map->colours['OVERLAP'] = $ranges;
		[$colour, $key, $tag]    = $map->NewColourFromPercent(50, 'OVERLAP');

		expect([$colour->r, $colour->g, $colour->b])->toBe([200, 210, 220]);
		expect($key)->toBe($ranges[0] === $narrow ? 0 : 1);
		expect($tag)->toBe('narrow');
	}
});

it('retains collinear reversal points while removing an in-between point', function (): void {
	$forward  = [[X => 0, Y => 0], [X => 5, Y => 0], [X => 10, Y => 0]];
	$reversal = [[X => 0, Y => 0], [X => 10, Y => 0], [X => 5, Y => 0]];
	$bend     = [[X => 0, Y => 0], [X => 5, Y => 5], [X => 10, Y => 0]];

	expect(simplify_spine($forward))->toBe([$forward[0], $forward[2]]);
	expect(simplify_spine($reversal))->toBe($reversal);
	expect(simplify_spine($bend))->toBe($bend);
});

it('clamps distance lookups to endpoints and handles degenerate paths', function (): void {
	$path = [[0, 0, 0], [10, 0, 10], [20, 0, 20]];

	expect(find_distance_coords($path, -5))->toBe([0, 0, 0]);
	expect(find_distance_coords($path, 5))->toBe([5.0, 0.0, 0]);
	expect(find_distance_coords($path, 25))->toBe([20, 0, 1]);
	expect(find_distance_coords_angle($path, 25))->toBe([20, 0, 1, 0.0]);

	$single = [[7, 9, 0]];
	$empty  = [];
	expect(find_distance_coords($single, 5))->toBe([7, 9, 0]);
	expect(find_distance_coords_angle($single, 5))->toBe([7, 9, 0, 0]);
	expect(find_distance_coords_angle($empty, 5))->toBe([0, 0, 0, 0]);

	$zeroLength = [[1, 2, 0], [1, 2, 0], [1, 2, 0]];
	expect(find_distance_coords($zeroLength, 0))->toBe([1, 2, 0]);
	expect(find_distance_coords_angle($zeroLength, 0))->toBe([1, 2, 0, 0]);
	expect(find_distance_coords($zeroLength, 5))->toBe([1, 2, 1]);
});
