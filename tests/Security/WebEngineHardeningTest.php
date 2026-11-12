<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Request-method guard, inline-script escaping, hover-graph URLs, format
 * widths, reserved output names, INCLUDE logging and the page title hook.
 */

beforeAll(function (): void {
	$plugin = dirname(__DIR__, 2);

	require_once $plugin . '/setup.php';
	require_once $plugin . '/lib/WeatherMap.class.php';
	require_once $plugin . '/lib/poller-common.php';
});

describe('wm_require_post()', function (): void {
	afterEach(function (): void {
		unset($_SERVER['REQUEST_METHOD']);
		http_response_code(200);
	});

	it('refuses a state-changing action that is not a POST', function (): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		expect(wm_require_post(['delete_map'], 'delete_map'))->toBeFalse()
			->and(http_response_code())->toBe(405);
	});

	it('lets a POST and any read-only action through', function (): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		expect(wm_require_post(['delete_map'], 'delete_map'))->toBeTrue();

		$_SERVER['REQUEST_METHOD'] = 'GET';

		expect(wm_require_post(['delete_map'], 'viewconfig'))->toBeTrue();
	});
});

describe('js_escape()', function (): void {
	it('cannot end an inline script block or the string it is in', function (): void {
		$out = js_escape("</script><img src=x onerror='a'>\"\\");

		expect($out)->not->toContain('<')
			->and($out)->not->toContain('>')
			->and($out)->not->toContain("'")
			->and(substr_count($out, '"'))->toBe(2)
			->and(json_decode($out))->toBe("</script><img src=x onerror='a'>\"\\");
	});

	it('returns the bare string when asked not to wrap it', function (): void {
		expect(js_escape('a"b', false))->toBe('a\\u0022b')
			->and(js_escape('node-1'))->toBe('"node-1"');
	});
});

describe('mysprintf()', function (): void {
	it('prints a format with a huge width literally instead of allocating it', function (): void {
		$GLOBALS['weathermap_warncount'] = 0;

		expect(mysprintf('%2000000000d', 5))->toBe('%2000000000d')
			->and(mysprintf('%1$*d', 2000000000))->toBe('%1$*d')
			->and(mysprintf("%'a2000000000d", 5))->toBe("%'a2000000000d")
			->and($GLOBALS['weathermap_warncount'])->toBe(3);
	});

	it('still applies an ordinary width', function (): void {
		expect(mysprintf('%5.2f', 2.25))->toBe(' 2.25');
	});
});

describe('weathermap_confine_output_path()', function (): void {
	beforeEach(function (): void {
		$this->outdir = sys_get_temp_dir() . '/wm-out-' . bin2hex(random_bytes(6));
		mkdir($this->outdir);
	});

	afterEach(function (): void {
		rmdir($this->outdir);
	});

	it('refuses the names the poller and the graph tree own', function (string $name): void {
		expect(weathermap_confine_output_path($name, $this->outdir))->toBeFalse();
	})->with([
		'tree page'   => ['weathermap_7.html'],
		'map html'    => ['0123456789abcdef0123.html'],
		'map image'   => ['0123456789abcdef0123.png'],
		'map thumb'   => ['0123456789abcdef0123.thumb.png'],
		'map results' => ['0123456789abcdef0123.results.txt'],
	]);

	it('still accepts a chosen file name', function (): void {
		expect(weathermap_confine_output_path('weathermap_core.html', $this->outdir))->not->toBeFalse();
	});
});

/*
 * Read a config with the engine and return what it logged and the hover markup
 * built for the node "a".
 */
function wmHardeningHover(string $graph): string {
	$plugin = dirname(__DIR__, 2);
	$cwd    = getcwd();
	$png    = sys_get_temp_dir() . '/wm-hover-' . bin2hex(random_bytes(6)) . '.png';

	try {
		chdir($plugin);

		$map          = new WeatherMap();
		$map->rrdtool = PHP_BINARY;
		$map->ReadConfig("WIDTH 200\nHEIGHT 200\nHTMLSTYLE overlib\nNODE a\n\tLABEL a\n\tPOSITION 50 50\n\tINFOURL http://x.test/\n\tOVERLIBGRAPH $graph\n");
		$map->ReadData();
		$map->DrawMap($png);

		preg_match('/data-hover="([^"]*)"/', $map->MakeHTML('wm_hover'), $m);

		return base64_decode($m[1] ?? '', true);
	} finally {
		chdir($cwd);

		if (is_file($png)) {
			unlink($png);
		}
	}
}

