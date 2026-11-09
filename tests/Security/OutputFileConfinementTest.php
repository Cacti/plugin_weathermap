<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/lib/poller-common.php';

/* The OUTPUTFILE family of map directives names a file the poller then writes.
 * A map config is authored by Manage/Edit Weathermap users, so an absolute path
 * or one with "../" must not let the write escape the plugin's output directory
 * (which would otherwise allow dropping or overwriting a file as the poller or
 * web-server user). */
describe('weathermap_confine_output_path', function (): void {
	beforeEach(function (): void {
		$this->outdir = sys_get_temp_dir() . '/wm-out-' . bin2hex(random_bytes(6));
		mkdir($this->outdir);
	});

	afterEach(function (): void {
		@rmdir($this->outdir);
	});

	it('accepts a bare filename and returns it inside the output directory', function (): void {
		$safe = weathermap_confine_output_path('map.html', $this->outdir);

		expect($safe)->toBe(realpath($this->outdir) . DIRECTORY_SEPARATOR . 'map.html');
	});

	it('accepts each supported output extension', function (): void {
		foreach (['out.htm', 'pic.png', 'photo.jpeg', 'photo.jpg', 'anim.gif', 'data.txt', 'data.dat', 'data.tsv'] as $name) {
			expect(weathermap_confine_output_path($name, $this->outdir))->not->toBeFalse();
		}
	});

	it('rejects an executable extension', function (): void {
		expect(weathermap_confine_output_path('evil.php', $this->outdir))->toBeFalse();
		expect(weathermap_confine_output_path('evil.phtml', $this->outdir))->toBeFalse();
		expect(weathermap_confine_output_path('map.html.php', $this->outdir))->toBeFalse();
	});

	it('rejects a dotfile such as .htaccess', function (): void {
		expect(weathermap_confine_output_path('.htaccess', $this->outdir))->toBeFalse();
	});

	it('rejects a name without a supported extension', function (): void {
		expect(weathermap_confine_output_path('index', $this->outdir))->toBeFalse();
		expect(weathermap_confine_output_path('data.', $this->outdir))->toBeFalse();
	});

	it('rejects an absolute path', function (): void {
		expect(weathermap_confine_output_path('/var/www/html/evil.php', $this->outdir))->toBeFalse();
	});

	it('rejects a parent-directory traversal', function (): void {
		expect(weathermap_confine_output_path('../../evil.php', $this->outdir))->toBeFalse();
	});

	it('rejects any nested directory component', function (): void {
		expect(weathermap_confine_output_path('sub/evil.php', $this->outdir))->toBeFalse();
	});

	it('rejects a Windows-style separator', function (): void {
		expect(weathermap_confine_output_path('..\\evil.php', $this->outdir))->toBeFalse();
	});

	it('rejects a NUL byte', function (): void {
		expect(weathermap_confine_output_path("map.html\0.php", $this->outdir))->toBeFalse();
	});

	it('rejects an empty or non-string value', function (): void {
		expect(weathermap_confine_output_path('', $this->outdir))->toBeFalse();
		expect(weathermap_confine_output_path(null, $this->outdir))->toBeFalse();
	});

	it('returns false when the output directory does not resolve', function (): void {
		expect(weathermap_confine_output_path('map.html', $this->outdir . '/missing'))->toBeFalse();
	});
});
