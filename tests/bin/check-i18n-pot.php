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
 * and comparing timestamps (which couples CI to one exact gettext toolchain),
 * this reconstructs the set of translatable strings xgettext would extract at
 * the merge-base and at HEAD and compares them. If that set changed, then
 * locales/po/cacti.pot must also be part of the pull request.
 *
 * The strings are recovered with PHP's own tokenizer rather than a line regex,
 * so that:
 *   - a change to surrounding code on a line that also holds an i18n call does
 *     not look like a string change (no false positive), and
 *   - a multi-line i18n call whose literal sits on its own line is still seen
 *     (no false negative).
 *
 * It is not this check's job to prove the template is byte-correct, only that
 * it was regenerated and committed when the translatable strings moved.
 *
 * Usage: php tests/bin/check-i18n-pot.php <base-ref>
 *
 * Exits 1 when a required pot update is missing, 2 on bad input, 0 otherwise.
 */

/*
 * The gettext keywords build_gettext.sh passes to xgettext, mapped to the
 * 1-based argument positions that form the pot entry (msgctxt/msgid/plural):
 *
 *   -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3
 *   -k__esc -k__esc_n:1,2 -k__esc_x:1c,2 -k__esc_xn:1c,2,3 -k__date
 *
 * A call only contributes an entry when every listed position is a literal
 * string (optionally a concatenation of literals), mirroring xgettext, which
 * silently ignores calls whose keyword argument is not a constant string.
 */
$i18n_keywords = [
	'__gettext' => [1],
	'__'        => [1],
	'__n'       => [1, 2],
	'__x'       => [1, 2],
	'__xn'      => [1, 2, 3],
	'__esc'     => [1],
	'__esc_n'   => [1, 2],
	'__esc_x'   => [1, 2],
	'__esc_xn'  => [1, 2, 3],
	'__date'    => [1],
];

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

/*
 * Compare HEAD against the point the branch diverged from the base so that
 * unrelated commits landing on the base after branch-off are not attributed to
 * this pull request. Fall back to the base ref itself if no merge-base exists
 * (e.g. an unrelated-history or very shallow checkout).
 */
$base_commit = git_capture_line('git merge-base ' . escapeshellarg($base_ref) . ' HEAD');

if ($base_commit === null) {
	$base_commit = $base_ref;
}

$base_strings = collect_strings($base_commit, $i18n_keywords);
$head_strings = collect_strings('HEAD', $i18n_keywords);

sort($base_strings);
sort($head_strings);

if ($base_strings === $head_strings) {
	fwrite(STDOUT, "No translatable-string changes detected; locales/po/cacti.pot update not required.\n");

	exit(0);
}

if (pot_updated($base_commit, $pot_path)) {
	fwrite(STDOUT, "Translatable strings changed and locales/po/cacti.pot is included in the pull request. OK.\n");

	exit(0);
}

$added   = array_values(array_diff($head_strings, $base_strings));
$removed = array_values(array_diff($base_strings, $head_strings));

fwrite(STDERR, "This pull request changes i18n strings but does not update locales/po/cacti.pot.\n");
fwrite(STDERR, "Run locales/build_gettext.sh and commit the regenerated locales/po/cacti.pot.\n");
fwrite(STDERR, "\n");

report_strings('Added translatable strings', $added);
report_strings('Removed translatable strings', $removed);

exit(1);

/**
 * Run a git command expected to print a single value and return it trimmed.
 *
 * @param string $command Fully-escaped git command line.
 *
 * @return string|null The first output line, or null when the command failed
 *                     or produced nothing.
 */
function git_capture_line($command) {
	$output = [];
	$status = 0;

	exec($command . ' 2>/dev/null', $output, $status);

	if ($status !== 0 || !isset($output[0]) || trim($output[0]) === '') {
		return null;
	}

	return trim($output[0]);
}

/**
 * Build the multiset of translatable strings xgettext would extract from the
 * template-feeding PHP files at a given revision.
 *
 * @param string $ref      Git revision to read the tree from.
 * @param array  $keywords Map of i18n keyword to contributing argument
 *                         positions.
 *
 * @return array<int, string> Canonical "keyword\x1farg...\x1farg" entries, one
 *                            per qualifying i18n call.
 */
function collect_strings($ref, $keywords) {
	$strings = [];

	foreach (template_files($ref) as $file) {
		$code = git_show($ref, $file);

		if ($code === null) {
			continue;
		}

		foreach (extract_calls($code, $keywords) as $entry) {
			$strings[] = $entry;
		}
	}

	return $strings;
}

