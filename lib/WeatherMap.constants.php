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

/*
 * Each define is guarded so a test or caller that already declared one can
 * still load the engine.
 */

/**
 * Channel indices into the in/out value pairs, and the channel count.
 */
if (!defined('IN')) {
	define('IN', 0);
}

if (!defined('OUT')) {
	define('OUT', 1);
}

if (!defined('WMCHANNELS')) {
	define('WMCHANNELS', 2);
}

/**
 * Value types in the WriteConfig() field tables: literal text or a colour.
 */
if (!defined('CONFIG_TYPE_LITERAL')) {
	define('CONFIG_TYPE_LITERAL', 0);
}

if (!defined('CONFIG_TYPE_COLOR')) {
	define('CONFIG_TYPE_COLOR', 1);
}

/**
 * Default link label format strings for bits, unformatted values and percentages.
 */
if (!defined('FMT_BITS_IN')) {
	define('FMT_BITS_IN', '{link:this:bandwidth_in:%2k}');
}

if (!defined('FMT_BITS_OUT')) {
	define('FMT_BITS_OUT', '{link:this:bandwidth_out:%2k}');
}

if (!defined('FMT_UNFORM_IN')) {
	define('FMT_UNFORM_IN', '{link:this:bandwidth_in}');
}

if (!defined('FMT_UNFORM_OUT')) {
	define('FMT_UNFORM_OUT', '{link:this:bandwidth_out}');
}

if (!defined('FMT_PERC_IN')) {
	define('FMT_PERC_IN', '{link:this:inpercent:%.2f}%');
}

if (!defined('FMT_PERC_OUT')) {
	define('FMT_PERC_OUT', '{link:this:outpercent:%.2f}%');
}

/**
 * Field indices within a link spine point: x, y and the distance along the spine.
 */
if (!defined('X')) {
	define('X', 0);
}

if (!defined('Y')) {
	define('Y', 1);
}

if (!defined('DISTANCE')) {
	define('DISTANCE', 2);
}
