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

	setUpNavigation( root, versions, navLinks );
	setUpCollapse( root );
	const pagination = setUpLoadMore( root, versions );
	setUpSearch( root, versions, navLinks, pagination );
	setUpFilters( root, versions, navLinks, pagination );
	setUpReveal( versions );
}

/* ---------------------------------------------------------------------- */
/* Version navigation                                                      */
/* ---------------------------------------------------------------------- */

/**
 * Smooth-scroll to a version and keep the list's active item in step.
 *
 * @param {HTMLElement} root     Block root.
 * @param {Array}       versions Version elements.
 * @param {Array}       navLinks Navigation links.
 */
function setUpNavigation( root, versions, navLinks ) {
	if ( ! navLinks.length ) {
		return;
	}

	const linkFor = ( slug ) =>
		navLinks.find( ( link ) => link.dataset.versionLink === slug );

	navLinks.forEach( ( link ) => {
		link.addEventListener( 'click', ( event ) => {
			const target = root.querySelector(
				`[data-version="${ CSS.escape( link.dataset.versionLink ) }"]`
			);

			if ( ! target ) {
				return;
			}

			event.preventDefault();

			// A hidden version (beyond the current load-more page, or filtered
			// out by a search) is revealed first, so the link never scrolls to
			// nothing.
			if ( target.hidden ) {
				revealThrough( versions, versions.indexOf( target ) );
			}

			target.scrollIntoView( {
				behavior: REDUCED_MOTION.matches ? 'auto' : 'smooth',
				block: 'start',
			} );

			// Moves keyboard focus with the scroll; tabindex is removed again
			// so the heading does not become a permanent tab stop.
			const heading = target.querySelector( '.darkify-changelog__version-number' );
			if ( heading ) {
				heading.setAttribute( 'tabindex', '-1' );
				heading.focus( { preventScroll: true } );
				heading.addEventListener( 'blur', () => heading.removeAttribute( 'tabindex' ), {
					once: true,
				} );
			}

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', `#${ link.dataset.versionLink }` );
			}
		} );
	} );

	const setActive = ( slug ) => {
		navLinks.forEach( ( link ) => {
			const isActive = link.dataset.versionLink === slug;
			link.classList.toggle( 'is-active', isActive );
			if ( isActive ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	};

	if ( typeof window.IntersectionObserver !== 'function' ) {
		return;
	}

	const observer = new IntersectionObserver(
		( entries ) => {
			const visible = entries
				.filter( ( entry ) => entry.isIntersecting )
				.sort( ( a, b ) => a.boundingClientRect.top - b.boundingClientRect.top );

			if ( visible.length ) {
				setActive( visible[ 0 ].target.dataset.version );
			}
		},
		{ rootMargin: '-20% 0px -70% 0px', threshold: 0 }
	);

	versions.forEach( ( version ) => observer.observe( version ) );

	const initial = linkFor( window.location.hash.replace( '#', '' ) );
	setActive( initial ? initial.dataset.versionLink : versions[ 0 ].dataset.version );
}

/* ---------------------------------------------------------------------- */
/* Collapsible versions                                                    */
/* ---------------------------------------------------------------------- */

/**
 * @param {HTMLElement} root Block root.
 */
function setUpCollapse( root ) {
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
 * @return {?Object} Pagination controls, or null when there is no paging.
 */
function setUpLoadMore( root, versions ) {
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
 */
function setUpFilters( root, versions, navLinks, pagination ) {
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
 */
function setUpSearch( root, versions, navLinks, pagination ) {
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
 * @param {Array} versions Version elements.
 */
function setUpReveal( versions ) {
	if ( REDUCED_MOTION.matches || typeof window.IntersectionObserver !== 'function' ) {
		return;
	}

	const observer = new IntersectionObserver(
		( entries, self ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					reveal( [ entry.target ] );
					self.unobserve( entry.target );
				}
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
