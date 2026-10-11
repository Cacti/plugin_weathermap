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
 * Resolve a managed image library, rejecting symlinked directories.
 *
 * @param string $kind The objects or backgrounds library.
 *
 * @return string The library directory.
 */
function wm_asset_directory($kind) {
	if (!in_array($kind, ['objects', 'backgrounds'], true)) throw new InvalidArgumentException(__('Invalid image category.', 'weathermap'));
	$parent = dirname(__DIR__) . '/images';
	$dir = $parent . '/' . $kind;
	if (is_link($parent) || is_link($dir)) throw new InvalidArgumentException(__('Symbolic links cannot be managed here.', 'weathermap'));
	return $dir;
}
/**
 * Validate a filename and resolve its non-symlinked library path.
 *
 * @param string $kind The library category.
 * @param mixed $name The filename to validate.
 *
 * @return string The validated image path.
 */
function wm_asset_image_path($kind, $name) {
	$dir = wm_asset_directory($kind);
	if (!is_string($name) || !preg_match('/^[A-Za-z0-9_.-]+\.(png|gif|jpe?g)$/iD', $name) || strpos($name, '..') !== false) throw new InvalidArgumentException(__('Invalid image filename.', 'weathermap'));
	$path = $dir . '/' . $name;
	if (is_link($dir) || is_link($path)) throw new InvalidArgumentException(__('Symbolic links cannot be managed here.', 'weathermap'));
	return $path;
}
/**
 * Read configuration files and includes once to index image references.
 *
 *
 * @return array The reference patterns and source filenames.
 */
function wm_asset_image_reference_index() {
	$references = [];
	$queue = [];
	$root = dirname(__DIR__);
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/configs', FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) if ($file->isFile()) $queue[] = $file->getPathname();
	$seen = [];
	while ($queue) {
		$path = array_pop($queue);
		$real = realpath($path);
		if ($real === false || !is_readable($real)) throw new RuntimeException(__('Cannot check configuration references.', 'weathermap'));
		if (isset($seen[$real])) continue;
		$seen[$real] = true;
		if (count($seen) > 1000) throw new RuntimeException(__('Too many included configurations to check.', 'weathermap'));
		$text = file_get_contents($real);
		if ($text === false) throw new RuntimeException(__('Cannot check configuration references.', 'weathermap'));
		if (preg_match_all('/^\s*(ICON|BACKGROUND)\s+(?:[0-9]+\s+[0-9]+\s+)?(.+?)\s*$/mi', $text, $directives, PREG_SET_ORDER)) {
			foreach ($directives as $directive) {
				$image = trim($directive[2]);
				$image = strpos($image, '/') === 0 ? $image : $root . '/' . $image;
				$parts = [];
				foreach (explode('/', $image) as $part) {
					if ($part === '' || $part === '.') continue;
					if ($part === '..') array_pop($parts); else $parts[] = $part;
				}
				$image = '/' . implode('/', $parts);
				// Dynamic token values are unknown here: protect all possible paths.
				$pattern = implode('.*', array_map(function($part) { return preg_quote($part, '~'); }, preg_split('/\{[^}]+\}/', $image)));
				$references[] = ['pattern' => '~^' . $pattern . '$~D', 'user' => basename($real)];
			}
		}
		if (preg_match_all('/^\s*INCLUDE\s+(.+)$/mi', $text, $matches)) foreach ($matches[1] as $include) {
			$include = trim($include);
			$include = strpos($include, '/') === 0 ? $include : $root . '/' . $include;
			$matches = glob($include);
			// The renderer tolerates absent optional includes; unreadable matches still fail closed.
			foreach ($matches ?: [] as $match) $queue[] = $match;
		}
	}
	return $references;
}
/**
 * Find map configurations referencing an image.
 *
 * @param string $name The image filename.
 * @param string|null $kind One library category, or both when null.
 * @param array|null $references A request-local reference index; null builds an index.
 *
 * @return string[] Unique configuration filenames using the image.
 */
