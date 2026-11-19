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
 * Unit coverage for lib/node-cache.php: the version 1 node cache names stay
 * byte-identical to the original md5 scheme, and wm_node_cache_prune() removes
 * only regular files of other name format versions.
 */

require_once __DIR__ . '/../../lib/node-cache.php';

/**
 * Create an empty scratch directory for one prune test.
 *
 * @return string Absolute directory path.
 */
function wm_node_cache_fixture_dir(): string {
	$dir = sys_get_temp_dir() . '/wm-node-cache-' . bin2hex(random_bytes(6));
	mkdir($dir, 0700, true);

	return $dir;
}

/**
 * Remove a scratch directory without following symlinks.
 *
 * @param string $dir Directory to remove.
 *
 * @return void
 */
function wm_node_cache_fixture_remove(string $dir): void {
	chmod($dir, 0700);

	foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			wm_node_cache_fixture_remove($path);
		} else {
			unlink($path);
		}
	}

	rmdir($dir);
}

it('keeps the version 1 node cache name byte-identical to the original md5 scheme', function (string $map, string $node, string $expected) {
	expect(WM_NODE_CACHE_VERSION)->toBe(1)
		->and(wm_node_cache_name($map, $node))->toBe($expected)
		->and(wm_node_cache_name($map, $node, 1))->toBe($expected)
		->and(wm_node_cache_version($expected))->toBe(1);
})->with([
	'plain'          => ['map.conf', 'router1', 'node_25ba903756e4ff347edbcadfef4a4ee7.png'],
	'empty names'    => ['', '', 'node_6666cd76f96956469e7be39d750cc7d9.png'],
	'unicode'        => ['carte-é.conf', 'nœud-日本', 'node_ec5a7adea1d1a3e1e4a541776756dce4.png'],
	'slash in map'   => ['a/b', 'c', 'node_cff49f359f080f71548fcee824af6ad3.png'],
	// The original scheme joins with '/', so this collides with the case above; preserved as-is.
	'slash in node'  => ['a', 'b/c', 'node_cff49f359f080f71548fcee824af6ad3.png'],
]);

it('falls back to a 32-hex sha256 prefix when md5 is unavailable', function () {
	expect(wm_node_cache_hash('map.conf/router1', false))->toBe('536863744f1340ec2b8c01763ee86cca')
		->and(wm_node_cache_hash('/', false))->toBe('8a5edab282632443219e051e4ade2d1d')
		->and(wm_node_cache_hash('map.conf/router1', true))->toBe('25ba903756e4ff347edbcadfef4a4ee7')
		->and(wm_node_cache_hash('map.conf/router1'))->toBe('25ba903756e4ff347edbcadfef4a4ee7')
		->and(wm_node_cache_version('node_536863744f1340ec2b8c01763ee86cca.png'))->toBe(1);
});

it('refuses to build a name for an unknown format version', function (int $version) {
	expect(fn () => wm_node_cache_name('map.conf', 'router1', $version))->toThrow(InvalidArgumentException::class);
})->with([0, 2, -1]);

it('recognises only the plugin node cache name formats', function (string $file, ?int $version) {
	expect(wm_node_cache_version($file))->toBe($version);
})->with([
	'version 1'          => ['node_25ba903756e4ff347edbcadfef4a4ee7.png', 1],
	'version 2'          => ['node_v2_' . str_repeat('a', 64) . '.png', 2],
	'version 12'         => ['node_v12_0f.png', 12],
	'version 10'         => ['node_v10_0f.png', 10],
	'tagged v1'          => ['node_v1_0f.png', null],
	'version 0'          => ['node_v0_0f.png', null],
	'leading zero'       => ['node_v02_0f.png', null],
	'no hash'            => ['node_v2_.png', null],
	'short v1 hash'      => ['node_' . str_repeat('a', 31) . '.png', null],
	'long v1 hash'       => ['node_' . str_repeat('a', 33) . '.png', null],
	'upper-case hex'     => ['node_25BA903756E4FF347EDBCADFEF4A4EE7.png', null],
	'trailing newline'   => ["node_25ba903756e4ff347edbcadfef4a4ee7.png\n", null],
	'other extension'    => ['node_v2_0f.png.bak', null],
	'map cache file'     => ['1a2b3c_bg.123.png', null],
	'unrelated'          => ['index.php', null],
	'dot entry'          => ['..', null],
]);

