<?php

it('KeepsBundledDocumentationTargetsAndAnchorsResolvable', function () {
	$lines = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/check-doc-links.php') . ' 2>&1', $lines, $status);
	expect($status)->toBe(0, implode("\n", $lines));
});

it('ExposesHelpWithoutGrantingManagementAccess', function () {
	$lines = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../Support/DocumentationNavigationRegression.php') . ' 2>&1', $lines, $status);
	expect($status)->toBe(0, implode("\n", $lines));
});

it('RendersPublishedConfigurationExamplesWithCorrectUnits', function () {
	$lines = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/render-doc-examples.php') . ' 2>&1', $lines, $status);
	expect($status)->toBe(0, implode("\n", $lines));
});
