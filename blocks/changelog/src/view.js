/**
 * darkify-util/changelog — front-end behaviour.
 *
 * A true ES module (block.json declares it as `viewScriptModule`), vanilla, no
 * dependencies. Everything here is enhancement: PHP has already rendered every
 * version, every entry, the search field and the load-more button. This module
 * only ever *hides* things it knows how to bring back, which is why the page is
 * complete and readable with JavaScript switched off.
 */

const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' );

/**
 * Enhance one rendered changelog.
 *
 * Exported because the editor runs it over the same server-rendered markup, so
 * the preview filters, pages and scrolls exactly like the published page rather
 * than approximating it.
 *
 * @param {HTMLElement} root Block root.
 */
export function setUpBlock( root ) {
	if ( root.dataset.changelogReady ) {
		return;
	}
	root.dataset.changelogReady = '1';

	const versions = Array.from(
		root.querySelectorAll( '.darkify-changelog__version' )
	);

	if ( ! versions.length ) {
		return;
	}

	const navLinks = Array.from( root.querySelectorAll( '[data-version-link]' ) );

	/*
	 * Shared between the pieces below. `scrolling` is on while the navigation
	 * animates the page, so the reveal animation stays out of the way and the
	 * active item does not sweep through every version on the way past;
	 * `syncActive` lets anything that changes which versions are on the page
	 * (paging, filtering, searching, collapsing) re-run the scroll spy.
	 */
	const state = {
		scrolling: false,
		syncActive: () => {},
	};

	setUpCollapse( root, state );
	const pagination = setUpLoadMore( root, versions, state );
	setUpNavigation( root, versions, navLinks, pagination, state );
	setUpSearch( root, versions, navLinks, pagination, state );
	setUpFilters( root, versions, navLinks, pagination, state );
	setUpReveal( versions, state );
}

/* ---------------------------------------------------------------------- */
/* Scrolling                                                               */
/* ---------------------------------------------------------------------- */

/**
 * The thing that actually scrolls behind an element.
 *
 * On the front end that is the document. In the editor the block sits inside
 * the canvas, which is its own scroll container — so this is resolved from the
 * element rather than assumed to be the window, and `node: null` means "the
 * document itself" so the two cases can share the readers below.
 *
 * @param {HTMLElement} element Element to find the scroller for.
 * @return {Object} `{ node, win, doc }`.
 */
function scrollerFor( element ) {
	const doc = element.ownerDocument || document;
	const win = doc.defaultView || window;
	let node = element.parentElement;

	while ( node && node !== doc.body && node !== doc.documentElement ) {
		const style = win.getComputedStyle( node );

		if (
			/(auto|scroll|overlay)/.test( style.overflowY ) &&
			node.scrollHeight > node.clientHeight + 1
		) {
			return { node, win, doc };
		}

		node = node.parentElement;
	}

	return { node: null, win, doc };
}

/** @param {Object} scroller Scroller. @return {number} Current scroll offset. */
function scrollTop( scroller ) {
	return scroller.node
		? scroller.node.scrollTop
		: scroller.win.scrollY || scroller.doc.documentElement.scrollTop || 0;
}

/** @param {Object} scroller Scroller. @param {number} value Offset to set. */
function setScrollTop( scroller, value ) {
	if ( scroller.node ) {
		scroller.node.scrollTop = value;
	} else {
		scroller.win.scrollTo( 0, value );
	}
}

/** @param {Object} scroller Scroller. @return {number} Largest valid offset. */
function maxScrollTop( scroller ) {
	if ( scroller.node ) {
		return Math.max( 0, scroller.node.scrollHeight - scroller.node.clientHeight );
	}

	const docEl = scroller.doc.documentElement;

	return Math.max( 0, docEl.scrollHeight - scroller.win.innerHeight );
}

/** @param {Object} scroller Scroller. @return {number} Top of the visible area, in client coordinates. */
function viewportTop( scroller ) {
	return scroller.node ? scroller.node.getBoundingClientRect().top : 0;
}

