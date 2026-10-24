<?php

it('ValidatesBundledDocumentation', function (string $script) {
	$lines = [];
	$status = 0;
	$path = realpath(__DIR__ . '/../' . $script);
	expect($path)->not->toBeFalse();
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1', $lines, $status);
	expect($status)->toBe(0, implode("\n", $lines));
})->with([
	'links and anchors' => ['bin/check-doc-links.php'],
	'permissions and navigation' => ['Support/DocumentationNavigationRegression.php'],
	'rendered examples and units' => ['bin/render-doc-examples.php'],
]);
