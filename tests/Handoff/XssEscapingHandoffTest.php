<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

describe('XSS escaping handoff at output boundaries', function (): void {
	describe('weathermap-cacti-plugin.php', function (): void {
		it('passes $maptitle through html_escape at both title output locations', function (): void {
			$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

			// Line ~408: single-map view title row.
			expect($source)->toContain("html_escape(\$maptitle)");

			// Both occurrences must be present (single-map and cycle/thumbnail views).
			$count = substr_count($source, 'html_escape($maptitle)');
			expect($count)->toBeGreaterThanOrEqual(2, 'html_escape($maptitle) must appear at least twice');
		});

		it('does not print $maptitle without escaping', function (): void {
			$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

			$lines = explode("\n", $source);

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				// A bare print/echo of $maptitle without html_escape is an XSS vector.
				if (preg_match('/(?:print|echo)\s+\$maptitle\s*;/', $line)) {
					expect($line)->toContain('html_escape', 'Bare $maptitle output found without html_escape');
				}
			}
		});

		it('uses a static mime map for Content-Type (not raw user input)', function (): void {
			$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

			// The file must declare a $mime_map array and index into it — never
			// concatenate $imageformat directly into a Content-type header string.
			expect($source)->toContain('$mime_map');
			expect($source)->toContain("'Content-type: ' . (\$mime_map[");
		});
	});

	describe('weathermap-cacti-plugin-mgmt.php', function (): void {
		it('escapes config file buffer with html_escape before printing', function (): void {
			$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

			expect($source)->toContain('print html_escape($buffer);');
		});

		it('does not print $buffer directly without escaping', function (): void {
			$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

			$lines = explode("\n", $source);

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				// A standalone print $buffer; without html_escape is an XSS vector.
				if (preg_match('/^\s*(?:print|echo)\s+\$buffer\s*;/', $line)) {
					expect($line)->toContain('html_escape', 'Bare $buffer output found without html_escape');
				}
			}
		});
	});
});
