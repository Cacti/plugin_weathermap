const {JSDOM}=require('jsdom'),jquery=require('jquery'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const ids=['dlgNodeProperties','dlgLinkProperties','dlgMapProperties','dlgMapStyle'];
const dom=new JSDOM('<form id="frmMain"><input id="action" name="action"><input id="param" name="param"><input id="node_name" name="node_name" value="A"><input id="link_name" name="link_name" value="AB"><input id="map_background_colour" name="map_background_colour">'+ids.map(id=>`<div id="${id}" class="ui-dialog-content"><input class="focus-field"></div>`).join('')+'</form><div id="wm-map-background-colour"><input class="wm-colour-value" value="#ffffff"></div>',{runScripts:'outside-only'});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;Object.assign(w,{editor_url:'/editor',wmEditorText:{},WMNotes:{},mapmode:()=>{}});let request;$.ajax=opts=>request=opts;
const src=fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');w.eval(src.slice(src.indexOf('function form_submit('),src.indexOf('function map_properties(')));
for(const [button,action,param] of [['tb_node_submit','set_node_properties','A'],['tb_link_submit','set_link_properties','AB'],['tb_map_submit','set_map_properties'],['tb_mapstyle_submit','set_map_style']]) {
 $('#action').val('place_stamp');w.form_submit({currentTarget:{id:button}});const data=new URLSearchParams(request.data);assert.equal(data.get('action'),action);if(param)assert.equal(data.get('param'),param);
}
const start=src.indexOf("$('#frmMain').off('submit')"),end=src.indexOf('\n\t});',start)+6;w.eval(src.slice(start,end));
for(const [id,action] of ids.map((id,index)=>[id,['set_node_properties','set_link_properties','set_map_properties','set_map_style'][index]])) {
 $('#'+id+' input')[0].focus();$('#action').val('place_legend');$('#frmMain').trigger('submit');assert.equal(new URLSearchParams(request.data).get('action'),action);
}
$('#action').val('move_node');w.form_submit();assert.equal(new URLSearchParams(request.data).get('action'),'move_node');
dom.window.close();console.log('PASS: modeless dialog buttons and implicit form submission restore property actions');
