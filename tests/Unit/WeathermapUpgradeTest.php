<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/** Exercise upgrade dispatch without sharing production-function stubs. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WeathermapUpgradeTest extends TestCase {
	/**
	 * Install the upgrade-only repair stub in this isolated process.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		require_once __DIR__ . '/../../setup.php';
		if (!function_exists('weathermap_repair_maps')) {
			/**
			 * Stub filesystem repair only within an isolated upgrade test.
			 *
			 * @return void
			 */
			function weathermap_repair_maps() {}
		}
		$GLOBALS['__test_db_calls'] = [];
		$GLOBALS['__test_current_page'] = '';
	}

	/**
	 * Verify unrelated pages do not trigger upgrade queries.
	 *
	 * @return void
	 */
	public function testUnrelatedPageSkipsUpgrade(): void {
		test_set_current_page('graphs.php');
		plugin_weathermap_upgrade();
		self::assertEmpty($GLOBALS['__test_db_calls']);
	}

	/**
	 * Verify a version drift updates the realm and version metadata.
	 *
	 * @return void
	 */
	public function testVersionDriftUpdatesRealmAndVersion(): void {
		test_set_current_page('plugins.php');
		$restore = $GLOBALS['config']['base_path'];
		$base = sys_get_temp_dir() . '/weathermap-upg-' . bin2hex(random_bytes(6));
		$plugin = $base . '/plugins/weathermap';
		mkdir($plugin . '/lib', 0777, true);
		file_put_contents($plugin . '/lib/poller-common.php', "<?php\n");
		file_put_contents($plugin . '/INFO', "[info]\nversion = 9.9.9\nname = weathermap\nlongname = Weathermap\nauthor = x\nhomepage = x\n");
		$GLOBALS['config']['base_path'] = $base;
		try {
			plugin_weathermap_upgrade();
			$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
			});
			self::assertNotEmpty($updates);
		} finally {
			$GLOBALS['config']['base_path'] = $restore;
			unlink($plugin . '/INFO'); unlink($plugin . '/lib/poller-common.php');
			rmdir($plugin . '/lib'); rmdir($plugin); rmdir($base . '/plugins'); rmdir($base);
		}
	}
}
