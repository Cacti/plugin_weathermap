const assert = require('node:assert/strict');
// Load the actual module without evaluating source strings. Defer DOM-ready setup.
global.document = {};
global.$ = Object.assign(selector => typeof selector === 'function' ? undefined : {
 val: () => 'map.conf', on() {return this;}
}, {fn: {}});
const navigation = require('../../js/editor.js');
for (const fragment of ['', '#', '#note']) {
 const calls = [];
 const window = global.window = {location: {
  href: 'https://example.test/editor.php?action=nothing&mapname=map.conf' + fragment,
  reload() { calls.push('reload'); },
  replace(url) { calls.push(url); }
 }};
 navigation.wmReloadEditor();
 assert.deepEqual(calls, ['reload'], 'saving an already-open map must fetch a fresh document');
 window.location.href = 'https://example.test/editor.php?action=edit&config_revision=old&__csrf_magic=old#note';
 calls.length = 0;
 navigation.wmReloadEditor();
 const target = new URL(calls[0]);
 assert.equal(target.hash, '');
 assert.equal(target.searchParams.get('action'), 'nothing');
 assert.equal(target.searchParams.get('mapname'), 'map.conf');
 assert.equal(target.searchParams.has('config_revision'), false);
 assert.equal(target.searchParams.has('__csrf_magic'), false);
}
console.log('PASS: successful saves reload the document even at the same URL or fragment.');