/**
 * How much of the top of the viewport is covered by something pinned there.
 *
 * The theme's header is `position: sticky` and slides its own `top` between 0
 * and -100px as the visitor scrolls, and the admin bar is fixed — so this
 * cannot be a constant. Rather than keep a list of selectors that would go
 * stale, it asks the browser what is actually painted over the top of the
 * viewport right now and takes the lowest edge of whatever is pinned there.
 *
 * @param {Object}      scroller Scroller.
 * @param {HTMLElement} root     Block root, whose own sticky nav is excluded.
 * @return {number} Covered height in pixels.
 */
function overlayOffset( scroller, root ) {
	// Only the document's own viewport can have things pinned over it; a
	// scrolling container inside the page has no such overlay.
	if ( scroller.node || 'function' !== typeof scroller.doc.elementsFromPoint ) {
		return 0;
	}

	const { doc, win } = scroller;
	const x = Math.round( win.innerWidth / 2 );
	const limit = win.innerHeight / 2;
	let offset = 0;

	// A few sample rows so a thin admin bar and a tall header are both found.
	[ 1, 16, 40, 72 ].forEach( ( y ) => {
		doc.elementsFromPoint( x, y ).forEach( ( node ) => {
			if (
				! node ||
				node === doc.body ||
				node === doc.documentElement ||
				root.contains( node )
			) {
				return;
			}

			const position = win.getComputedStyle( node ).position;

			if ( 'fixed' !== position && 'sticky' !== position ) {
				return;
			}

			const rect = node.getBoundingClientRect();

			// Pinned to the top, and a bar rather than a full-height overlay.
			if ( rect.top <= 1 && rect.bottom > offset && rect.bottom < limit ) {
				offset = rect.bottom;
			}
		} );
	} );

	return offset;
}

/**
 * The gap a version wants above it: whatever is pinned over the top of the
 * viewport, plus the block's own `scroll-margin-top`.
 *
 * @param {HTMLElement} target   Version element.
 * @param {Object}      scroller Scroller.
 * @param {HTMLElement} root     Block root.
 * @return {number} Offset in pixels.
 */
function offsetFor( target, scroller, root ) {
	const margin = parseFloat(
		scroller.win.getComputedStyle( target ).scrollMarginTop
	);

	return overlayOffset( scroller, root ) + ( isNaN( margin ) ? 0 : margin );
}

/**
 * Where the scroller has to land for a version to sit under the overlay.
 *
 * Read from the element's live position rather than a number cached when the
 * animation started, so a reveal, an image settling or a version opening part
 * way through moves the destination instead of leaving the scroll short.
 *
 * @param {HTMLElement} target   Version element.
 * @param {Object}      scroller Scroller.
 * @param {number}      offset   Overlay offset.
 * @return {number} Scroll offset, clamped to the scrollable range.
 */
function destinationFor( target, scroller, offset ) {
	const relative =
		target.getBoundingClientRect().top - viewportTop( scroller );
	const destination = scrollTop( scroller ) + relative - offset;

	return Math.max( 0, Math.min( destination, maxScrollTop( scroller ) ) );
}

/**
 * Marks the document while the page is being moved by a script rather than by
 * the visitor. `assets/js/custom-script.js` reads it and holds the site header
 * still for the duration.
 *
 * Without that agreement the two fight: the header hides on the way down, so
 * the scroll aims at an unobstructed target, then slides back in over it once
 * the page stops — and correcting for that mid-flight only starts an
 * oscillation, because scrolling down to compensate hides the header again.
 */
const SCROLLING_FLAG = 'darkifyScrolling';

let holds = 0;

/** @param {HTMLElement} docEl Document element carrying the flag. */
function holdHeader( docEl ) {
	holds++;
	holdHeader( docEl );
}

