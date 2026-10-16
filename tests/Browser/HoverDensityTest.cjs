const {JSDOM}=require('jsdom'), jquery=require('jquery'), fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const dom=new JSDOM('',{url:'https://cacti.example/cacti/plugins/weathermap/view.php',runScripts:'outside-only'}),w=dom.window,$=jquery(w);w.$=$;
const source=fs.readFileSync(path.join(__dirname,'../../js/weathermap.js'),'utf8');w.eval(source.slice(0,source.indexOf('function wmInteractiveHover')));
const url='/cacti/graph_image.php?local_graph_id=108&graph_width=300&graph_height=100&graph_start=123';
const image=$('<img>').attr('src',url);w.wmSharpHoverGraph(image);
assert.equal(image.attr('src'),url);const density=image.attr('srcset');assert.match(density,/ 2x$/);
const request=new URL(density.slice(0,-3));assert.equal(request.searchParams.get('wm_hidpi'),'2');assert.equal(request.searchParams.get('graph_width'),'300');assert.equal(request.searchParams.get('graph_height'),'100');assert.equal(request.searchParams.get('graph_start'),'123');
image.trigger('error');assert.equal(image.attr('srcset'),undefined);assert.equal(image.attr('src'),url);
for(const src of ['https://external.example'+url,'/cacti/graph_image.php?local_graph_id=108',url+'&image_format=svg',url.replace('300','1500'),'/custom.png']){const img=$('<img>').attr('src',src);w.wmSharpHoverGraph(img);assert.equal(img.attr('srcset'),undefined,src);}
console.log('PASS: local compact previews request double density with unchanged dimensions and original-image fallback');w.close();
