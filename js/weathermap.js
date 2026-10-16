// Link local Cacti previews to their corresponding full graph, independently of INFOURL.
function wmHoverGraphDestination(source) {
 try {
  var url = new URL(source, window.location.href);
  if (url.origin !== window.location.origin || !/\/graph_image\.php$/.test(url.pathname)) return null;
  var id = url.searchParams.get('local_graph_id');
  if (!/^[1-9][0-9]*$/.test(id || '')) return null;
  url.pathname = url.pathname.replace(/graph_image\.php$/, 'graph.php');
  url.search = ''; url.hash = '';
  url.searchParams.set('rra_id', 'all');
  url.searchParams.set('local_graph_id', id);
  return url.pathname + url.search;
 } catch (error) { return null; }
}

// Density descriptors keep the preview's CSS size and text proportions unchanged.
function wmSharpHoverGraph(image) {
 var original = image.attr('src');
 if (!wmHoverGraphDestination(original) || image.attr('srcset')) return;
 var url = new URL(original, window.location.href);
 if (url.searchParams.has('image_format') && url.searchParams.get('image_format').toLowerCase() !== 'png') return;
 var width = url.searchParams.get('graph_width'), height = url.searchParams.get('graph_height');
 if (!/^[1-9][0-9]*$/.test(width || '') || Number(width) > 1000 || !/^[1-9][0-9]*$/.test(height || '') || Number(height) > 400) return;
 url.searchParams.set('wm_hidpi', '2');
 url.searchParams.set('image_format', 'png');
 image.one('error.wmHiDpi', function() { $(this).removeAttr('srcset'); });
 image.attr('srcset', url.href + ' 2x');
}

function wmInteractiveHover(map) {
 var instance = map.tooltip('instance');
 if (!instance || typeof instance.close !== 'function' || instance.wmInteractiveHover) return;
 var state = instance.wmInteractiveHover = {timer: null, close: instance.close};
 instance.close = function(event) {
  clearTimeout(state.timer);
  if (event && (event.type === 'mouseleave' || event.type === 'focusout')) {
   var target = $(event.currentTarget), data = instance._find(target);
   var tip = data && data.tooltip;
   if (tip && event.relatedTarget && (tip[0].contains(event.relatedTarget) || target[0] === event.relatedTarget)) return;
   var delayed = $.Event(event); delayed.currentTarget = target[0];
   state.timer = setTimeout(function() {
    if (tip && (tip.data('wm-pointer-inside') || tip[0].contains(document.activeElement))) return;
    state.close.call(instance, delayed);
   }, 300);
   return;
  }
  return state.close.apply(instance, arguments);
 };
}

