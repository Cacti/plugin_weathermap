<?php
/**
 * Build the same escaped map heading and authorized shortcuts in each view.
 *
 * @param string $maptitle HTML-escaped heading.
 * @param array<string, mixed>|bool $map Map metadata, or false when unavailable.
 *
 * @return string The map heading with management shortcuts when authorized.
 */
function weathermap_map_title_controls($maptitle, $map) {
	$heading = '<a class="wm-docs-link linkOverDark" href="docs/index.html" target="_blank" rel="noopener">[' . __esc('Docs', 'weathermap') . ']</a><strong>' . $maptitle . '</strong>';

	if (!is_array($map) || !isset($map['id'], $map['configfile']) || !is_string($map['configfile']) || !api_user_realm_auth('weathermap-cacti-plugin-mgmt.php')) {
		return $heading;
	}
	$id = (int) $map['id'];
	$heading .= ' [ <a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php">' . __esc('Manage Weathermaps', 'weathermap') . '</a> | ';
	$heading .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=map_settings&id=' . $id . '">' . __esc('Map Settings', 'weathermap') . '</a> | ';
	$heading .= '<a class="pic linkOverDark" href="weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=' . $id . '">' . __esc('Map Permissions', 'weathermap') . '</a> | ';
	$heading .= '<a class="wm-edit-map" href="' . html_escape('weathermap-cacti-plugin-editor.php?action=nothing&mapname=' . rawurlencode($map['configfile'])) . '">' . __esc('Edit Map', 'weathermap') . '</a> ]';

	return $heading;
}
