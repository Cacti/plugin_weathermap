<?php
/** Validate bundled HTML links and anchors without network access. */
$root = realpath(__DIR__ . '/../..');
$pages = [];
$errors = [];
$previousErrors = libxml_use_internal_errors(true);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/docs', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
	if ($file->getExtension() !== 'html') continue;
	$dom = new DOMDocument();
	$dom->loadHTML(file_get_contents($file->getPathname()));
	$ids = [];
	foreach ((new DOMXPath($dom))->query('//*[@id] | //a[@name]') as $element) {
		foreach (['id', 'name'] as $attribute) {
			if (!$element->hasAttribute($attribute)) continue;
			$id = $element->getAttribute($attribute);
			if (isset($ids[$id]) && $ids[$id] !== $element) $errors[] = $file->getPathname() . ': duplicate anchor ' . $id;
			$ids[$id] = $element;
		}
	}
	$pages[$file->getRealPath()] = ['dom' => $dom, 'ids' => $ids];
}
$count = 0;
foreach ($pages as $path => $page) {
	foreach ((new DOMXPath($page['dom']))->query('//*[@href or @src]') as $element) {
		foreach (['href', 'src'] as $attribute) {
			if (!$element->hasAttribute($attribute)) continue;
			$url = $element->getAttribute($attribute);
			if ($url === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $url)) continue;
			$parts = parse_url($url);
			if ($parts === false) { $errors[] = "$path: malformed URL $url"; continue; }
			$destination = isset($parts['path']) && $parts['path'] !== '' ? dirname($path) . '/' . rawurldecode($parts['path']) : $path;
			if (is_dir($destination)) $destination .= '/index.html';
			$destination = realpath($destination);
			$count++;
			if ($destination === false) { $errors[] = "$path: missing target $url"; continue; }
			if (isset($parts['fragment'], $pages[$destination]) && !isset($pages[$destination]['ids'][rawurldecode($parts['fragment'])])) $errors[] = "$path: missing anchor $url";
		}
	}
}
libxml_clear_errors();
libxml_use_internal_errors($previousErrors);
if ($errors) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}
fwrite(STDOUT, count($pages) . " HTML pages; $count local references checked.\n");
