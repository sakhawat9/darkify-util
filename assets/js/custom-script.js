/*
 * The site header: opaque once the visitor has scrolled past the hero, and out
 * of the way while they are heading down the page.
 *
 * The header is `position: sticky` and animates its own `top`, so it sits over
 * whatever is at the top of the viewport — anything scrolling the page to an
 * anchor has to measure it rather than assume a height (the changelog block's
 * version navigation does exactly that).
 */
const header = document.querySelector("header:has(.darkify_header)");

if (header) {
    let lastScrollTop = 0;
    let ticking = false;

    // Read and write inside one frame. Doing this straight from the scroll
    // event means a class change per event, and the layout that forces is felt
    // as stutter during a long or animated scroll.
    const update = () => {
        ticking = false;

        const currentScroll =
            window.pageYOffset || document.documentElement.scrollTop;

        // sticky background control
        header.classList.toggle("is-sticky", currentScroll > 200);

        // scroll direction control
        //
        // Held still while something is scrolling the page for the visitor —
        // the changelog block's version navigation sets this flag. Hiding on
        // the way down and sliding back in at the end would drop the header
        // over whatever the scroll was aiming at, and a scroll that corrected
        // for it would only hide the header again.
        if (!document.documentElement.dataset.darkifyScrolling) {
            const down = currentScroll > lastScrollTop && currentScroll > 50;
            header.classList.toggle("scroll-down", down);
            header.classList.toggle("scroll-up", !down);
        } else {
            header.classList.remove("scroll-down");
            header.classList.add("scroll-up");
        }

        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    };

    window.addEventListener(
        "scroll",
        function () {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(update);
        },
        { passive: true }
    );

    // Take the shown position the moment the flag appears rather than waiting
    // for the first scroll event of the animation. Whatever set the flag is
    // about to measure this header to decide where to stop, and measuring it
    // half-way out is how a heading ends up behind it.
    new MutationObserver(function () {
        if (document.documentElement.dataset.darkifyScrolling) {
            header.classList.remove("scroll-down");
            header.classList.add("scroll-up");
        }
    }).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-darkify-scrolling"],
    });
}
