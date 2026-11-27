<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/editor.inc.php';

describe('Weathermap editor action normalization', function (): void {
	afterEach(function (): void {
		$GLOBALS['__test_cacti_log'] = [];
		http_response_code(200);
	});

	it('allows valid editor actions', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/editor.inc.php');
		$entry  = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($source)->toContain('function wm_editor_sanitize_action($action, $valid = [])')
			->and($entry)->toContain("'draw'")
			->and($entry)->toContain("'load_map_javascript'")
			->and(wm_editor_sanitize_action('draw', ['draw', 'nothing']))->toBe('draw')
			->and(wm_editor_sanitize_action('', ['draw', 'nothing']))->toBe('');
	});

	it('normalizes invalid editor actions to empty string at the entrypoint', function (): void {
		$entry = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($entry)->toContain("\$action = wm_editor_sanitize_action(get_nfilter_request_var('action'), [")
			->and($entry)->not->toContain("\$action = get_nfilter_request_var('action');")
			->and($entry)->toContain('if (!wm_editor_action_is_permitted($raw_action, $action))')
			->and(wm_editor_sanitize_action('unknown_action', ['draw', 'nothing']))->toBe('')
			->and(wm_editor_sanitize_action('DRAW', ['draw', 'nothing']))->toBe('')
			->and(wm_editor_sanitize_action(['draw'], ['draw', 'nothing']))->toBe('');
	});

	it('permits empty or allowlisted actions and logs and rejects unknown actions', function (): void {
		$GLOBALS['__test_cacti_log'] = [];

		expect(wm_editor_action_is_permitted('', ''))->toBeTrue()
			->and(wm_editor_action_is_permitted(null, ''))->toBeTrue()
			->and(wm_editor_action_is_permitted('draw', 'draw'))->toBeTrue()
			->and($GLOBALS['__test_cacti_log'])->toBe([]);

		expect(wm_editor_action_is_permitted("bad\r\naction", ''))->toBeFalse()
			->and(http_response_code())->toBe(400)
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('WARNING: Invalid Weathermap editor action rejected: badaction');

		$GLOBALS['__test_cacti_log'] = [];
		expect(wm_editor_action_is_permitted(['unexpected'], ''))->toBeFalse()
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('WARNING: Invalid Weathermap editor action rejected: array');
	});
});
