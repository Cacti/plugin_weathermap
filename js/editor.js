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

$(document).on('unload', cleanupJS);

$(function() {
	initJS();
});

/** wmApplyEditorTheme - The standalone map editor only loads a theme's
 *  jquery-ui.css and main.css, not Cacti's page loader. editor.css is written
 *  against the standard Cacti colour variables (--background,
 *  --background-content, --text-color-lighter, --border-color). Only the
 *  Midwinter theme actually defines them, so every other theme fell back to the
 *  hard-coded light values and dark themes rendered light form controls.
 *
 *  When the active theme already defines those variables we leave them alone so
 *  the theme keeps full control of the editor. Otherwise we read the theme's
 *  genuine form-field colours and publish them. The field surface and border go
 *  onto the standard variables, but the field text colour is published as a
 *  separate --wm-field-text so it only recolours the form controls; the dialog
 *  hint text keeps inheriting the dialog foreground, which stays readable on
 *  themes (such as Dark) whose fields and dialogs use different colours. */
function wmApplyEditorTheme() {
	var root = document.getElementById('mainView');

	if (!root || typeof window.getComputedStyle !== 'function') {
		return;
	}

	// If the theme already supplies the Cacti palette (Midwinter), it themes the
	// editor itself; don't overwrite its carefully chosen light/dark values.
	if (window.getComputedStyle(root).getPropertyValue('--text-color-lighter').trim() !== '') {
		return;
	}

	// Probe a plain text input that editor.css does not override, so we read the
	// theme's genuine field colours rather than editor.css fallback colours.
	var probe = document.createElement('div');
	probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;visibility:hidden;pointer-events:none';

	var input = document.createElement('input');
	input.type = 'text';
	probe.appendChild(input);
	document.body.appendChild(probe);

	var style   = window.getComputedStyle(input);
	var surface = style.backgroundColor;
	var text    = style.color;
	var border  = style.borderTopColor;

	document.body.removeChild(probe);

	var usable = function(colour) {
		return colour && colour !== 'transparent' &&
			colour.replace(/\s+/g, '') !== 'rgba(0,0,0,0)';
	};

	if (usable(surface)) {
		root.style.setProperty('--background', surface);
		root.style.setProperty('--background-content', surface);
	}

	if (usable(text)) {
		// A control-only variable, so dialog hint text is left to inherit the
		// readable dialog foreground instead of the field text colour.
		root.style.setProperty('--wm-field-text', text);
	}

	if (usable(border)) {
		root.style.setProperty('--border-color', border);
	}
}

/** wmTwoLineLabel - Render a toolbar label on two lines without an HTML string,
 *  keeping all but the final word together on the first line. The toolbar
 *  buttons are capped narrow, so a plain multi-word label (e.g. the dynamic
 *  "Return to Map"/"Return to Manage") otherwise wrapped onto three lines. */
function wmTwoLineLabel($el, text) {
	var words = String(text).split(/\s+/).filter(function(word) {
		return word.length > 0;
	});

	$el.empty();

	if (words.length > 1) {
		var last = words.pop();
		$el.append(document.createTextNode(words.join('\u00a0')));
		$el.append(document.createElement('br'));
		$el.append(document.createTextNode(last));
	} else {
		$el.text(text);
	}

	return $el;
}

