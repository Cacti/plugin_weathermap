/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const jquery = require('jquery');
const {webcrypto} = require('node:crypto');
const {init} = require('../../js/editor.history.js');
const dom = new JSDOM('<input id="mapname" value="map.conf"><button id="wm-undo" disabled>Undo</button><button id="wm-redo" disabled>Redo</button><span id="wm-history-status"></span><input id="text"><div class="ui-dialog-content" data-visible="false"></div>', {url: 'https://example.test/editor.php?action=nothing&mapname=map.conf#node'});
const $ = jquery(dom.window), requests = [], navigation = [], alerts = [];
$.expr.pseudos.visible = element => element.dataset.visible === 'true';
const win = {
 document: dom.window.document, sessionStorage: dom.window.sessionStorage, crypto: webcrypto,
 location: {href: dom.window.location.href, pathname: '/editor.php', reload: () => navigation.push('reload'), replace: url => navigation.push(url)},
 WMConfigRevision: 'revision', editor_url: 'editor.php', csrfMagicToken: 'csrf', alert: message => alerts.push(message),
 wmEditorText: {historyUndo: 'Undo', historyRedo: 'Redo', historyEmpty: 'No map changes', historyAvailable: 'Undo: %s steps; Redo: %s steps.', historyDialog: 'Save or cancel first', historyStorage: 'Storage unavailable', saveError: 'Failed'}
};
$.ajaxTransport('+*', options => ({send(headers, complete) {requests.push({options, complete});}, abort() {}}));
function respond(index, status, body) {requests[index].complete(status, status === 200 ? 'OK' : 'Conflict', {text: JSON.stringify(body)}, 'Content-Type: application/json');}
function count() {return requests.length;}
function key(target, extra = {}) {$(target).trigger($.Event('keydown', {key: 'z', ctrlKey: true, ...extra}));}
init($, win);
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
console.log('PASS: history buttons, shortcuts, tab identity, CSRF, pending saves, native text Undo, and stale-map errors.');
dom.window.close();
