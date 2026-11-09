// Graph and node icon pickers for the map editor; loaded after editor.js.

/**
 * Replace each `.selectmenu-ajax` input with a searchable, paged graph
 * picker and its Add and Replace buttons.
 *
 * @returns {void}
 */
function graphPicker() {
	$('.selectmenu-ajax').each(function() {
		var id       = $(this).attr('id');
		var value    = $(this).val();
		var title    = 'Click to Search';
		var action   = $(this).attr('data-action');
		var mapname  = 'none';
		var pickerOffset = 0;
		var pickerTerm = '';
		var pickerTemplate = null;

		if ($('#'+id+'_wrap').length) {
			$('#'+id+'_wrap').remove();
			$('#'+id+'_add').remove();
			$('#'+id+'_rep').remove();
		}

		var dialogForm = "<span id='" + id + "_wrap' class='autodrop ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget'>";
		dialogForm    += "<span id='" + id + "_click' style='z-index:4' class='ui-selectmenu-icon ui-icon ui-icon-triangle-1-s'></span>";
		dialogForm    += "<span class='ui-select-text'>";
		dialogForm    += "<input type='text' class='ui-state-default ui-corner-all' id='" + id + "_input' value='" + title + "'>";
		dialogForm    += "</span>";
		dialogForm    += "</span>&nbsp;";
		dialogForm    += "<button id='" + id + "_add' type='button' class='ui-button ui-corner-all ui-widget'>Add</button>&nbsp;";
		if (id !== 'link_target_picker') dialogForm    += "<button id='" + id + "_rep' type='button' class='ui-button ui-corner-all ui-widget'>Replace</button>";

		$(this).after(dialogForm);
		$(this).hide();
		$('#' + id + '_add').text($(this).attr('data-add-label') || 'Add');
		$('#' + id + '_rep').text($(this).attr('data-replace-label') || 'Replace');
		$('#' + id + '_input').attr('aria-label', $(this).attr('aria-label') || $(this).closest('tr').find('td:first').text());

		$('#' + id + '_add').off('click').on('click', function() {
			var hover   = 'graph_image.php?local_graph_id=';;
			var infourl = infoUrlTarget;

			if (id == 'link_target_picker') {
				wmApplyLinkTrafficSource();
			} else {
				var hover   = 'graph_image.php?local_graph_id=';;
				var infourl = infoUrlTarget;

				if (id == 'link_picker') {
					var target = String($('#' + id).val() || '');
					if (!/^[1-9][0-9]*$/.test(target)) return;
					var graphUrl = new URL(hover + target, new URL('../../', window.location.href));
					wmSetLinkExtraHover(wmLinkCompactHoverUrl(graphUrl.pathname + graphUrl.search), false);
				} else if (id == 'node_picker') {
					var target = $('#' + id).val();
					var ehover = $('#node_hover').val();
					var einfo  = $('#node_infourl').val();

					if (einfo == '') {
						einfo = infourl;
					}

					$('#node_hover').val(ehover + (ehover != '' ? ' ':'') + hover + target);

					if (infoUrlStyle == 0) {
						$('#node_infourl').val(einfo + (einfo != '' ? ',':'') + target);
					} else {
						$('#node_infourl').val(infourl + target);
					}
				}
			}
		});

		$('#' + id + '_rep').off('click').on('click', function() {
			if (id == 'link_picker') {
				var target = String($('#' + id).val() || '');
				if (!/^[1-9][0-9]*$/.test(target)) return;
				var graphUrl = new URL('graph_image.php?local_graph_id=' + target, new URL('../../', window.location.href));
				wmSetLinkExtraHover(wmLinkCompactHoverUrl(graphUrl.pathname + graphUrl.search), true);
			} else if (id == 'node_picker') {
				$('#node_hover').val('graph_image.php?local_graph_id=' + $('#' + id).val());
				$('#node_infourl').val(infoUrlTarget + $('#' + id).val());
			}
		});

		$('#' + id + '_input').autocomplete({
			source: function(request, response) {
				if (id == 'node_picker') {
					var template = $('#node_template').val();
				} else if (id == 'link_picker') {
					var template = $('#link_template').val();
				} else {
					var template = -1;
				}

				if (request.term !== pickerTerm || template !== pickerTemplate) {
					pickerOffset = 0;
				}
				pickerTerm = request.term;
				pickerTemplate = template;

				var url = 'weathermap-cacti-plugin-editor.php' +
					'?mapname=' + encodeURIComponent($('#mapname').val()) +
					'&action=' + action +
					'&term=' + encodeURIComponent(request.term) +
					'&target=' + id +
					'&graph_template_id='+template + '&paged=1&include_rrds=' + (id === 'link_picker' ? '1' : '0') + '&offset=' + pickerOffset;

				$.getJSON(url, function(data) {
					var items = data.items;
					if (data.offset > 0) {
						items.unshift({label: data.previous_label, value: request.term, wmOffset: Math.max(0, data.offset - 100)});
					}
					if (data.next_offset !== null) {
						items.push({label: data.next_label, value: request.term, wmOffset: data.next_offset});
					}
					response(items);
				}).fail(function() { response([]); });
			},
			autoFocus: false,
			minLength: 0,
			focus: function(event, ui) {
				if (ui.item.wmOffset !== undefined) { return false; }
			},
			select: function(event, ui) {
				if (ui.item.wmOffset !== undefined) {
					pickerOffset = ui.item.wmOffset;
					setTimeout(function() { $('#' + id + '_input').autocomplete('search', pickerTerm); }, 0);
					return false;
				}
				$('#' + id + '_input').val(ui.item.label);
				if (id === 'link_picker') $(this).data('wm-graph-choice', ui.item);

				if (ui.item.id) {
					$('#' + id).val(ui.item.id);
					local_graph_id = ui.item.local_graph_id;
				} else {
					$('#' + id).val(ui.item.value);
					local_graph_id = ui.item.local_graph_id;
				}
			},
			open: function(event, ui) {
				$('.ui-dialog').css('z-index', '20');
				$(this).css('z-index', '5000');
			}
		}).css('border', 'none').css('background-color', 'transparent');

		$('#' + id + '_wrap').on('dblclick', function() {
			graphOpen = false;
			clearTimeout(graphTimer);
			clearTimeout(graphClickTimer);
			$('#' + id + '_input').autocomplete('close').select();
		}).on('click', function() {
			if (graphOpen) {
				$('#'+'_input').autocomplete('close');
				clearTimeout(graphTimer);
				graphOpen = false;
			} else {
				graphClickTimer = setTimeout(function() {
					pickerOffset = 0;
					$('#' + id + '_input').autocomplete('search', '');
						clearTimeout(graphTimer);
						graphOpen = true;
					}, 200);
			}
			$('#' + id + '_input').select();
		}).on('mouseleave', function() {
			graphTimer = setTimeout(function() { $('#' + id + '_input').autocomplete('close'); }, 800);
		});

		var width = $('#' + id + '_input').textBoxWidth();
		if (width < 200) {
			width = 200;
		}

		$('#' + id + '_wrap').css('width', width+20);
		$('#' + id + '_input').css('width', width);
		$('#' + id + '_wrap').find('.ui-select-text').css('width', width);

		$('ul[id^="ui-id"]').on('mouseenter', function() {
			clearTimeout(graphTimer);
		}).on('mouseleave', function() {
			graphTimer = setTimeout(function() {
				$('#' + id + '_input').autocomplete('close');
			}, 800);
		});

		$('ul[id^="ui-id"] > li').on('mouseenter', function() {
			$(this).addClass('ui-state-hover');
		}).on('mouseleave', function() {
			$(this).removeClass('ui-state-hover');
		});

		$('#' + id + '_wrap').on('mouseenter', function() {
			$(this).addClass('ui-state-hover');
			$('input#' + id + '_input').addClass('ui-state-hover');
		}).on('mouseleave', function() {
			$(this).removeClass('ui-state-hover');
			$('input#' + id + '_input').removeClass('ui-state-hover');
		});
	});
}

