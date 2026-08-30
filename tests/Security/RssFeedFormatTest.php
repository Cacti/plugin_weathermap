<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap.php';

/**
 * Render the feed's description item the way weathermap-cacti-plugin.php does.
 *
 * @param  string $maptitle map title, already escaped
 * @return string the rendered item fragment
 */
function wm_render_feed_item($maptitle) {
	ob_start();

	printf('<description>%s</description>
		<link>%s</link>
		<media:thumbnail url="%s"/>
		<media:content url="%s"/>
		<guid isPermaLink="false">%s%s</guid>
		</item>',
		__('Network Weathermap named "%s"', $maptitle, 'weathermap'),
		'LINK', 'THUMB', 'BIG', 'PATH', 'GUID');

	return ob_get_clean();
}

describe('RSS feed rendering', function () {
	/* __() runs its own sprintf, so concatenating its result into printf's
	 * format made the map title part of that format.  A percent sign in a
	 * title then aborted the feed: "Core 100% Uptime" raised a ValueError and
	 * "Link %s Status" an ArgumentCountError, truncating the XML mid document
	 * for that map and every map after it.  action=mrss only needs the View
	 * Weathermaps realm, and 100% in a title is ordinary usage. */
	it('renders a title containing a percent sign', function () {
		$out = wm_render_feed_item('Core 100% Uptime');

		expect($out)->toContain('Core 100% Uptime');
		expect($out)->toContain('</item>');
	});

	it('renders a title containing a format specifier', function () {
		$out = wm_render_feed_item('Link %s Status');

		expect($out)->toContain('Link %s Status');
		expect($out)->toContain('</item>');
	});

	it('renders a title containing a doubled percent', function () {
		expect(wm_render_feed_item('Fifty %% Load'))->toContain('</item>');
	});

	it('still binds every url placeholder to the right argument', function () {
		$out = wm_render_feed_item('Core 100% Uptime');

		expect($out)->toContain('<link>LINK</link>');
		expect($out)->toContain('url="THUMB"');
		expect($out)->toContain('url="BIG"');
		expect($out)->toContain('>PATHGUID<');
	});

	it('never builds the printf format by concatenating a translated title', function () {
		$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');

		// printf('...' . __(...) . '...') puts caller data into the format string.
		expect($source)->not->toMatch("/printf\(\s*'[^']*'\s*\.\s*__\(/");
	});
});
