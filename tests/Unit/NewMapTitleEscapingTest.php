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

it('EscapesTranslatedTitleFieldsInTheActualCreateForm', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-mgmt.php');
	$start = strpos($source, "<td><label for='newtitle'>");
	$end = strpos($source, '</td>', strpos($source, "<input id='newtitle'", $start)) + strlen('</td>');
	$fragment = substr($source, $start, $end - $start);
	$payload = "Title' autofocus onfocus='alert(1) <script>";
	// Substitute translated output before the escape helper executes.
	$fragment = str_replace(["'Map Title'", "'Optional map title'"], var_export($payload, true), $fragment);
	ob_start();
	eval('?>' . $fragment);
	$html = ob_get_clean();
	expect($html)->toContain('&apos;', '&lt;script&gt;')->not->toContain("placeholder='Title' autofocus", '<script>');
});
