# Darkify Util

Site-specific helper plugin for the Darkify demo site.

Two preview shortcodes, both built on the same idea: a sample site rendered into
an isolated same-origin frame, running the **real** Darkify engine against the
site's own settings.

| Shortcode | What it is |
| --- | --- |
| `[darkify_demo]` | The interactive "Try it yourself" demo — the visitor throws the switch, and controls change the preset, size and position live. |
| `[darkify_hero_demo]` | The hero preview — no interaction, it flips itself on a loop. |

`includes/class-darkify-util-preview.php` holds what they share: the frame
machinery, the Darkify lookups, the asset wiring and the switcher markup. Each
shortcode adds only its own sample site and its own driver.

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
behind it to pick light or dark styling. The sample site's brand mark and card
icons carry the same class — the runtime marker behind Darkify's Disallowed
Elements setting. Without it the engine reads a solid-coloured box as a surface
and maps it into the palette, which paints a `#f3cac2` icon the same `#171717`
as the card behind it: right for a page's own panels, wrong for a brand accent.

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
| `presets`   | the first five Darkify lists | Which Darkify colour presets to offer, as a comma-separated list of preset keys (`set1,set3,set9,set6,set10`; Pro adds `set2`, `set4`, `set5`, `set7`, `set8`, `set11`). Order is respected. Empty means the first five Darkify lists — Carbon Mist, Midnight Reverie, Verdant Depths, Celestial Tide, Emberwood — the same five in either edition. |
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

## `[darkify_hero_demo]` — the auto-playing hero preview

The same preview, driven by a timer instead of a visitor: it opens in light
mode, flips to dark, holds long enough for the difference to register, flips
back, and loops. Nobody clicks anything.

The flip is Darkify's own `darkify_switch_trigger()` called inside the frame —
the same call the switcher's click makes — so what plays is the engine
repainting the sample site, not a canned animation between two hand-drawn
states. Every dark colour comes from the site's configured palette.

**The transition is a cross-fade on the frame, not a colour tween.** Darkify
suspends transitions while it repaints (`darkify_suspend_transitions` sets
`transition-property: none` on everything it reads) so it classifies settled
colours rather than interpolated ones — which makes a per-element tween
impossible from outside, and is the right call for the plugin. So the flip is
covered instead: the preview dips out, the engine repaints while it is faint,
and it comes back in the other mode. Darkify's switcher is exempt from that
suspension, so its sun-to-moon morph plays right through the fade and the moment
still reads as a switch being thrown.

### The pointer

The switch is not thrown invisibly: an animated cursor walks in from the side,
settles on the switcher, presses it — the switcher gives under it, and a ring
opens out from the pointer's tip — and withdraws once the new mode is on screen.
It genuinely clicks the switcher (`element.click()`, the same path a visitor's
click takes), so what the pointer appears to do is what actually happens.

The cursor lives on the host page, above the frame, rather than inside it: the
preview dips while the engine repaints, and the hand doing the clicking has to
stay crisp through that. Its target is read from the switcher's real position
inside the frame every time it sets off, so it lands on the switch at any width.
The walk overlaps the tail of the hold, so `light_hold` and `dark_hold` stay what
they say — time spent in that mode, not time plus travel. `cursor="no"` turns it
off and the preview flips on its own.

Its motion is driven by the Web Animations API rather than CSS transitions, for
three reasons: it follows a **curve** (a hand does not travel in a straight
line, and the path out is bowed the other way from the path in); it starts from
wherever the pointer actually is, so nothing is ever teleported to a start
position; and its duration comes from the **distance** (~2.1ms per pixel,
clamped), so the pointer moves at one speed whatever the preview's width. It
waits out over the page rather than off-stage, drifting a few pixels instead of
freezing, and the cycle is paced to leave it resting there for a beat before it
sets off again.

The loop runs only while the preview is on screen (IntersectionObserver) and the
tab is visible, and **not at all** under `prefers-reduced-motion: reduce` —
including when the visitor turns that on mid-visit. The cursor is not rendered
at all in that case.

### Attributes