it('removes stale-version files and keeps current, unrelated, linked and nested entries', function () {
	$dir     = wm_node_cache_fixture_dir();
	$outside = wm_node_cache_fixture_dir();
	$current = 'node_25ba903756e4ff347edbcadfef4a4ee7.png';

	try {
		foreach ([$current, 'node_v2_0a.png', 'node_v7_' . str_repeat('b', 64) . '.png', 'index.php', 'node_v2_0a.png.bak'] as $file) {
			file_put_contents($dir . '/' . $file, 'x');
		}

		file_put_contents($outside . '/target.png', 'keep');
		symlink($outside . '/target.png', $dir . '/node_v3_0c.png');
		mkdir($dir . '/node_v2_0d.png');
		file_put_contents($dir . '/node_v2_0d.png/node_v2_0e.png', 'x');
		mkdir($dir . '/sub');
		file_put_contents($dir . '/sub/node_v2_0f.png', 'x');

		$result = wm_node_cache_prune($dir);
		sort($result['removed']);

		expect($result)->toBe(['removed' => ['node_v2_0a.png', 'node_v7_' . str_repeat('b', 64) . '.png'], 'failed' => []])
			->and(is_file($dir . '/' . $current))->toBeTrue()
			->and(is_file($dir . '/index.php'))->toBeTrue()
			->and(is_file($dir . '/node_v2_0a.png.bak'))->toBeTrue()
			->and(is_link($dir . '/node_v3_0c.png'))->toBeTrue()
			->and(file_get_contents($outside . '/target.png'))->toBe('keep')
			->and(is_file($dir . '/node_v2_0d.png/node_v2_0e.png'))->toBeTrue()
			->and(is_file($dir . '/sub/node_v2_0f.png'))->toBeTrue()
			->and(wm_node_cache_prune($dir))->toBe(['removed' => [], 'failed' => []]);
	} finally {
		wm_node_cache_fixture_remove($dir);
		wm_node_cache_fixture_remove($outside);
	}
});

it('removes version 1 files once a later version is current', function () {
	$dir = wm_node_cache_fixture_dir();

	try {
		file_put_contents($dir . '/node_25ba903756e4ff347edbcadfef4a4ee7.png', 'x');
		file_put_contents($dir . '/node_v2_0a.png', 'x');

		expect(wm_node_cache_prune($dir, 2))->toBe(['removed' => ['node_25ba903756e4ff347edbcadfef4a4ee7.png'], 'failed' => []])
			->and(is_file($dir . '/node_v2_0a.png'))->toBeTrue();
	} finally {
		wm_node_cache_fixture_remove($dir);
	}
});

it('returns nothing for a missing directory or a regular file', function () {
	$dir = wm_node_cache_fixture_dir();

	try {
		file_put_contents($dir . '/file', 'x');

		expect(wm_node_cache_prune($dir . '/missing'))->toBe(['removed' => [], 'failed' => []])
			->and(wm_node_cache_prune($dir . '/file'))->toBe(['removed' => [], 'failed' => []]);
	} finally {
		wm_node_cache_fixture_remove($dir);
	}
});

it('reports a stale file it cannot remove and leaves it in place', function () {
	if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
		$this->markTestSkipped('root ignores directory permissions');
	}

	$dir = wm_node_cache_fixture_dir();

	try {
		file_put_contents($dir . '/node_v2_0a.png', 'x');
		chmod($dir, 0500);
		set_error_handler(static fn (): bool => true); // swallow the expected unlink warning

		try {
			$result = wm_node_cache_prune($dir);
		} finally {
			restore_error_handler();
		}

		expect($result)->toBe(['removed' => [], 'failed' => ['node_v2_0a.png']])
			->and(is_file($dir . '/node_v2_0a.png'))->toBeTrue();
	} finally {
		wm_node_cache_fixture_remove($dir);
	}
});
