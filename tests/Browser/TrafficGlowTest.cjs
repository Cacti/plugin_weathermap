const {JSDOM}=require('jsdom'),jquery=require('jquery'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const dom=new JSDOM('<body></body>',{runScripts:'outside-only',pretendToBeVisual:true});const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
 const media={matches:false,addEventListener(t,fn){this.listener=fn},removeEventListener(t,fn){if(this.listener===fn)this.listener=null}};w.matchMedia=()=>media;
 const observers=[];w.ResizeObserver=class {constructor(fn){this.fn=fn;observers.push(this)}observe(){}disconnect(){this.disconnected=true}};
 function map(enabled,regions){const holder=w.document.createElement('div');holder.className='weathermapimage';holder.dataset.wmTraffic=JSON.stringify({enabled,width:100,height:50,regions,occlusions:[[[0,0],[5,0],[5,5]]]});holder.innerHTML='<img usemap="#existing-map"><map name="existing-map"><area href="graph.php"></map>';w.document.body.appendChild(holder);return holder}
 const region={colour:'#ff8000',points:[[10,10],[30,10],[30,20]]};const active=map(true,[region]),disabled=map(false,[region]),quiet=map(true,[]);
 const source=fs.readFileSync(path.join(__dirname,'../../js/weathermap.js'),'utf8');w.eval(source.slice(source.indexOf('// Optional pulsing glow')));
 await new Promise(r=>w.setTimeout(r,20));
 assert.equal(active.querySelectorAll('.wm-traffic-pulse').length,1);assert.equal(active.querySelector('polygon.wm-traffic-pulse').getAttribute('stroke'),'#ff8000');
 assert.equal(active.querySelectorAll('mask polygon').length,1);assert.equal(active.querySelector('area').getAttribute('href'),'graph.php');
 assert.equal(disabled.querySelector('svg'),null);assert.equal(quiet.querySelector('svg'),null);
 $(w.document).trigger('ajaxComplete');assert.equal(active.querySelectorAll('svg').length,1);assert.equal(observers.length,1);
 media.matches=true;media.listener();assert.ok(active.querySelector('svg').classList.contains('wm-glow-paused'));
 active.remove();observers[0].fn();assert.ok(observers[0].disconnected);assert.equal(media.listener,null);
 console.log('Traffic glow colour, masking, reduced motion and cleanup PASS');w.close();
})().catch(e=>{console.error(e);process.exitCode=1});
