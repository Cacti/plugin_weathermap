const assert = require('node:assert/strict'), fs = require('fs'), path = require('path'), vm = require('vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/editor.js'), 'utf8');
const start = source.indexOf('function wmEditorReturnUrl('), end = source.indexOf('\nfunction attach_click_events()', start);
assert.ok(start >= 0 && end > start);
const context = {}; vm.createContext(context); vm.runInContext(source.slice(start, end), context);
const resolve = context.wmEditorReturnUrl, viewer = 'weathermap-cacti-plugin.php', manage = 'weathermap-cacti-plugin-mgmt.php';
assert.equal(resolve(manage), manage);
for (const hash of ['a'.repeat(20), 'A'.repeat(32), 'b'.repeat(64)]) {
 const url = viewer + '?action=viewmap&id=' + hash;
 assert.equal(resolve(url), url);
}
for (const value of [undefined, null, {}, viewer, 'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>',
 'https://example.test/' + manage, '//example.test/' + manage, '../' + manage, manage + '?next=javascript:alert(1)',
 viewer + '?action=viewmap&id=' + 'a'.repeat(19), viewer + '?action=viewmap&id=' + 'a'.repeat(65),
 viewer + '?action=viewmap&id=<img>', viewer + '?action=viewmap&id=' + 'a'.repeat(32) + '&next=evil',
 viewer + '?action=viewmap&id=' + 'a'.repeat(32) + '#fragment']) {
 assert.equal(resolve(value), viewer);
}
console.log('PASS: editor return destinations are restricted to local viewer/management pages and validated map hashes; script, external, traversal and malformed URLs fall back safely.');
