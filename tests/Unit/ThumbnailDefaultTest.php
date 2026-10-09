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

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/setup.php';
	require_once dirname(__DIR__, 2) . '/includes/database.php';
});
it('RegistersTheNewDefaultWithoutChangingStoredPreferences', function () {
	$GLOBALS['settings']                                      = [];
	$GLOBALS['tabs']                                          = [];
	$GLOBALS['__test_config_options']['weathermap_thumbsize'] = '250';
	$GLOBALS['__test_set_config_option_calls']                = [];
	weathermap_config_settings();
	expect($GLOBALS['settings']['wmap']['weathermap_thumbsize']['default'])->toBe(1000)->and(read_config_option('weathermap_thumbsize'))->toBe('250')->and($GLOBALS['__test_set_config_option_calls'])->toBeEmpty();
	unset($GLOBALS['__test_config_options']['weathermap_thumbsize']);
});

it('InitializesMissingAndNonPositiveThumbnailSizesAndPreservesPositiveValues', function () {
	$old_options = $GLOBALS['__test_config_options'] ?? [];
	$old_calls   = $GLOBALS['__test_set_config_option_calls'] ?? [];
	$old_tables  = $GLOBALS['__test_table_exists'] ?? [];
	$old_db_calls = $GLOBALS['__test_db_calls'] ?? [];

	try {
		foreach (['', plugin_weathermap_numeric_version()] as $version) {
			$GLOBALS['__test_table_exists']['weathermap_maps'] = true;
			foreach (['' => '1000', '0' => '1000', '-5' => '1000', '250' => null] as $value => $expected) {
				$GLOBALS['__test_config_options']          = ['weathermap_db_version' => $version, 'weathermap_thumbsize' => (string) $value];
				$GLOBALS['__test_set_config_option_calls'] = [];
				$GLOBALS['__test_db_calls'] = [];
				weathermap_setup_table();
				if ($version !== '') {
					expect($GLOBALS['__test_db_calls'])->toBeEmpty();
				}
				$calls = array_values(array_filter($GLOBALS['__test_set_config_option_calls'], fn ($call) => $call['name'] === 'weathermap_thumbsize'));
				expect($calls)->toBe($expected === null ? [] : [['name' => 'weathermap_thumbsize', 'value' => $expected]]);
			}
		}
	} finally {
		$GLOBALS['__test_table_exists'] = $old_tables;
		$GLOBALS['__test_db_calls'] = $old_db_calls;
		$GLOBALS['__test_config_options']          = $old_options;
		$GLOBALS['__test_set_config_option_calls'] = $old_calls;
	}
});
