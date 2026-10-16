const {JSDOM}=require('jsdom'),jquery=require('jquery'),fs=require('node:fs'),assert=require('node:assert/strict');
const dom=new JSDOM('<select id="node_labelposition"><option value="N60">Top</option><option value="C">Middle</option><option value="S60">Bottom</option><option value="keep" hidden>Custom</option></select>',{runScripts:'outside-only'});
const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
const source=fs.readFileSync(require('node:path').join(__dirname,'../../js/editor.js'),'utf8');
w.eval(source.slice(source.indexOf('function wmPrimeNodeLabelPosition('),source.indexOf('function prime_node_form(')));
for(const [node,want] of [[{},'C'],[{labeloffset:'C'},'C'],[{labeloffset:'N60'},'N60'],[{labeloffset:'S60'},'S60'],[{labeloffset:'N'},'keep'],[{labeloffsetx:12,labeloffsety:-9},'keep']]){
 w.wmPrimeNodeLabelPosition(node);assert.equal($('#node_labelposition').val(),want);assert.equal($('#node_labelposition option[value=keep]').prop('hidden'),want!=='keep');
}
console.log('PASS: default/Top/Middle/Bottom and preservation of custom positions');w.close();
