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
 * Versioned names for the per-node PNG cache that WeatherMapNode::update_cache()
 * writes for the editor.
 *
 * Every writer and reader of these files must build the name here, so a later
 * change of format (for example a different hash) only bumps
 * WM_NODE_CACHE_VERSION and adds a case to wm_node_cache_name();
 * wm_node_cache_prune() then removes the files the old format left behind.
 *
 * Name formats:
 *   version 1     node_<32 hex>.png      (the original, unversioned name)
 *   version N>=2  node_v<N>_<hex>.png    (reserved for future formats)
 *
 * The 'v' cannot appear in a version 1 name, so the two shapes never overlap.
 */

/** Current node cache name format; version 1 is the original md5 name. */
const WM_NODE_CACHE_VERSION = 1;

/**
 * Hash a cache key with md5, or a same-length sha256 prefix where md5 is unavailable.
 *
 * Some FIPS builds disable md5(). The name is a cache key, not a security
 * control, so on such a host a 32-hex sha256 prefix keeps the version 1 shape;
 * no md5-named file can exist there to be orphaned.
 *
 * @param string    $data          Value to hash.
 * @param bool|null $md5_available Override for md5() availability; null detects it.
 *
 * @return string 32 lowercase hex characters.
 */
function wm_node_cache_hash(string $data, ?bool $md5_available = null): string {
	if ($md5_available ?? function_exists('md5')) {
		// Version 1 cache names are MD5 by contract; the hash only names a file.
		return md5($data); // NOSONAR
	}

	return substr(hash('sha256', $data), 0, 32);
}

/**
 * Build the file name (no directory) of a node's cached PNG.
 *
 * @param string $mapname  Map identifier passed to WeatherMapNode::update_cache().
 * @param string $nodename Node name.
 * @param int    $version  Name format version; defaults to WM_NODE_CACHE_VERSION.
 *
 * @return string Cache file name.
 *
 * @throws InvalidArgumentException For a version this build cannot produce.
 */
function wm_node_cache_name(string $mapname, string $nodename, int $version = WM_NODE_CACHE_VERSION): string {
	if ($version === 1) {
		return 'node_' . wm_node_cache_hash($mapname . '/' . $nodename) . '.png';
	}

	throw new InvalidArgumentException('Unsupported node cache name version: ' . $version);
}

/**
 * Identify the name format version of a file in the cache directory.
 *
 * @param string $file File name without directory.
 *
 * @return int|null Format version, or null when the file is not a node cache file.
 */
function wm_node_cache_version(string $file): ?int {
	if (preg_match('/^node_[0-9a-f]{32}\.png$/D', $file)) {
		return 1;
	}

	// Version 1 never carries a 'v' tag, so the tagged form starts at 2.
	if (preg_match('/^node_v([2-9]|[1-9][0-9]{1,8})_[0-9a-f]+\.png$/D', $file, $matches)) {
		return (int) $matches[1];
	}

	return null;
}

/**
 * Remove node cache files written in any format other than the current one.
 *
 * Only regular files directly inside $cachedir whose names match a node cache
 * format are considered. Symlinks, subdirectories and unrelated files are left
 * alone, and the directory is not searched recursively.
 *
 * @param string $cachedir Node cache directory.
 * @param int    $current  Format version to keep; defaults to WM_NODE_CACHE_VERSION.
 *
 * @return array{removed: list<string>, failed: list<string>} File names removed, and those that could not be.
 */
function wm_node_cache_prune(string $cachedir, int $current = WM_NODE_CACHE_VERSION): array {
	$result  = ['removed' => [], 'failed' => []];
	$entries = is_dir($cachedir) ? scandir($cachedir) : false;

	foreach (($entries !== false ? $entries : []) as $file) {
		$version = wm_node_cache_version($file);
		$path    = $cachedir . '/' . $file;

		if ($version === null || $version === $current || is_link($path) || !is_file($path)) {
			continue;
		}

		// unlink() removes a path swapped for a symlink after the check, never its target.
		if (@unlink($path)) { // nosemgrep: php.lang.security.unlink-use.unlink-use -- name matched against the plugin's own cache pattern
			$result['removed'][] = $file;
		} else {
			$result['failed'][] = $file;
		}
	}

	return $result;
}
