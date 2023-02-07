var tooltipObject = null;
var wmHoverTimeout = null;

$(function() {
	$('map').tooltip({
		items: 'area',
		open: function(event, ui) {
			if (typeof(event.originalEvent) == 'undefined') {
				return false;
			}

			var id = $(ui.tooltip).attr('id');

			$('div.ui-tooltip').not('#'+ id).remove();

			ui.tooltip.css('min-width', '600px');
			ui.tooltip.css('max-width', '1800px');
			ui.tooltip.css('overflow-y', 'visible');

			ui.tooltip.position({
				my: 'left top',
				at: 'right+15 center',
				of: event
			});

			tooltipObject      = ui.tooltip;
			wmHoverTimeout = setTimeout(adjustTooltipWindow, 200);
		},
		close: function(event, ui) {
			ui.tooltip.hover(
			function () {
				$(this).stop(true).fadeTo(1000, 1);
			},
			function() {
				$(this).fadeOut(1000, function() {
					$(this).remove();
				});
			});
		},
		content: function(callback) {
			var data = $('<div id="wm_hover" class="cactiTable"><div><div id="wm_hover_child" class="cactiTableTitleRow"></div></div><table class="cactiTable"><tr><td class="wmcontent"></td></tr></table></div>');

			data.find('#wm_hover_child.cactiTableTitleRow').html($(this).attr('data-caption'));
			data.find('.wmcontent').html($(this).attr('data-hover'));

			callback(data);
		}
	});

	waitForFinalEvent(function() {
		$('.cactiGraphContentArea').removeClass('cactiGraphContentArea').addClass('wm_scroll');
	});
});

function adjustTooltipWindow() {
	var maxWidth = $(tooltipObject).find('img').width();
	//console.log(maxWidth);
	tooltipObject.css('max-width', maxWidth+'px');
	tooltipObject.css('max-height', '800px');
	tooltipObject.css('overflow-y', 'visible');
}
