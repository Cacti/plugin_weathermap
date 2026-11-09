// Compact link and node property editors for the map editor; loaded after editor.js.

// Compact link workflow; custom configuration fields remain submitted unchanged.
function wmLinkHoverEntries(value) {
	return String(value || '').trim().split(/\s+/).filter(Boolean);
}
function wmLinkCompactHoverUrl(value) {
	if (!wmLocalGraphIds(value, ['graph_image.php']).length) return value;
	var root = new URL('../../', window.location.href);
	var url = new URL(value, /^graph_image\.php/.test(value) ? root : window.location.href);
	[['rra_id', '0'], ['graph_nolegend', 'true'], ['graph_height', '100'], ['graph_width', '300']].forEach(function(pair) {
		if (!url.searchParams.has(pair[0])) url.searchParams.set(pair[0], pair[1]);
	});
	return url.pathname + url.search + url.hash;
}
function wmSyncLinkHoverParts() {
	if (!$('#wm-link-extra-hover').length) return;
	var main = $('#wm-link-primary-hover').val();
	var entries = wmLinkHoverEntries(main).concat(wmLinkHoverEntries($('#wm-link-extra-hover').val()));
	$('#link_hover').val(Array.from(new Set(entries)).join(' '));
	wmRenderLinkExtraGraphs();
}
function wmRenderLinkExtraGraphs() {
 var list = $('#wm-link-extra-list');
 if (!list.length) return;
 list.empty();
 var count = wmLinkHoverEntries($('#wm-link-extra-hover').val()).length;
 $('#link_picker_add').prop('disabled', count >= 1);
 $('#wm-link-hover-status').text(count >= 1 ? wmEditorText.hoverLimit : '');
 var table = $('<table class="cactiTable"></table>').appendTo(list);
 wmLinkHoverEntries($('#wm-link-extra-hover').val()).forEach(function(url) {
  var row = $('<tr></tr>').appendTo(table);
  $('<td></td>').text(wmEditorText.additionalGraph).appendTo(row);
  var item = $('<div class="wm-link-extra-item"></div>').appendTo($('<td></td>').appendTo(row));
  var details = $('<div class="wm-link-extra-details"></div>').appendTo(item);
  var ids = wmLocalGraphIds(url, ['graph_image.php']);
  var label = $('<div class="wm-link-extra-name"></div>').appendTo(details);
  var files = $('<small class="wm-link-extra-rrds"></small>').appendTo(details);
  item.attr('data-hover-url', url);
  if (ids.length) {
   var selected = String($('#link_picker').val()) === ids[0] ? $('#link_picker_input').val() : '';
   label.text(selected || wmEditorText.currentGraph.replace('%s', ids[0]));
   var choice = $('#link_picker_input').data('wm-graph-choice');
   if (choice && String(choice.id) === ids[0]) files.text((choice.rrds || []).join(', ') || wmEditorText.noGraphRrd);
  } else label.text(wmEditorText.customHoverGraph);
  $('<button type="button" class="ui-button ui-corner-all"></button>').text(wmEditorText.removeHoverGraph)
   .appendTo(item).on('click', function() {
    $('#wm-link-extra-hover').val(wmLinkHoverEntries($('#wm-link-extra-hover').val()).filter(function(entry) { return entry !== url; }).join('\n'));
    wmSyncLinkHoverParts();
   });
 });
}
function wmInitLinkHoverParts() {
	if (!$('#wm-link-extra-hover').length) return;
	var entries = wmLinkHoverEntries($('#link_hover').val());
	$('#wm-link-primary-hover').val(entries.shift() || '');
	$('#wm-link-extra-hover').val(entries.join('\n')).off('.wmHovers').on('input.wmHovers change.wmHovers', wmSyncLinkHoverParts);
	wmSyncLinkHoverParts();
}
function wmSetLinkPrimaryHover(value) {
	if (!$('#wm-link-extra-hover').length) {
		$('#link_hover').val(value);
		return;
	}
	$('#wm-link-primary-hover').val(value);
	var ids = wmLocalGraphIds(value, ['graph_image.php']);
	var extras = wmLinkHoverEntries($('#wm-link-extra-hover').val()).filter(function(entry) {
		return !ids.some(function(id) { return wmLocalGraphIds(entry, ['graph_image.php']).includes(id); });
	});
	$('#wm-link-extra-hover').val(extras.join('\n'));
	wmSyncLinkHoverParts();
}
function wmPromoteLinkTrafficHover(graphId) {
	if (!$('#wm-link-extra-hover').length || !/^[1-9][0-9]*$/.test(String(graphId))) return;
	var main = $('#wm-link-primary-hover').val();
	var extras = wmLinkHoverEntries($('#wm-link-extra-hover').val());
	if (!main && !extras.length) return;
	if (wmLocalGraphIds(main, ['graph_image.php']).includes(String(graphId))) return;
	var traffic = extras.find(function(entry) {
		return wmLocalGraphIds(entry, ['graph_image.php']).includes(String(graphId));
	});
	if (!traffic) return;
	if (main) extras.unshift(main);
	$('#wm-link-extra-hover').val(extras.join('\n'));
	wmSetLinkPrimaryHover(traffic);
	wmRestoreLinkGraphChoice(true);
}
function wmSetLinkExtraHover(value, replace) {
	var field = $('#wm-link-extra-hover');
	if (!field.length) field = $('#link_hover');
	var ids = wmLocalGraphIds(value, ['graph_image.php']);
	var primary = wmLocalGraphIds($('#wm-link-primary-hover').val() || '', ['graph_image.php']);
	if (ids.some(function(id) { return primary.includes(id); })) return;
	var entries = replace ? [] : wmLinkHoverEntries(field.val());
	if (!entries.some(function(entry) {
		return entry === value || ids.some(function(id) { return wmLocalGraphIds(entry, ['graph_image.php']).includes(id); });
	})) {
		if (entries.length >= 1) {
			$('#wm-link-hover-status').text(wmEditorText.hoverLimit);
			return;
		}
		entries.push(value);
	}
	field.val(entries.join('\n'));
	wmSyncLinkHoverParts();
}

