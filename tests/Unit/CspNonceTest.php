<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/*
 * Unit coverage for plugin_weathermap_csp_nonce().
 *
 * The helper has two branches that hinge on whether Cacti's global
 * CactiSecureHeaders class is present. A single PHP process can only ever
 * see the class as present or absent, never both, so the class-present
 * branch is exercised in an isolated child process that defines a
 * controlled double, rather than by mutating global state in this process
 * or asserting on the source text.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

test('csp nonce returns an empty string when CactiSecureHeaders is unavailable', function () {
	if (class_exists('CactiSecureHeaders')) {
		$this->markTestSkipped('CactiSecureHeaders is present in this runtime; the delegation path is covered by the isolated-process test.');
	}

	expect(plugin_weathermap_csp_nonce())->toBe('');
});

test('csp nonce helper is declared to return a string', function () {
	$ref = new ReflectionFunction('plugin_weathermap_csp_nonce');

	expect((string) $ref->getReturnType())->toBe('string');
});

test('csp nonce delegates to CactiSecureHeaders when the class is available', function () {
	$wrapper = realpath(__DIR__ . '/../../setup.php');
	expect($wrapper)->not->toBeFalse();

	// The wrapper's file may not be loadable in isolation (some plugins
	// include translised arrays at file scope), so hand the child the unit
	// bootstrap too; it defines the Cacti stubs the file needs at load time.
	$bootstrap = realpath(__DIR__ . '/../bootstrap-unit.php');

	// Run in a clean child process so the double never leaks into the rest
	// of the suite and the delegation branch is genuinely invoked.
	$script = <<<'CHILD'
<?php
error_reporting(0);
ini_set('display_errors', '0');

class CactiSecureHeaders {
	public static function getNonceAttribute(): string {
		return ' nonce="pest-controlled-double"';
	}
}

if (isset($argv[2]) && $argv[2] !== '' && is_file($argv[2])) {
	try { require $argv[2]; } catch (\Throwable $e) { /* no Cacti host locally */ }
}

require $argv[1];

echo "<<<" . plugin_weathermap_csp_nonce() . ">>>";
CHILD;

	$tmp = tempnam(sys_get_temp_dir(), 'csp_');
	expect($tmp)->not->toBeFalse();

	try {
		file_put_contents($tmp, $script);

		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($bootstrap === false ? '' : $bootstrap);
		$output  = shell_exec($command);

		expect($output)->not->toBeNull();

		preg_match('/<<<(.*)>>>/s', (string) $output, $matches);
		expect($matches)->toHaveKey(1);
		expect($matches[1])->toBe(' nonce="pest-controlled-double"');
	} finally {
		@unlink($tmp);
	}
});
