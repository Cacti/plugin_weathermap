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
 * Validates Weathermap configuration basenames without Cacti bootstrap.
 */
class MapnameValidator {
	/**
	 * Validate a map configuration basename that has already been decoded by PHP.
	 *
	 * @param mixed $filename The decoded request value or local basename.
	 *
	 * @return string The unchanged basename, or an empty string when invalid.
	 */
	public static function sanitize($filename) {
		if (!is_string($filename) || strlen($filename) <= 5 || !str_ends_with($filename, '.conf')) {
			return '';
		}

		// Reject path separators and controls instead of rewriting a different filename.
		if (strpbrk($filename, '/\\') !== false || preg_match('/[\x00-\x1f\x7f]/', $filename)) {
			return '';
		}

		return $filename;
	}

	/**
	 * Check whether a value is a valid map configuration basename.
	 *
	 * @param mixed $filename The decoded request value or local basename.
	 *
	 * @return bool True when the basename passes validation.
	 */
	public static function isValid($filename) {
		return self::sanitize($filename) !== '';
	}
}
