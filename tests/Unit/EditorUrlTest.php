<?php

beforeAll(function (): void {
	require_once __DIR__ . '/../../lib/editor.inc.php';
});

it('preserves query parameters when an editor click URL is saved', function (): void {
	$url = '/cacti/graph.php?rra_id=all&local_graph_id=42';

	expect(wm_editor_sanitize_url($url))->toBe($url);
	expect(wm_editor_sanitize_url(wm_editor_sanitize_url($url)))->toBe($url);
});

it('retains attribute escaping and prevents additional map directives', function (): void {
	$url = "/graph?a=1&title=\"test\"\nINFOURL /other";
	$stored = wm_editor_sanitize_url($url);

	expect($stored)->not->toContain('"');
	expect($stored)->not->toContain("\n");
	expect($stored)->toContain('&title=&quot;test&quot;');
});
