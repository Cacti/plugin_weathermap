<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the argument text of every call to one of the named db_ helpers.
 *
 * The scan walks forward from each call site balancing parentheses so that a
 * statement broken across several lines is returned as one string.  Quoted
 * parentheses are not tracked; SQL that unbalances them would be rejected by
 * MySQL long before this test sees it.
 *
 * @param  string   $source PHP source to scan
 * @param  string   $names  alternation of helper names, without the db_ prefix
 * @return string[] one entry per call, holding that call's argument text
 */
function wm_collect_db_call_args($source, $names) {
	$calls  = [];
	$offset = 0;

	while (preg_match('/\bdb_(?:' . $names . ')\s*\(/', $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
		$start = $m[0][1] + strlen($m[0][0]);
		$depth = 1;
		$pos   = $start;
		$len   = strlen($source);

		while ($pos < $len && $depth > 0) {
			if ($source[$pos] === '(') {
				$depth++;
			} elseif ($source[$pos] === ')') {
				$depth--;
			}

			$pos++;
		}

		$calls[] = substr($source, $start, $pos - $start - 1);
		$offset  = $pos;
	}

	return $calls;
}

describe('prepared statement consistency in weathermap', function () {
	$targetFiles = [
		'cli/cacti-mapper.php',
		'lib/WeatherMap.class.php',
		'lib/WeatherMap.functions.php',
		'lib/datasources/WeatherMapDataSource_fping.php',
		'lib/datasources/WeatherMapDataSource_rrd.php',
		'lib/editor.inc.php',
	];

	it('never builds SQL from variables in an unprepared db call', function () use ($targetFiles) {
		/* A raw db_ helper is only dangerous when its SQL is assembled from a
		 * variable.  A fully static query carries no user input and needs no
		 * placeholder, so requiring _prepared there would be noise. */
		$offenders = [];

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			/* Strip the _prepared variants first so the raw scan cannot match them. */
			$raw = preg_replace('/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/', 'db_prepared_call(', $contents);

			foreach (wm_collect_db_call_args($raw, 'execute|fetch_row|fetch_assoc|fetch_cell') as $args) {
				if (preg_match('/\$[a-zA-Z_]/', $args)) {
					$offenders[] = $relativeFile . ': ' . trim(preg_replace('/\s+/', ' ', substr($args, 0, 80)));
				}
			}
		}

		expect($offenders)->toBe([], "Unprepared db calls built from variables:\n" . implode("\n", $offenders));
	});

	it('uses parameterized placeholders not string interpolation in SQL', function () use ($targetFiles) {
		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines           = explode("\n", $contents);
			$interpolatedSql = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
					continue;
				}

				// Detect _prepared calls with $ interpolation instead of ? placeholders
				if (preg_match('/_prepared\s*\(/', $line) && preg_match('/\$[a-zA-Z_]/', $line)) {
					// Allow array($var) param binding but flag "WHERE id = $var"
					if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE|WHERE|SET|FROM|JOIN).*\$/', $line)) {
						$interpolatedSql++;
					}
				}
			}

			expect($interpolatedSql)->toBe(0,
				"File {$relativeFile} has SQL interpolation in prepared calls"
			);
		}
	});
});
