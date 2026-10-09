<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Exercise getEditorJs() in-process so the translated editor string table is
 * covered. The function only emits a <script> block, so the output is captured
 * and inspected rather than printed. plugin_weathermap_csp_nonce() lives in
 * setup.php; requiring it keeps the nonce attribute real.
 */

beforeAll(function (): void {
	require_once dirname(__DIR__, 2) . '/setup.php';
	require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';
});

it('emits the translated editor javascript string table', function (): void {
	ob_start();

	try {
		getEditorJs();
	} finally {
		$output = ob_get_clean();
	}

	expect($output)->toContain('var wmEditorText =');
	expect($output)->toContain('var delNodePrompt =');
	expect($output)->toContain('currentInterface');
	expect($output)->toContain('deleteLinkPrompt');
});
