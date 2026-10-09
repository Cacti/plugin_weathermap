const {JSDOM} = require('jsdom');
const jquery = require('jquery');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const dom = new JSDOM('<input id="mapname" name="mapname"><input id="link_picker" class="selectmenu-ajax" data-action="graphs" value="7"><select id="link_template"><option value="1">One</option></select><div class="mapData"></div><img id="existingdata"><img id="xycapture"><input id="action">', {runScripts: 'outside-only'});
const w = dom.window, $ = jquery(w);
w.$ = $;
let pickerOptions;
$.fn.textBoxWidth = () => 200;
$.fn.autocomplete = function(options) { if (typeof options === 'object') pickerOptions = options; return this; };
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/editor.js'), 'utf8'), dom.getInternalVMContext());
w.hide_all_dialogs = () => {};
w.initJS = () => {};
w.graphPicker();
for (const filename of ['ordinary.conf', 'A&B#C? +%é.conf']) {
 $('#mapname').val(filename);
 const requests = [];
 const check = url => {
  const parsed = new URL(url, 'https://example.test/editor');
  assert.equal(parsed.searchParams.get('mapname'), filename);
  assert.equal(parsed.hash, '');
  requests.push(parsed.searchParams.get('action'));
 };
 $.getJSON = (url, callback) => { check(url); callback({items: [], offset: 0, next_offset: null}); return {fail() {}}; };
 pickerOptions.source({term: 'interface'}, () => {});
 $.ajax = options => {
  assert.equal(new URLSearchParams(options.data).get('mapname'), filename);
  options.success('');
 };
 $.get = (url, callback) => { check(url); callback(''); };
 $.getScript = (url, callback) => { check(url); callback(''); };
 $('#existingdata, #xycapture').attr('src', '?action=draw&mapname=' + encodeURIComponent(filename));
 w.form_submit();
 assert.deepEqual(requests, ['graphs', 'load_area_data', 'load_map_javascript']);
 check($('#existingdata').attr('src'));
 check($('#xycapture').attr('src'));
}
w.close();
console.log('PASS: picker, serialized save, map reload, JavaScript reload and image refresh retain ordinary and delimiter-containing filenames.');
