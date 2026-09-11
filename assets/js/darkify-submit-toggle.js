/**
 * Showcase "Submit your site" — reveal the form on click.
 *
 * The button is a Kadence single button whose anchor is `submit_your_site`, and
 * the thing it reveals is the Fluent Forms block sitting below it in the same
 * card. Neither knows about the other in the markup, so the pairing is worked
 * out here rather than hard-coded: the Kadence uniqueIDs in those class names
 * (`kb-row-layout-id24_9964c3-64`) are regenerated whenever the block is
 * rebuilt, and anything keyed to them would quietly stop matching.
 *
 * Loaded in the <head> on purpose. The stylesheet hides the panel only under
 * `html.darkify-submit-toggle-ready`, and stamping that class before first
 * paint is what stops the form appearing and then collapsing. It also means a
 * JS failure degrades to "form always visible" rather than "form unreachable",
 * which is the right way round for a form people are meant to submit.
 */
(function () {
  "use strict";

  var BUTTON_ID = "submit_your_site";
  var READY_CLASS = "darkify-submit-toggle-ready";
  var PANEL_CLASS = "darkify-submit-panel";
  var OPEN_CLASS = "is-open";
  var ANIMATING_CLASS = "is-animating";

  /** Matches the transition duration in darkify.css; the fallback timer needs it. */
  var DURATION = 320;

  document.documentElement.classList.add(READY_CLASS);

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function prefersReducedMotion() {
    return (
      typeof window.matchMedia === "function" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    );
  }

  /**
   * The block this button reveals.
   *
   * Starts from the form wrapper, then climbs for as long as the parent does
   * NOT contain the button. That lands on the outermost wrapper belonging to
   * the form alone — the Kadence row — so the row's own padding collapses with
   * it instead of leaving a gap where the form used to be. The moment a parent
   * also contains the button we have reached the shared card and must stop.
   *
   * `data-darkify-toggle` on the button overrides all of this with a selector,
   * for a layout this heuristic does not suit.
   */
  function findPanel(button) {
    var explicit = button.getAttribute("data-darkify-toggle");

    if (explicit) {
      try {
        var chosen = document.querySelector(explicit);
        if (chosen) {
          return chosen;
        }
      } catch (e) {
        // A malformed selector is a content mistake, not a reason to throw.
      }
    }

    // Nearest form wrapper that comes after the button and does not contain it.
    // Document order matters: this page also has the collection block's search
    // form, and picking "first in the document" would find that instead.
    var wrappers = document.querySelectorAll(".fluentform, form");
    var wrapper = null;

    for (var i = 0; i < wrappers.length; i++) {
      var candidate = wrappers[i];

      if (candidate.contains(button)) {
        continue;
      }

      var position = button.compareDocumentPosition(candidate);

      if (position & Node.DOCUMENT_POSITION_FOLLOWING) {
        wrapper = candidate.closest(".fluentform") || candidate;
        break;
      }
    }

    if (!wrapper) {
      return null;
    }

    var panel = wrapper;

    while (
      panel.parentElement &&
      panel.parentElement !== document.body &&
      !panel.parentElement.contains(button)
    ) {
      panel = panel.parentElement;
    }

    return panel;
  }

  /** Run `fn` when the height transition finishes, or when it never starts. */
  function afterTransition(el, fn) {
    var done = false;

    function finish(event) {
      if (done || (event && event.target !== el) || (event && event.propertyName !== "height")) {
        return;
      }
      done = true;
      el.removeEventListener("transitionend", finish);
      fn();
    }

    el.addEventListener("transitionend", finish);
    // A panel that is display:none in a collapsed ancestor never fires
    // transitionend, and the height must not be left pinned if that happens.
    window.setTimeout(finish, DURATION + 60);
  }

  function open(panel) {
    panel.classList.add(OPEN_CLASS);

    if (prefersReducedMotion()) {
      return;
    }

    panel.classList.add(ANIMATING_CLASS);
    panel.style.height = "0px";
    // Read the layout back so the browser has a start value to animate FROM;
    // without it the two heights are set in one task and it jumps.
    void panel.offsetHeight;
    panel.style.height = panel.scrollHeight + "px";

    afterTransition(panel, function () {
      // Back to `auto`, so the panel keeps fitting its content if the form
      // grows later — validation messages, conditional fields.
      panel.style.height = "";
      panel.classList.remove(ANIMATING_CLASS);
    });
  }

  function close(panel) {
    if (prefersReducedMotion()) {
      panel.classList.remove(OPEN_CLASS);
      return;
    }

    panel.classList.add(ANIMATING_CLASS);
    panel.style.height = panel.scrollHeight + "px";
    void panel.offsetHeight;
    panel.style.height = "0px";

    afterTransition(panel, function () {
      panel.classList.remove(OPEN_CLASS);
      panel.classList.remove(ANIMATING_CLASS);
      panel.style.height = "";
    });
  }

  ready(function () {
    var button = document.getElementById(BUTTON_ID);

    if (!button) {
      return;
    }

    var panel = findPanel(button);

    if (!panel) {
      // Nothing to toggle: leave the page exactly as the theme rendered it.
      document.documentElement.classList.remove(READY_CLASS);
      return;
    }

    panel.classList.add(PANEL_CLASS);

    if (!panel.id) {
      panel.id = "darkify-submit-panel";
    }

    // Kadence renders this button as a <span>, so the semantics a real button
    // would carry have to be added by hand or it is invisible to assistive
    // tech and unreachable by keyboard.
    if (!button.hasAttribute("role")) {
      button.setAttribute("role", "button");
    }
    if (!button.hasAttribute("tabindex")) {
      button.setAttribute("tabindex", "0");
    }
    button.setAttribute("aria-controls", panel.id);
    button.setAttribute("aria-expanded", "false");

    function toggle() {
      var isOpen = panel.classList.contains(OPEN_CLASS);

      if (isOpen) {
        close(panel);
      } else {
        open(panel);
      }

      button.setAttribute("aria-expanded", isOpen ? "false" : "true");
    }

    button.addEventListener("click", function (event) {
      event.preventDefault();
      toggle();
    });

    button.addEventListener("keydown", function (event) {
      // Space scrolls the page by default, and Enter does nothing on a span.
      if (event.key === "Enter" || event.key === " " || event.key === "Spacebar") {
        event.preventDefault();
        toggle();
      }
    });
  });
})();
