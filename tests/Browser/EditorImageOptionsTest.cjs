// Browser half of the editor image-option contract: the server markup pinned by
// tests/E2E/EditorImageOptionsFixtureTest.php, previewed through the real wmEditorImageSrc().
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const markup=fs.readFileSync(path.join(__dirname,'fixtures/editor-image-options.html'),'utf8');
const dom=new JSDOM('<form>'+markup+'</form>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/cacti/plugins/weathermap/weathermap-cacti-plugin-editor.php'});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;w.selectedNode='images/objects/core router.png';
const inc=fs.readFileSync(path.join(__dirname,'../../lib/editor.inc.php'),'utf8');
w.wmEditorText={};
for(const match of inc.matchAll(/'(icon[A-Za-z]+)' => __\('([^']+)'/g))w.wmEditorText[match[1]]=match[2];
// Evaluate the whole editor scripts the page includes, in page order, so the
// check does not depend on which file declares wmEditorImageSrc(). editor.js
// runs initJS() on DOM ready; this check needs only the picker functions.
for(const name of ['editor.js','editor.dialogs.js','editor.picker.js','editor.compact.js','editor.assets.js']){
	const file=path.join(__dirname,'../../js',name);
	if(fs.existsSync(file))w.eval(fs.readFileSync(file,'utf8'));
}
w.initJS=()=>{};
const base='https://example.test/cacti/plugins/weathermap/';
const expected={
	node_iconfilename:[
		['/srv/icons/outside.png',''],
		['images/../setup.png',''],
		['images/objects/%2e%2e/%2e%2e/setup.png',base+'images/objects/%252e%252e/%252e%252e/setup.png'],
		['images/objects/100%.png',base+'images/objects/100%25.png'],
		['images/objects/café.png',base+'images/objects/caf%C3%A9.png'],
		['images/objects/core router.png',base+'images/objects/core%20router.png'],
		['images/objects/custom folder/site.png',base+'images/objects/custom%20folder/site.png'],
		['images/objects/router.png',base+'images/objects/router.png'],
		['images/objects/site#2.png',base+'images/objects/site%232.png'],
	],
	map_bgfile:[
		['images/backgrounds/plain.gif',base+'images/backgrounds/plain.gif'],
		['images/backgrounds/world map.jpg',base+'images/backgrounds/world%20map.jpg'],
	],
};
for(const [id,rows] of Object.entries(expected)){
	const options=$('#'+id+' option').toArray();
	assert.deepEqual(options.map(o=>o.value),rows.map(r=>r[0]),id+' values keep the configuration paths in server order');
	options.forEach((option,i)=>{
		const imagesrc=option.getAttribute('data-imagesrc');
		assert.equal(w.wmEditorImageSrc(imagesrc),rows[i][1],id+' preview for '+option.value);
		// The server already sends the canonical encoding, so the browser resolves it unchanged.
		if(rows[i][1])assert.equal(rows[i][1],base+imagesrc,'server url is the preview path for '+option.value);
	});
}
assert.equal($('#map_bgfile').val(),'images/backgrounds/world map.jpg','selected background survives encoding');
w.wmNodeIconPicker();
const previews=$('.wm-icon-choice').toArray().map(b=>{const img=b.querySelector('img');return img?img.src:'';});
assert.deepEqual(previews,expected.node_iconfilename.map(r=>r[1]),'icon picker renders exactly the accepted previews');
assert.equal($('#node_iconfilename').val(),'images/objects/core router.png');
console.log('Editor image option previews PASS');
