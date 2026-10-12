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

namespace WeathermapConfig;

/**
 * Acquire the lock shared by configuration writers and image deletion.
 *
 * @return resource|false The exclusive lock handle, or false on failure.
 */
function acquire() {
	$path = dirname(__DIR__) . '/configs/.weathermap-config.lock';
	$handle = @fopen($path, 'c+b');
	if (!$handle) $handle = @fopen($path, 'rb');
	if ($handle && flock($handle, LOCK_EX)) return $handle;
	if ($handle) fclose($handle);
	return false;
}

/**
 * Release a configuration lock.
 *
 * @param resource $handle The acquired lock.
 *
 * @return void
 */
function release($handle) {
	flock($handle, LOCK_UN);
	fclose($handle);
}

/**
 * Write all bytes, including when the stream accepts only a partial chunk.
 *
 * @param resource $stream The writable stream.
 * @param string $contents The bytes to write.
 *
 * @return bool Whether every byte was written.
 */
function writeAll($stream, $contents) {
	$offset = 0;
	$length = strlen($contents);
	while ($offset < $length) {
		$written = @fwrite($stream, substr($contents, $offset));
		if ($written === false || $written === 0) return false;
		$offset += $written;
	}
	return true;
}

/**
 * Replace a configuration only after a complete, flushed and closed local write.
 *
 * @param string $filename The destination configuration path.
 * @param string $contents The complete new configuration.
 *
 * @return bool Success; failures preserve the existing configuration.
 */
function atomicWrite($filename, $contents) {
	if (strpos($filename, '://') !== false) return false;
	if (is_link($filename)) $filename = realpath($filename);
	if ($filename === false || !is_dir(dirname($filename)) || !is_writable(dirname($filename))) return false;
	if (file_exists($filename) && (!is_file($filename) || !is_writable($filename))) return false;
	$lock = acquire();
	if (!$lock) return false;
	$temp = false;
	$stream = false;
	try {
		$temp = @tempnam(dirname($filename), '.wm-config-');
		if ($temp === false) return false;
		$stream = @fopen($temp, 'wb');
		if (!$stream) return false;
		$complete = writeAll($stream, $contents) && fflush($stream);
		$closed = fclose($stream);
		$stream = false;
		if (!$complete || !$closed) return false;
		$mode = file_exists($filename) ? fileperms($filename) & 0777 : 0666 & ~umask();
		if (PHP_OS_FAMILY !== 'Windows' && file_exists($filename) && !chgrp($temp, filegroup($filename))) return false;
		if (!chmod($temp, $mode)) return false;
		return @rename($temp, $filename);
	} finally {
		if (is_resource($stream)) fclose($stream);
		if ($temp !== false && file_exists($temp)) unlink($temp);
		release($lock);
	}
}
