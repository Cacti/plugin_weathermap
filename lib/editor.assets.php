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
 * @param string|null $root Optional library root; defaults to the plugin directory.
 *
 * @return string The library directory.
 */
function wm_asset_directory($kind, $root = null) {
	if (!in_array($kind, ['objects', 'backgrounds'], true)) throw new InvalidArgumentException(__('Invalid image category.', 'weathermap'));
	$parent = ($root ?? dirname(__DIR__)) . '/images';
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
 * Canonicalise directive paths using portable forward-slash separators.
 *
 * @param string $path The image or include path.
 * @param string $root The base for relative paths.
 *
 * @return string The normalised absolute path.
 */
function wm_asset_normalize_path($path, $root) {
	$path = str_replace('\\', '/', $path);
	$root = str_replace('\\', '/', $root);
	if (substr($path, 0, 1) !== '/' && !preg_match('~^[A-Za-z]:/~', $path)) $path = rtrim($root, '/') . '/' . $path;
	$prefix = substr($path, 0, 2) === '//' ? '//' : '/';
	if (preg_match('~^([A-Za-z]:)/~', $path, $drive)) {
		$prefix = $drive[1] . '/';
		$path = substr($path, 3);
	}
	$parts = [];
	foreach (explode('/', $path) as $part) {
		if ($part === '' || $part === '.') continue;
		if ($part === '..') array_pop($parts); else $parts[] = $part;
	}
	return $prefix . implode('/', $parts);
}
/**
 * Make a renderer path absolute without collapsing symlinks or parent components.
 *
 * @param string $path Original directive path.
 * @param string $root Renderer working directory for relative paths.
 *
 * @return string Absolute path with filesystem resolution semantics preserved.
 */
function wm_asset_absolute_path($path, $root) {
	if (substr($path, 0, 1) === '/' || substr($path, 0, 1) === '\\' || preg_match('~^[A-Za-z]:[/\\\\]~', $path)) return $path;
	return rtrim($root, '/\\') . '/' . $path;
}
/**
 * Resolve a managed image identity even when the final file does not exist.
 *
 * @param mixed $path Original image path, retaining any renderer tokens.
 * @param string $root Plugin directory.
 *
 * @return string|null Library-relative identity, or null for an unmanaged path.
 */
function wm_asset_managed_path($path, $root) {
	if (!is_string($path) || $path === '') return null;
	clearstatcache(true);
	$parent = wm_asset_absolute_path($path, $root);
	$tail = [];
	while (($resolved = realpath($parent)) === false) {
		$next = dirname($parent);
		if ($next === $parent) return null;
		array_unshift($tail, basename($parent));
		$parent = $next;
	}
	$identity = wm_asset_normalize_path($resolved . ($tail ? '/' . implode('/', $tail) : ''), $root);
	$base = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
	if (substr($identity, 0, strlen($base)) !== $base) return null;
	$relative = substr($identity, strlen($base));
	return preg_match('#^images/(objects|backgrounds)/#', $relative) ? $relative : null;
}
/**
 * Read configuration files and includes once to index image references.
 *
 * @param array|null $directories Logical and physical parent directories to stabilise.
 *
 * @return array The reference patterns and source filenames.
 */
function wm_asset_image_reference_index(&$directories = null) {
	$directories = [];
	$references = [];
	$queue = [];
	$root = dirname(__DIR__);
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/configs', FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) if ($file->isFile() && strtolower($file->getExtension()) === 'conf') $queue[] = $file->getPathname();
	$seen = [];
	while ($queue) {
		$path = array_pop($queue);
		$real = realpath($path);
		if ($real === false || !is_file($real) || !is_readable($real)) throw new RuntimeException(__('Cannot check configuration references.', 'weathermap'));
		if (isset($seen[$real])) continue;
		$directories[dirname($path)] = true;
		$directories[dirname($real)] = true;
		$seen[$real] = true;
		if (cacti_count($seen) > 1000) throw new RuntimeException(__('Too many included configurations to check.', 'weathermap'));
		$text = file_get_contents($real);
		if ($text === false) throw new RuntimeException(__('Cannot check configuration references.', 'weathermap'));
		if (preg_match_all('/^[ \t]*(ICON|BACKGROUND)[ \t]+(?:[0-9]+[ \t]+[0-9]+[ \t]+)?([^\r\n]+?)[ \t]*\r?$/mi', $text, $directives, PREG_SET_ORDER)) {
			foreach ($directives as $directive) {
				$rawImage = trim($directive[2]);
				$image = wm_asset_normalize_path(wm_asset_managed_path($rawImage, $root) ?? $rawImage, $root);
				// Dynamic token values are unknown here: protect all possible paths.
				$pattern = implode('.*', array_map(function($part) { return preg_quote($part, '~'); }, preg_split('/\{[^}]+\}/', $image)));
				$references[] = ['pattern' => '~^' . $pattern . '$~iD', 'user' => basename($real)];
			}
		}
		if (preg_match_all('/^[ \t]*INCLUDE[ \t]+([^\r\n]+?)[ \t]*\r?$/mi', $text, $matches)) foreach ($matches[1] as $include) {
			if (trim($include) === '') continue;
			$include = wm_asset_absolute_path(trim($include), $root);
			// A writer can create a currently missing include while holding its parent lock.
			$directories[dirname($include)] = true;
			// INCLUDE is a literal filename in the renderer, including glob metacharacters.
			if (file_exists($include) || is_link($include)) $queue[] = $include;
		}
	}
	return $references;
}
/**
 * Lock every physical reference source before deciding whether deletion is safe.
 *
 * @param string $rootDirectory Already locked logical config directory.
 * @param array $locks Additional locks, released by the caller.
 *
 * @return array The stable reference index.
 */
function wm_asset_locked_reference_index($rootDirectory, &$locks) {
	$covered = [realpath($rootDirectory) => true];
	while (true) {
		$references = wm_asset_image_reference_index($directories);
		$added = false;
		foreach (array_keys($directories) as $directory) {
			$directory = realpath($directory);
			if ($directory === false) throw new RuntimeException(__('Could not lock map configurations.', 'weathermap'));
			if (isset($covered[$directory])) continue;
			$lock = \WeathermapConfig\acquire($directory, false);
			if (!$lock) throw new RuntimeException(__('Could not lock map configurations.', 'weathermap'));
			if ($lock) $locks[] = $lock;
			$covered[$directory] = true;
			if (count($covered) > 1000) throw new RuntimeException(__('Too many included configurations to check.', 'weathermap'));
			$added = true;
		}
		if (!$added) return $references;
	}
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
			if (preg_match($reference['pattern'], wm_asset_normalize_path('images/' . $library . '/' . $name, $root))) {
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
			if (!$size || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) continue;
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
	$temporary = @tempnam($dir, '.wm-upload-');
	$handle = $temporary !== false ? @fopen($temporary, 'wb') : false;
	if (!$handle) { if ($temporary !== false) unlink($temporary); imagedestroy($image); throw new RuntimeException(__('Could not reserve an image filename.', 'weathermap')); }
	try {
		// Keep encoded bytes outside the library until flush, close and permissions succeed.
		$encoded = $size[2] === IMAGETYPE_JPEG ? $encode($image, $handle, 90) : $encode($image, $handle);
		$written = $encoded && fflush($handle);
		$closed = fclose($handle);
		$handle = false;
		if (!$written || !$closed || !chmod($temporary, 0644) || !@link($temporary, $path)) throw new RuntimeException(__('Could not save the image.', 'weathermap'));
	} finally {
		if (is_resource($handle)) fclose($handle);
		imagedestroy($image);
		unlink($temporary);
	}
	return $name;
}
