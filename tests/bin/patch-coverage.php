<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Report line coverage for the lines a branch changes.
 *
 * Whole-file coverage is not a useful gate here: most of the plugin only runs
 * inside a live Cacti, so the repository figure would sit near zero no matter
 * how well a change is tested.  What a reviewer wants to know is whether the
 * lines this branch adds are exercised, which is what this measures.
 *
 * Usage: php tests/bin/patch-coverage.php <clover.xml> <base-ref> [min-percent]
 *
 * Exits 1 if coverage is below the threshold, 2 on bad input.
 */

if ($argc < 3) {
	fwrite(STDERR, "usage: patch-coverage.php <clover.xml> <base-ref> [min-percent]\n");

	exit(2);
}

$clover_path = $argv[1];
$base_ref    = $argv[2];
$minimum     = isset($argv[3]) ? (float) $argv[3] : 100.0;

if (!is_readable($clover_path)) {
	fwrite(STDERR, "cannot read coverage report: $clover_path\n");

	exit(2);
}

/**
 * Line numbers each measured file changed, keyed by repository-relative path.
 *
 * Only added and modified lines count. Deletions have nothing left to cover,
 * and context lines were not part of this change.
 *
 * Paths stay repository-relative so the report can be produced in a container
 * and evaluated on the host, where the absolute paths differ.
 *
 * @param string $base_ref Git ref to diff against.
 *
 * @return array<string, array<int, bool>>
 */
function changed_lines($base_ref) {
	$command = 'git diff --no-ext-diff --unified=0 --no-color --diff-filter=AM ' . escapeshellarg($base_ref) . '...HEAD -- "*.php"';
	$output  = [];
	$status  = 0;

	$last_line = exec($command, $output, $status);

	if ($last_line === false || $status !== 0) {
		fwrite(STDERR, "git diff failed\n");

		exit(2);
	}

	$diff = implode("\n", $output);

	$changed = [];
	$file    = null;

	foreach (explode("\n", $diff) as $line) {
		if (strncmp($line, '+++ b/', 6) === 0) {
			$file           = substr($line, 6);

			if (strncmp($file, 'tests/', 6) === 0) {
				$file = null;

				continue;
			}

			$changed[$file] = [];
		} elseif (strncmp($line, '@@', 2) === 0 && $file !== null) {
			if (preg_match('/\+(\d+)(?:,(\d+))?/', $line, $match)) {
				$start = (int) $match[1];
				$count = isset($match[2]) ? (int) $match[2] : 1;

				for ($i = 0; $i < $count; $i++) {
					$changed[$file][$start + $i] = true;
				}
			}
		}
	}

	// Drop files whose only diff is deleted lines: a pure deletion adds no
	// lines to exercise, so it has nothing to measure and must not trip the
	// "changed production file absent from Clover" gate.
	return array_filter($changed, static fn ($lines) => $lines !== []);
}

$changed = changed_lines($base_ref);
$clover  = simplexml_load_file($clover_path);

if ($clover === false) {
	fwrite(STDERR, "cannot parse coverage report: $clover_path\n");

	exit(2);
}

$covered  = 0;
$total    = 0;
$missing  = [];
$measured = [];

foreach ($clover->xpath('//file') as $file) {
	$path     = (string) $file['name'];
	$relative = null;

	foreach (array_keys($changed) as $candidate) {
		if ($path === $candidate || substr($path, -strlen('/' . $candidate)) === '/' . $candidate) {
			$relative = $candidate;

			break;
		}
	}

	if ($relative === null) {
		continue;
	}

	$measured[$relative] = true;

	foreach ($file->line as $line) {
		$number = (int) $line['num'];

		// Only statement lines are measurable; method markers double-count.
		if ((string) $line['type'] !== 'stmt' || !isset($changed[$relative][$number])) {
			continue;
		}

		$total++;

		if ((int) $line['count'] > 0) {
			$covered++;
		} else {
			$missing[] = $relative . ':' . $number;
		}
	}
}

/*
 * Production PHP entry points that cannot safely be loaded into the isolated
 * unit process (CLI/daemon/web entry points that chdir + include auth.php,
 * do pcntl signal handling, or execute at the top level) belong here, each
 * with a one-line justification. Keep the exception explicit: any newly
 * changed production PHP file must either appear in Clover or be added here.
 *
 * Empty by default; add entries per repository as the need arises.
 */
$unmeasured_allowlist = [
	// Web UI entry point: top-level include of ../../include/auth.php plus a
	// switch (get_request_var('action')) dispatch that runs at load, so it
	// cannot load in the isolated unit process.
	'weathermap-cacti-plugin.php',
	// Web UI entry point: top-level include of ../../include/auth.php plus
	// request-var dispatch, so it cannot load in the isolated unit process.
	'weathermap-cacti-plugin-mgmt.php',
	// Editor web entry point: top-level include of ../../include/auth.php that
	// emits the editor HTML at the top level, so it cannot load in the isolated
	// unit process.
	'weathermap-cacti-plugin-editor.php',
	// Schema provisioning relocated verbatim from setup.php; its data-migration
	// branches (column-exists upgrades, duplicate-row cleanup) are not reachable
	// from the isolated unit process, though the table creation is exercised by
	// WeathermapInstallHooksTest.
	'includes/database.php',
	// Editor map-mutation actions: every function instantiates the 4,500-line
	// WeatherMap engine (new WeatherMap + ReadConfig/WriteConfig), which cannot
	// load in the isolated unit process - it redefines the IN/OUT constants and
	// collides with the datasource test harness's WeatherMapDataSource stub. The
	// editor save/delete workflow is covered by the Browser/E2E suites instead.
	'lib/editor.actions.php',
];
$unmeasured            = array_values(array_diff(array_keys($changed), array_keys($measured)));
$unexpected_unmeasured = array_values(array_diff($unmeasured, $unmeasured_allowlist));

if ($unmeasured !== []) {
	print "Changed production PHP files absent from Clover:\n  " . implode("\n  ", $unmeasured) . "\n";
}

if ($unexpected_unmeasured !== []) {
	print "FAIL: changed production PHP files are not measured or allowlisted:\n  "
		. implode("\n  ", $unexpected_unmeasured) . "\n";

	exit(1);
}

if ($total === 0) {
	print "Patch coverage: no measured lines changed.\n";

	exit(0);
}

$percent = ($covered / $total) * 100;

printf("Patch coverage: %.2f%% (%d/%d lines)\n", $percent, $covered, $total);

if ($missing !== []) {
	print "Uncovered changed lines:\n  " . implode("\n  ", $missing) . "\n";
}

if ($percent + 0.005 < $minimum) {
	printf("FAIL: below the %.2f%% minimum.\n", $minimum);

	exit(1);
}

exit(0);
