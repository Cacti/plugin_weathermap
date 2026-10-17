const { JSDOM } = require('jsdom'),
	jquery = require('jquery'),
	fs = require('fs'),
	vm = require('vm'),
	assert = require('node:assert/strict');
const row = (id) => `<tr><td>${id}</td><td>${id.includes("hover") ? `<textarea id="${id}"></textarea>` : `<input id="${id}">`}</td></tr>`;
const dom = new JSDOM(
	`<input id="mapname" value="fixture.conf"><input id="link_name" value="a-b"><div id="dlgLinkProperties"><p><span id="link_nodename1"></span><span id="link_nodename2"></span></p><table>${['viastyle', 'link_target', 'link_infourl', 'link_hover', 'wm-link-primary-hover', 'wm-link-extra-hover', 'link_template', 'link_picker', 'link_bandwidth_in', 'link_bandwidth_out', 'link_width', 'link_commentin', 'link_commentout'].map(row).join('')}<tr><td>Picker</td><td><input id="link_target_picker"><span id="link_target_picker_wrap"><input id="link_target_picker_input"><button id="link_target_picker_click"></button></span><button id="link_target_picker_add"></button><button id="link_target_picker_rep"></button></td></tr></table><div class="dlgButtons"><div class="dlgSubButtons">${['link_delete', 'link_tidy', 'link_via', 'link_edit', 'tb_link_cancel', 'tb_link_submit'].map((id) => `<a id="${id}">${id}</a>`).join('')}</div></div><div class="dlgHelp">Help</div></div>`,
	{ url: 'https://example.test/cacti/plugins/weathermap/editor.php', runScripts: 'outside-only' }
);
const w = dom.window,
	$ = jquery(w);
