// The editor pages include every split script once, in the loader's order, and
// the scripts work together when evaluated in that order.
const {JSDOM} = require('jsdom'), jquery = require('jquery'), fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {EDITOR_SCRIPTS, declaredFunctions, loadEditorScripts} = require('./editorScripts.cjs');
const root = path.join(__dirname, '../..');

const onDisk = fs.readdirSync(path.join(root, 'js')).filter(name => /^editor(\.[a-z]+)?\.js$/.test(name) && !name.startsWith('editor.history'));
assert.deepEqual(onDisk.sort(), [...EDITOR_SCRIPTS].sort(), 'every editor script is in the include order');
for (const page of ['weathermap-cacti-plugin-editor.php', 'lib/editor.inc.php']) {
	const included = Array.from(fs.readFileSync(path.join(root, page), 'utf8').matchAll(/get_md5_include_js\('plugins\/weathermap\/js\/([^']+)'\)/g), match => match[1])
		.filter(name => EDITOR_SCRIPTS.includes(name));
	assert.deepEqual(included, EDITOR_SCRIPTS, page + ' includes each editor script once, in order');
}

const owner = {};
for (const name of EDITOR_SCRIPTS) for (const fn of declaredFunctions(name)) {
	assert.equal(owner[fn], undefined, fn + ' is declared in both ' + owner[fn] + ' and ' + name);
	owner[fn] = name;
}

const dom = new JSDOM('<input id="action" value="pending"><div id="dlgSmoke"></div>', {runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/cacti/plugins/weathermap/weathermap-cacti-plugin-editor.php'});
const w = dom.window, $ = jquery(w);
w.$ = w.jQuery = $;
w.eval(fs.readFileSync(path.join(process.env.CACTI_JS_DIR, 'jquery-ui.js'), 'utf8'));
loadEditorScripts(w);
for (const fn of Object.keys(owner)) assert.equal(typeof w[fn], 'function', fn + ' from ' + owner[fn] + ' is a global');

// editor.js
assert.equal(w.wmEditorReturnUrl('weathermap-cacti-plugin-mgmt.php'), 'weathermap-cacti-plugin-mgmt.php');
// editor.dialogs.js, which calls back into editor.js for the map mode
w.show_dialog('dlgSmoke');
assert.equal($('#dlgSmoke').dialog('isOpen'), true);
assert.ok($('#dlgSmoke').closest('.ui-dialog').hasClass('wm-editor-dialog'));
w.hide_dialog('dlgSmoke');
assert.equal($('#dlgSmoke').dialog('isOpen'), false);
assert.equal($('#action').val(), '');
// editor.picker.js
assert.equal(w.wmEditorImageSrc('images/objects/router.png'), 'https://example.test/cacti/plugins/weathermap/images/objects/router.png');
// editor.compact.js
assert.equal(w.wmLinkCompactHoverUrl('graph_image.php?local_graph_id=5'), '/cacti/graph_image.php?local_graph_id=5&rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300');
// editor.assets.js
assert.equal(w.wmTrafficPresetMatch(w.wmTrafficPresetRows('signals', [])), 'signals');

w.close();
console.log('PASS: editor pages include each split script once in order, without duplicate globals, and each module answers in that order');
