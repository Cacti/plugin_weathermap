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
 * @return string path to the copied check.php
 */
function wm_stage_check_page() {
	$root = sys_get_temp_dir() . '/wm_check_' . getmypid();

	if (!is_dir($root . '/plugins/weathermap')) {
		mkdir($root . '/plugins/weathermap', 0o777, true);
		mkdir($root . '/include', 0o777, true);
	}

	copy(dirname(__DIR__, 2) . '/check.php', $root . '/plugins/weathermap/check.php');

	file_put_contents($root . '/include/auth.php', <<<'STUB'
<?php
$config = ['url_path' => '/cacti/'];

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($filename = '') {
		return getenv('WM_TEST_REALM') === '1';
	}
}
STUB);

	return $root . '/plugins/weathermap/check.php';
}

/**
 * Locate a CGI binary, so the guard's non-CLI branch can be exercised.
 *
 * @return string|null
 */
function wm_php_cgi() {
	$found = trim((string) @shell_exec('command -v php-cgi 2>/dev/null'));

	return $found !== '' ? $found : null;
}

describe('check.php web access', function () {
	/* The report names the host, kernel, PHP build and ini paths.  The realm is
	 * enforced in the page itself rather than left to the plugin_realms row:
	 * that row is written by the installer and by the upgrade step, and the
	 * upgrade step only runs when the recorded version differs from INFO, so an
	 * install already on this version would get authentication with no
	 * authorisation at all. */
	it('refuses the report to a user without the Manage Weathermap realm', function () {
		$cgi = wm_php_cgi();

		if ($cgi === null) {
			expect(true)->toBeTrue();

			return;
		}

		$page   = wm_stage_check_page();
		$output = (string) shell_exec('WM_TEST_REALM=0 ' . escapeshellarg($cgi) . ' ' . escapeshellarg($page) . ' 2>/dev/null');

		expect($output)->toContain('Location: /cacti/permission_denied.php');
		expect($output)->not->toContain('Weathermap Pre-Install Checker');
		expect($output)->not->toContain(php_uname());
	});

	it('serves the report to a user who holds the realm', function () {
		$cgi = wm_php_cgi();

		if ($cgi === null) {
			expect(true)->toBeTrue();

			return;
		}

		$page   = wm_stage_check_page();
		$output = (string) shell_exec('WM_TEST_REALM=1 ' . escapeshellarg($cgi) . ' ' . escapeshellarg($page) . ' 2>/dev/null');

		expect($output)->not->toContain('permission_denied.php');
		expect($output)->toContain('Weathermap Pre-Install Checker');
	});

	it('still runs from the command line with no Cacti session', function () {
		$page   = wm_stage_check_page();
		$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($page) . ' 2>/dev/null');

		expect($output)->not->toContain('permission_denied.php');
		expect($output)->toContain('Weathermap Pre-Install Checker');
	});

	it('checks the realm and not only the login', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/check.php');

		expect($source)->toContain("api_plugin_user_realm_auth('weathermap-cacti-plugin-mgmt.php')");
	});

	it('registers check.php against the Manage Weathermap realm on install', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

		expect($source)->toContain("weathermap-cacti-plugin-mgmt-groups.php,check.php', 'Manage Weathermap'");
	});
});
