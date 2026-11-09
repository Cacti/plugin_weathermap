// Evaluate whole editor scripts in a jsdom window, in the order the editor page
// includes them, so tests do not depend on where a function sits inside a file.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const EDITOR_SCRIPTS = ['editor.js', 'editor.dialogs.js', 'editor.picker.js', 'editor.compact.js', 'editor.assets.js'];

/**
 * Read one editor script from js/.
 *
 * @param {string} name File name inside js/.
 * @returns {string} Script source.
 */
function editorSource(name) {
	return fs.readFileSync(path.join(__dirname, '../../js', name), 'utf8');
}

/**
 * Evaluate editor scripts in a jsdom window, in the given order.
 *
 * @param {Window} w jsdom window with jQuery installed.
 * @param {string[]} [names] Files inside js/; defaults to the page's include order.
 * @returns {?Function} The real initJS() when editor.js was loaded, otherwise null.
 */
function loadEditorScripts(w, names = EDITOR_SCRIPTS) {
	for (const name of names) w.eval(editorSource(name));
	if (!names.includes('editor.js')) return null;
	// editor.js schedules initJS() for DOM ready. Hand the real one back so each
	// test decides whether page setup runs, against its own stubs.
	const initJS = w.initJS;
	w.initJS = () => {};
	return initJS;
}

/**
 * List the functions a script declares at top level.
 *
 * @param {string} name File name inside js/.
 * @returns {string[]} Function names in source order.
 */
function declaredFunctions(name) {
	return Array.from(editorSource(name).matchAll(/^function ([A-Za-z_$][\w$]*)\(/gm), match => match[1]);
}

/**
 * Check that a script loads on its own and that its CommonJS exports are
 * exactly its top-level functions, each also a browser global after a
 * classic-script evaluation.
 *
 * @param {string} name File name inside js/.
 * @param {string[]} expected Names the script must export.
 * @returns {void}
 */
function checkEditorScriptApi(name, expected) {
	const {JSDOM} = require('jsdom');
	const jquery = require('jquery');
	const exported = require(path.join(__dirname, '../../js', name));
	const declared = declaredFunctions(name);
	assert.deepEqual(Object.keys(exported).sort(), [...expected].sort(), name + ' exports its public API');
	assert.deepEqual([...declared].sort(), [...expected].sort(), name + ' declares no unexported top-level function');
	const w = new JSDOM('', {runScripts: 'outside-only'}).window;
	w.$ = w.jQuery = jquery(w);
	w.eval(editorSource(name));
	for (const key of expected) {
		assert.equal(typeof exported[key], 'function', key + ' is exported as a function');
		assert.equal(typeof w[key], 'function', key + ' is a browser global');
		assert.equal(w[key].toString(), exported[key].toString(), key + ' is the same function in both');
	}
	w.close();
}

module.exports = {EDITOR_SCRIPTS, checkEditorScriptApi, declaredFunctions, editorSource, loadEditorScripts};
