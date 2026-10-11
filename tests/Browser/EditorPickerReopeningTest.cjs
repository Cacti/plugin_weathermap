// Exercise actual jQuery UI focus/blur and menu selection for both editor pickers.
const {JSDOM} = require('jsdom');
const jquery = require('jquery');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const row = id => `<tr><td>${id}</td><td><input id="${id}"></td></tr>`;
const pickerRow = id => `<tr><td>Picker</td><td><input id="${id}"><span id="${id}_wrap"><input id="${id}_input"><button id="${id}_click"></button></span><button id="${id}_add"></button><button id="${id}_rep"></button></td></tr>`;
const dom = new JSDOM(`<input id="mapname" value="fixture.conf"><input id="link_name" value="a-b"><div id="dlgLinkProperties"><p><span id="link_nodename1"></span><span id="link_nodename2"></span></p><table>${['viastyle','link_target','link_infourl','link_hover','link_template','link_picker','link_width','link_commentin','link_commentout'].map(row).join('')}${pickerRow('link_target_picker')}</table><div class="dlgButtons"><div class="dlgSubButtons">${['link_delete','link_tidy','link_via','link_edit','tb_link_cancel','tb_link_submit'].map(id => `<a id="${id}">${id}</a>`).join('')}</div></div><div class="dlgHelp"></div></div><div id="dlgNodeProperties"><table>${pickerRow('node_picker')}${row('node_hover')}</table><button id="wm-node-toggle"></button></div>`, {url:'https://example.test/cacti/plugins/weathermap/editor.php',runScripts:'outside-only',pretendToBeVisual:true});
const w = dom.window, $ = jquery(w);
w.$ = w.jQuery = $;
const cactiJs = process.env.CACTI_JS_DIR || path.join(__dirname,'../../../../include/js');
w.eval(fs.readFileSync(path.join(cactiJs,'jquery-ui.js'),'utf8'));
const phpStrings = fs.readFileSync(path.join(__dirname,'../../lib/editor.inc.php'),'utf8');
w.wmEditorText = Object.fromEntries(Array.from(phpStrings.matchAll(/'([a-zA-Z]+)'\s*=> __\('([^']*)', (?:'%s', )?'weathermap'\)/g), match => [match[1],match[2]]));
w.infoUrlTarget = 'graph.php?rra_id=all&local_graph_id=';
const editor = fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');
w.eval(editor.slice(editor.indexOf('// Compact link workflow;')));
const items = [
 {id:'a.rrd',local_graph_id:10,label:'Switch A — Traffic — Gi0/1 — Uplink'},
 {id:'b.rrd',local_graph_id:20,label:'Switch B — Traffic — Gi0/2 — Backup'}
];
const terms = [];
for (const id of ['link_target_picker','node_picker']) {
 $(`#${id}_input`).autocomplete({minLength:0,delay:0,source(request,response) {
  terms.push(request.term);
  // Model an endpoint that searches individual fields, not the full display label.
  response(request.term === '' ? items : items.filter(item => item.label.endsWith(request.term)));
 },select(event,ui) { $(`#${id}`).val(ui.item.id); }});
}
w.compactLinkEditor();
w.refineNodePicker();
// jsdom has no layout: keep menu visibility observable without replacing widget logic.
const visible = $.expr.pseudos.visible;
$.expr.pseudos.visible = element => $(element).hasClass('ui-autocomplete') ? $(element).css('display') !== 'none' : visible(element);
(async () => {
 for (const [id,browse] of [['link_target_picker','wm-link-browse'],['node_picker','wm-node-browse']]) {
  const input = $(`#${id}_input`), button = $(`#${browse}`), widget = input.autocomplete('instance'), menu = input.autocomplete('widget');
  input.trigger('focus');
  button.trigger('focus'); // Actual button interaction blurs the input first.
  button.trigger('click');
  assert.ok(w.document.activeElement === input[0],`${id}: Browse all restores focus`);
  await new Promise(resolve => setTimeout(resolve,180)); // jQuery UI delayed blur close.
  assert.notEqual(menu.css('display'),'none',`${id}: results survive blur timer`);
  assert.equal(menu.children().length,2);
  // Use the real menu's focus/select path to select an item.
  widget.menu.focus($.Event('keydown'),menu.children().first());
  widget.menu.select($.Event('keydown'));
  assert.equal(input.val(),items[0].label);
  assert.equal($(`#${id}`).val(),items[0].id);
  for (const control of [input,$(`#${id}_click`)]) {
   control.trigger('click');
   assert.equal(terms.at(-1),'');
   assert.equal(input.val(),items[0].label);
   assert.equal($(`#${id}`).val(),items[0].id);
   assert.equal(menu.children().length,2);
   widget.close();
  }
  input.trigger('click');
  widget.menu.focus($.Event('keydown'),menu.children().last());
  widget.menu.select($.Event('keydown'));
  assert.equal(input.val(),items[1].label);
  assert.equal($(`#${id}`).val(),items[1].id);
  input.val('Backup').trigger('input');
  input.autocomplete('search',input.val());
  assert.equal(terms.at(-1),'Backup');
  assert.equal(menu.children().length,1);
  assert.equal(menu.children().first().text(),items[1].label);
  widget.close();
 }
 w.close();
 console.log('PASS: link/node Browse all focus, delayed blur, selection, input/arrow reopening and typed filtering');
})().catch(error => {console.error(error.message);process.exit(1);});
