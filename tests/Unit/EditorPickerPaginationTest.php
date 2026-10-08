<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Howard Jones                    |
 | Licensed under the GNU General Public License, version 2.              |
 +-------------------------------------------------------------------------+
*/

/**
 * Exercise the actual endpoint in an isolated SQL catalog process.
 *
 * @param array $request Request values and graph permissions.
 *
 * @return array Decoded endpoint response.
 */
function editorPickerResponse(array $request): array {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Support/EditorPickerEndpoint.php') . ' ' . escapeshellarg(base64_encode(json_encode($request)));
	$lines = [];
	exec($command, $lines, $status);
	expect($status)->toBe(0);

	return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('The isolated SQL catalog requires pdo_sqlite.');
	}
});

it('PagesBeyondTheFormerCapWithoutLeakingDeniedGraphs', function (string $endpoint): void {
	$first = editorPickerResponse(['endpoint' => $endpoint, 'paged' => '1', 'denied' => [2]]);
	$next = editorPickerResponse(['endpoint' => $endpoint, 'paged' => '1', 'offset' => 100, 'denied' => [2]]);
	expect($first['items'])->toHaveCount(99);
	expect($first['next_offset'])->toBe(100);
	expect($next['items'])->toHaveCount(25);
	expect($next['next_offset'])->toBeNull();
	$ids = array_column(array_merge($first['items'], $next['items']), $endpoint === 'graphs' ? 'id' : 'local_graph_id');
	expect($ids)->not->toContain(2)->toContain(125);
})->with(['graphs', 'datasources']);

it('KeepsLaterPermittedResultsReachableAfterADeniedPage', function (): void {
	$first = editorPickerResponse(['paged' => '1', 'denied' => range(1, 100)]);
	expect($first['items'])->toBe([]);
	expect($first['next_offset'])->toBe(100);
	$next = editorPickerResponse(['paged' => '1', 'offset' => 100, 'denied' => range(1, 100)]);
	expect($next['items'])->toHaveCount(25);
});

it('FindsAliasOnlyMatchesAndRetainsDescriptionInTheLabel', function (): void {
	$result = editorPickerResponse(['endpoint' => 'datasources', 'paged' => '1', 'term' => 'Unique uplink']);
	expect($result['items'])->toHaveCount(1);
	expect($result['items'][0]['label'])->toBe('Interface 115 — Unique uplink');
});

it('RetainsInterfacesWithNoAliasAndFiltersDeniedAliasMatches', function (): void {
	$result = editorPickerResponse(['endpoint' => 'datasources', 'paged' => '1', 'term' => 'Interface 116']);
	expect($result['items'][0]['label'])->toBe('Interface 116');
	$denied = editorPickerResponse(['endpoint' => 'datasources', 'paged' => '1', 'term' => 'Unique uplink', 'denied' => [115]]);
	expect($denied['items'])->toBe([]);
});

it('KeepsLegacyArrayResponsesBounded', function (): void {
	$result = editorPickerResponse([]);
	expect($result)->toHaveCount(100);
	expect($result[0]['id'])->toBe(1);
});

it('LooksUpOnlyTheSelectedPermittedGraphForTheEditorSummary', function (): void {
	$result = editorPickerResponse(['endpoint' => 'datasources', 'paged' => '1', 'local_graph_id' => 115]);
	expect($result['items'])->toHaveCount(1);
	expect($result['items'][0]['local_graph_id'])->toBe(115);
	$denied = editorPickerResponse(['endpoint' => 'datasources', 'paged' => '1', 'local_graph_id' => 115, 'denied' => [115]]);
	expect($denied['items'])->toBe([]);
});

it('FindsSelectedGraphIdsBeyondTheFirstPageWithoutLeakingDeniedGraphs', function (): void {
	$result = editorPickerResponse(['paged' => '1', 'graph_ids' => '115,125', 'denied' => [125]]);
	expect(array_column($result['items'], 'id'))->toBe([115]);
	$invalid = editorPickerResponse(['paged' => '1', 'graph_ids' => 'bad,-1']);
	expect($invalid['items'])->toBe([]);
});
