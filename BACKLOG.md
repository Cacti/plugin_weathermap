# Backlog: plugin_weathermap

Issues derived from SECURITY-AUDIT.md. Do not push to GitHub until contrib-ledger gate passes.

---

## Issue #1: refactor: extract FpingRunner and RrdCommandBuilder seams
**Priority**: medium (blocker for security tests)
**Labels**: refactor, tech-debt
**Branch**: `refactor/1-shell-command-seams`
**Evidence**: WeatherMapDataSource_fping.php:103; WeatherMapDataSource_rrd.php:296–325
**Acceptance criteria**:
- [ ] `src/Shell/FpingRunner.php` wraps popen call; accepts pre-built command string
- [ ] `src/Rrd/RrdCommandBuilder.php` assembles rrdtool command string from typed args
- [ ] Both classes instantiable without Cacti bootstrap
- [ ] PHPStan level 6 passes on `src/`

**Dependencies**: none

---

## Issue #2: security(cmd): escapeshellarg fping target and validate as IP/hostname
**Priority**: medium
**Labels**: security
**Branch**: `security/2-escapeshellarg-fping-target`
**Evidence**: lib/datasources/WeatherMapDataSource_fping.php:103 (WM-CMD-01)
**Acceptance criteria**:
- [ ] `$target` validated with `filter_var($target, FILTER_VALIDATE_IP)` or strict hostname regex before use
- [ ] `escapeshellarg($target)` applied in command string
- [ ] `src/Shell/FpingRunner.php` seam extracted and unit-tested
- [ ] Security test WM-CMD-01 passes (todo removed)
- [ ] Targets that fail IP/hostname validation are rejected with a logged warning

**Dependencies**: Issue #1

---

## Issue #3: security(cmd): fix RRD popen argument quoting
**Priority**: low
**Labels**: security, tech-debt
**Branch**: `security/3-fix-rrd-arg-quoting`
**Evidence**: lib/datasources/WeatherMapDataSource_rrd.php:325, 429 (WM-CMD-02)
**Acceptance criteria**:
- [ ] Manual double-quote wrapping replaced with `escapeshellarg()` per argument
- [ ] `$rrdfile` validated as absolute filesystem path with no shell metacharacters
- [ ] `src/Rrd/RrdCommandBuilder.php` seam extracted and unit-tested
- [ ] Security test WM-CMD-02 passes (todo removed)

**Dependencies**: Issue #1

---

## Issue #4: security(path): add backslash check to wm_editor_sanitize_conffile
**Priority**: low
**Labels**: security, tech-debt
**Branch**: `security/4-sanitize-conffile-backslash`
**Evidence**: lib/editor.inc.php:232–246 (WM-PATH-01)
**Acceptance criteria**:
- [ ] `wm_editor_sanitize_conffile` rejects filenames containing `\`
- [ ] Path traversal test WM-PATH-01 (backslash case) todo removed
- [ ] Existing positive test cases remain green

**Dependencies**: none

---

## Issue #5: security(input): validate $action against allowed-list in editor
**Priority**: medium
**Labels**: security, tech-debt
**Branch**: `security/5-validate-editor-action`
**Evidence**: weathermap-cacti-plugin-editor.php:91 — `$action = get_nfilter_request_var('action')` without allowed-list validation
**Acceptance criteria**:
- [ ] `$action` validated against an explicit list of valid editor action strings
- [ ] Unknown actions produce a logged warning and abort
- [ ] PHPStan level 6 passes

**Dependencies**: none

---

## Issue #6: refactor: extract MapnameValidator as testable class
**Priority**: low
**Labels**: refactor, tech-debt
**Branch**: `refactor/6-mapname-validator-class`
**Evidence**: lib/editor.inc.php:232–246
**Acceptance criteria**:
- [ ] `src/Config/MapnameValidator.php` wraps `wm_editor_sanitize_conffile` logic
- [ ] All existing sanitization cases covered by unit tests
- [ ] PHPStan level 6 passes

**Dependencies**: none

---

## Issue #7: ci: add Pest 4 + PHPStan CI workflow
**Priority**: medium
**Labels**: ci
**Branch**: `ci/7-pest-phpstan-workflow`
**Acceptance criteria**:
- [ ] `.github/workflows/test.yml` runs `composer test` and `composer analyse`
- [ ] PHPStan level 6 clean on `src/` and `tests/`
- [ ] Actions pinned to full commit SHA

**Dependencies**: Issues #1–#5

---

## Issue #8: docs: complete audit of weathermap-cacti-plugin-editor.php action dispatch
**Priority**: medium
**Labels**: security, docs
**Branch**: `docs/8-editor-action-audit`
**Evidence**: editor action variable not fully traced in this audit
**Acceptance criteria**:
- [ ] All action values and their code paths documented
- [ ] Any state-changing actions that lack CSRF or auth checks identified and filed as separate issues

**Dependencies**: none