/**
 * List the PHP files that feed build_gettext.sh at a revision, i.e. those no
 * more than one directory below the plugin root (`find . -maxdepth 2`).
 *
 * @param string $ref Git revision to read the tree from.
 *
 * @return array<int, string> Repository-relative PHP paths.
 */
function template_files($ref) {
	$output = [];
	$status = 0;

	exec('git ls-tree -r --name-only ' . escapeshellarg($ref), $output, $status);

	if ($status !== 0) {
		fwrite(STDERR, "git ls-tree failed for " . $ref . "\n");

		exit(2);
	}

	$files = [];

	foreach ($output as $file) {
		$file = trim($file);

		if (feeds_template($file)) {
			$files[] = $file;
		}
	}

	return $files;
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
 * Read a file's contents at a revision.
 *
 * @param string $ref  Git revision.
 * @param string $file Repository-relative path.
 *
 * @return string|null File contents, or null when the file is absent there.
 */
function git_show($ref, $file) {
	$descriptors = [
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$process = proc_open('git show ' . escapeshellarg($ref . ':' . $file), $descriptors, $pipes);

	if (!is_resource($process)) {
		return null;
	}

	$code = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$status = proc_close($process);

	if ($status !== 0) {
		return null;
	}

	return $code;
}

/**
 * Extract the translatable-string entries from PHP source using the tokenizer.
 *
 * @param string $code     PHP source.
 * @param array  $keywords Map of i18n keyword to contributing argument
 *                         positions.
 *
 * @return array<int, string> Canonical entries for each qualifying i18n call.
 */
function extract_calls($code, $keywords) {
	$tokens = @token_get_all($code);
	$count  = count($tokens);
	$calls  = [];

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token) || $token[0] !== T_STRING || !isset($keywords[$token[1]])) {
			continue;
		}

		$prev = previous_significant($tokens, $i);

		if ($prev !== null && is_array($prev) && in_array($prev[0], call_disqualifiers(), true)) {
			continue;
		}

		$open = next_significant_index($tokens, $i);

		if ($open === null || $tokens[$open] !== '(') {
			continue;
		}

		$args  = parse_arguments($tokens, $open);
		$parts = [];
		$ok    = true;

		foreach ($keywords[$token[1]] as $position) {
			if (!isset($args[$position - 1])) {
				$ok = false;

				break;
			}

			$literal = literal_value($args[$position - 1]);

			if ($literal === null) {
				$ok = false;

				break;
			}

			$parts[] = $literal;
		}

		if ($ok) {
			$calls[] = $token[1] . "\x1f" . implode("\x1f", $parts);
		}
	}

	return $calls;
}

/**
 * Token types that, immediately before a keyword, mean it is a method call or
 * declaration rather than the i18n helper (e.g. $o->__(), C::__(), function __).
 *
 * @return array<int, int> Token id constants.
 */
function call_disqualifiers() {
	$ids = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW];

	if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
		$ids[] = T_NULLSAFE_OBJECT_OPERATOR;
	}

	return $ids;
}

/**
 * The significant token preceding a position (skipping whitespace/comments).
 *
 * @param array $tokens token_get_all() output.
 * @param int   $index  Position to look back from.
 *
 * @return array|string|null The token, or null at the start of the stream.
 */
function previous_significant($tokens, $index) {
	for ($i = $index - 1; $i >= 0; $i--) {
		if (is_significant($tokens[$i])) {
			return $tokens[$i];
		}
	}

	return null;
}

/**
 * Index of the significant token following a position.
 *
 * @param array $tokens token_get_all() output.
 * @param int   $index  Position to look forward from.
 *
 * @return int|null Index of the next significant token, or null at end.
 */
function next_significant_index($tokens, $index) {
	$count = count($tokens);

	for ($i = $index + 1; $i < $count; $i++) {
		if (is_significant($tokens[$i])) {
			return $i;
		}
	}

	return null;
}

/**
 * Whether a token carries code (not whitespace or a comment).
 *
 * @param array|string $token A token_get_all() element.
 *
 * @return bool
 */
