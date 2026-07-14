<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';

describe('format_number', function (): void {
	it('formats decimal values on PHP 8 without a strlen type error', function (): void {
		expect(format_number(12.345, 2))->toBe('12.35');
	});

	it('formats whole numbers without a decimal suffix', function (): void {
		expect(format_number(12.0, 2))->toBe(12);
	});
});