function wm_asset_image_users($name, $kind = null, $references = null) {
	$references = $references ?? wm_asset_image_reference_index();
	$users = [];
	$root = dirname(__DIR__);
	foreach ($references as $reference) {
		foreach ($kind === null ? ['objects', 'backgrounds'] : [$kind] as $library) {
			if (preg_match($reference['pattern'], $root . '/images/' . $library . '/' . $name)) {
				$users[] = $reference['user'];
				break;
			}
		}
	}
	return array_values(array_unique($users));
}
/**
 * List image metadata using one shared configuration reference index.
 *
 * @param array|null $references A precomputed index, or null to read configurations.
 *
 * @return array The image dimensions, usage and deletion permissions.
 */
function wm_asset_image_list($references = null) {
	$result = [];
	$references = $references ?? wm_asset_image_reference_index();
	foreach (['objects', 'backgrounds'] as $kind) {
		$dir = wm_asset_directory($kind);
		foreach (scandir($dir) ?: [] as $name) {
			$path = $dir . '/' . $name;
			if (!is_file($path) || is_link($path) || !is_readable($path) || !preg_match('/\.(png|gif|jpe?g)$/iD', $name)) continue;
			$size = @getimagesize($path);
			$users = wm_asset_image_users($name, $kind, $references);
			$result[] = ['width' => $size ? $size[0] : null, 'height' => $size ? $size[1] : null, 'users' => $users, 'kind' => $kind, 'name' => $name, 'url' => 'images/' . $kind . '/' . rawurlencode($name), 'deletable' => !$users && (bool) preg_match('/^[A-Za-z0-9_.-]+\.(png|gif|jpe?g)$/iD', $name) && strpos($name, '..') === false];
		}
	}
	return $result;
}
/**
 * Validate and re-encode an upload under an exclusively reserved filename.
 *
 * @param string $kind The destination library category.
 * @param string $source The temporary source image path.
 * @param string $original_name The original name used to derive a safe filename.
 *
 * @return string The stored filename; invalid images or write failures raise exceptions.
 */
function wm_asset_store_image($kind, $source, $original_name) {
	$dir = wm_asset_directory($kind);
	if (is_link($dir) || !is_writable($dir)) throw new RuntimeException(__('The image directory is not writable.', 'weathermap'));
	if (!is_file($source) || filesize($source) > 5 * 1024 * 1024) throw new InvalidArgumentException(__('Choose a PNG, JPG or GIF file up to 5 MB.', 'weathermap'));
	$size = @getimagesize($source);
	$formats = [IMAGETYPE_PNG => ['png', 'imagecreatefrompng', 'imagepng'], IMAGETYPE_JPEG => ['jpg', 'imagecreatefromjpeg', 'imagejpeg'], IMAGETYPE_GIF => ['gif', 'imagecreatefromgif', 'imagegif']];
	if (!$size || !isset($formats[$size[2]]) || $size[0] * $size[1] > 4000000) throw new InvalidArgumentException(__('Choose a PNG, JPG or GIF image up to 4 million pixels.', 'weathermap'));
	[$extension, $decode, $encode] = $formats[$size[2]];
	$image = @$decode($source);
	if (!$image) throw new InvalidArgumentException(__('The image could not be read.', 'weathermap'));
	if ($size[2] === IMAGETYPE_PNG) {
		imagealphablending($image, false);
		imagesavealpha($image, true);
	}
	$stem = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo(basename($original_name), PATHINFO_FILENAME));
	$name = substr($stem ?: 'image', 0, 60) . '-' . bin2hex(random_bytes(16)) . '.' . $extension;
	$path = wm_asset_image_path($kind, $name);
	$handle = @fopen($path, 'x');
	if (!$handle) { imagedestroy($image); throw new RuntimeException(__('Could not reserve an image filename.', 'weathermap')); }
	$written = false;
	try {
		// Re-encode decoded pixels; never publish the original uploaded bytes.
		$encoded = $size[2] === IMAGETYPE_JPEG ? $encode($image, $handle, 90) : $encode($image, $handle);
		$written = $encoded && fflush($handle);
	} finally {
		$closed = fclose($handle);
		$written = $written && $closed;
		imagedestroy($image);
		if (!$written) unlink($path);
	}
	if (!$written) throw new RuntimeException(__('Could not save the image.', 'weathermap'));
	chmod($path, 0644);
	return $name;
}