/** @param {HTMLElement} docEl Document element carrying the flag. */
function releaseHeader( docEl ) {
	const win = docEl.ownerDocument.defaultView || window;

	/*
	 * A `scroll` event is dispatched after the scroll it describes, so the last
	 * hop's event arrives once the flag would already have been cleared — where
	 * the header reads it as the visitor scrolling down and slides away the
	 * moment they arrive. A pair of frames covers that normally, but not when a
	 * frame is dropped, so the hold is released on a short timer instead.
	 * Counted, so overlapping scrolls cannot release each other's hold.
	 */
	win.setTimeout( () => {
		holds = Math.max( 0, holds - 1 );

		if ( 0 === holds ) {
			delete docEl.dataset[ SCROLLING_FLAG ];
		}
	}, 150 );
}

/** @param {number} t Progress 0–1. @return {number} Eased progress. */
function easeInOutCubic( t ) {
	return t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2;
}

/**
 * Animate the page to a version.
 *
 * Hand-rolled rather than `scrollIntoView( { behavior: 'smooth' } )`, for two
 * reasons the native call cannot cover. The destination depends on a sticky
 * header whose height is only knowable at run time — `scroll-margin-top` alone
 * is a fixed number and cannot stand in for it — and it has to be recomputed as
 * the animation runs, because anything that shifts the page above the target
 * while the scroll is in flight moves where the target actually is. Native
 * smooth scroll fixes its destination on the first frame, which is what left
 * the page in the wrong place and then snapped.
 *
 * @param {HTMLElement} root   Block root.
 * @param {HTMLElement} target Version to scroll to.
 * @param {Function}    done   Called once the scroll has settled, with `true`
 *                             when it arrived and `false` when the visitor
 *                             scrolled out from under it.
 */
function scrollToVersion( root, target, done ) {
	const scroller = scrollerFor( target );
	const win = scroller.win;
	const docEl = scroller.doc.documentElement;
	const from = scrollTop( scroller );

	const destination = () =>
		destinationFor( target, scroller, offsetFor( target, scroller, root ) );

	docEl.dataset[ SCROLLING_FLAG ] = '1';

	let frame = null;
	let started = 0;
	let finished = false;

	const finish = ( arrived ) => {
		if ( finished ) {
			return;
		}

		finished = true;

		if ( null !== frame ) {
			win.cancelAnimationFrame( frame );
			frame = null;
		}

		unbind();
		releaseHeader( docEl );
		done( true === arrived );
	};

	// Any real scroll input hands control straight back to the visitor rather
	// than fighting them for the rest of the animation.
	const interrupt = ( event ) => {
		if (
			'keydown' === event.type &&
			! [
				'ArrowUp',
				'ArrowDown',
				'PageUp',
				'PageDown',
				'Home',
				'End',
				' ',
			].includes( event.key )
		) {
			return;
		}

		finish( false );
	};

	const events = [ 'wheel', 'touchstart', 'keydown' ];

	function unbind() {
		events.forEach( ( type ) =>
			win.removeEventListener( type, interrupt, { capture: true } )
		);
	}

	events.forEach( ( type ) =>
		win.addEventListener( type, interrupt, { capture: true, passive: true } )
	);

	const clock = win.performance || Date;
	let duration = 0;

	/*
	 * Two phases. The first eases from where the visitor was to where the
	 * version is; the second keeps following the destination for a moment
	 * afterwards, so anything that settles late above the fold — an image
	 * finding its height, a font swapping in — is absorbed as a few soft pixels
	 * instead of leaving the heading somewhere other than where it was aimed.
	 */
	let settled = 0;

	const step = () => {
		const elapsed = clock.now() - started;
		const current = scrollTop( scroller );
		const goal = destination();

		if ( elapsed < duration ) {
			setScrollTop(
				scroller,
				from + ( goal - from ) * easeInOutCubic( elapsed / duration )
			);
			frame = win.requestAnimationFrame( step );
			return;
		}

		const remaining = goal - current;

		if ( Math.abs( remaining ) < 0.5 ) {
			settled++;
		} else {
			settled = 0;
			// Reduced motion still has to settle — the header only becomes an
			// overlay once the page has scrolled past it, so its height cannot
			// be known before the move — but it gets there in one hop per
			// frame rather than easing into place.
			setScrollTop(
				scroller,
				current + remaining * ( REDUCED_MOTION.matches ? 1 : 0.25 )
			);
		}

		// Three still frames, or half a second of chasing, whichever is first.
		if ( settled < 3 && elapsed < duration + 500 ) {
			frame = win.requestAnimationFrame( step );
			return;
		}

		setScrollTop( scroller, goal );
		frame = null;
		finish( true );
	};

	/*
	 * One frame between raising the flag and taking the first measurement, so
	 * anything that reacts to it — the site header returning to its shown
	 * position — has done so. Measuring in the same frame reads a header that
	 * is about to move and aims the scroll at the wrong place.
	 */
	frame = win.requestAnimationFrame( () => {
		if ( finished ) {
			return;
		}

		const goal = destination();

		// Long jumps take longer, but never so long that the page feels slow.
		// A zero duration drops straight through to the settle, which is how
		// reduced motion arrives in one hop.
		duration = REDUCED_MOTION.matches
			? 0
			: Math.min( 900, Math.max( 320, Math.abs( goal - from ) * 0.5 ) );
		started = clock.now();
		frame = win.requestAnimationFrame( step );
	} );
}

