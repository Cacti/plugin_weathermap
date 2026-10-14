// global variable for subwindow reference
const MESSAGE_LEVEL_NONE  = 0;
const MESSAGE_LEVEL_INFO  = 1;
const MESSAGE_LEVEL_WARN  = 2;
const MESSAGE_LEVEL_ERROR = 3;
const MESSAGE_LEVEL_CSRF  = 4;
const MESSAGE_LEVEL_MIXED = 5;

var sessionMessage      = null;
var sessionMessageOpen  = null;
var sessionMessageTimer = null;

var newWindow;
var selectedNode;
var selectedLink;
var graphTimer;
var graphClickTimer;
var graphOpen = false;
var editor_url = 'weathermap-cacti-plugin-editor.php';
var imageWidth  = null;
var imageHeight = null;
var local_graph_id = null;
var infoUrlTarget = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';

function displayMessages() {
	var error   = false;
	var title   = '';
	var header  = '';

	if (typeof sessionMessageTimer == 'function' || sessionMessageTimer !== null) {
		clearInterval(sessionMessageTimer);
	}

	if (sessionMessage == null) {
		return;
	}

	if (typeof sessionMessage.level != 'undefined') {
		if (sessionMessage.level == MESSAGE_LEVEL_ERROR) {
			title = errorReasonTitle;
			header = errorOnPage;
			var sessionMessageButtons = {
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_MIXED) {
			title  = mixedReasonTitle;
			header = mixedOnPage;
			var sessionMessageButtons = {
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_CSRF) {
			var href = document.location.href;
			href = href + (href.indexOf('?') > 0 ? '&':'?') + 'csrf_timeout=true';
			document.location = href;
			return false;
		} else {
			title = sessionMessageTitle;
			header = sessionMessageSave;
			var sessionMessageButtons = {
				'Pause': {
					text: sessionMessagePause,
					id: 'btnSessionMessagePause',
					click: function() {
						if (sessionMessageTimer != null) {
							clearInterval(sessionMessageTimer);
							sessionMessageTimer = null;
						}
						$('#btnSessionMessagePause').remove();
						$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + '</span>');
					}
				},
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
						$('#messageContainer').remove();
						clearInterval(sessionMessageTimer);
					}
				}
			};

			sessionMessageOpen = function() {
				sessionMessageCountdown(5000);
			}
		}

		var returnStr = '<div id="messageContainer" style="display:none">' +
			'<h4>' + header + '</h4>' +
			'<p style="display:table-cell;overflow:auto"> ' + sessionMessage.message + '</p>' +
			'</div>';

		$('#messageContainer').remove();
		$('body').append(returnStr);

		var messageWidth = $(window).width();
		if (messageWidth > 600) {
			messageWidth = 600;
		} else {
			messageWidth -= 50;
		}

		$('#messageContainer').dialog({
			open: sessionMessageOpen,
			draggable: true,
			resizable: false,
			height: 'auto',
			minWidth: messageWidth,
			maxWidth: 800,
			maxHeight: 600,
			title: title,
			buttons: sessionMessageButtons
		});

		sessionMessage = null;
	}
}