function initJS() {
	wmApplyEditorTheme();

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
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">');
	html.text(org.val() || org.text());
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

// Some WebKit layouts route image-map clicks to the image after scrolling.
// Resolve those clicks against the same ordered, logical-coordinate regions.
function wmEditorAreaAt(image, event) {
	var rect = image.getBoundingClientRect();
	if (!rect.width || !rect.height) return null;
	var x = (event.clientX - rect.left) * Number(image.getAttribute('data-width')) / rect.width;
	var y = (event.clientY - rect.top) * Number(image.getAttribute('data-height')) / rect.height;
	var map = document.getElementById((image.getAttribute('usemap') || '').split('#').pop());
	if (!map) return null;
	return Array.from(map.querySelectorAll('area')).find(function(area) {
		var c = area.coords.split(',').map(Number), inside = false;
		if (!c.length || !c.every(Number.isFinite)) return false;
		if (area.shape.toLowerCase() === 'rect' && c.length === 4) {
			return x >= c[0] && x <= c[2] && y >= c[1] && y <= c[3];
		}
		if (area.shape.toLowerCase() === 'circle' && c.length === 3) {
			return Math.pow(x - c[0], 2) + Math.pow(y - c[1], 2) <= c[2] * c[2];
		}
		if (area.shape.toLowerCase() === 'poly' && c.length >= 6 && c.length % 2 === 0) {
			for (var i = 0, j = c.length - 2; i < c.length; j = i, i += 2) {
				if ((c[i + 1] > y) !== (c[j + 1] > y) && x < (c[j] - c[i]) * (y - c[i + 1]) / (c[j + 1] - c[i + 1]) + c[i]) inside = !inside;
			}
		}
		return inside;
	}) || null;
}

function attach_click_events() {
	$('#existingdata').off('.wmhit').on('click.wmhit', function(event) {
		if (event.target !== this) return;
		var area = wmEditorAreaAt(this, event);
		if (area) {
			event.preventDefault();
			$(area).triggerHandler('click');
		}
	}).on('mousemove.wmhit', function(event) {
		if (!$(this).hasClass('wm-placement-mode')) this.style.cursor = wmEditorAreaAt(this, event) ? 'pointer' : '';
	});
	$('#xycapture').off('click.wmnote').on('click.wmnote', function(event) {
		if ($('#action').val() === 'place_note' || ($('#action').val() === 'move_node' && typeof WMNotes !== 'undefined' && WMNotes[$('#param').val()])) coord_update(event);
	});
	$("area[id^='LINK:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='NODE:']").attr('href', '#').off('click').on('click', click_handler);
	$("area[id^='TIMES']").attr('href', '#').off('click').on('click', position_timestamp);
	$("area[id^='LEGEN']").attr('href', '#').off('click').on('click', position_legend);

	wmTwoLineLabel($('#tb_newfile'), $('body').attr('data-return-label') || 'Return to Map').off('click').on('click', function() {
		window.location.assign(wmEditorReturnUrl($('body').attr('data-return-map')));
	});

	$('#tb_addnode').off('click').on('click', add_node);
	$('#tb_mapprops').off('click').on('click', map_properties);
	$('#tb_mapstyle').off('click').on('click', map_style);

	$('#tb_addlink').off('click').on('click', add_link);
	$('#tb_addnote').off('click').on('click', add_note);
	$('.wm-note-colour').off('click').on('click', function() {
		$('#note_colour').val($(this).attr('data-colour'));
		wmNoteRefreshPalette();
	});
	$('#note_colour').off('input change').on('input change', wmNoteRefreshPalette);
	$('#note_advanced_toggle').off('click').on('click', function() {
		var open = $(this).attr('aria-expanded') !== 'true';
		$(this).attr('aria-expanded', String(open)).text(open ? wmEditorText.hideAdvanced : wmEditorText.advanced);
		$('#note_advanced').toggle(open);
	});
	$('#note_clone').off('click').on('click', function() {
		$('#note_name').val('');
		$('#note_x').val(Math.min(parseInt($('#note_x').attr('max'), 10), parseInt($('#note_x').val(), 10) + 20));
		$('#note_y').val(Math.min(parseInt($('#note_y').attr('max'), 10), parseInt($('#note_y').val(), 10) + 20));
		$('#note_delete,#note_move,#note_clone').hide();
		$('#note_text').trigger('focus');
	});
	$('#note_move').off('click').on('click', function() {
		$('#param').val($('#note_name').val());
		$('#node_name').val($('#note_name').val());
		hide_dialog('dlgNoteProperties');
		move_node();
	});
	$('#note_delete').off('click').on('click', function() {
		$('#param').val($('#note_name').val());
		$('#node_name').val($('#note_name').val());
		delete_node();
	});
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
function cancel_op(event) {
	if (event && event.currentTarget && event.currentTarget.id === 'tb_textedit_cancel') {
		$('#dlgTextEdit').dialog('close');
		return;
	}
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
	wmOpenImages();
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
	$('#existingdata, .mapData').toggleClass('wm-placement-mode', placing).css('cursor', '');
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
	var isNote = typeof WMNotes !== 'undefined' && Object.prototype.hasOwnProperty.call(WMNotes, name);
	var message = (isNote ? wmEditorText.noteDelete : delNodePrompt).replace('%s', function() { return wmNodeDisplayName(name); });
	if (connected) {
		message += ' ' + delNodeConnected.replace('%s', String(connected));
	}
	$('.dlgConfirm').text(message + (isNote ? '' : ' ' + delNodeKeepDevice));

	if (['add_link', 'add_link2', 'add_node', 'place_note', 'move_node', 'via_link', 'place_legend', 'place_stamp'].indexOf($('#action').val()) !== -1) $('#action').val('');
	mapmode('existing');

	$('.dlgConfirm').dialog({
		close: function() { mapmode('existing'); },
		resizable: false,
		title: isNote ? wmEditorText.noteDeleteTitle : delNodeTitle,
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
				text: isNote ? wmEditorText.noteDeleteAction : txtDelNode,
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
	url.hash = '';
	return url.href;
}

function wmReloadEditor() {
	var url = wmEditorReloadUrl();
	var current = new URL(window.location.href);
	current.hash = '';
	// Same-document fragment navigation does not fetch a fresh map snapshot.
	if (current.href === url) {
		window.location.reload();
	} else {
		window.location.replace(url);
	}
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
	if ($('#action').val() === 'place_note') {
		show_note('', $('#x').val(), $('#y').val());
		return;
	}
	var noteAction = $('#action').val();
	var noteTarget = noteAction === 'set_note_properties' ? $('#note_name').val() : noteAction === 'move_node' ? $('#node_name').val() : $('#param').val();
	var savingNote = noteAction === 'set_note_properties' || (['move_node', 'delete_node'].indexOf(noteAction) !== -1 && typeof WMNotes !== 'undefined' && WMNotes[noteTarget]);
	if ($('#action').val() === 'set_link_properties') {
		wmSyncLinkHoverParts();
		if (wmLinkHoverEntries($('#wm-link-extra-hover').val()).length > 1 || wmLinkHoverEntries($('#link_hover').val()).length > 2) {
			window.alert(wmEditorText.hoverLimit);
			return;
		}
	}
	var data = $('input, select, textarea').serialize();
	if (typeof WMConfigRevision !== 'undefined') data += '&config_revision=' + encodeURIComponent(WMConfigRevision);

	$.ajax({
		type: 'POST',
		url: editor_url,
		data: data,
		error: function(xhr) {
			if (xhr && xhr.status === 409) {
				window.alert(wmEditorText.staleError);
				return;
			}
			if (savingNote) {
				if (noteAction !== 'set_note_properties') show_note(noteTarget);
				$('#wm-note-error').text(wmEditorText.noteError).show();
			} else {
				window.alert(wmEditorText.saveError);
			}
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
	var parent = itemtype === 'link' ? 'dlgLinkProperties' : 'dlgNodeProperties';
	var returnTo = $('#' + parent).dialog('instance') && $('#' + parent).dialog('isOpen') ? parent : null;
	mapmode('existing');

	hide_all_dialogs();

	$('textarea#item_configtext').val('');

	if (itemtype === 'node') {
		$('#action').val('set_node_config');
	}

	if (itemtype === 'link') {
		$('#action').val('set_link_config');
	}

	$('#dlgTextEdit').data('wm-config-action', $('#action').val()).data('wm-config-parent', returnTo);
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

// Use existing relative LABELOFFSET syntax so positions scale with the icon.
function wmPrimeNodeLabelPosition(node) {
 var offset = String(node.labeloffset || '').toUpperCase();
 var position = (!offset || offset === 'C') && !Number(node.labeloffsetx || 0) && !Number(node.labeloffsety || 0) ? 'C' :
  (['N60', 'S60'].includes(offset) && node.labelposition === offset && !Number(node.labeloffsetx || 0) && !Number(node.labeloffsety || 0) ? offset : 'keep');
 $('#node_labelposition option[value="keep"]').prop('hidden', position !== 'keep');
 $('#node_labelposition').val(position).trigger('change.select2');
 if ($('#node_labelposition').data('ui-selectmenu')) $('#node_labelposition').selectmenu('refresh');
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
		wmPrimeNodeLabelPosition(mynode);
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
	if (typeof WMNotes !== 'undefined' && Object.prototype.hasOwnProperty.call(WMNotes, name)) {
		show_note(name);
		return;
	}
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

		$('#link_commentstyle').val(mylink.commentstylechoice);
		$('#link_labelposmode').val(mylink.labelposmode);
		$('#link_labelposin').val(mylink.labelposin);
		$('#link_labelposout').val(mylink.labelposout);
		$('#link_labelposmode').trigger('change');
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

// Map JavaScript HTML-escapes URL values; undo that when populating text fields.
function wmDecodeEditorUrl(value) {
	return String(value || '').replace(/&amp;/g, '&');
}

// Notes are ordinary label-only nodes, with a small editor-specific style form.
function add_note() {
	hide_all_dialogs();
	$('#tb_help').addClass('wm-action-help').text(wmEditorText.addNoteHelp);
	$('#action').val('place_note');
	mapmode('xy');
}
function wmNoteRefreshPalette() {
	var colour = String($('#note_colour').val()).toLowerCase();
	$('.wm-note-colour').each(function() {
		var selected = $(this).attr('data-colour') === colour;
		$(this).attr('aria-pressed', selected ? 'true' : 'false').css('outline', selected ? '2px solid currentColor' : '');
	});
}
function show_note(name, x, y) {
	hide_all_dialogs();
	mapmode('existing');
	var node = name ? Nodes[name] : null;
	var style = name ? WMNotes[name] : null;
	$('#note_name').val(name);
	$('#note_align').val(style && ['left', 'centre', 'right'].indexOf(style.align) !== -1 ? style.align : 'left');
	$('#note_text').val(node ? (style && typeof style.text === 'string' ? style.text : node.label.replace(/\\n/g, '\n')) : '');
	$('#note_x').val(node ? node.x : x);
	$('#note_y').val(node ? node.y : y);
	$('#note_colour').val(style ? style.colour : '#000000');
	wmNoteRefreshPalette();
	$('#note_advanced').hide();
	$('#note_advanced_toggle').attr('aria-expanded', 'false').text(wmEditorText.advanced);
	if (style) {
		if (!$('#note_font option').filter(function() { return this.value === style.font; }).length) {
			$('<option>').val(style.font).text(style.fontLabel).appendTo('#note_font');
		}
		$('#note_font').val(style.font);
	} else {
		var defaultFont = $('#note_font option').filter(function() { return this.value === 'bundled:Vera.ttf:9' || $(this).attr('data-wm-note-default') === '1'; }).first();
		$('#note_font').val(defaultFont.length ? defaultFont.val() : '3');
	}
	$('#note_delete,#note_move,#note_clone').toggle(!!node);
	$('#wm-note-error').hide();
	$('#action').val('set_note_properties');
	show_dialog('dlgNoteProperties');
	$('#note_font,#note_align').each(function() { if ($(this).data('ui-selectmenu')) $(this).selectmenu('refresh'); });
	$('#note_text').trigger('focus');
}

// Allow the navigation regression to load these helpers directly under Node.js.
if (typeof module !== 'undefined' && module.exports) {
	module.exports = {wmEditorReloadUrl: wmEditorReloadUrl, wmReloadEditor: wmReloadEditor};
}
