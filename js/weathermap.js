$(function() {
	$('map').tooltip({
		items: 'area',
		track: false,
		show: false,
		hide: false,
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

			ui.tooltip.css({width: 'max-content', maxWidth: 'calc(100vw - 48px)', pointerEvents: 'none'});
			ui.tooltip.position({
				my: 'left top+5%',
				at: 'right+15 center',
				of: event
			});

		},
		close: function(event, ui) {
			ui.tooltip.stop(true, true).remove();
		},
		content: function(callback) {
			$('.ui-helper-hidden-accessible').empty();

			// Grab the hover data
			var hoverData = atob($(this).attr('data-hover'));

			// Turn the hover data into an html object
			var object = $($.parseHTML(hoverData));

			// Peel the size of the image from the object data
			var width = object.find('img:first-child').attr('data-width');

			// Fit the popup to its graphs instead of inheriting full-width table styles.
			var caption = ($(this).attr('data-caption') || '').trim();
			var data = $('<div>', {id: 'wm_hover'}).css({display: 'inline-block'});
			if (caption && !/^node[0-9]+[a-z]*(?:-node[0-9]+[a-z]*)?$/i.test(caption)) {
				$('<div>').text(caption).css({fontSize: '12px', padding: '0 0 4px'}).appendTo(data);
			}
			object.find('img').css({
				display: 'block', width: 'auto', height: 'auto',
				maxWidth: Math.min((parseInt(width, 10) || 800) * 1.1, window.innerWidth - 48) + 'px'
			});
			$('<div>', {class: 'wmcontent'}).append(object).appendTo(data);
			callback(data);
		}
	});

	waitForFinalEvent(function() {
		$('.cactiGraphContentArea').removeClass('cactiGraphContentArea').addClass('wm_scroll');
	});
});

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
