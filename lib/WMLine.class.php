<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * A Line is simply a Vector that passes through a Point
 */
class WMLine {
	private $point;
	private $vector;

	/**
	 * Constructs a line passing through a given point in a given
	 * direction.
	 *
	 * @param WMPoint  $p A point the line passes through.
	 * @param WMVector $v The line's direction vector.
	 */
	public function __construct($p, $v) {
		$this->point  = $p;
		$this->vector = $v;
	}

	/**
	 * Returns the line's slope, derived from its direction vector.
	 *
	 * @return float The line's slope.
	 */
	public function getSlope() {
		return $this->vector->getSlope();
	}

	/**
	 * Computes the line's Y-intercept (where it crosses x=0), derived
	 * from its slope and a known point on the line.
	 *
	 * @return float The line's Y-intercept.
	 */
	public function getYIntercept() {
		$slope     = $this->getSlope();
		$intercept = $this->point->y - $this->point->x * $slope;

		return $intercept;
	}

	/**
	 * Returns a debug string representation of the line (its point and
	 * direction vector).
	 *
	 * @return string The formatted debug string.
	 */
	public function __toString() {
		return sprintf('/%s-%s/', $this->point, $this->vector);
	}

	/**
	 * Find the point where this line and another one cross
	 *
	 * @param                         $line2 the other line
	 * @return WMPoint                the crossing point
	 * @throws WeathermapInternalFail
	 */
	public function findCrossingPoint($line2) {
		$slope1 = $this->vector->getSlope();
		$slope2 = $line2->vector->getSlope();

		if ($slope1 == $slope2) {
			// for a general case, this should probably be handled better
			// but for our use, there should never be parallel lines
			throw new WeathermapInternalFail('ParallelLinesNeverCross');
		}

		$intercept1 = $this->getYIntercept();
		$intercept2 = $line2->getYIntercept();

		$xCrossing = ($intercept2 - $intercept1) / ($slope1 - $slope2);
		$yCrossing = $intercept1 + $slope1 * $xCrossing;

		return new WMPoint($xCrossing, $yCrossing);
	}
}
