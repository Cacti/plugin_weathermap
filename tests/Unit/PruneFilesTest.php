<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_weathermap_prune_files(): tombstone/tests removal,
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
		plugin_weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// Tombstone and the dev-only tests/ tree are gone.
	expect(is_dir($plugin . '/include'))->toBeFalse();
	expect(is_dir($plugin . '/tests'))->toBeFalse();
	expect(is_file($plugin . '/oldfile.php'))->toBeFalse();

	// Whitelisted user data, VCS metadata, and expected files are untouched.
	// (userdata/ is even listed as a tombstone, but the whitelist wins.)
	expect(is_file($plugin . '/userdata/keep.dat'))->toBeTrue();
	expect(is_dir($plugin . '/.git'))->toBeTrue();
	expect(is_file($plugin . '/INFO'))->toBeTrue();
	expect(is_dir($plugin . '/includes'))->toBeTrue();

	// An unexpected, non-whitelisted stray is left in place but logged.
	expect(is_file($plugin . '/stray.php'))->toBeTrue();

	$logged = implode("\n", $GLOBALS['__test_cacti_log']);
	expect($logged)->toContain('stray.php');
	expect($logged)->not->toContain('userdata');
	expect($logged)->not->toContain('.git');
});

it('is a safe no-op when the manifest is missing', function () {
	$base    = sys_get_temp_dir() . '/weathermap-prune-missing-' . uniqid();
	mkdir($base . '/plugins/weathermap', 0777, true);
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_weathermap_prune_files();
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
		plugin_weathermap_prune_files();
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
		plugin_weathermap_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The out-of-tree file is untouched and the refusal is logged.
	expect(is_file($outside))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('outside the plugin directory');
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
		plugin_weathermap_prune_files();
	} finally {
		restore_error_handler();
		$GLOBALS['config']['base_path'] = $restore;
		@chmod($plugin . '/locked/sub', 0700);
	}

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not remove');
})->skip(function () {
	return function_exists('posix_getuid') && posix_getuid() === 0;
}, 'permission checks are bypassed for the root user');
