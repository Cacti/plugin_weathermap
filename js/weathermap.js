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

// Optional pulsing glow for links at or above 80% utilisation.
(function() {
    var maskSequence = 0;
    function initTrafficGlow() {
        document.querySelectorAll('.weathermapimage[data-wm-traffic]').forEach(function(holder) {
            if (holder.dataset.wmAnimationReady) return;
            var image = holder.querySelector('img[usemap]'), data;
            try { data = JSON.parse(holder.dataset.wmTraffic); } catch (error) { return; }
            if (!image || !data || data.enabled !== true || !(data.width > 0) || !(data.height > 0) || !Array.isArray(data.regions) || !data.regions.length) return;
            holder.dataset.wmAnimationReady = '1';
            var surface = document.createElement('span');
            surface.style.cssText = 'display:inline-block;position:relative;line-height:0;padding:0;border:0;vertical-align:top';
            image.parentNode.insertBefore(surface, image); surface.appendChild(image);
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)'), svg;
            function stop() { if (svg) { svg.remove(); svg = null; } }
            function cleanup() {
                if (observer) observer.disconnect();
                image.removeEventListener('load', align);
                window.removeEventListener('resize', align);
                document.removeEventListener('visibilitychange', motionChanged);
                reduced.removeEventListener('change', motionChanged);
                stop();
            }
            function motionChanged() {
                if (!holder.isConnected) { cleanup(); return; }
                if (svg) svg.classList.toggle('wm-glow-paused', reduced.matches || document.hidden);
            }
            function align() {
                if (!holder.isConnected) { cleanup(); return; }
                if (!svg) return;
                var rect = image.getBoundingClientRect();
                svg.style.left = image.offsetLeft + 'px';
                svg.style.top = image.offsetTop + 'px';
                svg.style.width = rect.width + 'px';
                svg.style.height = rect.height + 'px';
            }
            function toggle() {
                stop();
                if (data.enabled !== true || !holder.isConnected) return;
                var ns = 'http://www.w3.org/2000/svg';
                svg = document.createElementNS(ns, 'svg');
                svg.setAttribute('viewBox', '0 0 ' + data.width + ' ' + data.height);
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.setAttribute('aria-hidden', 'true');
                svg.classList.add('wm-traffic-glow');
                // Keep nodes and traffic labels above the halo, as in the rendered map.
                var defs = document.createElementNS(ns, 'defs'), mask = document.createElementNS(ns, 'mask');
                var maskId = 'wm-glow-mask-' + (++maskSequence);
                mask.setAttribute('id', maskId);
                mask.setAttribute('maskUnits', 'userSpaceOnUse');
                mask.setAttribute('x', '-30'); mask.setAttribute('y', '-30');
                mask.setAttribute('width', data.width + 60); mask.setAttribute('height', data.height + 60);
                var background = document.createElementNS(ns, 'rect');
                background.setAttribute('x', '-30'); background.setAttribute('y', '-30');
                background.setAttribute('width', data.width + 60); background.setAttribute('height', data.height + 60);
                background.setAttribute('fill', 'white'); mask.appendChild(background);
                (data.occlusions || []).forEach(function(points) {
                    if (!Array.isArray(points) || points.length < 3 || !points.every(function(p) { return Array.isArray(p) && p.length === 2 && p.every(Number.isFinite); })) return;
                    var cover = document.createElementNS(ns, 'polygon');
                    cover.setAttribute('points', points.map(function(p) { return p.join(','); }).join(' '));
                    cover.setAttribute('fill', 'black'); cover.setAttribute('stroke', 'black'); cover.setAttribute('stroke-width', '2');
                    mask.appendChild(cover);
                });
                defs.appendChild(mask); svg.appendChild(defs);
                var arrows = document.createElementNS(ns, 'g');
                arrows.setAttribute('mask', 'url(#' + maskId + ')'); svg.appendChild(arrows);
                data.regions.forEach(function(region) {
                    if (!/^#[0-9a-f]{6}$/i.test(region.colour) || !Array.isArray(region.points) || region.points.length < 3 || !region.points.every(function(p) { return Array.isArray(p) && p.length === 2 && p.every(Number.isFinite); })) return;
                    var polygon = document.createElementNS(ns, 'polygon');
                    polygon.setAttribute('points', region.points.map(function(p) { return p.join(','); }).join(' '));
                    polygon.setAttribute('fill', 'none');
                    polygon.setAttribute('stroke', region.colour);
                    polygon.setAttribute('stroke-width', '2');
                    polygon.setAttribute('stroke-linejoin', 'round');
                    polygon.style.filter = 'drop-shadow(0 0 2px ' + region.colour + ') drop-shadow(0 0 5px ' + region.colour + ') drop-shadow(0 0 9px ' + region.colour + ')';
                    polygon.classList.add('wm-traffic-pulse');
                    arrows.appendChild(polygon);
                });
                surface.appendChild(svg); align();
                if (document.hidden || reduced.matches) svg.classList.add('wm-glow-paused');
            }
            toggle();
            reduced.addEventListener('change', motionChanged);
            image.addEventListener('load', align);
            var observer = new ResizeObserver(align); observer.observe(image); observer.observe(holder);
            window.addEventListener('resize', align);
            document.addEventListener('visibilitychange', motionChanged);
        });
    }
    $(initTrafficGlow);
    $(document).ajaxComplete(initTrafficGlow);
})();
