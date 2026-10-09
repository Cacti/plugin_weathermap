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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Pest's Composer proxy exposes the autoloader; PHPUnit needs it in isolated children.
if (!defined('PHPUNIT_COMPOSER_INSTALL') && isset($GLOBALS['_composer_autoload_path'])) {
	define('PHPUNIT_COMPOSER_INSTALL', $GLOBALS['_composer_autoload_path']);
}

final class AttributeEscapingTest extends TestCase {
	/** Verify fallback escaping with no Cacti helper loaded. */
	#[Test]
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function EscapesAttributesOnOlderCactiVersions(): void {
		require_once __DIR__ . '/../../setup.php';
		expect(function_exists('html_escape_attr'))->toBeFalse();
		$value   = "Map 'quoted' \"double\" &quot; <tag> `";
		$escaped = plugin_weathermap_escape_attr($value);
		expect(html_entity_decode($escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($value)
			->and($escaped)->not->toContain("'", '"', '<tag>', '`')
			->and($escaped)->toContain('&amp;quot;');
	}

	/** Verify exactly one call to the native Cacti helper. */
	#[Test]
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function DelegatesAttributeEscapingToCactiWhenAvailable(): void {
		require_once __DIR__ . '/../../setup.php';
		expect(function_exists('html_escape_attr'))->toBeFalse();
		/**
		 * Emulate Cacti's native helper and count delegation calls.
		 *
		 * @param string $value The unescaped attribute value.
		 *
		 * @return string The escaped HTML attribute value.
		 */
		function html_escape_attr($value) {
			$GLOBALS['attribute_delegations'] = ($GLOBALS['attribute_delegations'] ?? 0) + 1;

			return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
		}
		$GLOBALS['attribute_delegations'] = 0;
		$value                            = 'Map "quoted" <tag> &quot;';
		expect(plugin_weathermap_escape_attr($value))->toBe(htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true))
			->and($GLOBALS['attribute_delegations'])->toBe(1);
	}
}
