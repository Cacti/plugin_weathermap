// editor.dialogs.js loads on its own and exposes its API both as browser globals and CommonJS exports.
const {checkEditorScriptApi} = require('./editorScripts.cjs');
checkEditorScriptApi('editor.dialogs.js', [
	'show_dialog',
	'hide_dialog',
	'hide_all_dialogs'
]);
console.log('PASS: editor.dialogs.js exports and declares exactly its public functions as browser globals');
