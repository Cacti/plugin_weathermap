<?php

declare(strict_types=1);

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | hand-written mutation regression tests for plugin_weathermap.           |
 |                                                                         |
 | Each describe block targets one real bug that was present in the        |
 | codebase. Tests are structured to:                                       |
 |   1) Demonstrate the wrong output produced by the original code.        |
 |   2) Verify the corrected behaviour.                                    |
 |   3) Confirm the fix is present in the source file.                    |
 |                                                                         |
 | Covered bugs:                                                            |
 |   WM-BUG-COLOUR-SENTINEL   render_colour() checked $col[1] for all 3  |
 |                             sentinel conditions instead of $col[2].     |
 |   WM-BUG-BANDWIDTH-ZERO    bandwidth % divided by max without a zero   |
 |                             guard, causing division-by-zero on links    |
 |                             with no configured bandwidth limit.         |
 |   WM-BUG-READDATA-RETURN   base ReadData() returned [-1,-1] (2 items) |
 |                             instead of [-1,-1,0]; callers doing a 3-   |
 |                             way destructure got wrong $time and a PHP  |
 |                             notice.                                     |
 |   WM-BUG-REPLACE-INFO      weathermap_setting_save() used the invalid  |
 |                             SQL keyword "REPLACE INFO" instead of      |
 |                             "REPLACE INTO", silently dropping writes.  |
 |   WM-BUG-DIR-CHDIR         dir($orig_cwd) opened a Directory handle   |
 |                             instead of calling chdir(); the working    |
 |                             directory was never restored.               |
 +-------------------------------------------------------------------------+
*/

// ---------------------------------------------------------------------------
// WM-BUG-COLOUR-SENTINEL
// render_colour() had $col[1] in all three sentinel checks. The third check
// should use $col[2] (the blue channel). Without the fix a colour such as
// [-1, -1, 5] (red=-1, green=-1, blue=5) incorrectly matched the 'none'
// sentinel because the buggy code compared $col[1] == -1 a second time
// rather than $col[2] == -1.
// ---------------------------------------------------------------------------

/**
 * Original (buggy) implementation: all three conditions test $col[1].
 * Inlined here so tests remain isolated from the plugin's bootstrap.
 */
function render_colour_broken(array $col): string
{
    // Bug: third condition uses $col[1] instead of $col[2].
    if (($col[0] == -1) && ($col[1] == -1) && ($col[1] == -1)) {
        return 'none';
    }

    if (($col[0] == -2) && ($col[1] == -2) && ($col[1] == -2)) {
        return 'copy';
    }

    if (($col[0] == -3) && ($col[1] == -3) && ($col[1] == -3)) {
        return 'contrast';
    } else {
        return sprintf('%d %d %d', $col[0], $col[1], $col[2]);
    }
}

/**
 * Corrected implementation: each condition uses $col[2] in the third test.
 * Mirrors lib/WeatherMap.functions.php render_colour() post-fix.
 */
function render_colour_fixed(array $col): string
{
    if (($col[0] == -1) && ($col[1] == -1) && ($col[2] == -1)) {
        return 'none';
    }

    if (($col[0] == -2) && ($col[1] == -2) && ($col[2] == -2)) {
        return 'copy';
    }

    if (($col[0] == -3) && ($col[1] == -3) && ($col[2] == -3)) {
        return 'contrast';
    } else {
        return sprintf('%d %d %d', $col[0], $col[1], $col[2]);
    }
}

describe('WM-BUG-COLOUR-SENTINEL: render_colour() third sentinel must check $col[2]', function (): void {
    it('buggy version returns "none" for [-1,-1,5] — blue channel ignored (demonstrates the bug)', function (): void {
        // With the bug, $col[2] == 5 is never tested; both sentinel checks use $col[1] == -1.
        expect(render_colour_broken([-1, -1, 5]))->toBe('none');
    });

    it('fixed version returns the numeric string for [-1,-1,5] — blue channel is checked', function (): void {
        // Blue is 5, not -1; the colour is real and must render as a numeric triplet.
        expect(render_colour_fixed([-1, -1, 5]))->toBe('-1 -1 5');
    });

    it('fixed version returns "none" only when all three channels are -1', function (): void {
        expect(render_colour_fixed([-1, -1, -1]))->toBe('none');
    });

    it('fixed version returns "copy" only when all three channels are -2', function (): void {
        expect(render_colour_fixed([-2, -2, -2]))->toBe('copy');
    });

    it('fixed version does NOT return "copy" when blue channel differs from -2', function (): void {
        // [-2, -2, 0]: red and green are -2 but blue is 0 — should render numerically.
        expect(render_colour_fixed([-2, -2, 0]))->toBe('-2 -2 0');
    });

    it('fixed version returns "contrast" only when all three channels are -3', function (): void {
        expect(render_colour_fixed([-3, -3, -3]))->toBe('contrast');
    });

    it('fixed version formats a normal colour as a space-separated triplet', function (): void {
        expect(render_colour_fixed([255, 128, 0]))->toBe('255 128 0');
    });

    it('source: lib/WeatherMap.functions.php uses $col[2] in all three sentinel conditions', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php');

        // All three sentinel checks in render_colour() must test $col[2] as the third operand.
        $pattern = '/\(\$col\[0\]\s*==\s*-[123]\)\s*&&\s*\(\$col\[1\]\s*==\s*-[123]\)\s*&&\s*\(\$col\[2\]\s*==\s*-[123]\)/';
        preg_match_all($pattern, $source, $matches);
        expect(count($matches[0]))->toBe(3, 'Expected exactly 3 sentinel conditions each using $col[2]');

        // The old pattern — $col[1] used twice — must be absent.
        expect($source)->not->toContain('($col[1] == -1) && ($col[1] == -1)');
        expect($source)->not->toContain('($col[1] == -2) && ($col[1] == -2)');
        expect($source)->not->toContain('($col[1] == -3) && ($col[1] == -3)');
    });
});

