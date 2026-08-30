<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Build a throwaway Cacti tree with check.php in it and a stub auth.php.
 *
 * check.php resolves its include as __DIR__ . '/../../include/auth.php', so it
 * has to sit at plugins/weathermap/ inside the tree for the guard to load.
 *
 * @param  string|null $authStub contents for the stubbed include/auth.php
 * @return string      path to the copied check.php
 */
function wm_stage_check_page($authStub = null) {
	/* tempnam() creates and reserves the name, so unlinking it and taking the
	 * same path for a 0700 directory leaves no window for another local user to
	 * pre-create the tree with symlinks in it. */
	$root = tempnam(sys_get_temp_dir(), 'wm_check_');
	unlink($root);

	if (!mkdir($root, 0o700)) {
		throw new RuntimeException('could not create the staging directory');
	}

	mkdir($root . '/plugins/weathermap', 0o700, true);
	mkdir($root . '/include', 0o700, true);

	copy(dirname(__DIR__, 2) . '/check.php', $root . '/plugins/weathermap/check.php');

	if ($authStub === null) {
		$authStub = <<<'STUB'
<?php
$config = ['url_path' => '/cacti/'];

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($filename = '') {
		return getenv('WM_TEST_REALM') === '1';
	}
}
STUB;
	}

	file_put_contents($root . '/include/auth.php', $authStub);

	return $root . '/plugins/weathermap/check.php';
}

/**
 * Remove a tree left by wm_stage_check_page().
 *
 * @param  string $page path returned by wm_stage_check_page()
 * @return void
 */
function wm_unstage_check_page($page) {
	$root = dirname($page, 3);

	if (strpos($root, sys_get_temp_dir()) !== 0) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($items as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}

	rmdir($root);
}

/**
 * Serve a staged tree with PHP's built-in server and fetch one path from it.
 *
 * The guard only runs when PHP_SAPI is not cli.  The built-in server reports
 * cli-server, so it exercises the guard using the PHP already under test, with
 * nothing to install and no dependence on a distribution shipping a CGI binary
 * for the version in the matrix.
 *
 * @param  string $page path returned by wm_stage_check_page()
 * @param  string $realm value for the WM_TEST_REALM the stub reads
 * @return array  [body, response header lines]
 */
function wm_fetch_staged_page($page, $realm) {
	$root = dirname($page, 3);

	$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	$port   = (int) explode(':', stream_socket_get_name($socket, false))[1];
	fclose($socket);

	$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

	$server = proc_open(
		[PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root],
		$descriptors,
		$pipes,
		null,
		['WM_TEST_REALM' => $realm] + $_ENV
	);

	if (!is_resource($server)) {
		throw new RuntimeException('could not start the built-in server');
	}

	$url  = 'http://127.0.0.1:' . $port . '/plugins/weathermap/check.php';
	$body = false;

	for ($attempt = 0; $attempt < 50; $attempt++) {
		usleep(100000);
		$body = @file_get_contents($url, false, stream_context_create([
			'http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5],
		]));

		if ($body !== false) {
			break;
		}
	}

	$headers = $http_response_header ?? [];

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	proc_terminate($server);
	proc_close($server);

	return [(string) $body, $headers];
}

describe('check.php web access', function () {
	/* The report names the host, kernel, PHP build and ini paths.  The realm is
	 * enforced in the page itself rather than left to the plugin_realms row:
	 * that row is written by the installer and by the upgrade step, and the
	 * upgrade step only runs when the recorded version differs from INFO, so an
	 * install already on this version would get authentication with no
	 * authorisation at all. */
	it('refuses the report to a user without the Manage Weathermap realm', function () {
		$page              = wm_stage_check_page();
		[$body, $headers]  = wm_fetch_staged_page($page, '0');

		wm_unstage_check_page($page);

		expect(implode("\n", $headers))->toContain('Location: /cacti/permission_denied.php');
		$output = $body;
		expect($output)->not->toContain('Weathermap Pre-Install Checker');
		expect($output)->not->toContain(php_uname());
	});

	it('serves the report to a user who holds the realm', function () {
		$page          = wm_stage_check_page();
		[$output, $hdr] = wm_fetch_staged_page($page, '1');

		wm_unstage_check_page($page);

		expect(implode("\n", $hdr))->not->toContain('permission_denied.php');
		expect($output)->toContain('Weathermap Pre-Install Checker');
	});

	it('still runs from the command line with no Cacti session', function () {
		$page   = wm_stage_check_page();
		$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($page) . ' 2>/dev/null');

		wm_unstage_check_page($page);

		expect($output)->not->toContain('permission_denied.php');
		expect($output)->toContain('Weathermap Pre-Install Checker');
	});

	it('checks the realm and not only the login', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		expect($source)->toContain("api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')");
	});

	/* Cacti keys its realm lookup on the unqualified filename, so a generic name
	 * in plugin_realms.file sits in a namespace every plugin shares.  The guard
	 * above tests the plugin-prefixed mgmt realm, so registering check.php bought
	 * nothing and risked colliding with another plugin shipping the same name. */
	it('registers no unprefixed filename in the realm string', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

		preg_match_all("/api_plugin_register_realm\('weathermap',\s*'([^']+)'/", $source, $realms);

		expect($realms[1])->not->toBe([]);

		foreach ($realms[1] as $realmFiles) {
			foreach (explode(',', $realmFiles) as $file) {
				expect(trim($file))->toStartWith('weathermap-');
			}
		}
	});

	it('survives an auth include that declares a name check.php also defines', function () {
		// check.php declares return_bytes() at global scope; Cacti's include
		// chain is large enough that a collision is worth pinning.
		$page = wm_stage_check_page(<<<'STUB'
<?php
$config = ['url_path' => '/cacti/'];

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($filename = '') {
		return false;
	}
}

if (!function_exists('return_bytes')) {
	function return_bytes($val) {
		return 0;
	}
}
STUB);

		[$output, $headers] = wm_fetch_staged_page($page, '0');

		wm_unstage_check_page($page);

		expect($output)->not->toContain('Weathermap Pre-Install Checker');
		expect($output)->not->toContain('Cannot redeclare');
	});

	it('emits no report even when the auth include has already sent output', function () {
		$page = wm_stage_check_page(<<<'STUB'
<?php
print 'noise from the auth chain';
$config = ['url_path' => '/cacti/'];

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($filename = '') {
		return false;
	}
}
STUB);

		[$output, $headers] = wm_fetch_staged_page($page, '0');

		wm_unstage_check_page($page);

		// The redirect header may be unsendable once output has started; what
		// must hold either way is that the report itself is never written.
		expect($output)->not->toContain('Weathermap Pre-Install Checker');
	});
});
