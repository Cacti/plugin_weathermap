<?php
require __DIR__ . '/../bootstrap-unit.php';

function api_user_realm_auth($realm) {
	return $GLOBALS['docs_manage_allowed'];
}

require __DIR__ . '/../../lib/viewer.controls.php';

foreach ([false, true] as $allowed) {
	$GLOBALS['docs_manage_allowed'] = $allowed;
	foreach ([false, ['id' => 7, 'configfile' => 'A&B +é.conf']] as $map) {
		$html = weathermap_map_title_controls(htmlspecialchars('Demo <Map>', ENT_QUOTES), $map);
		$document = new DOMDocument();
		$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		$xpath = new DOMXPath($document);
		$docs = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " wm-docs-link ")]');
		if ($docs->length !== 1 || $docs[0]->getAttribute('href') !== 'docs/index.html' || $docs[0]->getAttribute('target') !== '_blank' || $docs[0]->getAttribute('rel') !== 'noopener') throw new RuntimeException('Missing or unsafe documentation shortcut.');
		$edit = $xpath->query('//a[@class="wm-edit-map"]');
		if ($edit->length !== (int)($allowed && is_array($map))) throw new RuntimeException('Documentation shortcut changed management access.');
		if ($edit->length) {
			parse_str(parse_url($edit[0]->getAttribute('href'), PHP_URL_QUERY), $query);
			if ($query['mapname'] !== $map['configfile']) throw new RuntimeException('Editor filename no longer round-trips.');
		}
	}
}
echo "Documentation access preserves management permissions and navigation.\n";
