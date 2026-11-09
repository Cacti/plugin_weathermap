// editor.picker.js loads on its own and exposes its API both as browser globals and CommonJS exports.
const {checkEditorScriptApi} = require('./editorScripts.cjs');
checkEditorScriptApi('editor.picker.js', [
	'graphPicker',
	'wmEditorImageSrc',
	'wmNodeIconPicker'
]);
console.log('PASS: editor.picker.js exports and declares exactly its public functions as browser globals');
