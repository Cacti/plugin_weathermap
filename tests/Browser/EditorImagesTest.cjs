// Verify library metadata, guarded deletion and upload/request wiring.
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const dom=new JSDOM('<div id="dlgImages"></div><div id="wm-images-error"></div><div id="wm-images-objects"></div><div id="wm-images-backgrounds"></div><div id="wm-images-objects-count"></div><div id="wm-images-backgrounds-count"></div><input id="mapname" value="map.conf"><select id="node_iconfilename"><option value="--NONE--">Label Only</option></select><select id="map_bgfile"><option value="--NONE--">No background</option></select><input type="file" id="wm-image-file-objects"><button class="wm-image-upload" data-kind="objects"></button>',{runScripts:'outside-only',pretendToBeVisual:true});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;w.editor_url='/editor';w.csrfMagicToken='fixture';$.fn.dialog=function(){return this;};
const inc=fs.readFileSync(path.join(__dirname,'../../lib/editor.inc.php'),'utf8');w.wmEditorText={};
for(const m of inc.matchAll(/'(imageText[0-9]+|imageUsed|imageUnused)' => __\('((?:\\.|[^'])+)'/g))w.wmEditorText[m[1]]=m[2].replace(/\\'/g,"'");
w.wmEditorText.imageUnused='Non utilisée';
const source=fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');w.eval(source.slice(source.indexOf('function wmAssetRequest(')));
const images=[{kind:'objects',name:'hospital.png',url:'images/objects/hospital.png',width:96,height:96,users:['map.conf'],deletable:false},{kind:'backgrounds',name:'background.jpg',url:'images/backgrounds/background.jpg',width:800,height:600,users:[],deletable:true}];
$('#node_iconfilename').append('<option value="images/icons/external.png">External icon</option>').val('images/icons/external.png');
w.wmAssetImages(images);
assert.equal($('#node_iconfilename').val(),'images/icons/external.png');assert.equal($('#node_iconfilename option[value="images/icons/external.png"]').length,1);
assert.equal($('#wm-images-objects .wm-image-delete').length,0);assert.match($('#wm-images-objects').text(),/96 × 96 px/);assert.match($('#wm-images-objects').text(),/map.conf/);assert.match($('#wm-images-backgrounds').text(),/Non utilisée/);
assert.equal($('#node_iconfilename option[value="images/objects/hospital.png"]').length,1);
for (const [id, value] of [['node_iconfilename','images/objects/custom folder/site.png'], ['map_bgfile','images/backgrounds/private.jpg']]) {
 const select=$('#'+id);
 select.append($('<option>').val(value).text('Current custom image').attr('data-imagesrc',value)).val(value);
 w.wmAssetImages(images);
 assert.equal(select.val(),value);
 assert.equal(select.find('option').filter(function(){return this.value===value;}).length,1);
 assert.equal(select.find('option:selected').attr('data-imagesrc'),value);
 w.wmAssetImages(images);
 assert.equal(select.val(),value);
 assert.equal(select.find('option').filter(function(){return this.value===value;}).length,1);
}
let request;$.ajax=opts=>request=opts;w.confirm=()=>false;$('#wm-images-backgrounds .wm-image-delete').trigger('click');assert.equal(request,undefined);
w.confirm=()=>true;$('#wm-images-backgrounds .wm-image-delete').trigger('click');assert.equal(request.type,'POST');assert.equal(request.data.action,'delete_image');assert.equal(request.data.image_kind,'backgrounds');assert.equal(request.data.__csrf_magic,'fixture');
request.error({responseJSON:{error:'In use'}});assert.equal($('#wm-images-error').text(),'In use');
w.wmOpenImages();assert.equal(request.type,'GET');assert.equal(request.data.action,'image_data');request.success({images});
$('.wm-image-upload').trigger('click');assert.match($('#wm-images-error').text(),/Choose a PNG/);
Object.defineProperty($('#wm-image-file-objects')[0],'files',{value:[new w.File(['image'],'test.png',{type:'image/png'})]});$('.wm-image-upload').trigger('click');assert.equal(request.data.get('action'),'upload_image');assert.equal(request.data.get('image_kind'),'objects');assert.equal(request.data.get('__csrf_magic'),'fixture');assert.equal($('.wm-image-upload').prop('disabled'),true);request.complete();assert.equal($('.wm-image-upload').prop('disabled'),false);
dom.window.close();console.log('PASS: image metadata, translations, protected delete, confirmation, request errors and upload wiring');
