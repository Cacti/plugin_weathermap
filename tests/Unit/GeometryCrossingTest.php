<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/WMPoint.class.php';
require_once dirname(__DIR__, 2) . '/lib/WMVector.class.php';
require_once dirname(__DIR__, 2) . '/lib/WMLine.class.php';

describe('WMLine::findCrossingPoint()', function () {
	it('finds where two sloped lines cross', function () {
		// y = x through the origin, and y = -x + 4 through (0,4)
		$a = new WMLine(new WMPoint(0, 0), new WMVector(1, 1));
		$b = new WMLine(new WMPoint(0, 4), new WMVector(1, -1));

		$crossing = $a->findCrossingPoint($b);

		expect($crossing)->toBeInstanceOf(WMPoint::class);
		expect($crossing->x)->toEqualWithDelta(2.0, 0.0001);
		expect($crossing->y)->toEqualWithDelta(2.0, 0.0001);
	});

	/* The parallel branch used to throw WeathermapInternalFail, a class that is
	 * defined nowhere in the plugin or in Cacti, so the guard turned into a
	 * fatal "Class not found" Error that nothing could catch. */
	it('throws a catchable Throwable when the lines are parallel', function () {
		$a = new WMLine(new WMPoint(0, 0), new WMVector(1, 1));
		$b = new WMLine(new WMPoint(0, 5), new WMVector(2, 2));

		expect(fn () => $a->findCrossingPoint($b))->toThrow(LogicException::class);
	});

	it('names the class it throws so the failure is not a fatal error', function () {
		$a = new WMLine(new WMPoint(0, 0), new WMVector(1, 1));
		$b = new WMLine(new WMPoint(0, 5), new WMVector(1, 1));

		$caught = null;

		try {
			$a->findCrossingPoint($b);
		} catch (Throwable $e) {
			$caught = $e;
		}

		expect($caught)->toBeInstanceOf(Throwable::class);
		expect(get_class($caught))->not->toBe('Error');
	});

	it('does not reference the undefined WeathermapInternalFail class', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WMLine.class.php');

		expect($source)->not->toContain('WeathermapInternalFail');
	});
});
