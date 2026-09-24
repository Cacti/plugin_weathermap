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
 * A simple port of the guts of Apache's mod_imap
 * - if you have an image control in a form, it's not really defined what happens to USEMAP
 *   attributes. They are allowed in HTML 4.0 and XHTML, but some testing shows that they're
 *   basically ignored. So you need to use server-side imagemaps if you want to have a form
 *   where you are choosing a verb from (for example) a <SELECT> and also specifying part of
 *   an image with an IMAGE control.
 */
class HTML_ImageMap_Area {
	var $href;
	var $name;
	var $id;
	var $alt;
	var $z;
	var $extrahtml;

	/**
	 * Builds the shared HTML attribute fragment (id/class/href/nohref and
	 * any extra HTML) common to every &lt;area&gt; shape type. Called from each
	 * subclass's asHTML() method.
	 *
	 * @return string The shared HTML attribute fragment.
	 */
	function common_html() {
		$h = '';

		if ($this->name != '') {
			// $h .= " alt=\"".$this->name."\" ";
			$h .= 'id="' . $this->name . '" ';
		}

		if (strpos($this->name, 'LINK') !== false) {
			$h .= 'class="link" ';
		} elseif (strpos($this->name, 'NODE') !== false) {
			$h .= 'class="node" ';
		}

		if ($this->href != '') {
			$h .= 'href="' . $this->href . '" ';
		} else {
			$h .= 'nohref ';
		}

		if ($this->extrahtml != '') {
			$h .= $this->extrahtml . ' ';
		}

		return $h;
	}
}

class HTML_ImageMap_Area_Polygon extends HTML_ImageMap_Area {
	var $points = [];
	var $minx;
	var $maxx;
	var $miny;
	var $maxy;
	var $npoints;

	function asHTML() {
		foreach ($this->points as $point) {
			$flatpoints[] = $point[0];
			$flatpoints[] = $point[1];
		}

		$coordstring = join(',', $flatpoints);

		return "\t\t\t<area " . $this->common_html() . "shape='poly' coords='" . $coordstring . "' />";
	}

	function asJSON() {
		$json = "{ 'shape':'poly', 'npoints':" .
			$this->npoints . ", \"name\":'" .
			$this->name . "',";

		$xlist = '';
		$ylist = '';

		foreach ($this->points as $point) {
			$xlist .= $point[0] . ',';
			$ylist .= $point[1] . ',';
		}

		$xlist = rtrim($xlist,', ');
		$ylist = rtrim($ylist,', ');
		$json .= " 'x': [ $xlist ], 'y':[ $ylist ], 'minx': " .
			$this->minx . ", 'miny': " .
			$this->miny . ", 'maxx':" .
			$this->maxx . ", 'maxy':" . $this->maxy . '}';

		return ($json);
	}

	function hitTest($x,$y) {
		$c = 0;

		// do the easy bounding-box test first.
		if (($x < $this->minx) || ($x > $this->maxx) || ($y < $this->miny) || ($y > $this->maxy)) {
			return false;
		}

		// Algorithm from
		// http://www.ecse.rpi.edu/Homepages/wrf/Research/Short_Notes/pnpoly.html#The%20C%20Code
		for ($i = 0, $j = $this->npoints - 1; $i < $this->npoints; $j = $i++) {
			// print "Checking: $i, $j\n";
			$x1 = $this->points[$i][0];
			$y1 = $this->points[$i][1];
			$x2 = $this->points[$j][0];
			$y2 = $this->points[$j][1];

			//  print "($x,$y) vs ($x1,$y1)-($x2,$y2)\n";

			if (((($y1 <= $y) && ($y < $y2)) || (($y2 <= $y) && ($y < $y1))) && ($x < ($x2 - $x1) * ($y - $y1) / ($y2 - $y1) + $x1)) {
				$c = !$c;
			}
		}

		return ($c);
	}

	/**
	 * Constructs a polygon area from a flat list of X/Y coordinate pairs,
	 * computing its point list and bounding box.
	 *
	 * @param string $name   The area's name/id.
	 * @param string $href   The area's link URL.
	 * @param array  $coords A one-element array whose first element is a
	 *                       flat [x1, y1, x2, y2, ...] coordinate list.
	 */
	function __construct($name = '', $href = '', $coords = '') {
		$c = $coords[0];

		$this->name    = $name;
		$this->href    = $href;
		$this->npoints = count($c) / 2;

		if (intval($this->npoints) != ($this->npoints)) {
			die('Odd number of points!');
		}

		for ($i = 0; $i < count($c); $i += 2) {
			$x = intval(round($c[$i]));
			$y = intval(round($c[$i + 1]));

			$point = [$x, $y];

			$xlist[] = $x; // these two are used to get the bounding box in a moment
			$ylist[] = $y;

			$this->points[] = $point;
		}

		$this->minx = min($xlist);
		$this->maxx = max($xlist);
		$this->miny = min($ylist);
		$this->maxy = max($ylist);

		//        print $this->asHTML()."\n";
	}
}

