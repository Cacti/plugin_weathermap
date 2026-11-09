// editor.assets.js loads on its own and exposes its API both as browser globals and CommonJS exports.
const {checkEditorScriptApi} = require('./lib/editorScripts.cjs');
checkEditorScriptApi('editor.assets.js', [
	'wmAssetRequest',
	'wmAssetImages',
	'wmOpenImages',
	'wmAssetColour',
	'wmColourRequest',
	'wmAssetScaleRow',
	'wmColourRefreshMap',
	'wmAssetScaleRows',
	'wmTrafficPresetRows',
	'wmTrafficPresetMatch',
	'wmTrafficPreview',
	'wmOpenColours'
]);
console.log('PASS: editor.assets.js exports and declares exactly its public functions as browser globals');
