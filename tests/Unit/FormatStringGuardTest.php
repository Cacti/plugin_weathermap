<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';

/* mysprintf() applies a map label/notes/title as a printf format string.  Those
 * values come from the map config, so a hostile or careless one can carry extra
 * or unknown conversion specifiers.  Under PHP 8 sprintf() throws rather than
 * warning, and mysprintf() runs inside the poller's unguarded map loop, so an
 * unhandled throw would abort the whole poll.  The guard must degrade to literal
 * text instead. */
describe('mysprintf format-string guard', function (): void {
	it('does not throw when the format has more specifiers than arguments', function (): void {
		$out = mysprintf('%s%s', 'value');

		expect($out)->toBeString();
	});

	it('does not throw on an unknown conversion specifier', function (): void {
		$out = mysprintf('%y', 'value');

		expect($out)->toBeString();
	});

	it('falls back to the literal format text on a bad format', function (): void {
		expect(mysprintf('%s%s%s', 'x'))->toBe('%s%s%s');
	});

	it('still substitutes a single ordinary specifier', function (): void {
		expect(mysprintf('[%s]', 'ok'))->toBe('[ok]');
	});

	it('still formats a plain numeric value with %d', function (): void {
		expect(mysprintf('%d', '42'))->toBe('42');
	});
});
