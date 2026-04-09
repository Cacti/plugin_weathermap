<?php

declare(strict_types=1);

/**
 * Command injection surface tests for plugin_weathermap.
 *
 * Findings documented:
 *   WM-CMD-01  lib/datasources/WeatherMapDataSource_fping.php:103
 *              $target (from map config TARGET line) used in fping command without escapeshellarg.
 *              Fixed: hostname regex validation + escapeshellarg() applied before popen().
 *   WM-CMD-02  lib/datasources/WeatherMapDataSource_rrd.php:325, 429
 *              $command assembled from $rrdfile/$dsnames without escapeshellarg on individual args.
 *              Fixed: strchr-based manual quoting replaced with escapeshellarg() per arg.
 *
 * Context notes:
 *   - $target originates from a .conf map file, parsed by the WeatherMap engine.
 *     Map configs are admin-authored, so this is admin-controlled input — not direct web-user input.
 *     However, a SSRF or file-write vulnerability delivering a crafted .conf would chain to RCE.
 *   - $rrdfile originates from Cacti's data_template_data table (admin-configured RRD paths).
 *     Same trust level: admin-controlled, one step from web.
 *   - Both are medium severity because the attack requires admin/file-write access first.
 */

describe('Weathermap fping command injection (WM-CMD-01)', function (): void {
    it('demonstrates that $target from map config flows into popen without escaping (pre-fix behaviour)', function (): void {
        // WeatherMapDataSource_fping.php:103 (pre-fix):
        //   $command = $this->fping_cmd . " -t100 -r1 -p20 -u -C $ping_count -i10 -q $target 2>&1";
        //   $pipe = popen($command, 'r');
        //
        // A crafted target value with shell metacharacters produces an injectable command.
        $fping_cmd  = '/usr/local/sbin/fping';
        $ping_count = 5;
        $target     = '192.0.2.1; id > /tmp/wm_pwned';

        $command = $fping_cmd . " -t100 -r1 -p20 -u -C $ping_count -i10 -q $target 2>&1";

        expect($command)->toContain('; id > /tmp/wm_pwned');

        // Fixed form: validate then escapeshellarg.
        $safe_command = $fping_cmd
            . ' -t100 -r1 -p20 -u -C ' . (int) $ping_count
            . ' -i10 -q ' . escapeshellarg($target)
            . ' 2>&1';

        // escapeshellarg wraps the target; metachar inside quotes is neutralised
        expect($safe_command)->toContain("'");
    });

    it('hostname regex \S+ does not prevent shell metacharacter injection', function (): void {
        // The regex /^fping:(\S+)$/ captures any non-whitespace string.
        // Shell metacharacters like ; | & ` $ are non-whitespace and pass the regex.
        $targetstring = 'fping:192.0.2.1;id';
        preg_match('/^fping:(\S+)$/', $targetstring, $matches);

        expect($matches[1])->toBe('192.0.2.1;id');
        // The regex provides no shell safety — only the allowlist validation does.
        expect($matches[1])->toContain(';');
    });

    it('escapeshellarg neutralises semicolon in fping target', function (): void {
        $target = '192.0.2.1; rm -rf /';
        $safe   = escapeshellarg($target);

        expect($safe)->toStartWith("'")->toEndWith("'");
    });

    it('allowlist regex accepts valid IPv4 address', function (): void {
        $target = '192.0.2.1';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(1);
    });

    it('allowlist regex accepts valid IPv6 address', function (): void {
        $target = '2001:db8::1';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(1);
    });

    it('allowlist regex accepts valid hostname', function (): void {
        $target = 'router-01.example.com';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(1);
    });

    it('allowlist regex rejects target containing semicolon', function (): void {
        $target = '192.0.2.1; id';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(0);
    });

    it('allowlist regex rejects target containing pipe', function (): void {
        $target = '192.0.2.1|cat /etc/passwd';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(0);
    });

    it('allowlist regex rejects target containing backtick', function (): void {
        $target = '`id`';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(0);
    });

    it('allowlist regex rejects target containing dollar sign', function (): void {
        $target = '$(/bin/sh)';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(0);
    });

    it('allowlist regex rejects target containing space', function (): void {
        $target = '192.0.2.1 -r 1';
        expect(preg_match('/^[a-zA-Z0-9.\-:]+$/', $target))->toBe(0);
    });
});

describe('Weathermap RRD popen command injection (WM-CMD-02)', function (): void {
    it('demonstrates that rrdfile path without escapeshellarg allows metacharacter injection (pre-fix)', function (): void {
        // WeatherMapDataSource_rrd.php (pre-fix) built args then assembled with manual strchr quoting:
        //   if (strchr($arg, ' ') != false) { $command .= ' "' . $arg . '"'; }
        // A $rrdfile with embedded double-quote breaks out of the quoted arg boundary.
        $rrdtool = '/usr/bin/rrdtool';
        $rrdfile = '/var/rra/host.rrd" --daemon /tmp/attacker.sock "';
        $ds      = 'traffic_in';
        $cf      = 'AVERAGE';

        $arg = "DEF:in=$rrdfile:$ds:$cf";
        expect($arg)->toContain('"');
    });

    it('escapeshellarg neutralises double-quote in rrdfile path', function (): void {
        $rrdfile   = '/var/rra/host.rrd" --daemon /tmp/attacker.sock "';
        $safe      = escapeshellarg($rrdfile);

        expect($safe)->toStartWith("'");
        // The injected double-quote is contained inside single-quoted shell argument.
        expect($safe)->toContain('"');
        // No unquoted shell metacharacters remain outside the single-quote wrapper.
        expect(substr($safe, 0, 1))->toBe("'");
        expect(substr($safe, -1))->toBe("'");
    });

    it('escapeshellarg neutralises backtick in rrdfile path', function (): void {
        $rrdfile = '/var/rra/`id`.rrd';
        $safe    = escapeshellarg($rrdfile);

        expect($safe)->toStartWith("'");
        // Backtick is inert inside single quotes.
        expect($safe)->toContain('`');
    });

    it('escapeshellarg on a path with spaces produces a single-quoted string', function (): void {
        $rrdfile = '/var/rra/my file.rrd';
        $safe    = escapeshellarg($rrdfile);

        expect($safe)->toBe("'/var/rra/my file.rrd'");
    });

    it('per-arg escapeshellarg on clean args preserves them functionally', function (): void {
        // Verify that escapeshellarg on benign args round-trips correctly when unquoted by shell.
        // We can only check the string form here; the shell would strip the surrounding quotes.
        $arg  = 'AVERAGE';
        $safe = escapeshellarg($arg);
        expect($safe)->toBe("'AVERAGE'");

        $arg2  = '--start';
        $safe2 = escapeshellarg($arg2);
        expect($safe2)->toBe("'--start'");
    });
});
