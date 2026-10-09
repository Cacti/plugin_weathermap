<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

require dirname(__DIR__) . '/bootstrap-unit.php';

set_error_handler(function ($severity, $message) {
	throw new RuntimeException($message);
});

/**
 * Provide an already permission-filtered gallery list to the renderer fixture.
 *
 * @param int $user  The fixture user.
 * @param int $group The optional group restriction.
 *
 * @return array The allowed fixture maps.
 */
function get_allowed_weathermaps($user, $group = -1) {
	return $GLOBALS['fixture_maps'];
}

/**
 * Skip unrelated tab rendering in this isolated gallery fixture.
 *
 * @param int $group The optional group restriction.
 *
 * @return void
 */
function weathermap_tabs($group) {
}

$directory = sys_get_temp_dir() . '/wm-gallery-' . bin2hex(random_bytes(8));
mkdir($directory . '/output', 0700, true);
$GLOBALS['config']['url_path']    = '/cacti/';
$GLOBALS['__test_config_options'] = ['weathermap_output_format' => 'png', 'weathermap_live_view' => '0'];
$_SESSION['sess_user_id']         = 23;
$GLOBALS['fixture_maps']          = [];

foreach (['thumb-only', 'full-only', 'both', 'missing'] as $index => $kind) {
	$hash                      = str_repeat((string) ($index + 1), 32);
	$GLOBALS['fixture_maps'][] = ['id' => $index + 1, 'group_id' => 7, 'filehash' => $hash, 'titlecache' => $kind . ' <map>', 'configfile' => $kind . '.conf'];

	if ($kind === 'thumb-only' || $kind === 'both') {
		file_put_contents($directory . '/output/' . $hash . '.thumb.png', 'fixture thumbnail');
		touch($directory . '/output/' . $hash . '.thumb.png', 1700000000);
	}

	if ($kind === 'full-only' || $kind === 'both') {
		file_put_contents($directory . '/output/' . $hash . '.png', 'fixture full map');
	}
}

try {
	$source   = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');
	$start    = strpos($source, 'function weathermap_thumbview(');
	$end      = strpos($source, "\n/**", $start);
	$function = str_replace('__DIR__', var_export($directory, true), substr($source, $start, $end - $start));
	eval($function);
	$renders = [];

	foreach ([false, false, true] as $change) {
		if ($change) {
			$file = $directory . '/output/' . str_repeat('1', 32) . '.thumb.png';
			touch($file, 1700000100);
			clearstatcache(true, $file);
		}
		ob_start();
		weathermap_thumbview(7);
		$renders[] = ob_get_clean();
	}
	print json_encode($renders, JSON_THROW_ON_ERROR);
} finally {
	foreach (glob($directory . '/output/*') as $file) {
		unlink($file);
	}
	rmdir($directory . '/output');
	rmdir($directory);
}
