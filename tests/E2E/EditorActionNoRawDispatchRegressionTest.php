<?php

declare(strict_types=1);

describe('Weathermap editor action dispatch regression', function (): void {
	it('does not leave raw action assignment in the editor entrypoint', function (): void {
		$entry = file_get_contents(dirname(__DIR__, 2) . '/weathermap-cacti-plugin-editor.php');

		expect($entry)->not->toContain("if (isset_request_var('action')) {\n\t\$action = get_nfilter_request_var('action');\n}");
		expect($entry)->toContain("wm_editor_sanitize_action(get_nfilter_request_var('action'), [");
	});
});
