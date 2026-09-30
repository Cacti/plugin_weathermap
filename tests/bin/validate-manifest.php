<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Validates that manifest.json's "expected" array matches the plugin's    |
 | actual top-level tree, so the manifest that drives upgrade-time pruning  |
 | cannot silently drift. tests/, .git* and whitelisted (user-data) paths  |
 | are intentionally excluded. Exits non-zero on any drift.                |
 +-------------------------------------------------------------------------+
*/

$root         = dirname(__DIR__, 2);
$manifestPath = $root . '/manifest.json';

if (!is_readable($manifestPath)) {
	fwrite(STDERR, "manifest.json not found at plugin root\n");
	exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (!is_array($manifest) || !isset($manifest['expected']) || !is_array($manifest['expected'])) {
	fwrite(STDERR, "manifest.json is missing a valid 'expected' array\n");
	exit(1);
}

$whitelist    = isset($manifest['whitelist']) && is_array($manifest['whitelist']) ? $manifest['whitelist'] : [];
$whitelistTop = [];

foreach ($whitelist as $entry) {
	$entry = trim((string) $entry, '/');

	if ($entry !== '') {
		$whitelistTop[explode('/', $entry)[0]] = true;
	}
}

$actual = [];

foreach (scandir($root) as $entry) {
	if ($entry === '.' || $entry === '..' || $entry === 'tests') {
		continue;
	}

	if (strncmp($entry, '.git', 4) === 0 || isset($whitelistTop[$entry])) {
		continue;
	}

	$actual[] = is_dir($root . '/' . $entry) ? $entry . '/' : $entry;
}

$expected = $manifest['expected'];
sort($actual);
sort($expected);

$missing = array_values(array_diff($expected, $actual));
$extra   = array_values(array_diff($actual, $expected));

if ($missing === [] && $extra === []) {
	echo "manifest.json 'expected' matches the plugin tree.\n";
	exit(0);
}

if ($missing !== []) {
	fwrite(STDERR, "manifest.json lists 'expected' entries that are missing on disk:\n  " . implode("\n  ", $missing) . "\n");
}

if ($extra !== []) {
	fwrite(STDERR, "Plugin tree has top-level entries not in manifest.json 'expected'\n(add them to 'expected', or record them under 'tombstones'/'whitelist'):\n  " . implode("\n  ", $extra) . "\n");
}

exit(1);
