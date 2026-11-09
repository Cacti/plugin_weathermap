// Evaluate whole editor scripts in a jsdom window, in the order the editor page
// includes them, so tests do not depend on where a function sits inside a file.
const fs = require('node:fs');
const path = require('node:path');

const EDITOR_SCRIPTS = ['editor.js', 'editor.dialogs.js', 'editor.picker.js', 'editor.compact.js', 'editor.assets.js'];

function editorSource(name) {
	return fs.readFileSync(path.join(__dirname, '../../js', name), 'utf8');
}

function loadEditorScripts(w, names = EDITOR_SCRIPTS) {
	for (const name of names) w.eval(editorSource(name));
	if (!names.includes('editor.js')) return null;
	// editor.js schedules initJS() for DOM ready. Hand the real one back so each
	// test decides whether page setup runs, against its own stubs.
	const initJS = w.initJS;
	w.initJS = () => {};
	return initJS;
}

module.exports = {EDITOR_SCRIPTS, editorSource, loadEditorScripts};
