<?php
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

require_once __DIR__ . '/WeatherMapBase.class.php';

// The 'things on the map' class. More common code (mainly variables, actually)
/**
 * Properties shared by the nodes and links drawn on a map.
 */
class WeatherMapItem extends WeatherMapBase {
	// Declared for static analysis (dynamically populated at runtime)
	/** @var mixed */
	var $a;
	/** @var mixed */
	var $b;
	/** @var mixed */
	var $name;
	/** @var mixed */
	var $notestext;
	/** @var mixed */
	var $targets;
	/** @var mixed */
	var $x;
	/** @var mixed */
	var $y;

	/** @var mixed */
	var $owner;

	/** @var mixed */
	var $configline;
	/** @var mixed */
	var $infourl;
	/** @var mixed */
	var $overliburl;
	/** @var mixed */
	var $overlibwidth;
	/** @var mixed */
	var $overlibheight;
	/** @var mixed */
	var $overlibcaption;
	/** @var mixed */
	var $my_default;
	/** @var mixed */
	var $defined_in;
	/** @var mixed */
	var $config_override; // used by the editor to allow text-editing

	/**
	 * Return the item type name used in config output and debug messages.
	 *
	 * @return string
	 */
	function my_type() {
		return 'ITEM';
	}
}
