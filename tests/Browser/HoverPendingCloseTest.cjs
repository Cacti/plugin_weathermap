/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
const {JSDOM} = require('jsdom'), jquery = require('jquery');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
(async () => {
 const dom = new JSDOM('<map><area data-hover=""></map>', {url: 'https://example.test/view.php', runScripts: 'outside-only'});
 const w = dom.window, $ = jquery(w); w.$ = w.jQuery = $;
 w.eval(fs.readFileSync(path.join(process.env.CACTI_JS_DIR, 'jquery-ui.js'), 'utf8'));
 w.eval(fs.readFileSync(path.join(__dirname, '../../js/weathermap.js'), 'utf8'));
 await new Promise(resolve => $(resolve));
 const map = $('map'), area = $('area'), instance = map.tooltip('instance');
 // Keep the real callback response path, including 1.13's deferred callback.
 instance.option('content', function(response) { response('<a href="/graph.php">Graph</a>'); });
 for (const type of ['mouseleave', 'focusout']) {
  area.trigger('mouseover');
  area.data('wm-pointer-inside', false);
  instance.close($.Event(type, {currentTarget: area[0]}));
  await new Promise(resolve => w.setTimeout(resolve, 350));
  assert.equal($('.ui-tooltip').length, 0, type + ' must not leave a deferred popup open');
  assert.equal(area.data('ui-tooltip-open'), undefined);
 }
 console.log('PASS: pending hover cancellation with jQuery UI ' + $.ui.version);
 w.close();
})().catch(error => { console.error(error); process.exitCode = 1; });
