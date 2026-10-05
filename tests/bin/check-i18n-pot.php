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
 * Fail a pull request that changes translatable strings without refreshing the
 * gettext template.
 *
 * locales/po/cacti.pot is produced by locales/build_gettext.sh, which runs
 * xgettext over `find . -maxdepth 2 -name '*.php'` extracting the Cacti i18n
 * helpers (__(), __n(), __esc(), ...). Rather than regenerating the template
 * and comparing timestamps, this inspects the branch diff: if any changed line
 * adds, removes, or edits one of those i18n calls in a file that feeds the
 * template, then locales/po/cacti.pot must also be part of the pull request.
 *
 * It is not this check's job to prove the template is byte-correct, only that
 * it was regenerated and committed when the translatable strings moved.
 *
 * Usage: php tests/bin/check-i18n-pot.php <base-ref>
 *
 * Exits 1 when a required pot update is missing, 2 on bad input, 0 otherwise.
 */

/*
 * The gettext keywords build_gettext.sh passes to xgettext. A changed line is
 * only interesting when it contains one of these calls. The negative lookbehind
 * keeps PHP magic methods such as __construct()/__toString() out of the match.
 */
$i18n_pattern = '/(?<![A-Za-z0-9_])__(?:gettext|date|esc_xn|esc_x|esc_n|esc|xn|x|n)?\s*\(/';

/* Repository-relative path of the template the plugin must keep in sync. */
$pot_path = 'locales/po/cacti.pot';

$base_ref = isset($argv[1]) ? trim($argv[1]) : '';

if ($base_ref === '') {
	$env = getenv('GITHUB_BASE_REF');
	$base_ref = ($env !== false) ? trim($env) : '';
}

if ($base_ref === '') {
	fwrite(STDOUT, "No base ref supplied; skipping i18n template check (not a pull request).\n");

	exit(0);
}

/*
 * Make sure the base commit is present. With fetch-depth: 0 it already is, but
 * a branch-name ref may still need fetching on a shallow checkout.
 */
$probe = [];
$probe_status = 0;
exec('git rev-parse --verify --quiet ' . escapeshellarg($base_ref . '^{commit}'), $probe, $probe_status);

if ($probe_status !== 0) {
	$ignore = [];
	$ignore_status = 0;
	exec('git fetch --quiet --no-tags --depth=200 origin ' . escapeshellarg($base_ref), $ignore, $ignore_status);
}

$range = escapeshellarg($base_ref) . '...HEAD';

/**
 * Normalise a source line so that a pure re-indent or reflow of an i18n call
 * does not read as a content change.
 *
 * @param string $line Raw diff line with its leading +/- already removed.
 *
 * @return string Whitespace-collapsed, trimmed line.
 */
function normalise_line($line) {
	return preg_replace('/\s+/', ' ', trim($line));
}

/**
 * Collect the i18n-bearing lines each side of the diff adds or removes, limited
 * to files that actually feed the gettext template.
 *
 * A file feeds the template when it is a PHP file no deeper than one directory
 * below the plugin root, mirroring `find . -maxdepth 2 -name '*.php'`.
 *
 * @param string $range        Git diff range expression.
 * @param string $i18n_pattern Regex matching the Cacti i18n helper calls.
 *
 * @return array{added: array<int, string>, removed: array<int, string>, files: array<string, bool>}
 */
function collect_i18n_changes($range, $i18n_pattern) {
	$command = 'git diff --no-ext-diff --unified=0 --no-color ' . $range . ' -- "*.php"';
	$output  = [];
	$status  = 0;

	exec($command, $output, $status);

	if ($status !== 0) {
		fwrite(STDERR, "git diff failed\n");

		exit(2);
	}

	$added    = [];
	$removed  = [];
	$files    = [];
	$old_file = null;
	$new_file = null;

	foreach ($output as $line) {
		if (strncmp($line, '--- ', 4) === 0) {
			$old_file = diff_path(substr($line, 4));

			continue;
		}

		if (strncmp($line, '+++ ', 4) === 0) {
			$new_file = diff_path(substr($line, 4));

			continue;
		}

		if ($line === '' || $line[0] !== '+' && $line[0] !== '-') {
			continue;
		}

		if (strncmp($line, '+++', 3) === 0 || strncmp($line, '---', 3) === 0) {
			continue;
		}

		$added_line = ($line[0] === '+');
		$file       = $added_line ? $new_file : $old_file;

		if ($file === null || !feeds_template($file)) {
			continue;
		}

		$content = substr($line, 1);

		if (!preg_match($i18n_pattern, $content)) {
			continue;
		}

		$files[$file] = true;

		if ($added_line) {
			$added[] = normalise_line($content);
		} else {
			$removed[] = normalise_line($content);
		}
	}

	return ['added' => $added, 'removed' => $removed, 'files' => $files];
}

/**
 * Turn a diff header path ("a/foo.php", "b/foo.php" or "/dev/null") into a plain
 * repository-relative path, or null when the side does not exist.
 *
 * @param string $raw Path portion following the "--- "/"+++ " marker.
 *
 * @return string|null Repository-relative path, or null for /dev/null.
 */
function diff_path($raw) {
	$raw = trim($raw);

	if ($raw === '/dev/null') {
		return null;
	}

	if (strncmp($raw, 'a/', 2) === 0 || strncmp($raw, 'b/', 2) === 0) {
		$raw = substr($raw, 2);
	}

	return $raw;
}

/**
 * Whether a path is scanned by build_gettext.sh, i.e. a PHP file no more than
 * one directory below the plugin root.
 *
 * @param string $file Repository-relative path.
 *
 * @return bool
 */
function feeds_template($file) {
	if (substr($file, -4) !== '.php') {
		return false;
	}

	return substr_count($file, '/') <= 1;
}

/**
 * Whether the pull request already touches the gettext template.
 *
 * @param string $range    Git diff range expression.
 * @param string $pot_path Repository-relative path of cacti.pot.
 *
 * @return bool
 */
function pot_updated($range, $pot_path) {
	$command = 'git diff --no-ext-diff --name-only --no-color ' . $range;
	$output  = [];
	$status  = 0;

	exec($command, $output, $status);

	if ($status !== 0) {
		fwrite(STDERR, "git diff failed\n");

		exit(2);
	}

	foreach ($output as $name) {
		if (trim($name) === $pot_path) {
			return true;
		}
	}

	return false;
}

$changes = collect_i18n_changes($range, $i18n_pattern);

sort($changes['added']);
sort($changes['removed']);

$i18n_changed = ($changes['added'] !== $changes['removed']);

if (!$i18n_changed) {
	fwrite(STDOUT, "No translatable-string changes detected in the diff; locales/po/cacti.pot update not required.\n");

	exit(0);
}

if (pot_updated($range, $pot_path)) {
	fwrite(STDOUT, "Translatable strings changed and locales/po/cacti.pot is included in the pull request. OK.\n");

	exit(0);
}

$files = array_keys($changes['files']);
sort($files);

fwrite(STDERR, "This pull request changes i18n strings but does not update locales/po/cacti.pot.\n");
fwrite(STDERR, "Run locales/build_gettext.sh and commit the regenerated locales/po/cacti.pot.\n");
fwrite(STDERR, "\n");
fwrite(STDERR, "Files with changed i18n calls:\n");

foreach ($files as $file) {
	fwrite(STDERR, "  - $file\n");
}

exit(1);
