// global variable for subwindow reference

var newWindow;

// seed the help text. Done in a big lump here, so we could make a foreign language version someday.

var helptexts = {
	'link_target': 'Where should Weathermap get data for this link? This can either be an RRD file, or an HTML with special comments in it (normally from MRTG).',
	'link_width': 'How wide the link arrow will be drawn, in pixels.',
	'link_infourl': 'If you are using the \'overlib\' HTML style then this is the URL that will be opened when you click on the link',
	'link_hover': 'If you are using the \'overlib\' HTML style then this is the URL of the image that will be shown when you hover over the link',
	'link_bandwidth_in': 'The bandwidth from the first node to the second node',
	'link_bandwidth_out': 'The bandwidth from the second node to the first node (if that is different)',
	'link_commentin': 'The text that will appear alongside the link',
	'link_commentout': 'The text that will appear alongside the link',
	'node_infourl': 'If you are using the \'overlib\' HTML style then this is the URL that will be opened when you click on the node',
	'node_hover': 'If you are using the \'overlib\' HTML style then this is the URL of the image that will be shown when you hover over the node',
	'node_x': 'How far from the left to position the node, in pixels',
	'node_y': 'How far from the top to position the node, in pixels',
	'node_label': 'The text that appears on the node',
	'node_new_name': 'The name used for this node when defining links',
	'tb_newfile': 'Change to a different file, or start creating a new one.',
	'tb_addnode': 'Add a new node to the map',
	'tb_addlink': 'Add a new link to the map, by joining two nodes together.',
	'hover_tb_newfile': 'Select a different map to edit, or start a new one.',

	// These are the default text - what appears when nothing more interesting
	// is happening. One for each dialog/location.
	'link_default': 'This is where help appears for links',
	'map_default': 'This is where help appears for maps',
	'node_default': 'This is where help appears for nodes',
	'tb_default': 'or click a Node or Link to edit it\'s properties'
};

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

	$('#frmMain').submit(function(event) {
		event.preventDefault();
		form_submit();
	});

	initContextMenu();
}

function initContextMenu() {
	var nodeMenu = [
		{title: "Node Actions", cmd: "cat1", isHeader: true},
		{title: 'Move', cmd: 'move', uiIcon: 'ui-icon-arrow-4'},
		{title: 'Clone', cmd: 'clone', uiIcon: 'ui-icon-copy'},
		{title: 'Edit', cmd: 'edit', uiIcon: 'ui-icon-pencil'},
		{title: 'Delete', cmd: 'delete', uiIcon: 'ui-icon-trash'},
		{title: "----"},
		{title: 'Properties', cmd: 'properties', uiIcon: 'ui-icon-gear'}
	];

	var linkMenu = [
		{title: "Link Actions", cmd: "cat1", isHeader: true},
		{title: 'Tidy', cmd: 'tidy', uiIcon: 'ui-icon-arrow-4'},
		{title: 'Via', cmd: 'via', uiIcon: 'ui-icon-copy'},
		{title: 'Edit', cmd: 'edit', uiIcon: 'ui-icon-pencil'},
		{title: 'Delete', cmd: 'delete', uiIcon: 'ui-icon-trash'},
		{title: "----"},
		{title: 'Properties', cmd: 'properties', uiIcon: 'ui-icon-gear'}
	];

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
			} else {
				$(this).contextmenu('replaceMenu', nodeMenu);
			}
		}
	});
}

function contextAction(event, ui) {
	var alt;

	if (ui.cmd == 'properties') {
		alt = ui.target[0].id;

		click_execute(event, alt);
	}
}

function cleanupJS() {
    // This should be cleaning up all the handlers we added in initJS, to avoid killing
    // IE/Win and Safari (at least) over a period of time with memory leaks.
}

