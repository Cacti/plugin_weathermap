<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                  |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

beforeAll(function () {
	if (!function_exists('weathermap_cycle_controls')) {
		$source = file_get_contents(__DIR__ . '/../../weathermap-cacti-plugin.php');
		$start  = strpos($source, 'function weathermap_cycle_controls(');
		eval(substr($source, $start));
	}
});

it('PreservesTranslationArgumentsWithAndWithoutAnExplicitDomain', function () {
	expect(__('Network Weathermap named "%s"', 'Example Map'))->toBe('Network Weathermap named "Example Map"')
		->and(__('Current graph: %s', 'Traffic', 'weathermap'))->toBe('Current graph: Traffic')
		->and(__('Map %s has %d links', 'Example', 3, 'weathermap'))->toBe('Map Example has 3 links')
		->and(__esc('Graph "%s"', 'A&B', 'weathermap'))->toBe('Graph &quot;A&amp;B&quot;');
});

it('RendersTheCountdownPlaceholderThroughTheTranslationFormatter', function (bool $fullscreen) {
	$html     = weathermap_cycle_controls($fullscreen, 1);
	$document = new DOMDocument();
	$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath     = new DOMXPath($document);
	$countdown = $xpath->query('//*[@id="wm_countdown"]')->item(0);
	expect($countdown)->not->toBeNull()
		->and($countdown->getAttribute('data-next-label'))->toBe('Next map in %ss')
		->and($countdown->getAttribute('data-paused-label'))->toBe('Paused');
})->with([false, true]);
