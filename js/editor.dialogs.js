// Property dialog management for the map editor; loaded after editor.js.

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
		width: dlg === 'dlgImages' ? Math.min(900, $(window).width() - 40) : 600,
		height: 'auto',
		modal: false,
		resizable: false,
		draggable: true,
		close: function() {
			$('#action').val('');
			mapmode('existing');
			if (dlg === 'dlgTextEdit') {
				var parent = $(this).data('wm-config-parent');
				$(this).removeData('wm-config-parent');
				if (parent) {
					show_dialog(parent);
					$('#action').val(parent === 'dlgLinkProperties' ? 'set_link_properties' : 'set_node_properties');
					$(parent === 'dlgLinkProperties' ? '#link_edit' : '#node_edit').attr('tabindex', '-1').trigger('focus');
				}
			}
		},
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
	// Navigation to another task must not reopen the configuration editor's parent.
	$('#dlgTextEdit').removeData('wm-config-parent');
	hide_dialog('dlgMapProperties');
	hide_dialog('dlgMapStyle');
	hide_dialog('dlgLinkProperties');
	hide_dialog('dlgTextEdit');
	hide_dialog('dlgNodeProperties');
	hide_dialog('dlgNoteProperties');
	hide_dialog('dlgColours');
	hide_dialog('dlgImages');
	hide_dialog('dlgEditorSettings');
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {show_dialog: show_dialog, hide_dialog: hide_dialog, hide_all_dialogs: hide_all_dialogs};
}
