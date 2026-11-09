<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/*
 * WeatherMap.class.php declares WeatherMapDataSource and the IN/OUT
 * constants, which collide with the datasource harness stubs other suites
 * load into the shared Pest process. Each test therefore loads the class in
 * its own process.
 */
final class AlgorithmRegressionTest extends TestCase {
	private static function loadWeatherMap(): void {
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testSelectsTheNarrowestMatchingScaleRangeRegardlessOfPosition(): void {
		self::loadWeatherMap();

		$map    = new WeatherMap();
		$broad  = ['bottom' => 0, 'top' => 100, 'red1' => 10, 'green1' => 20, 'blue1' => 30, 'tag' => 'broad'];
		$narrow = ['bottom' => 40, 'top' => 60, 'red1' => 200, 'green1' => 210, 'blue1' => 220, 'tag' => 'narrow'];

		foreach ([[$broad, $narrow], [$narrow, $broad]] as $ranges) {
			$map->colours['OVERLAP'] = $ranges;
			[$colour, $key, $tag]    = $map->NewColourFromPercent(50, 'OVERLAP');

			self::assertSame([200, 210, 220], [$colour->r, $colour->g, $colour->b]);
			self::assertSame($ranges[0] === $narrow ? 0 : 1, $key);
			self::assertSame('narrow', $tag);
		}
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRetainsCollinearReversalPointsWhileRemovingAnInBetweenPoint(): void {
		self::loadWeatherMap();

		$forward  = [[X => 0, Y => 0], [X => 5, Y => 0], [X => 10, Y => 0]];
		$reversal = [[X => 0, Y => 0], [X => 10, Y => 0], [X => 5, Y => 0]];
		$bend     = [[X => 0, Y => 0], [X => 5, Y => 5], [X => 10, Y => 0]];

		self::assertSame([$forward[0], $forward[2]], simplify_spine($forward));
		self::assertSame($reversal, simplify_spine($reversal));
		self::assertSame($bend, simplify_spine($bend));
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testClampsDistanceLookupsToEndpointsAndHandlesDegeneratePaths(): void {
		self::loadWeatherMap();

		$path = [[0, 0, 0], [10, 0, 10], [20, 0, 20]];

		self::assertSame([0, 0, 0], find_distance_coords($path, -5));
		self::assertSame([5.0, 0.0, 0], find_distance_coords($path, 5));
		self::assertSame([20, 0, 1], find_distance_coords($path, 25));
		self::assertSame([20, 0, 1, 0.0], find_distance_coords_angle($path, 25));

		$single = [[7, 9, 0]];
		$empty  = [];
		self::assertSame([7, 9, 0], find_distance_coords($single, 5));
		self::assertSame([7, 9, 0, 0], find_distance_coords_angle($single, 5));
		self::assertSame([0, 0, 0, 0], find_distance_coords_angle($empty, 5));

		$zeroLength = [[1, 2, 0], [1, 2, 0], [1, 2, 0]];
		self::assertSame([1, 2, 0], find_distance_coords($zeroLength, 0));
		self::assertSame([1, 2, 0, 0], find_distance_coords_angle($zeroLength, 0));
		self::assertSame([1, 2, 1], find_distance_coords($zeroLength, 5));
	}
}
