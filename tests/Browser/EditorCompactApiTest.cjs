// editor.compact.js loads on its own and exposes its API both as browser globals and CommonJS exports.
const {checkEditorScriptApi} = require('./lib/editorScripts.cjs');
checkEditorScriptApi('editor.compact.js', [
	'wmLinkHoverEntries',
	'wmLinkCompactHoverUrl',
	'wmSyncLinkHoverParts',
	'wmRenderLinkExtraGraphs',
	'wmInitLinkHoverParts',
	'wmSetLinkPrimaryHover',
	'wmPromoteLinkTrafficHover',
	'wmSetLinkExtraHover',
	'wmLinkTrafficIdentity',
	'wmLinkSameTrafficSource',
	'wmRenderLinkTrafficSources',
	'wmApplyLinkTrafficSource',
	'wmInitLinkSourcePicker',
	'wmRestoreLinkGraphChoice',
	'wmBuildLinkSections',
	'compactLinkEditorBase',
	'wmGetInterfaceSummary',
	'wmGetInterfaceCandidates',
	'compactLinkEditor',
	'compactNodeEditor',
	'refineNodePicker',
	'wmLocalGraphIds',
	'wmNodeHoverSummary',
	'wmGetGraphSummaries',
	'wmNodeDisplayName',
	'wmFriendlyLinkNames'
]);
console.log('PASS: editor.compact.js exports and declares exactly its public functions as browser globals');