/**
 * Return an absolute preview URL for a relative path inside the plugin's
 * images/ tree, or '' for anything else. URL parsing resolves dot segments
 * (including percent-encoded ones) before the prefix check, so traversal out
 * of images/ and other origins or schemes are rejected.
 *
 * @param {*} value Candidate image path from an option or the image library.
 * @returns {string} Absolute preview URL, or '' when the path is rejected.
 */
function wmEditorImageSrc(value) {
	if (typeof value !== 'string' || !/^images\/[^?#\\]+\.(png|gif|jpe?g)$/i.test(value)) return '';
	try {
		var image = new URL(value, document.baseURI);
		var root = new URL('images/', document.baseURI);
		return image.origin === root.origin && image.pathname.indexOf(root.pathname) === 0 ? image.href : '';
	} catch (error) {
		return '';
	}
}

/**
 * Add a searchable icon grid for the node icon select; the select stays the
 * submitted field.
 *
 * @returns {void}
 */
function wmNodeIconPicker() {
	var select = $('#node_iconfilename');
	if (select.hasClass('dd-container')) {
		select.ddslick('destroy');
		select = $('#node_iconfilename');
	}
	select.val(selectedNode).addClass('wm-icon-source');
	$('#wm-icon-picker').remove();
	var picker = $('<details id="wm-icon-picker" class="wm-icon-picker">').insertAfter(select);
	var summary = $('<summary>').appendTo(picker);
	var panel = $('<div class="wm-icon-panel">').appendTo(picker);
	var search =  $('<input type="search">').attr({placeholder: wmEditorText.iconSearchPlaceholder, 'aria-label': wmEditorText.iconSearchLabel}).appendTo(panel);
	var count = $('<div class="wm-icon-count" role="status" aria-live="polite">').appendTo(panel);
	var grid = $('<div class="wm-icon-grid">').attr({role: 'group', 'aria-label': wmEditorText.iconGroupLabel}).appendTo(panel);
	function title(option) {
		return option.text.replace(/\.(png|gif|jpe?g)$/i, '').replace(/[-_]+/g, ' ');
	}
	function content(target, option) {
		var src = wmEditorImageSrc($(option).attr('data-imagesrc'));
		if (src) {
			var image = document.createElement('img');
			image.alt = '';
			image.src = src;
			target.append(image);
		}
		$('<span>').text(title(option)).appendTo(target);
	}
	function updateSelection() {
		var option = select.find('option:selected')[0];
		summary.empty();
		if (option) content(summary, option);
		else summary.text(wmEditorText.iconChoosePrompt);
		grid.find('button').each(function() {
			$(this).attr('aria-pressed', String($(this).data('value') === select.val()));
		});
	}
	select.find('option').each(function() {
		var option = this;
		var button = $('<button type="button" class="wm-icon-choice">').attr('title', option.text).data('value', option.value).appendTo(grid);
		content(button, option);
		button.on('click', function() {
			select.val(option.value);
			updateSelection();
			picker.prop('open', false);
			summary.trigger('focus');
		});
	});
	search.on('input', function() {
		var query = this.value.trim().toLowerCase();
		var visible = 0;
		grid.find('button').each(function() {
			var matches = ($(this).text() + ' ' + $(this).data('value')).toLowerCase().indexOf(query) !== -1;
			$(this).prop('hidden', !matches);
			if (matches) visible++;
		});
		count.text(visible ? wmEditorText.iconMatchCount.replace('%s', String(visible)) : wmEditorText.iconNoMatches);
	});
	picker.on('keydown', function(event) {
		if (event.key === 'Escape' && picker.prop('open')) {
			event.stopPropagation();
			picker.prop('open', false);
			summary.trigger('focus');
		} else if (event.key === 'Enter' && event.target === search[0]) {
			event.preventDefault();
		}
	});
	search.trigger('input');
	updateSelection();
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {graphPicker: graphPicker, wmEditorImageSrc: wmEditorImageSrc, wmNodeIconPicker: wmNodeIconPicker};
}