function is_significant($token) {
	if (!is_array($token)) {
		return true;
	}

	return !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

/**
 * Split a call's top-level arguments, starting at the opening parenthesis.
 *
 * @param array $tokens token_get_all() output.
 * @param int   $open   Index of the '(' that opens the argument list.
 *
 * @return array<int, array> One token list per argument (empty list for none).
 */
function parse_arguments($tokens, $open) {
	$count   = count($tokens);
	$depth   = 0;
	$args    = [];
	$current = [];

	for ($i = $open; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			if ($token === '(' || $token === '[' || $token === '{') {
				$depth++;

				if ($depth === 1) {
					continue;
				}
			} elseif ($token === ')' || $token === ']' || $token === '}') {
				$depth--;

				if ($depth === 0) {
					$args[] = $current;

					break;
				}
			} elseif ($token === ',' && $depth === 1) {
				$args[]  = $current;
				$current = [];

				continue;
			}
		}

		if ($depth >= 1) {
			$current[] = $token;
		}
	}

	return $args;
}

/**
 * Resolve an argument to its string value when it is a literal string, or a
 * concatenation of literal strings, matching what xgettext can extract.
 *
 * @param array $tokens The argument's token list.
 *
 * @return string|null The decoded string, or null when not a constant string.
 */
function literal_value($tokens) {
	$parts         = [];
	$expect_string = true;

	foreach ($tokens as $token) {
		if (!is_significant($token)) {
			continue;
		}

		if ($expect_string) {
			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$parts[]       = decode_string($token[1]);
				$expect_string = false;

				continue;
			}

			return null;
		}

		if ($token === '.') {
			$expect_string = true;

			continue;
		}

		return null;
	}

	if ($expect_string && $parts !== []) {
		return null;
	}

	if ($parts === []) {
		return null;
	}

	return implode('', $parts);
}

/**
 * Decode a single- or double-quoted PHP string literal to its runtime value so
 * that a pure quote-style change is not mistaken for a content change. The
 * tokenizer only emits T_CONSTANT_ENCAPSED_STRING for strings without
 * interpolation, so no variable expansion is required here.
 *
 * @param string $raw The literal including its surrounding quotes.
 *
 * @return string The decoded value.
 */
function decode_string($raw) {
	if (strlen($raw) < 2) {
		return $raw;
	}

	$quote = $raw[0];
	$inner = substr($raw, 1, -1);

	if ($quote === "'") {
		return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
	}

	return preg_replace_callback(
		'/\\\\(?:x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|u\{[0-9A-Fa-f]+\}|.)/',
		'decode_double_quoted_escape',
		$inner
	);
}

/**
 * Expand one backslash escape from a double-quoted string literal.
 *
 * @param array $match preg_replace_callback match; $match[0] is the escape.
 *
 * @return string The expanded character(s).
 */
function decode_double_quoted_escape($match) {
	$escape = $match[0];
	$char   = $escape[1];

	$simple = [
		'n'  => "\n",
		't'  => "\t",
		'r'  => "\r",
		'v'  => "\v",
		'f'  => "\f",
		'e'  => "\e",
		'"'  => '"',
		'$'  => '$',
		'\\' => '\\',
	];

	if (isset($simple[$char])) {
		return $simple[$char];
	}

	if ($char === 'x') {
		return chr(hexdec(substr($escape, 2)));
	}

	if ($char === 'u') {
		$code = hexdec(substr($escape, 3, -1));

		if (function_exists('mb_chr')) {
			return mb_chr($code, 'UTF-8');
		}

		return $escape;
	}

	if ($char >= '0' && $char <= '7') {
		return chr(octdec(substr($escape, 1)) & 0xFF);
	}

	return $escape;
}

/**
 * Whether the pull request already touches the gettext template.
 *
 * @param string $base_commit Commit to diff HEAD against.
 * @param string $pot_path    Repository-relative path of cacti.pot.
 *
 * @return bool
 */
function pot_updated($base_commit, $pot_path) {
	$command = 'git diff --no-ext-diff --name-only --no-color ' . escapeshellarg($base_commit) . ' HEAD';
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

/**
 * Print a labelled, de-duplicated list of decoded i18n entries for diagnostics.
 *
 * @param string             $label   Human-readable heading.
 * @param array<int, string> $entries Canonical "keyword\x1farg..." entries.
 *
 * @return void
 */
function report_strings($label, $entries) {
	if ($entries === []) {
		return;
	}

	fwrite(STDERR, $label . ":\n");

	foreach (array_unique($entries) as $entry) {
		$fields  = explode("\x1f", $entry);
		$keyword = array_shift($fields);
		$shown   = array_map(function ($field) {
			return '"' . $field . '"';
		}, $fields);

		fwrite(STDERR, '  - ' . $keyword . '(' . implode(', ', $shown) . ")\n");
	}

	fwrite(STDERR, "\n");
}