function sessionMessageCountdown(time) {
	var sessionMessageTimeLeft = (time / 1000);

	$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + ' (' + sessionMessageTimeLeft + ')</span>');

	sessionMessageTimer = setInterval(function() {
		sessionMessageTimeLeft--;

		$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + ' (' + sessionMessageTimeLeft + ')</span>');

		if (sessionMessageTimeLeft <= 0) {
			clearInterval(sessionMessageTimer);
			$('#messageContainer').dialog('close');
			$('#messageContainer').remove();
		}
	}, 1000);
}

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
		dialogForm    += "<input id='" + id + "_add' type='button' class='ui-button ui-corner-all ui-widget' value='Add' />&nbsp;";
		dialogForm    += "<input id='" + id + "_rep' type='button' class='ui-button ui-corner-all ui-widget' value='Replace'/>";

		$(this).after(dialogForm);
		$(this).hide();

		$('#' + id + '_add').off('click').on('click', function() {
			var hover   = 'graph_image.php?local_graph_id=';;
			var infourl = infoUrlTarget;

			if (id == 'link_target_picker') {
				var target = $('#' + id).val();
				var existing = $('#link_target').val();

				$('#link_target').val(existing + (existing != '' ? ' ':'') + target);

				// Add the graph hovers if possible
				var ehover = $('#link_hover').val();
				var einfo  = $('#link_infourl').val();

				if (local_graph_id > 0) {
					if (ehover == '') {
						$('#link_hover').val(hover + local_graph_id);
					}

					if (einfo == '' || infoUrlStyle == 1) {
						$('#link_infourl').val(infourl + local_graph_id);
					}
				}
			} else {
				var hover   = 'graph_image.php?local_graph_id=';;
				var infourl = infoUrlTarget;

				if (id == 'link_picker') {
					var target = $('#' + id).val();
					var ehover = $('#link_hover').val();
					var einfo  = $('#link_infourl').val();

					if (einfo == '') {
						einfo = infourl;
					}

					$('#link_hover').val(ehover + (ehover != '' ? ' ':'') + hover + target);
					if (infoUrlStyle == 0) {
						$('#link_infourl').val(einfo + (einfo != '' ? ',':'') + target);
					} else {
						$('#link_infourl').val(infourl + target);
					}
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
				$('#link_hover').val('graph_image.php?local_graph_id=' + $('#' + id).val());
				$('#link_infourl').val(infoUrlTarget + $('#' + id).val());
			} else if (id == 'node_picker') {
				$('#node_hover').val('graph_image.php?local_graph_id=' + $('#' + id).val());
				$('#node_infourl').val(infoUrlTarget + $('#' + id).val());
			} else if (id == 'link_target_picker') {
				$('#link_target').val($('#' + id).val());
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
					'&graph_template_id='+template + '&paged=1&offset=' + pickerOffset;

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

$(document).on('unload', cleanupJS);

$(function() {
	initJS();
});

function initJS() {
	// if the xycapture element is there, then we are in the main edit screen
	if ($('#xycapture').length) {
		if ($('#tb_help').data('idle-help') === undefined) $('#tb_help').data('idle-help', $('#tb_help').text());
		attach_click_events();
		attach_help_events();
		//show_context_help('node_label', 'node_help');

		// set the mapmode, so we know where we stand.
		mapmode('existing');
	}

	$('area').draggable();

	$('#frmMain').off('submit').on('submit', function(event) {
		event.preventDefault();
		form_submit(event);
	});

	/* Cacti core may have already converted this select to select2; don't
	 * also layer a jQuery UI selectmenu widget on top of it */
	if (!$('#node_template').hasClass('select2-hidden-accessible')) {
		$('#node_template').selectmenu().selectmenu('menuWidget').addClass('overflow');
	}

	initContextMenu();

	graphPicker();

	if (infoUrlStyle == 0) {
		infoUrlTarget = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';
	} else {
		infoUrlTarget = 'graph.php?rra_id=all&local_graph_id=';
	}
}

/** textBoxWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textBoxWidth = function() {
	var org = $(this);
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + (org.val() || org.text()) + '</span>');
	html.css('font-family', org.css('font-family'));
	html.css('font-weight', org.css('font-weight'));
	html.css('font-size',   org.css('font-size'));
	html.css('padding',     org.css('padding'));
	html.css('margin',      org.css('margin'));
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
};

function initContextMenu() {
	var nodeMenu = [
		{title: txtNodeActions, cmd: "cat1", isHeader: true},
		{title: txtMove, cmd: 'move', uiIcon: 'ui-icon-arrow-4'},
		{title: txtClone, cmd: 'clone', uiIcon: 'ui-icon-copy'},
		{title: txtEdit, cmd: 'edit', uiIcon: 'ui-icon-pencil'},
		{title: txtDelNode, cmd: 'delete', uiIcon: 'ui-icon-trash', addClass: 'wm-delete-action'},
		{title: "----"},
		{title: txtProperties, cmd: 'properties', uiIcon: 'ui-icon-gear'}
	];

	var linkMenu = [
		{title: txtLinkActions, cmd: "cat1", isHeader: true},
		{title: txtTidy, cmd: 'tidy', uiIcon: 'ui-icon-arrow-4'},
		{title: txtVia, cmd: 'via', uiIcon: 'ui-icon-copy'},
		{title: txtEdit, cmd: 'edit', uiIcon: 'ui-icon-pencil'},
		{title: txtDelLink, cmd: 'delete', uiIcon: 'ui-icon-trash', addClass: 'wm-delete-action'},
		{title: "----"},
		{title: txtProperties, cmd: 'properties', uiIcon: 'ui-icon-gear'}
	];

	$('body').on('contextmenu', function() {
		return false;
	});

	$('map').contextmenu({
		delegate: 'area',
		menu: linkMenu,
		preventSelect: true,
		select: function(event, ui) {
			contextAction(event, ui);
		},
		beforeOpen: function(event, ui) {
			var target = ui.target[0].id;
			if (target.startsWith('LINK') || target.startsWith('NODE')) {
				if (['add_link', 'add_link2', 'add_node', 'place_note', 'move_node', 'via_link', 'place_legend', 'place_stamp'].indexOf($('#action').val()) !== -1) $('#action').val('');
				mapmode('existing');
			}

			if (target.startsWith('LINK')) {
				$(this).contextmenu('replaceMenu', linkMenu);
			} else if (target.startsWith('NODE')) {
				$(this).contextmenu('replaceMenu', nodeMenu);
			} else {
				return false;
			}
		}
	});
}

function contextAction(event, ui) {
	var alt, objectname, objecttype, objectid;

	alt        = ui.target[0].id;
	objecttype = alt.slice(0, 4);
	objectname = alt.slice(5, alt.length);
	objectid   = objectname.slice(0, objectname.length-2);

	if (ui.cmd == 'properties') {
		click_execute(event, alt);
	} else if (objecttype == 'NODE') {
		objectname = NodeIDs[objectid];

		if (prime_node_form(objectname)) {
			switch (ui.cmd) {
				case 'move':
					move_node();
					break;
				case 'clone':
					clone_node();
					break;
				case 'edit':
					edit_node();
					break;
				case 'delete':
					delete_node();
					break;
			}
		}
	} else if (objecttype == 'LINK') {
		objectname = LinkIDs[objectid];

		if (prime_link_form(objectname)) {
			switch (ui.cmd) {
				case 'tidy':
					tidy_link();
					break;
				case 'via':
					via_link();
					break;
				case 'edit':
					edit_link();
					break;
				case 'delete':
					delete_link();
					break;
			}
		}
	}
}

function cleanupJS() {
    // This should be cleaning up all the handlers we added in initJS, to avoid killing
    // IE/Win and Safari (at least) over a period of time with memory leaks.
}

// Rebuild return navigation from the allowed local pages and a validated map hash.
function wmEditorReturnUrl(target) {
	if (target === 'weathermap-cacti-plugin-mgmt.php') {
		return 'weathermap-cacti-plugin-mgmt.php';
	}

	var match = typeof target === 'string' ? /^weathermap-cacti-plugin\.php\?action=viewmap&id=([a-f0-9]{20,64})$/i.exec(target) : null;
	return 'weathermap-cacti-plugin.php' + (match ? '?action=viewmap&id=' + encodeURIComponent(match[1]) : '');
}

function attach_click_events() {
	$("area[id^='LINK:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='NODE:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='TIMES']").attr('href', '#').off('click').on('click', position_timestamp);
	$("area[id^='LEGEN']").attr('href', '#').off('click').on('click', position_legend);

	$('#tb_newfile').text($('body').attr('data-return-label') || 'Return to Map').off('click').on('click', function() {
		window.location.assign(wmEditorReturnUrl($('body').attr('data-return-map')));
	});

	$('#tb_addnode').off('click').on('click', add_node);
	$('#tb_mapprops').off('click').on('click', map_properties);
	$('#tb_mapstyle').off('click').on('click', map_style);

	$('#tb_addlink').off('click').on('click', add_link);
	$('#tb_poslegend').off('click').on('click', position_first_legend);
	$('#tb_postime').off('click').on('click', position_timestamp);
	$('#tb_colours').off('click').on('click', manage_colours);

	$('#tb_manageimages').off('click').on('click', manage_images);
	$('#tb_prefs').off('click').on('click', prefs);

	$('#node_move').off('click').on('click', move_node);
	$('#node_delete').off('click').on('click', delete_node);
	$('#node_clone').off('click').on('click', clone_node);
	$('#node_edit').off('click').on('click', edit_node);

	$('#link_delete').off('click').on('click', delete_link);
	$('#link_edit').off('click').on('click', edit_link);

	$('#link_tidy').off('click').on('click', tidy_link);

	$('#link_via').off('click').on('click', via_link);

	$('.wm_submit').off('click').on('click', form_submit);
	$('.wm_cancel').off('click').on('click', cancel_op);

	$('#xycapture').off('mouseover').mouseover(function(event) {
		coord_capture(event);
	});

	$('#xycapture').off('mousemove').mousemove(function(event) {
		coord_update(event);
	});

	$('#xycapture').off('mouseout').mouseout(function(event) {
		coord_release(event);
	});
}

// used by the cancel button on each of the properties dialogs
function cancel_op() {
	hide_all_dialogs();

	$('#action').val('');
}

function help_handler(event) {
	var objectid = $(this).attr('id');
	var section  = objectid.slice(0, objectid.indexOf('_'));
	var target   = section + '_help';
	var helptext = 'undefined';

	if (helptexts[objectid]) {
		helptext = helptexts[objectid];
	}

	if ((event.type == 'blur') || (event.type == 'mouseout')) {
        helptext = helptexts[section + '_default'];

		if (helptext == 'undefined') {
			alert('OID is: ' + objectid + ' and target is:' + target + ' and section is: ' + section);
		}
	}

	if (helptext != 'undefined') {
		$('#' + target).text(helptext);
	}
}

// Any clicks in the imagemap end up here.
function click_handler(event, target) {
	var alt = $(this).attr('id');

	if (alt == 'undefined') {
		alt = event.target.id;
	}

	click_execute(event, alt);
}

function click_execute(event, alt) {
	var objectname, objecttype, objectid;

	objecttype = alt.slice(0, 4);
	objectname = alt.slice(5, alt.length);
	objectid   = objectname.slice(0,objectname.length-2);

	// if we're not in a mode yet...
	if ($('#action').val() === '') {
		// if we're waiting for a node specifically (e.g. 'make link') then ignore links here
		if (objecttype == 'NODE') {
			// chop off the suffix
			objectname = NodeIDs[objectid];

			show_node(objectname);
		}

		if (objecttype == 'LINK') {
			// chop off the suffix
			objectname = LinkIDs[objectid];

			show_link(objectname);
		}
	} else {
		// we've got a command queued, so do the appropriate thing
		if (objecttype == 'NODE' && $('#action').val() == 'add_link') {
			$('#param').val(NodeIDs[objectid]);
			$('#action').val('add_link2');
			$('#tb_help').addClass('wm-action-help').text('Click on the second node for the end of the link.');
		} else if (objecttype == 'NODE' && $('#action').val() == 'add_link2') {
			$('#param2').val(NodeIDs[objectid]);
			form_submit();
		} else {
			// Halfway through one operation, the user has done something unexpected.
			// reset back to standard state, and see if we can oblige them
			//		alert('A bit confused');
			$('#action').val('');
			hide_all_dialogs();
			mapmode('existing');
		}
	}
}

function show_context_help(itemid, targetid) {
	var helpbox, helpboxtext, message;

	message = "We'd show helptext for " + itemid + " in the'" + targetid + "' div";

	helpbox = $('#'+targetid);
	helpboxtext = helpbox.firstChild;
	helpboxtext.nodeValue = message;
}

function manage_colours() {
	mapmode('existing');

	hide_all_dialogs();

	$('#action').val('set_map_colours');

	show_dialog('dlgColours');
	$('#dlgColours').dialog('option', 'width', Math.min(1100, $(window).width() - 40));
	wmOpenColours();
}

function manage_images() {
	mapmode('existing');

	hide_all_dialogs();

	$('#action').val('set_image');

	show_dialog('dlgImages');
}

function prefs() {
	hide_all_dialogs();

	$('#action').val('editor_settings');

	show_dialog('dlgEditorSettings');
}

function new_file() {
	self.location = '?action=newfile';
}

function wmResetActionHelp() {
	$('#tb_help').removeClass('wm-action-help').text($('#tb_help').data('idle-help'));
}

function mapmode(m) {
	var placing = m == 'xy' || ['add_link', 'add_link2'].indexOf($('#action').val()) !== -1;
	$('#existingdata, .mapData').toggleClass('wm-placement-mode', placing);
	$('#tb_help').toggleClass('wm-action-help', placing);
	if (!placing) wmResetActionHelp();
	if (m == 'xy') {
		$('#debug').val('xy');
		$('#xycapture').show();
		$('#existingdata').hide();

		setCanvasSize('xycapture');
	} else if (m == 'existing') {
		$('#debug').val('existing');
		$('#xycapture').hide();
		$('#existingdata').show();

		setCanvasSize('existingdata');
	}
}

function setCanvasSize(element) {
	imageWidth  = $('#'+element).attr('data-width');
	imageHeight = $('#'+element).attr('data-height');

	//console.log('Width:'+imageWidth+', Height:'+imageHeight);
}

function add_node() {
	$('#tb_help').addClass('wm-action-help').text(addNodeHelp);
	$('#action').val('add_node');

	mapmode('xy');
}

function delete_node() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	var name = $('#node_name').val();
	var connected = Object.keys(Links).filter(function(key) { return Links[key].a === name || Links[key].b === name; }).length;
	var message = delNodePrompt.replace('%s', function() { return wmNodeDisplayName(name); });
	if (connected) {
		message += ' ' + delNodeConnected.replace('%s', String(connected));
	}
	$('.dlgConfirm').text(message + ' ' + delNodeKeepDevice);

	if (['add_link', 'add_link2', 'add_node', 'place_note', 'move_node', 'via_link', 'place_legend', 'place_stamp'].indexOf($('#action').val()) !== -1) $('#action').val('');
	mapmode('existing');

	$('.dlgConfirm').dialog({
		close: function() { mapmode('existing'); },
		resizable: false,
		title: delNodeTitle,
		height: 'auto',
		width: 400,
		modal: true,
		buttons: [
			{
				text: txtCancel,
				click: function() {
					$(this).dialog('close');
					mapmode('existing');
				}
			},
			{
				text: txtDelNode,
				'class': 'wm-delete-action',
				click: function() {
					$(this).dialog('close');
					hide_all_dialogs();
					$('#param').val(name);
					$('#action').val('delete_node');
					form_submit();
				}
			}
		]
	});
}

function clone_node() {
	$('#action').val('clone_node');

	form_submit();
}

function edit_node() {
	$('#action').val('edit_node');

	show_itemtext('node', $('#node_name').val());
}

function edit_link() {
	$('#action').val('edit_link');

	show_itemtext('link', $('#link_name').val());
}

function move_node() {
	hide_dialog('dlgNodeProperties');

	$('#tb_help').addClass('wm-action-help').text(moveNodeHelp);
	$('#action').val('move_node');

	mapmode('xy');
}

function via_link() {
	hide_dialog('dlgLinkProperties');

	$('#tb_help').addClass('wm-action-help').text(viaLinkHelp);
	$('#action').val('via_link');

	mapmode('xy');
}

function add_link() {
	$('#tb_help').addClass('wm-action-help').text(addLinkHelp);
	$('#action').val('add_link');

	mapmode('existing');
}

function delete_link() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	var link = Links[$('#link_name').val()];
	var prompt = wmEditorText.deleteLinkPrompt
		.replace(/%([12])\$s/g, function(token, index) {
			return wmNodeDisplayName(index === '1' ? link.a : link.b);
		});
	$('.dlgConfirm').text(prompt + ' ' + wmEditorText.keepLinkNodes);

	if (['add_link', 'add_link2', 'add_node', 'place_note', 'move_node', 'via_link', 'place_legend', 'place_stamp'].indexOf($('#action').val()) !== -1) $('#action').val('');
	$('#param').val($('#link_name').val());
	mapmode('existing');

	$('.dlgConfirm').dialog({
		close: function() { mapmode('existing'); },
		resizable: false,
		title: delLinkTitle,
		height: 'auto',
		width: 400,
		modal: true,
		buttons: [
			{
				text: txtCancel,
				click: function() {
					$(this).dialog('close');
					mapmode('existing');
				}
			},
			{
				text: txtDelLink,
				'class': 'wm-delete-action',
				click: function() {
					$(this).dialog('close');
					hide_all_dialogs();
					$('#action').val('delete_link');
					form_submit();
				}
			}
		]
	});
}

function wmEditorReloadUrl() {
	var url = new URL(window.location.href);
	url.searchParams.set('mapname', $('#mapname').val());
	url.searchParams.set('action', 'nothing');
	url.searchParams.delete('config_revision');
	url.searchParams.delete('__csrf_magic');
	return url.href;
}

function wmReloadEditor() {
	window.location.replace(wmEditorReloadUrl());
}

function form_submit(event) {
	// Property dialogs can remain open while a toolbar placement action is active.
	// Their own Save buttons always dispatch the properties they are showing.
	var button = event && event.currentTarget && event.currentTarget.id;
	var sources = {
		tb_node_submit: ['set_node_properties', '#node_name'],
		tb_link_submit: ['set_link_properties', '#link_name'],
		tb_map_submit: ['set_map_properties'],
		tb_mapstyle_submit: ['set_map_style'],
		tb_editorsettings_submit: ['editor_settings'],
		tb_note_submit: ['set_note_properties', '#note_name'],
		tb_textedit_submit: [$('#dlgTextEdit').data('wm-config-action')]
	};
	if (button === 'frmMain' || !button) {
		var focused = $(button === 'frmMain' ? document.activeElement : event && event.currentTarget).closest('.ui-dialog-content').attr('id');
		var dialogs = {dlgNodeProperties: 'tb_node_submit', dlgLinkProperties: 'tb_link_submit', dlgMapProperties: 'tb_map_submit', dlgMapStyle: 'tb_mapstyle_submit', dlgEditorSettings: 'tb_editorsettings_submit', dlgNoteProperties: 'tb_note_submit', dlgTextEdit: 'tb_textedit_submit'};
		button = dialogs[focused];
	}
	if (sources[button] && sources[button][0]) {
		$('#action').val(sources[button][0]);
		if (sources[button][1]) $('#param').val($(sources[button][1]).val());
		mapmode('existing');
	}

	if ($('#action').val() === 'set_map_properties') {
		$('#map_background_colour').val($('#wm-map-background-colour .wm-colour-value').val());
	}
	var data = $('input, select, textarea').serialize();
	if (typeof WMConfigRevision !== 'undefined') data += '&config_revision=' + encodeURIComponent(WMConfigRevision);

	$.ajax({
		type: 'POST',
		url: editor_url,
		data: data,
		error: function(xhr) {
			window.alert(xhr && xhr.status === 409 ? wmEditorText.staleError : wmEditorText.saveError);
		},
		success: function() {
			hide_all_dialogs();
			wmReloadEditor();
		}
	});
}


function map_properties() {
	mapmode('existing');

	hide_all_dialogs();

	var background = wmAssetColour($('#map_background_colour').val(), false, true, true, wmEditorText.backgroundColour);
	$('#wm-map-background-colour').empty().append(background);
	$('#action').val('set_map_properties');

	show_dialog('dlgMapProperties');

	$('#map_title').focus();
}

function map_style() {
	mapmode('existing');

	hide_all_dialogs();

	$('#action').val('set_map_style');

	show_dialog('dlgMapStyle');

	$('#mapstyle_linklabels').focus();
}

function position_timestamp() {
	$('#tb_help').addClass('wm-action-help').text(timeStHelp);
	$('#action').val('place_stamp');

	mapmode('xy');
}

// called from clicking the toolbar
function position_first_legend() {
	real_position_legend('DEFAULT');
}

// called from clicking on the existing legends
function position_legend(event) {
	var el;
	var alt, objectname, objecttype;

	if (window.event && window.event.srcElement) {
		el = window.event.srcElement;
	}

	if (event && event.target) {
		el = event.target;
	}

	if (!el) {
		return;
	}

	alt = el.id;

	objectname = alt.slice(7, alt.length);

	real_position_legend(objectname);
}

function real_position_legend(scalename) {
	$('#tb_help').addClass('wm-action-help').text(posLegendHelp);
	$('#action').val('place_legend');
	$('#param').val(scalename);

	mapmode('xy');
}

function show_itemtext(itemtype,name) {
	mapmode('existing');

	hide_all_dialogs();

	$('textarea#item_configtext').val('');

	if (itemtype === 'node') {
		$('#action').val('set_node_config');
	}

	if (itemtype === 'link') {
		$('#action').val('set_link_config');
	}

	$('#dlgTextEdit').data('wm-config-action', $('#action').val());
	show_dialog('dlgTextEdit');

	$.ajax({
		type: 'GET',
		url: editor_url,
		data: {
			action: 'fetch_config',
			item_type: itemtype,
			item_name: name,
			mapname: $('#mapname').val()
		},
		success: function(text) {
			$('#item_configtext').val(text);
			$('#item_configtext').focus();
		}
	});
}

function prime_node_form(name) {
	var mynode = Nodes[name];

	if (mynode) {
		$('#node_name').val(name);
		$('#node_new_name').val(name);

		$('#node_x').val(mynode.x);
		$('#node_y').val(mynode.y);

		$('#node_name').val(mynode.name);
		$('#node_new_name').val(mynode.name);
		$('#node_label').val(mynode.label);
		$('#node_infourl').val(wmDecodeEditorUrl(mynode.infourl));
		$('#node_hover').val(wmDecodeEditorUrl(mynode.overliburl));

		if (mynode.iconfile != '') {
			//console.log(mynode.iconfile.substring(0,2));
			//console.log(mynode.iconfile);
			if (mynode.iconfile.substring(0, 2) == '::') {
				$('#node_iconfilename').val('--AICON--');
				selectedNode = '--AICON--';
			} else {
				$('#node_iconfilename').val(mynode.iconfile);
				selectedNode = mynode.iconfile;
			}
		} else {
			$('#node_iconfilename').val('--NONE--');
			selectedNode = '--NONE--';
		}

		// save this here, just in case they choose delete_node or move_node
		$('#param').val(mynode.name);

		return true;
	}

	return false;
}

function show_node(name) {
	mapmode('existing');

	hide_all_dialogs();

	var success = prime_node_form(name);

	if (success) {
		$('#action').val('set_node_properties');

		wmNodeIconPicker();

		$('.dd-container').on('click', function() {
			$('.ui-dialog').css('z-index', '100');
			$('.dd-options, .dd-container').css('z-index', '500');
		});

		compactNodeEditor();
 refineNodePicker();
		show_dialog('dlgNodeProperties');
	$('#dlgNodeProperties').dialog('option', 'title', wmEditorText.nodeTitle.replace('%s', function() { return wmNodeDisplayName(name); }));

		$('#node_new_name').focus();
	} else {
		console.log('Unable to find node');
	}
}

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
		var src = $(option).attr('data-imagesrc');
		if (src) $('<img alt="">').attr('src', src).appendTo(target);
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

function prime_link_form(name) {
	var mylink = Links[name];

	if (mylink) {
		$('#link_name').val(mylink.name);
		$('#link_target').val(mylink.target);
		$('#link_width').val(mylink.width);

		$('#link_bandwidth_in').val(mylink.bw_in);

		if (mylink.bw_in == mylink.bw_out) {
			$('#link_bandwidth_out').val('');
			$('#link_bandwidth_out_cb').prop('checked', true);
		} else {
			$('#link_bandwidth_out_cb').prop('checked', false);
			$('#link_bandwidth_out').val(mylink.bw_out);
		}

		$('#link_infourl').val(wmDecodeEditorUrl(mylink.infourl));
		$('#link_hover').val(wmDecodeEditorUrl(mylink.overliburl));
		$('#viastyle').val(mylink.viastyle);

		$('#link_commentin').val(mylink.commentin);
		$('#link_commentout').val(mylink.commentout);
		$('#link_commentposin').val(mylink.commentposin);
		$('#link_commentposout').val(mylink.commentposout);

		// if that didn't 'stick', then we need to add the special value
		if ($('#link_commentposout').val() != mylink.commentposout) {
			$('#link_commentposout').prepend($('<option>', { selected: true, value: mylink.commentposout, text: mylink.commentposout + '%' }));
		}

		if ($('#link_commentposin').val() != mylink.commentposin) {
			$('#link_commentposin').prepend($('<option>', { selected: true, value: mylink.commentposin, text: mylink.commentposin + '%' }));
		}

		document.getElementById('link_nodename1').firstChild.nodeValue  = mylink.a;
		document.getElementById('link_nodename2').firstChild.nodeValue  = mylink.b;

		$('#param').val(mylink.name);

		return true;
	}

	return false;
}

function show_link(name) {
	mapmode('existing');

	hide_all_dialogs();

	if (prime_link_form(name)) {
		$('#action').val('set_link_properties');

		compactLinkEditor();
wmFriendlyLinkNames();
show_dialog('dlgLinkProperties');

		$('#link_bandwidth_in').focus();
	}
}

function show_dialog(dlg) {
	mapmode('existing');
	wmResetActionHelp();
	if (dlg == 'dlgMapProperties') {
		var selectedNode = $('#map_bgfile').val();

		if ($('#map_bgfile.dd-container').length) {
			$('#map_bgfile').ddslick('destroy');
		}

		$('#map_bgfile').ddslick({
			height:240,
			defaultSelectedIndex:selectedNode
		});

		$('.dd-container').on('click', function() {
			$('.ui-dialog').css('z-index', '100');
			$('.dd-options, .dd-container').css('z-index', '500');
		});
	}

	$('#'+dlg).dialog({
		autoOpen: true,
		width: 600,
		height: 'auto',
		modal: false,
		resizable: false,
		draggable: true,
		open: function() {
			$(this).closest('.ui-dialog').addClass('wm-editor-dialog');
			$(this).find('.ui-autocomplete-input').each(function() {
				$(this).autocomplete('option', 'appendTo', $(this).closest('.ui-dialog'));
			});
			$('select').not('#node_iconfilename, #map_bgfile').not('.select2-hidden-accessible').selectmenu({
				open: function() {
					$('.ui-dialog').css('z-index', '20');
				}
			});
		}
	});
}

function hide_dialog(dlg) {
	if ($('#'+dlg).dialog('instance')) {
		$('#'+dlg).dialog('close');
	}

	$('#action').val('');
}

function hide_all_dialogs() {
	hide_dialog('dlgMapProperties');
	hide_dialog('dlgMapStyle');
	hide_dialog('dlgLinkProperties');
	hide_dialog('dlgTextEdit');
	hide_dialog('dlgNodeProperties');
	hide_dialog('dlgColours');
	hide_dialog('dlgImages');
	hide_dialog('dlgEditorSettings');
}

function coord_capture(event) {
	// $('#tb_coords').html('+++');
}

function coord_update(event) {
	/**
	 * Get the absolution location on the page of the
	 * cursor on the page
	 */
	var windowX = event.pageX.toFixed(0);
	var windowY = event.pageY.toFixed(0);

	/**
	 * Get the upper left hand corner of the image on page
	 * Which helps us perform the relative calculation
	 */
	if ($('#xycapture').is(':visible')) {
		var imageTopLeft = $('#xycapture').offset();
	} else {
		var imageTopLeft = $('#existing').offset();
	}
	//console.log('ImageTop:'+imageTopLeft.top+', ImageLeft:'+imageTopLeft.left);

	/**
	 * get the relative location on the image
	 * by subtracting the imageTop from the cursor
	 * position.
	 */
	windowX -= imageTopLeft.left;
	windowY -= imageTopLeft.top;
	windowX  = windowX.toFixed(0);
	windowY  = windowY.toFixed(0);

	$('#x').val(windowX);
	$('#y').val(windowY);

	// Log the coordinates
	//console.log('X Value:'+$('#x').val());
	//console.log('Y Value:'+$('#y').val());

	$('#tb_coords').html(txtPosition+'<br />'+ windowX + ', ' + windowY);
}

function coord_release(event) {
	$('#tb_coords').html(txtPosition+'<br />---, ---');
}

function tidy_link() {
	$('#action').val('link_tidy');
	form_submit();
}

function attach_help_events() {
	// add an onblur/onfocus handler to all the visible <input> items
	$('input').focus(help_handler).blur(help_handler);
}


// Compact link workflow; existing fields remain available under Advanced.
function compactLinkEditorBase() {
	var dlg = $('#dlgLinkProperties'),
		table = $('#link_target').closest('table');
	var fields = '#viastyle,#link_target,#link_infourl,#link_hover,#link_template,#link_picker';
	var rows = dlg.find(fields).closest('tr').addClass('wm-link-advanced');
	if (!$('#wm-link-summary').length) {
		$(
			'<tr id="wm-link-summary"><td></td><td><div id="wm-link-current"></div><small id="wm-link-graph"></small></td></tr>'
		).prependTo(table).find('td:first').text(wmEditorText.currentInterface);
		$('<button type="button" id="wm-link-toggle" class="ui-button ui-corner-all"></button>')
			.text(wmEditorText.advanced)
			.insertBefore(dlg.find('.dlgButtons'))
			.on('click', function () {
				var expanded = $(this).attr('aria-expanded') !== 'true';
				$(this)
					.attr('aria-expanded', String(expanded))
					.text(expanded ? wmEditorText.hideAdvanced : wmEditorText.advanced);
				rows.toggle(expanded);
				dlg.find('.dlgHelp,#link_edit').toggle(expanded);
				$('#link_target_picker_add,#link_target_picker_rep').toggle(expanded);
			});
		$(
			'<button type="button" id="wm-link-use" class="ui-button ui-corner-all" disabled></button>'
		)
			.text(wmEditorText.useInterface)
			.appendTo($('#link_target_picker').closest('td'))
			.on('click', function () {
				var item = $('#link_target_picker').data('wm-choice');
				if (!item || !item.id || !(item.local_graph_id > 0)) return;
				++wmInterfaceLookupGeneration;
				$('#link_target').val(item.id);
				var destination = new URL(infoUrlTarget + item.local_graph_id, new URL('../../', window.location.href));
				$('#link_infourl').val(destination.pathname + destination.search + destination.hash);
				$('#link_hover').val(
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
	rows.hide();
	dlg.find('.dlgHelp,#link_edit').hide();
	$('#wm-link-toggle').attr('aria-expanded', 'false').text(wmEditorText.advanced);
	$('#link_target_picker_add,#link_target_picker_rep').hide();
	$('#link_target_picker').closest('tr').find('td:first').text(wmEditorText.changeInterface);
	$('#link_target_picker_input').val('').attr('placeholder', wmEditorText.searchInterface);
	$('#link_target_picker').val('').removeData('wm-choice');
	local_graph_id = 0;
	$('#link_target_picker_add,#link_target_picker_rep,#wm-link-use').prop('disabled', true);
	var picker = $('#link_target_picker_input');
	if (!picker.data('wm-compact-hook')) {
		var original = picker.autocomplete('option', 'select');
		picker
			.autocomplete('option', 'select', function (event, ui) {
				if (original && original.call(this, event, ui) === false) return false;
				var valid = !!(ui.item.id && ui.item.local_graph_id > 0);
				$('#link_target_picker').val(valid ? ui.item.id : '').data('wm-choice', valid ? ui.item : null);
				local_graph_id = valid ? ui.item.local_graph_id : 0;
				$('#link_target_picker_add,#link_target_picker_rep,#wm-link-use').prop('disabled', !valid);
			})
			.on('input.wmCompact', function () {
				$('#link_target_picker').val('').removeData('wm-choice');
				local_graph_id = 0;
				$('#link_target_picker_add,#link_target_picker_rep,#wm-link-use').prop('disabled', true);
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
	dlg.find('#link_commentin,#link_commentout').closest('tr').removeClass('wm-link-advanced').show();
	if (!$('#wm-link-rrd').length)
		$('<small id="wm-link-rrd" style="display:block;overflow-wrap:anywhere;color:#666"></small>').insertAfter(
			'#wm-link-current'
		);
	if (!$('#wm-link-purpose').length)
		$(
			'<div id="wm-link-purpose" style="font-size:11px;margin:6px 0 12px;color:#555"></div>'
		).text(wmEditorText.linkPurpose).insertAfter('#wm-link-toggle');
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
	var file = target.split('/').pop();
	$('#wm-link-rrd').text(target);
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
			if (generation !== wmInterfaceLookupGeneration || String($('#link_target').val() || '').trim() !== target) return;
			var matches = items.filter(function (item) {
				return item.id === target;
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
			if (matches.length && !picker.val() && !$('#link_target_picker').data('wm-choice'))
				picker.val(matches[0].label);
		})
		.fail(function () {
			if (generation !== wmInterfaceLookupGeneration || String($('#link_target').val() || '').trim() !== target) return;
			$('#wm-link-current').text(wmEditorText.unavailableInterface);
		});
	if (!$('#wm-link-use').data('wm-detail-hook')) {
		$('#wm-link-use')
			.on('click.wmDetails', function () {
				$('#wm-link-rrd').text($('#link_target').val());
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
	$('#node_iconfilename').closest('tr').insertAfter($('#node_label').closest('tr'));
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
			target: 'node_picker', graph_template_id: -1, paged: 1,
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
			$('#link_target').closest('table')
		);
	$('#wm-link-internal-id')
		.text(wmEditorText.internalLink.replace('%s', function() { return link.name; }))
		.hide();
	$('#wm-link-toggle')
		.off('click.wmNames')
		.on('click.wmNames', function () {
			$('#wm-link-internal-id').toggle($(this).attr('aria-expanded') === 'true');
		});
}

// Map JavaScript HTML-escapes URL values; undo that when populating text fields.
function wmDecodeEditorUrl(value) {
	return String(value || '').replace(/&amp;/g, '&');
}
var wmAssetPalette = {Black: '#000000', White: '#ffffff', Blue: '#0000ff', Red: '#cc0000', Green: '#008000', Purple: '#800080', Orange: '#b35a00', Grey: '#666666', None: 'none'};
function wmAssetColour(value, allowEmpty, swatches, solid, label) {
	var box = $('<div class="wm-asset-colour">'), select = $('<select class="wm-colour-value">').attr('aria-label', label).appendTo(box);
	if (allowEmpty) select.append($('<option>').val('').text(wmEditorText.colourText0));
	Object.keys(wmAssetPalette).forEach(function(name) { if (solid && name === 'None') return; select.append($('<option>').val(wmAssetPalette[name]).text(wmEditorText.palette[name])); });
	if (value && Object.values(wmAssetPalette).indexOf(value.toLowerCase()) < 0) select.append($('<option>').val(value).text(wmEditorText.colourText8.replace('%s', value)));
	select.val(value);
	if (swatches) {
		var palette = $('<div class="wm-asset-swatches">').appendTo(box);
		Object.keys(wmAssetPalette).forEach(function(name) {
			if (name === 'None') return;
			$('<button type="button">').attr({'title': wmEditorText.palette[name], 'aria-label': wmEditorText.palette[name]}).css('background-color', wmAssetPalette[name]).appendTo(palette).on('click', function() { select.val(wmAssetPalette[name]).trigger('change'); });
		});
	}
	var advanced = $('<details>').append($('<summary>').text(wmEditorText.colourText1)).appendTo(box);
	var picker = $('<input type="color">').attr('aria-label', label).val(/^#[0-9a-f]{6}$/i.test(value) ? value : '#000000').appendTo(advanced);
	picker.on('input', function() {
		var colour = this.value;
		if (!select.find('option').filter(function() { return this.value === colour; }).length) select.append($('<option>').val(colour).text(wmEditorText.colourText8.replace('%s', colour)));
		select.val(colour).trigger('change');
	});
	select.on('change selectmenuchange', function() { if (/^#/.test(this.value)) picker.val(this.value); if (select.selectmenu('instance')) select.selectmenu('refresh'); });
	return box;
}
var wmColourRevision = null;
function wmColourRequest(action, extra, callback, errorId) {
	var values = {action: action, mapname: $('#mapname').val()};
	if (action === 'set_map_colours') values.config_revision = wmColourRevision;
	if (action === 'set_map_colours' && typeof csrfMagicToken !== 'undefined') values.__csrf_magic = csrfMagicToken;
	$.ajax({url: editor_url, type: action === 'colour_data' ? 'GET' : 'POST', dataType: 'json', data: $.extend(values, extra),
		success: function(data) { if (data.revision) wmColourRevision = data.revision; callback(data); }, error: function(xhr) { $('#' + errorId).text(xhr.status === 409 && wmEditorText.staleError || xhr.responseJSON && xhr.responseJSON.error || wmEditorText.colourText4); }});
}
function wmAssetScaleRow(row) {
	var tr = $('<tr>').appendTo('#wm-scale-rows');
	['scale', 'bottom', 'top'].forEach(function(key) {
		$('<td>').append($('<input>').attr('type', key === 'scale' ? 'text' : 'number').attr('step', 'any').attr('maxlength', key === 'scale' ? 1024 : null).addClass('wm-range-' + key).val(row[key])).appendTo(tr);
	});
	$('<td>').append(wmAssetColour(row.colour, false, false, false, wmEditorText.rangeColour)).appendTo(tr);
	$('<td>').append(wmAssetColour(row.end || '', true, false, true, wmEditorText.gradientColour)).appendTo(tr);
	$('<td>').append($('<input type="text" class="wm-range-tag">').val(row.tag || '')).appendTo(tr);
	$('<td>').append($('<button type="button" class="ui-button wm-asset-delete">').text(wmEditorText.colourText2).attr('aria-label', wmEditorText.colourText7).on('click', function() { tr.remove(); })).appendTo(tr);
}
function wmColourRefreshMap() {
	wmReloadEditor();
}
// Presets affect only DEFAULT; other named scales remain available in Advanced.
var wmTrafficPresets = {
    classic: [[0, 0, '#c0c0c0'], [0, 1, '#ffffff'], [1, 10, '#8c00ff'], [10, 25, '#2020ff'], [25, 40, '#00c0ff'], [40, 55, '#00f000'], [55, 70, '#f0f000'], [70, 85, '#ffc000'], [85, 100, '#ff0000']],
    signals: [[0, 0, '#c0c0c0'], [0, 50, '#008000'], [50, 75, '#d18b00'], [75, 90, '#e65c00'], [90, 100, '#cc0000']],
    ocean: [[0, 0, '#c0c0c0'], [0, 20, '#b3e5fc'], [20, 40, '#4fc3f7'], [40, 60, '#0288d1'], [60, 80, '#01579b'], [80, 100, '#002b55']],
    heat: [[0, 0, '#c0c0c0'], [0, 25, '#6a1b9a'], [25, 50, '#ab47bc'], [50, 75, '#ef6c00'], [75, 90, '#e53935'], [90, 100, '#9b0000']],
    modern: [[0, 0, '#c0c0c0'], [0, 20, '#d9edc3'], [20, 40, '#a4ca79'], [40, 60, '#6e9e46'], [60, 80, '#427322'], [80, 100, '#1f3b08']],
    sunrise: [[0, 0, '#c0c0c0'], [0, 20, '#fff0b3'], [20, 40, '#ffd166'], [40, 60, '#ffaa00'], [60, 80, '#e87500'], [80, 100, '#a63d00']],
    midwinter: [[0, 0, '#c0c0c0'], [0, 20, '#e2e8ff'], [20, 40, '#a8b9ee'], [40, 60, '#7585cc'], [60, 80, '#4e5697'], [80, 100, '#252637']]
};
function wmAssetScaleRows() {
    var rows = [];
    $('#wm-scale-rows tr').each(function() {
        var row = $(this);
        rows.push({scale: row.find('.wm-range-scale').val(), bottom: row.find('.wm-range-bottom').val(), top: row.find('.wm-range-top').val(), colour: row.find('.wm-colour-value').eq(0).val(), end: row.find('.wm-colour-value').eq(1).val(), tag: row.find('.wm-range-tag').val()});
    });
    return rows;
}
function wmTrafficPresetRows(key, rows) {
    if (!wmTrafficPresets[key]) return rows;
    return rows.filter(function(row) { return row.scale !== 'DEFAULT'; }).concat(wmTrafficPresets[key].map(function(band) {
        return {scale: 'DEFAULT', bottom: band[0], top: band[1], colour: band[2], end: '', tag: ''};
    }));
}
function wmTrafficPresetMatch(rows) {
    var current = rows.filter(function(row) { return row.scale === 'DEFAULT'; });
    return Object.keys(wmTrafficPresets).find(function(key) {
        var preset = wmTrafficPresets[key];
        return current.length === preset.length && preset.every(function(band) {
            return current.some(function(row) { return Number(row.bottom) === band[0] && Number(row.top) === band[1] && row.colour.toLowerCase() === band[2] && !row.end && !row.tag; });
        });
    }) || 'custom';
}
function wmTrafficPreview() {
    var preview = $('#wm-traffic-preview').empty();
    wmAssetScaleRows().filter(function(row) { return row.scale === 'DEFAULT'; }).forEach(function(row) {
        var band = $('<span class="wm-traffic-band">').appendTo(preview);
        var swatch = $('<span class="wm-traffic-swatch">').appendTo(band);
        if (/^#[0-9a-f]{6}$/i.test(row.colour)) {
            swatch.css('background-color', row.colour);
            if (/^#[0-9a-f]{6}$/i.test(row.end)) swatch.css('background-image', 'linear-gradient(to right, ' + row.colour + ', ' + row.end + ')');
        }
        $('<span>').text(row.bottom === row.top ? row.bottom : row.bottom + '–' + row.top).appendTo(band);
    });
    if (!preview.children().length) preview.text(wmEditorText.colourText3);
}
function wmOpenColours() {
	$('#wm-colours-error').empty(); $('#wm-colours-save').prop('disabled', true);
	wmColourRequest('colour_data', {}, function(data) {
		$('#wm-traffic-pulse').prop('checked', data.colours.pulse === true);
		$('#wm-outline-colour').empty().append(wmAssetColour(data.colours.outline, false, true, false, wmEditorText.borderColour));
		$('#wm-scale-rows').empty(); data.colours.rows.forEach(wmAssetScaleRow);
        $('#wm-traffic-advanced').prop('open', false);
        $('#wm-traffic-preset').val(wmTrafficPresetMatch(data.colours.rows)).off('change selectmenuchange').on('change selectmenuchange', function() {
            $('#wm-traffic-advanced').prop('open', this.value === 'custom');
            if (this.value !== 'custom') {
                var rows = wmTrafficPresetRows(this.value, wmAssetScaleRows());
                $('#wm-scale-rows').empty(); rows.forEach(wmAssetScaleRow);
            }
            wmTrafficPreview();
        });
        $('#wm-scale-rows').off('input.wmPreset change.wmPreset click.wmPreset').on('input.wmPreset change.wmPreset click.wmPreset', function() {
            $('#wm-traffic-preset').val(wmTrafficPresetMatch(wmAssetScaleRows())).selectmenu('refresh'); wmTrafficPreview();
        });
        $('#wm-traffic-preset').selectmenu('refresh');
        wmTrafficPreview();
		$('#dlgColours').dialog('option', {maxHeight: $(window).height() - 80, position: {my: 'center', at: 'center', of: window}});
		$('#dlgColours').off('keydown.wmColourSave').on('keydown.wmColourSave', 'input', function(event) {
			if (event.key === 'Enter' && (this.type === 'text' || this.type === 'number')) { event.preventDefault(); event.stopPropagation(); $('#wm-colours-save').trigger('click'); }
		});
		$('#wm-colours-save').prop('disabled', false).off('click').on('click', function() {
			var rows = wmAssetScaleRows();
			wmColourRequest('set_map_colours', {colours_json: JSON.stringify({outline: $('#wm-outline-colour .wm-colour-value').val(), pulse: $('#wm-traffic-pulse').prop('checked'), rows: rows})}, function() { hide_all_dialogs(); wmColourRefreshMap(); }, 'wm-colours-error');
		});
	}, 'wm-colours-error');
	$('#wm-scale-add').off('click').on('click', function() { wmAssetScaleRow({scale: 'DEFAULT', bottom: 0, top: 100, colour: '#0000ff'}); $('#wm-traffic-preset').val('custom').selectmenu('refresh'); wmTrafficPreview(); });
}