// Compare the effective RRD inputs without conflating custom data sources or scaling.
function wmLinkTrafficIdentity(source) {
 var defaults = typeof wmRrdSourceDefaults === 'undefined' ? {} : wmRrdSourceDefaults;
 var file = String(source), input = defaults.input || 'traffic_in', output = defaults.output || 'traffic_out', scale = 8, multiplier = 1;
 if (file.startsWith('-')) { multiplier = -1; file = file.slice(1); }
 var outer = file.match(/^([0-9]+(?:\.[0-9]*)?)\*(.*)$/);
 if (outer) { multiplier *= Number(outer[1]); file = outer[2]; }
 var ds = file.match(/^(.*\.rrd):([-a-zA-Z0-9_]+):([-a-zA-Z0-9_]+)$/);
 if (ds) { file = ds[1]; input = ds[2]; output = ds[3]; }
 file = file.replace(/^rrd:/, '');
 if (file.startsWith('gauge:')) { scale = 1; file = file.slice(6); }
 if (file.startsWith('scale:')) {
  var separator = file.indexOf(':', 6), value = file.slice(6, separator);
  if (separator < 0 || !/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/.test(value)) return null;
  scale = Number(value);
  if (!Number.isFinite(scale)) return null;
  file = file.slice(separator + 1);
 }
 if (!/\.rrd$/.test(file)) return null;
 if (!/^[/.]/.test(file) && defaults.path) file = defaults.path + '/' + file;
 if (!file.startsWith('/') && defaults.rraPath) file = defaults.rraPath + '/' + file;
 file = (file.startsWith('/') ? '/' : '') + file.split('/').filter(function(part) { return part !== '' && part !== '.'; }).join('/');
 scale *= multiplier;
 if (!Number.isFinite(scale)) return null;
 return JSON.stringify([file, input, output, scale]);
}
function wmLinkSameTrafficSource(left, right) {
 if (left === right) return true;
 var key = wmLinkTrafficIdentity(left);
 return key !== null && key === wmLinkTrafficIdentity(right);
}

function wmRenderLinkTrafficSources() {
 var list = $('#wm-link-measured-sources');
 if (!list.length) return;
 var entries = wmLinkHoverEntries($('#link_target').val());
 list.empty();
 if (!$('#wm-link-source-count').length) $('<small id="wm-link-source-count" style="display:block"></small>').insertAfter('#wm-link-current');
 $('#wm-link-source-count').text(entries.length > 1 ? wmEditorText.combinedSources.replace('%s', String(entries.length)) : '');
 $('#wm-link-rrd').text((entries[0] || '').split('/').pop());
 if (!entries.length) $('<small></small>').text(wmEditorText.noMeasuredSources).appendTo(list);
 entries.forEach(function(source, index) {
  var row = $('<div class="wm-link-source-item"></div>').appendTo(list);
  $('<code></code>').text(source).appendTo(row);
  if (index === 0) {
   $('<small></small>').text(wmEditorText.primarySource).appendTo(row);
   return;
  }
  $('<button type="button" class="ui-button ui-corner-all ui-widget"></button>')
   .text(wmEditorText.removeSource).attr('aria-label', wmEditorText.removeSource + ': ' + source)
   .appendTo(row).on('click', function() {
    var current = wmLinkHoverEntries($('#link_target').val());
    if (current.join(' ') !== entries.join(' ')) { wmRenderLinkTrafficSources(); return; }
    current.splice(index, 1);
    $('#link_target').val(current.join(' '));
    wmRenderLinkTrafficSources();
    $('#wm-link-source-status').text(wmEditorText.sourceRemoved);
    var next = list.find('button').eq(Math.min(index - 1, current.length - 2));
    if (next.length) next.trigger('focus');
    else $('#link_target').trigger('focus');
   });
 });
}

