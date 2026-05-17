<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

describe('weathermap settings persistence', function (): void {
	it('does not contain the REPLACE INFO typo', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		expect($source)->not->toContain('REPLACE INFO');
	});

	it('uses REPLACE INTO weathermap_settings for all three branches', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		expect($source)->toContain('REPLACE INTO weathermap_settings');

		// All three conditional branches in weathermap_setting_save write to weathermap_settings.
		$count = substr_count($source, 'REPLACE INTO weathermap_settings');
		expect($count)->toBe(3);
	});

	it('uses db_execute_prepared (not raw db_execute) for all three REPLACE INTO calls', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		$lines = explode("\n", $source);

		// Collect lines containing a REPLACE INTO weathermap_settings statement,
		// then verify that every such block is introduced by db_execute_prepared.
		// The SQL spans multiple lines, so we check the preceding call site context
		// by scanning for db_execute_prepared that is followed (within three lines)
		// by a REPLACE INTO weathermap_settings fragment.
		$replaceBlocks = [];
		foreach ($lines as $n => $line) {
			if (str_contains($line, "db_execute_prepared('REPLACE INTO weathermap_settings")) {
				$replaceBlocks[] = $n;
			}
		}

		expect(count($replaceBlocks))->toBe(3, 'Expected exactly three db_execute_prepared REPLACE INTO weathermap_settings calls');
	});

	it('uses db_execute_prepared with ? placeholders for both UPDATE statements in weathermap_group_move()', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		$lines = explode("\n", $source);

		$updatesPrepared = 0;
		$updatesRaw      = 0;

		foreach ($lines as $line) {
			$trimmed = ltrim($line);

			// Skip comments.
			if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#')) {
				continue;
			}

			if (str_contains($line, 'UPDATE weathermap_groups')) {
				if (str_contains($line, 'db_execute_prepared')) {
					$updatesPrepared++;
				} else {
					$updatesRaw++;
				}
			}
		}

		expect($updatesRaw)->toBe(0, 'Found raw db_execute UPDATE calls in weathermap_group_move()');
		expect($updatesPrepared)->toBeGreaterThanOrEqual(2, 'Expected at least two db_execute_prepared UPDATE calls');
	});

	it('does not interpolate variables directly into the REPLACE INTO SQL strings', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

		$lines = explode("\n", $source);

		foreach ($lines as $line) {
			if (!str_contains($line, 'REPLACE INTO weathermap_settings')) {
				continue;
			}

			// The SQL literal itself must not embed a PHP variable.
			expect($line)->not->toMatch('/REPLACE INTO weathermap_settings.*\$[a-zA-Z_]/');
		}
	});
});
