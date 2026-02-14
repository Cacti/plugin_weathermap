# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. Version Compatibility: always detect and respect explicit version statements found in the repo (see README and .github guidance) and do not use language features beyond those versions.
2. Context Files: prefer guidance already present under `.github/` (for example the repository contains PHP-focused agent guidance). Use those as authoritative instructions when present.
3. Codebase Patterns: follow the exact coding patterns used in the existing codebase (procedural PHP files, `include_once` usage, `cacti_` helper functions, `html_escape`/`__esc` escaping patterns, and jQuery-based frontend code).
4. Architectural Consistency: respect the plugin-style layout (PHP plugin integrated into Cacti) and maintain the clear boundary between plugin code and Cacti core functions.
5. Code Quality: prioritize maintainability, security, and compatibility with the Cacti 1.2.x plugin model.

## Technology Version Detection

Before generating code, scan the repository for explicit version information and configuration. Key places to check in this repo:

- [README.md](README.md#L1-L40) — documents this project as "PHP Network Weathermap" and states compatibility with Cacti 1.2.x.
- `.github` guidance files (for example `.github/agents/php-developer.agent.md`) — these files indicate the intended PHP guidance for contributors and mention PSR-12 and support for modern PHP versions (8.1+).

Rules:
- Do not assume a PHP language level beyond what the repository documents (use features compatible with PHP 8.1 unless a file explicitly restricts it).
- Respect the stated Cacti compatibility constraint: generated plugin code must be compatible with Cacti 1.2.x integration points.

## Context Files (priority order)

1. `.github/*` (agent and guidance files) — repository-specific guidance exists here and is authoritative.
2. `README.md` and `CHANGELOG.md` — project-level requirements and compatibility notes.
3. Existing PHP files under the project root and `lib/` — use these as exemplars for naming, escaping, and include patterns.

If the `.github/copilot/` directory exists, prefer files there; otherwise use `.github/*` guidance files as the next-best authoritative source.

## Codebase Scanning Instructions

When you cannot find direct guidance in `.github/copilot/`, scan the codebase and follow these concrete steps:

1. Identify the primary language and framework: this repo is PHP with jQuery-based frontend code; the plugin integrates into Cacti.
2. Collect examples of common function and naming patterns: look for `cacti_*` helpers, `weathermap_*` settings functions, `html_escape`, `__esc`, `__n` and `cacti_log` usage.
3. Note module boundaries: files in the root and `lib/` are plugin server-side code; `js/` and `css/` are frontend code.
4. Inspect how external resources are included: `include_once($config['base_path'] . '/plugins/weathermap/lib/WeatherMap.class.php')` style — match that pattern for plugin includes.

When conflicting patterns exist, prefer the patterns used in the most recently modified or most-central plugin files (for example `weathermap-cacti-plugin-mgmt.php` and `lib/WeatherMap.class.php`).

## Code Quality Standards (derived from repo patterns)

Maintainability
- Follow the repository's procedural PHP structure and function naming conventions (use `weathermap_` and `cacti_` prefixes where appropriate).
- Keep functions focused and reuse existing helper functions in `lib/` rather than adding new global helpers.

Security
- Use the repository's escaping and sanitization functions: `html_escape`, `__esc`, `get_request_var` and `get_nfilter_request_var` when reading user input (examples: [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L703) and related forms).
- Use `cacti_log()` for error logging where existing code uses it (see [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L335)).

Performance
- Follow existing RRD access patterns via the included `lib/rrd.php` wrapper and poller-aware approaches; prefer the poller output option where the codebase indicates it (see [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L45)).

Accessibility
- Match existing frontend patterns: use the same ARIA/label and button patterns already present in `weathermap-cacti-plugin-mgmt.php` and `js/` files.

Testability
- There are no repository-wide unit test patterns to copy; when adding tests, follow the minimal, repository-consistent approach (place tests near the corresponding code or in a `tests/` folder if the project already adopts one).

## Documentation Requirements

- Match the level and style of inline comments already present in files and the HTML docs in `docs/`.
- For PHP functions, include short docblocks when other files show docblocks; otherwise prefer concise in-line comments that follow existing style.

## Testing Approach

- The repository contains no enforced unit-test framework. If tests are added, choose a test structure consistent with other PHP projects in the organization and document test locations in the new code's README or comment blocks.

## Technology-Specific Guidelines

PHP
- Prefer code compatible with PHP 8.1 unless an explicit file documents a different requirement.
- Follow the existing procedural/plugin style — plugin entry scripts in the repo root and helper classes in `lib/`.
- Use `include_once` and the `$config['base_path']` patterns for loading Cacti and plugin resources (see [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L44-L46)).

JavaScript (frontend)
- The codebase uses jQuery and small plugins (see `js/` for examples). Follow the existing module patterns (immediate DOM-ready handlers, `loadPageNoHeader(...)` calls, and direct DOM manipulation), rather than introducing modern front-end frameworks.

## Version Control and Release Guidance

- Respect the project's release and compatibility notes: the plugin targets Cacti 1.2.x integration (see [README.md](README.md#L1-L20)).

## General Best Practices (concrete rules for Copilot)

- Match naming conventions exactly: use the `weathermap_` prefix for plugin-specific functions and `cacti_` for wrappers and helpers.
- Reuse existing helper functions (for escaping, logging, configuration) rather than creating new ones with different semantics.
- Use the same approach to configuration and path resolution as existing code (e.g., `$config['base_path']`, `$config['library_path']`).
- When interacting with RRD data, follow the repository's existing wrappers (`lib/rrd.php`) and poller patterns to avoid race conditions.

## Project-Specific Examples (use only these patterns)

- Loading Cacti/plugin helpers:

  include_once($config['base_path'] . '/plugins/weathermap/lib/poller-common.php');

  (Example usage in [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L45)).

- Escaping and printing user-supplied values in forms:

  <input type='text' value='<?php print html_escape_request_var("filter"); ?>'>

  (See form usage in [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L703-L710)).

- Logging fatal plugin errors:

  cacti_log("FATAL: The map config directory ($mapdir) is not writable...", true, 'WEATHERMAP');

  (See [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L335)).

## Implementation notes for maintainers of this file

- Place this file at `.github/copilot-instructions.md` so Copilot and other tooling can consult repository-specific guidance.
- Only include guidance that can be directly traced to files or docs in this repository. Do not recommend external frameworks or newer language features unless the repo shows them in use.

---

Generated from the repository’s `README.md`, `.github` guidance files, and representative plugin files such as `weathermap-cacti-plugin-mgmt.php`, `weathermap-cacti-rebuild.php`, and files in `lib/`.

- `.github` guidance files (for example `.github/agents/php-developer.agent.md`) — these files indicate the intended PHP guidance for contributors and mention PSR-12 and support for modern PHP versions (8.1+).

Rules:
- Do not assume a PHP language level beyond what the repository documents (use features compatible with PHP 8.1 unless a file explicitly restricts it).
- Respect the stated Cacti compatibility constraint: generated plugin code must be compatible with Cacti 1.2.x integration points.

## Context Files (priority order)

1. `.github/*` (agent and guidance files) — repository-specific guidance exists here and is authoritative.
2. `README.md` and `CHANGELOG.md` — project-level requirements and compatibility notes.
3. Existing PHP files under the project root and `lib/` — use these as exemplars for naming, escaping, and include patterns.

If the `.github/copilot/` directory exists, prefer files there; otherwise use `.github/*` guidance files as the next-best authoritative source.

## Codebase Scanning Instructions

When you cannot find direct guidance in `.github`, scan the codebase and follow these concrete steps:

1. Identify the primary language and framework: this repo is PHP with jQuery-based frontend code; the plugin integrates into Cacti.
2. Collect examples of common function and naming patterns: look for `cacti_*` helpers, `weathermap_*` settings functions, `html_escape`, `__esc`, `__n` and `cacti_log` usage.
3. Note module boundaries: files in the root and `lib/` are plugin server-side code; `js/` and `css/` are frontend code.
4. Inspect how external resources are included: `include_once($config['base_path'] . '/plugins/weathermap/lib/WeatherMap.class.php')` style — match that pattern for plugin includes.

When conflicting patterns exist, prefer the patterns used in the most recently modified or most-central plugin files (for example `weathermap-cacti-plugin-mgmt.php` and `lib/WeatherMap.class.php`).

## Code Quality Standards (derived from repo patterns)

Maintainability
- Follow the repository's procedural PHP structure and function naming conventions (use `weathermap_` and `cacti_` prefixes where appropriate).
- Keep functions focused and reuse existing helper functions in `lib/` rather than adding new global helpers.

Security
- Use the repository's escaping and sanitization functions: `html_escape`, `__esc`, `get_request_var` and `get_nfilter_request_var` when reading user input (examples: [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L703) and related forms).
- Use `cacti_log()` for error logging where existing code uses it (see [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L335)).

Performance
- Follow existing RRD access patterns via the included `lib/rrd.php` wrapper and poller-aware approaches; prefer the poller output option where the codebase indicates it (see [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L45)).

Accessibility
- Match existing frontend patterns: use the same ARIA/label and button patterns already present in `weathermap-cacti-plugin-mgmt.php` and `js/` files.

Testability
- There are no repository-wide unit test patterns to copy; when adding tests, follow the minimal, repository-consistent approach (place tests near the corresponding code or in a `tests/` folder if the project already adopts one).

## Documentation Requirements

- Match the level and style of inline comments already present in files and the HTML docs in `docs/`.
- For PHP functions, include short docblocks when other files show docblocks; otherwise prefer concise in-line comments that follow existing style.

## Testing Approach

- The repository contains no enforced unit-test framework. If tests are added, choose a test structure consistent with other PHP projects in the organization and document test locations in the new code's README or comment blocks.

## Technology-Specific Guidelines

PHP
- Prefer code compatible with PHP 8.1 unless an explicit file documents a different requirement.
- Follow the existing procedural/plugin style — plugin entry scripts in the repo root and helper classes in `lib/`.
- Use `include_once` and the `$config['base_path']` patterns for loading Cacti and plugin resources (see [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L44-L46)).

JavaScript (frontend)
- The codebase uses jQuery and small plugins (see `js/` for examples). Follow the existing module patterns (immediate DOM-ready handlers, `loadPageNoHeader(...)` calls, and direct DOM manipulation), rather than introducing modern front-end frameworks.

## Version Control and Release Guidance

- Respect the project's release and compatibility notes: the plugin targets Cacti 1.2.x integration (see [README.md](README.md#L1-L20)).

## General Best Practices (concrete rules for Copilot)

- Match naming conventions exactly: use the `weathermap_` prefix for plugin-specific functions and `cacti_` for wrappers and helpers.
- Reuse existing helper functions (for escaping, logging, configuration) rather than creating new ones with different semantics.
- Use the same approach to configuration and path resolution as existing code (e.g., `$config['base_path']`, `$config['library_path']`).
- When interacting with RRD data, follow the repository's existing wrappers (`lib/rrd.php`) and poller patterns to avoid race conditions.

## Project-Specific Examples (use only these patterns)

- Loading Cacti/plugin helpers:

  include_once($config['base_path'] . '/plugins/weathermap/lib/poller-common.php');

  (Example usage in [weathermap-cacti-rebuild.php](weathermap-cacti-rebuild.php#L45)).

- Escaping and printing user-supplied values in forms:

  <input type='text' value='<?php print html_escape_request_var("filter"); ?>'>

  (See form usage in [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L703-L710)).

- Logging fatal plugin errors:

  cacti_log("FATAL: The map config directory ($mapdir) is not writable...", true, 'WEATHERMAP');

  (See [weathermap-cacti-plugin-mgmt.php](weathermap-cacti-plugin-mgmt.php#L335)).


---

Generated from the repository’s `README.md`, `.github` guidance files, and representative plugin files such as `weathermap-cacti-plugin-mgmt.php`, `weathermap-cacti-rebuild.php`, and files in `lib/`.
