const {JSDOM}=require('jsdom'),jquery=require('jquery'),assert=require('node:assert/strict');
const dom=new JSDOM('<input id="action" value="set_map_properties"><input id="mapname" value="map.conf">',{runScripts:'outside-only'}),w=dom.window,$=jquery(w);w.$=w.jQuery=$;require('./lib/editorScripts.cjs').loadEditorScripts(w,['editor.js']);w.editor_url='/editor';w.wmEditorText={staleError:'Reload the editor',saveError:'Unable to save changes'};
let request,message,closed=0,refreshed=0;$.ajax=opts=>request=opts;w.alert=text=>message=text;w.hide_all_dialogs=()=>closed++;$.get=()=>refreshed++;
w.form_submit();request.error();assert.equal(message,'Unable to save changes');assert.equal(closed,0);assert.equal(refreshed,0);assert.equal($('#action').val(),'set_map_properties');
w.WMConfigRevision='b'.repeat(64);w.form_submit();assert.match(request.data,/config_revision=b{64}/);request.error({status:409});assert.equal(message,'Reload the editor');assert.equal(closed,0);assert.equal(refreshed,0);
let reloads=0;w.hide_all_dialogs=()=>closed++;w.wmReloadEditor=()=>reloads++;$.get=()=>{throw Error('partial editor refresh');};request.success();assert.equal(reloads,1);
w.close();console.log('PASS: save failure reports retry and preserves the form');
