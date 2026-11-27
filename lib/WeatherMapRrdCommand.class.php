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

/**
 * Validates RRD file paths and assembles shell-escaped rrdtool commands
 * without requiring a Cacti bootstrap.
 */
class WeatherMapRrdCommand {
	/** @var callable(string): string */
	private $escaper;

	/**
	 * @param (callable(string): string)|null $escaper Optional shell-argument escaper.
	 */
	public function __construct(?callable $escaper = null) {
		$this->escaper = $escaper ?? (function_exists('cacti_escapeshellarg') ? 'cacti_escapeshellarg' : 'escapeshellarg');
	}

	/**
	 * Validate an RRD file path before embedding it in an rrdtool command.
	 *
	 * Requires an absolute filesystem path and rejects NUL bytes, ASCII control
	 * characters, and shell metacharacters.
	 *
	 * @param mixed $rrdfile Candidate RRD filesystem path.
	 *
	 * @return bool True when the path is safe to pass to rrdtool.
	 */
	public static function isValidPath($rrdfile) {
		if (!is_string($rrdfile) || $rrdfile === '' || $rrdfile[0] !== '/') {
			return false;
		}

		if (preg_match('/[\x00-\x1f\x7f]/', $rrdfile) === 1) {
			return false;
		}

		if (strpbrk($rrdfile, ";|&`\$()<>\"'\\!*?[]{}~#") !== false) {
			return false;
		}

		return true;
	}

	/**
	 * Decide whether an rrd_options string is safe to append to the RRDTool
	 * command line.
	 *
	 * Each option token is passed through the shell escaper before it reaches
	 * the shell, so this is a second line of defence rather than the only one.
	 * A quote or backslash is rejected because it turns one intended flag into
	 * several arguments.
	 *
	 * @param string|null $options rrd_options as given in the map config.
	 *
	 * @return bool True when the value holds no quote and no backslash.
	 */
	public static function optionsAreSafe($options) {
		return strpbrk((string) $options, "\"'\\") === false;
	}

	/**
	 * Build a shell-escaped rrdtool graph (VDEF aggregate) command string.
	 *
	 * @param string      $rrdtool          Path to the rrdtool binary.
	 * @param string      $rrdfile          Validated absolute path to the RRD file.
	 * @param string      $cf               Consolidation function (e.g. AVERAGE).
	 * @param string      $aggregatefn      VDEF aggregation function.
	 * @param string      $start            Start time specification.
	 * @param string      $end              End time specification.
	 * @param string      $ds_in            Inbound data source name, or '-'.
	 * @param string      $ds_out           Outbound data source name, or '-'.
	 * @param string|null $extra_options    Optional extra rrdtool flags.
	 * @param bool        $options_rejected Set to true when $extra_options fails validation.
	 *
	 * @throws InvalidArgumentException When $rrdfile fails path validation.
	 *
	 * @return string Shell command ready for popen().
	 */
	public function buildAggregateCommand(
		string $rrdtool,
		string $rrdfile,
		string $cf,
		string $aggregatefn,
		string $start,
		string $end,
		string $ds_in,
		string $ds_out,
		?string $extra_options = null,
		bool &$options_rejected = false
	): string {
		if (!self::isValidPath($rrdfile)) {
			throw new InvalidArgumentException('Invalid RRD file path');
		}

		$args   = [];
		$args[] = 'graph';
		$args[] = '/dev/null';
		$args[] = '-f';
		$args[] = "'%s %lu %lu'";
		$args[] = '--start';
		$args[] = $start;
		$args[] = '--end';
		$args[] = $end;

		if ($ds_in !== '-') {
			$args[] = "DEF:in=$rrdfile:" . $ds_in . ":$cf";
			$args[] = "VDEF:agg_in=in,$aggregatefn";
			$args[] = "PRINT:agg_in:'IN %lf'";
		}

		if ($ds_out !== '-') {
			$args[] = "DEF:out=$rrdfile:" . $ds_out . ":$cf";
			$args[] = "VDEF:agg_out=out,$aggregatefn";
			$args[] = "PRINT:agg_out:'OUT %lf'";
		}

		return $this->assemble($rrdtool, $args, $extra_options, $options_rejected);
	}

	/**
	 * Build a shell-escaped rrdtool fetch command string.
	 *
	 * @param string      $rrdtool          Path to the rrdtool binary.
	 * @param string      $rrdfile          Validated absolute path to the RRD file.
	 * @param string      $cf               Consolidation function (e.g. AVERAGE).
	 * @param string      $start            Start time specification.
	 * @param string      $end              End time specification.
	 * @param string|null $extra_options    Optional extra rrdtool flags.
	 * @param bool        $options_rejected Set to true when $extra_options fails validation.
	 *
	 * @throws InvalidArgumentException When $rrdfile fails path validation.
	 *
	 * @return string Shell command ready for popen().
	 */
	public function buildFetchCommand(
		string $rrdtool,
		string $rrdfile,
		string $cf,
		string $start,
		string $end,
		?string $extra_options = null,
		bool &$options_rejected = false
	): string {
		if (!self::isValidPath($rrdfile)) {
			throw new InvalidArgumentException('Invalid RRD file path');
		}

		$args = [
			'fetch',
			$rrdfile,
			$cf,
			'--start',
			$start,
			'--end',
			$end,
		];

		return $this->assemble($rrdtool, $args, $extra_options, $options_rejected);
	}

	/**
	 * Escape the binary, positional arguments, and validated extra options.
	 *
	 * @param string        $rrdtool          Path to the rrdtool binary.
	 * @param array<string> $args             Command arguments.
	 * @param string|null   $extra_options    Optional extra rrdtool flags.
	 * @param bool          $options_rejected Set to true when $extra_options fails validation.
	 *
	 * @return string Shell-escaped command string.
	 */
	private function assemble(string $rrdtool, array $args, ?string $extra_options, bool &$options_rejected): string {
		$escaper          = $this->escaper;
		$options_rejected = false;
		$command          = $escaper($rrdtool);

		foreach ($args as $arg) {
			$command .= ' ' . $escaper($arg);
		}

		if ($extra_options !== '' && $extra_options !== null) {
			if (!self::optionsAreSafe($extra_options)) {
				$options_rejected = true;
			} else {
				foreach (preg_split('/\s+/', $extra_options, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $opt) {
					$command .= ' ' . $escaper($opt);
				}
			}
		}

		return $command;
	}
}
