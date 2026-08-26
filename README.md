# Darkify Util

Site-specific helper plugin for the Darkify demo site.

## `[darkify_demo]` — the "Try it yourself — right here" section

Renders a browser window containing a small sample site with Darkify's own
floating switcher in it. Clicking the switcher runs the **real** Darkify engine
against the sample page: the same client script, the same settings, the same
palette and the same switcher markup the site itself uses.

Nothing about the plugin is reimplemented. The demo boots from what Darkify has
already put on the page — its stylesheets, its inline configuration and its
engine — inside an isolated preview frame, which is what lets one part of the
page go dark while the rest of the site does not.

The demo cannot affect the site:

* the frame gets an in-memory `localStorage` stand-in, so toggling the demo
  never writes the visitor's real dark-mode preference;
* default-dark, OS-aware, time-based and keyboard-shortcut settings are
  neutralised inside the frame so the preview always starts light and never
  answers the page's own keyboard shortcut;
* if the host page's Darkify syncs iframes to its own state, the demo re-asserts
  the state the visitor chose in it.

The section renders nothing (an HTML comment) when Darkify's frontend is off —
there is nothing to demonstrate, and it will not stand in with a lookalike.

### Attributes

| Attribute     | Default        | Description |
| ------------- | -------------- | ----------- |
| `switch`      | `classic`      | Switcher style. Any style the installed Darkify edition ships, by name (`classic`, `expand`, `inner-moon`, `within`, `orbit`, plus Pro's `around`, `eclipse`, `shift`, …) or by the number `[darkify]` accepts. Unknown styles fall back to `classic`. |
| `switch_size` | `80`           | Switcher size in percent, as in `[darkify]` (40–150). |
| `brand`       | `Your Brand`   | Brand name in the sample site's header. |
| `url`         | `yoursite.com` | Address shown in the window's title bar. |
| `heading`     | —              | Optional heading above the window. |
| `subtitle`    | —              | Optional line under the heading. |
| `note`        | —              | Optional line under the window. |
| `max_width`   | `900`          | Window width in pixels. |

### Example

```
[darkify_demo heading="Try it yourself — right here"
              subtitle="This is Darkify's real floating switcher on a sample site."
              note="Prefer the whole site dark? Use the toggle in our header."]
```

Placing it inside a section that already has its own heading? Leave `heading`,
`subtitle` and `note` out and only the window renders.

### Files

```
includes/class-darkify-util-demo.php   shortcode, asset wiring, Darkify lookup
templates/demo.php                     the browser window (host page)
templates/demo-frame.php               the sample site (rendered into the frame)
assets/css/darkify-demo.css            host page styles
assets/css/darkify-demo-frame.css      sample site styles (loaded in the frame)
assets/js/darkify-demo.js              builds the frame and boots Darkify in it
```

Styles and scripts load only on pages where the shortcode is present.
