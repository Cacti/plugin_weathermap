const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../../js/editor.js'), 'utf8');
const navigation = source.slice(source.indexOf('function wmEditorReloadUrl()'), source.indexOf('function form_submit('));
for (const fragment of ['', '#', '#note']) {
 const calls = [];
 const window = {location: {
  href: 'https://example.test/editor.php?action=nothing&mapname=map.conf' + fragment,
  reload() { calls.push('reload'); },
  replace(url) { calls.push(url); }
 }};
 const context = {URL, window, $: () => ({val: () => 'map.conf'})};
 vm.createContext(context);
 vm.runInContext(navigation, context);
 context.wmReloadEditor();
 assert.deepEqual(calls, ['reload'], 'saving an already-open map must fetch a fresh document');
 window.location.href = 'https://example.test/editor.php?action=edit&config_revision=old&__csrf_magic=old#note';
 calls.length = 0;
 context.wmReloadEditor();
 const target = new URL(calls[0]);
 assert.equal(target.hash, '');
 assert.equal(target.searchParams.get('action'), 'nothing');
 assert.equal(target.searchParams.get('mapname'), 'map.conf');
 assert.equal(target.searchParams.has('config_revision'), false);
 assert.equal(target.searchParams.has('__csrf_magic'), false);
}
console.log('PASS: successful saves reload the document even at the same URL or fragment.');
