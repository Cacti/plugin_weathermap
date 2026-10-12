// Exercise note placement, default/edit values and actual save/error handlers.
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const fields=['action','param','node_name','note_name','note_text','note_x','note_y','note_colour','x','y','mapname'];
const dom=new JSDOM('<body>'+fields.map(id=>id==='note_text'?`<textarea id="${id}" name="${id}"></textarea>`:`<input id="${id}" name="${id}">`).join('')+'<select id="note_font"><option value="bundled:Vera.ttf:9">Vera Sans — 9 pt</option></select><select id="note_align"><option>left</option><option>centre</option><option>right</option></select><div id="tb_help"></div><div id="note_advanced"></div><div id="wm-note-error"></div>'+['tb_addnote','note_clone','note_move','note_delete','note_advanced_toggle'].map(id=>`<button id="${id}"></button>`).join('')+'<button class="wm-note-colour" data-colour="#000000"></button></body>',{runScripts:'outside-only',pretendToBeVisual:true});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
let mode,shown,moved,deleted,request;
Object.assign(w,{Nodes:{note1:{label:'Core\\nMaintenance',x:50,y:60}},WMNotes:{note1:{align:'centre',font:'bundled:Vera.ttf:9',colour:'#cc0000'}},wmEditorText:{advanced:'Advanced',hideAdvanced:'Hide',addNoteHelp:'Place a note',noteError:'Save failed'},editor_url:'/editor',hide_all_dialogs:()=>$('#action').val(''),mapmode:m=>mode=m,show_dialog:id=>shown=id,hide_dialog:()=>{},move_node:()=>moved=$('#node_name').val(),delete_node:()=>deleted=$('#node_name').val()});
$.fn.selectmenu=function(command){if(command==='refresh' && this.hasClass('select2-hidden-accessible'))throw Error('uninitialized selectmenu');return this;};
$('#note_align').addClass('select2-hidden-accessible');
const source=fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');
w.eval(source.slice(source.indexOf('// Notes are ordinary'),source.length));
w.eval(source.slice(source.indexOf('function form_submit('),source.indexOf('function map_properties(')));
w.eval(source.slice(source.indexOf("\t$('#tb_addnote')"),source.indexOf("\t$('#tb_poslegend')")));
$('#tb_addnote').trigger('click');assert.equal($('#action').val(),'place_note');assert.equal(mode,'xy');
$('#x').val('100');$('#y').val('120');w.form_submit();
assert.equal(shown,'dlgNoteProperties');assert.equal($('#note_font').val(),'bundled:Vera.ttf:9');assert.equal($('#note_colour').val(),'#000000');assert.equal($('#note_align').val(),'left');assert.equal($('#note_x').val(),'100');
$('#note_font').empty().append('<option value="10" data-wm-note-default="1">Translated Vera label (font 10)</option><option value="3">Builtin</option>');
w.show_note('',10,20);assert.equal($('#note_font').val(),'10');
w.show_note('note1');assert.equal($('#note_text').val(),'Core\nMaintenance');assert.equal($('#note_align').val(),'centre');
$('#note_x,#note_y').attr('max',600);$('#note_clone').trigger('click');assert.equal($('#note_name').val(),'');assert.equal($('#note_x').val(),'70');
w.show_note('note1');$('#note_move').trigger('click');assert.equal(moved,'note1');
w.show_note('note1');$('#note_delete').trigger('click');assert.equal(deleted,'note1');assert.equal($('#param').val(),'note1');
$.ajax=opts=>request=opts;w.show_note('note1');w.form_submit();
assert.equal(request.type,'POST');assert.match(request.data,/action=set_note_properties/);assert.match(request.data,/note_name=note1/);
request.error();assert.equal($('#wm-note-error').text(),'Save failed');assert.notEqual($('#wm-note-error').css('display'),'none');assert.equal($('#note_name').val(),'note1');
for(const action of ['move_node','delete_node']) {
 w.show_note('note1');$('#node_name,#param').val('note1');$('#action').val(action);w.form_submit();request.error();
 assert.equal(shown,'dlgNoteProperties');assert.equal($('#note_name').val(),'note1');assert.equal($('#wm-note-error').text(),'Save failed');
}
w.show_note('note1');w.form_submit();
let refreshed=false;$.get=(url,cb)=>{assert.match(url,/load_area_data/);cb('');};$.getScript=(url,cb)=>{assert.match(url,/load_map_javascript/);cb('');};w.initJS=()=>refreshed=true;
request.success('');assert.ok(refreshed);assert.equal($('#action').val(),'');
// Exercise the real note-aware confirmation, including attached-link warning and target capture.
w.eval(fs.readFileSync(path.join(process.env.CACTI_JS_DIR,'jquery-ui.js'),'utf8'));
Object.assign(w,{Links:{attached:{a:'note1',b:'other'}},delNodePrompt:'Delete %s from this map?',delNodeConnected:'Connected links to remove: %s.',delNodeKeepDevice:'The Cacti device and graphs will remain.',delNodeTitle:'Delete Node',txtCancel:'Cancel',txtDelNode:'Delete Node',wmNodeDisplayName:()=> 'Core maintenance',form_submit:()=>{deleted=$('#param').val();}});
Object.assign(w.wmEditorText,{noteDelete:'Delete note "%s"?',noteDeleteTitle:'Delete Note Confirmation',noteDeleteAction:'Delete Note'});
w.eval(source.slice(source.indexOf('function delete_node()'),source.indexOf('function clone_node()')));
$('#node_name').val('note1');w.delete_node();
assert.equal($('.dlgConfirm').dialog('option','modal'),true);
assert.equal($('.dlgConfirm').dialog('option','title'),'Delete Note Confirmation');
assert.match($('.dlgConfirm').text(),/Delete note "Core maintenance"/);
assert.match($('.dlgConfirm').text(),/Connected links to remove: 1/);
assert.doesNotMatch($('.dlgConfirm').text(),/Cacti device/);
$('#param').val('other_node');$('.dlgConfirm').dialog('option','buttons')[1].click.call($('.dlgConfirm')[0]);assert.equal(deleted,'note1');
dom.window.close();console.log('PASS: note placement, defaults, edit, clone, move, guarded delete, save/error and refresh');
