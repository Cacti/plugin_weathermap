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

	it('rejects a name that is not a plain output file name', function (mixed $path): void {
		expect(weathermap_confine_output_path($path, $this->outdir))->toBeFalse();
	})->with([
		'an executable extension'           => ['evil.php'],
		'a PHP handler extension'           => ['evil.phtml'],
		'an extension after .html'          => ['map.html.php'],
		'a dotfile such as .htaccess'       => ['.htaccess'],
		'no extension'                      => ['index'],
		'an empty extension'                => ['data.'],
		'an absolute path'                  => ['/var/www/html/evil.php'],
		'a parent-directory traversal'      => ['../../evil.php'],
		'a nested directory component'      => ['sub/evil.php'],
		'a Windows-style separator'         => ['..\\evil.php'],
		'a NUL byte'                        => ["map.html\0.php"],
		'an empty value'                    => [''],
		'a non-string value'                => [null],
		'a PHP segment before .png'         => ['map.php.png'],
		'a PHTML segment before .html'      => ['map.PHTML.html'],
		'a versioned PHP segment'           => ['map.php7.gif'],
		'a CGI segment'                     => ['map.cgi.txt'],
	]);

	it('accepts ordinary names with dots, spaces and an upper-case extension', function (): void {
		foreach (['network.prod.png', 'Core Links.html', 'MAP.PNG', 'site_a-2.JPEG'] as $name) {
			expect(weathermap_confine_output_path($name, $this->outdir))->toBe(realpath($this->outdir) . DIRECTORY_SEPARATOR . $name);
		}
	});

	it('accepts a path whose directory is the output directory, relative or absolute', function (): void {
		$expected = realpath($this->outdir) . DIRECTORY_SEPARATOR . 'map.png';

		expect(weathermap_confine_output_path(basename($this->outdir) . '/map.png', $this->outdir))->toBe($expected);
		expect(weathermap_confine_output_path($this->outdir . '/map.png', $this->outdir))->toBe($expected);
		expect(weathermap_confine_output_path($this->outdir . '/../' . basename($this->outdir) . '/map.png', $this->outdir))->toBe($expected);
	});

	it('rejects a path into a subdirectory of the output directory', function (): void {
		mkdir($this->outdir . '/sub');

		expect(weathermap_confine_output_path('sub/map.png', $this->outdir))->toBeFalse();
		expect(weathermap_confine_output_path($this->outdir . '/sub/map.png', $this->outdir))->toBeFalse();

		rmdir($this->outdir . '/sub');
	});

	it('rejects an existing destination that is a symbolic link', function (): void {
		$outside = tempnam(sys_get_temp_dir(), 'wm-outside');
		symlink($outside, $this->outdir . '/map.png');

		expect(weathermap_confine_output_path('map.png', $this->outdir))->toBeFalse();

		unlink($this->outdir . '/map.png');
		unlink($outside);
	});

	it('rejects an existing destination that is a dangling symbolic link', function (): void {
		symlink(sys_get_temp_dir() . '/wm-missing-' . bin2hex(random_bytes(6)), $this->outdir . '/map.png');

		expect(weathermap_confine_output_path('map.png', $this->outdir))->toBeFalse();

		unlink($this->outdir . '/map.png');
	});

	it('rejects an existing destination with a second hard link', function (): void {
		$outside = tempnam(sys_get_temp_dir(), 'wm-outside');
		link($outside, $this->outdir . '/map.png');

		expect(weathermap_confine_output_path('map.png', $this->outdir))->toBeFalse();

		unlink($this->outdir . '/map.png');
		unlink($outside);
	});

	it('rejects an existing destination that is a directory', function (): void {
		mkdir($this->outdir . '/map.png');

		expect(weathermap_confine_output_path('map.png', $this->outdir))->toBeFalse();

		rmdir($this->outdir . '/map.png');
	});

	it('accepts an existing ordinary file, which the poller overwrites', function (): void {
		file_put_contents($this->outdir . '/map.png', 'old');

		expect(weathermap_confine_output_path('map.png', $this->outdir))->toBe(realpath($this->outdir) . DIRECTORY_SEPARATOR . 'map.png');

		unlink($this->outdir . '/map.png');
	});

	it('returns false when the output directory does not resolve', function (): void {
		expect(weathermap_confine_output_path('map.html', $this->outdir . '/missing'))->toBeFalse();
	});
});
