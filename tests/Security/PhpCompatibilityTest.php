<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.3+/8.4-only syntax.
 * This plugin's floor version is PHP 8.2, matching the shared CI test
 * matrix (php: ['8.2', '8.3', '8.4']).
 */

// Discovered recursively so new production PHP files are covered automatically.
$pluginRoot = realpath(__DIR__ . '/../..');
$files      = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}

	$relativeFile = ltrim(str_replace($pluginRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
	$relativeFile = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFile);

	if (strpos($relativeFile, 'tests/') === 0 || strpos($relativeFile, 'vendor/') === 0) {
		continue;
	}

	$files[] = $relativeFile;
}

sort($files);

function plugin_test_read_source_file($relativeFile) {
	$path = realpath(__DIR__ . '/../../' . $relativeFile);

	if ($path === false) {
		throw new RuntimeException("Unable to resolve required plugin source");
	}

	$contents = file_get_contents($path);

	if ($contents === false) {
		throw new RuntimeException("Unable to read required plugin source");
	}

	return $contents;
}

it('does not use asymmetric visibility (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source_file($relativeFile);

		expect(preg_match('/\bprivate\s*\(\s*set\s*\)|\bprotected\s*\(\s*set\s*\)/', $contents))->toBe(0,
			"{$relativeFile} uses asymmetric visibility which requires PHP 8.4"
		);
	}
});

it('does not use the #[Override] attribute (PHP 8.3)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source_file($relativeFile);

		expect(preg_match('/#\[\s*Override\s*\]/i', $contents))->toBe(0,
			"{$relativeFile} uses #[Override] which requires PHP 8.3"
		);
	}
});

it('does not use the #[Deprecated] attribute (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source_file($relativeFile);

		expect(preg_match('/#\[\s*Deprecated\b/i', $contents))->toBe(0,
			"{$relativeFile} uses #[Deprecated] which requires PHP 8.4"
		);
	}
});

it('does not use json_validate() (PHP 8.3)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source_file($relativeFile);

		expect(preg_match('/\bjson_validate\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses json_validate() which requires PHP 8.3"
		);
	}
});

it('does not use array_find()/array_any()/array_all() (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source_file($relativeFile);

		expect(preg_match('/\barray_(find|any|all)\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses array_find()/array_any()/array_all() which requires PHP 8.4"
		);
	}
});
