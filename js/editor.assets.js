// Image library and colour scale editors for the map editor; loaded after editor.js.

/**
 * Send an image library request, adding the map name, configuration revision
 * and CSRF token.
 *
 * @param {string} action Editor action; image_data is sent as GET, others as POST.
 * @param {Object} extra Additional request fields.
 * @param {function(Object)} callback Success handler.
 * @param {string} errorId Id of the element that shows errors.
 * @returns {void}
 */
function wmAssetRequest(action, extra, callback, errorId) {
	var values = {action: action, mapname: $('#mapname').val()};
	if (action !== 'image_data' && typeof WMConfigRevision !== 'undefined') values.config_revision = WMConfigRevision;
	if (typeof csrfMagicToken !== 'undefined') values.__csrf_magic = csrfMagicToken;
	$.ajax({url: editor_url, type: action === 'image_data' ? 'GET' : 'POST', dataType: 'json', data: $.extend(values, extra),
		success: callback, error: function(xhr) { $('#' + errorId).text(xhr.responseJSON && xhr.responseJSON.error || wmEditorText.imageText3); }});
}

/**
 * Render the image library and refresh the icon and background selects from it.
 *
 * @param {Array<Object>} images Library entries returned by the editor.
 * @returns {void}
 */
function wmAssetImages(images) {
	$('#wm-images-objects, #wm-images-backgrounds').empty();
	images.forEach(function(item) {
		var card = $('<div class="wm-image-card">').appendTo('#wm-images-' + item.kind);
		var src = wmEditorImageSrc(item.url);
		if (src) {
			var image = document.createElement('img');
			image.src = src;
			image.alt = item.name;
			image.loading = 'lazy';
			card.append(image);
		}
		$('<span>').text(item.name).appendTo(card);
		if (item.width && item.height) $('<span>').text(item.width + ' × ' + item.height + ' px').appendTo(card);
		$('<span>').text(item.users && item.users.length ? wmEditorText.imageUsed.replace('%s', item.users.join(', ')) : wmEditorText.imageUnused).appendTo(card);
		if (item.deletable !== false) $('<button type="button" class="ui-button wm-image-delete">').text(wmEditorText.imageText0).attr('aria-label', wmEditorText.imageText9.replace('%s', item.name)).appendTo(card).on('click', function() {
			if (!window.confirm(wmEditorText.imageText10.replace('%s', item.name))) return;
			wmAssetRequest('delete_image', {image_kind: item.kind, image_name: item.name}, function(data) { wmAssetImages(data.images); }, 'wm-images-error');
		});
	});
	['objects', 'backgrounds'].forEach(function(kind) {
		var count = images.filter(function(item) { return item.kind === kind; }).length;
		$('#wm-images-' + kind + '-count').text(wmEditorText.imageText11.replace('%s', String(count)));
		if (!count) $('#wm-images-' + kind).append($('<p>').text(wmEditorText.imageText1));
	});
	// Rebuild the existing image selectors so uploads are available without reloading the editor.
	[{id: 'node_iconfilename', kind: 'objects', label: wmEditorText.imageText14}, {id: 'map_bgfile', kind: 'backgrounds', label: wmEditorText.imageText15}].forEach(function(spec) {
		var select = $('#' + spec.id), old = select.val();
		var slick = select.data('ddslick');
		if (slick) { old = slick.selectedData && slick.selectedData.value; select.ddslick('destroy'); select = $('#' + spec.id); }
		var current = select.find('option').filter(function() { return this.value === old; }).first().clone();
		select.find('option').filter(function() { return this.value.indexOf('images/' + spec.kind + '/') === 0 && /\.(png|gif|jpe?g)$/i.test(this.value); }).remove();
		if (!select.find('option[value="--NONE--"]').length) select.prepend($('<option>').val('--NONE--').text(spec.label));
		images.filter(function(item) { return item.kind === spec.kind; }).forEach(function(item) {
			select.append($('<option>').val('images/' + item.kind + '/' + item.name).text(item.name).attr({'data-imagesrc': item.url, 'data-description': item.name}));
		});
		if (old && !select.find('option').filter(function() { return this.value === old; }).length) {
			select.append(current.length ? current : $('<option>').val(old).text(old));
		}
		select.val(old);
	});
	$('#dlgImages').dialog('option', {maxHeight: $(window).height() - 80, position: {my: 'center', at: 'center', of: window}});
}

/**
 * Load the image library and bind the upload buttons.
 *
 * @returns {void}
 */
