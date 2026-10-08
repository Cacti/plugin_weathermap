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
console.log(
	'PASS: current interface, retained selection, explicit apply, advanced fields, action names, URL decoding and failed-request retry'
);
