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

/** Editor image library management. */
function wm_asset_directory($kind) {
	if (!in_array($kind, ['objects', 'backgrounds'], true)) throw new InvalidArgumentException('Invalid image category.');
	return dirname(__DIR__) . '/images/' . $kind;
}
function wm_asset_image_path($kind, $name) {
	$dir = wm_asset_directory($kind);
	if (!is_string($name) || !preg_match('/^[A-Za-z0-9_.-]+\.(png|gif|jpe?g)$/iD', $name) || strpos($name, '..') !== false) throw new InvalidArgumentException('Invalid image filename.');
	$path = $dir . '/' . $name;
	if (is_link($dir) || is_link($path)) throw new InvalidArgumentException('Symbolic links cannot be managed here.');
	return $path;
}
function wm_asset_image_users($name) {
	$users = [];
	$queue = [];
	$root = dirname(__DIR__);
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/configs', FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) if ($file->isFile()) $queue[] = $file->getPathname();
	$seen = [];
	while ($queue) {
		$path = array_pop($queue);
		$real = realpath($path);
		if ($real === false || !is_readable($real)) throw new RuntimeException('Cannot check configuration references.');
		if (isset($seen[$real])) continue;
		$seen[$real] = true;
		if (count($seen) > 1000) throw new RuntimeException('Too many included configurations to check.');
		$text = file_get_contents($real);
		if ($text === false) throw new RuntimeException('Cannot check configuration references.');
		if (strpos($text, $name) !== false) $users[] = basename($real);
		if (preg_match_all('/^\s*INCLUDE\s+(.+)$/mi', $text, $matches)) foreach ($matches[1] as $include) {
			$include = trim($include);
			$queue[] = strpos($include, '/') === 0 ? $include : $root . '/' . $include;
		}
	}
	return $users;
}
function wm_asset_image_list() {
	$result = [];
	foreach (['objects', 'backgrounds'] as $kind) {
		$dir = wm_asset_directory($kind);
		foreach (scandir($dir) ?: [] as $name) {
			$path = $dir . '/' . $name;
			if (!is_file($path) || is_link($path) || !is_readable($path) || !preg_match('/\.(png|gif|jpe?g)$/iD', $name)) continue;
			$result[] = ['kind' => $kind, 'name' => $name, 'url' => 'images/' . $kind . '/' . rawurlencode($name), 'deletable' => (bool) preg_match('/^[A-Za-z0-9_.-]+\.(png|gif|jpe?g)$/iD', $name) && strpos($name, '..') === false];
		}
	}
	return $result;
}
function wm_asset_store_image($kind, $source, $original_name) {
	$dir = wm_asset_directory($kind);
	if (is_link($dir) || !is_writable($dir)) throw new RuntimeException('The image directory is not writable.');
	if (!is_file($source) || filesize($source) > 5 * 1024 * 1024) throw new InvalidArgumentException('Choose a PNG, JPG or GIF file up to 5 MB.');
	$size = @getimagesize($source);
	$formats = [IMAGETYPE_PNG => ['png', 'imagecreatefrompng', 'imagepng'], IMAGETYPE_JPEG => ['jpg', 'imagecreatefromjpeg', 'imagejpeg'], IMAGETYPE_GIF => ['gif', 'imagecreatefromgif', 'imagegif']];
	if (!$size || !isset($formats[$size[2]]) || $size[0] * $size[1] > 4000000) throw new InvalidArgumentException('Choose a PNG, JPG or GIF image up to 4 million pixels.');
	[$extension, $decode, $encode] = $formats[$size[2]];
	$image = @$decode($source);
	if (!$image) throw new InvalidArgumentException('The image could not be read.');
	if ($size[2] === IMAGETYPE_PNG) {
		imagealphablending($image, false);
		imagesavealpha($image, true);
	}
	$stem = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo(basename($original_name), PATHINFO_FILENAME));
	$name = substr($stem ?: 'image', 0, 60) . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
	$path = wm_asset_image_path($kind, $name);
	try {
		// Re-encode decoded pixels; never publish the original uploaded bytes.
		$written = $size[2] === IMAGETYPE_JPEG ? $encode($image, $path, 90) : $encode($image, $path);
		if (!$written) throw new RuntimeException('Could not save the image.');
		chmod($path, 0644);
	} finally { imagedestroy($image); }
	return $name;
}
