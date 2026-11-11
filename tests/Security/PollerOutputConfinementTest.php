<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

if (!function_exists('get_selected_theme')) {
	/**
	 * Cacti's theme lookup, used when a standalone HTMLOUTPUTFILE is written.
	 */
	function get_selected_theme(): string {
		return 'modern';
	}
}

/**
 * Run the real poller loop over fixture maps whose OUTPUTFILE directives point
 * inside and outside the plugin output directory.  The engine is loaded, so
 * each test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PollerOutputConfinementTest extends TestCase {
	private string $plugin;

	protected function setUp(): void {
		$this->plugin = realpath(sys_get_temp_dir()) . '/wm-poller-' . bin2hex(random_bytes(6));
		mkdir($this->plugin);
		mkdir($this->plugin . '/output');
		mkdir($this->plugin . '/configs');
		symlink(dirname(__DIR__, 2) . '/lib', $this->plugin . '/lib');

		require_once dirname(__DIR__, 2) . '/setup.php';
		require_once dirname(__DIR__, 2) . '/lib/poller-common.php';

		$GLOBALS['__test_config_options'] = ['weathermap_output_format' => 'png', 'weathermap_thumbsize' => '100'];
		$GLOBALS['__test_cacti_log']      = [];
		// read by the RRD datasource when the engine loads its plugins
		$GLOBALS['config']['rra_path'] = $this->plugin . '/rra';
	}

	protected function tearDown(): void {
		foreach (['output', 'configs'] as $directory) {
			array_map('unlink', glob($this->plugin . '/' . $directory . '/*') ?: []);
			rmdir($this->plugin . '/' . $directory);
		}

		array_map('unlink', glob($this->plugin . '/*.*') ?: []);
		unlink($this->plugin . '/lib');
		rmdir($this->plugin);
	}

	/**
	 * Write a one-node map with the given output directives and poll it.
	 */
	private function poll(string $directives): void {
		file_put_contents($this->plugin . '/configs/map.conf', "WIDTH 120\nHEIGHT 80\n" . $directives . "NODE a\n\tPOSITION 60 40\n");

		$GLOBALS['__test_db_fetch_assoc'] = fn (string $sql): array => str_contains($sql, 'FROM weathermap_maps m') ? [[
			'id'         => 1,
			'configfile' => 'map.conf',
			'filehash'   => 'abc123',
			'group_id'   => 1,
			'groupname'  => 'Maps',
			'schedule'   => '*',
		]] : [];

		weathermap_run_maps($this->plugin, true, [1]);
	}

	/**
	 * @return list<string>
	 */
	private function warnings(): array {
		return array_values(array_filter($GLOBALS['__test_cacti_log'], fn (string $line): bool => str_contains($line, 'WMPOLL08')));
	}

	public function testOutputFilesInsideTheOutputDirectoryAreWritten(): void {
		$this->poll("HTMLOUTPUTFILE inside.html\nIMAGEOUTPUTFILE copy.png\nDATAOUTPUTFILE output/data.txt\n");

		self::assertStringContainsString('<map', (string) file_get_contents($this->plugin . '/output/inside.html'));
		self::assertFileExists($this->plugin . '/output/copy.png');
		self::assertFileExists($this->plugin . '/output/data.txt');
		self::assertSame([], $this->warnings());
	}

	public function testOutputFilesOutsideTheOutputDirectoryAreRefused(): void {
		$this->poll("HTMLOUTPUTFILE ../escape.html\nIMAGEOUTPUTFILE " . $this->plugin . "/escape.png\nDATAOUTPUTFILE escape.php\n");

		self::assertFileDoesNotExist(dirname($this->plugin) . '/escape.html');
		self::assertFileDoesNotExist($this->plugin . '/escape.png');
		self::assertFileDoesNotExist($this->plugin . '/output/escape.php');
		self::assertCount(3, $this->warnings());
		// the map itself is still drawn into the output directory
		self::assertFileExists($this->plugin . '/output/abc123.png');
	}

	public function testDataOutputFileCannotCreateAnIncludableHtml(): void {
		// output/weathermap_<id>.html is the file the graph tree include()s, so
		// a data file (which carries node/link names verbatim) must not be able
		// to land there as an includable .html.
		$this->poll("DATAOUTPUTFILE weathermap_1.html\n");

		self::assertFileDoesNotExist($this->plugin . '/output/weathermap_1.html');
		self::assertCount(1, $this->warnings());
	}
}
