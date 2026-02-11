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

			// Nasty tracking issue this prevents
			// multiple tooltips from being visible
			// on a trigger
			$('.ui-helper-hidden-accessible').hide();

			var id = $(ui.tooltip).attr('id');
			$('div.ui-tooltip').not('#'+ id).remove();

			ui.tooltip.position({
				my: 'left top+5%',
				at: 'right+15 center',
				of: event
			});

			tooltipObject  = ui.tooltip;

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
			$('.ui-helper-hidden-accessible').empty();

			// Grab the hover data
			var hoverData = atob($(this).attr('data-hover'));

			// Turn the hover data into an html object
			var object = $($.parseHTML(hoverData));

			// Peal the size of the image from the object data
            var width  = object.find('img:first-child').attr('data-width');
            var height = object.find('img:first-child').attr('data-height');

			// Create the container for the object data
			var data = '<div id="wm_hover" class="cactiTable"><div id="wm_hover_child" class="cactiTableTitleRow">'+$(this).attr('data-caption') + '</div><div class="cactiTable wmcontent" style="height:'+height+';width:'+width+';display:none">'+hoverData+'</div></div>';

			callback(data);
		}
	});

	waitForFinalEvent(function() {
		$('.cactiGraphContentArea').removeClass('cactiGraphContentArea').addClass('wm_scroll');
	});
});

function adjustTooltipWindow() {
    $('.ui-tooltip').find('img').css('max-width', '100%');
	$('.ui-tooltip').css('transform', 'translateX(+20px)');
    $('.wmcontent').show();
}

/**
 * only perform the recalculation of elements at the final end of the windows resize event
 */
var waitForFinalEvent = (function () {
  var timers = {};

  return function (callback, ms, uniqueId) {
    if (!uniqueId) {
      uniqueId = "Don't call this twice without a uniqueId";
    }

    if (timers[uniqueId]) {
      clearTimeout(timers[uniqueId]);
    }

    timers[uniqueId] = setTimeout(callback, ms);
  };
})();
