<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/HTML_ImageMap.class.php';

require_once dirname(__DIR__, 2) . '/lib/map-title.php';

describe('generated image map markup', function () {
	/* The poller writes this markup to output/<hash>.html and the plugin pages
	 * bring it back with include(), so values taken from a map config have to
	 * be escaped on the way in rather than trusted. */
	it('escapes an href taken from the map config', function () {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = 'http://example.com/"><b>x</b>';

		$html = $area->common_html();

		expect($html)->not->toContain('"><b>');
		expect($html)->toContain('&');
	});

	it('leaves an ordinary href usable', function () {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = 'http://example.com/graph.php?id=1';

		expect($area->common_html())->toContain('http://example.com/graph.php?id=1');
	});

	it('emits no PHP open tag for an href that contains one', function () {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = 'MARKER<?= X ?>MARKER';

		expect($area->common_html())->not->toContain('<?');
	});

	it('emits nohref when no link is set', function () {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);

		expect($area->common_html())->toContain('nohref');
	});

	/* html_escape() keeps an INFOURL inside the quoted attribute but leaves an
	 * executable-scheme URL clickable, so a javascript:/data:/vbscript: link in
	 * a map config would still run script in a viewer's session.  Those are
	 * dropped to nohref while ordinary http(s) and relative Cacti links stay. */
	it('drops an executable-scheme href to nohref', function (string $url) {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = $url;

		$html = $area->common_html();

		expect($html)->toContain('nohref');
		expect($html)->not->toContain('href="');
	})->with([
		'javascript scheme'            => ['javascript:alert(document.domain)'],
		'mixed-case javascript scheme' => ['JavaScript:alert(1)'],
		'whitespace-obfuscated scheme' => ["java\tscript:alert(1)"],
		'leading-space scheme'         => ['  javascript:alert(1)'],
		'data scheme'                  => ['data:text/html,<script>alert(1)</script>'],
		'vbscript scheme'              => ['vbscript:msgbox(1)'],
		// Cacti's html_escape() uses double_encode = false, so these entities
		// survive into the attribute and the browser decodes them once into
		// an executable javascript: link.  wm_href_is_safe() decodes first.
		'hex-entity letter'            => ['java&#x73;cript:alert(1)'],
		'decimal-entity letter'        => ['java&#115;cript:alert(1)'],
		'named-colon entity'           => ['javascript&colon;alert(1)'],
		'hex-entity colon'            => ['javascript&#x3a;alert(1)'],
		'entity tab in scheme'         => ['java&#9;script:alert(1)'],
	]);

	/* Belt and braces: with the faithful html_escape() stub (double_encode =
	 * false), confirm the rendered markup for an entity-encoded scheme carries
	 * no href the browser could decode into javascript:, i.e. it is nohref. */
	it('emits nohref markup that cannot decode to a javascript scheme', function () {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = 'java&#x73;cript:alert(1)';

		$html = $area->common_html();

		expect($html)->toContain('nohref');
		expect(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->not->toContain('javascript:');
	});

	it('keeps an ordinary http(s) or relative href', function (string $url) {
		$area = new HTML_ImageMap_Area_Rectangle('NODE:n1', '', [[0, 0, 10, 10]]);
		$area->href = $url;

		$html = $area->common_html();

		expect($html)->toContain('href="');
		expect($html)->not->toContain('nohref');
	})->with([
		'absolute http'       => ['http://example.com/graph.php?id=1'],
		'absolute https'      => ['https://example.com/'],
		'relative cacti page' => ['graph_view.php?action=tree&node=1'],
		'root-relative path'  => ['/cacti/graph_view.php'],
		'protocol relative'   => ['//example.com/x'],
		'anchor only'         => ['#here'],
		'query with colon'    => ['graph_view.php?x=a:b'],
	]);

	/* wm_href_is_safe() guards its own inputs: a non-string href (the property
	 * is loosely typed) and a value that is only whitespace or control
	 * characters both resolve to unusable, so the area must fall back to
	 * nohref rather than emit an empty or malformed href. */
	it('rejects a non-string href value', function (mixed $url) {
		expect(wm_href_is_safe($url))->toBeFalse();
	})->with([
		'null'    => [null],
		'integer' => [42],
		'array'   => [['http://example.com']],
	]);

	it('rejects an href that is only whitespace or control characters', function (string $url) {
		expect(wm_href_is_safe($url))->toBeFalse();
	})->with([
		'spaces'          => ['    '],
		'tabs and nl'     => ["\t\r\n"],
		'nulls'           => ["\x00\x01"],
	]);
});

describe('map_clean_title()', function () {
	/* A duplicated map has its title substituted into the copied config file,
	 * which is line oriented.  A newline in the title would start a fresh
	 * directive such as NODE or INFOURL in that config. */
	it('flattens a newline that would start a new config directive', function () {
		expect(map_clean_title("Site A\nINFOURL http://evil"))->toBe('Site A INFOURL http://evil');
	});

	it('strips carriage returns, tabs and nulls', function () {
		expect(map_clean_title("a\rb"))->toBe('a b');
		expect(map_clean_title("a\tb"))->toBe('a b');
		expect(map_clean_title("a\x00b"))->toBe('a b');
	});

	it('trims the result', function () {
		expect(map_clean_title("\n  Site A  \n"))->toBe('Site A');
	});

	it('leaves an ordinary title alone', function () {
		expect(map_clean_title('Core Network - Site A'))->toBe('Core Network - Site A');
	});

	/* The title reaches this through str_replace() on a request variable, so a
	 * parameter sent as title[]= arrives as an array rather than a string. */
	it('yields an empty title for non-string input', function () {
		expect(map_clean_title(['a', 'b']))->toBe('');
		expect(map_clean_title(null))->toBe('');
		expect(map_clean_title(42))->toBe('');
	});

	it('yields an empty title for a value that is only control characters', function () {
		expect(map_clean_title("\n\t\r"))->toBe('');
	});

	/* A percent sign is left alone here.  It is ordinary in a map title, and
	 * the feed and the messages that render the title now pass it as a printf
	 * argument rather than building it into a format, so it needs no escaping
	 * at this end.  Pinned so the two decisions cannot drift apart. */
	it('passes a percent sign through untouched', function () {
		expect(map_clean_title('Core 100% Uptime'))->toBe('Core 100% Uptime');
		expect(map_clean_title('Link %s Status'))->toBe('Link %s Status');
		expect(map_clean_title('Fifty %% Load'))->toBe('Fifty %% Load');
	});
});
