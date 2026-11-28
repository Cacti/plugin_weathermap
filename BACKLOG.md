# Backlog: plugin_weathermap

Issues derived from SECURITY-AUDIT.md.

---

## Issue #1: refactor: extract fping and rrdtool command-building seams
**Priority**: low (the injection fixes in Issues #2 and #3 were applied in place and no longer depend on it)
**Labels**: refactor, tech-debt
**Branch**: `refactor/1-shell-command-seams`
**Status**: resolved in lib/WeatherMapFpingCommand.class.php and lib/WeatherMapRrdCommand.class.php
**Evidence**: lib/datasources/WeatherMapDataSource_fping.php, lib/datasources/WeatherMapDataSource_rrd.php
**Acceptance criteria**:
- [x] The fping popen call is wrapped in a class that accepts a pre-built command string
- [x] The rrdtool command assembly is a class that builds the command string from typed args
- [x] Both classes instantiable without Cacti bootstrap

**Dependencies**: none

---

## Issue #2: security(cmd): escapeshellarg fping target and validate as IP/hostname
**Priority**: medium
**Labels**: security
**Branch**: `security/2-escapeshellarg-fping-target`
**Status**: remediated in lib/datasources/WeatherMapDataSource_fping.php:112–124
**Evidence**: WM-CMD-01
**Acceptance criteria**:
- [x] `$target` validated against an allowed-character pattern before use
- [x] `cacti_escapeshellarg($target)` applied in command string
- [x] Targets that fail validation are rejected with a logged warning

**Dependencies**: none

---

## Issue #3: security(cmd): fix RRD popen argument quoting
**Priority**: low
**Labels**: security, tech-debt
**Branch**: `security/3-fix-rrd-arg-quoting`
**Status**: remediated in lib/datasources/WeatherMapDataSource_rrd.php and lib/WeatherMapRrdCommand.class.php
**Evidence**: WM-CMD-02
**Acceptance criteria**:
- [x] Manual double-quote wrapping replaced with `cacti_escapeshellarg()` per argument
- [x] `$rrdfile` validated as absolute filesystem path with no shell metacharacters

**Dependencies**: none

---

## Issue #4: security(path): add backslash check to wm_editor_sanitize_conffile
**Priority**: low
**Labels**: security, tech-debt
**Branch**: `security/4-sanitize-conffile-backslash`
**Status**: remediated in lib/MapnameValidator.class.php and lib/editor.inc.php
**Evidence**: lib/editor.inc.php (WM-PATH-01)
**Acceptance criteria**:
- [x] `wm_editor_sanitize_conffile` rejects filenames containing `\`
- [x] Path traversal test WM-PATH-01 (backslash case) todo removed
- [x] Existing positive test cases remain green

**Dependencies**: none

---

## Issue #5: security(input): validate $action against allowed-list in editor
**Priority**: medium
**Labels**: security, tech-debt
**Branch**: `security/5-validate-editor-action`
**Status**: remediated in weathermap-cacti-plugin-editor.php and lib/editor.inc.php
**Evidence**: weathermap-cacti-plugin-editor.php:93–109
**Acceptance criteria**:
- [x] `$action` validated against an explicit list of valid editor action strings
- [x] Unknown actions produce a logged warning and abort
- [x] PHPStan level 6 passes

**Dependencies**: none

---

## Issue #6: refactor: extract MapnameValidator as testable class
**Priority**: low
**Labels**: refactor, tech-debt
**Branch**: `refactor/6-mapname-validator-class`
**Status**: resolved in lib/MapnameValidator.class.php (matching plugin lib/ class layout)
**Evidence**: lib/MapnameValidator.class.php, lib/editor.inc.php
**Acceptance criteria**:
- [x] `lib/MapnameValidator.class.php` wraps `wm_editor_sanitize_conffile` logic
- [x] All existing sanitization cases covered by unit tests
- [x] PHPStan level 6 passes

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

---

## Issue #9: feature: restore the legend "hide zero scale" option
**Priority**: low
**Labels**: bug, tech-debt
**Branch**: `feature/9-legend-hide-zero`
**Evidence**: lib/WeatherMap.class.php DrawLegend_Classic() — the `key_hidezero_*` check tested `isset($colours['0_0'])`, but `$colours` is reindexed to a plain list by `usort()` just above, so the string key never existed and the option had silently stopped working. The dead branch was removed during the PHPStan level 8 pass.
**Acceptance criteria**:
- [ ] Detect the zero/zero scale entry against the post-sort list (e.g. match `bottom == 0 && top == 0`)
- [ ] `key_hidezero_<scale>` again drops that entry from the rendered legend
- [ ] Unit test covers both the hidden and shown cases

**Dependencies**: none

---

## Issue #10: refactor: provision schema through api_plugin_db_table_create()/api_plugin_db_add_column()
**Priority**: low
**Labels**: refactor, tech-debt
**Branch**: `refactor/10-schema-plugin-db-api`
**Evidence**: includes/database.php — `weathermap_setup_table()` still provisions its tables with raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN` statements rather than the idempotent Cacti schema API mandated by `.github/copilot-instructions.md` ("Plugin table-creation API"). The code was relocated verbatim from `setup.php` in the schema-relocation PR; converting it to the API is deliberately deferred to keep that move behaviour-preserving.
**Acceptance criteria**:
- [ ] Each `weathermap_*` table is defined through `api_plugin_db_table_create()`
- [ ] Incremental column additions use `api_plugin_db_add_column()`
- [ ] Only genuine data migrations (duplicate-row cleanup, sortorder/filehash backfills) remain as prepared `db_execute*()` calls
- [ ] Install and upgrade paths both run the same idempotent provisioning
- [ ] Existing install/upgrade unit tests remain green

**Dependencies**: none

