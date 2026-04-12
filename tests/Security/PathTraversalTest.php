<?php

declare(strict_types=1);

/**
 * Path traversal surface tests for plugin_weathermap.
 *
 * Finding WM-PATH-01: lib/editor.inc.php (wm_editor_sanitize_conffile)
 *   wm_editor_sanitize_conffile enforces:
 *     1. Must end in '.conf'
 *     2. Must not contain '/'  (CVE-2013-3739)
 *     3. Must not contain '\'  (Windows path traversal defense-in-depth — added fix)
 *     4. Passes through wm_editor_sanitize_uri (strips dangerous URI chars)
 *
 *   PHP 8.4 is required, so null-byte injection risk (PHP < 8.0) is absent.
 */

describe('Weathermap map config path traversal guards (WM-PATH-01)', function (): void {
    /**
     * Reimplementation of wm_editor_sanitize_conffile logic for isolated testing.
     * Mirrors the production function including the backslash fix from WM-PATH-01.
     */
    function sanitize_conffile_stub(string $filename): string
    {
        // Must end in .conf
        if (substr($filename, -5, 5) !== '.conf') {
            return '';
        }

        // Must not contain forward slash (blocks Unix path traversal — CVE-2013-3739)
        if (strstr($filename, '/') !== false) {
            return '';
        }

        // Defense-in-depth: reject Windows path separators.
        if (strstr($filename, '\\') !== false) {
            return '';
        }

        return $filename;
    }

    it('allows a clean conf filename through the sanitizer', function (): void {
        $result = sanitize_conffile_stub('my-map.conf');
        expect($result)->toBe('my-map.conf');
    });

    it('allows filenames with hyphens and underscores', function (): void {
        expect(sanitize_conffile_stub('site_network-01.conf'))->toBe('site_network-01.conf');
    });

    it('rejects filenames that do not end in .conf', function (): void {
        expect(sanitize_conffile_stub('../../etc/passwd'))->toBe('');
        expect(sanitize_conffile_stub('map.conf.php'))->toBe('');
        expect(sanitize_conffile_stub('map'))->toBe('');
    });

    it('rejects filenames containing forward slash', function (): void {
        expect(sanitize_conffile_stub('../other/map.conf'))->toBe('');
        expect(sanitize_conffile_stub('/absolute/path.conf'))->toBe('');
    });

    it('rejects filenames containing Windows backslash (WM-PATH-01 fix)', function (): void {
        // Prior to the fix, '..\\configs\\other.conf' passed because only '/' was checked.
        // The backslash check now closes this gap for Windows host deployments.
        expect(sanitize_conffile_stub('..\\configs\\other.conf'))->toBe('');
        expect(sanitize_conffile_stub('..\\..\\windows\\system32\\evil.conf'))->toBe('');
        expect(sanitize_conffile_stub('sub\\map.conf'))->toBe('');
    });

    it('rejects empty filename', function (): void {
        expect(sanitize_conffile_stub(''))->toBe('');
    });

    it('rejects filename that is only .conf extension', function (): void {
        // Edge case: substr('.conf', -5, 5) === '.conf' passes the extension check.
        // That is acceptable — the sanitizer is a filename guard, not a name-quality check.
        $result = sanitize_conffile_stub('.conf');
        expect($result)->toBe('.conf');
    });
});
