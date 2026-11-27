<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Helpers/DataSourceHarness.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMapRrdCommand.class.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMapFpingCommand.class.php';
require_once dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_rrd.php';
require_once dirname(__DIR__, 2) . '/lib/datasources/WeatherMapDataSource_fping.php';

describe('WeatherMapRrdCommand seam and WM-CMD-02 path validation', function (): void {
	afterEach(function (): void {
		$GLOBALS['__test_cacti_log'] = [];
	});

	it('accepts valid absolute RRD filesystem paths', function (string $path): void {
		$ds = new WeatherMapDataSource_rrd();

		expect(WeatherMapRrdCommand::isValidPath($path))->toBeTrue()
			->and($ds->wmrrd_path_is_valid($path))->toBeTrue();
	})->with([
		'/var/www/html/cacti/rra/localhost_traffic_in_12.rrd',
		'/var/rra/my file.rrd',
		'/var/lib/cacti/rra/router-01.example.com_cpu_5.rrd',
	]);

	it('rejects relative paths, controls, NUL bytes, non-strings, and shell metacharacters', function (mixed $path): void {
		$ds = new WeatherMapDataSource_rrd();

		expect(WeatherMapRrdCommand::isValidPath($path))->toBeFalse()
			->and($ds->wmrrd_path_is_valid($path))->toBeFalse();
	})->with([
		'',
		null,
		123,
		'relative/path.rrd',
		'../rra/host.rrd',
		"/var/rra/host\0.rrd",
		"/var/rra/host\n.rrd",
		"/var/rra/host\x7f.rrd",
		'/var/rra/host.rrd;id',
		'/var/rra/host.rrd|cat',
		'/var/rra/host.rrd&id',
		'/var/rra/`id`.rrd',
		'/var/rra/$(id).rrd',
		'/var/rra/host.rrd" --daemon /tmp/x "',
		"/var/rra/host'.rrd",
		'/var/rra/host\\evil.rrd',
		'/var/rra/host>out.rrd',
		'/var/rra/host*.rrd',
		'/var/rra/host#comment.rrd',
	]);

	it('builds escaped fetch and aggregate commands without Cacti bootstrap', function (): void {
		$builder  = new WeatherMapRrdCommand('escapeshellarg');
		$rejected = false;

		$fetch = $builder->buildFetchCommand(
			'/usr/bin/rrdtool',
			'/var/rra/my file.rrd',
			'AVERAGE',
			'now-800',
			'now',
			'--resolution 300',
			$rejected
		);

		expect($rejected)->toBeFalse()
			->and($fetch)->toBe("'/usr/bin/rrdtool' 'fetch' '/var/rra/my file.rrd' 'AVERAGE' '--start' 'now-800' '--end' 'now' '--resolution' '300'");

		$agg = $builder->buildAggregateCommand(
			'/usr/bin/rrdtool',
			'/var/rra/host.rrd',
			'AVERAGE',
			'MAXIMUM',
			'now-1h',
			'now',
			'traffic_in',
			'traffic_out',
			'--daemon unix:/run/rrdcached.sock',
			$rejected
		);

		expect($rejected)->toBeFalse()
			->and($agg)->toContain("'graph' '/dev/null'")
			->and($agg)->toContain("'DEF:in=/var/rra/host.rrd:traffic_in:AVERAGE'")
			->and($agg)->toContain("'DEF:out=/var/rra/host.rrd:traffic_out:AVERAGE'")
			->and($agg)->toContain("'--daemon' 'unix:/run/rrdcached.sock'");
	});

	it('marks unsafe extra rrd_options as rejected and omits them from the command', function (): void {
		$builder  = new WeatherMapRrdCommand('escapeshellarg');
		$rejected = false;

		$cmd = $builder->buildFetchCommand(
			'/usr/bin/rrdtool',
			'/var/rra/host.rrd',
			'AVERAGE',
			'now-800',
			'now',
			'--start "-1h"',
			$rejected
		);

		expect($rejected)->toBeTrue()
			->and($cmd)->not->toContain('-1h');
	});

	it('throws InvalidArgumentException when building commands with an invalid RRD path', function (): void {
		$builder = new WeatherMapRrdCommand('escapeshellarg');

		expect(fn () => $builder->buildFetchCommand('/usr/bin/rrdtool', 'relative.rrd', 'AVERAGE', 'now-800', 'now'))
			->toThrow(InvalidArgumentException::class);

		expect(fn () => $builder->buildAggregateCommand('/usr/bin/rrdtool', '/var/rra/evil;id.rrd', 'AVERAGE', 'AVERAGE', 'now-800', 'now', 'in', 'out'))
			->toThrow(InvalidArgumentException::class);
	});

	it('rejects invalid RRD paths in datasource methods with a logged warning', function (): void {
		$ds   = new WeatherMapDataSource_rrd();
		$map  = new class {
			public string $rrdtool = '/usr/bin/true';
			/** @var array<string, string> */
			public array $hints = [];
			public function get_hint(string $name): ?string {
				return $this->hints[$name] ?? null;
			}
		};
		$item = new class {
			public string $name = 'link1';
			public function my_type(): string {
				return 'LINK';
			}
		};

		$data      = [IN => null, OUT => null];
		$data_time = 0;
		$GLOBALS['__test_cacti_log'] = [];

		$ds->wmrrd_read_from_real_rrdtool('/var/rra/bad;id.rrd', 'AVERAGE', 'now-800', 'now', [IN => 'in', OUT => 'out'], $data, $map, $data_time, $item);
		$ds->wmrrd_read_from_real_rrdtool_aggregate('relative.rrd', 'AVERAGE', 'AVERAGE', 'now-800', 'now', [IN => 'in', OUT => 'out'], $data, $map, $data_time, $item);

		$logs = implode("\n", $GLOBALS['__test_cacti_log']);

		expect($data[IN])->toBeNull()
			->and($data[OUT])->toBeNull()
			->and($logs)->toContain('[WMRRD13]');
	});

	it('wires WeatherMapRrdCommand and unsafe-option warnings in both rrdtool datasource methods', function (): void {
		$true_bin = is_executable('/usr/bin/true') ? '/usr/bin/true' : '/bin/true';
		$ds       = new WeatherMapDataSource_rrd();
		$map      = new class($true_bin) {
			public string $rrdtool;
			/** @var array<string, string> */
			public array $hints = ['rrd_options' => '--bad "quote"'];
			public function __construct(string $bin) {
				$this->rrdtool = $bin;
			}
			public function get_hint(string $name): ?string {
				return $this->hints[$name] ?? null;
			}
		};
		$item = new class {
			public string $name = 'link1';
			public function my_type(): string {
				return 'LINK';
			}
		};

		$data      = [IN => null, OUT => null];
		$data_time = 0;
		$GLOBALS['__test_cacti_log'] = [];

		$ds->wmrrd_read_from_real_rrdtool('/var/rra/valid.rrd', 'AVERAGE', 'now-800', 'now', [IN => 'in', OUT => 'out'], $data, $map, $data_time, $item);
		$ds->wmrrd_read_from_real_rrdtool_aggregate('/var/rra/valid.rrd', 'AVERAGE', 'AVERAGE', 'now-800', 'now', [IN => 'in', OUT => 'out'], $data, $map, $data_time, $item);

		$logs = implode("\n", $GLOBALS['__test_cacti_log']);

		expect($logs)->toContain('[WMRRD04]');
	});
});

