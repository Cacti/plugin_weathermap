const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
 const dom = new JSDOM('<div id="content" class="cactiGraphContentArea"><div class="weathermapimage"></div></div>', {
  url: 'https://cacti.example/cacti/plugins/weathermap/weathermap-cacti-plugin.php', runScripts: 'outside-only'
 });
 const w = dom.window, $ = jquery(w);
 w.$ = w.jQuery = $;
 w.eval(fs.readFileSync(path.join(__dirname, '../../js/weathermap.js'), 'utf8'));
 await new Promise(resolve => $(resolve));
 const settle = () => new Promise(resolve => w.setTimeout(resolve, 10));
 await settle();
 assert.ok($('#content').hasClass('cactiGraphContentArea'), 'Keep the core layout class on Weathermap pages');
 assert.ok($('#content').hasClass('wm_scroll'), 'Maps retain scrolling');
 // Cacti navigation replaces content while the plugin AJAX handler stays registered.
 $('#content').html('<div class="cactiGraphContentArea" id="graphs"><img src="graph_image.php"></div>');
 $(w.document).trigger('ajaxComplete');
 await settle();
 assert.ok($('#content').hasClass('cactiGraphContentArea'), 'Tree view retains its core layout');
 assert.ok($('#graphs').hasClass('cactiGraphContentArea'), 'Nested graph layout is preserved');
 assert.equal($('.wm_scroll').length, 0, 'Remove plugin scrolling when navigating away');
 for (const className of ['wm_gallery', 'all_map_holder', 'weathermapimage']) {
  $('#content').html('<div class="' + className + '"></div>');
  $(w.document).trigger('ajaxComplete');
  await settle();
  assert.ok($('#content').hasClass('cactiGraphContentArea'));
  assert.ok($('#content').hasClass('wm_scroll'), className + ' retains scrolling');
 }
 // A queued Weathermap update must inspect current content, not the previous page.
 w.initWeatherMapView();
 $('#content').html('<table id="tree-graphs"></table>');
 await settle();
 assert.ok($('#content').hasClass('cactiGraphContentArea'));
 assert.equal($('.wm_scroll').length, 0);
 console.log('PASS: Weathermap scrolling and Cacti tree layout survive AJAX navigation');
 w.close();
})().catch(error => { console.error(error); process.exitCode = 1; });
