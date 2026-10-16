const {JSDOM}=require('jsdom'), jquery=require('jquery'), fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const dom=new JSDOM('<map name="test"><area shape="rect" coords="0,0,100,100" href="/cacti/graph.php?local_graph_id=108"></map>',{url:'https://cacti.example/cacti/plugins/weathermap/view.php',runScripts:'outside-only',pretendToBeVisual:true});
 const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
 w.eval(fs.readFileSync(path.join(process.env.CACTI_JS_DIR,'jquery-ui.js'),'utf8'));
 $('area').attr('data-hover',Buffer.from('<div><img src="/cacti/graph_image.php?local_graph_id=108"><br><img src="/cacti/graph_image.php?local_graph_id=159"></div>').toString('base64'));
 w.eval(fs.readFileSync(path.join(__dirname,'../../js/weathermap.js'),'utf8'));
 await new Promise(r=>$(r));await new Promise(r=>setImmediate(r));
 const area=$('area'),map=$('map'),instance=map.tooltip('instance');
 function open(){area[0].dispatchEvent(new w.MouseEvent('mouseover',{bubbles:true}));}
 const delay=()=>new Promise(r=>w.setTimeout(r,350));
 open();let tip=$('.ui-tooltip');
 assert.equal(tip.length,1);assert.equal(tip.css('pointer-events'),'auto');
 assert.equal(tip.find('a').length,2);
 assert.equal(tip.find('a').eq(1).attr('href'),'/cacti/graph.php?rra_id=all&local_graph_id=159');
 assert.equal(area.attr('href'),'/cacti/graph.php?local_graph_id=108');
 area.trigger($.Event('mouseleave',{relatedTarget:tip[0]}));
 tip.trigger('mouseenter');await delay();
 assert.equal($('.ui-tooltip').length,1,'Popup remains while entering preview');
 tip.trigger($.Event('mouseleave',{relatedTarget:w.document.body}));await delay();
 assert.equal($('.ui-tooltip').length,0,'Popup closes after leaving both link and previews');
 open();tip=$('.ui-tooltip');area.trigger('mouseleave');
 tip.find('a')[0].focus();await delay();
 assert.equal($('.ui-tooltip').length,1,'Keyboard focus keeps previews open');
 tip.find('a').trigger($.Event('keydown',{key:'Escape'}));
 assert.equal($('.ui-tooltip').length,0,'Escape closes focused preview');
 open();area.trigger('mouseleave');await delay();
 assert.equal($('.ui-tooltip').length,0,'Ordinary mouse leave closes without sticking');
 // Switching quickly between hotspots must not leave the first area marked open.
 const other=area.clone().appendTo(map);
 open();area.trigger('mouseleave');
 other[0].dispatchEvent(new w.MouseEvent('mouseover',{bubbles:true}));
 assert.equal($('.ui-tooltip').length,1);
 assert.equal(area.data('ui-tooltip-open'),undefined);
 other.trigger('mouseleave');open();
 assert.equal($('.ui-tooltip').length,1,'First hotspot can reopen after switching');
 instance.close($.Event('keyup',{currentTarget:area[0]}));
 // Generated IDs stay hidden for cloned nodes, cloned endpoints and parallel links.
 for (const caption of ['node08121', 'node08121_copy', 'node08121_copy_copy',
  'node07486-node08121_copy', 'node07486_copy_copy-node08121_copya',
  'node07486a-node08121_copy_copyaa']) {
  area.attr('data-caption',caption);open();
  assert.equal($('#wm_hover').children().length,1,'No generated caption: '+caption);
  instance.close($.Event('keyup',{currentTarget:area[0]}));
 }
 for (const caption of ['Grand AP traffic', 'WAN backup', '<b>Custom caption</b>']) {
  area.attr('data-caption',caption);open();
  assert.equal($('#wm_hover').children().first().text(),caption,'Keep custom caption');
  assert.equal($('#wm_hover').find('b').length,0,'Caption remains plain text');
  instance.close($.Event('keyup',{currentTarget:area[0]}));
 }
 // Reinitialising after AJAX must not wrap close repeatedly.
 const close=instance.close;w.initWeatherMapView();assert.equal(instance.close,close);
 console.log('PASS: real jQuery UI graph links, pointer transfer, focus, Escape and cleanup');w.close();
})().catch(e=>{console.error(e);process.exitCode=1});
