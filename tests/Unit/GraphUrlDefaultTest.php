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
});
it('RegistersTheNewDefaultWithoutChangingStoredPreferences', function () {
	$GLOBALS['settings']                                          = [];
	$GLOBALS['tabs']                                              = [];
	$GLOBALS['__test_config_options']['weathermap_infourl_style'] = '0';
	$GLOBALS['__test_set_config_option_calls']                    = [];
	weathermap_config_settings();
	expect($GLOBALS['settings']['wmap']['weathermap_infourl_style']['default'])->toBe(1)->and(read_config_option('weathermap_infourl_style'))->toBe('0')->and($GLOBALS['__test_set_config_option_calls'])->toBeEmpty();
	unset($GLOBALS['__test_config_options']['weathermap_infourl_style']);
});
