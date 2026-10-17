const {JSDOM}=require('jsdom'), jquery=require('jquery'), fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const dom=new JSDOM('<input id="action"><input id="mapname" value="test.conf"><div id="dlgLinkProperties"><input id="unsaved" value="draft"><a id="link_edit">Edit</a></div><div id="dlgNodeProperties"><a id="node_edit">Edit</a></div><div id="dlgTextEdit"><textarea id="item_configtext"></textarea><button id="tb_textedit_cancel">Cancel</button></div>',{runScripts:'outside-only',pretendToBeVisual:true});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
w.eval(fs.readFileSync(path.join(process.env.CACTI_JS_DIR,'jquery-ui.js'),'utf8'));
const source=fs.readFileSync(path.join(__dirname,'../../js/editor.js'),'utf8');
w.eval(source.slice(source.indexOf('function cancel_op('),source.indexOf('function help_handler(')));
w.eval(source.slice(source.indexOf('function show_itemtext('),source.indexOf('function prime_node_form(')));
w.eval(source.slice(source.indexOf('function show_dialog('),source.indexOf('function coord_capture(')));
w.mapmode=()=>{};w.wmResetActionHelp=()=>{};w.editor_url='/editor';$.ajax=()=>{};
$('#tb_textedit_cancel').on('click',w.cancel_op);
for(const [type,parent,action,edit] of [['link','dlgLinkProperties','set_link_properties','link_edit'],['node','dlgNodeProperties','set_node_properties','node_edit']]){
 w.hide_all_dialogs();w.show_dialog(parent);w.show_itemtext(type,'test');
 assert.equal($('#'+parent).dialog('isOpen'),false);
 $('#item_configtext').val('discard this config draft');
 $('#tb_textedit_cancel').trigger('click');
 assert.equal($('#dlgTextEdit').dialog('isOpen'),false);
 assert.equal($('#'+parent).dialog('isOpen'),true);
 assert.equal($('#action').val(),action);
 assert.equal(w.document.activeElement.id,edit);
 assert.equal($('#unsaved').val(),'draft');
 // Title-bar close and Escape use this same close callback.
 w.show_itemtext(type,'test');$('#dlgTextEdit').dialog('close');
 assert.equal($('#'+parent).dialog('isOpen'),true);
 w.show_itemtext(type,'test');w.hide_all_dialogs();
 assert.equal($('#'+parent).dialog('isOpen'),false,'Navigation must not reopen the parent');
}
w.show_itemtext('link','test');$('#tb_textedit_cancel').trigger('click');
assert.equal($('#dlgLinkProperties').dialog('isOpen'),false,'Direct configuration editing has no parent to restore');
console.log('PASS: Cancel and close return to properties, preserve drafts and action, while navigation stays closed');
w.close();