class HTML_ImageMap_Area_Rectangle extends HTML_ImageMap_Area {
	var $x1;
	var $x2;
	var $y1;
	var $y2;

	/**
	 * Constructs a rectangular area from a two-corner coordinate list,
	 * normalizing the corners so (x1,y1) is always top-left.
	 *
	 * @param string $name   The area's name/id.
	 * @param string $href   The area's link URL.
	 * @param array  $coords A one-element array whose first element is
	 *                       [x1, y1, x2, y2].
	 */
	function __construct($name = '', $href = '', $coords = '') {
		$c = $coords[0];

		$x1 = (int) round((float) $c[0]);
		$y1 = (int) round((float) $c[1]);
		$x2 = (int) round((float) $c[2]);
		$y2 = (int) round((float) $c[3]);

		// sort the points, so that the first is the top-left
		if ($x1 > $x2) {
			$this->x1 = $x2;
			$this->x2 = $x1;
		} else {
			$this->x1 = $x1;
			$this->x2 = $x2;
		}

		if ($y1 > $y2) {
			$this->y1 = $y2;
			$this->y2 = $y1;
		} else {
			$this->y1 = $y1;
			$this->y2 = $y2;
		}

		$this->name = $name;
		$this->href = $href;
	}

	/**
	 * Tests whether a point falls inside this rectangle's bounds. Called
	 * from HTML_ImageMap::hitTest() when checking click coordinates
	 * against each defined area.
	 *
	 * @param int $x The X coordinate to test.
	 * @param int $y The Y coordinate to test.
	 *
	 * @return bool True if the point falls inside the rectangle, false
	 *             otherwise.
	 */
	function hitTest($x, $y) {
		return ($x > $this->x1 && $x < $this->x2 && $y > $this->y1 && $y < $this->y2);
	}

	/**
	 * Renders this rectangle area as an HTML &lt;area shape='rect'&gt;
	 * element. Called from HTML_ImageMap::asHTML() for each rectangular
	 * area.
	 *
	 * @return string The rendered &lt;area&gt; HTML tag.
	 */
	function asHTML() {
		$coordstring = join(',', [$this->x1, $this->y1, $this->x2, $this->y2]);

		return "\t\t\t<area " . $this->common_html() . 'shape="rect" coords="' . $coordstring . '" />';
	}

	/**
	 * Renders this rectangle area as a JSON object (shape, corners, and
	 * name). Called from HTML_ImageMap::asJSON() for each rectangular
	 * area.
	 *
	 * @return string The rendered JSON object text.
	 */
	function asJSON() {
		$json = "{ 'shape':'rect', ";

		$json .= " 'x1':" . $this->x1 .
			", 'y1':" . $this->y1 .
			", 'x2':" . $this->x2 .
			", 'y2':" . $this->y2 .
			", 'name':'" . $this->name . "'}";

		return ($json);
	}
}

class HTML_ImageMap_Area_Circle extends HTML_ImageMap_Area {
	var $centx,$centy, $edgex, $edgey;

	/**
	 * Renders this circular area as an HTML &lt;area shape='circle'&gt;
	 * element. Called from HTML_ImageMap::asHTML() for each circular
	 * area.
	 *
	 * @return string The rendered &lt;area&gt; HTML tag.
	 */
	function asHTML() {
		$coordstring = join(',', [$this->centx, $this->centy, $this->edgex, $this->edgey]);

		return "\t\t\t<area " . $this->common_html() . " shape='circle' coords='" . $coordstring . "' />";
	}

	/**
	 * Tests whether a point falls inside this circle, by comparing
	 * squared distances to avoid a sqrt() call. Called from
	 * HTML_ImageMap::hitTest() when checking click coordinates against
	 * each defined area.
	 *
	 * @param int $x The X coordinate to test.
	 * @param int $y The Y coordinate to test.
	 *
	 * @return bool True if the point falls inside the circle, false
	 *             otherwise.
	 */
	function hitTest($x,$y) {
		$radius1 = ($this->edgey - $this->centy) * ($this->edgey - $this->centy)
			+ ($this->edgex - $this->centx) * ($this->edgex - $this->centx);

		$radius2 = ($this->centy - $y) * ($this->centy - $y)
			+ ($this->centx - $x) * ($this->centx - $x);

		return ($radius2 <= $radius1);
	}

