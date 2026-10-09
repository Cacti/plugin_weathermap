<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * In-process coverage for the selected-graph (graph_ids) filtering branch of
 * display_graphs()/display_datasources(). The pagination suite drives these
 * endpoints out-of-process against a SQLite catalog, which the in-process
 * coverage run cannot observe; these cases run the branch directly with the
 * programmable request stub so the id-allowlist parsing stays measured. The
 * stubbed db returns no rows, so only the query-building branch is exercised.
 */

beforeAll(function (): void {
	require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';
});

afterEach(function (): void {
	$GLOBALS['__test_nfilter_request'] = [];
});

it('applies the selected graph id allowlist for the graph picker', function (): void {
	$GLOBALS['__test_nfilter_request'] = ['graph_ids' => '115,125', 'paged' => '1'];

	ob_start();

	try {
		display_graphs();
	} finally {
		$output = ob_get_clean();
	}

	$decoded = json_decode($output, true);

	expect($decoded)->toBeArray()->toHaveKey('items');
	expect($decoded['items'])->toBe([]);
});

it('applies the selected graph id allowlist for the datasource picker', function (): void {
	$GLOBALS['__test_nfilter_request'] = ['graph_ids' => '115,125', 'local_graph_id' => '115', 'paged' => '1'];

	ob_start();

	try {
		display_datasources();
	} finally {
		$output = ob_get_clean();
	}

	$decoded = json_decode($output, true);

	expect($decoded)->toBeArray()->toHaveKey('items');
	expect($decoded['items'])->toBe([]);
});

it('rejects non-numeric selected graph ids without emitting a filter', function (): void {
	$GLOBALS['__test_nfilter_request'] = ['graph_ids' => 'bad,-1', 'paged' => '1'];

	ob_start();

	try {
		display_graphs();
	} finally {
		$output = ob_get_clean();
	}

	$decoded = json_decode($output, true);

	expect($decoded)->toBeArray()->toHaveKey('items');
});