function wmApplyLinkTrafficSource() {
 var entries = wmLinkHoverEntries($('#link_target').val());
 if (!entries.length) {
  $('#wm-link-source-status').text(wmEditorText.choosePrimaryFirst);
  return;
 }
 var item = $('#wm-link-source-picker').data('wm-choice');
 if (!item || !item.id || !(item.local_graph_id > 0)) {
  $('#wm-link-source-status').text(wmEditorText.chooseSourceFirst);
  return;
 }
 var duplicate = entries.some(function(source) { return wmLinkSameTrafficSource(source, String(item.id)); });
 if (!duplicate) entries.push(String(item.id));
 $('#link_target').val(entries.join(' '));
 wmRenderLinkTrafficSources();
 $('#wm-link-source-status').text(duplicate ? wmEditorText.sourceAlreadyAdded : wmEditorText.sourceAdded);
}
function wmInitLinkSourcePicker(container) {
 var picker = $('<input type="text" id="wm-link-source-picker" class="ui-state-default ui-corner-all">')
  .attr({'aria-label': wmEditorText.chooseSource, placeholder: wmEditorText.searchInterface}).prependTo(container);
 var offset = 0, term = '', generation = 0;
 var buttons = $('#link_target_picker_add');
 picker.autocomplete({
  minLength: 0, autoFocus: false,
  source: function(request, response) {
   if (request.term !== term) offset = 0;
   term = request.term;
   var ticket = ++generation, link = $('#link_name').val(), map = $('#mapname').val();
   $.getJSON(window.location.pathname, {action:'datasources', target:'link_target_picker', mapname:map, graph_template_id:-1, paged:1, offset:offset, term:term})
    .done(function(data) {
     if (ticket !== generation || $('#link_name').val() !== link || $('#mapname').val() !== map) return response([]);
     var items = data.items.slice();
     if (data.offset > 0) items.unshift({label:data.previous_label, value:term, wmOffset:Math.max(0,data.offset-100)});
     if (data.next_offset != null) items.push({label:data.next_label, value:term, wmOffset:data.next_offset});
     response(items);
    }).fail(function() { response([]); });
  },
  focus: function(event, ui) { if (ui.item.wmOffset !== undefined) return false; },
  select: function(event, ui) {
   if (ui.item.wmOffset !== undefined) {
    offset = ui.item.wmOffset;
    setTimeout(function() { picker.autocomplete('search', term); }, 0);
    return false;
   }
   var valid = !!(ui.item.id && ui.item.local_graph_id > 0);
   picker.val(valid ? ui.item.label : '').data('wm-choice', valid ? ui.item : null);
   buttons.prop('disabled', !valid);
   $('#wm-link-source-status').text(valid ? wmEditorText.sourceReady : wmEditorText.chooseSourceFirst);
   return false;
  }
 });
 picker.on('input.wmSource', function() {
  picker.removeData('wm-choice'); buttons.prop('disabled', true); $('#wm-link-source-status').text(wmEditorText.chooseSourceFirst);
 }).on('focus.wmSource', function() { picker.autocomplete('search', picker.data('wm-choice') ? '' : picker.val()); });
}

var wmLinkGraphRestoreGeneration = 0;
function wmRestoreLinkGraphChoice(preserveSelection) {
 var picker = $('#link_picker_input');
 if (!picker.length) return;
 var ticket = ++wmLinkGraphRestoreGeneration;
 var extras = String($('#wm-link-extra-hover').val() || ''), link = $('#link_name').val();
 var automatic = !picker.val() || (String($('#link_picker').val()) === String(picker.data('wm-restored-graph')) && picker.val() === picker.data('wm-restored-label'));
 if (!preserveSelection || automatic) {
  $('#link_picker').val('');
  picker.val('').removeData('wm-graph-choice').removeData('wm-restored-graph').attr('placeholder', wmEditorText.searchGraph);
 }
 var selection = picker.val();
 var entries = wmLinkHoverEntries(extras);
 var ids = entries.length === 1 ? wmLocalGraphIds(entries[0], ['graph_image.php']) : [];
 if (ids.length !== 1) return;
 wmGetGraphSummaries(ids).done(function(items) {
  if (ticket !== wmLinkGraphRestoreGeneration || $('#link_name').val() !== link || String($('#wm-link-extra-hover').val() || '') !== extras) return;
  var item = items.find(function(item) { return String(item.id) === ids[0]; });
  if (!item) return;
  if ((!preserveSelection || automatic) && picker.val() === selection) {
   $('#link_picker').val(String(item.id));
   picker.val(item.label).data('wm-graph-choice', item).data('wm-restored-graph', String(item.id)).data('wm-restored-label', item.label);
  }
  var row = $('#wm-link-extra-list .wm-link-extra-item').filter(function() { return $(this).attr('data-hover-url') === entries[0]; });
  row.find('.wm-link-extra-name').text(item.label);
  row.find('.wm-link-extra-rrds').text((item.rrds || []).join(', ') || wmEditorText.noGraphRrd);
 });
}

