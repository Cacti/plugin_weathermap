/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const jquery = require('jquery');
const connectWorker = require('../../js/editor.history.worker.js');
const {MessageChannel} = require('node:worker_threads');
const {webcrypto} = require('node:crypto');
const {init, claimHistoryId} = require('../../js/editor.history.js');
(async () => {
const dom = new JSDOM('<input id="mapname" value="map.conf"><button id="wm-undo" disabled>Undo</button><button id="wm-redo" disabled>Redo</button><span id="wm-history-status"></span><input id="text"><div class="ui-dialog-content" data-visible="false"></div>', {url: 'https://example.test/editor.php?action=nothing&mapname=map.conf#node'});
const $ = jquery(dom.window), requests = [], navigation = [], alerts = [];
$.expr.pseudos.visible = element => element.dataset.visible === 'true';
// Model the browser's origin-wide LockManager across independent tab storage copies.
const held = new Set();
const locks = {request(name, options, callback) {
 if (held.has(name)) return Promise.resolve().then(() => callback(null));
 held.add(name);
 return Promise.resolve().then(() => callback({name})).finally(() => held.delete(name));
}};
const win = {
 navigator: {locks}, addEventListener: dom.window.addEventListener.bind(dom.window),
 document: dom.window.document, sessionStorage: dom.window.sessionStorage, crypto: webcrypto,
 location: {href: dom.window.location.href, pathname: '/editor.php', reload: () => navigation.push('reload'), replace: url => navigation.push(url)},
 WMConfigRevision: 'revision', editor_url: 'editor.php', csrfMagicToken: 'csrf', alert: message => alerts.push(message),
 wmEditorText: {historyUndo: 'Undo', historyRedo: 'Redo', historyEmpty: 'No map changes', historyAvailable: 'Undo: %s steps; Redo: %s steps.', historyDialog: 'Save or cancel first', historyStorage: 'Storage unavailable', saveError: 'Failed'}
};
$.ajaxTransport('+*', options => ({send(headers, complete) {requests.push({options, complete});}, abort() {}}));
function respond(index, status, body) {requests[index].complete(status, status === 200 ? 'OK' : 'Conflict', {text: JSON.stringify(body)}, 'Content-Type: application/json');}
function count() {return requests.length;}
function key(target, extra = {}) {$(target).trigger($.Event('keydown', {key: 'z', ctrlKey: true, ...extra}));}
const startup = init($, win);
assert.equal(count(), 0, 'history is not requested until tab ownership is established');
await startup;
assert.equal(count(), 1);
const initial = new URLSearchParams(requests[0].options.data);
assert.equal(initial.get('action'), 'history_status');
assert.match(initial.get('history_id'), /^[a-f0-9]{32}$/);
assert.equal(initial.get('__csrf_magic'), 'csrf');
respond(0, 200, {undo: 2, redo: 1, undoLabel: 'Delete node', redoLabel: 'Move node'});
assert.equal($('#wm-undo').prop('disabled'), false);
assert.equal($('#wm-redo').attr('title'), 'Redo: Move node');
assert.equal($('#wm-undo').attr('title'), 'Undo: Delete node');
key('#text'); assert.equal(count(), 1, 'text fields keep native Undo');
$('.ui-dialog-content').attr('data-visible', 'true');
key(dom.window.document); assert.equal(count(), 1, 'open dialogs keep native text Undo');
$('#wm-undo').trigger('click'); assert.deepEqual(alerts, ['Save or cancel first']);
$('.ui-dialog-content').attr('data-visible', 'false');
key(dom.window.document);
assert.equal(count(), 2);
assert.equal(new URLSearchParams(requests[1].options.data).get('action'), 'undo');
assert.equal($('#wm-redo').prop('disabled'), true, 'a pending mutation disables both buttons');
key(dom.window.document); assert.equal(count(), 2, 'cannot dispatch a duplicate Undo');
respond(1, 200, {undo: 1, redo: 2});
assert.deepEqual(navigation, ['reload'], 'even fragment URLs fetch a fresh document');
key(dom.window.document, {shiftKey: true, ctrlKey: false, metaKey: true});
assert.equal(new URLSearchParams(requests[2].options.data).get('action'), 'redo');
respond(2, 409, {error: 'Map changed elsewhere'});
assert.equal(alerts.at(-1), 'Map changed elsewhere');
assert.equal($('#wm-undo').prop('disabled'), true);
assert.equal($('#wm-redo').prop('disabled'), true);
const colour = $.ajax({url: 'editor.php', type: 'POST', data: {action: 'set_map_colours'}});
assert.equal(new URLSearchParams(requests.at(-1).options.data).get('history_id'), initial.get('history_id'), 'separate Manage Colors requests share the same history');
respond(requests.length - 1, 200, {});
$.ajax({url: 'other.php', type: 'POST', data: {action: 'save'}});
assert.equal(new URLSearchParams(requests.at(-1).options.data).has('history_id'), false, 'unrelated requests are untouched');
respond(requests.length - 1, 200, {});
// A duplicated tab inherits sessionStorage, but cannot inherit the live tab's stack.
const storageKey = 'weathermap-history:/editor.php';
function tab(copiedId, manager = locks) {
 const events = new Map(), store = new Map([[storageKey, copiedId]]);
 return {crypto: webcrypto, navigator: {locks: manager}, sessionStorage: {getItem: key => store.get(key), setItem: (key,value) => store.set(key,value)},
  location: {href: 'http://example.test/editor.php', reload() {events.set('reloaded', true);}}, editor_url: 'editor.php', addEventListener: (name, callback) => events.set(name, callback), events};
}
const duplicated = tab(initial.get('history_id'));
const duplicateId = await claimHistoryId(duplicated, storageKey);
assert.notEqual(duplicateId, initial.get('history_id'), 'a duplicated/opener tab claims a fresh history');
assert.equal(duplicated.sessionStorage.getItem(storageKey), duplicateId);
duplicated.events.get('pagehide')();
await new Promise(resolve => setImmediate(resolve));
const reloaded = tab(duplicateId);
assert.equal(await claimHistoryId(reloaded, storageKey), duplicateId, 'same-tab reload keeps its history ID after releasing ownership');
reloaded.events.get('pageshow')({persisted: true});
assert.equal(reloaded.events.get('reloaded'), true, 'back/forward restoration reacquires ownership');
reloaded.events.get('pagehide')();
// Exercise the actual shared worker protocol as the HTTP-compatible fallback.
class SharedWorker {
 constructor() {
  const channel = new MessageChannel();
  this.port = channel.port2;
  connectWorker({ports: [channel.port1]});
 }
}
const httpTab = tab(duplicateId, null); httpTab.SharedWorker = SharedWorker;
assert.equal(await claimHistoryId(httpTab, storageKey), duplicateId, 'HTTP fallback can reuse an unowned ID');
const httpDuplicate = tab(duplicateId, null); httpDuplicate.SharedWorker = SharedWorker;
assert.notEqual(await claimHistoryId(httpDuplicate, storageKey), duplicateId, 'HTTP duplicated tab gets a fresh ID');
httpTab.events.get('pagehide')();
await new Promise(resolve => setTimeout(resolve, 20));
const httpReload = tab(duplicateId, null); httpReload.SharedWorker = SharedWorker;
assert.equal(await claimHistoryId(httpReload, storageKey), duplicateId, 'HTTP reload keeps its history');
httpDuplicate.events.get('pagehide')(); httpReload.events.get('pagehide')();
console.log('PASS: history buttons, shortcuts, tab identity, CSRF, pending saves, native text Undo, and stale-map errors.');
dom.window.close();

})().catch(error => { console.error(error); process.exitCode = 1; });
