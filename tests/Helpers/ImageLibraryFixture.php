<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Write a small raster in the requested format.
 *
 * @param string $path Destination file.
 * @param string $format png, gif or jpeg.
 *
 * @return void
 */
function wm_fixture_raster($path, $format = 'png') {
	$image = imagecreatetruecolor(4, 3);
	imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
	if ($format === 'gif') imagegif($image, $path);
	elseif ($format === 'jpeg') imagejpeg($image, $path);
	else imagepng($image, $path);
}
/**
 * Create a temporary plugin directory whose images/ libraries exist but are empty.
 *
 * @return string The temporary plugin root.
 */
function wm_fixture_plugin_root() {
	$root = sys_get_temp_dir() . '/wm-image-library-' . bin2hex(random_bytes(6));
	mkdir($root . '/images/objects', 0777, true);
	mkdir($root . '/images/backgrounds');
	return $root;
}
/**
 * Remove a fixture tree without following symlinks.
 *
 * @param string $root Directory created by wm_fixture_plugin_root().
 *
 * @return void
 */
function wm_fixture_remove($root) {
	if (!is_dir($root) || is_link($root)) return;
	$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($entries as $entry) {
		if (!$entry->isLink()) @chmod($entry->getPathname(), 0700);
		if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
	}
	rmdir($root);
}
