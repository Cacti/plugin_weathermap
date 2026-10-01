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
 * Unit coverage for weathermap_prune_files(): tombstone/tests removal,
 * whitelist and .git protection, and logging of unaccounted-for entries.
 */

require_once __DIR__ . '/../../setup.php';

function weathermap_prune_fixture(array $manifest): string {
	$base   = sys_get_temp_dir() . '/weathermap-prune-' . uniqid();
	$plugin = $base . '/plugins/weathermap';

	mkdir($plugin . '/include', 0777, true);
	file_put_contents($plugin . '/include/old.php', "<?php\n");
	mkdir($plugin . '/tests/Unit', 0777, true);
	file_put_contents($plugin . '/tests/Unit/SomeTest.php', "<?php\n");
	mkdir($plugin . '/userdata', 0777, true);
	file_put_contents($plugin . '/userdata/keep.dat', 'keep');
	mkdir($plugin . '/includes', 0777, true);
	file_put_contents($plugin . '/INFO', "[info]\n");
	file_put_contents($plugin . '/setup.php', "<?php\n");
	file_put_contents($plugin . '/oldfile.php', "<?php\n");
	file_put_contents($plugin . '/stray.php', "<?php\n");
	file_put_contents($plugin . '/phpunit.xml', '');
	file_put_contents($plugin . '/.mdlrc', '');
	file_put_contents($plugin . '/.md_style.rb', '');
	mkdir($plugin . '/.git', 0777, true);
	file_put_contents($plugin . '/.git/config', '');
	file_put_contents($plugin . '/manifest.json', json_encode($manifest));

	return $base;
}

beforeEach(function () {
	$GLOBALS['__test_cacti_log'] = [];
});

it('removes tombstoned paths and the tests/ tree, keeps whitelist/.git/expected, logs strays', function () {
	$manifest = [
		'tombstones' => ['include/', 'oldfile.php', 'userdata/', 'gone.png'],
		'expected'   => ['INFO', 'setup.php', 'includes/', 'manifest.json'],
		'whitelist'  => ['userdata/'],
	];

	$base    = weathermap_prune_fixture($manifest);
	$plugin  = $base . '/plugins/weathermap';
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// Tombstone and the dev-only tests/ tree are gone.
	expect(is_dir($plugin . '/include'))->toBeFalse();
	expect(is_dir($plugin . '/tests'))->toBeFalse();
	expect(is_file($plugin . '/oldfile.php'))->toBeFalse();
	expect(is_file($plugin . '/phpunit.xml'))->toBeFalse();

	// Whitelisted user data, VCS metadata, and expected files are untouched.
	// (userdata/ is even listed as a tombstone, but the whitelist wins.)
	expect(is_file($plugin . '/userdata/keep.dat'))->toBeTrue();
	expect(is_dir($plugin . '/.git'))->toBeTrue();
	expect(is_file($plugin . '/INFO'))->toBeTrue();
	expect(is_dir($plugin . '/includes'))->toBeTrue();
	expect(is_file($plugin . '/.mdlrc'))->toBeTrue();
	expect(is_file($plugin . '/.md_style.rb'))->toBeTrue();

	// An unexpected, non-whitelisted stray is left in place but logged.
	expect(is_file($plugin . '/stray.php'))->toBeTrue();

	$logged = implode("\n", $GLOBALS['__test_cacti_log']);
	expect($logged)->toContain('stray.php');
	expect($logged)->not->toContain('userdata');
	expect($logged)->not->toContain('.git');
	expect($logged)->not->toContain('.mdlrc');
	expect($logged)->not->toContain('.md_style.rb');
});

it('is a safe no-op when the manifest is missing', function () {
	$base    = sys_get_temp_dir() . '/weathermap-prune-missing-' . uniqid();
	mkdir($base . '/plugins/weathermap', 0777, true);
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($GLOBALS['__test_cacti_log'])->toBe([]);
});

it('logs and skips pruning when the manifest is malformed', function () {
	$base   = sys_get_temp_dir() . '/weathermap-prune-bad-' . uniqid();
	$plugin = $base . '/plugins/weathermap';
	mkdir($plugin, 0777, true);
	file_put_contents($plugin . '/manifest.json', 'not json');
	mkdir($plugin . '/tests', 0777, true);
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// A malformed manifest must not delete anything.
	expect(is_dir($plugin . '/tests'))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not be parsed');
});

it('refuses to remove a tombstone that resolves outside the plugin directory', function () {
	$manifest = [
		'tombstones' => ['../escapee.txt'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base    = weathermap_prune_fixture($manifest);
	$plugin  = $base . '/plugins/weathermap';
	$outside = $base . '/plugins/escapee.txt';
	file_put_contents($outside, 'precious user data');
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The out-of-tree file is untouched and the refusal is logged.
	expect(is_file($outside))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('a traversal segment');
});

it('warns when a tombstoned path cannot be removed', function () {
	$manifest = [
		'tombstones' => ['locked/'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base   = weathermap_prune_fixture($manifest);
	$plugin = $base . '/plugins/weathermap';
	mkdir($plugin . '/locked/sub', 0777, true);
	file_put_contents($plugin . '/locked/sub/data', 'x');
	chmod($plugin . '/locked/sub', 0500); // read-only dir: its child cannot be unlinked
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	set_error_handler(static fn () => true); // swallow the expected unlink warning

	try {
		weathermap_prune_files();
	} finally {
		restore_error_handler();
		$GLOBALS['config']['base_path'] = $restore;
		@chmod($plugin . '/locked/sub', 0700);
	}

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not remove');
})->skip(function () {
	return function_exists('posix_getuid') && posix_getuid() === 0;
}, 'permission checks are bypassed for the root user');

it('refuses a tombstone that escapes through a symlinked directory', function () {
	$manifest = [
		'tombstones' => ['escdir/secret.txt'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base    = weathermap_prune_fixture($manifest);
	$plugin  = $base . '/plugins/weathermap';
	$outside = $base . '/outside';
	mkdir($outside, 0777, true);
	file_put_contents($outside . '/secret.txt', 'precious user data');
	@symlink($outside, $plugin . '/escdir');
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The out-of-tree file reached through the symlink is untouched and logged.
	expect(is_file($outside . '/secret.txt'))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('outside the plugin directory');
})->skip(function () {
	$probe = sys_get_temp_dir() . '/.prune-symlink-probe-' . uniqid();
	$ok = @symlink(__FILE__, $probe);
	@unlink($probe);

	return $ok === false;
}, 'symlinks are not supported on this filesystem');

it('protects a whitelisted file from a tombstone on its parent directory', function () {
	$manifest = [
		'tombstones' => ['userdata/'],
		'expected'   => ['manifest.json'],
		'whitelist'  => ['userdata/keep.dat'],
	];

	$base    = weathermap_prune_fixture($manifest);
	$plugin  = $base . '/plugins/weathermap';
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// A whitelisted file shields its parent directory from a tombstone.
	expect(is_file($plugin . '/userdata/keep.dat'))->toBeTrue();
});
