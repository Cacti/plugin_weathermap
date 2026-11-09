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

// template class for data sources. All data sources extend this class.
// I really wish PHP4 would just die overnight
class WeatherMapDataSource {
	// Cacti Integration
	/** @var mixed */
	var $local_data_id;

	/** @var array */
	var $down_cache = [];

	// Initialize - called after config has been read (so SETs are processed)
	// but just before ReadData. Used to allow plugins to verify their dependencies
	// (if any) and bow out gracefully. Return false to signal that the plugin is not
	// in a fit state to run at the moment.
	/**
	 * @param WeatherMap $map
	 * @return bool
	 */
	function Init(&$map) {
		return true;
	}

	// called with the TARGET string. Returns true or false, depending on whether it wants to handle this TARGET
	// called by map->ReadData()
	/**
	 * @param string $targetstring
	 * @return bool
	 */
	function Recognise($targetstring) {
		return false;
	}

	// the actual ReadData
	//   returns an array of two values (in,out). -1,-1 if it couldn't get valid data
	//   configline is passed in, to allow for better error messages
	//   itemtype and itemname may be used as part of the target (e.g. for TSV source line)
	// function ReadData($targetstring, $configline, $itemtype, $itemname, $map) { return ([-1,-1]); }
	/**
	 * @param string $targetstring
	 * @param WeatherMap $map
	 * @param WeatherMapItem $item
	 * @return mixed
	 */
	function ReadData($targetstring, &$map, &$item) {
		return ([-1, -1, 0]);
	}

	// pre-register a target + context, to allow a plugin to batch up queries to a slow database, or snmp for example
	/**
	 * @param string $targetstring
	 * @param WeatherMap $map
	 * @param WeatherMapItem $item
	 * @return void
	 */
	function Register($targetstring, &$map, &$item) {
	}

	// called before ReadData, to allow plugins to DO the prefetch of targets known from Register
	/**
	 * @return void
	 */
	function Prefetch() {
	}
}
