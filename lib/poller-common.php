<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// common code used by the poller, the manual-run from the Cacti UI, and from the command-line manual-run.
// this is the easiest way to keep it all consistent!

function weathermap_memory_check($note = 'MEM') {
	if (function_exists('memory_get_usage')) {
		$mem_used = nice_bandwidth(memory_get_usage());
		$mem_allowed = ini_get('memory_limit');

		wm_debug("$note: memory_get_usage() says " . $mem_used . 'Bytes used. Limit is ' . $mem_allowed);
	}
}

function weathermap_cron_part($value, $checkstring) {
	// XXX - this should really handle a few more crontab niceties like */5 or 3,5-9 but this will do for now
	if ($checkstring == '*') {
		return (true);
	}

	if ($checkstring == sprintf('%s', $value)) {
		return (true);
	}

	if (preg_match('/\*\/(\d+)/', $checkstring, $matches)) {
		$mod = $matches[1];

		if (($value % $mod) == 0) {
			return true;
		}
	}

	return (false);
}

function weathermap_check_cron($time, $string) {
	if ($string == '') {
		return (true);
	}

	if ($string == '*') {
		return (true);
	}

	$lt = localtime($time, true);

	list($minute, $hour, $wday, $day, $month) = preg_split('/\s+/', $string);

	$matched = true;

	$matched = $matched && weathermap_cron_part($lt['tm_min'], $minute);
	$matched = $matched && weathermap_cron_part($lt['tm_hour'], $hour);
	$matched = $matched && weathermap_cron_part($lt['tm_wday'], $wday);
	$matched = $matched && weathermap_cron_part($lt['tm_mday'], $day);
	$matched = $matched && weathermap_cron_part($lt['tm_mon'] + 1, $month);

	return ($matched);
}

