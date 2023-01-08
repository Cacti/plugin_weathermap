<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2023 The Cacti Group, Inc.                           |
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

function getTriangleArea($point1, $point2, $point3) {
	$area = abs($point1->x * ($point2->y - $point3->y)
		+ $point2->x * ($point3->y - $point1->y)
		+ $point3->x * ($point1->y - $point2->y)) / 2.0;

	return $area;
}

class WMLineSegment {
	public $point1;
	public $point2;
	public $vector;

	public function __construct($p1, $p2) {
		$this->point1 = $p1;
		$this->point2 = $p2;

		$this->vector = $p1->vectorToPoint($p2);
	}

	public function __toString() {
		return sprintf("{%s--%s}", $this->point1, $this->point2);
	}
}

/**
 * rotate a list of points around cx,cy by an angle in radians, IN PLACE
 *
 * TODO: This should be using WMPoints! (And should be a method of WMPoint)
 *
 * @param $points array of ordinates (x,y,x,y,x,y...)
 * @param $centre_x centre of rotation, X coordinate
 * @param $centre_y centre of rotation, Y coordinate
 * @param int $angle angle in radians
 */
function rotateAboutPoint(&$points, $centre_x, $centre_y, $angle = 0) {
	$nPoints = count($points) / 2;

	for ($i = 0; $i < $nPoints; $i ++) {
		$delta_x = $points[$i * 2] - $centre_x;
		$delta_y = $points[$i * 2 + 1] - $centre_y;
		$rotated_x = $delta_x * cos($angle) - $delta_y * sin($angle);
		$rotated_y = $delta_y * cos($angle) + $delta_x * sin($angle);

		$points[$i * 2] = $rotated_x + $centre_x;
		$points[$i * 2 + 1] = $rotated_y + $centre_y;
	}
}

