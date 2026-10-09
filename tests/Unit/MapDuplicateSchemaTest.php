<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

it('SavesTheDuplicatePayloadForBothDebugSchemaShapes', function (bool $has_debug): void {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Support/MapDuplicatePayload.php') . ' ' . ($has_debug ? 'present' : 'absent');
	$lines   = [];
	exec($command, $lines, $status);
	expect($status)->toBe(0);
	$saved = json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
	expect($saved['table'])->toBe('weathermap_maps');
	$payload = $saved['payload'];
	expect(array_key_exists('debug', $payload))->toBe($has_debug);

	if ($has_debug) {
		expect($payload['debug'])->toBe('off');
	}
	expect($payload['id'])->toBe(0)
		->and($payload['sortorder'])->toBe(10)
		->and($payload['titlecache'])->toBe('Original Map Copy')
		->and($payload['configfile'])->toBe('Original_copy.conf')
		->and($payload['group_id'])->toBe(2)
		->and($payload['active'])->toBe(1)
		->and($payload['imagefile'])->toBe('')
		->and($payload['htmlfile'])->toBe('')
		->and($payload['warncount'])->toBe(0);
})->with([false, true]);