// Keep advanced link tasks separate without duplicating submitted fields or pickers.
function wmBuildLinkSections() {
 var dlg = $('#dlgLinkProperties');
 if ($('#wm-link-sections').length) return;
 var body = dlg.find('.dlgBody');
 if (!body.length) body = dlg;
 var sections = $('<div id="wm-link-sections"></div>').appendTo(body);
 var controls = $('<div class="wm-link-section-buttons"></div>').appendTo(sections);
 function group(id, title, fields, hint) {
  var panel = $('<div class="wm-link-group" role="region" hidden></div>')
   .attr({id:id, 'aria-labelledby':id+'-button'}).appendTo(sections);
  $('<button type="button" class="ui-button ui-corner-all ui-widget wm-link-section-toggle" aria-expanded="false"></button>')
   .attr({id:id+'-button', 'aria-controls':id}).text(title).appendTo(controls)
   .on('click', function() {
    var open = $(this).attr('aria-expanded') !== 'true';
    controls.find('.wm-link-section-toggle').attr('aria-expanded', 'false').removeClass('ui-state-active');
    sections.find('.wm-link-group').prop('hidden', true);
    $(this).attr('aria-expanded', String(open)).toggleClass('ui-state-active', open);
    panel.prop('hidden', !open);
    if (dlg.dialog('instance')) dlg.dialog('option', 'position', {my:'center', at:'center', of:window});
   });
  if (hint) $('<p class="wm-link-group-hint"></p>').text(hint).appendTo(panel);
  var table = $('<table class="cactiTable"></table>').appendTo(panel);
  dlg.find(fields).closest('tr').appendTo(table);
  return panel;
 }
 $('#link_labelposmode').on('change.wmPositions', function() {
  $('#link_labelposin,#link_labelposout').prop('disabled', $(this).val() !== 'custom');
 });
 var hovers = group('wm-link-hovers', wmEditorText.additionalGraphs, '#link_template,#link_picker');
 dlg.find('.wm-link-section p').addClass('wm-link-group-hint').prependTo(hovers);
 dlg.find('.wm-link-section').remove();
 $('<div class="wm-link-hover-actions"></div>').append($('#link_picker_add,#link_picker_rep')).appendTo($('#link_picker').closest('td'));
 $('<p id="wm-link-hover-status" role="status" aria-live="polite" class="wm-link-group-hint"></p>').appendTo($('#link_picker').closest('td'));
 $('<div id="wm-link-extra-list"></div>').appendTo(hovers);
 wmRenderLinkExtraGraphs();
 $('#link_target_picker_rep').remove();
 var advanced = group('wm-link-advanced', wmEditorText.advanced,
  '#link_labelposmode,#link_target,#link_infourl,#wm-link-primary-hover,#wm-link-extra-hover', wmEditorText.linkAdvancedHint);
 $('#link_labelposmode').closest('tr').prependTo(advanced.find('table'));
 var sourceRow = $('<tr></tr>').insertBefore($('#link_target').closest('tr'));
 $('<td></td>').text(wmEditorText.chooseSource).appendTo(sourceRow);
 var sourceActions = $('<div class="wm-link-source-actions"></div>').appendTo($('<td></td>').appendTo(sourceRow))
  .append($('#link_target_picker_add'));
 wmInitLinkSourcePicker(sourceActions);
 $('<small class="wm-link-field-hint"></small>').text(wmEditorText.trafficSourcesHint).appendTo(sourceActions.parent());
 $('<p id="wm-link-source-status" role="status" aria-live="polite" aria-atomic="true" class="wm-link-group-hint"></p>').insertBefore('#link_target');
 $('<div id="wm-link-measured-sources"></div>').attr('aria-label', wmEditorText.trafficSources).insertBefore('#link_target');
 $('#link_target').off('.wmExpert').on('input.wmExpert', function() {
  ++wmInterfaceLookupGeneration;
  $('#wm-link-current').text(wmEditorText.customInterface);
  $('#wm-link-graph').text(wmEditorText.customConfig);
  $('#link_target_picker').val('').removeData('wm-choice');
  $('#link_target_picker_input').val('');
  $('#wm-link-use').prop('disabled', true);
  wmRenderLinkTrafficSources();
  $('#wm-link-source-status').text(wmEditorText.customSourcesChanged);
 });
 $('#link_infourl,#wm-link-primary-hover,#wm-link-extra-hover').off('.wmExpert').on('input.wmExpert change.wmExpert', function() {
  ++wmInterfaceLookupGeneration;
  wmSyncLinkHoverParts();
 });
 dlg.find('#viastyle,#link_width,#link_commentin,#link_commentout,#link_commentstyle').closest('tr')
  .insertAfter($('#link_target_picker').closest('tr'));
 $('#link_edit').appendTo(controls);
 var help = dlg.find('.dlgHelp').attr('id', 'wm-link-full-help').insertAfter(sections).hide();
 $('<button type="button" id="wm-link-help-button" class="ui-button ui-corner-all" aria-expanded="false" aria-controls="wm-link-full-help">?</button>')
  .attr({'aria-label': wmEditorText.linkHelp, title: wmEditorText.linkHelp}).appendTo(controls)
  .on('click', function() {
   var open = $(this).attr('aria-expanded') !== 'true';
   $(this).attr('aria-expanded', String(open));
   help.toggle(open);
   if (dlg.dialog('instance')) dlg.dialog('option', 'position', {my:'center', at:'center', of:window});
  });
 function hint(id, field, text) {
  $('<small class="wm-link-field-hint"></small>').attr('id', id).text(text).appendTo(dlg.find(field).closest('td'));
  var input = dlg.find(field), described = String(input.attr('aria-describedby') || '').split(/\s+/).filter(Boolean);
  if (!described.includes(id)) described.push(id);
  input.attr('aria-describedby', described.join(' '));
 }
 hint('wm-link-bandwidth-hint', '#link_bandwidth_in', wmEditorText.bandwidthHint);
 hint('wm-link-comments-hint', '#link_commentout', wmEditorText.commentsHint);
 hint('wm-link-destination-hint', '#link_infourl', wmEditorText.clickDestinationHint);
 hint('wm-link-extra-url-hint', '#wm-link-extra-hover', wmEditorText.extraUrlsHint);
}

