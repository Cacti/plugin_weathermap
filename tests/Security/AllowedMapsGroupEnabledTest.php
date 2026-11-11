<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* is_weathermap_allowed() excludes members of a disabled user group, but the
 * list query in get_allowed_weathermaps() used to omit that filter, so a member
 * of a disabled group could still read a map's generated HTML through the list
 * and cycle views.  Both must gate group membership on uag.enabled. */
describe('get_allowed_weathermaps honours disabled groups', function (): void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php');

	it('filters the group-membership branch on enabled groups', function () use ($source): void {
		$fn = substr($source, strpos($source, 'function get_allowed_weathermaps'));
		$fn = substr($fn, 0, strpos($fn, "\n}"));

		expect($fn)->toContain("uag.enabled = 'on'");
		expect($fn)->toContain('user_auth_group_members');
	});

    it('keeps the same enabled check in is_weathermap_allowed', function () use ($source): void {
		$fn = substr($source, strpos($source, 'function is_weathermap_allowed'));
		$fn = substr($fn, 0, strpos($fn, "\n}"));

		expect($fn)->toContain('uag.enabled = "on"');
	});
});