function wmOpenImages() {
	$('#wm-images-error').empty();
	wmAssetRequest('image_data', {}, function(data) { wmAssetImages(data.images); }, 'wm-images-error');
	$('.wm-image-upload').off('click').on('click', function() {
		var button = $(this), kind = button.attr('data-kind'), input = $('#wm-image-file-' + kind);
		var file = input[0].files[0];
		if (!file) { $('#wm-images-error').text(wmEditorText.imageText2); return; }
		var data = new FormData();
		data.append('action', 'upload_image'); data.append('mapname', $('#mapname').val());
		data.append('image_kind', kind); data.append('image_file', file);
		if (typeof WMConfigRevision !== 'undefined') data.append('config_revision', WMConfigRevision);
		if (typeof csrfMagicToken !== 'undefined') data.append('__csrf_magic', csrfMagicToken);
		button.prop('disabled', true);
		$.ajax({url: editor_url, type: 'POST', data: data, dataType: 'json', contentType: false, processData: false,
			success: function(result) { $('#wm-images-error').text(wmEditorText.imageText13.replace('%s', result.name)); input.val(''); wmAssetImages(result.images); },
			error: function(xhr) { $('#wm-images-error').text(xhr.responseJSON && xhr.responseJSON.error || wmEditorText.imageText4); },
			complete: function() { button.prop('disabled', false); }});
	});
}

var wmAssetPalette = {Black: '#000000', White: '#ffffff', Blue: '#0000ff', Red: '#cc0000', Green: '#008000', Purple: '#800080', Orange: '#b35a00', Grey: '#666666', None: 'none'};

/**
 * Build a colour control: a named colour select, optional swatches and a
 * custom colour input.
 *
 * @param {string} value Current colour.
 * @param {boolean} allowEmpty Offer a no-colour option.
 * @param {boolean} swatches Show palette swatch buttons.
 * @param {boolean} solid Omit the None option.
 * @param {string} label Accessible label.
 * @returns {jQuery} The control.
 */
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
var wmLockedScales = [];

/**
 * Send a colour settings request and track the revision the server returns.
 *
 * @param {string} action Editor action; colour_data is sent as GET, others as POST.
 * @param {Object} extra Additional request fields.
 * @param {function(Object)} callback Success handler.
 * @param {string} errorId Id of the element that shows errors.
 * @returns {void}
 */
function wmColourRequest(action, extra, callback, errorId) {
	var values = {action: action, mapname: $('#mapname').val()};
	if (action === 'set_map_colours') values.config_revision = wmColourRevision;
	if (action === 'set_map_colours' && typeof csrfMagicToken !== 'undefined') values.__csrf_magic = csrfMagicToken;
	$.ajax({url: editor_url, type: action === 'colour_data' ? 'GET' : 'POST', dataType: 'json', data: $.extend(values, extra),
		success: function(data) { if (data.revision) wmColourRevision = data.revision; callback(data); }, error: function(xhr) { $('#' + errorId).text(xhr.status === 409 && wmEditorText.staleError || xhr.responseJSON && xhr.responseJSON.error || wmEditorText.colourText4); }});
}

/**
 * Append an editable scale range row, disabled when its scale is shared.
 *
 * @param {Object} row Range with scale, bottom, top, colour, end and tag.
 * @returns {void}
 */
function wmAssetScaleRow(row) {
	var tr = $('<tr>').appendTo('#wm-scale-rows');
	['scale', 'bottom', 'top'].forEach(function(key) {
		$('<td>').append($('<input>').attr('type', key === 'scale' ? 'text' : 'number').attr('step', 'any').attr('maxlength', key === 'scale' ? 1024 : null).attr('aria-label', {scale: wmEditorText.rangeScale, bottom: wmEditorText.rangeFrom, top: wmEditorText.rangeTo}[key]).addClass('wm-range-' + key).val(row[key])).appendTo(tr);
	});
	$('<td>').append(wmAssetColour(row.colour, false, false, false, wmEditorText.rangeColour)).appendTo(tr);
	$('<td>').append(wmAssetColour(row.end || '', true, false, true, wmEditorText.gradientColour)).appendTo(tr);
	$('<td>').append($('<input type="text" class="wm-range-tag">').attr('aria-label', wmEditorText.rangeLabel).val(row.tag || '')).appendTo(tr);
	$('<td>').append($('<button type="button" class="ui-button wm-asset-delete">').text(wmEditorText.colourText2).attr('aria-label', wmEditorText.colourText7).on('click', function() { tr.remove(); })).appendTo(tr);
	if (wmLockedScales.indexOf(row.scale) !== -1) tr.find('input, select, button').prop('disabled', true);
}

/**
 * Reload the editor so the map shows the saved colours.
 *
 * @returns {void}
 */
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

/**
 * Read the scale range rows from the colour dialog.
 *
 * @returns {Array<Object>} Ranges with scale, bottom, top, colour, end and tag.
 */
function wmAssetScaleRows() {
    var rows = [];
    $('#wm-scale-rows tr').each(function() {
        var row = $(this);
        rows.push({scale: row.find('.wm-range-scale').val(), bottom: row.find('.wm-range-bottom').val(), top: row.find('.wm-range-top').val(), colour: row.find('.wm-colour-value').eq(0).val(), end: row.find('.wm-colour-value').eq(1).val(), tag: row.find('.wm-range-tag').val()});
    });
    return rows;
}

