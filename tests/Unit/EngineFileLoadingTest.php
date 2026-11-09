<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Run PHP code in a fresh interpreter and return its combined output.
 *
 * Standalone loading can only be proven in a process that has not already
 * loaded the engine, so each case runs in its own PHP binary.
 *
 * @param  string $code PHP source without the opening tag
 * @return array  [exit status, output]
 */
function wm_engine_load_run(string $code): array {
	$script = tempnam(sys_get_temp_dir(), 'wm-load-');
	file_put_contents($script, "<?php\n" . $code);

	try {
		$lines = [];
		exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stdout -d error_reporting=-1 ' . escapeshellarg($script) . ' 2>&1', $lines, $status);

		return [$status, implode("\n", $lines)];
	} finally {
		unlink($script);
	}
}

describe('engine files load standalone', function (): void {
	it('defines the constants once and tolerates a second load', function (): void {
		$lib = var_export(dirname(__DIR__, 2) . '/lib/WeatherMap.constants.php', true);

		[$status, $output] = wm_engine_load_run(
			"require $lib;\n"
			. "require $lib;\n"
			. "echo json_encode(get_defined_constants(true)['user']);\n"
		);

		expect($status)->toBe(0);
		expect(json_decode($output, true))->toBe([
			'IN'                  => 0,
			'OUT'                 => 1,
			'WMCHANNELS'          => 2,
			'CONFIG_TYPE_LITERAL' => 0,
			'CONFIG_TYPE_COLOR'   => 1,
			'FMT_BITS_IN'         => '{link:this:bandwidth_in:%2k}',
			'FMT_BITS_OUT'        => '{link:this:bandwidth_out:%2k}',
			'FMT_UNFORM_IN'       => '{link:this:bandwidth_in}',
			'FMT_UNFORM_OUT'      => '{link:this:bandwidth_out}',
			'FMT_PERC_IN'         => '{link:this:inpercent:%.2f}%',
			'FMT_PERC_OUT'        => '{link:this:outpercent:%.2f}%',
			'X'                   => 0,
			'Y'                   => 1,
			'DISTANCE'            => 2,
		]);
	});

	it('keeps a constant a caller defined first', function (): void {
		$lib = var_export(dirname(__DIR__, 2) . '/lib/WeatherMap.constants.php', true);

		[$status, $output] = wm_engine_load_run("define('IN', 0);\nrequire $lib;\necho IN, OUT;\n");

		expect($status)->toBe(0);
		expect($output)->toBe('01');
	});

	it('loads each base class file on its own', function (string $file, string $class, ?string $parent): void {
		$path = var_export(dirname(__DIR__, 2) . '/lib/' . $file, true);

		[$status, $output] = wm_engine_load_run(
			"require_once $path;\n"
			. "\$object = new $class();\n"
			. "echo get_class(\$object), '|', var_export(get_parent_class(\$object), true);\n"
		);

		expect($status)->toBe(0);
		expect($output)->toBe($class . '|' . var_export($parent ?? false, true));
	})->with([
		'datasource'     => ['WeatherMapDataSource.class.php', 'WeatherMapDataSource', null],
		'pre-processor'  => ['WeatherMapPreProcessor.class.php', 'WeatherMapPreProcessor', null],
		'post-processor' => ['WeatherMapPostProcessor.class.php', 'WeatherMapPostProcessor', null],
		'base'           => ['WeatherMapBase.class.php', 'WeatherMapBase', null],
		'config item'    => ['WeatherMapConfigItem.class.php', 'WeatherMapConfigItem', null],
		'item'           => ['WeatherMapItem.class.php', 'WeatherMapItem', 'WeatherMapBase'],
	]);

	it('keeps the base class default behaviour', function (): void {
		$lib = dirname(__DIR__, 2) . '/lib/';

		[$status, $output] = wm_engine_load_run(
			'foreach (["WeatherMapDataSource", "WeatherMapPreProcessor", "WeatherMapPostProcessor", "WeatherMapItem"] as $class) {' . "\n"
			. "\trequire_once " . var_export($lib, true) . " . \$class . '.class.php';\n"
			. "}\n"
			. "\$map = null;\n"
			. "\$item = null;\n"
			. "\$source = new WeatherMapDataSource();\n"
			. "\$result = [\$source->Init(\$map), \$source->Recognise('static:1:2'), \$source->ReadData('static:1:2', \$map, \$item), \$source->Register('t', \$map, \$item), \$source->Prefetch()];\n"
			. "\$result[] = (new WeatherMapPreProcessor())->run(\$map);\n"
			. "\$result[] = (new WeatherMapPostProcessor())->run(\$map);\n"
			. "\$result[] = (new WeatherMapItem())->my_type();\n"
			. "echo json_encode(\$result);\n"
		);

		expect($status)->toBe(0);
		expect(json_decode($output, true))->toBe([true, false, [-1, -1, 0], null, null, false, false, 'ITEM']);
	});

	it('loads the datasource base, then the harness, then the engine in one process', function (): void {
		$root = dirname(__DIR__, 2);

		[$status, $output] = wm_engine_load_run(
			// The engine finds its plugin directories relative to the working directory, as under the poller.
			'chdir(' . var_export($root, true) . ");\n"
			. 'require_once ' . var_export($root . '/lib/WeatherMapDataSource.class.php', true) . ";\n"
			. 'require_once ' . var_export($root . '/tests/Helpers/DataSourceHarness.php', true) . ";\n"
			. 'require_once ' . var_export($root . '/lib/WeatherMap.class.php', true) . ";\n"
			. "\$map = new WeatherMap();\n"
			. "echo get_parent_class(\$map), '|', (new ReflectionClass('WeatherMapDataSource'))->getFileName(), '|', IN, OUT;\n"
		);

		expect($status)->toBe(0);
		expect($output)->toBe('WeatherMapBase|' . $root . '/lib/WeatherMapDataSource.class.php|01');
	});
});
