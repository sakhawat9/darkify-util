/**
 * Captions that describe whichever mode is currently on.
 *
 * The Image Controls and Video Controls pages pair a Darkify switch with a
 * short caption. Each caption is authored as its light-mode wording and has to
 * become its dark-mode wording while dark mode is on.
 *
 * Driven by Darkify's STATE, not by a click on the switch. That distinction is
 * load-bearing: these pages carry several switches each, and dark mode can also
 * be turned on by the admin-bar toggle, the keyboard shortcut, the OS-aware
 * setting and the time-based schedule. Binding to one switch's click would
 * leave the caption lying in every one of those cases, and lying again on a
 * page that simply loads with dark mode already on. Reading the state covers
 * all of them with one code path and gets the first paint right too.
 */
(function () {
  "use strict";

  /* ------------------------------------------------------------------ config
   * Sections to manage. An id, a class and an opt-in attribute, so the pattern
   * can be reused on a new section by adding `data-darkify-label-toggle` to it
   * rather than by editing this file.
   */
  var SELECTORS = [
    "#image_control_btn",
    ".video_control_btn",
    "[data-darkify-label-toggle]"
  ];

  /**
   * Dark wording for a caption that does not carry its own.
   *
   * Keyed by the authored (light) caption. This is a fallback only: putting
   * `data-darkify-dark-label` on the section always wins, which is where the
   * wording belongs — copy lives with the content, and is translatable there.
   * A caption with no entry here and no attribute is LEFT ALONE, because a
   * caption showing the wrong wording is worse than one that does not change.
   */
  var DEFAULT_DARK_LABELS = {
    "Light mode: original image": "Dark mode: low brightness applied",
    "Light mode: original video": "Dark mode: replacement video",
    "Light mode: showing the original video": "Dark mode: showing the replacement video"
  };

  var DARK_CLASS = "darkify_dark_mode_enabled";

  function isDark() {
    return document.documentElement.classList.contains(DARK_CLASS);
  }

  function setUp(section) {
    // One caption per section, and the switch beside it is all divs and SVG, so
    // the first paragraph is unambiguous.
    var label = section.querySelector("p");

    if (!label) {
      return null;
    }

    var authored = label.textContent.trim();
    var light = section.getAttribute("data-darkify-light-label") || authored;
    var dark =
      section.getAttribute("data-darkify-dark-label") ||
      DEFAULT_DARK_LABELS[authored] ||
      "";

    if (!dark) {
      return null;
    }

    // The caption changes without being the thing that was interacted with, so
    // it has to announce itself or a screen reader user toggling the switch is
    // told nothing about what changed.
    if (!label.hasAttribute("aria-live")) {
      label.setAttribute("aria-live", "polite");
    }

    return function apply() {
      var next = isDark() ? dark : light;

      // Guarded: this runs on every class change on <html>, and Darkify changes
      // that class for reasons that have nothing to do with the mode.
      if (label.textContent !== next) {
        label.textContent = next;
      }
    };
  }

  function init() {
    var seen = [];
    var appliers = [];

    for (var s = 0; s < SELECTORS.length; s++) {
      var found = document.querySelectorAll(SELECTORS[s]);

      for (var i = 0; i < found.length; i++) {
        var section = found[i];

        // A section can match more than one selector.
        if (seen.indexOf(section) !== -1) {
          continue;
        }
        seen.push(section);

        var apply = setUp(section);

        if (apply) {
          apply();
          appliers.push(apply);
        }
      }
    }

    if (!appliers.length || typeof window.MutationObserver !== "function") {
      return;
    }

    // One observer for every caption on the page, rather than one each.
    new window.MutationObserver(function () {
      for (var k = 0; k < appliers.length; k++) {
        appliers[k]();
      }
    }).observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"]
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