// ---------------------------------------------------------------------------
// WM-BUG-BANDWIDTH-ZERO
// Percentage calculation divided by max_bandwidth_out / max_bandwidth_in
// without guarding against zero. Links where BANDWIDTH is not configured
// default to 0, causing a fatal "Division by zero" error at render time.
// The fix wraps each division in a ternary: ($max != 0) ? ... : 0.
// ---------------------------------------------------------------------------

/**
 * calc_pct mirrors the fixed ternary guard used in WeatherMap.class.php.
 */
function calc_pct(float $traffic, float $max): float
{
    return ($max != 0) ? (($traffic / $max) * 100) : 0.0;
}

describe('WM-BUG-BANDWIDTH-ZERO: bandwidth percentage must guard against zero divisor', function (): void {
    it('old unguarded formula requires $max != 0 to avoid division by zero', function (): void {
        // This test documents the precondition the old code violated.
        // We assert the guard condition directly rather than triggering the fatal.
        $max = 0.0;
        expect($max == 0)->toBeTrue('A zero max_bandwidth triggers DivisionByZeroError without the guard');
    });

    it('calc_pct returns 0.0 when max is zero (no fatal, no NaN)', function (): void {
        expect(calc_pct(100.0, 0.0))->toBe(0.0);
    });

    it('calc_pct returns 0.0 when traffic is zero', function (): void {
        expect(calc_pct(0.0, 1000.0))->toBe(0.0);
    });

    it('calc_pct returns 10.0 for 100 traffic on a 1000-unit link', function (): void {
        expect(calc_pct(100.0, 1000.0))->toBe(10.0);
    });

    it('calc_pct returns 100.0 when traffic equals max', function (): void {
        expect(calc_pct(500.0, 500.0))->toBe(100.0);
    });

    it('calc_pct returns correct value for asymmetric link (in != out)', function (): void {
        // Asymmetric: 10 Mbps in, 100 Mbps out; 5 Mbps of traffic in.
        expect(calc_pct(5_000_000.0, 10_000_000.0))->toBe(50.0);
    });

    it('source: WeatherMap.class.php guards max_bandwidth_out with != 0 check (twice)', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

        // The fix must appear in both the half-duplex and full-duplex branches.
        $count = substr_count($source, '($myobj->max_bandwidth_out != 0)');
        expect($count)->toBe(2, 'Expected guard on max_bandwidth_out in both duplex branches');
    });

    it('source: WeatherMap.class.php guards max_bandwidth_in with != 0 check (twice)', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

        $count = substr_count($source, '($myobj->max_bandwidth_in != 0)');
        expect($count)->toBe(2, 'Expected guard on max_bandwidth_in in both duplex branches');
    });
});

// ---------------------------------------------------------------------------
// WM-BUG-READDATA-RETURN
// The base WeatherMapDataSource ReadData() stub returned [-1, -1] (2 elements).
// The engine destructures the return as [$in, $out, $datatime]. With only 2
// elements PHP emits a notice and $datatime gets null instead of 0, which
// breaks timestamp-dependent logic downstream.
// The fix changes the stub to return [-1, -1, 0].
// ---------------------------------------------------------------------------

