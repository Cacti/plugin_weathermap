<?php

declare(strict_types = 1);

describe('Weathermap editor action normalization', function (): void {
	it('allows valid editor actions', function (): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/lib/editor.inc.php');
		$entry  = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($source)->toContain('function wm_editor_sanitize_action($action, $valid = [])');
		expect($entry)->toContain("'draw'");
		expect($entry)->toContain("'load_map_javascript'");
	});

	it('normalizes invalid editor actions to empty string at the entrypoint', function (): void {
		$entry = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($entry)->toContain("\$action = wm_editor_sanitize_action(get_nfilter_request_var('action'), [");
		expect($entry)->not->toContain("\$action = get_nfilter_request_var('action');");
	});
});
