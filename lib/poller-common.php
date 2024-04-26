<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2024 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
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

function weathermap_repair_maps() {
	global $config;

	$mydir = $config['base_path'] . '/plugins/weathermap/';

	include_once($mydir . 'lib/WeatherMap.class.php');

	$outdir  = $mydir . 'output';
	$confdir = $mydir . 'configs';

	$mapcount = 0;

	wm_debug('My Dir is: ' . $mydir);

	$maps = db_fetch_assoc('SELECT * FROM weathermap_maps');

	chdir($mydir);

	if (cacti_sizeof($maps)) {
		foreach($maps as $map) {
			$changes = 0;

			$configfile = $confdir . '/' . $map['configfile'];

			wm_debug('---------------------------------------------');
			wm_debug("Processing Map File $configfile");

			if (file_exists($configfile) && is_writable($configfile)) {
				$contents    = file($configfile);
				$outcontents = array();

				foreach($contents as $line) {
					if (strpos($line, 'BACKGROUND') !== false) {
						$parts   = explode('BACKGROUND', $line);
						$dirgood = false;
						$bgfile  = trim($parts[1]);

						if (strpos($bgfile, 'images/backgrounds/') === false) {
							wm_debug('Found background file reference in old location: ' . $bgfile);

							if (file_exists($mydir . 'images/backgrounds/' . basename($bgfile))) {
								wm_debug('Background file found in new location! Updating path only.');

								$line = 'BACKGROUND images/backgrounds/' . basename($bgfile);

								$changes++;
							} else {
								wm_debug('Background file not found in new location! Attmepting to move.');

								if (!is_dir($mydir . 'images/backgrounds')) {
									$dirgood = mkdir($mydir . 'images/backgrounds');
								} else {
									$dirgood = true;
								}

								if ($dirgood && file_exists($mydir . $bgfile)) {
									wm_debug('Found background file on disk: ' . $mydir . $bgfile . ' relocating!');

									$old = $mydir . $bgfile;
									$new = $mydir . 'images/backgrounds/' . basename($bgfile);

									if (is_writable($mydir . $bgfile)) {
										if (is_writeable($old) && is_writeable(dirname($new)) && is_writeable($new)) {
											if (rename($old, $new)) {
												$line = 'BACKGROUND images/backgrounds/' . basename($bgfile);
												$changes++;
											} else {
												wm_warn(sprintf('Unable to move BACKGROUND %s to %s, Permission error [WMPOLL09]', $old, $new));
											}
										} else {
											wm_warn(sprintf('Unable to move BACKGROUND %s to %s, Permission error [WMPOLL09]', $old, $new));
										}
									} else {
										wm_warn(sprintf('Unable to move BACKGROUND %s to %s, Permission error [WMPOLL09]', $old, $new));
									}
								} else {
									wm_warn(sprintf('BACKGROUND file not found on disk: %s skipping! [WMPOLL10]', $mydir . $objfile));
								}
							}
						} elseif (strpos($bgfile, 'images/backgrounds/') !== false) {
							wm_debug('Found background file reference in correct location: ' . $bgfile);
						}
					} elseif (strpos($line, 'ICON') !== false) {
						/**
						 * We are looking at the ICON parameter.  It can take two forms.  The first form is
						 * a path to a filename, or the pattern as follows
						 *
						 * ICON height width type
						 *
						 * Types include:
						 *
						 * - box - a square cornered box
						 * - rbox - a round cornered box
						 * - round - a circle or an ellipse
						 * - inpie & outpie to produce a pie chart of either the in or out value relative to it's maximum
						 * - nlink - to produce a circular 'yin-yang' style symbox with each half showing the in and out values
						 *
						 */
						$parts   = explode('ICON', $line);
						$dirgood = false;
						$attribs = explode(' ', $parts[1]);

						if (cacti_sizeof($attribs) == 1) {
							$objfile = trim($parts[1]);

							if (strpos($objfile, 'images/objects/') === false) {
								wm_debug('Found icon file reference in old location: ' . $objfile);

								if (file_exists($mydir . 'images/objects/' . basename($objfile))) {
									wm_debug('Found file already in new location! Updating path only.');

									$line = "\tICON images/objects/" . basename($objfile);

									$changes++;
								} else {
									if (!is_dir($mydir . 'images/objects')) {
										$dirgood = mkdir($mydir . 'images/objects');
									} else {
										$dirgood = true;
									}

									if ($dirgood && file_exists($mydir . $objfile)) {
										wm_debug('Object file found on disk: ' . $mydir . $objfile . ' relocating!');

										$old = $mydir . $objfile;
										$new = $mydir . 'images/objects/' . basename($objfile);

										if (is_writable($mydir . $bgfile)) {
											if (is_writeable($old) && is_writeable(dirname($new)) && is_writeable($new)) {
												if (rename($old, $new)) {
													$line = "\tICON images/objects/" . basename($objfile);
													$changes++;
												} else {
													wm_warn(sprintf('Unable to move ICONFILE %s to %s, Permission error [WMPOLL11]', $old, $new));
												}
											} else {
												wm_warn(sprintf('Unable to move ICONFILE %s to %s, Permission error [WMPOLL11]', $old, $new));
											}
										} else {
											wm_warn(sprintf('Unable to move ICONFILE %s to %s, Permission error [WMPOLL11]', $old, $new));
										}
									} else {
										wm_warn(sprintf('ICONFILE file not found on disk: %s skipping! [WMPOLL12]', $mydir . $objfile));
									}
								}
							} elseif (strpos($objfile, 'images/objects/') !== false) {
								wm_debug('Found object file reference in correct location: ' . $objfile);
							}
						}
					}

					$outcontents[] = rtrim($line);
				}
			}

			if ($changes) {
				wm_debug('Writing updated file to: ' . $configfile);
				file_put_contents($configfile, implode("\n", $outcontents), LOCK_EX);
			} else {
				wm_debug('File has correct BACKGROUND and ICON paths: ' . $configfile);
			}
		}
	}
}