function compactLinkEditorBase() {
	wmInitLinkHoverParts();
	var dlg = $('#dlgLinkProperties'),
		table = $('#link_bandwidth_in').closest('table');
	wmBuildLinkSections();
	wmRenderLinkTrafficSources();
	wmRestoreLinkGraphChoice();
	$('#wm-link-source-status').text(wmEditorText.chooseSourceFirst);
	$('#wm-link-source-picker').val('').removeData('wm-choice');
	$('#link_target_picker_add').prop('disabled', true);
	$('#wm-link-full-help').hide();
	$('#wm-link-help-button').attr('aria-expanded', 'false');
	$('#wm-link-primary-hover').prop('readonly', false).next('small').text(wmEditorText.trafficHoverHint);
	if (!$('#wm-link-summary').length) {
		$(
			'<tr id="wm-link-summary"><td></td><td><div id="wm-link-current"></div><small id="wm-link-graph"></small></td></tr>'
		).prependTo(table).find('td:first').text(wmEditorText.currentInterface);
		$(
			'<button type="button" id="wm-link-use" class="ui-button ui-corner-all" disabled></button>'
		)
			.text(wmEditorText.useInterface)
			.appendTo($('#link_target_picker').closest('td'))
			.on('click', function () {
				var item = $('#link_target_picker').data('wm-choice');
				if (!item || !item.id || !(item.local_graph_id > 0)) return;
				++wmInterfaceLookupGeneration;
				var extras = wmLinkHoverEntries($('#link_target').val()).slice(1).filter(function(source) { return !wmLinkSameTrafficSource(source, String(item.id)); });
				$('#link_target').val([String(item.id)].concat(extras).join(' '));
				wmRenderLinkTrafficSources();
				var destination = new URL(infoUrlTarget + item.local_graph_id, new URL('../../', window.location.href));
				$('#link_infourl').val(destination.pathname + destination.search + destination.hash);
				wmSetLinkPrimaryHover(
					new URL('../../', window.location.href).pathname +
						'graph_image.php?local_graph_id=' +
						item.local_graph_id +
						'&rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300'
				);
				$('#wm-link-current').text(item.label);
				$('#wm-link-graph').text(
					wmEditorText.graphSelected.replace('%s', String(item.local_graph_id))
				);
				$(this).prop('disabled', true);
			});
	}
	$('#wm-link-graph').prependTo('#wm-link-advanced');
	wmRenderLinkTrafficSources();
	$('#wm-link-sections').show().find('.wm-link-group').prop('hidden', true);
	$('#wm-link-sections .wm-link-section-toggle').attr('aria-expanded', 'false').removeClass('ui-state-active');
	$('#link_edit').show();
	$('#link_target_picker_add').show();
	$('#link_target_picker').closest('tr').find('td:first').text(wmEditorText.changeInterface);
	$('#link_target_picker_input').val('').attr('placeholder', wmEditorText.searchInterface);
	$('#link_target_picker').val('').removeData('wm-choice');
	local_graph_id = 0;
	$('#wm-link-use').prop('disabled', true);
	var picker = $('#link_target_picker_input');
	if (!picker.data('wm-compact-hook')) {
		var original = picker.autocomplete('option', 'select');
		picker
			.autocomplete('option', 'select', function (event, ui) {
				if (original && original.call(this, event, ui) === false) return false;
				var valid = !!(ui.item.id && ui.item.local_graph_id > 0);
				$('#link_target_picker').val(valid ? ui.item.id : '').data('wm-choice', valid ? ui.item : null);
				local_graph_id = valid ? ui.item.local_graph_id : 0;
				$('#wm-link-use').prop('disabled', !valid);
			})
			.on('input.wmCompact', function () {
				$('#link_target_picker').val('').removeData('wm-choice');
				local_graph_id = 0;
				$('#wm-link-use').prop('disabled', true);
			});
		picker.data('wm-compact-hook', true);
	}
	var target = String($('#link_target').val() || '').trim();
	$('#wm-link-current').text(target ? target.split('/').pop() : wmEditorText.noInterface);
	var ids = String($('#link_infourl').val() || '').match(/(?:local_graph_id|graph_list)=([0-9,]+)/);
	$('#wm-link-graph').text(
		ids
			? wmEditorText.currentGraph.replace('%s', ids[1])
			: target
				? wmEditorText.customConfig
				: wmEditorText.chooseInterface
	);
	$('#link_bandwidth_in')
		.closest('tr')
		.find('td:first')
		.text(wmEditorText.bandwidthIn.replace('%s', function() { return $('#link_nodename1').text(); }));
	$('#link_bandwidth_out')
		.closest('tr')
		.find('td:first')
		.text(wmEditorText.bandwidthOut.replace('%s', function() { return $('#link_nodename1').text(); }));
}

