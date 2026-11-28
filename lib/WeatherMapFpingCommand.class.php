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
 * Validates fping targets, builds shell-escaped fping commands, and wraps
 * the popen() call without requiring a Cacti bootstrap.
 */
class WeatherMapFpingCommand {
	/** @var callable(string): string */
	private $escaper;

	/** @var callable(string, string): (resource|false) */
	private $opener;

	/**
	 * @param (callable(string): string)|null                  $escaper Optional shell-argument escaper.
	 * @param (callable(string, string): (resource|false))|null $opener  Optional pipe opener (defaults to popen).
	 */
	public function __construct(?callable $escaper = null, ?callable $opener = null) {
		$this->escaper = $escaper ?? (function_exists('cacti_escapeshellarg') ? 'cacti_escapeshellarg' : 'escapeshellarg');
		$this->opener  = $opener ?? 'popen';
	}

	/**
	 * Validate an fping target before command construction.
	 *
	 * Allows IPv4/IPv6 addresses (including zone IDs with %), hostnames
	 * (including underscores per RFC 2181), and bracketed IPv6 literals.
	 *
	 * @param mixed $target Candidate fping target.
	 *
	 * @return bool True when the target contains only allowed characters.
	 */
	public static function isValidTarget($target) {
		return is_string($target) && preg_match('/^[a-zA-Z0-9._\-:%\[\]]+$/', $target) === 1;
	}

	/**
	 * Build a shell-escaped fping command string.
	 *
	 * @param string $fping_cmd  Path to the fping executable.
	 * @param int    $ping_count Number of ICMP probes.
	 * @param string $target     Validated target host or IP address.
	 *
	 * @throws InvalidArgumentException When $target fails validation.
	 *
	 * @return string Shell command ready for open().
	 */
	public function buildCommand(string $fping_cmd, int $ping_count, string $target): string {
		if (!self::isValidTarget($target)) {
			throw new InvalidArgumentException('Invalid fping target');
		}

		$escaper = $this->escaper;

		return $escaper($fping_cmd)
			. ' -t100 -r1 -p20 -u -C ' . $ping_count
			. ' -i10 -q ' . $escaper($target)
			. ' 2>&1';
	}

	/**
	 * Open a read pipe for a pre-built fping command string.
	 *
	 * @param string $command Pre-built shell command string.
	 *
	 * @return resource|false Pipe resource on success, or false on failure.
	 */
	public function open(string $command) {
		$opener = $this->opener;

		return $opener($command, 'r');
	}
}
