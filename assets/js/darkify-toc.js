/*
 * Table of contents behaviour.
 *
 * Kadence's table-of-contents block ships both a smooth scroll and a scroll
 * spy, but each is opt-in per block (`kb-toc-smooth-scroll` for the first,
 * `data-scroll-spy="true"` plus Gumshoe for the second) and neither is on for
 * the blocks on this site — which is why clicking an entry jumped and nothing
 * ever went active.
 *
 * The scrolling itself is `window.DarkifyScroll` (assets/js/darkify-scroll.js),
 * the same engine every other anchor on the site uses. What is left here is the
 * part only a table of contents needs: knowing which heading is being read, and
 * holding that entry active while a click is in flight.
 *
 * A block that *does* have Kadence's features switched on is left alone, so the
 * two never both bind to the same list.
 */
(function () {
    "use strict";

    const engine = window.DarkifyScroll;

    // Enqueued as a dependency, so this is a broken install rather than a
    // situation worth carrying a second implementation for.
    if (!engine) {
        return;
    }

    /* ------------------------------------------------------------------ */
    /* One table of contents                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @param {HTMLElement} nav A `.wp-block-kadence-tableofcontents` element.
     */
    function setUpToc(nav) {
        if (nav.dataset.darkifyTocReady) {
            return;
        }

        // Kadence's own smooth scroll and scroll spy are opt-in per block. When
        // either is switched on it owns the list, and binding a second
        // behaviour to the same entries would only fight it.
        if (
            nav.classList.contains("kb-toc-smooth-scroll") ||
            nav.getAttribute("data-scroll-spy") === "true"
        ) {
            return;
        }

        const list = nav.querySelector(".kb-table-of-content-list");

        if (!list) {
            return;
        }

        const extra = parseInt(nav.getAttribute("data-scroll-offset"), 10) || 0;

        // A sticky table of contents must not count itself as the thing
        // covering the top of the page.
        const measure = { exclude: nav, extra: extra };

        // Entries whose target is actually on the page, in document order of
        // the headings — which is the order the spy has to read them in, and is
        // not necessarily the order they are listed in.
        const items = Array.prototype.slice
            .call(list.querySelectorAll("a.kb-table-of-contents__entry"))
            .map(function (link) {
                const href = link.getAttribute("href") || "";
                const hash = href.slice(href.indexOf("#"));

                if (hash.length < 2) {
                    return null;
                }

                let heading = null;

                try {
                    heading = document.getElementById(
                        decodeURIComponent(hash.slice(1))
                    );
                } catch (e) {
                    heading = document.getElementById(hash.slice(1));
                }

                return heading ? { link: link, heading: heading, hash: hash } : null;
            })
            .filter(Boolean);

        if (!items.length) {
            return;
        }

        nav.dataset.darkifyTocReady = "1";

        items.sort(function (a, b) {
            // `compareDocumentPosition` rather than offsetTop: it is exact and
            // does not force a layout for every entry.
            return a.heading.compareDocumentPosition(b.heading) &
                Node.DOCUMENT_POSITION_FOLLOWING
                ? -1
                : 1;
        });

        /*
         * Kadence marks the `li`, not the anchor — `.active > .entry` is the
         * selector its own generated CSS uses — and `active-parent` marks the
         * ancestors of a nested entry. Following that contract means a block
         * with an active colour set in the editor keeps styling itself.
         */
        const rowFor = function (link) {
            return link.closest("li") || link;
        };

        let active = null;

        const setActive = function (item) {
            if (!item || item === active) {
                return;
            }

            active = item;

            items.forEach(function (other) {
                const row = rowFor(other.link);
                const isActive = other === item;

                row.classList.toggle("active", isActive);

                if (isActive) {
                    other.link.setAttribute("aria-current", "true");
                } else {
                    other.link.removeAttribute("aria-current");
                }
            });

            // Ancestors of the active entry, for nested lists.
            items.forEach(function (other) {
                const row = rowFor(other.link);

                row.classList.toggle(
                    "active-parent",
                    other !== item && row.contains(rowFor(item.link))
                );
            });

            keepInView(rowFor(item.link));
        };

        /*
         * The list is a 300px scroll box (see darkify.css), so on a long post
         * the active entry can sit outside it. Only the box's own scroll offset
         * is touched — nudging the page here would fight the scroll that caused
         * this in the first place.
         */
        const keepInView = function (row) {
            let box = list;

            while (box && box !== document.body) {
                if (box.scrollHeight > box.clientHeight + 1) {
                    break;
                }
                box = box.parentElement;
            }

            if (!box || box === document.body || box.scrollHeight <= box.clientHeight + 1) {
                return;
            }

            const boxRect = box.getBoundingClientRect();
            const rowRect = row.getBoundingClientRect();

            if (rowRect.top < boxRect.top) {
                box.scrollTop -= boxRect.top - rowRect.top + 12;
            } else if (rowRect.bottom > boxRect.bottom) {
                box.scrollTop += rowRect.bottom - boxRect.bottom + 12;
            }
        };

        /**
         * Which heading the visitor is actually looking at.
         *
         * The reading line is exactly where a click parks a heading, so
         * clicking and scrolling agree by construction: the heading a click
         * scrolls to lands on the line, so it is the heading the line reports.
         *
         * @return {?Object} The active item.
         */
        const resolveActive = function () {
            // At the bottom the page has run out of scroll, so the last entry
            // can never reach the line on its own — it wins outright instead.
            if (engine.scrollTop() >= engine.maxScrollTop() - 2) {
                return items[items.length - 1];
            }

            const line = engine.offsetFor(items[0].heading, measure) + 8;
            let current = items[0];

            for (let i = 0; i < items.length; i++) {
                if (items[i].heading.getBoundingClientRect().top - 1 <= line) {
                    current = items[i];
                } else {
                    break;
                }
            }

            return current;
        };

        let locked = null;
        let ticking = false;

        const sync = function () {
            if (ticking) {
                return;
            }

            ticking = true;

            window.requestAnimationFrame(function () {
                ticking = false;
                setActive(locked || resolveActive());
            });
        };

        items.forEach(function (item) {
            item.link.addEventListener("click", function (event) {
                // Leave modified and non-primary clicks to the browser: they
                // are "open this somewhere else", not "take me there".
                if (
                    event.defaultPrevented ||
                    (typeof event.button === "number" && event.button !== 0) ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                ) {
                    return;
                }

                event.preventDefault();

                // While the visitor is being taken somewhere by a click, that
                // somewhere is the active entry — otherwise the list would
                // flick through every heading on the way past.
                locked = item;
                setActive(item);

                engine.to(item.heading, {
                    exclude: nav,
                    extra: extra,
                    onDone: function () {
                        locked = null;
                        sync();
                    },
                });

                if (window.history && window.history.replaceState) {
                    window.history.replaceState("", "", item.hash);
                }
            });
        });

        window.addEventListener("scroll", sync, { passive: true });
        window.addEventListener("resize", sync, { passive: true });

        // The browser may still be settling on a hash target, so the first real
        // reading happens once that has finished.
        window.requestAnimationFrame(sync);
    }

    /* ------------------------------------------------------------------ */

    function init() {
        document
            .querySelectorAll(".wp-block-kadence-tableofcontents")
            .forEach(setUpToc);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