var wmInterfaceSummaries = {};
var wmInterfaceLookupGeneration = 0;
function wmGetInterfaceSummary(graphIds) {
	var key = $('#mapname').val() + ':' + graphIds.join(',');
	if (!wmInterfaceSummaries[key]) {
		wmInterfaceSummaries[key] = $.getJSON(window.location.pathname, {
			action: 'datasources',
			term: '',
			mapname: $('#mapname').val(),
			target: 'link_target_picker',
			graph_template_id: -1,
			paged: 1,
			graph_ids: graphIds.join(',')
		});
		wmInterfaceSummaries[key].fail(function () {
			delete wmInterfaceSummaries[key];
		});
	}
	return wmInterfaceSummaries[key];
}
function wmGetInterfaceCandidates(graphIds) {
	var result = $.Deferred();
	var unique = Array.from(new Set(graphIds));
	var items = [];
	var successes = 0;
	function next(offset) {
		if (offset >= unique.length) {
			if (successes || !unique.length) result.resolve({items: items});
			else result.reject();
			return;
		}
		wmGetInterfaceSummary(unique.slice(offset, offset + 100)).done(function(data) {
			items = items.concat(data.items);
			successes++;
		}).always(function() { next(offset + 100); });
	}
	next(0);
	return result.promise();
}
function compactLinkEditor() {
	var generation = ++wmInterfaceLookupGeneration;
	compactLinkEditorBase();
	var dlg = $('#dlgLinkProperties');
	if (!$('#wm-link-rrd').length)
		$('<small id="wm-link-rrd" style="display:block;overflow-wrap:anywhere;color:#666"></small>').insertAfter(
			'#wm-link-current'
		);

	var buttons = dlg.find('.dlgSubButtons');
	$('#tb_link_submit').prependTo(buttons).css({ 'font-weight': 'bold' });
	$('#tb_link_cancel').insertAfter('#tb_link_submit');
	if (!$('#wm-link-delete-bar').length)
		$('<div id="wm-link-delete-bar" style="text-align:right;margin-bottom:8px"></div>').insertBefore(
			$('#link_nodename1').parent()
		);
	$('#link_delete').appendTo('#wm-link-delete-bar').css({ 'margin-left': '0', color: '#9b2525' });
	$('#link_tidy').show().insertBefore('#tb_link_submit');
	$('#link_via').show().insertAfter('#link_tidy').css('margin-right', '24px');
	var picker = $('#link_target_picker_input');
	picker.attr('placeholder', wmEditorText.browseInterface);
	picker.autocomplete('option', 'autoFocus', false);
	$('#link_target_picker_wrap').off('click dblclick mouseleave');
	picker.off('click.wmReopen').on('click.wmReopen', function () {
		picker.autocomplete('search', '');
	});
	$('#link_target_picker_click')
		.off('click.wmReopen')
		.on('click.wmReopen', function () {
			picker.trigger('focus');
			picker.autocomplete('search', '');
		});
	if (!$('#wm-link-browse').length)
		$('<button type="button" id="wm-link-browse" class="ui-button ui-corner-all"></button>')
			.text(wmEditorText.browseAll)
			.insertAfter('#wm-link-use')
			.on('click', function () {
				picker.trigger('focus');
				picker.autocomplete('search', '');
			});
	if (!$('#wm-link-apply-hint').length)
		$('<p id="wm-link-apply-hint" style="margin: 0 0 6px;">').text(wmEditorText.applyInterfaceHint)
			.prependTo(picker.closest('td'));
	var describedBy = String(picker.attr('aria-describedby') || '').split(/\s+/).filter(Boolean);
	if (describedBy.indexOf('wm-link-apply-hint') === -1) describedBy.push('wm-link-apply-hint');
	picker.attr('aria-describedby', describedBy.join(' '));
	var target = String($('#link_target').val() || '').trim();
	var primary = wmLinkHoverEntries(target)[0] || '';
	var file = primary.split('/').pop();
	$('#wm-link-rrd').text(primary.split('/').pop());
	$('#wm-link-current').text(target ? wmEditorText.lookingUp : wmEditorText.noInterface);

	var graphIds = wmLocalGraphIds(String($('#link_infourl').val() || ''), ['graph.php', 'graph_view.php']);
	String($('#link_hover').val() || '').trim().split(/\s+/).forEach(function(entry) {
		graphIds = graphIds.concat(wmLocalGraphIds(entry, ['graph_image.php']));
	});
	graphIds = Array.from(new Set(graphIds));
	if (!graphIds.length) {
		$('#wm-link-current').text(target ? wmEditorText.customInterface : wmEditorText.noInterface);
		return;
	}
	wmGetInterfaceCandidates(graphIds)
		.done(function (data) {
			var items = data.items;
			if (generation !== wmInterfaceLookupGeneration || (wmLinkHoverEntries($('#link_target').val())[0] || '') !== primary) return;
			var matches = items.filter(function (item) {
				return wmLinkSameTrafficSource(item.id, primary);
			});
			if (!matches.length) {
				matches = items.filter(function(item) { return item.id.split('/').pop() === file; });
				if (matches.length !== 1) matches = [];
			}
			$('#wm-link-current').text(
				matches.length
					? matches[0].label
					: target
						? wmEditorText.customInterface
						: wmEditorText.noInterface
			);
			if (matches.length) wmPromoteLinkTrafficHover(matches[0].local_graph_id);
			if (matches.length && !$('#link_target_picker').data('wm-choice')) {
				if (!picker.val()) picker.val(matches[0].label);
				$('#link_target_picker').val(matches[0].id).data('wm-choice', matches[0]);
			}
		})
		.fail(function () {
			if (generation !== wmInterfaceLookupGeneration || (wmLinkHoverEntries($('#link_target').val())[0] || '') !== primary) return;
			$('#wm-link-current').text(wmEditorText.unavailableInterface);
		});
	if (!$('#wm-link-use').data('wm-detail-hook')) {
		$('#wm-link-use')
			.on('click.wmDetails', function () {
				$('#wm-link-rrd').text((wmLinkHoverEntries($('#link_target').val())[0] || '').split('/').pop());
			})
			.data('wm-detail-hook', true);
	}
}

