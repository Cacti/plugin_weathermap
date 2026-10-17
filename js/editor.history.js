/*
 * Copyright (C) 2004-2026 The Cacti Group.
 * Licensed under the GNU General Public License, version 2 or later.
 */
(function(root) {
	'use strict';

	function init($, win) {
		if (!$('#wm-undo').length || typeof win.WMConfigRevision !== 'string') return;
		var id, pending = 0, state = {undo: 0, redo: 0};
		var text = win.wmEditorText;
		try {
			var storageKey = 'weathermap-history:' + win.location.pathname;
			id = win.sessionStorage.getItem(storageKey);
			if (!/^[a-f0-9]{32}$/.test(id || '')) {
				id = Array.from(win.crypto.getRandomValues(new Uint8Array(16)), function(byte) { return byte.toString(16).padStart(2, '0'); }).join('');
				win.sessionStorage.setItem(storageKey, id);
			}
		} catch (error) {
			$('#wm-undo,#wm-redo').attr('title', text.historyStorage);
			return;
		}

		function render() {
			['undo', 'redo'].forEach(function(direction) {
				var label = direction === 'undo' ? text.historyUndo : text.historyRedo;
				$('#wm-' + direction).prop('disabled', pending > 0 || !state[direction])
					.attr('title', state[direction] ? label + ': ' + state[direction + 'Label'] : text.historyEmpty);
			});
			$('#wm-history-status').text(text.historyAvailable.replace('%s', state.undo).replace('%s', state.redo));
		}

		function request(action) {
			var values = {action: action, mapname: $('#mapname').val(), config_revision: win.WMConfigRevision};
			if (typeof win.csrfMagicToken !== 'undefined') values.__csrf_magic = win.csrfMagicToken;
			return $.ajax({url: win.editor_url, type: 'POST', dataType: 'json', data: values});
		}

		// All editor save paths, including Manage Colors, receive the tab's history ID.
		$.ajaxPrefilter(function(options, original, xhr) {
			var destination = new URL(options.url, win.location.href);
			var editor = new URL(win.editor_url, win.location.href);
			if (options.type.toUpperCase() !== 'POST' || destination.href !== editor.href || options.processData === false) return;
			var data = new URLSearchParams(options.data || '');
			data.set('history_id', id);
			options.data = data.toString();
			var action = data.get('action');
			if (['history_status', 'graphs', 'datasources', 'image_data', 'colour_data', 'delete_image'].indexOf(action) !== -1) return;
			pending++;
			render();
			xhr.always(function() { pending--; render(); });
		});

		function apply(direction) {
			if (pending || !state[direction]) return;
			if ($('.ui-dialog-content:visible').length) {
				win.alert(text.historyDialog);
				return;
			}
			request(direction).done(function() {
				// Always fetch a new document, including when the current URL has a fragment.
				var url = new URL(win.location.href);
				url.searchParams.set('mapname', $('#mapname').val());
				url.searchParams.set('action', 'nothing');
				url.searchParams.delete('config_revision');
				url.searchParams.delete('__csrf_magic');
				url.hash = '';
				if (win.location.href.split('#')[0] === url.href) win.location.reload();
				else win.location.replace(url.href);
			}).fail(function(xhr) {
				state = {undo: 0, redo: 0};
				render();
				win.alert(xhr.responseJSON && xhr.responseJSON.error || text.saveError);
			});
		}

		$('#wm-undo').on('click.wmhistory', function() { apply('undo'); });
		$('#wm-redo').on('click.wmhistory', function() { apply('redo'); });
		$(win.document).on('keydown.wmhistory', function(event) {
			if (!(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== 'z') return;
			if ($(event.target).closest('input,textarea,select,[contenteditable]:not([contenteditable="false"])').length || $('.ui-dialog-content:visible').length) return;
			var direction = event.shiftKey ? 'redo' : 'undo';
			if (!pending && state[direction]) { event.preventDefault(); apply(direction); }
		});
		request('history_status').done(function(data) { state = data; render(); })
			.fail(function() { $('#wm-undo,#wm-redo').attr('title', text.saveError); });
	}

	if (typeof module !== 'undefined' && module.exports) module.exports = {init: init};
	else root.jQuery(function() { init(root.jQuery, root); });
})(typeof window !== 'undefined' ? window : globalThis);