function attach_click_events() {
	$("area[id^='LINK:']").attr('href', '#').click(click_handler);
	$("area[id^='NODE:']").attr('href', '#').click(click_handler);
	$("area[id^='TIMES']").attr('href', '#').click(position_timestamp);
	$("area[id^='LEGEN']").attr('href', '#').click(position_legend);

	if (fromplug === 1) {
		$('#tb_newfile').html('Return to<br>Cacti').click(function() {
			window.location = 'weathermap-cacti-plugin-mgmt.php';
		});
	} else {
		$('#tb_newfile').click(new_file);
	}

	$('#tb_addnode').click(add_node);
	$('#tb_mapprops').click(map_properties);
	$('#tb_mapstyle').click(map_style);

	$('#tb_addlink').click(add_link);
	$('#tb_poslegend').click(position_first_legend);
	$('#tb_postime').click(position_timestamp);
	$('#tb_colours').click(manage_colours);

	$('#tb_manageimages').click(manage_images);
	$('#tb_prefs').click(prefs);

	$('#node_move').click(move_node);
	$('#node_delete').click(delete_node);
	$('#node_clone').click(clone_node);
	$('#node_edit').click(edit_node);

	$('#link_delete').click(delete_link);
	$('#link_edit').click(edit_link);

	$('#link_tidy').click(tidy_link);

	$('#link_via').click(via_link);

	$('.wm_submit').click(form_submit);
	$('.wm_cancel').click(cancel_op);

	$('#link_cactipick').click(cactipicker).attr('href','#');
	$('#node_cactipick').click(nodecactipicker).attr('href','#');

	$('#xycapture').mouseover(function(event) {
		coord_capture(event);
	});

	$('#xycapture').mousemove(function(event) {
		coord_update(event);
	});

	$('#xycapture').mouseout(function(event) {
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
function click_handler(event) {
	var alt;

	alt = $(this).attr('id');

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
			// objectid = objectname.slice(0,objectname.length-2);
			objectname = NodeIDs[objectid];

			show_node(objectname);
		}

		if (objecttype == 'LINK') {
			// chop off the suffix
			// objectid = objectname.slice(0,objectname.length-2);
			objectname = LinkIDs[objectid];

			show_link(objectname);
		}
	} else {
		// we've got a command queued, so do the appropriate thing
		if (objecttype == 'NODE' && $('#action').val() == 'add_link') {
			$('#param').val(NodeIDs[objectid]);
			form_submit();
		} else if (objecttype == 'NODE' && $('#action').val() == 'add_link2') {
			$('#param').val(NodeIDs[objectid]);
			form_submit();
		} else {
			// Halfway through one operation, the user has done something unexpected.
			// reset back to standard state, and see if we can oblige them
			//		alert('A bit confused');
			$('#action').val('');

			hide_all_dialogs()

			click_handler(event);
		}
	}
}

function cactipicker() {
	// make sure it isn't already opened
	if (!newWindow || newWindow.closed) {
		newWindow = window.open('', 'cactipicker', 'scrollbars=1,status=1,height=400,width=400,resizable=1');
	} else if (newWindow.focus) {
		// window is already open and focusable, so bring it to the front
		newWindow.focus();
	}

	// newWindow.location = 'cacti-pick.php?command=link_step1';
	newWindow.location = 'cacti-pick.php?command=link_step1';
}

function nodecactipicker() {
	// make sure it isn't already opened
	if (!newWindow || newWindow.closed) {
		newWindow = window.open('', 'cactipicker', 'scrollbars=1,status=1,height=400,width=400,resizable=1');
	} else if (newWindow.focus) {
		// window is already open and focusable, so bring it to the front
		newWindow.focus();
	}

	newWindow.location = 'cacti-pick.php?command=node_step1';
}

function show_context_help(itemid, targetid) {
	//    var itemid = item.id;
	var helpbox, helpboxtext, message;
	//    var ct = document.getElementById(targetid);
	//    if (ct)
	//  {
	message = "We'd show helptext for " + itemid + " in the'" + targetid + "' div";
	// }

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

function default_toolbar() {
}

function working_toolbar() {
}

function new_file() {
	self.location = '?action=newfile';
}

function mapmode(m) {
	if (m == 'xy') {
		$('#debug').val('xy');
		$('#xycapture').css('display', 'inline');
		$('#existingdata').css('display', 'none');
	} else if (m == 'existing') {
		$('#debug').val('existing');
		$('#xycapture').css('display', 'none');
		$('#existingdata').css('display', 'inline');
	} else {
		alert('invalid mode');
	}
}

function add_node() {
	$('#tb_help').text('Click on the map where you would like to add a new node.');
	$('#action').val('add_node');

	mapmode('xy');
}

function delete_node() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	$('.dlgConfirm').text('WARNING: Pressing \'Delete Node\' will delete this Node and any Links its a part of.');

	mapmode('xy');

	$('.dlgConfirm').dialog({
		resizable: false,
		title: 'Delete Node Confirmation',
		height: 'auto',
		width: 400,
		modal: true,
		buttons: {
			Cancel: function() {
				$(this).dialog('close');
			},
			'Delete Node': function() {
				$(this).dialog('close');
				hide_all_dialogs();
				$('#action').val('delete_node');
				form_submit();
			}
		}
	});
}

function clone_node() {
	$('#action').val('clone_node');
	form_submit();
}

function edit_node() {
	$('#action').val('edit_node');

	show_itemtext('node', $('#node_name').val());
	// document.frmMain.submit();
}

function edit_link() {
	$('#action').val('edit_link');

	show_itemtext('link', $('#link_name').val());
	// document.frmMain.submit();
}

function move_node() {
	hide_dialog('dlgNodeProperties');

	$('#tb_help').text('Click on the map where you would like to move the node to.');
	$('#action').val('move_node');

	mapmode('xy');
}

function via_link() {
	hide_dialog('dlgLinkProperties');

	$('#tb_help').text('Click on the map via which point you whant to redirect link.');
	$('#action').val('via_link');

	mapmode('xy');
}

function add_link() {
	$('#tb_help').text('Click on the first node for one end of the link.');
	$('#action').val('add_link');

	mapmode('existing');
}

function delete_link() {
	if ($('.dlgConfirm').length == 0) {
		$('body').append('<div class="dlgConfirm"></div>');
	}

	$('.dlgConfirm').text('WARNING: Pressing \'Delete Link\' will delete this Link.');

	mapmode('xy');

	$('.dlgConfirm').dialog({
		resizable: false,
		title: 'Delete Link Confirmation',
		height: 'auto',
		width: 400,
		modal: true,
		buttons: {
			Cancel: function() {
				$(this).dialog('close');
			},
			'Delete Node': function() {
				$(this).dialog('close');
				hide_all_dialogs();
				$('#action').val('delete_link');
				form_submit();
			}
		}
	});
}

function form_submit() {
	var data = $('input, select, textarea').serialize();

	$.ajax({
		type: 'POST',
		url: editor_url,
		data: data,
		success: function(html) {
			console.log('done');

			var htmlObject  = $(html);

			if (htmlObject != null) {
				var newhtml = htmlObject.filter('#mainView').html();

				if (newhtml != null) {
					$('#mainView').fadeOut('fast').empty().html(newhtml).fadeIn('fast');;
				} else {
					var url = editor_url + '?action=nothing&mapname=' + $('#mapname').val();
					document.location = url;
				}
			}
		}
	});
}

function loadPage(href) {
	$.get(href)
	.done(function(html) {
		$(document).replaceWith(html);
	})
	.fail(function(html) {
		console.log('failed');
	});

	return false;
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

	//$('#fontsamples').attr('src', '?action=fontsamples&mapname='+$('#mapname').val();

	show_dialog('dlgMapStyle');

	$('#mapstyle_linklabels').focus();
}

function position_timestamp() {
	$('#tb_help').text('Click on the map where you would like to put the timestamp.');
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

	// we need to figure out WHICH legend, nowadays
	//alt = el.getAttribute('alt');
	alt = el.id;

	// objecttype = alt.slice(0, 5);
	objectname = alt.slice(7, alt.length);

	real_position_legend(objectname);

	//document.getElementById('tb_help').innerText = 'Click on the map where you would like to put the legend.';
	//document.getElementById('action').value = 'place_legend';
	//document.getElementById('param').value = objectname;
	//mapmode('xy');
}

function real_position_legend(scalename) {
	$('#tb_help').text('Click on the map where you would like to put the legend.');
	$('#action').val('place_legend');
	$('#param').val(scalename);

	mapmode('xy');
}

function show_itemtext(itemtype,name) {
	var found = -1;

	mapmode('existing');

	hide_all_dialogs();

	// $('#dlgNodeProperties').block();

	//  $.blockUI.defaults.elementMessage = 'Please Wait';

	$('textarea#item_configtext').val('');

	if (itemtype === 'node') {
		$('#action').val('set_node_config');
	}

	if (itemtype === 'link') {
		$('#action').val('set_link_config');
	}

	show_dialog('dlgTextEdit');

	// $('#item_configtext').block();

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
			//  $('#dlgTextEdit').unblock();
		}
	});
}

