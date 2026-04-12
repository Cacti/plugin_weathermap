<?php

declare(strict_types=1);

describe('Weathermap editor action wiring', function (): void {
	it('uses the normalized action before the switch dispatch', function (): void {
		$entry = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($entry)->toContain("switch(\$action) {");
		expect($entry)->toContain("\$action = wm_editor_sanitize_action(get_nfilter_request_var('action'), [");
	});
});
