const {JSDOM, VirtualConsole} = require('jsdom');
const jquery = require('jquery');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

/**
 * Load a page from disk with its scripts and subresources enabled, recording load errors.
 *
 * @param {string} file Absolute path of the HTML page.
 * @returns {Promise<{dom: JSDOM, errors: string[]}>} The loaded page and any resource or script errors.
 */
async function loadPage(file) {
	const errors = [];
	const virtualConsole = new VirtualConsole();
	virtualConsole.on('jsdomError', (error) => errors.push(error.message));
	virtualConsole.on('error', (message) => errors.push(String(message)));
	const dom = await JSDOM.fromFile(file, {runScripts: 'dangerously', resources: 'usable', virtualConsole});
	await new Promise((resolve) => dom.window.addEventListener('load', resolve));
	return {dom, errors};
}

(async () => {
	const example = path.join(__dirname, '../../docs/example/example.html');
	const {dom, errors} = await loadPage(example);
	const doc = dom.window.document;
	const links = [...doc.querySelectorAll('area[id^="LINK:"]')];

	assert.equal(doc.querySelectorAll('script').length, 0, 'static example loads no script');
	assert.equal(typeof dom.window.overlib, 'undefined', 'overLIB is not defined');
	assert.ok(links.length > 0, 'example has link areas');
	for (const area of links) {
		assert.ok(area.title.trim() !== '', `${area.id} has a hover caption`);
		assert.deepEqual(area.getAttributeNames().filter((name) => name.startsWith('on')), [], `${area.id} has no inline handler`);
		area.dispatchEvent(new dom.window.MouseEvent('mouseover', {bubbles: true}));
		area.dispatchEvent(new dom.window.MouseEvent('mouseout', {bubbles: true}));
	}
	const image = doc.getElementById('wmapimage').getAttribute('src');
	assert.ok(fs.existsSync(path.join(path.dirname(example), image)), 'map image is shipped');
	assert.deepEqual(errors, [], 'page loads and hovers without errors');

	// HTMLSTYLE overlib output is now rendered by the viewer from data-hover, not by overLIB.
	const viewer = new JSDOM('<map><area id="LINK:L114:0" data-caption="sync-core"></map>', {runScripts: 'outside-only', url: 'https://cacti.example/cacti/plugins/weathermap/view.php'});
	const w = viewer.window;
	const $ = jquery(w);
	let options;
	w.$ = $;
	$.fn.tooltip = function (value) {
		if (value === 'instance') return undefined;
		options = value;
		return this;
	};
	vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/weathermap.js'), 'utf8'), viewer.getInternalVMContext());
	await new Promise((resolve) => $(resolve));
	await new Promise((resolve) => setImmediate(resolve));
	const area = $('area');
	area.attr('data-hover', Buffer.from("<div><img data-height='153' data-width='395' src='graph_image.png'></div>").toString('base64'));
	let output;
	options.content.call(area[0], (html) => (output = html));
	assert.equal($(output).find('img').attr('src'), 'graph_image.png');
	assert.match($(output).text(), /sync-core/);
	assert.equal(typeof w.overlib, 'undefined');
	console.log('Docs example hover captions and viewer hover content without overLIB PASS');
})().catch((error) => {
	console.error(error);
	process.exit(1);
});
