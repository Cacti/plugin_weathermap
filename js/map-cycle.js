"use strict";
/*global $:false */

// http://stackoverflow.com/a/210733/2397542 - jQuery center function from Tony L.
jQuery.fn.center = function () {
    this.css("position", "fixed");

    this.css("top",  Math.max(0, (($(window).height() - this.outerHeight()) / 2) + $(window).scrollTop()) + "px");
    this.css("left", Math.max(0, (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft()) + "px");

    return this;
};

if (typeof WMcycler !== 'undefined' && WMcycler.stop) { WMcycler.stop(); }

var WMcycler = {

    KEYCODE_ESCAPE : 27,
    KEYCODE_LEFT : 37,
    KEYCODE_RIGHT : 39,
    KEYCODE_SPACE : 32,

    period : 0,
    fullscreen : 0,
    poller_cycle : 0,
    current : 0,
    countdown : 0,
    nmaps : 0,
    paused : false,
    timer_counter : null,
    timer_reloader : null,

    updateProgress : function () {
        $("#cycle_pause").attr("aria-pressed", this.paused ? "true" : "false");
        // Update the countdown as a proportion of the selected cycle period.
        var progress = this.period > 0 ? Math.max(0, Math.min(100, this.countdown / (this.period / 200) * 100)) : 100;
        $("#wm_progress").css("width", progress + "%");
        var label = $("#wm_countdown");
        label.text(this.paused ? (label.attr('data-paused-label') || 'Paused') :
            (label.attr('data-next-label') || 'Next map in %ss').replace('%s', Math.max(0, Math.ceil(this.countdown / 5))));
    },

    counterHandler : function () {
        if (this.paused) {
            this.updateProgress();
        } else {
            this.updateProgress();
            this.countdown--;

            if (this.countdown < 0) {
                this.switchMap(1);
            }
        }
    },
    forceReload: function (that) {
        var d = new Date(),
            newurl = $(that).find('img').attr("src");

        newurl = newurl.replace(/time=\d+/, "time=" + d.getTime());

        $(that).find('img').attr("src", newurl);
    },

    // change to the next (or previous) map, reset the countdown, update the bar
    switchMap : function (direction) {
        var wm_new = this.current + direction;

        if (wm_new < 0) {
            wm_new += this.nmaps;
        }
        wm_new = wm_new % this.nmaps;

        var now = $(".weathermapholder").eq(this.current),
            next = $(".weathermapholder").eq(wm_new);

        if (this.fullscreen) {
            // in fullscreen, we centre everything, layer it with z-index and
            // cross-fade
            next.center();
            now.css("z-index", 2);
            next.css("z-index", 3);

            now.fadeOut(1200, function () {
                // now that we're done with it, force a reload on the image just
                // passed
                WMcycler.forceReload(this);
            });
            next.fadeIn(1200);
        } else {
            // in non-fullscreen mode, the fades just make things look strange.
            // Snap-changes
            now.hide(1, function () {
                // now that we're done with it, force a reload on the image just
                // passed
                WMcycler.forceReload(this);
            });
            next.show(1);
        }

        this.countdown = this.period / 200;
        this.current = wm_new;

        $("#wm_current_map").text(this.current + 1);
        this.updateProgress();
    },


    stop : function () {
        clearInterval(this.timer_counter);
        clearTimeout(this.timer_reloader);
        $(document).off('.wmCycle');
        $('#cycle_pause,#cycle_next,#cycle_prev,.wm-fullscreen-link').off('.wmCycle');
    },

    start : function (initialData) {
        this.stop();
        this.paused = false;
        $('#cycle_pause').attr('aria-pressed', 'false');


	$('.weathermapholder').hide();

        this.period = initialData.period;
        this.poller_cycle = initialData.poller_cycle;
        this.fullscreen = initialData.fullscreen;

        this.nmaps = $(".weathermapholder").length;
        $("#wm_total_map").text(this.nmaps);

        // copy of this that we can pass into callbacks
        var that = this;

        this.initEvents(that);
        this.initKeys(that);

        // stop here if there were no maps
        if (this.nmaps > 0) {
            if (this.period === 0) { this.period = this.poller_cycle / this.nmaps; }
            this.current = 0;

            this.switchMap(0);


            // a countdown timer in the top corner
            this.timer_counter = setInterval(function () {
                that.counterHandler();
            }, 200);

            // when to reload the whole page (with new map data)
            this.timer_reloader = setTimeout(function () {
				if (typeof loadPage === 'function' && !that.fullscreen) {
                    loadPage(document.location.href);
                } else {
                    window.location.reload();
                }
            }, this.poller_cycle);
        }
    },

    initKeys: function (that) {
        $(document).on('keyup.wmCycle', function(event) {
            if ($(event.target).closest('a, button, input, select, textarea, [role="button"], [contenteditable]:not([contenteditable="false"])').length) {
                return;
            }

            if (event.keyCode === that.KEYCODE_ESCAPE) {
                window.location.href = $(that.fullscreen ? '#cycle_exit_fullscreen' : '#cycle_stop').attr('href');
                event.preventDefault();
            }

            if (event.keyCode === that.KEYCODE_SPACE) {
                that.pauseAction();
                event.preventDefault();
            }
            // left
            if (event.keyCode === that.KEYCODE_LEFT) {
                that.previousAction();
                event.preventDefault();
            }
            // right
            if (event.keyCode === that.KEYCODE_RIGHT) {
                that.nextAction();
                event.preventDefault();
            }
        });
    },

    initEvents: function (that) {
        $('.wm-fullscreen-link').off('click.wmCycle').on('click.wmCycle', function(event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.location.assign(this.href);
        });

        $("#cycle_pause").off('click.wmCycle').on('click.wmCycle', function(event) {
            event.preventDefault();
            that.pauseAction();
        });
        $("#cycle_next").off('click.wmCycle').on('click.wmCycle', function(event) {
            event.preventDefault();
            that.nextAction();
        });
        $("#cycle_prev").off('click.wmCycle').on('click.wmCycle', function(event) {
            event.preventDefault();
            that.previousAction();
        });
    },

    nextAction : function () {
        this.switchMap(1);
    },
    previousAction : function () {
        this.switchMap(-1);
    },
    pauseAction : function () {
        this.paused = !this.paused;
        this.updateProgress();
        // remove the paused class on the progress bar, if we're mid-flash and
        // no longer paused
        if (!this.paused) {
            $("#wm_progress").removeClass("paused");
        }
    }
};