describe('WeatherMapFpingCommand seam and datasource wiring', function (): void {
	it('validates fping targets, builds escaped commands, and delegates open()', function (): void {
		$captured = [];
		$runner   = new WeatherMapFpingCommand(
			'escapeshellarg',
			static function (string $command, string $mode) use (&$captured) {
				$captured[] = [$command, $mode];

				return false;
			}
		);

		expect(WeatherMapFpingCommand::isValidTarget('192.0.2.1'))->toBeTrue()
			->and(WeatherMapFpingCommand::isValidTarget('2001:db8::1'))->toBeTrue()
			->and(WeatherMapFpingCommand::isValidTarget('router_01.example.com'))->toBeTrue()
			->and(WeatherMapFpingCommand::isValidTarget('192.0.2.1;id'))->toBeFalse()
			->and(WeatherMapFpingCommand::isValidTarget(null))->toBeFalse();

		$cmd = $runner->buildCommand('/usr/bin/fping', 3, '192.0.2.1');
		expect($cmd)->toBe("'/usr/bin/fping' -t100 -r1 -p20 -u -C 3 -i10 -q '192.0.2.1' 2>&1");
		expect($runner->open($cmd))->toBeFalse()
			->and($captured)->toBe([[$cmd, 'r']]);

		expect(fn () => $runner->buildCommand('/usr/bin/fping', 5, '192.0.2.1|id'))
			->toThrow(InvalidArgumentException::class);
	});

	it('wires WeatherMapFpingCommand into WeatherMapDataSource_fping::ReadData()', function (): void {
		$captured = [];
		$ds       = new WeatherMapDataSource_fping();
		$ds->fping_cmd = PHP_BINARY;
		$ds->command_runner = new WeatherMapFpingCommand(
			'escapeshellarg',
			static function (string $command, string $mode) use (&$captured) {
				$captured[] = [$command, $mode];

				return false;
			}
		);

		$map = new class {
			public function get_hint(string $name): ?string {
				return $name === 'fping_ping_count' ? '4' : null;
			}
		};
		$item = wm_test_item('host1');

		$rejected = $ds->ReadData('fping:192.0.2.1;id', $map, $item);
		expect($rejected)->toBe([-1, -1, 0])
			->and($captured)->toBe([]);

		$result = $ds->ReadData('fping:192.0.2.1', $map, $item);
		expect($result)->toBe([null, null, 0])
			->and($captured)->toHaveCount(1)
			->and($captured[0][0])->toContain("-C 4 -i10 -q '192.0.2.1' 2>&1")
			->and($captured[0][1])->toBe('r');
	});
});