function weathermap_run_maps($mydir, $force = false, $maps = array()) {
	global $config;
	global $weathermap_debugging;
	global $weathermap_map;
	global $weathermap_warncount;
	global $weathermap_poller_start_time;

	include_once($mydir . '/lib/HTML_ImageMap.class.php');
	include_once($mydir . '/lib/WeatherMap.class.php');

	// Check and enable boost supprt if it's enabled
	weathermap_check_set_boost();

	$total_warnings = 0;
	$warning_notes  = '';

	$start_time = time();

	if ($weathermap_poller_start_time == 0) {
		$weathermap_poller_start_time = $start_time;
	}

	if ($force) {
		cacti_log('NOTE: Weathermap Run is a Force Run', false, 'WEATHERMAP');
	}

	$outdir  = $mydir . '/output';
	$confdir = $mydir . '/configs';

	$mapcount = 0;

    // take our debugging cue from the poller - turn on Poller debugging to get weathermap debugging
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		$weathermap_debugging = true;
		$mode_message = 'DEBUG mode is on';
	} else {
		$mode_message = 'Normal logging mode. Turn on DEBUG in Cacti for more information';
	}

	$quietlogging = read_config_option('weathermap_quiet_logging');

	$total_start = microtime(true);

	weathermap_memory_check('MEM Initial');

	// move to the weathermap folder so all those relatives paths don't *have* to be absolute
	$orig_cwd = getcwd();

	chdir($mydir);

	if (!$force && !cacti_sizeof($maps)) {
		set_config_option('weathermap_last_start_time', time());
	}

	// first, see if the output directory even exists
	if (is_dir($outdir) && is_writable($outdir)) {
		if (cacti_sizeof($maps)) {
			$sql_where = 'AND m.id IN (' . implode(', ', $maps) . ')';
		} else {
			$sql_where = '';
		}

		$queryrows = db_fetch_assoc("SELECT m.*, g.name AS groupname
			FROM weathermap_maps m, weathermap_groups g
			WHERE m.group_id = g.id
			AND active = 'on'
			$sql_where
			ORDER BY sortorder, id");

		if (is_array($queryrows)) {
			wm_debug('Iterating all maps.');

			$imageformat  = strtolower(read_config_option('weathermap_output_format'));
			$rrdtool_path = read_config_option('path_rrdtool');

			foreach ($queryrows as $map) {
				$start = microtime(true);

				// reset the warning counter
				$weathermap_warncount = 0;

				if ($map['filehash'] == '') {
					db_execute_prepared('UPDATE weathermap_maps
						SET filehash = LEFT(MD5(CONCAT(id, configfile, rand())), 20)
						WHERE id = ?',
						array($map['id']));

					$map['filehash'] = db_fetch_cell_prepared('SELECT filehash
						FROM weathermap_maps
						WHERE id = ?',
						array($map['id']));
				}

				// this is what will prefix log entries for this map
				$weathermap_map = '[Map ' . $map['id'] . '] ' . $map['configfile'];

				wm_debug('FIRST TOUCH');

				if (weathermap_check_cron($weathermap_poller_start_time, $map['schedule']) || $force) {
					$mapfile        = $confdir . '/' . $map['configfile'];
					$htmlfile       = $outdir  . '/' . $map['filehash'] . '.html';
					$imagefile      = $outdir  . '/' . $map['filehash'] . '.' . $imageformat;
					$thumbimagefile = $outdir  . '/' . $map['filehash'] . '.thumb.' . $imageformat;
					$resultsfile    = $outdir  . '/' . $map['filehash'] . '.results.txt';
					$tempfile       = $outdir  . '/' . $map['filehash'] . '.tmp.png';

					if (file_exists($mapfile)) {
						wm_debug("Map: $mapfile -> $htmlfile & $imagefile", true);

						if (!$force && !cacti_sizeof($maps)) {
							set_config_option('weathermap_last_started_file', $weathermap_map);
						}

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

							if (cacti_sizeof($settingrows)) {
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
						$wmap->imageuri = $config['url_path'] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewimage&id=' . $map['filehash'] . '&time=' . time();

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

						wm_debug("Wrote map to $imagefile and $thumbimagefile", true);

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
								if (file_exists($htmlfile)) {
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

						if (!$force && !cacti_sizeof($maps)) {
							set_config_option('weathermap_last_finished_file', $weathermap_map);
						}
					} else {
						wm_warn(sprintf("Mapfile %s is not readable or doesn't exist [WMPOLL04]", $mapfile));
					}

					db_execute_prepared('UPDATE weathermap_maps
						SET warncount = ?
						WHERE id = ?',
						array(intval($weathermap_warncount), intval($map['id'])));

					$total_warnings += $weathermap_warncount;
					$weathermap_warncount = 0;
					$weathermap_map = '';

					$end = microtime(true);

					cacti_log(sprintf('MAPSTATS Time:%0.2f MapId:%d MapFile:%s Warnings:%d', $end - $start, $map['id'], basename($mapfile), $weathermap_warncount), false, 'WEATHERMAP', POLLER_VERBOSITY_MEDIUM);

					if (db_column_exists('weathermap_maps', 'last_runtime')) {
						db_execute_prepared('UPDATE weathermap_maps
							SET duration = ?, last_runtime = ?
							WHERE id = ?',
							array($end - $start, time(), $map['id']));
					}
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
	} elseif (!is_writable($outdir)) {
		wm_warn("Output directory ($outdir) isn't writable.  No maps created.  The output directory must be writable by both the Web Server and Data Collector processes. [WMPOLL06]");

		$total_warnings++;
		$warning_notes .= ' (Outdir Perm WMPOLL06)';
	} else {
		wm_warn("Output directory ($outdir) doesn't exist!.  No maps created.  The output directory must exist and be writable by both the Web Server and Data Collector processes. [WMPOLL07]");

		$total_warnings++;
		$warning_notes .= ' (Outdir Missing WMPOLL07)';
	}

	weathermap_memory_check('MEM Final');

	chdir($orig_cwd);

	$duration = microtime(true) - $total_start;

	if ($warning_notes == '') {
		$warning_notes = 'None';
	}

	if (!$force && !cacti_sizeof($maps)) {
		$stats_string = sprintf('Time:%0.2f Maps:%d Warnings:%s Notes:%s', $duration, $mapcount, $total_warnings, $warning_notes);

		cacti_log("STATS: WEATHERMAP $stats_string", true, 'SYSTEM');

		set_config_option('weathermap_last_stats', $stats_string);
		set_config_option('weathermap_last_finish_time', time());
	}
}

