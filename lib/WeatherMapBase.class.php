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

// Links, Nodes and the Map object inherit from this class ultimately.
// Just to make some common code common.
/**
 * Note and hint storage shared by the map, nodes and links.
 */
class WeatherMapBase {
	// Declared for static analysis (dynamically populated at runtime)
	/** @var mixed */
	var $name;

	/** @var array */
	var $notes = [];
	/** @var array */
	var $hints = [];
	/** @var mixed */
	var $inherit_fieldlist;

	/**
	 * Set a note, a value attached by a data source and available to {...} tokens.
	 *
	 * @param  string $name
	 * @param  mixed  $value
	 * @return void
	 */
	function add_note($name,$value) {
		wm_debug("Adding note $name='$value' to " . $this->name);

		$this->notes[$name] = $value;
	}

	/**
	 * Return a note, or null when it is not set.
	 *
	 * @param  string $name
	 * @return mixed
	 */
	function get_note($name) {
		if (isset($this->notes[$name])) {
			//	debug("Found note $name in ".$this->name." with value of ".$this->notes[$name].".\n");
			return ($this->notes[$name]);
		} else {
			//	debug("Looked for note $name in ".$this->name." which doesn't exist.\n");
			return (null);
		}
	}

	/**
	 * Set a hint, a SET value from the map config.
	 *
	 * @param  string $name
	 * @param  string $value
	 * @return void
	 */
	function add_hint($name,$value) {
		wm_debug("Adding hint $name='$value' to " . $this->name);

		$this->hints[$name] = $value;
		// warn("Adding hint $name to ".$this->my_type()."/".$this->name."\n");
	}

	/**
	 * Return a hint, or $default when it is not set.
	 *
	 * @param  string $name
	 * @param  mixed  $default Value returned when the hint is not set.
	 * @return mixed
	 */
	function get_hint($name, $default = null) {
		if (isset($this->hints[$name])) {
			//	debug("Found hint $name in ".$this->name." with value of ".$this->hints[$name].".\n");
			return ($this->hints[$name]);
		} else {
			//	debug("Looked for hint $name in ".$this->name." which doesn't exist.\n");
			return ($default);
		}
	}
}
