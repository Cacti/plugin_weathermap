<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2024 The Cacti Group, Inc.                           |
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
 * Utility class for 2D vectors.
 * Mostly used in the VIA calculations
 */
class WMVector {
	public $dx;
	public $dy;

	public function __construct($dx = 0, $dy = 0) {
		$this->dx = $dx;
		$this->dy = $dy;
	}

	public function flip() {
		$this->dx = - $this->dx;
		$this->dy = - $this->dy;
	}

	public function getAngle() {
		return rad2deg(atan2((-$this->dy), ($this->dx)));
	}

	public function getSlope() {
		if ($this->dx == 0) {
			// special case - if slope is infinite, fudge it to be REALLY BIG instead. Close enough for TV.
			wm_debug("Slope is infinite.\n");

			return 1e10;
		}

		return ($this->dy / $this->dx);
	}

	/**
	 * @param float $angle
	 */
	public function rotate($angle) {
		$points = array();
		$points[0] = $this->dx;
		$points[1] = $this->dy;

		rotateAboutPoint($points, 0, 0, $angle);

		$this->dx = $points[0];
		$this->dy = $points[1];
	}

	/**
	 * @return WMVector
	 */
	public function getNormal() {
		$len = $this->length();

		if ($len==0) {
			return new WMVector(0, 0);
		}

		return new WMVector($this->dy / $len, -$this->dx / $len);
	}

	/**
	 * Turn vector into unit-vector
	 */
	public function normalise() {
		$len = $this->length();

		if ($len > 0 && $len != 1) {
			$this->dx = $this->dx / $len;
			$this->dy = $this->dy / $len;
		}
	}

	/**
	 * Calculate the square of the vector length.
	 * Save calculating a square-root if all you need to do is compare lengths
	 *
	 * @return float
	 */
	public function squaredLength() {
		if (($this->dx == 0) && ($this->dy == 0)) {
			return 0;
		}

		$squaredLength = ($this->dx) * ($this->dx) + ($this->dy) * ($this->dy);

		return $squaredLength;
	}

	/**
	 * @return float
	 */
	public function length() {
		if ($this->dx==0 && $this->dy==0) {
			return 0;
		}

		return (sqrt($this->squaredLength()));
	}

	public function asString() {
		return $this->__toString();
	}

    /**
	 * @return string
	 */
	public function __toString() {
		return sprintf("[%f,%f]", $this->dx, $this->dy);
	}
}

