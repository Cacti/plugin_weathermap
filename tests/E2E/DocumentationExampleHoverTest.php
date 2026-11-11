<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
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
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// Pest's Composer proxy exposes the autoloader; PHPUnit needs it in isolated children.
if (!defined('PHPUNIT_COMPOSER_INSTALL') && isset($GLOBALS['_composer_autoload_path'])) {
	define('PHPUNIT_COMPOSER_INSTALL', $GLOBALS['_composer_autoload_path']);
}

/**
 * Renders the shipped docs/example/example.conf with the real engine and checks
 * that HTMLSTYLE overlib hovers work without the retired overLIB library.
 */
final class DocumentationExampleHoverTest extends TestCase {
	/** Engine-rendered fragment and standalone pages carry viewer hover data, not overLIB calls. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testExampleConfigRendersViewerHoverWithoutOverlib(): void {
		$plugin = dirname(__DIR__, 2);
		[$map, $fragment, $standalone] = self::renderExample($plugin);

		$this->assertSame('overlib', $map->htmlstyle);
		$this->assertSame(0, $GLOBALS['weathermap_warncount'], 'example.conf renders without warnings');

		foreach ([$fragment, $standalone] as $html) {
			$this->assertStringNotContainsStringIgnoringCase('overlib(', $html);
			$this->assertStringNotContainsStringIgnoringCase('overlib.js', $html);
			$this->assertStringNotContainsString('overDiv', $html);
		}

		$areas    = self::linkAreas(self::parse($fragment));
		$captions = [];
		$this->assertNotEmpty($areas, 'example links produce image-map areas');

		foreach ($areas as $area) {
			foreach ($area->attributes as $attribute) {
				$this->assertStringStartsNotWith('on', strtolower($attribute->name), 'no inline event handlers on ' . $area->getAttribute('id'));
			}

			$name = $area->getAttribute('data-caption');
			$link = $map->links[$name] ?? null;
			$this->assertNotNull($link, 'caption names a configured link');
			$captions[$name] = true;

			$hover = base64_decode($area->getAttribute('data-hover'), true);
			$this->assertIsString($hover, 'data-hover is valid base64');
			$images = self::parse($hover)->getElementsByTagName('img');
			$this->assertSame(count($link->overliburl[IN]), $images->length, "$name hover shows every OVERLIBGRAPH");

			foreach ($images as $index => $image) {
				$this->assertSame('395', $image->getAttribute('data-width'));
				$this->assertSame('153', $image->getAttribute('data-height'));
				$this->assertStringStartsWith($link->overliburl[IN][$index], $image->getAttribute('src'));
			}
		}

		// The static historical page uses the same captions as plain title tooltips.
		$static = self::parse(file_get_contents($plugin . '/docs/example/example.html'));
		$titles = [];

		foreach (self::linkAreas($static, 'title') as $area) {
			$titles[$area->getAttribute('title')] = true;
		}

		ksort($captions);
		ksort($titles);
		$this->assertSame(array_keys($captions), array_keys($titles), 'static example titles match engine captions');
		$this->assertSame(0, $static->getElementsByTagName('script')->length, 'static example loads no script');
	}

	/** The standalone page only references scripts that the plugin ships or Cacti provides. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testStandaloneExampleReferencesOnlyAvailableScripts(): void {
		$plugin             = dirname(__DIR__, 2);
		[, , $standalone]   = self::renderExample($plugin);
		$scripts            = self::parse($standalone)->getElementsByTagName('script');
		$cactiProvided      = ['/cacti/include/js/jquery.js', '/cacti/include/js/jquery-ui.js', '/cacti/include/js/pace.js'];
		$pluginScriptPrefix = '/cacti/plugins/weathermap/';
		$this->assertGreaterThan(0, $scripts->length);

		foreach ($scripts as $script) {
			$src = $script->getAttribute('src');

			if (str_starts_with($src, $pluginScriptPrefix)) {
				$this->assertFileExists($plugin . '/' . substr($src, strlen($pluginScriptPrefix)), "$src is shipped");
			} else {
				$this->assertContains($src, $cactiProvided, "$src is a Cacti core script");
			}
		}
	}

	/**
	 * Render docs/example/example.conf through ReadConfig, DrawMap and MakeHTML.
	 *
	 * The engine is constructed in the plugin root so data-source plugins load, then
	 * reads the example from its own directory so its relative fonts and background resolve.
	 * ReadData is skipped: the example's RRD targets are not shipped.
	 *
	 * @param string $plugin Absolute plugin root.
	 *
	 * @return array{0: WeatherMap, 1: string, 2: string} The map, the viewer fragment and the standalone page.
	 */
	private static function renderExample(string $plugin): array {
		chdir($plugin);
		require_once $plugin . '/setup.php';
		require_once $plugin . '/lib/WeatherMap.class.php';

		if (!function_exists('get_selected_theme')) {
			/**
			 * Stand in for Cacti's theme lookup used by standalone HTML.
			 *
			 * @return string The theme directory name.
			 */
			function get_selected_theme() {
				return 'modern';
			}
		}

		$GLOBALS['weathermap_warncount'] = 0;
		$map          = new WeatherMap();
		$map->rrdtool = PHP_BINARY;
		chdir($plugin . '/docs/example');

		if (!$map->ReadConfig('example.conf')) {
			throw new RuntimeException('Cannot parse docs/example/example.conf');
		}

		// DrawMap picks the image writer from the extension, so the temporary file needs .png.
		$temporary = tempnam(sys_get_temp_dir(), 'wm-example-');
		$output    = $temporary . '.png';
		unlink($temporary);

		try {
			$map->DrawMap($output);
		} finally {
			if (is_file($output)) {
				unlink($output);
			}
		}

		$map->imageuri = 'example.png';

		return [$map, $map->MakeHTML(), $map->MakeHTML('weathermap_example_imap', true)];
	}

	/**
	 * Parse an HTML fragment or page without emitting libxml warnings.
	 *
	 * @param string $html Markup to parse.
	 *
	 * @return DOMDocument The parsed document.
	 */
	private static function parse(string $html): DOMDocument {
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="UTF-8">' . $html);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $document;
	}

	/**
	 * Return the image-map areas for links that carry a hover attribute.
	 *
	 * @param DOMDocument $document  Parsed page.
	 * @param string      $attribute Attribute that marks a hover area.
	 *
	 * @return DOMElement[] Matching LINK areas.
	 */
	private static function linkAreas(DOMDocument $document, string $attribute = 'data-hover'): array {
		$areas = [];

		foreach ($document->getElementsByTagName('area') as $area) {
			if (str_starts_with($area->getAttribute('id'), 'LINK:') && $area->hasAttribute($attribute)) {
				$areas[] = $area;
			}
		}

		return $areas;
	}
}
