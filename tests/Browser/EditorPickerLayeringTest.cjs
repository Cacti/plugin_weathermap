// Regression for actual jQuery UI menu ownership when opening and reopening dialogs.
const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const dom = new JSDOM('<div id="dlgLinkProperties"><input id="picker"></div>', {runScripts: 'outside-only', pretendToBeVisual: true});
const w = dom.window;
const $ = jquery(w);
w.$ = w.jQuery = $;
const cactiJs = process.env.CACTI_JS_DIR || path.join(__dirname, '../../../../include/js');
w.eval(fs.readFileSync(path.join(cactiJs, 'jquery-ui.js'), 'utf8'));
const source = fs.readFileSync(path.join(__dirname, '../../js/editor.js'), 'utf8');
w.eval(source.slice(source.indexOf('function show_dialog('), source.indexOf('function hide_dialog(')));
$('#picker').autocomplete({source: ['Interface A'], appendTo: w.document.body});
const menu = $('#picker').autocomplete('widget');
assert.equal(menu.parent()[0], w.document.body);
for (let attempt = 0; attempt < 2; attempt++) {
  w.show_dialog('dlgLinkProperties');
  const dialog = $('#dlgLinkProperties').closest('.ui-dialog');
  assert.equal($('#picker').autocomplete('option', 'appendTo')[0], dialog[0]);
  assert.equal(menu.parent()[0], dialog[0]);
  $('#dlgLinkProperties').dialog('close');
  $('#picker').autocomplete('option', 'appendTo', w.document.body);
  assert.equal(menu.parent()[0], w.document.body);
}
$('#dlgLinkProperties').dialog('destroy');
dom.window.close();
console.log('PASS: actual jQuery UI autocomplete stays inside the editor dialog on open and reopen');
