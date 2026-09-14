<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Base class for Weathermap tests.
 *
 * Pest's functional tests do not need this directly, but any test that
 * prefers a class-based fixture can `uses(TestCase::class)` to get a clean
 * stub-call log per test.
 */
abstract class TestCase extends PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['__test_db_calls'] = [];
	}
}
