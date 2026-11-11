<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

/* map_clean_title() strips control characters from a duplicate-map title but
 * not its length.  ReadConfig() parses the config with fgets($fd, 4096), so a
 * title long enough to push "TITLE <title>" past that chunk boundary would have
 * its overflow read as a separate directive.  map_duplicate() must reject a
 * cleaned title longer than 4088 bytes before writing the config, matching
 * newMap(). */
function wm_run_duplicate_boundary(string $mode): array {
	$command = escapeshellarg(PHP_BINARY) . ' '
		. escapeshellarg(dirname(__DIR__) . '/Support/MapDuplicateTitleBoundaryPayload.php') . ' '
		. escapeshellarg($mode);

	$lines = [];
	exec($command, $lines, $status);

	expect($status)->toBe(0);

	return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
}

describe('map_duplicate title chunk-boundary guard', function (): void {
	it('writes a 4088-byte title that stays within one fgets chunk', function (): void {
		$result = wm_run_duplicate_boundary('max');

		expect($result['reached'])->toBeTrue();
		// "TITLE " (6) + 4088 bytes = 4094, so "TITLE <title>\n" is 4095 bytes,
		// the most that fits in a single fgets($fd, 4096) read.
		expect($result['titleLineLen'])->toBe(4094);
	});

	it('rejects a 4089-byte title before writing the config', function (): void {
		$result = wm_run_duplicate_boundary('over');

		expect($result['reached'])->toBeFalse();
	});

	it('rejects an oversized title that carries an injected directive', function (): void {
		$result = wm_run_duplicate_boundary('inject');

		expect($result['reached'])->toBeFalse();
	});

	it('rejects a title whose source-config indentation pushes it past the boundary', function (): void {
		// 4086 bytes clears the length pre-check, but 20 bytes of preserved
		// leading whitespace on the source TITLE line carries the tail across
		// the fgets chunk boundary; the post-substitution line check catches it.
		$result = wm_run_duplicate_boundary('indent');

		expect($result['reached'])->toBeFalse();
	});
});
