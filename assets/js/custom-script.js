let lastScrollTop = 0;
const header = document.querySelector("header:has(.darkify_header)");

window.addEventListener("scroll", function () {
    let currentScroll =
        window.pageYOffset || document.documentElement.scrollTop;

    // sticky background control
    if (currentScroll > 200) {
        header.classList.add("is-sticky");
    } else {
        header.classList.remove("is-sticky");
    }

    // scroll direction control
    if (currentScroll > lastScrollTop && currentScroll > 50) {
        header.classList.add("scroll-down");
        header.classList.remove("scroll-up");
    } else {
        header.classList.add("scroll-up");
        header.classList.remove("scroll-down");
    }

    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
});