function initWeatherMapView() {
	if (typeof $.fn.tooltip === 'function') $('map').tooltip({
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
			$('map').each(function() {
				var widget = $(this).tooltip('instance');
				if (!widget || !widget.wmInteractiveHover) return;
				clearTimeout(widget.wmInteractiveHover.timer);
				$.each(widget.tooltips, function(otherId, item) {
					if (otherId !== id) widget.wmInteractiveHover.close.call(widget, $.Event('mouseleave', {currentTarget:item.element[0]}));
				});
			});
			$('div.ui-tooltip').not('#'+ id).remove();

			ui.tooltip.css({width: 'max-content', maxWidth: 'calc(100vw - 48px)', pointerEvents: 'auto'});
			var area = $(event.originalEvent.target).closest('area'), instance = $(this).tooltip('instance');
			if (instance && instance.wmInteractiveHover) {
				var state = instance.wmInteractiveHover;
				clearTimeout(state.timer);
				area.off('.wmGraphHover').on('mouseenter.wmGraphHover', function() { clearTimeout(state.timer); });
				ui.tooltip.on('mouseenter.wmGraphHover focusin.wmGraphHover', function(e) {
					clearTimeout(state.timer);
					if (e.type === 'mouseenter') $(this).data('wm-pointer-inside', true);
				}).on('mouseleave.wmGraphHover focusout.wmGraphHover', function(e) {
					$(this).data('wm-pointer-inside', false);
					if (e.relatedTarget && this.contains(e.relatedTarget)) return;
					instance.close($.Event(e.type, {currentTarget:area[0], relatedTarget:e.relatedTarget}));
				}).on('keydown.wmGraphHover', function(e) {
					if (e.key === 'Escape') state.close.call(instance, $.Event('keyup', {currentTarget:area[0]}));
				});
			}
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
			// Generated node/link names can include repeated clone suffixes and collision letters.
			if (caption && !/^node[0-9]+[a-z]*(?:_copy)*(?:-node[0-9]+[a-z]*(?:_copy)*)?[a-z]*$/i.test(caption)) {
				$('<div>').text(caption).css({fontSize: '12px', padding: '0 0 4px'}).appendTo(data);
			}
			object.find('img').css({
				display: 'block', width: 'auto', height: 'auto',
				maxWidth: Math.min((parseInt(width, 10) || 800) * 1.1, window.innerWidth - 48) + 'px'
			});
			object.find('img').each(function() {
				wmSharpHoverGraph($(this));
				var destination = wmHoverGraphDestination($(this).attr('src'));
				if (!destination || $(this).closest('a').length) return;
				var label = $(this).attr('alt') || 'Open graph ' + new URL(destination, window.location.href).searchParams.get('local_graph_id');
				$(this).wrap($('<a>').attr({href:destination, 'aria-label':label, title:label}));
			});
			$('<div>', {class: 'wmcontent'}).append(object).appendTo(data);
			callback(data);
		}
	}).each(function() { wmInteractiveHover($(this)); });

	waitForFinalEvent(function() {
		$('.cactiGraphContentArea').removeClass('cactiGraphContentArea').addClass('wm_scroll');
	});
}
$(initWeatherMapView);
$(document).ajaxComplete(initWeatherMapView);

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
    var activeMaps = new Map();
    function initTrafficGlow() {
        activeMaps.forEach(function(cleanup, holder) {
            if (!holder.isConnected) cleanup();
        });
        document.querySelectorAll('.weathermapimage[data-wm-traffic]').forEach(function(holder) {
            if (holder.dataset.wmAnimationReady) return;
            var image = holder.querySelector('img[usemap]'), data;
            try { data = JSON.parse(holder.dataset.wmTraffic); } catch (error) { return; }
            if (!image || !data) return;
            data.width = Number(data.width); data.height = Number(data.height);
            if (!Number.isFinite(data.width) || !Number.isFinite(data.height)) return;
            if (!image || !data || data.enabled !== true || !(data.width > 0) || !(data.height > 0) || !Array.isArray(data.regions) || !data.regions.length) return;
            holder.dataset.wmAnimationReady = '1';
            var surface = document.createElement('span');
            surface.style.cssText = 'display:inline-block;position:relative;line-height:0;padding:0;border:0;vertical-align:top';
            image.parentNode.insertBefore(surface, image); surface.appendChild(image);
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)'), svg;
            function stop() { if (svg) { svg.remove(); svg = null; } }
            function cleanup() {
                activeMaps.delete(holder);
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
                mask.setAttribute('maskContentUnits', 'userSpaceOnUse');
                mask.setAttribute('mask-type', 'luminance');
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
                data.regions.forEach(function(region, index) {
                    if (!/^#[0-9a-f]{6}$/i.test(region.colour) || !Array.isArray(region.points) || region.points.length < 3 || !region.points.every(function(p) { return Array.isArray(p) && p.length === 2 && p.every(Number.isFinite); })) return;
                    var polygon = document.createElementNS(ns, 'polygon');
                    polygon.setAttribute('points', region.points.map(function(p) { return p.join(','); }).join(' '));
                    polygon.setAttribute('fill', 'none');
                    polygon.setAttribute('stroke', region.colour);
                    polygon.setAttribute('stroke-width', '2');
                    polygon.setAttribute('stroke-linejoin', 'round');
                    // SVG primitives avoid WebKit's CSS drop-shadow failures on SVG shapes.
                    var filter = document.createElementNS(ns, 'filter');
                    var filterId = maskId + '-filter-' + index;
                    var xs = region.points.map(function(p) { return p[0]; });
                    var ys = region.points.map(function(p) { return p[1]; });
                    filter.setAttribute('id', filterId);
                    filter.setAttribute('filterUnits', 'userSpaceOnUse');
                    filter.setAttribute('x', Math.min.apply(null, xs) - 30);
                    filter.setAttribute('y', Math.min.apply(null, ys) - 30);
                    filter.setAttribute('width', Math.max.apply(null, xs) - Math.min.apply(null, xs) + 60);
                    filter.setAttribute('height', Math.max.apply(null, ys) - Math.min.apply(null, ys) + 60);
                    filter.setAttribute('color-interpolation-filters', 'sRGB');
                    var merge = document.createElementNS(ns, 'feMerge');
                    [2, 5, 9].forEach(function(radius) {
                        var blur = document.createElementNS(ns, 'feGaussianBlur');
                        var result = 'glow-' + radius;
                        blur.setAttribute('in', 'SourceGraphic');
                        blur.setAttribute('stdDeviation', radius);
                        blur.setAttribute('result', result);
                        filter.appendChild(blur);
                        var layer = document.createElementNS(ns, 'feMergeNode');
                        layer.setAttribute('in', result); merge.appendChild(layer);
                    });
                    var outline = document.createElementNS(ns, 'feMergeNode');
                    outline.setAttribute('in', 'SourceGraphic'); merge.appendChild(outline);
                    filter.appendChild(merge); defs.appendChild(filter);
                    polygon.setAttribute('filter', 'url(#' + filterId + ')');
                    polygon.classList.add('wm-traffic-pulse');
                    arrows.appendChild(polygon);
                });
                surface.appendChild(svg); align();
                if (document.hidden || reduced.matches) svg.classList.add('wm-glow-paused');
            }
            activeMaps.set(holder, cleanup);
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