function weathermap_run_maps($mydir) {
	global $config;
	global $weathermap_debugging, $WEATHERMAP_VERSION;
	global $weathermap_map;
	global $weathermap_warncount;
	global $weathermap_poller_start_time;

	include_once($mydir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'HTML_ImageMap.class.php');
	include_once($mydir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Weathermap.class.php');

	$total_warnings = 0;
	$warning_notes = '';

	$start_time = time();

	if ($weathermap_poller_start_time == 0) {
		$weathermap_poller_start_time = $start_time;
	}

	$outdir  = $mydir . DIRECTORY_SEPARATOR . 'output';
	$confdir = $mydir . DIRECTORY_SEPARATOR . 'configs';

	$mapcount = 0;

    // take our debugging cue from the poller - turn on Poller debugging to get weathermap debugging
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		$weathermap_debugging = true;
		$mode_message = 'DEBUG mode is on';
	} else {
		$mode_message = 'Normal logging mode. Turn on DEBUG in Cacti for more information';
	}

	$quietlogging = read_config_option('weathermap_quiet_logging');

	// moved this outside the module_checks, so there should always be something in the logs!
	if ($quietlogging == 0) {
		cacti_log("Weathermap $WEATHERMAP_VERSION starting - $mode_message", true, 'WEATHERMAP');
	}

    if (wm_module_checks()) {
		weathermap_memory_check('MEM Initial');

		// move to the weathermap folder so all those relatives paths don't *have* to be absolute
		$orig_cwd = getcwd();

		chdir($mydir);

		set_config_option('weathermap_last_start_time', time());

		// first, see if the output directory even exists
		if (is_dir($outdir)) {
			// next, make sure that we stand a chance of writing files
			//// $testfile = realpath($outdir.'weathermap.permissions.test');
			$testfile = $outdir . DIRECTORY_SEPARATOR . 'weathermap.permissions.test';
			$testfd = fopen($testfile, 'w');

			if ($testfd) {
				fclose($testfd);
				unlink($testfile);

				$queryrows = db_fetch_assoc('SELECT m.*, g.name AS groupname
					FROM weathermap_maps m, weathermap_groups g
					WHERE m.group_id = g.id
					AND active = "on"
					ORDER BY sortorder, id');

				if (is_array($queryrows)) {
					wm_debug('Iterating all maps.');

					$imageformat = strtolower(read_config_option('weathermap_output_format'));
					$rrdtool_path = read_config_option('path_rrdtool');

					foreach ($queryrows as $map) {
						// reset the warning counter
						$weathermap_warncount = 0;

						// this is what will prefix log entries for this map
						$weathermap_map = '[Map ' . $map['id'] . '] ' . $map['configfile'];

						wm_debug('FIRST TOUCH');

						if (weathermap_check_cron($weathermap_poller_start_time, $map['schedule'])) {
							$mapfile        = $confdir . DIRECTORY_SEPARATOR . $map['configfile'];
							$htmlfile       = $outdir . DIRECTORY_SEPARATOR . $map['filehash'] . '.html';
							$imagefile      = $outdir . DIRECTORY_SEPARATOR . $map['filehash'] . '.' . $imageformat;
							$thumbimagefile = $outdir . DIRECTORY_SEPARATOR . $map['filehash'] . '.thumb.' . $imageformat;
							$resultsfile    = $outdir . DIRECTORY_SEPARATOR . $map['filehash'] . '.results.txt';
							$tempfile       = $outdir . DIRECTORY_SEPARATOR . $map['filehash'] . '.tmp.png';

							if (file_exists($mapfile)) {
								if ($quietlogging == 0) {
									wm_warn("Map: $mapfile -> $htmlfile & $imagefile", true);
								}

								set_config_option('weathermap_last_started_file', $weathermap_map);

								$map_start = time();
								weathermap_memory_check("MEM starting $mapcount");
								$wmap = new Weathermap;
								$wmap->context = 'cacti';

								// we can grab the rrdtool path from Cacti's config, in this case
								$wmap->rrdtool = $rrdtool_path;

								$wmap->ReadConfig($mapfile);

								$wmap->add_hint('mapgroup', $map['groupname']);
								$wmap->add_hint('mapgroupextra', ($map['group_id'] == 1 ? '' : $map['groupname']));

								# in the order of precedence - global extras, group extras, and finally map extras
								$queries = array();
								$queries[] = 'SELECT * FROM weathermap_settings WHERE mapid = 0 AND groupid = 0';
								$queries[] = 'SELECT * FROM weathermap_settings WHERE mapid = 0 AND groupid = ' . intval($map['group_id']);
								$queries[] = 'SELECT * FROM weathermap_settings WHERE mapid = ' . intval($map['id']);

								foreach ($queries as $sql) {
									$settingrows = db_fetch_assoc($sql);

									if (is_array($settingrows) && count($settingrows) > 0) {
										foreach ($settingrows as $setting) {
											$set_it = false;

											if ($setting['mapid'] == 0 && $setting['groupid'] == 0) {
												wm_debug('Setting additional (all maps) option: ' . $setting['optname'] . " to '" . $setting['optvalue'] . "'");
												$set_it = true;
											} elseif ($setting['groupid'] != 0) {
												wm_debug('Setting additional (all maps in group) option: ' . $setting['optname'] . " to '" . $setting['optvalue'] . "'");
												$set_it = true;
											} else {
												wm_debug('Setting additional map-global option: ' . $setting['optname'] . " to '" . $setting['optvalue'] . "'");
												$set_it = true;
											}

											if ($set_it) {
												$wmap->add_hint($setting['optname'], $setting['optvalue']);

												if (substr($setting['optname'], 0, 7) == 'nowarn_') {
													$code = strtoupper(substr($setting['optname'], 7));
													$weathermap_error_suppress[] = $code;
												}
											}
										}
									}
								}

								weathermap_memory_check("MEM postread $mapcount");
								$wmap->ReadData();
								weathermap_memory_check("MEM postdata $mapcount");

								// why did I change this before? It's useful...
								// $wmap->imageuri = $config['url_path'].'/plugins/weathermap/output/weathermap_'.$map['id'].".".$imageformat;
								$configured_imageuri = $wmap->imageuri;
								$wmap->imageuri = $config['url_path'].'plugins/weathermap/weathermap-cacti-plugin.php?action=viewimage&id=' . $map['filehash'] . '&time=' . time();

								if ($quietlogging == 0) {
									wm_warn('About to write image file. If this is the last message in your log, increase memory_limit in php.ini [WMPOLL01]', true);
								}

								weathermap_memory_check("MEM pre-render $mapcount");

								// Write the image to a temporary file first - it turns out that libpng is not that fast
								// and this way we avoid showing half a map
								$wmap->DrawMap($tempfile, $thumbimagefile, read_config_option('weathermap_thumbsize'));

								// Firstly, don't move or delete anything if the image saving failed
								if (file_exists($tempfile)) {
									// Don't try and delete a non-existent file (first run)
									if (file_exists($imagefile)) {
										unlink($imagefile);
									}

									rename($tempfile, $imagefile);
								}

   								if ($quietlogging == 0) {
									wm_warn("Wrote map to $imagefile and $thumbimagefile", true);
								}

								$fd = @fopen($htmlfile, 'w');

								if ($fd != false) {
									fwrite($fd, $wmap->MakeHTML('weathermap_' . $map['filehash'] . '_imap'));
									fclose($fd);

									wm_debug("Wrote HTML to $htmlfile");
								} else {
									if (file_exists($htmlfile)) {
										wm_warn("Failed to overwrite $htmlfile - permissions of existing file are wrong? [WMPOLL02]");
									} else {
										wm_warn("Failed to create $htmlfile - permissions of output directory are wrong? [WMPOLL03]");
									}
								}

								$wmap->WriteDataFile($resultsfile);

								// if the user explicitly defined a data file, write it there too
								if ($wmap->dataoutputfile) {
									$wmap->WriteDataFile($wmap->dataoutputfile);
								}

								// put back the configured imageuri
								$wmap->imageuri = $configured_imageuri;

								// if an htmloutputfile was configured, output the HTML there too
								// but using the configured imageuri and imagefilename
								if ($wmap->htmloutputfile != '') {
									$htmlfile = $wmap->htmloutputfile;
									$fd = @fopen($htmlfile, 'w');

									if ($fd !== false) {
										fwrite($fd, $wmap->MakeHTML('weathermap_' . $map['filehash'] . '_imap'));
										fclose($fd);

										wm_debug('Wrote HTML to %s', $htmlfile);
									} else {
										if (true === file_exists($htmlfile)) {
											wm_warn('Failed to overwrite ' . $htmlfile . ' - permissions of existing file are wrong? [WMPOLL02]');
										} else {
											wm_warn('Failed to create ' . $htmlfile . ' - permissions of output directory are wrong? [WMPOLL03]');
										}
									}
								}

								if ($wmap->imageoutputfile != '' && $wmap->imageoutputfile != 'weathermap.png' && file_exists($imagefile)) {
									// copy the existing image file to the configured location too
									@copy($imagefile, $wmap->imageoutputfile);
								}

								$processed_title = $wmap->ProcessString($wmap->title, $wmap);

								db_execute_prepared('UPDATE weathermap_maps
									SET titlecache = ?
									WHERE id = ?',
									array($processed_title, intval($map['id'])));

								if (intval($wmap->thumb_width) > 0) {
									db_execute_prepared('UPDATE weathermap_maps
										SET thumb_width = ?, thumb_height = ?
										WHERE id = ?',
										array(intval($wmap->thumb_width), intval($wmap->thumb_height), intval($map['id'])));
								}

								$wmap->CleanUp();
								unset($wmap);

								$map_duration = time() - $map_start;

								wm_debug("TIME: $mapfile took $map_duration seconds.");

								weathermap_memory_check("MEM after $mapcount");

								$mapcount++;

								set_config_option('weathermap_last_finished_file', $weathermap_map);
							} else {
								wm_warn("Mapfile $mapfile is not readable or doesn't exist [WMPOLL04]");
							}

							db_execute_prepared('UPDATE weathermap_maps
								SET warncount = ?
								WHERE id = ?',
								array(intval($weathermap_warncount), intval($map['id'])));

							$total_warnings += $weathermap_warncount;
							$weathermap_warncount = 0;
							$weathermap_map = '';
						} else {
							wm_debug('Skipping ' . $map['id'] . ' (' . $map['configfile'] . ') due to schedule.');
						}
					}

					wm_debug("Iterated all $mapcount maps.");
				} else {
					if ($quietlogging == 0) {
						wm_warn('No activated maps found. [WMPOLL05]');
					}
				}
			} else {
				wm_warn("Output directory ($outdir) isn't writable (tried to create '$testfile'). No maps created. You probably need to make it writable by the poller process (like you did with the RRA directory) [WMPOLL06]");

				$total_warnings++;
				$warning_notes .= ' (Permissions problem prevents any maps running WMPOLL06)';
			}
		} else {
			wm_warn("Output directory ($outdir) doesn't exist!. No maps created. You probably need to create that directory, and make it writable by the poller process (like you did with the RRA directory) [WMPOLL07]");

			$total_warnings++;
			$warning_notes .= ' (Output directory problem prevents any maps running WMPOLL07)';
		}

		weathermap_memory_check('MEM Final');

		chdir($orig_cwd);
		$duration = time() - $start_time;

		$stats_string = date(DATE_RFC822) . ": $mapcount maps were run in $duration seconds with $total_warnings warnings." . $warning_notes;
		if ($quietlogging == 0) {
			wm_warn("STATS: Weathermap $WEATHERMAP_VERSION run complete - $stats_string", true);
		}

		set_config_option('weathermap_last_stats', $stats_string);
		set_config_option('weathermap_last_finish_time', time());
	} else {
		wm_warn("Required modules for PHP Weathermap $WEATHERMAP_VERSION were not present. Not running. [WMPOLL08]");
	}
}

