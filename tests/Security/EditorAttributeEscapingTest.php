<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Return attribute values the editor page builds from unescaped PHP values.
 *
 * Flags a quoted attribute followed by string concatenation, or by variable
 * interpolation inside a double-quoted PHP string, unless the value passes
 * through an escaping call.
 *
 * @param string $source The PHP source to scan.
 *
 * @return array<int, string> The offending lines, prefixed with line numbers.
 */
function wm_editor_unescaped_attributes($source) {
	$attribute = '(?:value|data-[a-z-]+|title|alt|placeholder|href|src|id|name|class|label)';
	$escaped   = '(?:html_escape|__esc|plugin_weathermap_escape_attr|htmlspecialchars|wm_editor_attributes)\s*\(';
	$patterns  = [
		// value="' . $var  or  data-x=\'' . $var
		'/\b' . $attribute . '\s*=\s*(?:"\'|\\\\\'\')\s*\.(?!\s*' . $escaped . ')/i',
		// "<option value='$var'"  or  "... value=\"{$var}\""
		'/\b' . $attribute . '\s*=\s*(?:\'|\\\\")\{?\$/i',
	];
	$found = [];

	foreach (explode("\n", $source) as $number => $line) {
		$trimmed = ltrim($line);

		if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
			continue;
		}

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $line)) {
				$found[] = ($number + 1) . ': ' . trim($line);

				break;
			}
		}
	}

	return $found;
}

describe('editor page attribute escaping', function () {
	$editor = __DIR__ . '/../../weathermap-cacti-plugin-editor.php';

	it('does not concatenate unescaped values into HTML attributes', function () use ($editor) {
		expect(wm_editor_unescaped_attributes(file_get_contents($editor)))->toBe([]);
	});

	it('builds dynamic options with wm_editor_option', function () use ($editor) {
		$source = file_get_contents($editor);

		expect(preg_match_all('/\bprint\s+["\']<option\b/', $source))->toBe(0)
			->and(substr_count($source, 'wm_editor_option('))->toBeGreaterThanOrEqual(8);
	});

	it('flags the concatenation forms it is meant to catch', function (string $line) {
		expect(wm_editor_unescaped_attributes($line))->toHaveCount(1);
	})->with([
		"print 'value=\"' . \$im . '\">';",
		"print 'data-imagesrc=\"' . \$im . '\" ';",
		"print 'data-description=\"' . ucfirst(\$display) . '\" ';",
		"print \"<option value='\$id' \";",
		"print \"<option value='{\$id}' \";",
		"print '<option value=\\'' . \$id . '\\'>';",
	]);

	it('accepts escaped values and static markup', function (string $line) {
		expect(wm_editor_unescaped_attributes($line))->toBe([]);
	})->with([
		"print 'value=\"' . html_escape(\$im) . '\">';",
		"print 'data-description=\"' . __esc('Label', 'weathermap') . '\" ';",
		"print 'value=\"' . plugin_weathermap_escape_attr(\$im) . '\">';",
		"print wm_editor_option(\$display, ['value' => \$im]);",
		"<option value='curved'><?php print __('Curved', 'weathermap'); ?></option>",
		"// print 'value=\"' . \$im;",
	]);
});