function show_node(name) {
	var found = -1;

	mapmode('existing');

	hide_all_dialogs();

	var mynode = Nodes[name];

	if (mynode) {
		$('#action').val('set_node_properties');
		$('#node_name').val(name);
		$('#node_new_name').val(name);

		$('#node_x').val(mynode.x);
		node_y.value = mynode.y;

		$('#node_name').val(mynode.name);
		$('#node_new_name').val(mynode.name);
		$('#node_label').val(mynode.label);
		$('#node_infourl').val(mynode.infourl);
		$('#node_hover').val(mynode.overliburl);

		var selectedNode;

		if (mynode.iconfile != '') {
			// console.log(mynode.iconfile.substring(0,2));
			if (mynode.iconfile.substring(0,2) == '::') {
				$('node_iconfilename').val('--AICON--');
				selectedNode = '--AICON--';
			} else {
				$('node_iconfilename').val(mynode.iconfile);
				selectedNode = mynode.iconfile;
			}
		} else {
			$('node_iconfilename').val('--NONE--');
			selectedNode = '--NONE--';
		}

		if ($('#node_iconfilename.dd-container').length) {
			$('#node_iconfilename').ddslick('destroy');
		}

		$('#node_iconfilename').val(selectedNode).ddslick({
			height:120,
			defaultSelectedIndex:selectedNode
		});

		$('.dd-container').click(function() {
			$('.ui-dialog').css('z-index', '100');
			$('.dd-options, .dd-container').css('z-index', '500');
		});

		// save this here, just in case they choose delete_node or move_node
		$('#param').val(mynode.name);

		show_dialog('dlgNodeProperties');

		$('#node_new_name').focus();
	}
}

function show_link(name) {
	var found = -1;

	mapmode('existing');

	hide_all_dialogs();

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
			height:120,
			defaultSelectedIndex:selectedNode
		});

		$('.dd-container').click(function() {
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
		$('#'+dlg).dialog('close').dialog('destroy');
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
	var cursorx = event.pageX;
	var cursory = event.pageY;

	// Adjust for coords relative to the image, not the document
	if ($('#xycapture').is(':visible')) {
		var p = $('#xycapture').offset();
	} else {
		var p = $('#existing').offset();
	}

	cursorx -= parseInt(p.left);
	cursory -= parseInt(p.top);
	cursory++; // fudge to make coords match results from imagemap (not sure why this is needed)

	$('#x').val(cursorx);
	$('#y').val(cursory);

	$('#tb_coords').html('Position<br />'+ cursorx + ', ' + cursory);
}

function coord_release(event) {
	$('#tb_coords').html('Position<br />---, ---');
}

function tidy_link() {
	$('#action').val('link_tidy');
	form_submit();
}

function attach_help_events() {
	// add an onblur/onfocus handler to all the visible <input> items

	$('input').focus(help_handler).blur(help_handler);
}

