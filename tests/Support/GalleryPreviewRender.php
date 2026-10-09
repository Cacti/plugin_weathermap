<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                  |
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
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require dirname(__DIR__) . '/bootstrap-unit.php';
require dirname(__DIR__, 2) . '/setup.php';

if (($argv[1] ?? '') === 'native') {
	/**
	 * Emulate Cacti's attribute helper and record delegation in this fixture.
	 *
	 * @param string $value The unescaped attribute value.
	 *
	 * @return string The escaped HTML attribute value.
	 */
	function html_escape_attr($value) {
		$GLOBALS['fixture_attribute_calls'] = ($GLOBALS['fixture_attribute_calls'] ?? 0) + 1;

		return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
	}
}

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
	$hash                      = str_repeat((string) ($index + 1), 20);
	$GLOBALS['fixture_maps'][] = ['id' => $index + 1, 'group_id' => 7, 'filehash' => $hash, 'titlecache' => $kind . ' <map> "quoted" &quot; ` &', 'configfile' => $kind . '.conf'];

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
	$start = strpos($source, 'function weathermap_translate_id(');
	$end   = strpos($source, "\n/**", $start);
	eval(substr($source, $start, $end - $start));
	$start                                    = strpos($source, '$id = -1;');
	$end                                      = strpos($source, 'if ($id >= 0)', $start);
	$dispatch                                 = substr($source, $start, $end - $start);
	$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql, $params) {
		foreach ($GLOBALS['fixture_maps'] as $map) {
			if ($params === [$map['filehash'], $map['filehash']]) {
				return $map['id'];
			}
		}

		return false;
	};
	$renders = [];

	foreach ([false, false, true] as $change) {
		if ($change) {
			$file = $directory . '/output/' . str_repeat('1', 20) . '.thumb.png';
			touch($file, 1700000100);
			clearstatcache(true, $file);
		}
		ob_start();
		weathermap_thumbview(7);
		$html = ob_get_clean();
		preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $images);

		foreach ($images[1] as $url) {
			parse_str(parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY), $query);
			$GLOBALS['__test_request'] = $query;
			eval($dispatch);

			if (!in_array($id, [1, 3], true)) {
				throw new RuntimeException('Generated thumbnail URL must resolve through the real dispatcher.');
			}
		}
		$GLOBALS['__test_request'] = [];
		$renders[]                 = $html;
	}

	if (($argv[1] ?? '') === 'native' && ($GLOBALS['fixture_attribute_calls'] ?? 0) !== 24) {
		throw new RuntimeException('Gallery attributes must delegate to Cacti when available.');
	}
	print json_encode($renders, JSON_THROW_ON_ERROR);
} finally {
	foreach (glob($directory . '/output/*') as $file) {
		unlink($file);
	}
	rmdir($directory . '/output');
	rmdir($directory);
}