	/**
	 * Constructs a circular area from a center point and edge point
	 * coordinate list.
	 *
	 * @param string $name   The area's name/id.
	 * @param string $href   The area's link URL.
	 * @param array  $coords A one-element array whose first element is
	 *                       [centerX, centerY, edgeX, edgeY].
	 */
	function __construct($name = '', $href = '', $coords = '') {
		$c = $coords[0];

		$this->name  = $name;
		$this->href  = $href;

		$this->centx = intval(round($c[0]));
		$this->centy = intval(round($c[1]));
		$this->edgex = intval(round($c[2]));
		$this->edgey = intval(round($c[3]));
	}
}

class HTML_ImageMap {
	var $shapes;
	var $nshapes;
	var $name;

	/**
	 * Constructs an empty image map with the given name.
	 *
	 * @param string $name The image map's name/id.
	 */
	function __construct($name = '') {
		$this->Reset();
		$this->name = $name;
	}

	/**
	 * Clears all shapes and resets the map's name.
	 *
	 * @return void
	 */
	function Reset() {
		$this->shapes  = [];
		$this->nshapes = 0;
		$this->name    = '';
	}

	// add an element to the map - takes an array with the info, in a similar way to HTML_QuickForm
	/**
	 * Adds an area shape to the map, either an already-constructed
	 * HTML_ImageMap_Area subclass instance, or a shape type name plus
	 * constructor arguments (name, href, coords) to build one.
	 *
	 * @param object|string $element Either an HTML_ImageMap_Area
	 *                               subclass instance, or a shape suffix
	 *                               ('Polygon'/'Rectangle'/'Circle') when
	 *                               constructing a new shape from the
	 *                               remaining variadic arguments.
	 *
	 * @return void
	 */
	function addArea($element) {
		if (is_object($element) && is_subclass_of($element, 'html_imagemap_area')) {
			$elementObject = &$element;
		} else {
			$args          = func_get_args();
			$className     = 'HTML_ImageMap_Area_' . $element;
			$elementObject = new $className($args[1], $args[2], array_slice($args, 3));
		}

		$this->shapes[] = &$elementObject;
		$this->nshapes++;
		//      print $this->nshapes." shapes\n";
	}

	// do a hit-test based on the current map
	// - can be limited to only match elements whose names match the filter
	//   (e.g. pick a building, in a campus map)
	/**
	 * Finds the first area shape (in reverse/top-most order) whose
	 * hitTest() matches the given point, optionally restricted to areas
	 * whose name matches a filter substring. Called from map click-
	 * handling code to resolve which area a user clicked.
	 *
	 * @param int    $x          The X coordinate to test.
	 * @param int    $y          The Y coordinate to test.
	 * @param string $namefilter Optional substring the matched area's
	 *                          name must contain.
	 *
	 * @return HTML_ImageMap_Area|false The matching area object, or false
	 *                                 if none matched.
	 */
	function hitTest($x, $y, $namefilter = '') {
		$preg = '/' . $namefilter . '/';

		foreach ($this->shapes as $shape) {
			if ($shape->hitTest($x, $y)) {
				if (($namefilter == '') || (preg_match($preg,$shape->name))) {
					return $shape->name;
				}
			}
		}

		return false;
	}

	// update a property on all elements in the map that match a name
	// (use it for retro-actively adding in link information to a pre-built geometry before generating HTML)
	// returns the number of elements that were matched/changed
	/**
	 * Updates a property ('href' or 'extrahtml') on every shape whose
	 * name exactly matches (or, if $where is empty, on every shape).
	 * Called to retro-actively add link/HTML info to a pre-built
	 * geometry before generating output.
	 *
	 * @param string $which The property to update: 'href' or
	 *                      'extrahtml'.
	 * @param string $what  The new value to set.
	 * @param string $where The exact shape name to match, or '' to match
	 *                      all shapes.
	 *
	 * @return int The number of shapes updated.
	 */
	function setProp($which, $what, $where) {
		$count = 0;

		for ($i = 0; $i < count($this->shapes); $i++) {
			// this USED to be a substring match, but that broke some things
			// and wasn't actually used as one anywhere.
			if (($where == '') || ($this->shapes[$i]->name == $where)) {
				switch($which) {
					case 'href':
						$this->shapes[$i]->href = $what;

						break;
					case 'extrahtml':
						$this->shapes[$i]->extrahtml = $what;
						// print "IMAGEMAP: Found $where and adding $which\n";

						break;
				}

				$count++;
			}
		}

		return $count;
	}

