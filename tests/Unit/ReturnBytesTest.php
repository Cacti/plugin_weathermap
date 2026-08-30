<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* check.php runs its report at include time, so the one function under test is
 * lifted out of it into a temporary file rather than including the whole page. */
if (!function_exists('return_bytes')) {
	$checkSource = file_get_contents(dirname(__DIR__, 2) . '/check.php');

	if (preg_match('/^function return_bytes\(.*?^}/ms', $checkSource, $fn) !== 1) {
		throw new RuntimeException('return_bytes() could not be located in check.php');
	}

	/* Write to the exact path tempnam() created and reserved.  Appending an
	 * extension would point at a path nothing holds, and would leak the file
	 * tempnam() did create. */
	$extracted = tempnam(sys_get_temp_dir(), 'wmcheck');
	file_put_contents($extracted, "<?php\n" . $fn[0] . "\n");
	require_once $extracted;
	unlink($extracted);
}

describe('return_bytes()', function () {
	/* An ini value such as "256M" is a leading-numeric string.  Multiplying it
	 * without dropping the suffix raises "A non-numeric value encountered" on
	 * PHP 8, once per unit in the fall-through, on every call. */
	it('converts each unit suffix', function () {
		expect(return_bytes('512K'))->toBe(524288);
		expect(return_bytes('256M'))->toBe(268435456);
		expect(return_bytes('1G'))->toBe(1073741824);
	});

	it('accepts a lower case suffix', function () {
		expect(return_bytes('8m'))->toBe(8388608);
	});

	it('passes a plain byte count through', function () {
		expect(return_bytes('1024'))->toBe(1024);
	});

	it('trims surrounding whitespace', function () {
		expect(return_bytes('  8M  '))->toBe(8388608);
	});

	it('treats an empty value as zero', function () {
		expect(return_bytes(''))->toBe(0);
	});

	it('handles the unlimited memory_limit sentinel', function () {
		expect(return_bytes('-1'))->toBe(-1);
	});

	it('raises no warning or notice for a suffixed value', function () {
		$raised = [];

		set_error_handler(function ($errno, $errstr) use (&$raised) {
			$raised[] = $errstr;

			return true;
		});

		return_bytes('256M');

		restore_error_handler();

		expect($raised)->toBe([]);
	});
});
