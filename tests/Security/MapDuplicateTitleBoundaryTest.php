<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/map-title.php';

/* map_clean_title() strips control characters from a duplicated map title but
 * not its length.  ReadConfig() parses the config with fgets($fd, 4096) and
 * trims each chunk, so a title long enough to push a rewritten line past that
 * boundary would have its tail parsed as a separate directive.
 * map_duplicate_title_overflows_line() is the guard map_duplicate() uses to
 * reject such a copy before it writes the config. */
describe('map_duplicate_title_overflows_line()', function (): void {
	$source = "WIDTH 100\nTITLE Original Map\nNODE a\n";

	it('accepts a 4088-byte title on a column-0 TITLE line', function () use ($source): void {
		// "TITLE " (6) + 4088 = 4094, the longest line fgets($fd, 4096) still
		// reads whole with its newline.
		expect(map_duplicate_title_overflows_line('Original Map', str_repeat('T', 4088), $source))->toBeFalse();
	});

	it('rejects a 4089-byte title on a column-0 TITLE line', function () use ($source): void {
		expect(map_duplicate_title_overflows_line('Original Map', str_repeat('T', 4089), $source))->toBeTrue();
	});

	it('rejects a title whose source-config indentation pushes the line over', function (): void {
		// 4088 bytes clears a title-only length check, but 20 bytes of preserved
		// leading whitespace carry the rewritten line past the boundary.
		$indented = "WIDTH 100\n" . str_repeat(' ', 20) . "TITLE Original Map\nNODE a\n";

		expect(map_duplicate_title_overflows_line('Original Map', str_repeat('T', 4088), $indented))->toBeTrue();
	});

	it('rejects a title that overflows a non-TITLE line holding the marker', function (): void {
		// str_replace() rewrites "TITLE <old>" inside a comment too; "# TITLE "
		// (8) + 4088 = 4096 overflows even though the real TITLE line (4094) does
		// not, and the split tail would parse as a directive past the '#'.
		$commented = "# TITLE Original Map\nTITLE Original Map\nNODE a\n";

		expect(map_duplicate_title_overflows_line('Original Map', str_repeat('T', 4088), $commented))->toBeTrue();
	});

	it('leaves an ordinary short title alone', function () use ($source): void {
		expect(map_duplicate_title_overflows_line('Original Map', 'Original Map Copy', $source))->toBeFalse();
	});

	it('does nothing when the marker is absent from the config', function (): void {
		expect(map_duplicate_title_overflows_line('Missing Title', str_repeat('T', 5000), "WIDTH 100\nNODE a\n"))->toBeFalse();
	});

	it('returns false for non-string arguments', function (mixed $old, mixed $new, mixed $contents): void {
		expect(map_duplicate_title_overflows_line($old, $new, $contents))->toBeFalse();
	})->with([
		'non-string contents' => ['Original Map', 'x', null],
		'non-string old'      => [null, 'x', 'TITLE x'],
		'non-string new'      => ['Original Map', ['x'], 'TITLE Original Map'],
	]);
});
