const { JSDOM } = require('jsdom'),
	jquery = require('jquery'),
	fs = require('fs'),
	vm = require('vm'),
	assert = require('node:assert/strict');
const row = (id) => `<tr><td>${id}</td><td><input id="${id}"></td></tr>`;
const dom = new JSDOM(
	`<input id="mapname" value="fixture.conf"><input id="link_name" value="a-b"><div id="dlgLinkProperties"><p><span id="link_nodename1"></span><span id="link_nodename2"></span></p><table>${['viastyle', 'link_target', 'link_infourl', 'link_hover', 'link_template', 'link_picker', 'link_bandwidth_in', 'link_bandwidth_out', 'link_width', 'link_commentin', 'link_commentout'].map(row).join('')}<tr><td>Picker</td><td><input id="link_target_picker"><span id="link_target_picker_wrap"><input id="link_target_picker_input"><button id="link_target_picker_click"></button></span><button id="link_target_picker_add"></button><button id="link_target_picker_rep"></button></td></tr></table><div class="dlgButtons"><div class="dlgSubButtons">${['link_delete', 'link_tidy', 'link_via', 'link_edit', 'tb_link_cancel', 'tb_link_submit'].map((id) => `<a id="${id}">${id}</a>`).join('')}</div></div><div class="dlgHelp">Help</div></div>`,
	{ url: 'https://example.test/cacti/plugins/weathermap/editor.php', runScripts: 'outside-only' }
);
const w = dom.window,
	$ = jquery(w);
