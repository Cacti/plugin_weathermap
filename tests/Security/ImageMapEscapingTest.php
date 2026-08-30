<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/HTML_ImageMap.class.php';

/* weathermap-cacti-plugin-mgmt.php renders a page at include time, so the one
 * function under test is lifted out of it rather than including the whole page. */
if (!function_exists('map_clean_title')) {
	$mgmtSource = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');

	if (preg_match('/^function map_clean_title\(.*?^}/ms', $mgmtSource, $fn) !== 1) {
		throw new RuntimeException('map_clean_title() could not be located');
	}

	$extracted = tempnam(sys_get_temp_dir(), 'wmmgmt') . '.php';
	file_put_contents($extracted, "<?php\ndeclare(strict_types = 1);\n" . $fn[0] . "\n");
	require_once $extracted;
	unlink($extracted);
}

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
});