| Attribute | Default | Description |
| --- | --- | --- |
| `autoplay` | `yes` | `no` renders the preview without the loop. |
| `light_hold` | `3000` | Milliseconds spent in light mode (600–20000). |
| `dark_hold` | `3800` | Milliseconds spent in dark mode (600–20000). |
| `fade` | `260` | Cross-fade duration in milliseconds (0–1200). |
| `start` | `light` | Which mode the preview opens in. |
| `heading` / `text` | sample copy | The sample site's headline and paragraph. |
| `cta` / `cta_alt` | `Get Started` / `See Features` | The two button labels. |
| `brand` / `url` | `Your Brand` / `yoursite.com` | Brand name and address bar. |
| `menu` / `nav` / `menu_limit` | — | Same menu resolution as `[darkify_demo]`; falls back to short generic labels. |
| `max_width` | `640` | Window width in pixels. |
| `switch` / `switch_size` / `radius` | `classic` / `80` / — | The switcher riding along in the corner. |
| `switcher` / `chrome` / `badge` | `yes` | Turn off the switcher, the browser chrome, or the Light/Dark badge. |
| `cursor` | `yes` | The animated pointer that clicks the switch. Needs `switcher="yes"`. |

```
[darkify_hero_demo heading="Beautiful dark mode for any WordPress site"
                   cta="Get Darkify" cta_alt="See Live Demo"
                   dark_hold="4200" max_width="600"]
```

### Placeholders and the flip

The sample site's content placeholders are translucent, not solid grey, and they
carry `darkify_ignore`. A solid light grey cannot survive the flip: Darkify maps
every neutral surface onto the palette, so a `#e4e8f0` bar inside a card becomes
exactly the same colour as the card and the content disappears. (Measured: every
grey from `#e4e8f0` to `#7d8aa3` lands on the same `#171717`.) A translucent
slate reads as a light bar on white and a soft light bar on dark, because what
shows through it is whatever Darkify painted underneath. The cards are tinted
rather than white for the same reason — a white panel maps onto the palette's
base background, which is also what the page becomes.

## Building the changelog block

The block owns its tooling. Everything is run **from the block directory**:

```bash
cd blocks/changelog
npm install        # once
npm run build      # production build into blocks/changelog/build
npm run start      # watch while developing
```

`build/` is committed, because the plugin is deployed by copying the folder.

Two things about this setup are deliberate, and both fix a way the build can
appear to do nothing:

* **No `--webpack-src-dir` / `--output-path` flags.** wp-scripts' defaults are
  `src/` → `build/` relative to the working directory, which is exactly the
  block's own layout. When the tooling lived in the plugin root those flags were
  needed, and running the same command from inside the block wrote the output to
  `blocks/changelog/blocks/changelog/build/` — a stray nested folder — while the
  real `build/` sat untouched and every change looked like it had been ignored.
  With no flags, the command is either correct or it fails loudly.
* **`--experimental-modules` is baked into the scripts.** `view.js` is declared
  as `viewScriptModule` so the front end gets a real ES module. Without that flag
  wp-scripts skips the module pass entirely: the build reports success, and
  `view.js` is silently never rebuilt.

To confirm a change reached the output: `grep` for it in `build/style-index.css`,
`build/view.js` or `build/index.js`.

## Files

```
includes/class-darkify-util-preview.php  shared: frame machinery, Darkify
                                         lookups, assets, switcher markup, menus
includes/class-darkify-util-demo.php     [darkify_demo] and its controls
includes/class-darkify-util-hero.php     [darkify_hero_demo]
templates/demo.php                       the demo's browser window (host page)
templates/demo-frame.php                 the demo's sample site (in the frame)
templates/hero.php                       the hero's window and loop settings
templates/hero-frame.php                 the hero's sample site (in the frame)
assets/css/darkify-preview.css           host page styles for both
assets/css/darkify-demo-frame.css        the demo's sample site
assets/css/darkify-hero-frame.css        the hero's sample site
assets/js/darkify-preview.js             builds the frame and boots Darkify in
                                         it, then attaches the controls or the
                                         autoplay loop
```

One stylesheet and one script serve every preview on a page, and they load only
on pages where one of the shortcodes is present.
