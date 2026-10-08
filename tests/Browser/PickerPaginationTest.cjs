const {JSDOM} = require('jsdom');
const jquery = require('jquery');
const fs = require('fs');
const vm = require('vm');
const assert = require('node:assert/strict');
(async () => {
 const dom = new JSDOM('<input id="mapname" value="test.conf"><input id="link_picker" class="selectmenu-ajax" data-action="graphs" value="7"><select id="link_template"><option value="1">One</option><option value="2">Two</option></select>', {runScripts: 'outside-only'});
 const w = dom.window, $ = jquery(w);
 w.$ = $;
 let options, requestUrl, responseItems, failure;
 $.fn.textBoxWidth = () => 200;
 $.fn.autocomplete = function(arg, term) {
  if (typeof arg === 'object') { options = arg; }
  if (arg === 'search') { options.source({term}, data => { responseItems = data; }); }
  return this;
 };
 $.getJSON = (url, callback) => {
  requestUrl = new URL(url, 'https://example.test/');
  const offset = Number(requestUrl.searchParams.get('offset'));
  callback({items: [{label: 'Result', id: 115}], offset, next_offset: offset === 0 ? 100 : null, next_label: 'Next results', previous_label: 'Previous results'});
  return {fail: fn => { failure = fn; }};
 };
 vm.runInContext(fs.readFileSync(require('node:path').join(__dirname, '../../js/editor.js'), 'utf8'), dom.getInternalVMContext());
 w.initJS = () => {};
 w.graphPicker();
 options.source({term: 'A&B'}, data => { responseItems = data; });
 assert.equal(requestUrl.searchParams.get('term'), 'A&B');
 assert.equal(requestUrl.searchParams.get('paged'), '1');
 assert.equal(responseItems.at(-1).wmOffset, 100);
 assert.equal(options.autoFocus, false);
 assert.equal(options.focus({}, {item: responseItems.at(-1)}), false);
 assert.equal(options.select({}, {item: responseItems.at(-1)}), false);
 await new Promise(resolve => setTimeout(resolve, 10));
 assert.equal(requestUrl.searchParams.get('offset'), '100');
 assert.equal($('#link_picker').val(), '7');
 assert.equal(responseItems[0].wmOffset, 0);
 options.select({}, {item: responseItems[0]});
 await new Promise(resolve => setTimeout(resolve, 10));
 assert.equal(requestUrl.searchParams.get('offset'), '0');
 options.source({term: 'Other'}, data => { responseItems = data; });
 assert.equal(requestUrl.searchParams.get('offset'), '0');
 $('#link_template').val('2');
 options.source({term: 'Other'}, data => { responseItems = data; });
 assert.equal(requestUrl.searchParams.get('offset'), '0');
 failure();
 assert.equal(responseItems.length, 0);
 options.select({}, {item: {label: 'Chosen', id: 115, local_graph_id: 115}});
 assert.equal($('#link_picker').val(), '115');
 assert.equal($('#link_picker_input').val(), 'Chosen');
 console.log('PASS: encoded searches, paging controls, selection preservation, filter resets, and request failure handling');
 w.close();
})().catch(error => {console.error(error); process.exitCode = 1;});
