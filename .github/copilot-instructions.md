# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`weathermap`, version 1.7) targeting Cacti 1.2.24+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: 8.1+ (see `.github/agents/php-developer.agent.md` for contributor guidance; do not use language features beyond what CI tests)
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.24+), "PHP Network Weathermap"
- **Database**: MySQL/MariaDB
- **Frontend**: jQuery-based UI (`js/`)

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `cacti_log()`)
- `lib/WeatherMap.class.php` core map-rendering engine; `lib/rrd.php` RRDtool access wrapper
- Optional: `gettext` for internationalization

## Project Structure

```
weathermap/                        # Repository root (install to plugins/weathermap/ in Cacti)
├── cli/                             # CLI utilities
├── configs/                           # Map configuration files
├── docs/                                # Documentation
├── lib/                                   # WeatherMap.class.php engine, rrd.php, poller-common.php
├── output/                                  # Generated map output
├── tests/                                     # Test suite (phpunit.xml, infection.json)
├── weathermap-cacti-plugin.php                  # Main map viewer page
├── weathermap-cacti-plugin-mgmt.php               # Map management/editor entry
├── weathermap-cacti-plugin-editor.php               # Visual map editor
├── weathermap-cacti-rebuild.php                       # Poller-triggered map rebuild
├── INFO                                                 # Plugin metadata (name, version, compat)
├── SECURITY.md / SECURITY-AUDIT.md                        # Security posture notes
├── README.md
└── setup.php                                                # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_weathermap_` where used, but most hook callbacks and settings functions in this codebase use the `weathermap_` prefix directly: `weathermap_config_arrays()`, `weathermap_config_settings()`, `weathermap_show_tab()`, `weathermap_poller_bottom()`.
- Wrapper/helper functions use the `cacti_` prefix where they mirror or call into Cacti core helpers.
- Match the existing prefix used by the function you are editing; do not introduce a new naming scheme.

### Variables and Constants
- Use snake_case for variables; access Cacti configuration via `$config['base_path']` / `$config['library_path']`.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group, Howard Jones".

## Security Standards

### SQL Query Security
**ALWAYS use prepared statements** for database operations — never concatenate user input into SQL. See `SECURITY.md`/`SECURITY-AUDIT.md` for this plugin's documented security posture before making security-relevant changes.

### Output Escaping
Use the repository's existing escaping helpers — `html_escape()`, `__esc()`, `html_escape_request_var()` — for anything printed into HTML, e.g.:

```php
<input type='text' value='<?php print html_escape_request_var("filter"); ?>'>
```

### Input Validation
Use `get_request_var()` / `get_nfilter_request_var()` for request input; never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### Logging
Use `cacti_log()` for error logging, matching the existing severity-tagged message style:

```php
cacti_log("FATAL: The map config directory ($mapdir) is not writable...", true, 'WEATHERMAP');
```

## Database Operations

Use Cacti's `db_*` / `db_*_prepared()` functions; do not introduce a separate database abstraction.

## Internationalization

Wrap user-facing strings in `__()`/`__n()` with the `'weathermap'` text domain where the surrounding code already does so.

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `setup.php`:

```php
api_plugin_register_hook('weathermap', 'config_arrays',   'weathermap_config_arrays',   'setup.php');
api_plugin_register_hook('weathermap', 'config_settings', 'weathermap_config_settings', 'setup.php');
api_plugin_register_hook('weathermap', 'top_header_tabs',       'weathermap_show_tab', 'setup.php');
api_plugin_register_hook('weathermap', 'top_graph_header_tabs', 'weathermap_show_tab', 'setup.php');
api_plugin_register_hook('weathermap', 'draw_navigation_text', 'weathermap_draw_navigation_text', 'setup.php');
api_plugin_register_hook('weathermap', 'top_graph_refresh', 'weathermap_top_graph_refresh', 'setup.php');
api_plugin_register_hook('weathermap', 'page_title',        'weathermap_page_title',        'setup.php');
api_plugin_register_hook('weathermap', 'poller_top',    'weathermap_poller_top',    'setup.php');
api_plugin_register_hook('weathermap', 'poller_output', 'weathermap_poller_output', 'setup.php');
api_plugin_register_hook('weathermap', 'poller_bottom', 'weathermap_poller_bottom', 'setup.php');

api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin.php', 'View Weathermaps', 1);
api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin-mgmt.php,weathermap-cacti-plugin-mgmt-groups.php', 'Manage Weathermap', 1);
api_plugin_register_realm('weathermap', 'weathermap-cacti-plugin-editor.php', 'Edit Weathermaps', 1);
```

### Map Rendering Engine
Core rendering/parsing logic lives in `lib/WeatherMap.class.php`; RRD access goes through `lib/rrd.php`. Prefer extending these existing classes/wrappers over introducing parallel implementations.

## Best Practices

1. Match existing procedural + `lib/` class structure; plugin entry scripts live in the repo root, helper classes in `lib/`.
2. Reuse existing escaping/logging/config-path helpers rather than introducing new ones with different semantics.
3. Follow existing RRD access patterns via `lib/rrd.php` and poller-aware approaches.
4. Match existing frontend patterns in `js/` (jQuery, `loadPageNoHeader()`) rather than introducing new frontend frameworks.

## Common Pitfalls to Avoid

```php
// WRONG - printing unescaped request input
print "<input value='" . get_nfilter_request_var('filter') . "'>";

// CORRECT
print "<input value='" . html_escape_request_var('filter') . "'>";
```

## Version Control

Document all changes in `CHANGELOG.md`/`BACKLOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md`, `SECURITY.md`, `SECURITY-AUDIT.md` for feature and security details
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allow_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **Plugin table-creation API.** Use `api_plugin_db_table_create()`/`api_plugin_db_add_column()`
  (from Cacti core's `lib/plugins.php`) instead of raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN`.
  Both are idempotent (safe no-ops when already applied), so the same call can run unconditionally
  from both the install AND upgrade paths.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.