/* ---------------------------------------------------------------------- */
/* Version navigation                                                      */
/* ---------------------------------------------------------------------- */

/**
 * Smooth-scroll to a version and keep the list's active item in step.
 *
 * @param {HTMLElement} root       Block root.
 * @param {Array}       versions   Version elements.
 * @param {Array}       navLinks   Navigation links.
 * @param {?Object}     pagination Load-more controls, if any.
 * @param {Object}      state      Shared block state.
 */
function setUpNavigation( root, versions, navLinks, pagination, state ) {
	if ( ! navLinks.length ) {
		return;
	}

	const doc = root.ownerDocument || document;
	const win = doc.defaultView || window;
	const navList = root.querySelector( '.darkify-changelog__nav-list' );

	let scroller = null;
	const getScroller = () => scroller || ( scroller = scrollerFor( root ) );

	let active = '';
	// While the visitor is being taken somewhere by a click, that somewhere is
	// the active item — otherwise the list would flick through every version on
	// the way past and only settle at the end.
	let locked = '';

	/*
	 * On a phone the list is a horizontal strip and on a wide screen it is a
	 * scrolling column, so a long changelog can leave the active item out of
	 * sight. Only the list's own scroll offsets are touched — nudging the page
	 * itself here would fight the scroll that caused this in the first place.
	 */
	const keepLinkInView = ( link ) => {
		if ( ! navList ) {
			return;
		}

		const listRect = navList.getBoundingClientRect();
		const linkRect = link.getBoundingClientRect();

		if ( navList.scrollWidth > navList.clientWidth + 1 ) {
			if ( linkRect.left < listRect.left ) {
				navList.scrollLeft -= listRect.left - linkRect.left + 12;
			} else if ( linkRect.right > listRect.right ) {
				navList.scrollLeft += linkRect.right - listRect.right + 12;
			}
		}

		const column = navList.scrollHeight > navList.clientHeight + 1
			? navList
			: navList.closest( '.darkify-changelog__nav' );

		if ( ! column || column.scrollHeight <= column.clientHeight + 1 ) {
			return;
		}

		const columnRect = column.getBoundingClientRect();

		if ( linkRect.top < columnRect.top ) {
			column.scrollTop -= columnRect.top - linkRect.top + 12;
		} else if ( linkRect.bottom > columnRect.bottom ) {
			column.scrollTop += linkRect.bottom - columnRect.bottom + 12;
		}
	};

	const setActive = ( slug ) => {
		if ( ! slug || slug === active ) {
			return;
		}

		active = slug;

		navLinks.forEach( ( link ) => {
			const isActive = link.dataset.versionLink === slug;

			link.classList.toggle( 'is-active', isActive );

			if ( isActive ) {
				link.setAttribute( 'aria-current', 'true' );
				keepLinkInView( link );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	};

	/**
	 * Which version the visitor is actually looking at.
	 *
	 * Resolved from position rather than from IntersectionObserver callbacks.
	 * An observer only reports the entries that *changed*, and its margin band
	 * leaves the last version unreachable when the page runs out of scroll
	 * before it gets there — which is why the final item could never go active.
	 * Measuring against a reading line has neither problem, and it agrees with
	 * where a click lands the page by construction.
	 *
	 * @return {?string} Version slug.
	 */
	const resolveActive = () => {
		const visible = versions.filter(
			( version ) => ! version.hidden && null !== version.offsetParent
		);

		if ( ! visible.length ) {
			return null;
		}

		const view = getScroller();

		/*
		 * The reading line is exactly where a click parks a version: just under
		 * whatever is pinned over the top of the page, plus the block's own
		 * scroll-margin. Putting it there is what makes clicking and scrolling
		 * agree — the version a click scrolls to lands on the line, so it is
		 * the version the line reports.
		 *
		 * A line further down the viewport reads more naturally in the
		 * abstract, but it cannot be reached by anything shorter than the drop:
		 * a short release would scroll into place and still leave the release
		 * above it marked active, because that one's top was also past the line.
		 */
		const line =
			viewportTop( view ) + offsetFor( visible[ 0 ], view, root ) + 8;

		// The page can run out of scroll before the last versions reach the
		// line, so at the bottom the last one on screen wins outright.
		if ( scrollTop( view ) >= maxScrollTop( view ) - 2 ) {
			return visible[ visible.length - 1 ].dataset.version;
		}

		let current = visible[ 0 ];

		for ( const version of visible ) {
			if ( version.getBoundingClientRect().top - 1 <= line ) {
				current = version;
			} else {
				break;
			}
		}

		return current.dataset.version;
	};

	let ticking = false;

	const sync = () => {
		if ( ticking ) {
			return;
		}

		ticking = true;

		win.requestAnimationFrame( () => {
			ticking = false;

			if ( locked ) {
				setActive( locked );
				return;
			}

			setActive( resolveActive() );
		} );
	};

	state.syncActive = sync;

	navLinks.forEach( ( link ) => {
		link.addEventListener( 'click', ( event ) => {
			// Leave modified and non-primary clicks to the browser: they are
			// "open this somewhere else", not "take me there".
			if (
				event.defaultPrevented ||
				( typeof event.button === 'number' && 0 !== event.button ) ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}

			const slug = link.dataset.versionLink;
			const target = root.querySelector(
				`[data-version="${ CSS.escape( slug ) }"]`
			);

			if ( ! target ) {
				return;
			}

			event.preventDefault();

			// A hidden version (beyond the current load-more page, or filtered
			// out by a search) is revealed first, so the link never scrolls to
			// nothing.
			if ( target.hidden ) {
				const index = versions.indexOf( target );

				if ( pagination ) {
					// Through the pager, so its own count moves with the
					// reveal and the next page does not re-hide what this
					// click just opened.
					pagination.revealThrough( index );
				} else {
					revealThrough( versions, index );
				}
			}

			locked = slug;
			state.scrolling = true;
			setActive( slug );

			scrollToVersion( root, target, ( arrived ) => {
				state.scrolling = false;
				locked = '';

				// Moves keyboard focus with the scroll; tabindex is removed
				// again so the heading does not become a permanent tab stop.
				// Focus is taken at the end rather than the start because
				// focusing mid-animation is what browsers scroll for — and not
				// at all if the visitor scrolled somewhere else on the way,
				// since the heading is no longer where they are looking.
				const heading = arrived
					? target.querySelector( '.darkify-changelog__version-number' )
					: null;

				if ( heading ) {
					heading.setAttribute( 'tabindex', '-1' );
					heading.focus( { preventScroll: true } );
					heading.addEventListener(
						'blur',
						() => heading.removeAttribute( 'tabindex' ),
						{ once: true }
					);
				}

				sync();
			} );

			if ( win.history && win.history.replaceState ) {
				win.history.replaceState( null, '', `#${ slug }` );
			}
		} );
	} );

	doc.addEventListener( 'scroll', sync, { capture: true, passive: true } );
	win.addEventListener( 'resize', sync, { passive: true } );

	const hash = win.location.hash.replace( '#', '' );
	const fromHash = navLinks.some( ( link ) => link.dataset.versionLink === hash );

	setActive( fromHash ? hash : versions[ 0 ].dataset.version );

	// The browser may still be settling on a hash target, so the first real
	// reading happens once that has finished.
	win.requestAnimationFrame( sync );
}

/* ---------------------------------------------------------------------- */
/* Collapsible versions                                                    */
/* ---------------------------------------------------------------------- */

/**
 * @param {HTMLElement} root  Block root.
 * @param {Object}      state Shared block state.
 */
function setUpCollapse( root, state ) {
	if ( '1' !== root.dataset.collapsible ) {
		return;
	}

	root.querySelectorAll( '.darkify-changelog__toggle' ).forEach( ( toggle ) => {
		const body = root.querySelector( `#${ CSS.escape( toggle.getAttribute( 'aria-controls' ) ) }` );

		if ( ! body ) {
			return;
		}

		toggle.addEventListener( 'click', () => {
			const expanded = 'true' === toggle.getAttribute( 'aria-expanded' );

			toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

			if ( expanded ) {
				// Height is set from the measured value first so the transition
				// has somewhere to animate from.
				body.style.height = `${ body.scrollHeight }px`;
				requestAnimationFrame( () => {
					body.style.height = '0px';
				} );
				body.addEventListener(
					'transitionend',
					() => {
						body.hidden = true;
						body.style.height = '';
						state.syncActive();
					},
					{ once: true }
				);
			} else {
				body.hidden = false;
				const target = body.scrollHeight;
				body.style.height = '0px';
				requestAnimationFrame( () => {
					body.style.height = `${ target }px`;
				} );
				body.addEventListener(
					'transitionend',
					() => {
						body.style.height = '';
						state.syncActive();
					},
					{ once: true }
				);
			}
		} );
	} );
}

/* ---------------------------------------------------------------------- */
/* Load more                                                               */
/* ---------------------------------------------------------------------- */

/**
 * Hide the tail of the list and hand the visitor a button for it.
 *
 * @param {HTMLElement} root     Block root.
 * @param {Array}       versions Version elements.
 * @param {Object}      state    Shared block state.
 * @return {?Object} Pagination controls, or null when there is no paging.
 */
function setUpLoadMore( root, versions, state ) {
	const wrapper = root.querySelector( '[data-changelog-more]' );

	if ( ! wrapper || 'load-more' !== root.dataset.pagination ) {
		return null;
	}

	const button = wrapper.querySelector( '.darkify-changelog__more-button' );
	const status = wrapper.querySelector( '.darkify-changelog__more-status' );
	const step = Math.max( 1, parseInt( root.dataset.perPage, 10 ) || 10 );
	let shown = step;

	const apply = () => {
		versions.forEach( ( version, index ) => {
			// A version the category filter closed stays closed; paging must
			// not bring it back.
			version.hidden = '1' === version.dataset.filtered || index >= shown;
		} );

		const done = shown >= versions.length;
		wrapper.hidden = done;

		if ( status ) {
			status.textContent = done
				? ''
				: `${ Math.min( shown, versions.length ) } / ${ versions.length }`;
		}

		state.syncActive();
	};

	wrapper.hidden = false;
	apply();

	button.addEventListener( 'click', () => {
		const from = shown;
		shown = Math.min( shown + step, versions.length );
		apply();

		// Reveal animation for the batch that just arrived, then focus its
		// first heading so keyboard users are not left where the button was.
		const first = versions[ from ];
		if ( first ) {
			reveal( versions.slice( from, shown ) );
			const heading = first.querySelector( '.darkify-changelog__version-number' );
			if ( heading ) {
				heading.setAttribute( 'tabindex', '-1' );
				heading.focus( { preventScroll: true } );
				heading.addEventListener( 'blur', () => heading.removeAttribute( 'tabindex' ), {
					once: true,
				} );
			}
		}
	} );

	return {
		showAll() {
			shown = versions.length;
			apply();
		},
		reset() {
			shown = step;
			apply();
		},
		revealThrough( index ) {
			shown = Math.max( shown, index + 1 );
			apply();
		},
		get paging() {
			return true;
		},
	};
}

/**
 * Reveal every version up to and including an index — used when a navigation
 * link points at something the load-more has not reached yet.
 *
 * @param {Array}  versions Version elements.
 * @param {number} index    Index to reveal through.
 */
function revealThrough( versions, index ) {
	for ( let i = 0; i <= index; i++ ) {
		versions[ i ].hidden = false;
	}
}

/* ---------------------------------------------------------------------- */
/* Category filter                                                         */
/* ---------------------------------------------------------------------- */

/**
 * Filter entries by category.
 *
 * Entries are hidden rather than removed, and a version with nothing left to
 * show is hidden with them — so "Removed" does not leave a page of empty cards.
 *
 * @param {HTMLElement} root       Block root.
 * @param {Array}       versions   Version elements.
 * @param {Array}       navLinks   Navigation links.
 * @param {?Object}     pagination Load-more controls, if any.
 * @param {Object}      state      Shared block state.
 */
function setUpFilters( root, versions, navLinks, pagination, state ) {
	const bar = root.querySelector( '[data-changelog-filters]' );

	if ( ! bar ) {
		return;
	}

	const chips = Array.from( bar.querySelectorAll( '[data-filter]' ) );
	const noResults = root.querySelector( '[data-changelog-no-results]' );

	const apply = ( category ) => {
		const all = 'all' === category;
		let matches = 0;

		// Flags first, pagination second. The other way round — which is how
		// this read at first — hands the pager the *previous* filter's flags,
		// so versions the last filter closed stay closed and versions this one
		// matches never reopen.
		versions.forEach( ( version ) => {
			const entries = Array.from(
				version.querySelectorAll( '.darkify-changelog__entry' )
			);
			let visible = 0;

			entries.forEach( ( entry ) => {
				const hit = all || entry.dataset.category === category;
				entry.hidden = ! hit;
				if ( hit ) {
					visible++;
				}
			} );

			// `data-filtered` marks a version this filter closed, so the pager
			// can tell it apart from one it simply has not reached yet.
			if ( all || visible ) {
				delete version.dataset.filtered;
			} else {
				version.dataset.filtered = '1';
			}

			if ( visible ) {
				matches++;
			}

			const link = navLinks.find(
				( l ) => l.dataset.versionLink === version.dataset.version
			);

			if ( link ) {
				link.hidden = ! all && ! visible;
			}
		} );

		if ( pagination ) {
			// Filtering looks at the whole changelog, not only the versions
			// loaded so far; clearing the filter goes back to one page.
			if ( all ) {
				pagination.reset();
			} else {
				pagination.showAll();
			}
		} else {
			versions.forEach( ( version ) => {
				version.hidden = '1' === version.dataset.filtered;
			} );
		}

		if ( noResults ) {
			noResults.hidden = matches > 0;
		}

		const more = root.querySelector( '[data-changelog-more]' );
		if ( more && ! all ) {
			more.hidden = true;
		}

		chips.forEach( ( chip ) => {
			const isActive = chip.dataset.filter === category;
			chip.classList.toggle( 'is-active', isActive );
			chip.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
		} );

		state.syncActive();
	};

	chips.forEach( ( chip ) => {
		chip.addEventListener( 'click', () => apply( chip.dataset.filter ) );
	} );
}

/* ---------------------------------------------------------------------- */
/* Search                                                                  */
/* ---------------------------------------------------------------------- */

/**
 * Client-side filtering across version numbers and entry text.
 *
 * @param {HTMLElement} root       Block root.
 * @param {Array}       versions   Version elements.
 * @param {Array}       navLinks   Navigation links.
 * @param {?Object}     pagination Load-more controls, if any.
 * @param {Object}      state      Shared block state.
 */
function setUpSearch( root, versions, navLinks, pagination, state ) {
	const wrapper = root.querySelector( '[data-changelog-search]' );

	if ( ! wrapper ) {
		return;
	}

	const input = wrapper.querySelector( 'input' );
	const noResults = root.querySelector( '[data-changelog-no-results]' );

	wrapper.hidden = false;

	const index = versions.map( ( version ) => ( {
		element: version,
		slug: version.dataset.version,
		haystack: (
			version.textContent || ''
		).toLowerCase(),
		entries: Array.from( version.querySelectorAll( '.darkify-changelog__entry' ) ),
	} ) );

	let timer = null;

	const run = ( raw ) => {
		const term = raw.trim().toLowerCase();

		if ( ! term ) {
			index.forEach( ( item ) => {
				item.element.hidden = false;
				item.entries.forEach( ( entry ) => ( entry.hidden = false ) );
			} );
			navLinks.forEach( ( link ) => ( link.hidden = false ) );
			if ( noResults ) {
				noResults.hidden = true;
			}
			if ( pagination ) {
				pagination.reset();
			}
			state.syncActive();
			return;
		}

		// Searching looks at the whole changelog, not just the pages a visitor
		// has loaded so far.
		if ( pagination ) {
			pagination.showAll();
		}

		let matches = 0;

		index.forEach( ( item ) => {
			const hit = item.haystack.includes( term );
			item.element.hidden = ! hit;

			if ( hit ) {
				matches++;
				item.entries.forEach( ( entry ) => {
					const text = ( entry.textContent || '' ).toLowerCase();
					// A version matched by its number keeps all of its entries;
					// otherwise only the matching rows stay.
					entry.hidden = ! (
						text.includes( term ) ||
						item.slug.replace( /-/g, '.' ).includes( term )
					);
				} );
			}

			const link = navLinks.find( ( l ) => l.dataset.versionLink === item.slug );
			if ( link ) {
				link.hidden = ! hit;
			}
		} );

		if ( noResults ) {
			noResults.hidden = matches > 0;
		}

		const more = root.querySelector( '[data-changelog-more]' );
		if ( more ) {
			more.hidden = true;
		}

		state.syncActive();
	};

	input.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( () => run( input.value ), 120 );
	} );
}

/* ---------------------------------------------------------------------- */
/* Reveal animation                                                        */
/* ---------------------------------------------------------------------- */

/**
 * @param {Array} elements Version elements to animate in.
 */
function reveal( elements ) {
	if ( REDUCED_MOTION.matches ) {
		return;
	}

	elements.forEach( ( element, index ) => {
		element.classList.add( 'is-revealing' );
		window.setTimeout( () => {
			element.classList.add( 'is-revealed' );
			element.addEventListener(
				'transitionend',
				() => element.classList.remove( 'is-revealing', 'is-revealed' ),
				{ once: true }
			);
		}, index * 60 );
	} );
}

/**
 * Fade and rise each version as it first scrolls into view.
 *
 * @param {Array}  versions Version elements.
 * @param {Object} state    Shared block state.
 */
function setUpReveal( versions, state ) {
	if ( REDUCED_MOTION.matches || typeof window.IntersectionObserver !== 'function' ) {
		return;
	}

	const observer = new IntersectionObserver(
		( entries, self ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) {
					return;
				}

				// A version passed on the way to somewhere else is simply
				// there when the visitor arrives: fading it in mid-flight is
				// the flicker that made a long jump look like a stutter.
				if ( ! state.scrolling ) {
					reveal( [ entry.target ] );
				}

				self.unobserve( entry.target );
			} );
		},
		{ rootMargin: '0px 0px -10% 0px', threshold: 0.05 }
	);

	versions.forEach( ( version ) => observer.observe( version ) );
}

/* ---------------------------------------------------------------------- */

/**
 * Enhance every changelog on the page.
 */
function init() {
	document.querySelectorAll( '[data-darkify-changelog]' ).forEach( setUpBlock );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
