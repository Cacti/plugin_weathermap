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

it('LoadsViewerSetupAfterAuthenticationBeforeRenderingOrDispatch', function () {
	$source   = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin.php');
	$auth     = strpos($source, "include_once('../../include/auth.php');");
	$setup    = strpos($source, "require_once __DIR__ . '/setup.php';");
	$dispatch = strpos($source, 'switch (get_request_var');
	expect($auth)->not->toBeFalse()->and($setup)->not->toBeFalse()->and($dispatch)->not->toBeFalse();
	expect($auth)->toBeLessThan($setup)->and($setup)->toBeLessThan($dispatch);
});
