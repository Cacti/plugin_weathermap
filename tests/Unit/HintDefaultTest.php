<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Helpers/DataSourceHarness.php';

describe('WeatherMapBase::get_hint() default value', function () {
	/* WeatherMapDataSource_snmp3 calls get_hint($name, $default) to fill in the
	 * SNMPv3 profile fields.  get_hint() took one argument, so PHP discarded the
	 * default and every unset profile field came back null instead. */
	it('returns the supplied default when the hint is absent', function () {
		$base = new WeatherMapBase();

		expect($base->get_hint('snmp3_prof_sec_level', 'authPriv'))->toBe('authPriv');
	});

	it('still returns null when no default is supplied', function () {
		$base = new WeatherMapBase();

		expect($base->get_hint('missing'))->toBeNull();
	});

	it('prefers a set hint over the default', function () {
		$base = new WeatherMapBase();
		$base->add_hint('snmp3_prof_sec_level', 'noAuthNoPriv');

		expect($base->get_hint('snmp3_prof_sec_level', 'authPriv'))->toBe('noAuthNoPriv');
	});

	it('returns a falsy hint rather than falling back to the default', function () {
		$base = new WeatherMapBase();
		$base->add_hint('zero', '0');

		expect($base->get_hint('zero', 'fallback'))->toBe('0');
	});

	it('fills every snmp3 profile default the datasource asks for', function () {
		$base   = new WeatherMapBase();
		$params = [];

		foreach (['sec_level' => 'authPriv', 'auth_proto' => 'MD5', 'priv_proto' => 'DES'] as $key => $default) {
			$params[$key] = $base->get_hint('snmp3_prof_' . $key, $default);
		}

		expect($params)->toBe(['sec_level' => 'authPriv', 'auth_proto' => 'MD5', 'priv_proto' => 'DES']);
	});
});

describe('declared properties', function () {
	/* Writing to an undeclared property is deprecated from PHP 8.2 and becomes
	 * an Error in PHP 9, so the map must declare the ones it writes. */
	it('WeatherMap declares keycache so the scale cache is not a dynamic property', function () {
		expect(property_exists('WeatherMap', 'keycache'))->toBeTrue();
	});

	it('WeatherMapBase declares the name its own add_note and add_hint read', function () {
		expect(property_exists('WeatherMapBase', 'name'))->toBeTrue();
	});
});
