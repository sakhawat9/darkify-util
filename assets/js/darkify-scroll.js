/*
 * Site-wide smooth scrolling for in-page anchors.
 *
 * Two things live here:
 *
 *   1. The scroll engine, exposed as `window.DarkifyScroll`, so anything in the
 *      plugin that needs to move the page to an element uses one implementation
 *      instead of its own copy. `darkify-toc.js` is built on it.
 *   2. A delegated click handler on the document, so *any* link pointing at a
 *      section of the current page scrolls smoothly — including markup added
 *      later, since nothing is bound per element.
 *
 * The engine exists rather than `scrollIntoView( { behavior: 'smooth' } )`
 * because the Darkify header is `position: sticky` with a height that is only
 * knowable at run time, and it takes its place while the scroll is still
 * running. The native call fixes its destination on the first frame, so a
 * heading lands behind the header and the correction reads as a snap.
 */
(function () {
    "use strict";

    const REDUCED_MOTION = window.matchMedia("(prefers-reduced-motion: reduce)");

    // Read by assets/js/custom-script.js, which holds the header still while it
    // is set. Without that the header hides on the way down and slides back
    // over the target once the page stops.
    const SCROLLING_FLAG = "darkifyScrolling";

    // Breathing room between whatever is pinned at the top and the target.
    const GAP = 16;

    /*
     * Links that point at an id but are not navigation.
     *
     * The pricing tabs are the reason this list is not optional: they are
     * `<a href="#tab-lifetime" data-tab="2">` and the page really does contain
     * `id="tab-lifetime"`, so "the target exists" is not enough on its own to
     * tell a section link from a control. Anything that names a region it
     * operates (`aria-controls`), announces itself as a control (`role`,
     * `aria-expanded`) or carries a toggle hook is left to its own script.
     *
     * Skip links are excluded deliberately: someone using one wants to be at
     * the content now, not taken there over half a second.
     */
    const NOT_NAVIGATION = [
        "[data-darkify-no-smooth]",
        ".skip-link",
        "[data-tab]",
        ".kt-tab-title",
        "[aria-controls]",
        "[aria-expanded]",
        '[role="button"]',
        "[data-toggle]",
        "[data-bs-toggle]",
        "[download]",
    ].join(",");

    /* ------------------------------------------------------------------ */
    /* Holding the header still                                            */
    /* ------------------------------------------------------------------ */

    let holds = 0;

    function holdHeader() {
        holds++;
        document.documentElement.dataset[SCROLLING_FLAG] = "1";
    }

    function releaseHeader() {
        /*
         * A `scroll` event is dispatched after the scroll it describes, so the
         * last hop's event arrives once the flag would already have been
         * cleared — where the header reads it as the visitor scrolling down and
         * slides away the moment they arrive. A pair of frames covers that
         * normally, but not when a frame is dropped, so the hold is released on
         * a short timer instead. Counted, so overlapping scrolls cannot release
         * each other's hold.
         */
        window.setTimeout(function () {
            holds = Math.max(0, holds - 1);

            if (holds === 0) {
                delete document.documentElement.dataset[SCROLLING_FLAG];
            }
        }, 150);
    }

    /* ------------------------------------------------------------------ */
    /* Measuring                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * How much of the top of the viewport is covered by something pinned there.
     *
     * Asks the browser what is actually painted over the top of the page right
     * now rather than keeping a list of selectors that would go stale, and
     * takes the lowest edge of whatever is pinned up there.
     *
     * @param {HTMLElement} [exclude] Subtree to ignore — a sticky table of
     *                                contents should not offset itself.
     * @return {number} Covered height in pixels.
     */
    function overlay(exclude) {
        if (typeof document.elementsFromPoint !== "function") {
            return 0;
        }

        const x = Math.round(window.innerWidth / 2);
        const limit = window.innerHeight / 2;
        let offset = 0;

        // A few sample rows so a thin admin bar and a tall header are both found.
        [1, 16, 40, 72].forEach(function (y) {
            document.elementsFromPoint(x, y).forEach(function (node) {
                if (
                    !node ||
                    node === document.body ||
                    node === document.documentElement ||
                    (exclude && exclude.contains(node))
                ) {
                    return;
                }

                const position = window.getComputedStyle(node).position;

                if (position !== "fixed" && position !== "sticky") {
                    return;
                }

                const rect = node.getBoundingClientRect();

                // Pinned to the top, and a bar rather than a full-height overlay.
                if (rect.top <= 1 && rect.bottom > offset && rect.bottom < limit) {
                    offset = rect.bottom;
                }
            });
        });

        return offset;
    }

    /**
     * The gap a target wants above it.
     *
     * @param {HTMLElement} target    Element being scrolled to.
     * @param {Object}      [options] `exclude` and `extra`.
     * @return {number} Offset in pixels.
     */
    function offsetFor(target, options) {
        const settings = options || {};
        const margin = parseFloat(window.getComputedStyle(target).scrollMarginTop);

        return (
            overlay(settings.exclude) +
            (settings.extra || 0) +
            (isNaN(margin) ? 0 : margin) +
            GAP
        );
    }

    /** @return {number} Current page scroll offset. */
    function scrollTop() {
        return window.scrollY || document.documentElement.scrollTop || 0;
    }

    /** @return {number} Largest valid page scroll offset. */
    function maxScrollTop() {
        return Math.max(
            0,
            document.documentElement.scrollHeight - window.innerHeight
        );
    }

    /**
     * Where the page has to land for a target to sit clear of the overlay.
     *
     * Read from the target's live position rather than a number cached when the
     * animation started, so an image settling or a font swapping in part way
     * through moves the destination instead of leaving the scroll short.
     *
     * @param {HTMLElement} target    Element being scrolled to.
     * @param {Object}      [options] `exclude` and `extra`.
     * @return {number} Scroll offset, clamped to the scrollable range.
     */
    function destinationFor(target, options) {
        const destination =
            scrollTop() +
            target.getBoundingClientRect().top -
            offsetFor(target, options);

        return Math.max(0, Math.min(destination, maxScrollTop()));
    }

    /* ------------------------------------------------------------------ */
    /* The scroll                                                          */
    /* ------------------------------------------------------------------ */

    /** @param {number} t Progress 0–1. @return {number} Eased progress. */
    function easeInOutCubic(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }

    /**
     * Animate the page to an element.
     *
     * @param {HTMLElement} target    Element to scroll to.
     * @param {Object}      [options] `exclude`, `extra`, `focus`, and `onDone`,
     *                                which is called with `true` if the scroll
     *                                arrived and `false` if the visitor took
     *                                over on the way.
     */
    function scrollToElement(target, options) {
        const settings = options || {};
        const done = settings.onDone || function () {};
        const from = scrollTop();
        const clock = window.performance || Date;
        const events = ["wheel", "touchstart", "keydown"];

        let frame = null;
        let finished = false;
        let started = 0;
        let duration = 0;
        let settled = 0;

        holdHeader();

        function unbind() {
            events.forEach(function (type) {
                window.removeEventListener(type, interrupt, { capture: true });
            });
        }

        const finish = function (arrived) {
            if (finished) {
                return;
            }

            finished = true;

            if (frame !== null) {
                window.cancelAnimationFrame(frame);
                frame = null;
            }

            unbind();
            releaseHeader();

            // Focus follows the scroll for keyboard and screen reader users, at
            // the end rather than the start because focusing mid-animation is
            // what browsers scroll for — and not at all if the visitor scrolled
            // somewhere else on the way.
            if (arrived === true && settings.focus !== false) {
                focusTarget(target);
            }

            done(arrived === true);
        };

        // Any real scroll input hands control straight back to the visitor
        // rather than fighting them for the rest of the animation.
        const interrupt = function (event) {
            if (
                event.type === "keydown" &&
                [
                    "ArrowUp",
                    "ArrowDown",
                    "PageUp",
                    "PageDown",
                    "Home",
                    "End",
                    " ",
                ].indexOf(event.key) === -1
            ) {
                return;
            }

            finish(false);
        };

        events.forEach(function (type) {
            window.addEventListener(type, interrupt, {
                capture: true,
                passive: true,
            });
        });

        /*
         * Two phases. The first eases from where the visitor was to where the
         * target is; the second keeps following the destination for a moment
         * afterwards, so anything that settles late above the fold is absorbed
         * as a few soft pixels instead of a jump.
         */
        const step = function () {
            const elapsed = clock.now() - started;
            const current = scrollTop();
            const goal = destinationFor(target, settings);

            if (elapsed < duration) {
                window.scrollTo(
                    0,
                    from + (goal - from) * easeInOutCubic(elapsed / duration)
                );
                frame = window.requestAnimationFrame(step);
                return;
            }

            const remaining = goal - current;

            if (Math.abs(remaining) < 0.5) {
                settled++;
            } else {
                settled = 0;
                // Reduced motion still has to settle — the header only becomes
                // an overlay once the page has scrolled past it, so its height
                // cannot be known before the move — but it gets there in one
                // hop per frame rather than easing into place.
                window.scrollTo(
                    0,
                    current + remaining * (REDUCED_MOTION.matches ? 1 : 0.25)
                );
            }

            // Three still frames, or half a second of chasing, whichever first.
            if (settled < 3 && elapsed < duration + 500) {
                frame = window.requestAnimationFrame(step);
                return;
            }

            window.scrollTo(0, goal);
            frame = null;
            finish(true);
        };

        /*
         * One frame between raising the flag and taking the first measurement,
         * so the header has returned to its shown position before it is
         * measured. Measuring in the same frame reads a header that is about to
         * move and aims the scroll at the wrong place.
         */
        frame = window.requestAnimationFrame(function () {
            if (finished) {
                return;
            }

            const goal = destinationFor(target, settings);

            // Long jumps take longer, but never so long that the page feels
            // slow. A zero duration drops straight through to the settle, which
            // is how reduced motion arrives in one hop.
            duration = REDUCED_MOTION.matches
                ? 0
                : Math.min(900, Math.max(320, Math.abs(goal - from) * 0.5));
            started = clock.now();
            frame = window.requestAnimationFrame(step);
        });
    }

    /**
     * Move keyboard focus to a target without moving the page.
     *
     * @param {HTMLElement} target Element to focus.
     */
    function focusTarget(target) {
        if (!target || typeof target.focus !== "function") {
            return;
        }

        const had = target.hasAttribute("tabindex");

        target.setAttribute("tabindex", "-1");
        target.focus({ preventScroll: true });

        // Removed again so the target does not become a permanent tab stop.
        if (!had) {
            target.addEventListener(
                "blur",
                function () {
                    target.removeAttribute("tabindex");
                },
                { once: true }
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Resolving links                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The element a hash points at, following the rules a browser uses.
     *
     * @param {string} hash A `#…` fragment.
     * @return {?HTMLElement} The target, or null.
     */
    function targetForHash(hash) {
        if (!hash || hash.length < 2) {
            return null;
        }

        const raw = hash.slice(1);
        let id = raw;

        try {
            id = decodeURIComponent(raw);
        } catch (e) {
            id = raw;
        }

        return (
            document.getElementById(id) ||
            document.getElementById(raw) ||
            document.querySelector('[name="' + CSS.escape(id) + '"]')
        );
    }

    /**
     * Whether a link points at a section of the page it is already on.
     *
     * Compares the resolved URL rather than the attribute, so `/controls/#pricing`
     * counts while on `/controls/` — links written that way are common in menus
     * and would otherwise be left as a hard jump.
     *
     * @param {HTMLAnchorElement} link Candidate link.
     * @return {?HTMLElement} The target on this page, or null.
     */
    function samePageTarget(link) {
        const href = link.getAttribute("href");

        // `href="#"` on its own is a placeholder, not a destination.
        if (!href || href === "#") {
            return null;
        }

        let url;

        try {
            url = new URL(link.href, window.location.href);
        } catch (e) {
            return null;
        }

        if (
            url.origin !== window.location.origin ||
            url.pathname !== window.location.pathname ||
            url.search !== window.location.search
        ) {
            return null;
        }

        return targetForHash(url.hash);
    }

    /* ------------------------------------------------------------------ */
    /* The delegated handler                                               */
    /* ------------------------------------------------------------------ */

    document.addEventListener("click", function (event) {
        // Something closer to the link has already dealt with this — the table
        // of contents handles its own entries so it can hold its active item
        // while the page moves.
        if (event.defaultPrevented) {
            return;
        }

        // Leave modified and non-primary clicks to the browser: they are "open
        // this somewhere else", not "take me there".
        if (
            (typeof event.button === "number" && event.button !== 0) ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        const link = event.target.closest ? event.target.closest("a[href]") : null;

        if (!link) {
            return;
        }

        // Opening elsewhere is the visitor's choice, not ours to animate.
        const linkTarget = link.getAttribute("target");

        if (linkTarget && linkTarget !== "_self") {
            return;
        }

        if (link.matches(NOT_NAVIGATION) || link.closest("[data-darkify-no-smooth]")) {
            return;
        }

        const target = samePageTarget(link);

        if (!target) {
            return;
        }

        event.preventDefault();

        /*
         * Before the scroll, not after it, which is the order a browser uses
         * for a real anchor click. The entry being left behind records the
         * scroll position at the moment it is superseded, so pushing late
         * stamps it with the destination and going back returns to the wrong
         * place — the visitor presses back and nothing appears to happen.
         */
        if (window.history && window.history.pushState) {
            window.history.pushState(null, "", link.hash);
        }

        scrollToElement(target);
    });

    /*
     * Back and forward still have to work once the clicks above stop producing
     * their own history entries the browser knows how to restore.
     */
    window.addEventListener("popstate", function () {
        const target = targetForHash(window.location.hash);

        if (target) {
            scrollToElement(target, { focus: false });
        }
    });

    /*
     * Arriving with a hash already in the URL — a link followed from another
     * page — is the same problem as clicking one: the browser puts the target
     * at the very top, where the header covers it.
     */
    function correctInitialHash() {
        const target = targetForHash(window.location.hash);

        if (target) {
            scrollToElement(target, { focus: false });
        }
    }

    if (document.readyState === "complete") {
        correctInitialHash();
    } else {
        window.addEventListener("load", correctInitialHash);
    }

    /* ------------------------------------------------------------------ */

    window.DarkifyScroll = {
        overlay: overlay,
        offsetFor: offsetFor,
        destinationFor: destinationFor,
        scrollTop: scrollTop,
        maxScrollTop: maxScrollTop,
        to: scrollToElement,
        targetForHash: targetForHash,
    };
})();
