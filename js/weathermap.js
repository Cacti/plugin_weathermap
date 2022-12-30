$(function() {
	$('map').tooltip({
		items: 'area',
		open: function(event, ui) {
			if (typeof(event.originalEvent) == 'undefined') {
				return false;
			}

			var id = $(ui.tooltip).attr('id');

			$('div.ui-tooltip').not('#'+ id).remove();

			ui.tooltip.css('max-width', '800px');
			ui.tooltip.css('max-height', '800px');

			ui.tooltip.position({
				my: 'left top',
				at: 'right+15 center',
				of: event
			});
		},
		close: function(event, ui) {
			ui.tooltip.hover(
			function () {
				$(this).stop(true).fadeTo(400, 1);
			},
			function() {
				$(this).fadeOut(400, function() {
					$(this).remove();
				});
			});
		},
		content: function(callback) {
			var data = $('<div id="wm_hover" class="cactiTable"><div><div id="wm_hover_child" class="cactiTableTitleRow"></div></div><table class="cactiTable"><tr><td class="wmcontent"></td></tr></table></div>');

			data.find('#wm_hover_child.cactiTableTitleRow').html($(this).attr('data-caption'));
			data.find('.wmcontent').html($(this).attr('data-hover'));

			callback(data);
			//callback($(this).attr('data-hover'));
		}
	});
});
