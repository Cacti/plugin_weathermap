<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

beforeAll(function (): void {
	$plugin = dirname(__DIR__, 2);

	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
});

/*
 * Drives the engine the way the poller does: read a config from disk, read
 * data, draw the PNG and build the HTML imagemap. The map is the published
 * docs/example/traffic-pulse-2.0.conf with an INFOURL on every item, since the
 * imagemap only emits areas for items that link somewhere.
 */
it('renders a map config to a PNG and an HTML imagemap', function (): void {
	$plugin = dirname(__DIR__, 2);
	$dir    = sys_get_temp_dir() . '/wm-e2e-' . bin2hex(random_bytes(6));
	$cwd    = getcwd();

	mkdir($dir, 0700);

	$config = file_get_contents($plugin . '/docs/example/traffic-pulse-2.0.conf');
	$config = str_replace(
		["    POSITION 60 90\n", "    POSITION 600 90\n", "    BWLABEL percent\n"],
		[
			"    POSITION 60 90\n    INFOURL https://example.invalid/core\n",
			"    POSITION 600 90\n    INFOURL https://example.invalid/access\n",
			"    BWLABEL percent\n    INFOURL https://example.invalid/uplink\n",
		],
		$config,
		$replaced
	);

	expect($replaced)->toBe(3);

	$conf = $dir . '/map.conf';
	$png  = $dir . '/map.png';
	$html = $dir . '/map.html';

	file_put_contents($conf, $config);

	try {
		// Bundled fonts and icons resolve against the plugin directory, as under the poller.
		chdir($plugin);

		$GLOBALS['weathermap_warncount'] = 0;

		$map = new WeatherMap();
		// Static sources never call RRDtool; any existing executable passes its Init check.
		$map->rrdtool  = PHP_BINARY;
		$map->imageuri = 'map.png';

		expect($map->ReadConfig($conf))->toBeTrue();

		$map->ReadData();
		$map->DrawMap($png);

		file_put_contents($html, $map->MakeHTML('wm_e2e_imap'));

		expect($GLOBALS['weathermap_warncount'])->toBe(0);

		expect(is_file($png))->toBeTrue();
		$size = getimagesize($png);
		expect($size)->not->toBeFalse();
		expect([$size[0], $size[1], $size[2], $size['mime']])->toBe([660, 180, IMAGETYPE_PNG, 'image/png']);

		expect(is_file($html))->toBeTrue();
		$document = new DOMDocument();
		expect($document->loadHTML(file_get_contents($html), LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();

		$xpath = new DOMXPath($document);
		expect($xpath->query("//img[@id='wmapimage' and @src='map.png' and @usemap='#wm_e2e_imap']")->length)->toBe(1);
		expect($xpath->query("//map[@name='wm_e2e_imap']")->length)->toBe(1);

		$hrefs = [];

		foreach ($xpath->query("//map[@name='wm_e2e_imap']/area") as $area) {
			$hrefs[substr($area->getAttribute('id'), 0, 5)][$area->getAttribute('href')] = true;

			expect($area->getAttribute('coords'))->toMatch('/^\d+(,\d+)+$/');
		}

		expect(array_keys($hrefs['NODE:']))->toBe(['https://example.invalid/core', 'https://example.invalid/access']);
		expect(array_keys($hrefs['LINK:']))->toBe(['https://example.invalid/uplink']);

		expect($map->links['uplink']->inpercent)->toEqualWithDelta(90.0, 0.001);
		expect($map->links['uplink']->outpercent)->toEqualWithDelta(20.0, 0.001);
	} finally {
		chdir($cwd);

		foreach ([$conf, $png, $html] as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		rmdir($dir);
	}
});
