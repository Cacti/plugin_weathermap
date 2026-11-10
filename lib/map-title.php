<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Flattens a map title so it cannot inject map-config directives.
 *
 * Map configs are line oriented, and a duplicated map has its title substituted
 * into the copied config file's TITLE line.  A title carrying a newline would
 * otherwise append arbitrary directives (such as NODE or INFOURL) to that
 * config, so every control character is collapsed to a space and the result is
 * trimmed.  A non-string value (for example an array from a `title[]=` request
 * variable) yields an empty string rather than a TypeError.
 *
 * @param  mixed  $title The raw title, from a request variable or the stored map.
 * @return string The single-line title, safe to write into a config file.
 */
function map_clean_title($title): string {
	/* The title arrives from a request variable by way of str_replace(), so an
	 * array valued parameter such as title[]= would otherwise reach the string
	 * type declaration here and raise a TypeError. */
	if (!is_string($title)) {
		return '';
	}

	return trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $title) ?? $title);
}
