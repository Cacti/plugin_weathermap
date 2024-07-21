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
			var infourl = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';

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

					if (einfo == '') {
						$('#link_infourl').val(infourl + local_graph_id);
					}
				}
			} else {
				var hover   = 'graph_image.php?local_graph_id=';;
				var infourl = 'graph_view.php?action=preview&reset=true&style=selective&graph_list=';

				if (id == 'link_picker') {
					var target = $('#' + id).val();
					var ehover = $('#link_hover').val();
					var einfo  = $('#link_infourl').val();

					if (einfo == '') {
						einfo = infourl;
					}

					$('#link_hover').val(ehover + (ehover != '' ? ' ':'') + hover + target);
					$('#link_infourl').val(einfo + (einfo != '' ? ',':'') + target);
				} else if (id == 'node_picker') {
					var target = $('#' + id).val();
					var ehover = $('#node_hover').val();
					var einfo  = $('#node_infourl').val();

					if (einfo == '') {
						einfo = infourl;
					}

					$('#node_hover').val(ehover + (ehover != '' ? ' ':'') + hover + target);
					$('#node_infourl').val(einfo + (einfo != '' ? ',':'') + target);
				}
			}
		});

		$('#' + id + '_rep').off('click').on('click', function() {
			if (id == 'link_picker') {
				$('#link_hover').val('graph_image.php?local_graph_id=' + $('#' + id).val());
				$('#link_infourl').val('graph_view.php?action=preview&reset=true&style=selective&graph_list=' + $('#' + id).val());
			} else if (id == 'node_picker') {
				$('#node_hover').val('graph_image.php?local_graph_id=' + $('#' + id).val());
				$('#node_infourl').val('graph_view.php?action=preview&reset=true&style=selective&graph_list=' + $('#' + id).val());
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

				var url = 'weathermap-cacti-plugin-editor.php' +
					'?mapname=' + $('#mapname').val() +
					'&action=' + action +
					'&term=' + request.term +
					'&target=' + id +
					'&graph_template_id='+template;

				$.getJSON(url, function(data) {
					response(data);
				});
			},
			autoFocus: true,
			minLength: 0,
			select: function(event, ui) {
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
		attach_click_events();
		attach_help_events();
		//show_context_help('node_label', 'node_help');

		// set the mapmode, so we know where we stand.
		mapmode('existing');
	}

	$('area').draggable();

	$('#frmMain').off('submit').on('submit', function(event) {
		event.preventDefault();
		form_submit();
	});

	$('#node_template').selectmenu().selectmenu('menuWidget').addClass('overflow');

	initContextMenu();

	graphPicker();
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
		{title: txtDelete, cmd: 'delete', uiIcon: 'ui-icon-trash'},
		{title: "----"},
		{title: txtProperties, cmd: 'properties', uiIcon: 'ui-icon-gear'}
	];

	var linkMenu = [
		{title: txtLinkActions, cmd: "cat1", isHeader: true},
		{title: txtTidy, cmd: 'tidy', uiIcon: 'ui-icon-arrow-4'},
		{title: txtVia, cmd: 'via', uiIcon: 'ui-icon-copy'},
		{title: txtEdit, cmd: 'edit', uiIcon: 'ui-icon-pencil'},
		{title: txtDelete, cmd: 'delete', uiIcon: 'ui-icon-trash'},
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

function attach_click_events() {
	$("area[id^='LINK:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='NODE:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='TIMES']").attr('href', '#').off('click').on('click', position_timestamp);
	$("area[id^='LEGEN']").attr('href', '#').off('click').on('click', position_legend);

	$('#tb_newfile').html('Return to<br>Cacti').on('click', function() {
		window.location = 'weathermap-cacti-plugin-mgmt.php';
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
			$('#tb_help').text('Click on the second node for the end of the link.');
		} else if (objecttype == 'NODE' && $('#action').val() == 'add_link2') {
			$('#param2').val(NodeIDs[objectid]);
			form_submit();
		} else {
			// Halfway through one operation, the user has done something unexpected.
			// reset back to standard state, and see if we can oblige them
			//		alert('A bit confused');
			$('#action').val('');
			hide_all_dialogs()
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

function mapmode(m) {
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
	$('#tb_help').text(addNodeHelp);
	$('#action').val('add_node');

	mapmode('xy');
}

function delete_node() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	$('.dlgConfirm').text(delNodeWarning);

	mapmode('xy');

	$('.dlgConfirm').dialog({
		resizable: false,
		title: delNodeTitle,
		height: 'auto',
		width: 400,
		modal: false,
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
				click: function() {
					$(this).dialog('close');
					hide_all_dialogs();
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

	$('#tb_help').text(moveNodeHelp);
	$('#action').val('move_node');

	mapmode('xy');
}

function via_link() {
	hide_dialog('dlgLinkProperties');

	$('#tb_help').text(viaLinkHelp);
	$('#action').val('via_link');

	mapmode('xy');
}

function add_link() {
	$('#tb_help').text(addLinkHelp);
	$('#action').val('add_link');

	mapmode('existing');
}

function delete_link() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	$('.dlgConfirm').text(delLinkWarning);

	mapmode('xy');

	$('.dlgConfirm').dialog({
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

function form_submit() {
	var data = $('input, select, textarea').serialize();

	$.ajax({
		type: 'POST',
		url: editor_url,
		data: data,
		success: function(html) {
			hide_all_dialogs();

			$.get('?action=load_area_data&mapname=' + $('#mapname').val(), function(data) {
				$('.mapData').empty().html(data);

				$.getScript('?action=load_map_javascript&mapname=' + $('#mapname').val(), function(data) {
					var date = new Date();

					// Reload the images to update page
					$('#existingdata').attr('src', $('#existingdata').attr('src') + '&date=' + date.getTime());
					$('#xycapture').attr('src', $('#xycapture').attr('src') + '&date=' + date.getTime());

					$('#action').val('');

					initJS();
				});
			});
		}
	});
}

function map_properties() {
	mapmode('existing');

	hide_all_dialogs();

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
	$('#tb_help').text(timeStHelp);
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
	$('#tb_help').text(posLegendHelp);
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
		$('#node_infourl').val(mynode.infourl);
		$('#node_hover').val(mynode.overliburl);

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

		if ($('#node_iconfilename.dd-container').length) {
			$('#node_iconfilename').ddslick('destroy');
		}

		$('#node_iconfilename').val(selectedNode).ddslick({
			height:240,
			defaultSelectedIndex:selectedNode
		});

		$('.dd-container').on('click', function() {
			$('.ui-dialog').css('z-index', '100');
			$('.dd-options, .dd-container').css('z-index', '500');
		});

		show_dialog('dlgNodeProperties');

		$('#node_new_name').focus();
	} else {
		console.log('Unable to find node');
	}
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

		$('#link_infourl').val(mylink.infourl);
		$('#link_hover').val(mylink.overliburl);

		$('#link_commentin').val(mylink.commentin);
		$('#link_commentout').val(mylink.commentout);
		$('#link_commentposin').val(mylink.commentposin);
		$('#link_commentposout').val(mylink.commentposout);

		// if that didn't 'stick', then we need to add the special value
		if ($('#link_commentposout').val() != mylink.commentposout) {
			$('#link_commentposout').prepend("<option selected value='" + mylink.commentposout + "'>" + mylink.commentposout + "%</option>");
		}

		if ($('#link_commentposin').val() != mylink.commentposin) {
			$('#link_commentposin').prepend("<option selected value='" + mylink.commentposin + "'>" + mylink.commentposin + "%</option>");
		}

		document.getElementById('link_nodename1').firstChild.nodeValue  = mylink.a;
		document.getElementById('link_nodename1a').firstChild.nodeValue = mylink.a;
		document.getElementById('link_nodename1b').firstChild.nodeValue = mylink.a;
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

		show_dialog('dlgLinkProperties');

		$('#link_bandwidth_in').focus();
	}
}

function show_dialog(dlg) {
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
			$('select').not('#node_iconfilename, #map_bgfile').selectmenu({
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

