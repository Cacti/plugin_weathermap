# Security Audit: plugin_weathermap

**Date**: 2026-03-09
**Auditor**: automated static analysis + manual review
**Scope**: all PHP files under plugin_weathermap/ excluding vendor/ (none present)

---

## Summary

Weathermap is the most security-mature of the four plugins audited. The Cacti Group
rewrite uses prepared statements throughout the management UI (weathermap-cacti-plugin-mgmt.php),
consistently applies html_escape() on DB-sourced output, and enforces api_plugin_user_realm_auth
at well-defined entry points. The two remaining surface areas are in the map-rendering engine
(datasource plugins) where admin-controlled config values flow into shell commands without
escapeshellarg, and a residual Windows-only path traversal gap in the config filename sanitizer.

---

## Findings

### WM-CMD-01 — OS Command Injection via map config TARGET value (MEDIUM)
**File**: `lib/datasources/WeatherMapDataSource_fping.php:103`
**Category**: OS Command Injection
**Severity**: MEDIUM (requires write access to a .conf map file; admin-controlled input)

```php
$command = $this->fping_cmd . " -t100 -r1 -p20 -u -C $ping_count -i10 -q $target 2>&1";
$pipe = popen($command, 'r');
```

`$target` is extracted from the TARGET line in a weathermap `.conf` file via the regex
`/^fping:(\S+)$/`. The regex captures any non-whitespace string, including shell
metacharacters (`;`, `|`, `&`, `` ` ``, `$`). A crafted TARGET line such as
`TARGET fping:192.0.2.1;id>/tmp/x` injects into the shell command.

**Attack path**: Admin writes (or attacker achieves file-write on) a .conf file containing
a malicious TARGET line → weathermap poller processes it → `popen` executes the injected command
as the web/poller process user.

**Remediation**: Wrap `$target` with `escapeshellarg()`. Additionally, validate that
`$target` is a valid IP address or hostname before use (reject if it fails `filter_var`
with `FILTER_VALIDATE_IP` or a strict hostname regex).

---

### WM-CMD-02 — OS Command Injection via RRD file path in popen command (LOW-MEDIUM)
**File**: `lib/datasources/WeatherMapDataSource_rrd.php:325, 429`
**Category**: OS Command Injection
**Severity**: LOW-MEDIUM (rrdfile sourced from Cacti DB, admin-controlled)

```php
// args array assembled with:
$args[] = "DEF:in=$rrdfile:" . $dsnames[IN] . ":$cf";
// then:
foreach ($args as $arg) {
    if (strchr($arg, ' ') != false) {
        $command .= ' "' . $arg . '"';
    } else {
        $command .= ' ' . $arg;
    }
}
$pipe = popen($command, 'r');
```

`$rrdfile` and `$dsnames` originate from Cacti's `data_template_data` table (admin-set
RRD paths). The quoting logic uses double quotes around args containing spaces, but does
not escape double-quote characters within the value itself. A `$rrdfile` containing `"`
or `` ` `` breaks out of the double-quote boundary.

The `db_fetch_row_prepared` calls that source `$rrdfile` use parameterized queries, so
the DB-layer is safe. The risk is an admin storing a crafted RRD path.

**Remediation**: Validate `$rrdfile` as an absolute filesystem path with no shell
metacharacters before embedding in command args. Use `escapeshellarg()` per argument
rather than manual double-quoting.

---

### WM-PATH-01 — Path traversal in map config filename (LOW — mitigated, residual Windows risk)
**File**: `weathermap-cacti-plugin-editor.php:96`, `lib/editor.inc.php:232–246`
**Category**: Path Traversal
**Severity**: LOW (effectively mitigated on Linux/macOS; residual risk on Windows)

```php
function wm_editor_sanitize_conffile($filename) {
    $filename = wm_editor_sanitize_uri($filename);
    if (substr($filename, -5, 5) != '.conf') { $filename = ''; }
    if (strstr($filename, '/') !== false)     { $filename = ''; }
    return $filename;
}
```

The sanitizer enforces `.conf` extension and rejects any `/` character, preventing
Unix path traversal (e.g., `../../etc/passwd.conf` is rejected). PHP null-byte injection
(`file.conf\0.php`) was fixed in PHP 8.0; with PHP 8.4 as the requirement it is not
exploitable.

Residual gap: Windows path separator `\` is not checked. On Windows hosts,
`..\\configs\\other.conf` passes both checks. Since Cacti targets Linux, this is
informational.

**Remediation**: Add a backslash check for defense-in-depth: `if (strstr($filename, '\\') !== false) { $filename = ''; }`.

---

### WM-POSITIVE-01 — Management UI uses prepared statements throughout (INFORMATIONAL)
**File**: `weathermap-cacti-plugin-mgmt.php:354–390, 467–476`

The `weathermap_form_actions()` function iterates `$_POST`, extracts checkbox keys via
`preg_match('/^chk_([0-9:a-z]+)$/', ...)`, validates numeric parts with
`input_validate_input_number()`, and uses `db_execute_prepared` with `?` placeholders
for all INSERT/DELETE operations. DB-sourced output is wrapped with `html_escape()`.
No SQL injection or XSS issues found in this file.

---

### WM-POSITIVE-02 — Auth enforcement is consistent (INFORMATIONAL)
**File**: `weathermap-cacti-plugin.php:418, 445`, `setup.php:766`

`api_plugin_user_realm_auth` is called at the correct entry points for both viewer and
management realms. No unauthenticated paths to state-modifying actions were found.

---

### WM-POSITIVE-03 — Editor mapname sanitization prevents path traversal (INFORMATIONAL)
**File**: `weathermap-cacti-plugin-editor.php:91–96`

```php
$mapname = get_nfilter_request_var('mapname');
$mapname = wm_editor_sanitize_conffile($mapname);
```

The sanitizer is applied immediately after reading `$mapname`. The `wm_editor_sanitize_uri`
call within `wm_editor_sanitize_conffile` additionally strips dangerous URI characters.
This is a well-implemented control.

---

## Unknowns and Blind Spots

- **weathermap-cacti-plugin-editor.php** was only partially read. The editor has a large
  surface area (map config editing, node/link add/remove). The `$action` variable is read
  via `get_nfilter_request_var('action')` without validation against an allowed-list;
  tracing what each action value triggers requires a full read.
- **`lib/editor.actions.php`** was not fully read. It uses `wm_editor_sanitize_conffile`
  on `$sourcemapname` (line 68) which is correct, but other action parameters may not
  be sanitized.
- **`weathermap-cacti-rebuild.php`** was not examined. Rebuild scripts that process
  map configs offline may have additional shell execution paths.
- **`check.php`** was not examined.
- **fping target IP validation**: whether `$target` is validated as a valid IP/hostname
  anywhere before reaching the popen call was not confirmed beyond the `\S+` regex.

---

## Seams Required Before TDD Can Fully Proceed

| Seam | Files Affected | Description |
|------|---------------|-------------|
| `src/Shell/FpingRunner.php` | WeatherMapDataSource_fping.php:103 | Extract popen call; test command assembly without executing fping |
| `src/Rrd/RrdCommandBuilder.php` | WeatherMapDataSource_rrd.php:296–325 | Extract arg assembly; test escapeshellarg application |
| `src/Config/MapnameValidator.php` | editor.inc.php:232–246 | Expose sanitize logic as a testable class method |

---

## Estimated Effort

| Finding | Fix Effort |
|---------|-----------|
| WM-CMD-01 (fping escapeshellarg + IP validation) | 1 h |
| WM-CMD-02 (rrd arg quoting) | 1 h |
| WM-PATH-01 (backslash check) | 15 min |
| Full test coverage via seams | 1 day |
