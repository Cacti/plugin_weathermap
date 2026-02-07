var tooltipObject = null;
var wmHoverTimeout = null;

$(function() {
	$('map').tooltip({
		items: 'area',
		track: false,
		open: function(event, ui) {
			if (typeof(event.originalEvent) == 'undefined') {
				return false;
			}

			var id = $(ui.tooltip).attr('id');

			$('div.ui-tooltip').not('#'+ id).remove();

			ui.tooltip.position({
				my: 'left top+5%',
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
			var object = $($.parseHTML($(this).attr('data-hover')));
            var width  = object.find('img:first-child').attr('data-width');
            var height = object.find('img:first-child').attr('data-height');

			var data = $('<div id="wm_hover" class="cactiTable"><div><div id="wm_hover_child" class="cactiTableTitleRow"></div></div><div class="cactiTable"><div class="wmcontent" style="height:'+height+';width:'+width+';display:none"></div></div></div>');

			data.find('#wm_hover_child.cactiTableTitleRow').html($(this).attr('data-caption'));
			data.find('.wmcontent').html($(this).attr('data-hover')).hide();

			callback(data);
		}
	});

	waitForFinalEvent(function() {
		$('.cactiGraphContentArea').removeClass('cactiGraphContentArea').addClass('wm_scroll');
	});
});

function adjustTooltipWindow() {
    $('.ui-tooltip').find('img').css('max-width', '100%');
    $('.wmcontent').show();
}