/**
 * Replace the DEFAULT scale rows with a traffic preset, keeping other scales.
 *
 * @param {string} key Preset name.
 * @param {Array<Object>} rows Current ranges.
 * @returns {Array<Object>} New ranges, or rows unchanged when the preset is unknown.
 */
function wmTrafficPresetRows(key, rows) {
    if (!wmTrafficPresets[key]) return rows;
    return rows.filter(function(row) { return row.scale !== 'DEFAULT'; }).concat(wmTrafficPresets[key].map(function(band) {
        return {scale: 'DEFAULT', bottom: band[0], top: band[1], colour: band[2], end: '', tag: ''};
    }));
}

/**
 * Name the preset that exactly matches the DEFAULT rows.
 *
 * @param {Array<Object>} rows Current ranges.
 * @returns {string} Preset name, or 'custom'.
 */
function wmTrafficPresetMatch(rows) {
    var current = rows.filter(function(row) { return row.scale === 'DEFAULT'; });
    return Object.keys(wmTrafficPresets).find(function(key) {
        var preset = wmTrafficPresets[key];
        return current.length === preset.length && preset.every(function(band) {
            return current.some(function(row) { return Number(row.bottom) === band[0] && Number(row.top) === band[1] && row.colour.toLowerCase() === band[2] && !row.end && !row.tag; });
        });
    }) || 'custom';
}

/**
 * Render a swatch preview of the DEFAULT scale bands.
 *
 * @returns {void}
 */
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

/**
 * Load the map's colour settings into the colour dialog and bind its preset,
 * edit and save controls.
 *
 * @returns {void}
 */
function wmOpenColours() {
	$('#wm-colours-error').empty(); $('#wm-colours-save').prop('disabled', true);
	wmColourRequest('colour_data', {}, function(data) {
		$('#wm-traffic-pulse').prop('checked', data.colours.pulse === true);
		$('#wm-outline-colour').empty().append(wmAssetColour(data.colours.outline, false, true, false, wmEditorText.borderColour));
		var comment = $('<select id="wm-comment-colour-select">');
		Object.keys(data.colours.commentchoices || {}).forEach(function(value) { comment.append($('<option>').val(value).text(data.colours.commentchoices[value])); });
		if (!Object.prototype.hasOwnProperty.call(data.colours.commentchoices || {}, data.colours.commentcolour)) comment.append($('<option>').val(data.colours.commentcolour).text(wmEditorText.colourText8.replace('%s', data.colours.commentcolour)));
		$('#wm-comment-colour').empty().append(comment.val(data.colours.commentcolour));
		wmLockedScales = data.colours.locked_scales || [];
		$('#wm-scale-rows').empty(); data.colours.rows.forEach(wmAssetScaleRow);
		$('#wm-included-scale-help').remove();
		if (wmLockedScales.length) $('<p id="wm-included-scale-help">').text(wmEditorText.includedScaleHelp).insertBefore('#wm-traffic-advanced');
		$('#wm-traffic-preset').prop('disabled', wmLockedScales.indexOf('DEFAULT') !== -1);
        $('#wm-traffic-advanced').prop('open', false);
        $('#wm-traffic-preset').val(wmTrafficPresetMatch(data.colours.rows)).off('change selectmenuchange').on('change selectmenuchange', function() {
            if (wmLockedScales.indexOf('DEFAULT') !== -1) return;
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
			wmColourRequest('set_map_colours', {colours_json: JSON.stringify({outline: $('#wm-outline-colour .wm-colour-value').val(), commentcolour: $('#wm-comment-colour-select').val(), pulse: $('#wm-traffic-pulse').prop('checked'), rows: rows})}, function() { hide_all_dialogs(); wmColourRefreshMap(); }, 'wm-colours-error');
		});
	}, 'wm-colours-error');
	$('#wm-scale-add').off('click').on('click', function() { var scale = 'DEFAULT'; if (wmLockedScales.indexOf(scale) !== -1) { scale = 'Custom'; var suffix = 2; while (wmLockedScales.indexOf(scale) !== -1) scale = 'Custom' + suffix++; } wmAssetScaleRow({scale: scale, bottom: 0, top: 100, colour: '#0000ff'}); $('#wm-traffic-preset').val('custom').selectmenu('refresh'); wmTrafficPreview(); });
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {wmAssetRequest: wmAssetRequest, wmAssetImages: wmAssetImages, wmOpenImages: wmOpenImages, wmAssetColour: wmAssetColour, wmColourRequest: wmColourRequest, wmAssetScaleRow: wmAssetScaleRow, wmColourRefreshMap: wmColourRefreshMap, wmAssetScaleRows: wmAssetScaleRows, wmTrafficPresetRows: wmTrafficPresetRows, wmTrafficPresetMatch: wmTrafficPresetMatch, wmTrafficPreview: wmTrafficPreview, wmOpenColours: wmOpenColours};
}
