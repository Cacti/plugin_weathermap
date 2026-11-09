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
 * End-to-end coverage for the versioned node cache: a real map is read and
 * drawn by the engine, WeatherMapNode::update_cache() writes the node images,
 * and the files are then located by name and pruned as designed.
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NodeCacheE2ETest extends TestCase {
	/** @var list<string> Scratch paths removed after each test. */
	private array $cleanup = [];

	/**
	 * Remove every scratch path the test created, without following symlinks.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (array_reverse($this->cleanup) as $path) {
			$this->remove($path);
		}
	}

	/**
	 * Recursively delete a path, unlinking symlinks rather than following them.
	 *
	 * @param string $path File or directory to remove.
	 *
	 * @return void
	 */
	private function remove(string $path): void {
		if (is_dir($path) && !is_link($path)) {
			chmod($path, 0700);

			foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
				$this->remove($path . '/' . $entry);
			}

			rmdir($path);
		} elseif (is_link($path) || file_exists($path)) {
			unlink($path);
		}
	}

	/**
	 * Create a scratch directory that tearDown() removes.
	 *
	 * @return string Absolute directory path.
	 */
	private function scratchDir(): string {
		$dir = sys_get_temp_dir() . '/wm-node-cache-e2e-' . bin2hex(random_bytes(6));
		mkdir($dir, 0700, true);
		$this->cleanup[] = $dir;

		return $dir;
	}

	/**
	 * Read and draw a two-node map with the engine, ready for update_cache().
	 *
	 * @param string $dir Directory for the config file and rendered image.
	 *
	 * @return WeatherMap The drawn map.
	 */
	private function drawMap(string $dir): WeatherMap {
		$plugin = dirname(__DIR__, 2);
		chdir($plugin);
		require_once dirname(__DIR__) . '/bootstrap-unit.php';
		require_once $plugin . '/lib/WeatherMap.class.php';

		$config = $dir . '/e2e.conf';
		file_put_contents($config, "WIDTH 200\nHEIGHT 100\n\nNODE A\n\tLABEL Alpha\n\tPOSITION 40 50\n\nNODE B\n\tLABEL Beta\n\tPOSITION 160 50\n\nLINK AB\n\tNODES A B\n");

		$map = new WeatherMap();
		self::assertTrue($map->ReadConfig($config));

		ob_start();

		try {
			$map->DrawMap($dir . '/e2e.png');
		} finally {
			ob_end_clean();
		}

		self::assertFileExists($dir . '/e2e.png');

		return $map;
	}

	/**
	 * Write every real node's cache image through the engine's writer.
	 *
	 * @param WeatherMap $map      Drawn map.
	 * @param string     $cachedir Node cache directory.
	 *
	 * @return void
	 */
	private function writeNodeCache(WeatherMap $map, string $cachedir): void {
		foreach (['A', 'B'] as $name) {
			self::assertInstanceOf(GdImage::class, $map->nodes[$name]->image);
			$map->nodes[$name]->update_cache($cachedir, $map->configfile);
		}
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRenderedNodesAreCachedUnderVersionOneNamesAndStaleVersionsArePruned(): void {
		$work     = $this->scratchDir();
		$cachedir = $this->scratchDir();
		$outside  = $this->scratchDir();
		$map      = $this->drawMap($work);

		$kept = 'node_' . str_repeat('c', 32) . '.png';
		file_put_contents($cachedir . '/node_v2_' . str_repeat('a', 64) . '.png', 'stale');
		file_put_contents($cachedir . '/' . $kept, 'current version, other node');
		file_put_contents($cachedir . '/notes.txt', 'unrelated');
		file_put_contents($outside . '/target.png', 'keep');
		symlink($outside . '/target.png', $cachedir . '/node_v3_0b.png');

		$this->writeNodeCache($map, $cachedir);

		foreach (['A', 'B'] as $name) {
			$legacy = $cachedir . '/node_' . md5($map->configfile . '/' . $name) . '.png';
			self::assertSame($legacy, $cachedir . '/' . wm_node_cache_name($map->configfile, $name));
			self::assertSame(IMAGETYPE_PNG, getimagesize($legacy)[2]);
		}

		self::assertFileDoesNotExist($cachedir . '/node_v2_' . str_repeat('a', 64) . '.png');
		self::assertFileExists($cachedir . '/' . $kept);
		self::assertFileExists($cachedir . '/notes.txt');
		self::assertTrue(is_link($cachedir . '/node_v3_0b.png'));
		self::assertSame('keep', file_get_contents($outside . '/target.png'));

		// The prune runs once per directory per process, not on every node write.
		file_put_contents($cachedir . '/node_v2_0c.png', 'stale');
		$this->writeNodeCache($map, $cachedir);
		self::assertFileExists($cachedir . '/node_v2_0c.png');

		$names  = array_values(array_diff((array) scandir($cachedir), ['.', '..']));
		$cached = array_values(array_filter($names, fn (string $file): bool => wm_node_cache_version($file) === WM_NODE_CACHE_VERSION));
		sort($cached);
		$expected = [$kept, wm_node_cache_name($map->configfile, 'A'), wm_node_cache_name($map->configfile, 'B')];
		sort($expected);
		self::assertSame($expected, $cached);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testUnremovableStaleFileIsReportedAndNodesStillCached(): void {
		if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
			self::markTestSkipped('root ignores directory permissions');
		}

		$work                        = $this->scratchDir();
		$cachedir                    = $this->scratchDir();
		$map                         = $this->drawMap($work);
		$GLOBALS['__test_cacti_log'] = [];

		$stale = $cachedir . '/node_v2_0a.png';
		file_put_contents($stale, 'stale');
		chmod($cachedir, 0500);

		// The image write fails too in a read-only directory; only the prune report is under test.
		set_error_handler(static fn (): bool => true);

		try {
			$map->nodes['A']->update_cache($cachedir, $map->configfile);
		} finally {
			restore_error_handler();
		}

		self::assertFileExists($stale);
		self::assertCount(1, array_filter($GLOBALS['__test_cacti_log'], fn (string $line): bool => str_contains($line, 'Failed to remove stale node cache file ' . $stale)));

		// With the directory writable again the writer still uses the version 1 name.
		chmod($cachedir, 0700);
		$map->nodes['B']->update_cache($cachedir, $map->configfile);
		self::assertFileExists($cachedir . '/' . wm_node_cache_name($map->configfile, 'B'));
	}
}
