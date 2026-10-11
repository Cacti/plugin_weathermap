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
 * Preserve absolute Unix and Windows registered paths before canonicalization.
 *
 * @param string $directory
 * @param string $file
 *
 * @return string
 */
function wm_config_registered_path($directory, $file) {
	$first = $file[0] ?? '';
	$drive_rooted = strlen($file) >= 3 && ctype_alpha($first) && $file[1] === ':' &&
		($file[2] === '/' || $file[2] === chr(92));

	if ($first === '/' || $first === chr(92) || $drive_rooted) {
		return $file;
	}

	return $directory . '/' . $file;
}

/**
 * Lock the config inode shared by registration and deletion.
 *
 * @param string $path
 *
 * @return resource|false
 */
function wm_config_lock($path) {
	$handle = @fopen($path, 'r');

	if ($handle === false) {
		return false;
	}

	if (!flock($handle, LOCK_EX | LOCK_NB)) {
		fclose($handle);
		return false;
	}

	clearstatcache(true, $path);
	$current = @stat($path);
	$opened = fstat($handle);

	if (is_link($path) || !is_file($path) || $current === false || $opened === false ||
		$current['dev'] !== $opened['dev'] || $current['ino'] !== $opened['ino']) {
		flock($handle, LOCK_UN);
		fclose($handle);
		return false;
	}

	return $handle;
}

/**
 * Delete a regular unused configuration file inside the configuration directory.
 *
 * @param string $directory
 * @param mixed  $file
 *
 * @return string
 */
function wm_config_delete($directory, $file) {
	$directory = realpath($directory);

	if (!is_string($file) || $file === '' || basename($file) !== $file ||
		strpos($file, chr(92)) !== false || preg_match('/[\x00-\x1f\x7f]/', $file) || substr($file, -5) !== '.conf' || !$directory) {
		return 'invalid';
	}
	$path     = $directory . '/' . $file;
	$resolved = realpath($path);

	if (is_link($path) || !is_file($path) || $resolved === false || dirname($resolved) !== $directory) {
		return 'invalid';
	}

	$lock = wm_config_lock($path);
	if ($lock === false) {
		return 'failed';
	}
	try {
		$usage = db_fetch_cell_prepared('SELECT COUNT(*) FROM weathermap_maps WHERE configfile IN (?, ?)', [$file, $path]);

		if ($usage === false || $usage === null) {
			return 'failed';
		}

		if ($usage) {
			return 'used';
		}

		// Existing registrations may use ./, absolute paths or other aliases.
		$maps = db_fetch_assoc_prepared('SELECT configfile FROM weathermap_maps', []);
		if (!is_array($maps)) {
			return 'failed';
		}
		foreach ($maps as $map) {
			$registered = $map['configfile'];
			$registered_path = realpath(wm_config_registered_path($directory, $registered));
			if ($registered_path === $resolved) {
				return 'used';
			}
		}

		return unlink($path) ? 'deleted' : 'failed';
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}
/**
 * Render an escaped delete button and filename confirmation.
 *
 * @param string $file
 *
 * @return string
 */
function wm_config_delete_button($file) {
	$label   = plugin_weathermap_escape_attr(__('Delete configuration file %s', $file, 'weathermap'));
	$confirm = plugin_weathermap_escape_attr(__('Are you sure you want to permanently delete the configuration file "%s"?', $file, 'weathermap'));
	$file    = plugin_weathermap_escape_attr($file);

	return '<button type="button" class="wm-config-delete" style="color:#d32f2f;background:transparent;border:0;padding:2px 5px;cursor:pointer" data-file="' . $file . '" data-confirm="' . $confirm . '" title="' . $label . '" aria-label="' . $label . '"><i class="fa fa-minus" aria-hidden="true"></i></button>';
}
/**
 * Bind configuration deletion to a confirmed CSRF-protected POST.
 *
 * @return void
 */
function wm_config_delete_script() {
	$nonce = function_exists('plugin_weathermap_csp_nonce') ? plugin_weathermap_csp_nonce() : '';
	print '<script' . ($nonce !== '' ? ' ' . $nonce : '') . '>';
	print <<<'HTML'
$(function() {
	$('.wm-config-delete').off('click.wmConfigDelete').on('click.wmConfigDelete', function() {
		if (!window.confirm(this.getAttribute('data-confirm'))) { return; }
		var form = document.createElement('form');
		form.method = 'post'; form.action = 'weathermap-cacti-plugin-mgmt.php';
		var values = {action: 'delete_config', file: this.getAttribute('data-file'), __csrf_magic: csrfMagicToken};
		Object.keys(values).forEach(function(name) {
			var input = document.createElement('input');
			input.type = 'hidden'; input.name = name; input.value = values[name];
			form.appendChild(input);
		});
		document.body.appendChild(form); form.submit();
	});
});
</script>
HTML;
}
