const {JSDOM}=require('jsdom'),jquery=require('jquery'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const dom=new JSDOM('<body></body>',{runScripts:'outside-only',pretendToBeVisual:true});const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
 const media={matches:false,addEventListener(t,fn){this.listener=fn},removeEventListener(t,fn){if(this.listener===fn)this.listener=null}};w.matchMedia=()=>media;
 const observers=[];w.ResizeObserver=class {constructor(fn){this.fn=fn;observers.push(this)}observe(){}disconnect(){this.disconnected=true}};
 function map(enabled,regions){const holder=w.document.createElement('div');holder.className='weathermapimage';holder.dataset.wmTraffic=JSON.stringify({enabled,width:'400',height:'300',regions,occlusions:[[[0,0],[5,0],[5,5]]]});holder.innerHTML='<img usemap="#existing-map"><map name="existing-map"><area href="graph.php"></map>';w.document.body.appendChild(holder);return holder}
 const region={colour:'#ff8000',points:[[10,10],[30,10],[30,20]]};const active=map(true,[region]),disabled=map(false,[region]),quiet=map(true,[]);
 const source=fs.readFileSync(path.join(__dirname,'../../js/weathermap.js'),'utf8');w.eval(source);
 await new Promise(r=>w.setTimeout(r,20));
 assert.equal(active.querySelectorAll('.wm-traffic-pulse').length,1);assert.equal(active.querySelector('polygon.wm-traffic-pulse').getAttribute('stroke'),'#ff8000');
 const polygon=active.querySelector('polygon.wm-traffic-pulse');
 const filter=active.querySelector('filter');
 assert.equal(polygon.style.filter,'');
 assert.equal(polygon.getAttribute('filter'),'url(#'+filter.id+')');
 assert.equal(filter.getAttribute('filterUnits'),'userSpaceOnUse');
 assert.equal(filter.getAttribute('x'),'-20');assert.equal(filter.getAttribute('width'),'80');
 assert.deepEqual([...filter.querySelectorAll('feGaussianBlur')].map(b=>b.getAttribute('stdDeviation')),['2','5','9']);
 assert.equal(filter.querySelectorAll('feMergeNode').length,4);
 assert.equal(active.querySelector('mask').getAttribute('mask-type'),'luminance');
 assert.equal(active.querySelector('mask').getAttribute('width'),'460');assert.equal(active.querySelector('mask').getAttribute('height'),'360');assert.equal(active.querySelector('mask rect').getAttribute('width'),'460');assert.equal(active.querySelector('mask rect').getAttribute('height'),'360');
 assert.equal(active.querySelectorAll('mask polygon').length,1);assert.equal(active.querySelector('area').getAttribute('href'),'graph.php');
 assert.equal(disabled.querySelector('svg'),null);assert.equal(quiet.querySelector('svg'),null);
 $(w.document).trigger('ajaxComplete');assert.equal(active.querySelectorAll('svg').length,1);
 let tooltipCalls=0;$.fn.tooltip=function(options){if(options==='instance')return undefined;tooltipCalls++;return this};w.initWeatherMapView();assert.equal(tooltipCalls,1,'tooltip still initialises when UI exists');assert.equal(observers.length,1);
 media.matches=true;media.listener();assert.ok(active.querySelector('svg').classList.contains('wm-glow-paused'));
 active.remove();$(w.document).trigger('ajaxComplete');assert.ok(observers[0].disconnected);assert.equal(media.listener,null);
 console.log('Traffic glow colour, masking, reduced motion and cleanup PASS');w.close();
})().catch(e=>{console.error(e);process.exitCode=1});