	// update a property on all elements in the map that match a name as a substring
	// (use it for retro-actively adding in link information to a pre-built geometry before generating HTML)
	// returns the number of elements that were matched/changed
	/**
	 * Updates a property ('href' or 'extrahtml') on every shape whose
	 * name contains a given substring (or, if $where is empty, on every
	 * shape). Called to retro-actively add link/HTML info to a
	 * pre-built geometry before generating output.
	 *
	 * @param string $which The property to update: 'href' or
	 *                      'extrahtml'.
	 * @param string $what  The new value to set.
	 * @param string $where A substring to match against each shape's
	 *                      name, or '' to match all shapes.
	 *
	 * @return int The number of shapes updated.
	 */
	function setPropSub($which, $what, $where) {
		$count = 0;

		for ($i = 0; $i < count($this->shapes); $i++) {
			if (($where == '') || (strstr($this->shapes[$i]->name, $where) != false)) {
				switch($which) {
					case 'href':
						$this->shapes[$i]->href = $what;

						break;
					case 'extrahtml':
						$this->shapes[$i]->extrahtml = $what;

						break;
				}

				$count++;
			}
		}

		return $count;
	}

	// Return the imagemap as an HTML client-side imagemap for inclusion in a page
	/**
	 * Renders the full image map as an HTML client-side &lt;map&gt; element
	 * containing every shape's &lt;area&gt; tag. Called wherever the complete
	 * map's HTML output is needed.
	 *
	 * @return string The rendered &lt;map&gt; HTML block.
	 */
	function asHTML() {
		$html = '<map';

		if ($this->name != '') {
			$html .= " name='" . $this->name . "'";
		}

		$html .= '>' . PHP_EOL;

		foreach ($this->shapes as $shape) {
			$html .= $shape->asHTML() . PHP_EOL;
			$html .= PHP_EOL;
		}

		$html .= "\t\t\t</map>" . PHP_EOL;

		return $html;
	}

	/**
	 * Renders a filtered subset of the map's shapes as a comma-separated
	 * list of JSON objects, optionally in reverse order. Called when
	 * generating client-side JSON hit-test data for a subset of shapes
	 * (e.g. just links, or just nodes).
	 *
	 * @param string $namefilter   A regex pattern to match against each
	 *                            shape's name; '' matches all shapes.
	 * @param bool   $reverseorder Whether to prepend (true) or append
	 *                            (false) each matching shape's JSON.
	 *
	 * @return string The concatenated JSON objects, comma-separated.
	 */
	function subJSON($namefilter = '',$reverseorder = false) {
		$json = '';

		$preg = '/' . $namefilter . '/';

		foreach ($this->shapes as $shape) {
			if (($namefilter == '') || (preg_match($preg,$shape->name))) {
				if ($reverseorder) {
					$json  = $shape->asJSON() . ",\n" . $json;
				} else {
					$json .= $shape->asJSON() . ",\n";
				}
			}
		}

		$json  = rtrim($json, "\n, ");
		$json .= PHP_EOL;

		return $json;
	}

	// return HTML for a subset of the map, specified by the filter string
	// (suppose you want some partof your UI to have precedence over another part
	//  - the imagemap is checked from top-to-bottom in the HTML)
	// - skipnolinks -> in normal HTML output, we don't need areas for things with no href
	// return HTML for a subset of the map, specified by the filter string
	// (suppose you want some partof your UI to have precedence over another part
	//  - the imagemap is checked from top-to-bottom in the HTML)
	// - skipnolinks -> in normal HTML output, we don't need areas for things with no href
	/**
	 * Renders a filtered subset of the map's shapes as HTML &lt;area&gt; tags,
	 * optionally in reverse order (to control z-order/precedence in the
	 * generated HTML) and optionally skipping shapes with no link/extra
	 * HTML. Called when generating HTML output for a subset of shapes
	 * (e.g. just links, or just nodes).
	 *
	 * @param string $namefilter   A substring to match against each
	 *                            shape's name; '' matches all shapes.
	 * @param bool   $reverseorder Whether to prepend (true) or append
	 *                            (false) each matching shape's HTML.
	 * @param bool   $skipnolinks  Whether to omit shapes with no href and
	 *                            no extra HTML.
	 *
	 * @return string The concatenated &lt;area&gt; HTML tags.
	 */
	function subHTML($namefilter = '',$reverseorder = false, $skipnolinks = false) {
		$html = '';
		$preg = '/' . $namefilter . '/';

		foreach ($this->shapes as $shape) {
			// if ( ($namefilter == '') || ( preg_match($preg,$shape->name) ))
			if (($namefilter == '') || (strstr($shape->name, $namefilter) !== false)) {
				if (!$skipnolinks || $shape->href != '' || $shape->extrahtml != '') {
					if ($reverseorder) {
						$html  = $shape->asHTML() . "\n" . $html;
					} else {
						$html .= $shape->asHTML() . "\n";
					}
				}
			}
		}

		return $html;
	}
}
