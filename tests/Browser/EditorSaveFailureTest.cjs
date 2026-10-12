const {JSDOM}=require('jsdom'),jquery=require('jquery'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const dom=new JSDOM('<input id="action" value="set_map_properties"><input id="mapname" value="map.conf">',{runScripts:'outside-only'}),w=dom.window,$=jquery(w);w.$=w.jQuery=$;w.editor_url='/editor';w.wmEditorText={staleError:'Reload the editor',saveError:'Unable to save changes'};
let request,message,closed=0,refreshed=0;$.ajax=opts=>request=opts;w.alert=text=>message=text;w.hide_all_dialogs=()=>closed++;$.get=()=>refreshed++;
const source=fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');w.eval(source.slice(source.indexOf('function form_submit()'),source.indexOf('function map_properties()')));
w.form_submit();request.error();assert.equal(message,'Unable to save changes');assert.equal(closed,0);assert.equal(refreshed,0);assert.equal($('#action').val(),'set_map_properties');
w.WMConfigRevision='b'.repeat(64);w.form_submit();assert.match(request.data,/config_revision=b{64}/);request.error({status:409});assert.equal(message,'Reload the editor');assert.equal(closed,0);assert.equal(refreshed,0);
w.close();console.log('PASS: save failure reports retry and preserves the form');
