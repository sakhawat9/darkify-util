/**
 * Darkify previews — boots a real Darkify instance inside a preview frame.
 *
 * Serves both shortcodes: [darkify_demo], where a visitor throws the switch,
 * and [darkify_hero_demo], where the preview flips itself on a loop. The frame
 * machinery below is the same for both; what differs is the driver attached
 * once the engine is running.
 *
 * The frame is built from what the host page already carries: Darkify's own
 * stylesheets, its inline configuration (the `darkify_*` settings its header
 * template prints) and its client engine. Nothing about the plugin is
 * reimplemented here — this file only moves Darkify into a document of its own
 * so the demo can go dark while the page around it does not.
 *
 * Three things keep the demo from touching the real site:
 *
 *   1. localStorage inside the frame is replaced with an in-memory stand-in, so
 *      playing with the demo never writes the visitor's dark-mode preference.
 *   2. A few settings are neutralised in the frame (default-dark, OS-aware,
 *      time-based, keyboard shortcut) so the preview always starts light and
 *      the page's own keyboard shortcut isn't triggered twice.
 *   3. The host page's Darkify also syncs iframes; a guard re-asserts the
 *      demo's own state if that sync overrides it.
 */
(function () {
	"use strict";

	var DATA = window.DarkifyPreviewData || {};
	var SKELETON =
		'<!doctype html><html><head><meta charset="utf-8">' +
		'<meta name="viewport" content="width=device-width,initial-scale=1">' +
		"<title>Darkify preview</title></head><body></body></html>";

	// Settings that would make the preview start dark, or would fight the host
	// page for the same keystroke. Everything else is used exactly as configured.
	var OVERRIDES = [
		'darkify_enable_default_dark_mode="";',
		'darkify_enable_os_aware="";',
		'darkify_enable_time_based_dark="";',
		'darkify_enable_keyboard_shortcut="";',
		'darkify_enable_switch_dragging="";',
		'darkify_is_this_admin_panel="0";',
		// The preview is a frame, but it is the page being demonstrated: it must
		// behave like a top-level document, not like embedded content Darkify
		// should keep its hands off.
		'darkify_enable_frontend_iframe_dark_mode="1";'
	].join("");

	var MIN_HEIGHT = 240;

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	function toArray(list) {
		return Array.prototype.slice.call(list || []);
	}

	function isDarkifyAsset(url) {
		var bases = DATA.assetBases || [];
		for (var i = 0; i < bases.length; i++) {
			if (bases[i] && url.indexOf(bases[i]) === 0) {
				return true;
			}
		}
		return false;
	}

	function baseFont() {
		try {
			var font = window.getComputedStyle(document.body).fontFamily;
			if (font) {
				return font;
			}
		} catch (e) {
			/* fall through to the stylesheet default */
		}
		return "";
	}

	/**
	 * Give the frame its own storage. Darkify reads and writes its state as
	 * plain properties (`localStorage.darkify_last_state`), so a plain object
	 * with the Storage methods defined on top behaves identically — and throws
	 * everything away when the page is left.
	 */
	function isolateStorage(win) {
		var fake = {};

		Object.defineProperties(fake, {
			getItem: {
				value: function (key) {
					return Object.prototype.hasOwnProperty.call(this, key) ? String(this[key]) : null;
				}
			},
			setItem: {
				value: function (key, value) {
					this[key] = String(value);
				}
			},
			removeItem: {
				value: function (key) {
					delete this[key];
				}
			},
			clear: {
				value: function () {
					var self = this;
					Object.keys(self).forEach(function (key) {
						delete self[key];
					});
				}
			},
			key: {
				value: function (index) {
					var keys = Object.keys(this);
					return index < keys.length ? keys[index] : null;
				}
			},
			length: {
				get: function () {
					return Object.keys(this).length;
				}
			}
		});

		try {
			Object.defineProperty(win, "localStorage", {
				value: fake,
				configurable: true
			});
			return { isolated: true };
		} catch (e) {
			// Some browsers refuse to shadow Storage on a window. The frame then
			// shares the site's real storage, so the demo restores whatever it
			// finds there after every toggle instead.
			return { isolated: false };
		}
	}

	function appendScript(doc, options, onload) {
		var script = doc.createElement("script");
		if (options.src) {
			script.src = options.src;
			script.onload = onload || null;
			script.onerror = onload || null;
		} else {
			script.text = options.text;
		}
		doc.body.appendChild(script);
		if (!options.src && onload) {
			onload();
		}
	}

	function loadSequentially(doc, sources, done) {
		var index = 0;
		(function next() {
			if (index >= sources.length) {
				done();
				return;
			}
			appendScript(doc, { src: sources[index++] }, next);
		})();
	}

	/* --------------------------------------------------------------------- */
	/* Frame construction                                                    */
	/* --------------------------------------------------------------------- */

	function buildHead(root, doc, onReady) {
		var pending = 0;
		var settled = false;

		function maybeReady() {
			if (!settled && pending === 0) {
				settled = true;
				onReady();
			}
		}

		function addLink(href) {
			var link = doc.createElement("link");
			pending++;
			link.rel = "stylesheet";
			link.href = href;
			link.onload = link.onerror = function () {
				pending--;
				maybeReady();
			};
			doc.head.appendChild(link);
		}

		// Darkify's own stylesheets — the engine's base CSS and the stylesheet
		// for whichever switcher style this demo uses.
		toArray(document.querySelectorAll('link[rel="stylesheet"][href]')).forEach(function (link) {
			if (isDarkifyAsset(link.href)) {
				addLink(link.href);
			}
		});

		// Each preview names its own sample-site stylesheet; the localized
		// default is only a fallback for markup that predates the attribute.
		var frameCss = root.getAttribute("data-dkfd-frame-css") || DATA.frameCss;
		if (frameCss) {
			addLink(frameCss);
		}

		// Darkify's inline CSS: the palette variables for the selected colour
		// set, the scrollbar rules, and any custom CSS entered in its settings.
		toArray(document.querySelectorAll("style.darkify_inline_css")).forEach(function (style) {
			var copy = doc.createElement("style");
			copy.textContent = style.textContent;
			doc.head.appendChild(copy);
		});

		// Nothing to wait for (no stylesheets found) — carry on immediately.
		maybeReady();
	}

	function bootEngine(win, doc, finalize) {
		// Darkify's configuration, exactly as the host page received it.
		toArray(document.querySelectorAll("script.darkify_inline_js")).forEach(function (script) {
			appendScript(doc, { text: script.textContent });
		});

		appendScript(doc, { text: OVERRIDES });

		var externals = toArray(document.querySelectorAll("script[src]"))
			.filter(function (script) {
				return isDarkifyAsset(script.src);
			})
			.map(function (script) {
				return script.src;
			});

		if (!externals.length && DATA.engineJs) {
			externals = [DATA.engineJs];
		}

		loadSequentially(doc, externals, function () {
			finalize(win, doc);
		});
	}

	/**
	 * Keep the demo's state its own.
	 *
	 * The host page's Darkify syncs every iframe it finds to the page's state,
	 * which would pull the preview back to light the moment anything on the page
	 * changed. Rather than patch the plugin, the frame simply re-asserts the
	 * state the visitor chose here.
	 */
	function installGuard(win, doc, onChange) {
		var root = doc.documentElement;
		var intended = root.classList.contains("darkify_dark_mode_enabled");
		var correcting = false;
		var original = win.darkify_switch_trigger;

		if (typeof original === "function") {
			win.darkify_switch_trigger = function () {
				intended = !root.classList.contains("darkify_dark_mode_enabled");
				return original.apply(this, arguments);
			};
		}

		if (typeof win.MutationObserver === "function") {
			new win.MutationObserver(function () {
				var isDark = root.classList.contains("darkify_dark_mode_enabled");
				if (!correcting && isDark !== intended) {
					correcting = true;
					root.classList.toggle("darkify_dark_mode_enabled", intended);
					correcting = false;
				}
				onChange();
			}).observe(root, { attributes: true, attributeFilter: ["class"] });
		}
	}

	/**
	 * Without an isolated storage the frame writes to the site's real one, so
	 * put back what was there after every toggle.
	 */
	function protectRealStorage(win, doc) {
		var key = "darkify_last_state";
		var saved = null;

		try {
			saved = window.localStorage.getItem(key);
		} catch (e) {
			return;
		}

		function restore() {
			try {
				if (saved === null) {
					window.localStorage.removeItem(key);
				} else {
					window.localStorage.setItem(key, saved);
				}
			} catch (e) {
				/* storage unavailable — nothing to protect */
			}
		}

		restore();

		var original = win.darkify_switch_trigger;
		if (typeof original === "function") {
			win.darkify_switch_trigger = function () {
				var result = original.apply(this, arguments);
				restore();
				return result;
			};
		}

		if (typeof win.MutationObserver === "function") {
			new win.MutationObserver(restore).observe(doc.documentElement, {
				attributes: true,
				attributeFilter: ["class"]
			});
		}
	}

	/**
	 * How tall the preview needs to be.
	 *
	 * Measured from the sample site's own boxes rather than from
	 * documentElement.scrollHeight, which can never report less than the frame
	 * it is already in — so the window could grow but never shrink back.
	 * Anything pinned to the frame (the switcher) is skipped: it floats over the
	 * page, it does not extend it.
	 */
	function contentHeight(win, doc) {
		var body = doc.body;
		if (!body) {
			return MIN_HEIGHT;
		}

		var bottom = 0;
		toArray(body.children).forEach(function (child) {
			var style = win.getComputedStyle(child);
			if (style.position === "fixed" || style.display === "none") {
				return;
			}
			var rect = child.getBoundingClientRect();
			if (rect.bottom > bottom) {
				bottom = rect.bottom;
			}
		});

		bottom += parseFloat(win.getComputedStyle(body).paddingBottom) || 0;

		return Math.max(MIN_HEIGHT, Math.ceil(bottom));
	}

	function watchHeight(win, doc, apply) {
		apply();

		if (typeof win.ResizeObserver === "function") {
			new win.ResizeObserver(apply).observe(doc.documentElement);
		} else {
			win.addEventListener("resize", apply);
		}

		// The engine repaints asynchronously after a toggle; a couple of late
		// measurements catch any reflow that lands after the first frame.
		[120, 400, 900].forEach(function (delay) {
			win.setTimeout(apply, delay);
		});
	}

	/* --------------------------------------------------------------------- */
	/* Controls                                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * Each control writes the same thing Darkify itself writes.
	 *
	 * The colour preset sets the `--darkify_dark_mode_*` variables on the
	 * preview's <html> — the same variables Darkify's header template prints
	 * for the saved preset and its own palette switcher assigns at runtime — and
	 * swaps the `darkify-<set>` class alongside them, which is what invalidates
	 * the engine's surface-token cache (it is keyed on the html class list).
	 *
	 * Size is `--darkify-switch-scale`, the variable Darkify's `switch_size`
	 * attribute produces. Both survive a dark-mode toggle.
	 */
	var CONTROLS = {
		preset: function (frame, option) {
			var root = frame.doc.documentElement;

			if (option.vars) {
				Object.keys(option.vars).forEach(function (name) {
					root.style.setProperty(name, option.vars[name]);
				});
			}

			toArray(root.classList).forEach(function (name) {
				if (name.indexOf("darkify-set") === 0) {
					root.classList.remove(name);
				}
			});
			root.classList.add("darkify-" + option.value);

			// Colours the engine resolved in JS (rather than through its
			// variable-driven CSS) are only re-derived by a sweep, which
			// normally runs when dark mode flips. Ask for one so a preset change
			// while the preview is already dark repaints everything.
			if (
				root.classList.contains("darkify_dark_mode_enabled") &&
				typeof frame.win.darkify_state_sweep === "function"
			) {
				try {
					frame.win.darkify_state_sweep();
				} catch (e) {
					/* the preview keeps the variable-driven colours regardless */
				}
			}
		},

		size: function (frame, option) {
			var el = frame.doc.querySelector(".darkify_switch");
			if (el) {
				el.style.setProperty("--darkify-switch-scale", (parseInt(option.value, 10) || 100) / 100);
			}
		},

		position: function (frame, option) {
			var fab = frame.doc.querySelector(".dkfd-fab");
			if (fab) {
				fab.setAttribute("data-dkfd-position", option.value);
			}
		}
	};

	/**
	 * A control button as the state understands it: its value, plus the
	 * variables the server resolved for it (colour presets carry Darkify's own
	 * palette values).
	 */
	function readOption(button) {
		var option = { value: button.getAttribute("data-dkfd-value"), vars: null };
		var vars = button.getAttribute("data-dkfd-vars");

		if (vars) {
			try {
				option.vars = JSON.parse(vars);
			} catch (e) {
				option.vars = null;
			}
		}

		return option;
	}

	/**
	 * The control panel is designed for the dark section the demo usually sits
	 * in, but the shortcode goes anywhere. Measure the surface behind it and
	 * flip the panel's tokens when that surface is light, so the selected
	 * states stay visible instead of turning white-on-white.
	 */
	function readSurface(root) {
		var element = root;
		var background = "";

		while (element && element !== document.documentElement) {
			var color = window.getComputedStyle(element).backgroundColor;
			if (color && color !== "transparent" && !/,\s*0\s*\)$/.test(color)) {
				background = color;
				break;
			}
			element = element.parentElement;
		}

		if (!background) {
			background = window.getComputedStyle(document.body).backgroundColor || "rgb(255, 255, 255)";
		}

		var channels = background.match(/\d+(\.\d+)?/g);
		if (!channels || channels.length < 3) {
			return;
		}

		// Rec. 601 luma is plenty for a light/dark decision.
		var luma = (0.299 * channels[0] + 0.587 * channels[1] + 0.114 * channels[2]) / 255;
		root.classList.toggle("dkfd--on-light", luma > 0.55);
	}

	function watchSurface(root) {
		readSurface(root);

		if (typeof window.MutationObserver !== "function") {
			return;
		}

		// The host page's own dark-mode toggle repaints the section behind the
		// demo, so the panel re-reads it whenever that state changes.
		new MutationObserver(function () {
			requestAnimationFrame(function () {
				readSurface(root);
			});
		}).observe(document.documentElement, { attributes: true, attributeFilter: ["class"] });
	}

	function selectOption(button) {
		var group = button.parentNode;
		toArray(group.querySelectorAll("[data-dkfd-control]")).forEach(function (option) {
			var on = option === button;
			option.classList.toggle("is-selected", on);
			option.setAttribute("aria-pressed", on ? "true" : "false");
		});
	}

	function applyState(instance) {
		if (!instance.frame) {
			return;
		}
		Object.keys(instance.state).forEach(function (control) {
			if (CONTROLS[control]) {
				CONTROLS[control](instance.frame, instance.state[control]);
			}
		});
	}

	function wireControls(instance) {
		var panel = instance.root.querySelector(".dkfd__controls");
		if (!panel) {
			return;
		}

		watchSurface(instance.root);

		// Seed from what the server rendered as selected: the switcher is
		// already drawn with these values, so a frame that boots later (or a
		// visitor who clicks before it does) comes up matching the panel.
		toArray(panel.querySelectorAll("[data-dkfd-control].is-selected")).forEach(function (button) {
			instance.state[button.getAttribute("data-dkfd-control")] = readOption(button);
		});

		panel.addEventListener("click", function (event) {
			var button = event.target.closest ? event.target.closest("[data-dkfd-control]") : null;
			if (!button || !panel.contains(button)) {
				return;
			}

			selectOption(button);
			instance.state[button.getAttribute("data-dkfd-control")] = readOption(button);
			applyState(instance);
		});
	}

	/* --------------------------------------------------------------------- */
	/* Autoplay (the hero preview)                                           */
	/* --------------------------------------------------------------------- */

	/**
	 * The pointer that throws the switch.
	 *
	 * It lives on the host page, above the frame, because the preview dips while
	 * the engine repaints and the hand doing the clicking has to stay crisp
	 * through that. Its target is read from the switcher's real position inside
	 * the frame every time it sets off, so it lands on the switch at any width
	 * and after any reflow.
	 *
	 * Motion is driven by the Web Animations API rather than CSS transitions, for
	 * two reasons: it can follow a curve (a hand does not travel in a straight
	 * line), and it never needs the pointer to be teleported to a start position
	 * first — the animation begins from wherever it actually is. Between clicks
	 * it drifts a few pixels instead of freezing, which is what stops the whole
	 * thing from reading as a slideshow.
	 */
	function createCursor(instance) {
		var root = instance.root;
		var node = root.querySelector(".dkfd__cursor");

		if (!node || !root.hasAttribute("data-dkfd-cursor")) {
			return null;
		}

		// Travel time is derived from the distance rather than fixed, so the
		// pointer moves at one speed whatever the preview's width — a fixed
		// duration makes a short hop crawl and a long one dart.
		var SPEED = 2.1; // milliseconds per pixel
		var TRAVEL_MIN = 620;
		var TRAVEL_MAX = 1500;
		var SETTLE = 220;
		var PRESS = 200;

		var animated = typeof node.animate === "function";
		var position = null;
		var drift = null;
		var motion = null;
		var pressed = null;

		var transformFor = function (point) {
			return "translate3d(" + point.x.toFixed(1) + "px, " + point.y.toFixed(1) + "px, 0)";
		};

		var place = function (point) {
			position = point;
			node.style.transform = transformFor(point);
		};

		var cancel = function (animation) {
			if (animation) {
				try {
					animation.cancel();
				} catch (e) {
					/* already finished */
				}
			}
		};

		/**
		 * Whatever the pointer is meant to press right now.
		 *
		 * A step can name its own control (the palette dropdown, an option
		 * inside it). Otherwise it is the switch for the view on screen: on the
		 * front end Darkify's floating switcher, in the admin the moon in the
		 * admin bar, which is where the plugin puts its switch on a dashboard.
		 * Either way the pointer goes to the control a visitor would actually
		 * reach for on the screen they are looking at.
		 */
		var switchTarget = function (selector) {
			var doc = instance.frame && instance.frame.doc;
			if (!doc) {
				return null;
			}

			if (selector) {
				return doc.querySelector(selector);
			}

			var stage = doc.querySelector(".dkfh-stage");
			if (stage && stage.classList.contains("is-admin")) {
				return doc.querySelector(".dkfa-toggle");
			}

			return doc.querySelector(".darkify_switch");
		};

		/** Where that control is, in the host window's coordinates. */
		var switchPoint = function (selector) {
			var frameEl = root.querySelector(".dkfd__frame");
			var target = switchTarget(selector);
			if (!frameEl || !target) {
				return null;
			}

			var host = root.querySelector(".dkfd__window") || root;
			var frameBox = frameEl.getBoundingClientRect();
			var hostBox = host.getBoundingClientRect();
			var targetBox = target.getBoundingClientRect();

			// Low and right of centre: still unmistakably on the switch, with the
			// pointer's body hanging off it rather than over the icon it is there
			// to show off. A wide control — a dropdown, one of its rows — is aimed
			// at near its left edge instead, where a hand would actually land;
			// 62% across one of those puts the pointer out in empty space.
			var wide = targetBox.width > targetBox.height * 2;

			return {
				x: frameBox.left - hostBox.left + targetBox.left +
					(wide ? Math.min(targetBox.width * 0.5, 34) : targetBox.width * 0.62),
				y: frameBox.top - hostBox.top + targetBox.top + targetBox.height * 0.6
			};
		};

		/**
		 * Where it waits between clicks: out over the page, left of centre.
		 * Placed as a fraction of the window so the walk stays a long, readable
		 * glide at any size instead of a twitch at small ones.
		 */
		var restPoint = function () {
			var host = root.querySelector(".dkfd__window") || root;
			var box = host.getBoundingClientRect();
			return { x: box.width * 0.32, y: box.height * 0.55 };
		};

		var distanceBetween = function (a, b) {
			return Math.sqrt(Math.pow(b.x - a.x, 2) + Math.pow(b.y - a.y, 2));
		};

		var travelTime = function (from, to) {
			return Math.max(TRAVEL_MIN, Math.min(TRAVEL_MAX, distanceBetween(from, to) * SPEED));
		};

		/**
		 * A curved move. The bow is perpendicular to the straight line between
		 * the two points, so the pointer swings into place instead of sliding
		 * along a ruler.
		 */
		var moveTo = function (to, duration, easing, bowSign, done) {
			cancel(drift);
			drift = null;
			cancel(motion);

			var from = position || to;

			if (!animated || duration <= 0) {
				place(to);
				if (done) {
					done();
				}
				return;
			}

			var dx = to.x - from.x;
			var dy = to.y - from.y;
			var length = Math.sqrt(dx * dx + dy * dy) || 1;
			var bow = Math.min(52, length * 0.18) * bowSign;
			var middle = {
				x: from.x + dx * 0.5 + (-dy / length) * bow,
				y: from.y + dy * 0.5 + (dx / length) * bow
			};

			motion = node.animate(
				[
					{ transform: transformFor(from) },
					{ transform: transformFor(middle), offset: 0.5 },
					{ transform: transformFor(to) }
				],
				{ duration: duration, easing: easing, fill: "both" }
			);

			motion.onfinish = function () {
				// Commit the end state as a plain style and drop the animation,
				// so nothing stacks up over a long-running loop.
				place(to);
				cancel(motion);
				motion = null;
				if (done) {
					done();
				}
			};
		};

		/** Never quite still: a few pixels of wander while it waits. */
		var startDrift = function () {
			if (!animated || !position) {
				return;
			}
			cancel(drift);

			var base = position;
			drift = node.animate(
				[
					{ transform: transformFor(base) },
					{ transform: transformFor({ x: base.x + 8, y: base.y - 6 }), offset: 0.35 },
					{ transform: transformFor({ x: base.x + 3, y: base.y + 7 }), offset: 0.7 },
					{ transform: transformFor(base) }
				],
				{ duration: 5200, easing: "ease-in-out", iterations: Infinity }
			);
		};

		return {
			/** How long before the click the pointer has to set off. */
			lead: function (selector) {
				var point = switchPoint(selector);
				if (!point) {
					return TRAVEL_MIN + SETTLE;
				}
				return travelTime(position || restPoint(), point) + SETTLE;
			},

			approach: function (selector) {
				var point = switchPoint(selector);
				if (!point) {
					return;
				}

				// First run: start out over the page and fade in, rather than
				// appearing already on the switch.
				if (!position) {
					place(restPoint());
				}

				root.classList.add("is-pointing");
				moveTo(point, travelTime(position, point), "cubic-bezier(0.32, 0.02, 0.16, 1)", 1);
			},

			press: function (selector) {
				root.classList.add("is-clicking");
				pressSwitch(true, selector);
			},

			release: function () {
				root.classList.remove("is-clicking");
				pressSwitch(false);
			},

			retreat: function () {
				var rest = restPoint();
				// Bowed the other way on the way out, so the path back is not a
				// retrace of the path in, and a touch slower — leaving is never
				// as purposeful as arriving.
				moveTo(rest, travelTime(position || rest, rest), "cubic-bezier(0.3, 0, 0.28, 1)", -1, startDrift);
			},

			reset: function () {
				cancel(motion);
				cancel(drift);
				motion = null;
				drift = null;
				root.classList.remove("is-clicking");
				pressSwitch(false);
			},

			pressDuration: PRESS
		};

		/**
		 * The control gives under the press, the way a real one would — whichever
		 * control this step is on.
		 *
		 * The last one pressed is remembered rather than being searched for
		 * again on release: the view, and with it the control, can change
		 * between a press and its release.
		 */
		function pressSwitch(down, selector) {
			if (pressed) {
				pressed.classList.remove("is-pressed");
				pressed = null;
			}

			if (!down) {
				return;
			}

			var target = switchTarget(selector);
			if (!target) {
				return;
			}

			// Darkify's switcher is styled to give on its wrapper, not on the
			// switch itself, which the engine leaves alone.
			pressed = (target.closest && target.closest(".dkfh-fab")) || target;
			pressed.classList.add("is-pressed");
		}
	}

	/**
	 * Flip the preview on a loop.
	 *
	 * The switch itself is Darkify's — `darkify_switch_trigger()` inside the
	 * frame, the same call the switcher's own click makes — so what a visitor
	 * watches is the engine repainting the sample site, not a canned animation
	 * between two hand-drawn states.
	 *
	 * The visible transition is a cross-fade on the frame rather than a colour
	 * tween on its elements, because the engine suspends transitions while it
	 * repaints (it has to read settled colours, not interpolated ones). Dipping
	 * the preview out, flipping it, and bringing it back reads as one smooth
	 * change and never as a flash.
	 *
	 * It runs only while the preview is on screen and the tab is visible, and
	 * not at all for a visitor who asked for reduced motion.
	 */
	function startAutoplay(instance) {
		var root = instance.root;
		if (!root.hasAttribute("data-dkfd-autoplay") || instance.autoplay) {
			return;
		}

		var frame = instance.frame;
		var number = function (name, fallback) {
			var value = parseInt(root.getAttribute("data-dkfd-" + name), 10);
			return isNaN(value) ? fallback : value;
		};

		var timing = {
			light: number("light-hold", 2800),
			dark: number("dark-hold", 3600),
			adminLight: number("admin-hold", 2800),
			adminDark: number("admin-dark-hold", 3600),
			fade: number("fade", 260),
			// The extra beat the veil is held shut for while the preview walks
			// between the front end and wp-admin. A flip is instant and should
			// look it; moving to another screen is not.
			travel: 340
		};

		var badge = root.querySelector("[data-dkfd-mode]");
		var labels = {
			light: root.getAttribute("data-dkfd-label-light") || "Light",
			dark: root.getAttribute("data-dkfd-label-dark") || "Dark"
		};

		var stage = frame.doc.querySelector(".dkfh-stage");
		var address = root.querySelector(".dkfd__url");

		/*
		 * The preset the loop picks in the admin's palette dropdown, with the
		 * `--darkify_dark_mode_*` values the server resolved for it. Absent —
		 * no dropdown rendered, or a Darkify whose schema does not offer the
		 * named preset — and the loop simply does not have that step.
		 */
		var palette = (function () {
			var raw = root.getAttribute("data-dkfd-palette");
			if (!raw || !frame.doc.querySelector(".dkfa-select--live")) {
				return null;
			}
			try {
				return JSON.parse(raw);
			} catch (e) {
				return null;
			}
		})();

		timing.palette = number("palette-hold", 3600);
		var urls = {
			site: root.getAttribute("data-dkfd-url") || "",
			admin: root.getAttribute("data-dkfd-admin-url") || ""
		};

		var timers = [];
		var running = false;
		var cursor = createCursor(instance);

		var after = function (delay, fn) {
			var id = window.setTimeout(function () {
				if (running) {
					fn();
				}
			}, delay);
			timers.push(id);
			return id;
		};

		var clearTimers = function () {
			timers.forEach(window.clearTimeout);
			timers = [];
		};

		var isDark = function () {
			return frame.doc.documentElement.classList.contains("darkify_dark_mode_enabled");
		};

		var announce = function () {
			var mode = isDark() ? "dark" : "light";
			if (badge && badge.getAttribute("data-dkfd-mode") !== mode) {
				badge.setAttribute("data-dkfd-mode", mode);
				badge.textContent = labels[mode];
			}
		};

		/**
		 * Throw the switch — by clicking Darkify's own switcher, the same path a
		 * visitor's click takes, so what the pointer appears to do is what
		 * actually happens.
		 */
		var throwSwitch = function () {
			var element = frame.doc.querySelector(".darkify_switch");
			if (element && typeof element.click === "function") {
				element.click();
			} else if (typeof frame.win.darkify_switch_trigger === "function") {
				frame.win.darkify_switch_trigger();
			}
		};

		/**
		 * Cross-dissolve the flip.
		 *
		 * Darkify repaints the sample page in a single frame, background and
		 * all, and that background cannot be tweened from out here: the engine
		 * suspends CSS transitions while it reads colours (so it classifies
		 * settled ones), and the page background is painted on the canvas behind
		 * the content, so dimming the content leaves it untouched. Measured, it
		 * went rgb(255,255,255) to rgb(15,15,15) in one frame at full contrast —
		 * the flash.
		 *
		 * So the swap happens behind a veil: it takes the colour the page is
		 * already showing, fades in over it, the switch is thrown while it is
		 * opaque, and it fades out onto the new colour. The veil is driven with
		 * the Web Animations API, which the engine's transition suspension does
		 * not touch, and it is marked `darkify_ignore` so the engine never
		 * repaints the veil itself mid-dissolve.
		 */
		var veil = frame.doc.querySelector(".dkfh-veil");

		var pageColor = function () {
			var body = frame.doc.body;
			var color = frame.win.getComputedStyle(body).backgroundColor;

			// A transparent body would make the veil invisible and defeat the
			// point; fall back to the html element, then to white.
			if (!color || "rgba(0, 0, 0, 0)" === color || "transparent" === color) {
				color = frame.win.getComputedStyle(frame.doc.documentElement).backgroundColor;
			}

			return !color || "rgba(0, 0, 0, 0)" === color ? "#ffffff" : color;
		};

		var fade = function (from, to, duration) {
			if (!veil || typeof veil.animate !== "function") {
				return null;
			}

			return veil.animate(
				[ { opacity: from }, { opacity: to } ],
				{ duration: duration, easing: "ease-in-out", fill: "forwards" }
			);
		};

		/**
		 * Throw the switch behind the veil: cover, flip, reveal.
		 */
		var flip = function (done) {
			if (!veil || reducedMotion()) {
				// No dissolve to run: throw the switch and move on.
				throwSwitch();
				announce();
				done();
				return;
			}

			cover(function () {
				throwSwitch();
				announce();
			}, 0, done);
		};

		/**
		 * Walk to the other view — the front end to wp-admin, or back.
		 *
		 * It rides the same cover as a flip, held a beat longer so it reads as a
		 * page load rather than a switch being thrown, with the address in the
		 * window chrome and a loading bar carrying the rest of the story. The
		 * preview always arrives in light mode: what the next half of the loop
		 * is there to show is Darkify turning that screen dark.
		 */
		var travel = function (view, done) {
			var arrive = function () {
				// Order matters. The front end's switcher is taken off screen
				// first, so it cannot be seen morphing above the veil while the
				// page it belongs to is being swapped out.
				if (stage) {
					stage.classList.add("is-swapping");
				}
				// The palette goes back before the lights do, while the engine is
				// still dark and a sweep still means something. Leaving it set
				// would start the next pass from this one's leftovers.
				setPalette(false);
				if (isDark()) {
					throwSwitch();
				}
				if (stage) {
					stage.classList.toggle("is-admin", "admin" === view);
				}
				setAddress(view);
				announce();
			};

			var settle = function () {
				if (stage) {
					stage.classList.remove("is-swapping");
				}
				root.classList.remove("is-loading");
				done();
			};

			root.classList.add("is-loading");

			if (!veil || reducedMotion()) {
				arrive();
				settle();
				return;
			}

			cover(arrive, timing.travel, settle);
		};

		/**
		 * The cross-dissolve itself: fade the veil in, run `change` while it is
		 * opaque, hold for `dwell`, then fade it back out onto whatever is now
		 * underneath.
		 */
		var cover = function (change, dwell, done) {
			veil.style.backgroundColor = pageColor();

			var closing = fade(0, 1, timing.fade);

			after(timing.fade, function () {
				change();

				// One frame for the engine to commit its repaint underneath,
				// then reveal the page in its new state.
				frame.win.requestAnimationFrame(function () {
					if (!running) {
						return;
					}

					after(dwell, function () {
						if (closing) {
							closing.cancel();
						}

						var opening = fade(1, 0, timing.fade);

						after(timing.fade, function () {
							if (opening) {
								opening.cancel();
							}
							veil.style.opacity = "";
							done();
						});
					});
				});
			});
		};

		/*
		 * Picking the preset.
		 *
		 * The recolour is Darkify's: `CONTROLS.preset` writes the same
		 * `--darkify_dark_mode_*` variables Darkify's own palette switcher
		 * assigns, swaps the `darkify-<set>` class that keys the engine's
		 * surface-token cache, and asks for a state sweep so colours the engine
		 * resolved in JS are re-derived too. Nothing here paints a second
		 * palette of its own.
		 *
		 * The class the preview opened with is remembered so the loop can hand
		 * the frame back exactly as it found it — otherwise the second pass
		 * would start from the first pass's leftovers.
		 */
		var baseSet = toArray(frame.doc.documentElement.classList).filter(function (name) {
			return 0 === name.indexOf("darkify-set");
		})[0] || "";

		var select = frame.doc.querySelector(".dkfa-select--live");
		var pickedOption = select && select.querySelector(".dkfa-option--target");
		var autoOption = select && select.querySelector(".dkfa-option.is-selected");

		var setPalette = function (picked) {
			if (!palette || !select) {
				return;
			}

			var label = select.querySelector(".dkfa-select__label");
			var dot = select.querySelector(".dkfa-select__dot");
			var element = frame.doc.documentElement;

			/*
			 * The control is updated before the preset is applied, not after.
			 * The swatch is set to the palette's own colour — a dark green for
			 * Verdant Depths — and it is `CONTROLS.preset`'s state sweep that
			 * turns that into the light teal a dark dashboard needs. Setting it
			 * afterwards would leave the sweep nothing to re-derive and the dot
			 * invisible on its own background.
			 */
			if (label) {
				label.textContent = picked ? palette.label : autoLabel;
			}
			// A custom property, not a background: the stylesheet needs to be able
			// to override it in dark mode, where the preset's raw colour is the
			// page's own and would vanish into it. An inline background could not
			// be overridden at all.
			if (dot) {
				dot.style.setProperty("--dkfa-dot", picked ? palette.dot : "");
			}
			if (select) {
				select.classList.toggle("is-picked", picked);
			}
			if (pickedOption) {
				pickedOption.classList.toggle("is-selected", picked);
				pickedOption.classList.remove("is-hover");
			}
			if (autoOption && autoOption !== pickedOption) {
				autoOption.classList.toggle("is-selected", !picked);
			}

			if (picked) {
				CONTROLS.preset(frame, { value: palette.value, vars: palette.vars });
			} else {
				// Back to Auto: drop the variables rather than overwriting them,
				// so the engine falls back to the site's own configuration.
				Object.keys(palette.vars || {}).forEach(function (name) {
					element.style.removeProperty(name);
				});
				CONTROLS.preset(frame, { value: baseSet.replace(/^darkify-/, "") || "set1", vars: null });
				if (!baseSet) {
					element.classList.remove("darkify-set1");
				}
			}
		};

		var autoLabel = (function () {
			var label = select && select.querySelector(".dkfa-select__label");
			return label ? label.textContent : "";
		})();

		/** Open the dropdown, and walk the highlight onto the row being aimed at. */
		var openPalette = function (done) {
			if (select) {
				select.classList.add("is-open");
			}
			// The highlight lands as the pointer does, not halfway down the list.
			after(Math.max(0, timing.pick - 220), function () {
				if (pickedOption) {
					pickedOption.classList.add("is-hover");
				}
			});
			done();
		};

		/** Choose it: close the menu and repaint, behind the same veil as a flip. */
		var choosePalette = function (done) {
			var change = function () {
				if (select) {
					select.classList.remove("is-open");
				}
				setPalette(true);
			};

			if (!veil || reducedMotion()) {
				change();
				done();
				return;
			}

			cover(change, 0, done);
		};

		/** The address follows the view, with a beat of fade so it is noticed. */
		var setAddress = function (view) {
			if (!address || !urls[view]) {
				return;
			}

			address.classList.add("is-changing");
			address.textContent = urls[view];

			after(240, function () {
				address.classList.remove("is-changing");
			});
		};

		/**
		 * The loop, as a list of held moments.
		 *
		 * Each step is something the preview sits in front of and the move it
		 * makes when the hold runs out: front end light, front end dark, over to
		 * wp-admin, admin light, admin dark, the palette dropdown, and back.
		 * Without the admin half it is the two-step loop it has always been, and
		 * without a resolvable preset the two palette steps drop out of it.
		 *
		 * `hold` is time spent looking at that state. The pointer's walk overlaps
		 * the tail of it rather than being added to it, so `light_hold` and the
		 * rest stay what they say.
		 *
		 * `target` names the control the pointer walks to; a step without one is
		 * not a click at all, and the pointer sits it out — nobody presses a
		 * button to load a page.
		 */
		var admin = root.hasAttribute("data-dkfd-admin") && stage &&
			!!frame.doc.querySelector(".dkfh-view--admin");

		// How long the dropdown stays open before the row under the pointer is
		// taken. Long enough to read the list it came from.
		timing.pick = 1100;

		var steps = [{ hold: timing.light, act: flip }];

		if (admin) {
			steps.push({ hold: timing.dark, act: travelTo("admin") });
			steps.push({ hold: timing.adminLight, act: flip });

			if (palette && select) {
				steps.push({ hold: timing.adminDark, act: openPalette, target: ".dkfa-select--live .dkfa-select__value" });
				steps.push({ hold: timing.pick, act: choosePalette, target: ".dkfa-option--target" });
				steps.push({ hold: timing.palette, act: travelTo("site") });
			} else {
				steps.push({ hold: timing.adminDark, act: travelTo("site") });
			}
		} else {
			steps.push({ hold: timing.dark, act: flip });
		}

		var step = 0;

		function travelTo(view) {
			return function (done) {
				travel(view, done);
			};
		}

		/**
		 * One cycle: hold, walk the pointer over, press, act, and withdraw.
		 */
		var schedule = function () {
			if (!running) {
				return;
			}

			// Deliberately not clearing here: the pointer's withdrawal is still
			// pending when the next cycle is scheduled, and it belongs to the
			// move that just happened. start() and stop() own the timer list.
			var current = steps[step % steps.length];
			step = (step + 1) % steps.length;

			var act = function () {
				current.act(schedule);
			};

			// A step with no control to press is not a click: hold, then act.
			if (!cursor || (!current.target && current.act !== flip)) {
				after(current.hold, act);
				return;
			}

			var lead = cursor.lead(current.target);

			after(Math.max(0, current.hold - lead), function () {
				cursor.approach(current.target);

				after(lead, function () {
					cursor.press(current.target);
					act();

					after(cursor.pressDuration, cursor.release);
					// Leaves once the new state is on screen, not before — and
					// early enough to be back at rest, drifting, before the next
					// cycle sets off. It stays put while a menu it just opened is
					// still open: the next step's target is inside that menu.
					if (current.act !== openPalette) {
						after(timing.fade + 260, cursor.retreat);
					}
				});
			});
		};

		var start = function () {
			if (running || reducedMotion()) {
				return;
			}
			running = true;
			// Pick up at the step that matches the state the preview is actually
			// in, so a hero told to open dark holds for `dark_hold` rather than
			// for the light step it never played.
			step = isDark() ? 1 : 0;
			announce();
			schedule();
		};

		var stop = function () {
			running = false;
			clearTimers();

			// Leave nothing half-dissolved behind.
			if (veil) {
				veil.getAnimations().forEach(function (animation) {
					animation.cancel();
				});
				veil.style.opacity = "";
			}

			if (stage) {
				stage.classList.remove("is-swapping");
			}
			root.classList.remove("is-loading");

			// A dropdown caught mid-open would still be hanging there when the
			// preview scrolls back into view.
			if (select) {
				select.classList.remove("is-open");
			}
			if (pickedOption) {
				pickedOption.classList.remove("is-hover");
			}

			if (cursor) {
				cursor.reset();
			}
		};

		instance.autoplay = { start: start, stop: stop };

		// The preview should be flipping when it is being looked at, and idle
		// when it is not: off screen, in a background tab, or once the visitor
		// switches on reduced motion mid-visit.
		if (typeof window.IntersectionObserver === "function") {
			new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting && !document.hidden) {
						start();
					} else {
						stop();
					}
				});
			}, { threshold: 0.25 }).observe(root);
		} else {
			start();
		}

		document.addEventListener("visibilitychange", function () {
			if (document.hidden) {
				stop();
			} else if (isVisible(root)) {
				start();
			}
		});

		watchReducedMotion(function (reduced) {
			if (reduced) {
				stop();
			} else if (isVisible(root)) {
				start();
			}
		});
	}

	function reducedMotion() {
		return typeof window.matchMedia === "function" &&
			window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	function watchReducedMotion(onChange) {
		if (typeof window.matchMedia !== "function") {
			return;
		}
		var query = window.matchMedia("(prefers-reduced-motion: reduce)");
		var handler = function () {
			onChange(query.matches);
		};
		if (typeof query.addEventListener === "function") {
			query.addEventListener("change", handler);
		} else if (typeof query.addListener === "function") {
			query.addListener(handler);
		}
	}

	function isVisible(element) {
		var rect = element.getBoundingClientRect();
		return rect.bottom > 0 && rect.top < (window.innerHeight || document.documentElement.clientHeight);
	}

	/* --------------------------------------------------------------------- */
	/* Boot                                                                  */
	/* --------------------------------------------------------------------- */

	function boot(instance) {
		var root = instance.root;
		if (root.dataset.dkfdReady) {
			return;
		}

		var iframe = root.querySelector(".dkfd__frame");
		var template = root.querySelector(".dkfd__source");
		if (!iframe || !template) {
			return;
		}

		var doc = iframe.contentDocument;
		var win = iframe.contentWindow;
		if (!doc || !win) {
			return;
		}

		root.dataset.dkfdReady = "1";

		doc.open();
		doc.write(SKELETON);
		doc.close();

		// Re-read: writing the skeleton replaces the document.
		doc = iframe.contentDocument;
		win = iframe.contentWindow;

		var storage = isolateStorage(win);

		doc.documentElement.setAttribute("lang", document.documentElement.lang || "en");

		var font = baseFont();
		if (font) {
			// The sample site borrows the host site's typography so the preview
			// reads as "your site", not as a generic mock-up.
			doc.documentElement.style.setProperty("--dkfd-font", font);
		}

		buildHead(root, doc, function () {
			doc.body.innerHTML = template.innerHTML;

			bootEngine(win, doc, function (frameWin, frameDoc) {
				function applyHeight() {
					root.style.setProperty("--dkfd-height", contentHeight(frameWin, frameDoc) + "px");
				}

				if (!storage.isolated) {
					protectRealStorage(frameWin, frameDoc);
				}

				installGuard(frameWin, frameDoc, applyHeight);
				watchHeight(frameWin, frameDoc, applyHeight);

				// Darkify runs this from a DOMContentLoaded handler, which has
				// already fired by the time the engine is appended here.
				try {
					if (typeof frameWin.darkify_init_attention_effect === "function") {
						frameWin.darkify_init_attention_effect();
					}
				} catch (e) {
					/* optional flourish only */
				}

				// The controls address the switcher inside the frame, so they
				// can only take effect once it exists.
				instance.frame = { win: frameWin, doc: frameDoc };
				applyState(instance);

				// A hero told to open in dark mode throws the switch once
				// before anyone sees it, through the guard so the state sticks.
				if ("dark" === root.getAttribute("data-dkfd-start") &&
					typeof frameWin.darkify_switch_trigger === "function") {
					frameWin.darkify_switch_trigger();
				}

				// Needs instance.frame, so it goes last.
				startAutoplay(instance);

				root.classList.add("dkfd--ready");
			});
		});
	}

	function observe(instance) {
		if (typeof window.IntersectionObserver !== "function") {
			boot(instance);
			return;
		}

		// The preview loads a full copy of the engine; there is no reason to do
		// that before it is anywhere near the viewport.
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					observer.disconnect();
					boot(instance);
				}
			});
		}, { rootMargin: "300px" });

		observer.observe(instance.root);
	}

	function init() {
		toArray(document.querySelectorAll(".dkfd")).forEach(function (root) {
			var instance = { root: root, frame: null, state: {} };

			// Wired before the frame is built: a click that lands early is kept
			// as state and applied the moment the preview is ready.
			wireControls(instance);
			observe(instance);
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
