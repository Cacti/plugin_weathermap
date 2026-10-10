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

it('KeepsBuiltInFontsWithoutFreeTypeOrTheBundledFontFile', function (string $disabledFunction) {
	$plugin  = dirname(__DIR__, 2);
	$fixture = tempnam(sys_get_temp_dir(), 'wm-preset-');
	$code    = '<?php chdir(' . var_export($plugin, true) . '); require ' . var_export($plugin . '/tests/bootstrap-unit.php', true) . '; require ' . var_export($plugin . '/lib/WeatherMap.class.php', true) . '; require ';
	$check   = '; $map = new WeatherMap(); $before = [$map->links["DEFAULT"]->bwfont, $map->links["DEFAULT"]->commentfont]; wm_new_map_preset($map); if ($before !== [$map->links["DEFAULT"]->bwfont, $map->links["DEFAULT"]->commentfont]) { exit(1); } echo "PASS";';

	try {
		file_put_contents($fixture, $code . var_export($plugin . '/lib/editor.new-map-preset.php', true) . $check);
		exec(escapeshellarg(PHP_BINARY) . ' -d disable_functions=' . escapeshellarg($disabledFunction) . ' ' . escapeshellarg($fixture) . ' 2>&1', $output, $status);
		expect($status)->toBe(0)->and(implode("\n", $output))->toBe('PASS');
		$copy = $fixture . '.helper.php';
		file_put_contents($copy, file_get_contents($plugin . '/lib/editor.new-map-preset.php'));
		file_put_contents($fixture, $code . var_export($copy, true) . $check);
		$output = [];
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' 2>&1', $output, $status);
		expect($status)->toBe(0)->and(implode("\n", $output))->toBe('PASS');
	} finally {
		unlink($fixture);

		if (isset($copy) && file_exists($copy)) {
			unlink($copy);
		}
	}
})->with(['imagettfbbox', 'imagettftext']);

it('RendersSavedPresetsWhenEitherFreeTypeFunctionIsUnavailable', function (string $disabledFunction) {
	$fixture = dirname(__DIR__) . '/Support/SavedPresetFallbackRegression.php';
	$coverage = \PHPUnit\Runner\CodeCoverage::instance();
	$coverageFile = $coverage->isActive() && function_exists('xdebug_start_code_coverage') ? tempnam(sys_get_temp_dir(), 'wm-fallback-coverage-') : null;
	try {
		$command = escapeshellarg(PHP_BINARY) . ' -d disable_functions=' . escapeshellarg($disabledFunction) . ' ' . escapeshellarg($fixture);
		if ($coverageFile !== null) {
			$command .= ' ' . escapeshellarg($coverageFile);
		}
		exec($command . ' 2>&1', $output, $status);
		expect($status)->toBe(0)->and(implode("\n", $output))->toBe('PASS');
		if ($coverageFile !== null) {
			// Merge execution from the independently configured PHP runtime into Pest's report.
			$raw = json_decode(file_get_contents($coverageFile), true, 512, JSON_THROW_ON_ERROR);
			$coverage->codeCoverage()->append(\SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData::fromXdebugWithoutPathCoverage($raw), 'SavedPresetFallback-' . $disabledFunction);
		}
	} finally {
		if ($coverageFile !== null) {
			unlink($coverageFile);
		}
	}
})->with(['imagettfbbox', 'imagettftext']);
