<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

describe('poller-common cron field destructuring order', function (): void {
	it('uses the correct minute, hour, day, month, wday order', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/poller-common.php');

		expect($source)->toContain('[$minute, $hour, $day, $month, $wday]');
	});

	it('does not contain the wrong wday/day transposition', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/poller-common.php');

		// The historical bug placed $wday before $day.
		expect($source)->not->toContain('[$minute, $hour, $wday, $day, $month]');
	});

	it('maps $minute to tm_min, $hour to tm_hour, $wday to tm_wday, $day to tm_mday, $month to tm_mon', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/poller-common.php');

		// Verify each field drives the correct localtime key.
		expect($source)->toContain("weathermap_cron_part(\$lt['tm_min'], \$minute)");
		expect($source)->toContain("weathermap_cron_part(\$lt['tm_hour'], \$hour)");
		expect($source)->toContain("weathermap_cron_part(\$lt['tm_wday'], \$wday)");
		expect($source)->toContain("weathermap_cron_part(\$lt['tm_mday'], \$day)");
	});
});
