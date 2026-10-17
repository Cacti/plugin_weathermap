const { JSDOM } = require('jsdom'),
	jquery = require('jquery'),
	fs = require('fs'),
	vm = require('vm'),
	assert = require('node:assert/strict');
(async () => {
	const dom = new JSDOM('<map><area data-caption="node0123-node0456"></map>', { runScripts: 'outside-only', url:'https://cacti.example/cacti/plugins/weathermap/view.php' }),
		w = dom.window,
		$ = jquery(w);
	w.$ = $;
	let opts;
	$.fn.tooltip = function (options) {
		if (options === 'instance') return undefined;
		opts = options;
		return this;
	};
	vm.runInContext(
		fs.readFileSync(require('node:path').join(__dirname, '../../js/weathermap.js'), 'utf8'),
		dom.getInternalVMContext()
	);
	await new Promise((r) => $(r));
	await new Promise((r) => setImmediate(r));
	const area = $('area');
	area.attr(
		'data-hover',
		Buffer.from('<div><img src="/graph.png" data-width="300" data-height="100"></div>').toString('base64')
	);
	let output;
	opts.content.call(area[0], (html) => (output = html));
	const content = $(output);
	assert.equal(content.children().not('.wmcontent').length, 0);
	assert.equal(content.find('img')[0].style.width, 'auto');
	assert.equal(content.find('img')[0].style.height, 'auto');
	assert.equal(content.find('img').css('max-width'), '330px');
	area.attr('data-caption', 'Uplink <example>');
	opts.content.call(area[0], (html) => (output = html));
	assert.equal($(output).children().not('.wmcontent').text(), 'Uplink <example>');
	assert.equal($(output).find('example').length, 0);
	area.attr('data-hover', Buffer.from('<div><img src="/cacti/graph_image.php?local_graph_id=108&amp;graph_width=300"><img src="/cacti/graph_image.php?local_graph_id=159"><img src="https://external.example/graph_image.php?local_graph_id=9"></div>').toString('base64'));
	opts.content.call(area[0], html => output=html);
	assert.deepEqual($(output).find('a').map(function(){return $(this).attr('href');}).get(), ['/cacti/graph.php?rra_id=all&local_graph_id=108','/cacti/graph.php?rra_id=all&local_graph_id=159']);
	assert.equal($(output).find('a img').length,2);
	assert.equal(w.wmHoverGraphDestination('/cacti/graph_image.php?local_graph_id=-1'),null);
	assert.equal(w.wmHoverGraphDestination('javascript:alert(1)'),null);
	assert.equal(opts.show, false);
	assert.equal(opts.hide, false);
	const tip = $('<div class="ui-tooltip"></div>').appendTo('body');
	opts.close({}, { tooltip: tip });
	assert.equal($('.ui-tooltip').length, 0);
	console.log(
		'PASS: aspect ratio, modest size increase, generated caption suppression, caption escaping and immediate tooltip removal'
	);
	w.close();
})().catch((e) => {
	console.error(e);
	process.exitCode = 1;
});