w.$ = $;
const phpStrings = fs.readFileSync(require('node:path').join(__dirname, '../../lib/editor.inc.php'), 'utf8');
w.wmEditorText = Object.fromEntries(Array.from(phpStrings.matchAll(/'([a-zA-Z]+)'\s*=> __\('([^']*)', (?:'%s', )?'weathermap'\)/g), match => [match[1], match[2]]));
w.infoUrlTarget = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';
w.Nodes = { a: { label: 'Switch A' }, b: { label: 'Router B' } };
w.Links = { 'a-b': { a: 'a', b: 'b', name: 'a-b' } };
$.fn.autocomplete = function (method, key, value) {
	const o = this.data('options') || {};
	if (method === 'option') {
		if (arguments.length === 2) return o[key];
		o[key] = value;
		this.data('options', o);
	} else if (method === 'search') this.data('lastSearch', key);
	return this;
};
let request = $.Deferred(),
	requests = 0;
$.getJSON = () => {
	requests++;
	return request.promise();
};
const editor = fs.readFileSync(require('node:path').join(__dirname, '../../js/editor.js'), 'utf8');
const originalSource = (request, response) => response(items.filter(item => item.label.toLowerCase().includes(request.term.toLowerCase())));
$('#link_target_picker_input').data('options', {source: originalSource, select: (event, ui) => ui.item.wmOffset !== undefined ? false : undefined});
vm.runInContext(editor.slice(editor.indexOf('// Compact link workflow;')), dom.getInternalVMContext());
$('#link_target').val('/rrd/interface.rrd');
$('#link_infourl').val('/cacti/graph.php?rra_id=all&local_graph_id=10');
w.compactLinkEditor();
w.wmFriendlyLinkNames();
const items = [
	{ id: '/rrd/interface.rrd', local_graph_id: 10, label: 'Switch A — Gi0/1 — Uplink' },
	{ id: '/rrd/other.rrd', local_graph_id: 20, label: 'Switch A — Gi0/2 — Backup' }
];
request.resolve({items});
assert.equal($('#link_target_picker_input').val(), items[0].label);
assert.equal($('#wm-link-current').text(), items[0].label);
assert.equal($('#link_target').closest('tr').css('display'), 'none');
assert.notEqual($('#link_width').closest('tr').css('display'), 'none');
assert.notEqual($('#link_commentin').closest('tr').css('display'), 'none');
$('#wm-link-toggle').trigger('click');
assert.notEqual($('#link_target').closest('tr').css('display'), 'none');
assert.equal($('#wm-link-internal-id').text(), 'Internal link ID: a-b');
let results;
$('#link_target_picker_input').autocomplete('option', 'source')({ term: 'backup' }, (v) => (results = v));
assert.deepEqual(
	results.map((x) => x.id),
	[items[1].id]
);
$('#link_target_picker_input')
	.autocomplete('option', 'select')
	.call($('#link_target_picker_input')[0], {}, { item: items[1] });
$('#link_target_picker_input').val(items[1].label).trigger('click');
assert.equal($('#link_target_picker_input').data('lastSearch'), items[1].label);
assert.equal($('#link_target').val(), items[0].id);
$('#wm-link-use').trigger('click');
assert.equal($('#link_target').val(), items[1].id);
assert.equal($('#link_infourl').val(), '/cacti/graph_view.php?action=preview&reset=true&style=selective&graph_list=20');
w.infoUrlTarget = 'graph.php?rra_id=all&local_graph_id=';
$('#link_target_picker').data('wm-choice', items[1]);
$('#wm-link-use').trigger('click');
assert.equal($('#link_infourl').val(), '/cacti/graph.php?rra_id=all&local_graph_id=20');
assert.equal(w.wmDecodeEditorUrl('/graph?a=1&amp;b=2'), '/graph?a=1&b=2');
w.compactLinkEditor();
assert.equal($('#link_target_picker_input').val(), items[1].label);
assert.equal(requests, 2);
w.wmInterfaceSummaries = {};
request = $.Deferred();
w.compactLinkEditor();
request.reject();
request = $.Deferred();
w.compactLinkEditor();
request.resolve({items});
assert.equal($('#link_target_picker_input').autocomplete('option', 'source'), originalSource);
assert.equal($('#link_target_picker_input').autocomplete('option', 'select').call($('#link_target_picker_input')[0], {}, {item: {wmOffset: 100}}), false);
assert.equal($('#wm-link-use').prop('disabled'), true);
w.wmInterfaceSummaries = {};
request = $.Deferred();
w.compactLinkEditor();
$('#link_target').val('/rrd/newer.rrd');
$('#wm-link-current').text('Newer link');
request.reject();
assert.equal($('#wm-link-current').text(), 'Newer link');
// External click destinations still resolve the interface from Cacti hover URLs.
w.wmInterfaceSummaries = {};
$('#link_target').val('/rrd/interface.rrd');
$('#link_infourl').val('https://external.test/device');
$('#link_hover').val('/cacti/graph_image.php?local_graph_id=10');
request = $.Deferred();
w.compactLinkEditor();
request.resolve({items: [items[0]]});
assert.equal($('#wm-link-current').text(), items[0].label);
// A mismatched click graph cannot override the matching hover graph.
w.wmInterfaceSummaries = {};
$('#link_infourl').val('/cacti/graph.php?local_graph_id=20');
$('#link_hover').val('/cacti/graph_image.php?local_graph_id=10 /cacti/graph_image.php?local_graph_id=10');
const interfaceRequests = [];
$.getJSON = (url, data) => {
 const deferred = $.Deferred();
 interfaceRequests.push({data, deferred});
 return deferred.promise();
};
w.compactLinkEditor();
assert.equal(interfaceRequests.length, 1);
assert.equal(interfaceRequests[0].data.graph_ids, '20,10');
interfaceRequests[0].deferred.resolve({items: [items[1], items[0]]});
assert.equal($('#wm-link-current').text(), items[0].label);
// Large selections are bounded and sequential; a failed chunk can be retried.
w.wmInterfaceSummaries = {};
interfaceRequests.length = 0;
let interfaceResult;
w.wmGetInterfaceCandidates(Array.from({length: 125}, (_, i) => i + 1)).done(data => {interfaceResult = data;});
assert.equal(interfaceRequests.length, 1);
assert.equal(interfaceRequests[0].data.graph_ids.split(',').length, 100);
interfaceRequests[0].deferred.reject();
assert.equal(interfaceRequests.length, 2);
assert.equal(interfaceRequests[1].data.graph_ids.split(',').length, 25);
interfaceRequests[1].deferred.resolve({items: [{id: 'interface_115.rrd', local_graph_id: 115}]});
assert.equal(interfaceResult.items[0].local_graph_id, 115);
interfaceRequests.length = 0;
w.wmGetInterfaceCandidates(Array.from({length: 125}, (_, i) => i + 1));
assert.equal(interfaceRequests.length, 1);
interfaceRequests[0].deferred.resolve({items: []});
assert.equal(interfaceRequests.length, 1); // The successful second batch is cached.
const graphRequests = [];
$.getJSON = (url, data) => {
 const deferred = $.Deferred();
 graphRequests.push({data, deferred});
 return deferred.promise();
};
let graphLabels;
w.wmGetGraphSummaries(Array.from({length: 125}, (_, i) => String(i + 1))).done(data => { graphLabels = data; });
assert.equal(graphRequests.length, 1);
graphRequests[0].deferred.resolve({items: [{id: 1, label: 'First graph'}]});
assert.equal(graphRequests.length, 2);
assert.equal(graphRequests[0].data.graph_ids.split(',').length, 100);
assert.equal(graphRequests[1].data.graph_ids, Array.from({length: 25}, (_, i) => String(i + 101)).join(','));
graphRequests[1].deferred.resolve({items: [{id: 115, label: 'Later graph'}]});
assert.equal(graphLabels.some(item => item.id === 115), true);
$.fn.dialog = function() { return this; };
w.mapmode = () => {};
w.delNodePrompt = 'Translated remove %s?';
w.delNodeConnected = 'Translated connected count: %s';
w.delNodeKeepDevice = 'Translated keep device';
w.delNodeTitle = 'Translated title';
w.txtCancel = 'Cancel';
w.txtDelNode = 'Delete';
w.delLinkTitle = 'Translated link title';
w.txtDelLink = 'Delete link';
$('body').append('<input id="node_name" value="a">');
const wholeEditor = editor.slice(0, editor.indexOf('// Compact link workflow;'));
vm.runInContext(wholeEditor, dom.getInternalVMContext());
w.initJS = () => {};
w.mapmode = () => {};
w.Nodes = {a: {label: '<Named node>'}};
for (const count of [0, 1, 2, 5, 11]) {
 w.Links = Object.fromEntries(Array.from({length: count}, (_, i) => [String(i), {a: 'a', b: 'b'}]));
 w.delete_node();
 const message = $('.dlgConfirm').text();
 assert.equal(message.includes('Translated remove <Named node>?'), true);
 assert.equal(message.includes('Translated keep device'), true);
 assert.equal(message.includes('Translated connected count: ' + count), count > 0);
 assert.equal($('.dlgConfirm').find('named').length, 0);
}
w.Nodes.b = {label: '<Other node>'};
w.Links = {'a-b': {a: 'a', b: 'b'}};
w.wmEditorText.deleteLinkPrompt = 'Translated %2$s then %1$s';
w.wmEditorText.keepLinkNodes = 'Translated keep nodes';
w.delete_link();
assert.equal($('.dlgConfirm').text(), 'Translated <Other node> then <Named node> Translated keep nodes');
assert.equal($('.dlgConfirm').children().length, 0);
w.wmEditorText.advanced = 'Translated advanced';
w.wmEditorText.currentInterface = 'Translated current';
w.wmEditorText.browseAll = 'Translated browse';
$('#wm-link-summary,#wm-link-toggle,#wm-link-browse').remove();
w.compactLinkEditor();
assert.equal($('#wm-link-summary td:first').text(), 'Translated current');
assert.equal($('#wm-link-toggle').text(), 'Translated advanced');
assert.equal($('#wm-link-browse').text(), 'Translated browse');
$('body').append('<div id="dlgNodeProperties"><table>' + ['node_label','node_iconfilename','node_template','node_picker','node_new_name','node_infourl','node_hover'].map(row).join('') + '</table><a id="node_delete"></a><div class="dlgButtons"><div class="dlgSubButtons"></div></div><div class="dlgHelp"></div></div><input id="node_picker_input"><button id="node_picker_add"></button><button id="node_picker_rep"></button>');
w.wmEditorText.displayName = 'Translated display';
w.wmEditorText.nodeDisplayHelp = '<Translated safe help>';
w.compactNodeEditor();
w.refineNodePicker();
assert.equal($('#node_label').closest('tr').find('td:first').text(), 'Translated display');
assert.equal($('#wm-node-toggle').text(), 'Translated advanced');
assert.equal($('#wm-node-browse').text(), 'Translated browse');
assert.equal($('#dlgNodeProperties .dlgHelp p:first').text(), '<Translated safe help>');
assert.equal($('#dlgNodeProperties .dlgHelp p:first').children().length, 0);
// Summaries retain every configured entry without exposing denied graph names.
const mixedHover = '/cacti/graph_image.php?local_graph_id=115 https://external.test/image.png /cacti/graph_image.php?local_graph_id=125';
assert.equal(w.wmNodeHoverSummary(mixedHover, [{id:115, label:'Permitted graph'}]), 'Hover graphs: Permitted graph; custom images; unavailable graph');
assert.equal(w.wmNodeHoverSummary('   ', []), 'No hover graphs configured');
assert.equal(w.wmNodeHoverSummary('https://external.test/image.png', []), 'Hover graphs: custom images');
assert.equal(w.wmNodeHoverSummary('/cacti/graph_image.php?local_graph_id=125', []), 'Hover graphs: unavailable graph');
$('#node_hover').val(mixedHover);
graphRequests.length = 0;
w.refineNodePicker();
graphRequests[0].deferred.resolve({items: [{id:115, label:'Permitted $& graph'}]});
assert.equal($('#wm-node-graphs').text(), 'Hover graphs: Permitted $& graph; custom images; unavailable graph');
// Positional placeholders in names are literal and translations can repeat/reorder tokens.
w.Nodes.a = {label: '%2$s'};
w.Nodes.b = {label: '%1$s'};
w.wmEditorText.deleteLinkPrompt = '%1$s / %2$s / %1$s';
w.delete_link();
assert.equal($('.dlgConfirm').text(), '%2$s / %1$s / %2$s Translated keep nodes');
assert.deepEqual(Array.from(w.wmLocalGraphIds('graph_image.php?local_graph_id=115', ['graph_image.php'])), ['115']);
assert.deepEqual(Array.from(w.wmLocalGraphIds('../../graph_image.php?local_graph_id=115', ['graph_image.php'])), ['115']);
assert.deepEqual(Array.from(w.wmLocalGraphIds('/cacti/graph_view.php?graph_list=10,20', ['graph_view.php'])), ['10','20']);
for (const url of ['https://external.test/cacti/graph_image.php?local_graph_id=115', '/other/graph_image.php?local_graph_id=115', '/cacti/unrelated.php?local_graph_id=115', 'http://[invalid']) {
 assert.deepEqual(Array.from(w.wmLocalGraphIds(url, ['graph_image.php'])), []);
 assert.equal(w.wmNodeHoverSummary(url, [{id:115, label:'Local graph'}]), 'Hover graphs: custom images');
}
graphRequests.length = 0;
$('#node_hover').val('/cacti/graph_image.php?local_graph_id=115 https://external.test/cacti/graph_image.php?local_graph_id=125');
w.refineNodePicker();
assert.equal(graphRequests[0].data.graph_ids, '115');
graphRequests[0].deferred.resolve({items:[{id:115,label:'Local graph'}]});
assert.equal($('#wm-node-graphs').text(), 'Hover graphs: Local graph; custom images');
// Reopening the dialog clears both visible and hidden selections and legacy actions.
$('#link_target_picker').val('/rrd/previous.rrd').data('wm-choice', items[1]);
w.local_graph_id = 20;
w.compactLinkEditorBase();
assert.equal($('#link_target_picker').val(), '');
assert.equal(w.local_graph_id, 0);
assert.equal($('#link_target_picker_add').prop('disabled'), true);
assert.equal($('#link_target_picker_rep').prop('disabled'), true);
$('#link_target_picker_input').autocomplete('option', 'select').call($('#link_target_picker_input')[0], {}, {item:items[0]});
assert.equal($('#link_target_picker').val(), items[0].id);
assert.equal(w.local_graph_id, 10);
assert.equal($('#link_target_picker_add').prop('disabled'), false);
assert.equal($('#link_target_picker_rep').prop('disabled'), false);
$('#link_target_picker_input').trigger('input');
assert.equal($('#link_target_picker').val(), '');
assert.equal(w.local_graph_id, 0);
assert.equal($('#link_target_picker_add').prop('disabled'), true);
assert.equal($('#link_target_picker_rep').prop('disabled'), true);
// An exact full path wins even if a same-basename candidate arrives first.
w.wmInterfaceSummaries = {};
$('#link_target').val('/rrd/expected/interface.rrd');
$('#link_infourl').val('/cacti/graph.php?local_graph_id=10');
$('#link_hover').val('');
graphRequests.length = 0;
w.compactLinkEditor();
graphRequests[0].deferred.resolve({items:[{id:'/rrd/other/interface.rrd',label:'Wrong interface'},{id:'/rrd/expected/interface.rrd',label:'Exact interface'}]});
assert.equal($('#wm-link-current').text(), 'Exact interface');
w.wmInterfaceSummaries = {};
$('#link_target').val('/rrd/unknown/interface.rrd');
graphRequests.length = 0;
w.compactLinkEditor();
graphRequests[0].deferred.resolve({items:[{id:'/rrd/other/interface.rrd',label:'Wrong interface'},{id:'/rrd/expected/interface.rrd',label:'Other interface'}]});
assert.equal($('#wm-link-current').text(), 'Custom or unavailable interface');
// Same RRD targets still need generation guards when the selected graphs differ.
for (const failOld of [false, true]) {
 w.wmInterfaceSummaries = {};
 $('#link_target').val('/rrd/shared.rrd');
 $('#link_hover').val('');
 $('#link_infourl').val('/cacti/graph.php?local_graph_id=10');
 graphRequests.length = 0;
 w.compactLinkEditor();
 $('#link_infourl').val('/cacti/graph.php?local_graph_id=20');
 w.compactLinkEditor();
 graphRequests[1].deferred.resolve({items:[{id:'/rrd/shared.rrd',label:'Newer shared interface'}]});
 if (failOld) graphRequests[0].deferred.reject();
 else graphRequests[0].deferred.resolve({items:[{id:'/rrd/shared.rrd',label:'Older shared interface'}]});
 assert.equal($('#wm-link-current').text(), 'Newer shared interface');
}
for (const failOld of [false, true]) {
 w.wmInterfaceSummaries = {};
 $('#link_infourl').val('/cacti/graph.php?local_graph_id=10');
 graphRequests.length = 0;
 w.compactLinkEditor();
 $('#link_infourl').val('https://external.test/device');
 w.compactLinkEditor(); // No graph IDs must invalidate the pending lookup too.
 if (failOld) graphRequests[0].deferred.reject();
 else graphRequests[0].deferred.resolve({items:[{id:'/rrd/shared.rrd',label:'Older shared interface'}]});
 assert.equal($('#wm-link-current').text(), 'Custom or unavailable interface');
}
// Use interface is authoritative even when a pending graph lookup shares its RRD.
w.infoUrlTarget = 'graph.php?rra_id=all&local_graph_id=';
for (const failOld of [false, true]) {
 w.wmInterfaceSummaries = {};
 $('#link_target').val('/rrd/shared.rrd');
 $('#link_infourl').val('/cacti/graph.php?local_graph_id=10');
 $('#link_hover').val('');
 graphRequests.length = 0;
 w.compactLinkEditor();
 const selected = {id:'/rrd/shared.rrd',local_graph_id:20,label:'Explicitly chosen interface'};
 $('#link_target_picker_input').autocomplete('option', 'select').call($('#link_target_picker_input')[0], {}, {item:selected});
 $('#wm-link-use').trigger('click');
 if (failOld) graphRequests[0].deferred.reject();
 else graphRequests[0].deferred.resolve({items:[{id:'/rrd/shared.rrd',label:'Older graph interface'}]});
 assert.equal($('#wm-link-current').text(), 'Explicitly chosen interface');
 assert.equal($('#link_target').val(), '/rrd/shared.rrd');
 assert.equal($('#link_infourl').val().includes('local_graph_id=20'), true);
}
console.log(
	'PASS: current interface, retained selection, explicit apply, advanced fields, action names, URL decoding and failed-request retry'
);