function compactNodeEditor() {
	var dlg = $('#dlgNodeProperties'),
		table = $('#node_label').closest('table');
	var rows = dlg.find('#node_new_name,#node_infourl,#node_hover,#node_template,#node_picker').closest('tr');
	$('#node_label').closest('tr').find('td:first').text(wmEditorText.displayName);
	$('#node_iconfilename').closest('tr').find('td:first').text(wmEditorText.icon);
	$('#node_template').closest('tr').find('td:first').text(wmEditorText.graphType);
	$('#node_picker').closest('tr').find('td:first').text(wmEditorText.hoverGraphs);
	$('#node_label').closest('tr').prependTo(table);
	$('#node_labelposition').closest('tr').insertAfter($('#node_label').closest('tr'));
	$('#node_iconfilename').closest('tr').insertAfter($('#node_labelposition').length ? $('#node_labelposition').closest('tr') : $('#node_label').closest('tr'));
	if (!$('#wm-node-delete-bar').length)
		$('<div id="wm-node-delete-bar" style="text-align:right;margin-bottom:8px"></div>').insertBefore(table);
	$('#node_delete').text(wmEditorText.deleteNode).appendTo('#wm-node-delete-bar').css('color', '#9b2525');
	var buttons = dlg.find('.dlgSubButtons');
	$('#node_move').prependTo(buttons);
	$('#node_clone').insertAfter('#node_move').css('margin-right', '24px');
	$('#tb_node_submit').insertAfter('#node_clone').css('font-weight', 'bold');
	$('#tb_node_cancel').insertAfter('#tb_node_submit');
	if (!$('#wm-node-toggle').length) {
		$('<button type="button" id="wm-node-toggle" class="ui-button ui-corner-all"></button>')
			.text(wmEditorText.advanced)
			.insertBefore(dlg.find('.dlgButtons'))
			.on('click', function () {
				var open = $(this).attr('aria-expanded') !== 'true';
				$(this)
					.attr('aria-expanded', String(open))
					.text(open ? wmEditorText.hideAdvanced : wmEditorText.advanced);
				rows.toggle(open);
				dlg.find('#node_edit,.dlgHelp').toggle(open);
			});
		$(
			'<div style="font-size:11px;margin:6px 0 12px;color:#555"></div>'
		).text(wmEditorText.nodePurpose).insertAfter('#wm-node-toggle');
		dlg.find('.dlgHelp').empty().append(
			$('<p>').text(wmEditorText.nodeDisplayHelp),
			$('<p>').text(wmEditorText.nodeHoverHelp),
			$('<p>').text(wmEditorText.nodeAdvancedHelp)
		);
	}
	rows.hide();
	dlg.find('#node_edit,.dlgHelp').hide();
	$('#wm-node-toggle').attr('aria-expanded', 'false').text(wmEditorText.advanced);
}