describe('map hover graphs', function (): void {
	it('keeps a hover graph URL inside its attribute', function (): void {
		$markup = wmHardeningHover("http://x.test/g.png'onerror='alert(1)");

		expect($markup)->toContain("src='http://x.test/g.png&")
			->and(substr_count($markup, "'"))->toBe(6);
	});

	it('drops a hover graph with a script scheme', function (): void {
		$markup = wmHardeningHover('javascript:alert(1)');

		expect($markup)->toContain('<div>')
			->and($markup)->not->toContain('javascript');
	});
});

describe('INCLUDE', function (): void {
	it('does not copy lines of an included file into the log', function (): void {
		$dir = sys_get_temp_dir() . '/wm-inc-' . bin2hex(random_bytes(6));

		mkdir($dir);
		file_put_contents($dir . '/secret.txt', "database_password = hunter2\n");
		file_put_contents($dir . '/map.conf', "WIDTH 200\nHEIGHT 200\nINCLUDE $dir/secret.txt\nmain_only_line\n");

		$GLOBALS['__test_cacti_log']     = [];
		$GLOBALS['weathermap_warncount'] = 0;

		$cwd = getcwd();
		chdir(dirname(__DIR__, 2));

		$map = new WeatherMap();
		$map->ReadConfig($dir . '/map.conf');

		chdir($cwd);

		$log = implode("\n", $GLOBALS['__test_cacti_log']);

		unlink($dir . '/secret.txt');
		unlink($dir . '/map.conf');
		rmdir($dir);

		expect($GLOBALS['weathermap_warncount'])->toBe(2);

		// Warnings reach cacti_log() only when no editor code has been loaded.
		if (!function_exists('show_editor_startpage')) {
			expect($log)->not->toContain('hunter2')
				->and($log)->toContain('main_only_line');
		}
	});
});

describe('weathermap_page_title()', function (): void {
	beforeEach(function (): void {
		$_SERVER['REQUEST_URI']                  = '/cacti/plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&id=3';
		$_SESSION['sess_user_id']                = 1;
		$GLOBALS['__test_db_fetch_row_prepared'] = fn ($sql, $p) => ['id' => 3, 'titlecache' => '<b>Core</b>'];
	});

	afterEach(function (): void {
		unset($_SERVER['REQUEST_URI'], $GLOBALS['__test_db_fetch_row_prepared'], $GLOBALS['__test_db_fetch_cell_prepared']);
	});

	it('escapes the title of a map the user may view', function (): void {
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn ($sql, $p) => 3;

		expect(weathermap_page_title('Cacti'))->toBe('Cacti > &lt;b&gt;Core&lt;/b&gt;');
	});

	it('does not name a map the user may not view', function (): void {
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn ($sql, $p) => false;

		expect(weathermap_page_title('Cacti'))->toBe('Cacti');
	});
});

describe('weathermap page output', function (): void {
	afterEach(function (): void {
		unset($GLOBALS['__test_db_fetch_row_prepared'], $_SERVER['REQUEST_URI']);
	});

	it('names a map viewed by its file hash', function (): void {
		$_SERVER['REQUEST_URI']                   = '/cacti/plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&id=0123456789abcdef0123';
		$_SESSION['sess_user_id']                 = 1;
		$GLOBALS['__test_db_fetch_row_prepared']  = fn ($sql, $p) => str_contains($sql, 'filehash') ? ['id' => 4, 'titlecache' => 'Edge'] : [];
		$GLOBALS['__test_db_fetch_cell_prepared'] = fn ($sql, $p) => 4;

		expect(weathermap_page_title('Cacti'))->toBe('Cacti > Edge');

		unset($GLOBALS['__test_db_fetch_cell_prepared']);
	});

	it('prints the tree view HTML without running it', function (): void {
		$file = dirname(__DIR__, 2) . '/output/weathermap_987654.html';

		file_put_contents($file, "<p><?php print 'ran'; ?></p>");

		$_SESSION['sess_user_id']                = 1;
		$GLOBALS['__test_db_fetch_row_prepared'] = fn () => ['id' => 987654, 'titlecache' => 'Tree', 'configfile' => 'tree.conf'];

		ob_start();
		weathermap_tree_item_render(['item_id' => 987654]);
		$html = ob_get_clean();

		unlink($file);

		expect($html)->toContain("<p><?php print 'ran'; ?></p>");
	});

	it('gives the editor start page forms the CSRF token', function (): void {
		if (!function_exists('csrf_get_tokens')) {
			function csrf_get_tokens() {
				return 'sid:abc,123';
			}
		}

		require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';

		ob_start();
		wm_editor_csrf_input();

		expect(ob_get_clean())->toBe('<input name="__csrf_magic" type="hidden" value="' . html_escape(csrf_get_tokens()) . '">');
	});
});