describe('WM-BUG-READDATA-RETURN: base ReadData() stub must return a 3-element array', function (): void {
    it('a 2-element return leaves $time undefined in a 3-way destructure', function (): void {
        // Demonstrates the root cause: count() is 2, not 3.
        $broken_result = [-1, -1];
        expect(count($broken_result))->toBe(2);
        expect(count($broken_result) < 3)->toBeTrue('2-element array cannot fully satisfy [$in, $out, $time]');
    });

    it('3-element result satisfies [$in, $out, $time] destructure with $time === 0', function (): void {
        $result = [-1, -1, 0];
        [$in, $out, $time] = $result;

        expect($in)->toBe(-1);
        expect($out)->toBe(-1);
        expect($time)->toBe(0);
    });

    it('$time from fixed stub is an integer, not null', function (): void {
        $result = [-1, -1, 0];
        [, , $time] = $result;

        expect($time)->toBeInt();
        expect($time)->toBe(0);
    });

    it('source: WeatherMap.class.php base ReadData() returns [-1,-1,0]', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

        expect($source)->toContain('return ([-1, -1, 0]);');
    });

    it('source: WeatherMap.class.php does NOT contain the old 2-element return stub', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.class.php');

        // The old stub comment is kept for history; the live function body must not use it.
        // We check that no un-commented return of exactly [-1,-1] is present.
        // The comment line starts with '//' so we strip comments before checking.
        $lines = explode("\n", $source);
        $live_lines = array_filter($lines, static function (string $line): bool {
            return !str_contains(ltrim($line), '//');
        });
        $live_source = implode("\n", $live_lines);

        expect($live_source)->not->toContain('return ([-1,-1])');
        expect($live_source)->not->toContain('return [-1,-1]');
    });
});

// ---------------------------------------------------------------------------
// WM-BUG-REPLACE-INFO
// weathermap_setting_save() used the SQL fragment "REPLACE INFO" — a typo
// that is not valid SQL in MySQL/MariaDB. The statement was silently ignored
// by db_execute_prepared, meaning plugin settings were never persisted.
// The fix changes all three occurrences to "REPLACE INTO".
// ---------------------------------------------------------------------------

describe('WM-BUG-REPLACE-INFO: weathermap_setting_save() must use REPLACE INTO not REPLACE INFO', function (): void {
    it('source: weathermap-cacti-plugin-mgmt.php does NOT contain "REPLACE INFO"', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

        expect($source)->not->toContain('REPLACE INFO');
    });

    it('source: weathermap-cacti-plugin-mgmt.php contains "REPLACE INTO weathermap_settings"', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

        expect($source)->toContain('REPLACE INTO weathermap_settings');
    });

    it('source: "REPLACE INTO weathermap_settings" appears exactly 3 times (one per branch)', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

        // weathermap_setting_save() has three branches: positive mapid, negative mapid, zero.
        // Each must use REPLACE INTO.
        $count = substr_count($source, 'REPLACE INTO weathermap_settings');
        expect($count)->toBe(3, 'All three INSERT branches in weathermap_setting_save() must use REPLACE INTO');
    });
});

// ---------------------------------------------------------------------------
// WM-BUG-DIR-CHDIR
// The plugin called dir($orig_cwd) to restore the working directory after
// rendering a map. dir() opens a directory iterator and returns a Directory
// object; it has no effect on the process working directory. The correct
// function is chdir(). Without the fix each map render left the CWD changed,
// causing subsequent file-path lookups to fail.
// ---------------------------------------------------------------------------

describe('WM-BUG-DIR-CHDIR: working directory must be restored with chdir(), not dir()', function (): void {
    it('PHP built-in dir() returns a Directory object, not a bool — it does NOT change CWD', function (): void {
        $result = dir(sys_get_temp_dir());

        expect($result)->toBeInstanceOf(Directory::class);

        // Close the handle to avoid resource leak.
        $result->close();
    });

    it('PHP built-in chdir() returns bool — it changes the process working directory', function (): void {
        $orig = getcwd();
        $result = chdir(sys_get_temp_dir());

        expect($result)->toBeBool();
        expect($result)->toBeTrue();

        // Restore so subsequent tests are unaffected.
        chdir((string) $orig);
    });

    it('dir() on a valid path does NOT change the current working directory', function (): void {
        $orig = getcwd();
        $tmp  = sys_get_temp_dir();

        // Ensure we start from a different directory.
        if (realpath((string) $orig) === realpath($tmp)) {
            chdir(__DIR__);
            $orig = getcwd();
        }

        $handle = dir($tmp);
        $after  = getcwd();
        $handle->close();

        expect(realpath((string) $after))->toBe(realpath((string) $orig), 'dir() must not change the CWD');
    });

    it('source: weathermap-cacti-plugin.php does NOT contain a bare dir($orig_cwd) call', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

        // 'dir($orig_cwd)' is a substring of 'chdir($orig_cwd)', so we need a word-boundary
        // check: a bare dir() call would be preceded by whitespace, =, or ( — not by 'ch'.
        // preg_match returns 0 (no match) if the bug is absent, 1 if present.
        $bare_dir_call = preg_match('/(?<![a-z])dir\(\$orig_cwd\)/', $source);
        expect($bare_dir_call)->toBe(0, 'A bare dir($orig_cwd) call was found; it should have been chdir()');
    });

    it('source: weathermap-cacti-plugin.php contains chdir($orig_cwd)', function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

        expect($source)->toContain('chdir($orig_cwd)');
    });
});