w.$ = $;
$.fn.dialog = function(method) { return method === 'instance' ? null : this; };
const phpStrings = fs.readFileSync(require('node:path').join(__dirname, '../../lib/editor.inc.php'), 'utf8');
w.wmEditorText = Object.fromEntries(Array.from(phpStrings.matchAll(/'([a-zA-Z]+)'\s*=> __\('([^']*)', (?:'%s', )?'weathermap'\)/g), match => [match[1], match[2]]));
w.infoUrlTarget = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';
w.Nodes = { a: { label: 'Switch A' }, b: { label: 'Router B' } };
w.Links = { 'a-b': { a: 'a', b: 'b', name: 'a-b' } };
$.fn.autocomplete = function (method, key, value) {
	if (typeof method === 'object') { this.data('options',method); return this; }
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
$('#link_target_picker_input').attr('aria-describedby', 'existing-description');
$('#dlgLinkProperties table').append('<tr class="wm-link-section"><td colspan="2">Additional hover graphs</td></tr>');
w.compactLinkEditor();
assert.notEqual($('#wm-link-sections').css('display'), 'none');
assert.equal($('#wm-link-sections .wm-link-section-toggle').length, 2);
assert.equal($('#wm-link-sections .wm-link-group:not([hidden])').length, 0);
assert.equal($('#dlgLinkProperties .dlgHelp').closest('details,#wm-link-sections').length,0);
assert.equal($('#dlgLinkProperties .dlgHelp').css('display'),'none');
assert.equal($('#wm-link-appearance').length,0);
assert.equal($('#link_width').closest('.wm-link-group').length,0);
$('#wm-link-help-button').trigger('click');
assert.notEqual($('#wm-link-full-help').css('display'),'none');
assert.equal($('#wm-link-help-button').attr('aria-expanded'),'true');
$('#wm-link-help-button').trigger('click');
assert.equal($('#wm-link-full-help').css('display'),'none');
assert.equal($('#link_target_picker_add').closest('.wm-link-group').attr('id'), 'wm-link-advanced');
assert.equal($('#link_target_picker_rep').length, 0);
assert.equal($('#wm-link-source-picker').length, 1);
assert.equal($('#link_target').closest('.wm-link-group').attr('id'), 'wm-link-advanced');
assert.equal($('#link_infourl').closest('.wm-link-group').attr('id'), 'wm-link-advanced');
assert.equal($('#link_target').prop('disabled'), false);
assert.equal($('#link_infourl').prop('disabled'), false);
assert.equal($('#link_edit').css('display') === 'none', false);
assert.equal($('#wm-link-graph').closest('#wm-link-advanced').length,1);
assert.equal($('#wm-link-summary #wm-link-graph').length,0);
assert.equal($('#wm-link-rrd').text(),'interface.rrd');
assert.equal($('#wm-link-advanced-button').next().attr('id'),'link_edit');
assert.equal($('#link_edit').next().attr('id'),'wm-link-help-button');
assert.equal($('#dlgLinkProperties .dlgSubButtons #link_edit,#dlgLinkProperties .dlgSubButtons #wm-link-help-button').length,0);
assert.equal($('#link_picker').closest('.wm-link-group').attr('id'), 'wm-link-hovers');
assert.equal($('#link_commentin').closest('.wm-link-group').length, 0);
assert.equal($('#link_target_picker').closest('tr').next().find('#viastyle').length, 1);
assert.equal($('#link_commentin').closest('table')[0], $('#link_target_picker').closest('table')[0]);
assert.equal($('#link_target_picker').closest('tr').find('td:first').text(), 'Traffic interface');
assert.equal($('#wm-link-apply-hint').text(), 'Choose a primary interface, then click "Use interface". Additional sources and hover graphs are kept. Save applies changes.');
assert.equal($('#link_target_picker_input').attr('aria-describedby'), 'existing-description wm-link-apply-hint');
w.compactLinkEditor();
assert.equal($('#link_target_picker_input').attr('aria-describedby'), 'existing-description wm-link-apply-hint');
w.wmFriendlyLinkNames();
const items = [
	{ id: '/rrd/interface.rrd', local_graph_id: 10, label: 'Switch A — Gi0/1 — Uplink' },
	{ id: '/rrd/other.rrd', local_graph_id: 20, label: 'Switch A — Gi0/2 — Backup' }
];
request.resolve({items});
assert.equal($('#link_target_picker_input').val(), items[0].label);
assert.equal($('#wm-link-current').text(), items[0].label);
assert.equal($('#link_target').closest('.wm-link-group').attr('id'), 'wm-link-advanced');
assert.notEqual($('#link_width').closest('tr').css('display'), 'none');
assert.notEqual($('#link_commentin').closest('tr').css('display'), 'none');
$('#wm-link-hovers-button').trigger('click');
assert.equal($('#wm-link-hovers').prop('hidden'),false);
assert.equal($('#wm-link-hovers-button').attr('aria-expanded'),'true');
assert.equal($('#wm-link-advanced').prop('hidden'),true);
$('#wm-link-advanced-button').trigger('click');
assert.equal($('#wm-link-advanced').prop('hidden'),false);
assert.equal($('#wm-link-hovers').prop('hidden'),true);
assert.equal($('#wm-link-hovers-button').attr('aria-expanded'),'false');
$('#wm-link-hovers-button').trigger('click');
assert.equal($('#wm-link-hovers').prop('hidden'),false);
assert.equal($('#wm-link-hovers-button').attr('aria-expanded'),'true');
assert.equal($('#wm-link-advanced').prop('hidden'),true);
assert.equal($('#wm-link-advanced-button').attr('aria-expanded'),'false');
$('#wm-link-hovers-button').trigger('click');
assert.equal($('#wm-link-hovers').prop('hidden'),true);
assert.equal($('#wm-link-advanced').prop('hidden'),true);
assert.equal($('#wm-link-internal-id').text(), 'Internal link ID: a-b');
assert.equal($('#wm-link-internal-id').closest('.wm-link-group').attr('id'), 'wm-link-advanced');
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
assert.equal($('#link_target_picker_input').data('lastSearch'), '');
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
assert.equal($('#wm-link-toggle').length,0);
assert.equal($('#wm-link-browse').text(), 'Translated browse');
$('body').append('<div id="dlgNodeProperties"><table>' + ['node_label','node_iconfilename','node_template','node_picker','node_new_name','node_infourl','node_hover'].map(row).join('') + '</table><a id="node_delete"></a><div class="dlgButtons"><div class="dlgSubButtons"></div></div><div class="dlgHelp"></div></div><input id="node_picker_input"><button id="node_picker_add"></button><button id="node_picker_rep"></button>');
$('#node_label').closest('tr').after('<tr><td>Display name position</td><td><select id="node_labelposition"><option value="C">Middle</option></select></td></tr>');
w.wmEditorText.displayName = 'Translated display';
w.wmEditorText.nodeDisplayHelp = '<Translated safe help>';
w.compactNodeEditor();
w.refineNodePicker();
assert.equal($('#node_label').closest('tr').find('td:first').text(), 'Translated display');
assert.equal($('#node_label').closest('tr').next().find('select').attr('id'),'node_labelposition');
assert.equal($('#node_labelposition').closest('tr').next().find('input').attr('id'),'node_iconfilename');
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
assert.equal($('#link_target_picker_rep').length, 0);
$('#link_target_picker_input').autocomplete('option', 'select').call($('#link_target_picker_input')[0], {}, {item:items[0]});
assert.equal($('#link_target_picker').val(), items[0].id);
assert.equal(w.local_graph_id, 10);
assert.equal($('#link_target_picker_add').prop('disabled'), true); // Main interface does not select an advanced source.
assert.equal($('#link_target_picker_rep').length, 0);
$('#link_target_picker_input').trigger('input');
assert.equal($('#link_target_picker').val(), '');
assert.equal(w.local_graph_id, 0);
assert.equal($('#link_target_picker_add').prop('disabled'), true);
assert.equal($('#link_target_picker_rep').length, 0);
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
// Real mode and delete handlers must cancel placement on every confirmation exit.
$('body').append('<input id="param"><input id="action"><input id="debug"><input id="xycapture"><img id="existingdata" data-width="600" data-height="300"><div class="mapData"></div><span id="tb_help"></span>');
$('#tb_help').data('idle-help','Idle help');
w.setCanvasSize=()=>{};
w.eval(editor.slice(editor.indexOf('function wmResetActionHelp()'),editor.indexOf('function setCanvasSize(')));
for (const kind of ['node','link']) {
 for (const exit of ['cancel','close']) for (const placement of ['add_link','add_link2']) {
  $('#param').val('first-endpoint');$('#action').val(placement);w.mapmode('existing');$('#tb_help').text('Place link');
  assert.equal($('#existingdata').hasClass('wm-placement-mode'),true);
  if(kind==='node') w.delete_node();else w.delete_link();
  if(kind==='link')assert.equal($('#param').val(),$('#link_name').val());
  if(exit==='cancel')$('.dlgConfirm').dialog('option','buttons')[0].click.call($('.dlgConfirm')[0]);else $('.dlgConfirm').dialog('close');
  assert.equal($('#action').val(),'');assert.equal($('#existingdata').hasClass('wm-placement-mode'),false);assert.equal($('#tb_help').text(),'Idle help');
 }
 $('#action').val(kind==='node'?'set_node_properties':'set_link_properties');
 if(kind==='node')w.delete_node();else w.delete_link();
 assert.equal($('#action').val(),kind==='node'?'set_node_properties':'set_link_properties');$('.dlgConfirm').dialog('close');
}
$('#link_hover').val('/cacti/graph_image.php?local_graph_id=10 /cacti/graph_image.php?local_graph_id=112');
w.wmInitLinkHoverParts();
const extrasBeforeInterfaceChange = $('#wm-link-extra-hover').val();
$('#link_target_picker').data('wm-choice', items[1]);
$('#wm-link-use').trigger('click');
assert.equal($('#wm-link-extra-hover').val(), extrasBeforeInterfaceChange, 'Changing traffic interface preserves extras');
assert.equal(new URL($('#wm-link-primary-hover').val(), w.location.href).searchParams.get('local_graph_id'), '20');
assert.equal($('#link_hover').val().split(/\s+/).length, 2);
// Opening a newly created link must clear previews from the previously edited link.
w.eval(editor.slice(editor.indexOf('function prime_link_form('), editor.indexOf('function show_link(')));
w.Links.fresh = {name:'fresh', target:'', width:7, bw_in:'1000M', bw_out:'1000M', infourl:'', overliburl:'', viastyle:'curved', commentin:'', commentout:'', commentposin:95, commentposout:5, a:'a', b:'b'};
$('#link_nodename1').text('a');$('#link_nodename2').text('b');
assert.equal(w.prime_link_form('fresh'),true);
w.compactLinkEditor();
assert.equal($('#wm-link-extra-hover').val(),'');
assert.equal($('#wm-link-primary-hover').val(),'');
$('#link_target_picker').data('wm-choice',items[0]);$('#wm-link-use').trigger('click');
assert.equal($('#wm-link-extra-hover').val(),'');
assert.equal($('#link_hover').val().split(/\s+/).length,1);
// Opening/simplifying the form preserves custom sources and destinations.
$('#link_target').val('/rrd/one.rrd /rrd/two.rrd');
$('#link_infourl').val('/custom/destination');
w.compactLinkEditor();
assert.equal($('#link_target').val(), '/rrd/one.rrd /rrd/two.rrd');
assert.equal($('#link_infourl').val(), '/custom/destination');
assert.equal($('#link_target').serialize(), ''); // Fixture input has no name.
$('#link_target').attr('name','link_target');
$('#link_infourl').attr('name','link_infourl');
const serialized = $('#dlgLinkProperties :input').serialize();
assert.match(serialized,/link_target=%2Frrd%2Fone.rrd%20%2Frrd%2Ftwo.rrd/);
assert.match(serialized,/link_infourl=%2Fcustom%2Fdestination/);
// The independent advanced source picker changes only TARGET, not click or hover settings.
const clickBeforeSources = $('#link_infourl').val(), hoverBeforeSources = $('#link_hover').val();
$('#link_target').val(items[0].id);
const sourceOptions = $('#wm-link-source-picker').data('options');
const mainChoice = $('#link_target_picker').data('wm-choice');
sourceOptions.select({}, {item:items[1]});
assert.equal($('#link_target_picker_add').prop('disabled'),false);
assert.equal($('#link_target_picker').data('wm-choice'),mainChoice);
w.wmApplyLinkTrafficSource();
assert.equal($('#link_target').val(),items[0].id+' '+items[1].id);
assert.match($('#wm-link-source-status').text(),/added/);
w.wmApplyLinkTrafficSource();
assert.equal($('#link_target').val(),items[0].id+' '+items[1].id);
assert.match($('#wm-link-source-status').text(),/already included/);
assert.equal($('#link_infourl').val(),clickBeforeSources);
assert.equal($('#link_hover').val(),hoverBeforeSources);
// Switching the primary preserves extras, without duplicating an extra promoted to primary.
$('#link_target_picker').data('wm-choice',items[1]);
$('#wm-link-use').trigger('click');
assert.equal($('#link_target').val(),items[1].id);
$('#link_infourl').val(clickBeforeSources);$('#link_hover').val(hoverBeforeSources);w.wmInitLinkHoverParts();
$('#wm-link-source-picker').val('New source search').trigger('input');
assert.equal($('#link_target_picker_add').prop('disabled'),true);
graphRequests.length=0;
sourceOptions.source({term:'Backup'},items=>{w.sourceResults=items;});
assert.equal(graphRequests[0].data.target,'link_target_picker');
graphRequests[0].deferred.resolve({items:[items[1]],offset:0,next_offset:100,next_label:'Next'});
assert.equal(w.sourceResults.length,2);
assert.equal(w.sourceResults[1].wmOffset,100);
// Missing and unchanged selections explain why replacement cannot change the list.
$('#link_target').val(items[1].id);
$('#wm-link-source-picker').data('wm-choice',items[1]);
w.wmApplyLinkTrafficSource();
assert.equal($('#wm-link-source-status').text(),w.wmEditorText.sourceAlreadyAdded);
assert.equal($('#link_target').val(),items[1].id);
$('#wm-link-source-picker').removeData('wm-choice');
w.wmApplyLinkTrafficSource();
assert.equal($('#wm-link-source-status').text(),w.wmEditorText.chooseSourceFirst);
assert.equal($('#link_target').val(),items[1].id);
// Removing a measured source changes only that entry, including duplicate/custom targets.
$('#link_target').val('first.rrd:in:out custom:second third.rrd').trigger('input');
assert.equal($('#wm-link-measured-sources button').length,2);
$('#wm-link-measured-sources button').eq(0).trigger('click');
assert.equal($('#link_target').val(),'first.rrd:in:out third.rrd');
assert.equal($('#link_infourl').val(),clickBeforeSources);
assert.equal($('#link_hover').val(),hoverBeforeSources);
assert.match($('#wm-link-source-status').text(),/removed/);
$('#link_target').val('same.rrd same.rrd').trigger('input');
$('#wm-link-measured-sources button').eq(0).trigger('click');
assert.equal($('#link_target').val(),'same.rrd','Remove one occurrence, not every duplicate');
$('#wm-link-measured-sources button').trigger('click');
assert.equal($('#link_target').val(),'same.rrd','Primary source cannot be removed using the extra-source controls');
assert.equal($('#wm-link-measured-sources button').length,0);
assert.match($('#wm-link-measured-sources').text(),/Primary/);
$('#link_target').val('').trigger('input');
$('#wm-link-source-picker').data('wm-choice',items[1]);
w.wmApplyLinkTrafficSource();
assert.equal($('#link_target').val(),'');
assert.equal($('#wm-link-source-status').text(),w.wmEditorText.choosePrimaryFirst);
assert.equal($('#link_hover').val(),hoverBeforeSources);
assert.equal($('#link_infourl').val(),clickBeforeSources);
$('#link_target').val('<img>custom.rrd').trigger('input');
assert.equal($('#wm-link-measured-sources img').length,0);
assert.equal($('#wm-link-measured-sources code').text(),'<img>custom.rrd');
$('#link_target_picker').data('wm-choice',items[0]);$('#wm-link-use').trigger('click');
assert.equal($('#wm-link-measured-sources code').text(),items[0].id,'Use interface refreshes list');
$('#link_target').val(items[0].id+' custom:backup third.rrd');
$('#link_target_picker').data('wm-choice',items[1]);$('#wm-link-use').trigger('click');
assert.equal($('#link_target').val(),items[1].id+' custom:backup third.rrd');
assert.equal($('#wm-link-measured-sources button').length,2);
assert.match($('#wm-link-source-count').text(),/3/);
const primaryChoice = $('#link_target_picker').data('wm-choice');
$('#wm-link-measured-sources button').first().trigger('click');
assert.equal($('#link_target_picker').data('wm-choice'),primaryChoice);
assert.equal($('#link_target').val(),items[1].id+' third.rrd');
// Promoting an explicit-default extra removes its implicit equivalent exactly once.
$('#link_target').val('primary.rrd '+items[1].id+':traffic_in:traffic_out custom.rrd:in:out gauge:'+items[1].id);
$('#link_target_picker').data('wm-choice',items[1]);$('#wm-link-use').trigger('click');
assert.equal($('#link_target').val(),items[1].id+' custom.rrd:in:out gauge:'+items[1].id);
assert.equal(w.wmLinkSameTrafficSource('rrd:backup.rrd:traffic_in:traffic_out','backup.rrd'),true);
assert.equal(w.wmLinkSameTrafficSource('scale:8:backup.rrd','backup.rrd'),true);
assert.equal(w.wmLinkSameTrafficSource('scale:2:backup.rrd','backup.rrd'),false);
assert.equal(w.wmLinkSameTrafficSource('scale:+8.0:backup.rrd','backup.rrd'),true);
assert.equal(w.wmLinkSameTrafficSource('scale:.5:backup.rrd','scale:0.5:backup.rrd'),true);
assert.equal(w.wmLinkTrafficIdentity('scale:'+ '9'.repeat(20000)+'x:backup.rrd'),null);
assert.equal(w.wmLinkTrafficIdentity('scale:'+ '9'.repeat(20000)+':backup.rrd'),null);
w.wmRrdSourceDefaults={input:'octets_in',output:'octets_out',path:'/rrd'};
assert.equal(w.wmLinkSameTrafficSource('backup.rrd','/rrd/backup.rrd:octets_in:octets_out'),true);
assert.equal(w.wmLinkSameTrafficSource('backup.rrd','backup.rrd:traffic_in:traffic_out'),false);
w.wmRrdSourceDefaults={};
// Outer multipliers participate in both add and promotion identities.
$('#link_target').val('primary.rrd 1*'+items[1].id);
$('#wm-link-source-picker').data('wm-choice',items[1]);w.wmApplyLinkTrafficSource();
assert.equal($('#link_target').val(),'primary.rrd 1*'+items[1].id);
$('#link_target_picker').data('wm-choice',items[1]);$('#wm-link-use').trigger('click');
assert.equal($('#link_target').val(),items[1].id);
assert.equal(w.wmLinkSameTrafficSource('2*scale:4:backup.rrd','backup.rrd'),true);
assert.equal(w.wmLinkSameTrafficSource('-2*scale:-4:backup.rrd','backup.rrd'),true);
assert.equal(w.wmLinkSameTrafficSource('-backup.rrd','backup.rrd'),false);
assert.equal(w.wmLinkSameTrafficSource('2*backup.rrd','backup.rrd'),false);
// Restore the custom click/hover setup for the independent editor assertions below.
$('#link_infourl').val(clickBeforeSources);$('#link_hover').val(hoverBeforeSources);w.wmInitLinkHoverParts();
// Manual expert edits remain serializable and cannot be overwritten by pending automatic lookups.
$('#link_target').val('/custom/source.rrd').trigger('input');
assert.equal($('#wm-link-use').prop('disabled'),true);
assert.match($('#wm-link-source-status').text(),/Custom traffic sources changed/);
assert.equal($('#wm-link-primary-hover').prop('readonly'),false);
$('#wm-link-primary-hover').val('/cacti/graph_image.php?local_graph_id=51').trigger('input');
$('#wm-link-extra-hover').val('/cacti/graph_image.php?local_graph_id=52').trigger('input');
assert.equal($('#link_hover').val(),'/cacti/graph_image.php?local_graph_id=51 /cacti/graph_image.php?local_graph_id=52');
assert.equal($('#link_infourl').val(),clickBeforeSources);
w.compactLinkEditor();
assert.equal($('#wm-link-sections .wm-link-group:not([hidden])').length,0);
assert.equal($('#wm-link-sections .wm-link-section-toggle[aria-expanded=true]').length,0);
assert.equal($('#wm-link-source-picker').val(),'');
assert.equal($('#link_target_picker_add').prop('disabled'),true);
assert.equal($('#link_target').val(),'/custom/source.rrd');
// The preview list removes only the selected extra, preserving traffic and click URLs.
const savedClick = $('#link_infourl').val();
const savedPrimary = $('#wm-link-primary-hover').val();
w.wmSetLinkExtraHover('/cacti/graph_image.php?local_graph_id=112',true);
assert.equal($('#wm-link-extra-list img').length,0);
assert.equal($('#wm-link-extra-list a').length,0);
assert.equal($('#wm-link-extra-list tr td:first').text(),'Additional graph');
w.wmSetLinkExtraHover('/cacti/graph_image.php?local_graph_id=113',false);
assert.match($('#wm-link-extra-hover').val(),/local_graph_id=112/);
assert.doesNotMatch($('#wm-link-extra-hover').val(),/local_graph_id=113/);
assert.match($('#wm-link-hover-status').text(),/One additional graph maximum/);
w.wmSetLinkExtraHover('/cacti/graph_image.php?local_graph_id=113',true);
assert.match($('#wm-link-extra-hover').val(),/local_graph_id=113/);
assert.doesNotMatch($('#wm-link-extra-hover').val(),/local_graph_id=112/);
assert.equal($('#wm-link-primary-hover').val(),savedPrimary);
assert.equal($('#link_infourl').val(),savedClick);
$('#wm-link-extra-list button').trigger('click');
assert.equal($('#wm-link-extra-hover').val(),'');
assert.equal($('#wm-link-primary-hover').val(),savedPrimary);
assert.equal($('#link_infourl').val(),savedClick);
// Reopening a single extra graph restores its readable picker selection.
$('<input id="link_picker_input">').appendTo(w.document.body);
$('#wm-link-extra-hover').val('/cacti/graph_image.php?local_graph_id=112');
w.wmSyncLinkHoverParts();
graphRequests.length=0;
w.wmRestoreLinkGraphChoice();
graphRequests[0].deferred.resolve({items:[{id:112,label:'Router CPU Usage',rrds:['router_cpu.rrd','router_cpu2.rrd']}]});
assert.equal($('#link_picker_input').val(),'Router CPU Usage');
assert.equal($('#link_picker').val(),'112');
assert.equal($('#wm-link-extra-list .wm-link-extra-name').text(),'Router CPU Usage');
assert.equal($('#wm-link-extra-list .wm-link-extra-rrds').text(),'router_cpu.rrd, router_cpu2.rrd');
graphRequests.length=0;
w.wmRestoreLinkGraphChoice();
$('#link_picker_input').val('New search');
graphRequests[0].deferred.resolve({items:[{id:112,label:'Old selection'}]});
assert.equal($('#link_picker_input').val(),'New search');
// Opening existing properties must preserve inherited dimensions and legend preferences.
const originalHovers='/cacti/graph_image.php?local_graph_id=112 /cacti/graph_image.php?local_graph_id=108';
$('#link_hover').val(originalHovers);w.wmInitLinkHoverParts();
assert.equal($('#link_hover').val(),originalHovers);
// Promotion refreshes metadata whether the old lookup resolves before or after it.
for(const earlier of [false,true]) {
 $('#link_hover').val(originalHovers);w.wmInitLinkHoverParts();graphRequests.length=0;
 w.wmRestoreLinkGraphChoice();
 if(earlier)graphRequests[0].deferred.resolve({items:[{id:108,label:'Traffic',rrds:['traffic.rrd']}]});
 w.wmPromoteLinkTrafficHover(108);
 assert.equal(graphRequests.length,2,'Promotion requests the new extra metadata');
 if(!earlier)graphRequests[0].deferred.resolve({items:[{id:108,label:'Traffic',rrds:['traffic.rrd']}]});
 graphRequests[1].deferred.resolve({items:[{id:112,label:'CPU',rrds:['cpu.rrd']}]});
 assert.equal($('#link_picker_input').val(),'CPU');
 assert.equal($('#wm-link-extra-list .wm-link-extra-rrds').text(),'cpu.rrd');
}
$('#link_hover').val(originalHovers);w.wmInitLinkHoverParts();
$('#link_picker').val('999');$('#link_picker_input').val('New user choice').data('wm-graph-choice',{id:999});
graphRequests.length=0;w.wmPromoteLinkTrafficHover(108);
graphRequests[0].deferred.resolve({items:[{id:112,label:'CPU',rrds:['cpu.rrd']}]});
assert.equal($('#link_picker_input').val(),'New user choice','Metadata refresh preserves newer user selection');
assert.equal($('#wm-link-extra-list .wm-link-extra-rrds').text(),'cpu.rrd');
console.log(
	'PASS: current interface, retained selection, explicit apply, advanced fields, action names, URL decoding and failed-request retry'
);
