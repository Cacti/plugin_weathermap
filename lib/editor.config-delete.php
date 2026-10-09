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
	$path = $directory . '/' . $file;

	if (is_link($path) || !is_file($path) || dirname(realpath($path)) !== $directory) {
		return 'invalid';
	}

	if (db_fetch_cell_prepared('SELECT COUNT(*) FROM weathermap_maps WHERE configfile IN (?, ?)', [$file, $path])) {
		return 'used';
	}

	return unlink($path) ? 'deleted' : 'failed';
}
/**
 * Render an escaped delete button and filename confirmation.
 *
 * @param string $file
 *
 * @return string
 */
function wm_config_delete_button($file) {
	$label   = htmlspecialchars(__('Delete configuration file %s', $file, 'weathermap'), ENT_QUOTES, 'UTF-8');
	$confirm = htmlspecialchars(__('Are you sure you want to permanently delete the configuration file "%s"?', $file, 'weathermap'), ENT_QUOTES, 'UTF-8');
	$file    = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');

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
