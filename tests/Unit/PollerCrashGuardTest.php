<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Inputs that used to throw out of the poller: an unrecognised TARGET, link
 * geometry with a zero-length segment or parallel offsets, a first sample for
 * a new weathermap_data row, and a zero cron step. Each one stopped every map
 * after it and the rest of the Cacti poller cycle.
 */

beforeAll(function (): void {
	$plugin = dirname(__DIR__, 2);

	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
	require_once $plugin . '/lib/poller-common.php';
});

if (!function_exists('register_process_start')) {
	function register_process_start($tasktype, $taskname, $taskid = 0, $timeout = 300) {
		return $GLOBALS['__test_register_process_start'] ?? true;
	}
}

if (!function_exists('unregister_process')) {
	function unregister_process($tasktype, $taskname, $taskid = 0, $pid = 0) {
		$GLOBALS['__test_unregister_process_calls'][] = $taskname;
	}
}

/*
 * Read, collect and draw a two-node map with the given LINK lines, the way
 * the poller does, and return the warning count.
 */
function wmCrashGuardRender(string $link_lines): int {
	$plugin = dirname(__DIR__, 2);
	$dir    = sys_get_temp_dir() . '/wm-guard-' . bin2hex(random_bytes(6));
	$cwd    = getcwd();

	mkdir($dir, 0700);

	$conf = "WIDTH 400\nHEIGHT 400\n\n"
		. "NODE a\n\tPOSITION 100 100\n\n"
		. "NODE b\n\tPOSITION 100 300\n\n"
		. "LINK ab\n\tNODES a b\n" . $link_lines;

	file_put_contents($dir . '/map.conf', $conf);

	try {
		chdir($plugin);

		$GLOBALS['weathermap_warncount'] = 0;

		$map          = new WeatherMap();
		$map->rrdtool = PHP_BINARY;

		expect($map->ReadConfig($dir . '/map.conf'))->toBeTrue();

		$map->ReadData();
		$map->DrawMap($dir . '/map.png');

		expect(is_file($dir . '/map.png'))->toBeTrue();

		return (int) $GLOBALS['weathermap_warncount'];
	} finally {
		chdir($cwd);

		foreach (glob($dir . '/*') as $file) {
			unlink($file);
		}

		rmdir($dir);
	}
}

describe('map collection and drawing', function (): void {
	it('treats an unrecognised target as no data instead of throwing', function (): void {
		expect(wmCrashGuardRender("\tTARGET nosuchsource:value\n"))->toBeGreaterThan(0);
	});

	it('draws an angled link whose VIA doubles back on itself', function (): void {
		expect(wmCrashGuardRender("\tVIASTYLE angled\n\tVIA 100 380\n"))->toBe(0);
	});

	it('draws a zero-width link', function (): void {
		expect(wmCrashGuardRender("\tWIDTH 0\n"))->toBe(0);
	});

	it('draws a zero-width curved link', function (): void {
		expect(wmCrashGuardRender("\tWIDTH 0\n\tVIA 200 200\n"))->toBe(0);
	});
});

describe('geometry helpers', function (): void {
	it('returns a zero normal for a zero-length direction', function (): void {
		expect(wm_unit_normal(0, 0))->toBe([0.0, 0.0])
			->and(wm_unit_normal(3, 4))->toBe([0.8, -0.6]);
	});

	it('meets parallel lines at the shared vertex', function (): void {
		expect(line_crossing(0, 0, 10, 0, 10, 0, 0, 0))->toBe([10, 0])
			->and(line_crossing(0, 0, 10, 10, 0, 10, 10, 0))->toBe([5.0, 5.0]);
	});
});

describe('weathermap_cron_part()', function (): void {
	it('matches the forms crontab allows', function (): void {
		$matches = ['*' => [7], '5' => [5], '*/15' => [30], '0,15,30' => [15], '10-20' => [12], '10-20/5' => [15], '5/10' => [25]];
		$misses  = ['5' => [6], '*/15' => [31], '0,15,30' => [20], '10-20' => [21], '10-20/5' => [16], '5/10' => [0], '*/0' => [0], 'x*/5' => [10]];

		foreach ($matches as $field => $values) {
			foreach ($values as $value) {
				expect(weathermap_cron_part($value, (string) $field))->toBeTrue("'$field' should match $value");
			}
		}

		foreach ($misses as $field => $values) {
			foreach ($values as $value) {
				expect(weathermap_cron_part($value, (string) $field))->toBeFalse("'$field' should not match $value");
			}
		}
	});

	it('runs a schedule that uses lists and ranges', function (): void {
		$time = mktime(10, 15, 0, 6, 3, 2026);

		expect(weathermap_check_cron($time, '0,15 9-17 * * *'))->toBeTrue()
			->and(weathermap_check_cron($time, '*/0 * * * *'))->toBeFalse();
	});
});

describe('weathermap_poller_output()', function (): void {
	it('records a zero rate for the first sample of a new counter row', function (int $type): void {
		$GLOBALS['__test_db_calls']       = [];
		$GLOBALS['__test_db_fetch_assoc'] = fn ($sql) => [[
			'id'                  => 9,
			'last_value'          => '',
			'last_time'           => 0,
			'data_source_name'    => 'traffic_in',
			'data_source_path'    => '<path_rra>/router_traffic_in_9.rrd',
			'local_data_id'       => 9,
			'data_source_type_id' => $type,
		]];

		$rrd = ['/rra/router_traffic_in_9.rrd' => [
			'local_data_id' => 9,
			'times'         => [1700000000 => ['traffic_in' => '123456']],
		]];

		$rra_path                      = $GLOBALS['config']['rra_path'] ?? null;
		$GLOBALS['config']['rra_path'] = '/rra';

		weathermap_poller_output($rrd);

		$GLOBALS['config']['rra_path'] = $rra_path;

		unset($GLOBALS['__test_db_fetch_assoc']);

		$update = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_contains($c['sql'], 'UPDATE weathermap_data')));

		expect($update)->toHaveCount(1)
			->and($update[0]['params'][1])->toEqual(0)
			->and($update[0]['params'][2])->toBe('123456');
	})->with(['counter' => [2], 'derive' => [3]]);
});

describe('weathermap_poller_bottom()', function (): void {
	beforeEach(function (): void {
		$GLOBALS['__test_config_options']           = ['weathermap_render_period' => 0, 'weathermap_timeout' => 300];
		$GLOBALS['__test_unregister_process_calls'] = [];
		$GLOBALS['__test_register_process_start']   = true;
	});

	afterEach(function (): void {
		unset($GLOBALS['__test_db_fetch_assoc'], $GLOBALS['__test_register_process_start']);
	});

	it('logs a failed map run and releases the process lock', function (): void {
		// Schema checks run first; fail only once the map run queries the maps.
		$GLOBALS['__test_db_fetch_assoc'] = function ($sql): array {
			if (str_contains($sql, 'g.name AS groupname')) {
				throw new RuntimeException('map run failed');
			}

			return [];
		};

		weathermap_poller_bottom();

		expect($GLOBALS['__test_unregister_process_calls'])->toBe(['master']);
	});

	it('returns to the poller when another run holds the lock', function (): void {
		$GLOBALS['__test_register_process_start'] = false;

		weathermap_poller_bottom();

		expect($GLOBALS['__test_unregister_process_calls'])->toBe([]);
	});
});