function refineNodePicker() {
	var picker = $('#node_picker_input'),
		buttons = $('#node_picker_add,#node_picker_rep');
	picker.val('').attr('placeholder', wmEditorText.searchGraph);
	$('#node_picker').val('');
	buttons.prop('disabled', true);
	picker.autocomplete('option', 'autoFocus', false);
	$('#node_picker_wrap').off('click dblclick mouseleave');
	picker.off('click.wmNode').on('click.wmNode', function () {
		picker.autocomplete('search', '');
	});
	$('#node_picker_click')
		.off('click.wmNode')
		.on('click.wmNode', function () {
			picker.trigger('focus');
			picker.autocomplete('search', '');
		});
	if (!picker.data('wm-node-hook')) {
		var original = picker.autocomplete('option', 'select');
		picker.autocomplete('option', 'select', function (event, ui) {
			if (original && original.call(this, event, ui) === false) return false;
			buttons.prop('disabled', !ui.item.id);
		});
		picker.on('input.wmNode', function () {
			buttons.prop('disabled', true);
		});
		picker.data('wm-node-hook', true);
	}
	if (!$('#wm-node-browse').length)
		$('<button type="button" id="wm-node-browse" class="ui-button ui-corner-all"></button>')
			.text(wmEditorText.browseAll)
			.appendTo($('#node_picker').closest('td'))
			.on('click', function () {
				picker.trigger('focus');
				picker.autocomplete('search', '');
			});
	if (!$('#wm-node-graphs').length)
		$('<div id="wm-node-graphs" style="font-size:11px;margin:6px 0"></div>').insertBefore('#wm-node-toggle');
	var hover = String($('#node_hover').val() || '');
	var matches = [];
	hover.trim().split(/\s+/).forEach(function(entry) {
		matches = matches.concat(wmLocalGraphIds(entry, ['graph_image.php']));
	});
	$('#wm-node-graphs').text(wmNodeHoverSummary(hover, []));
	if (matches.length)
		wmGetGraphSummaries(matches).done(function (items) {
			if (String($('#node_hover').val() || '') !== hover) return;
			var labels = items.filter(function (i) {
				return matches.indexOf(String(i.id)) !== -1;
			});
			$('#wm-node-graphs').text(wmNodeHoverSummary(hover, labels));
			if (labels.length === 1 && !picker.val()) picker.val(labels[0].label);
		});
}
// Only IDs from this installation's known graph endpoints are local candidates.
function wmLocalGraphIds(value, endpoints) {
	try {
		var root = new URL('../../', window.location.href);
		var url = new URL(value, /^graph(?:_image|_view)?\.php(?:[?#]|$)/.test(value) ? root : window.location.href);
		if (url.origin !== root.origin || !endpoints.some(function(endpoint) { return url.pathname === root.pathname + endpoint; })) return [];
		var ids = url.searchParams.get(url.pathname.endsWith('/graph_view.php') ? 'graph_list' : 'local_graph_id');
		return ids && /^[0-9]+(?:,[0-9]+)*$/.test(ids) ? ids.split(',') : [];
	} catch (error) {
		return [];
	}
}
function wmNodeHoverSummary(hover, items) {
	var entries = hover.trim().split(/\s+/).filter(Boolean);
	if (!entries.length) return wmEditorText.noHover;
	var labels = new Map(items.map(function(item) { return [String(item.id), item.label]; }));
	var summary = entries.map(function(entry) {
		var graph = wmLocalGraphIds(entry, ['graph_image.php']);
		return graph.length ? (labels.get(graph[0]) || wmEditorText.unavailableGraph) : wmEditorText.customImages;
	});
	return wmEditorText.hoverSummary.replace('%s', function() { return summary.join('; '); });
}
function wmGetGraphSummaries(ids) {
	var result = $.Deferred();
	var unique = Array.from(new Set(ids));
	var labels = [];
	function next(offset) {
		if (offset >= unique.length) {
			result.resolve(labels);
			return;
		}
		$.getJSON(window.location.pathname, {
			action: 'graphs', term: '', mapname: $('#mapname').val(),
			target: 'node_picker', graph_template_id: -1, paged: 1, include_rrds: '1',
			graph_ids: unique.slice(offset, offset + 100).join(',')
		}).done(function(data) {
			labels = labels.concat(data.items);
			next(offset + 100);
		}).fail(function() { result.reject(); });
	}
	next(0);
	return result.promise();
}
function wmNodeDisplayName(name) {
	var node = Nodes[name];
	var label = node ? String(node.label || '').trim() : '';
	return label && label !== 'Node' ? label.replace(/\\n/g, ' ').replace(/\s+/g, ' ') : name;
}
function wmFriendlyLinkNames() {
	var link = Links[$('#link_name').val()];
	if (!link) return;
	$('#link_nodename1').text(wmNodeDisplayName(link.a));
	$('#link_nodename2').text(wmNodeDisplayName(link.b));
	$('#link_bandwidth_in')
		.closest('tr')
		.find('td:first')
		.text(wmEditorText.bandwidthIn.replace('%s', function() { return wmNodeDisplayName(link.a); }));
	$('#link_bandwidth_out')
		.closest('tr')
		.find('td:first')
		.text(wmEditorText.bandwidthOut.replace('%s', function() { return wmNodeDisplayName(link.a); }));
	if (!$('#wm-link-internal-id').length)
		$('<p id="wm-link-internal-id" class="wm-link-advanced" style="font-size:0.9em"></p>').insertBefore(
			$('#wm-link-advanced').find('table')
		);
	$('#wm-link-internal-id')
		.text(wmEditorText.internalLink.replace('%s', function() { return link.name; }));

}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {wmLinkHoverEntries: wmLinkHoverEntries, wmLinkCompactHoverUrl: wmLinkCompactHoverUrl, wmSyncLinkHoverParts: wmSyncLinkHoverParts, wmRenderLinkExtraGraphs: wmRenderLinkExtraGraphs, wmInitLinkHoverParts: wmInitLinkHoverParts, wmSetLinkPrimaryHover: wmSetLinkPrimaryHover, wmPromoteLinkTrafficHover: wmPromoteLinkTrafficHover, wmSetLinkExtraHover: wmSetLinkExtraHover, wmLinkTrafficIdentity: wmLinkTrafficIdentity, wmLinkSameTrafficSource: wmLinkSameTrafficSource, wmRenderLinkTrafficSources: wmRenderLinkTrafficSources, wmApplyLinkTrafficSource: wmApplyLinkTrafficSource, wmInitLinkSourcePicker: wmInitLinkSourcePicker, wmRestoreLinkGraphChoice: wmRestoreLinkGraphChoice, wmBuildLinkSections: wmBuildLinkSections, compactLinkEditorBase: compactLinkEditorBase, wmGetInterfaceSummary: wmGetInterfaceSummary, wmGetInterfaceCandidates: wmGetInterfaceCandidates, compactLinkEditor: compactLinkEditor, compactNodeEditor: compactNodeEditor, refineNodePicker: refineNodePicker, wmLocalGraphIds: wmLocalGraphIds, wmNodeHoverSummary: wmNodeHoverSummary, wmGetGraphSummaries: wmGetGraphSummaries, wmNodeDisplayName: wmNodeDisplayName, wmFriendlyLinkNames: wmFriendlyLinkNames};
}
