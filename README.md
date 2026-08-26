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

Below the window sit three live controls — **Color preset**, **Switch size** and
**Position**. They drive the preview in real time, with no reload, by writing the
same things Darkify itself writes:

* **Color preset** offers Darkify's own dark-mode presets — Carbon Mist,
  Midnight Reverie, Verdant Depths, Celestial Tide, Emberwood — read from the
  schema the plugin registers on every request, so the names, the swatches and
  the colours are the plugin's own, including anything customised under
  Settings → Colors. Choosing one sets the `--darkify_dark_mode_*` variables on
  the preview's `<html>` (what Darkify's header template prints for the saved
  preset, and what its own palette switcher assigns at runtime) and swaps the
  `darkify-<set>` class alongside them, which is what invalidates the engine's
  surface-token cache. If the preview is already dark, the engine's own sweep is
  asked to repaint, so the change lands instantly rather than on the next
  toggle. The preset starts on whichever one the site itself is set to.
* **Switch size** sets `--darkify-switch-scale`, the variable Darkify's
  `switch_size` attribute produces. It survives a dark-mode toggle because the
  engine leaves `.darkify_switch` alone.

  Two things keep the demo's switcher identical to the site's floating one.
  First, `.dkfd-fab` is a flex container: Darkify's *minified* Orbit stylesheet
  sets `display: inline-block` on the switcher (its unminified source does not),
  and an inline-block sits on its line's text baseline, so the line-height of
  whatever it is dropped into pushes the pill's track down inside its own fixed
  height. The plugin's floating switcher never shows this because
  `position: fixed` blockifies it; a flex parent does the same for the demo's.

  Second, the switcher's border width is passed to `[darkify]` explicitly, with
  its unit. Darkify stores that setting as a bare number and its floating switcher
  adds the unit when writing the `:root` variables, but its shortcode passes the
  raw value — and `border-width: 1` is invalid CSS, so the browser falls back to
  `medium` (3px) regardless of the setting. That is invisible at full size and
  swallows the switcher at the small end: at 50% an Orbit pill is 35×15, with
  3px on every side. See `Darkify_Util_Demo::switch_border_width()`.
* **Position** moves the switcher in the preview the way Darkify's placement
  setting does — the frame is a viewport, so the switcher floats in it as it
  would on a real site.

Whatever the controls start on is what the switcher is rendered with
server-side, so the first paint already matches the panel — there is no flash
and no state to reconcile. Nothing here touches the site's real Darkify
settings.

The panel carries Darkify's own `darkify_ignore` class, so its deliberate
colours survive the host page going dark, and it reads the background actually
behind it to pick light or dark styling.

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
| `switch_size` | `100`          | Starting switcher size in percent, as in `[darkify]`. The size control starts on the preset nearest to it. |
| `radius`      | —              | Optional corner radius for the switcher (`50%` for a circle, `12px`, or a bare number for pixels). Empty keeps Darkify's own Switcher Style setting. |
| `brand`       | `Your Brand`   | Brand name in the sample site's header. |
| `menu`        | —              | A real WordPress menu to show in the sample site's header, by title, slug or ID — a block theme's navigation (`wp_navigation`) or a classic nav menu. Top-level items only; the links open in the top window, not inside the preview. The demo follows the menu when it is edited. |
| `nav`         | —              | Manual alternative to `menu`: `Label\|https://…` items, comma separated (the URL is optional). |
| `menu_limit`  | `6`            | How many items to show. |
| `url`         | `yoursite.com` | Address shown in the window's title bar. |
| `heading`     | —              | Optional heading above the window. |
| `subtitle`    | —              | Optional line under the heading. |
| `note`        | —              | Optional line under the window. |
| `max_width`   | `900`          | Window width in pixels. |

#### Controls

| Attribute   | Default | Description |
| ----------- | ------- | ----------- |
| `controls`  | `yes`   | `no` renders the preview on its own, with no control panel. |
| `presets`   | the installed edition's free presets | Which Darkify colour presets to offer, as a comma-separated list of preset keys (`set1,set3,set9,set6,set10`; Pro adds `set2`, `set4`, `set5`, `set7`, `set8`, `set11`). Order is respected. Empty hides nothing — it means "the free ones". |
| `preset`    | the site's own preset | Which preset starts selected. |
| `sizes`     | `XS:50,S:60,M:75,L:85,XL:100,XXL:125` | Size options as `Label:percent`, where the percent is Darkify's own `switch_size`. Empty hides the group. |
| `positions` | `bottom-left,bottom-right` | Placements offered. Also accepts `top-left` and `top-right`. |
| `position`  | `bottom-right` | Which placement starts selected. |

The panel's colours are CSS custom properties on `.dkfd`
(`--dkfd-panel-bg`, `--dkfd-panel-border`, `--dkfd-panel-label`,
`--dkfd-panel-text`, `--dkfd-panel-strong`, `--dkfd-panel-strong-text`) if a
section needs its own palette.

### Example

```
[darkify_demo menu="Demos"
              heading="Try it yourself — right here"
              subtitle="This is Darkify's real floating switcher on a sample site."
              note="Prefer the whole site dark? Use the toggle in our header."]
```

A circular switcher, starting on Verdant Depths at a small size, with no
position control:

```
[darkify_demo radius="50%" preset="set9" switch_size="55" positions=""]
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
assets/js/darkify-demo.js              builds the frame, boots Darkify in it,
                                       and wires the controls to the switcher
```

Styles and scripts load only on pages where the shortcode is present.
