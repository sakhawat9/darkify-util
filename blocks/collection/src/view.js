/**
 * darkify-util/collection — front-end behaviour.
 *
 * A true ES module (block.json declares it as `viewScriptModule`), vanilla, no
 * dependencies. Everything here is enhancement: PHP has already rendered the
 * filter bar, the search form, one page of cards and whichever pager is switched
 * on, and every one of those controls is a real link or a real form pointed at
 * query arguments the server reads back.
 *
 * So this module never *implements* filtering, search or paging — it intercepts
 * the click, asks the server for the same HTML the link would have produced, and
 * swaps it in. One definition of "which items match" lives in PHP; there is no
 * second copy here to drift out of step with it. And when a request fails — an
 * expired nonce on a cached page is the realistic case — it stops intercepting
 * and lets the browser follow the link it was always pointing at.
 */

const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' );

/** Query arguments, matching Darkify_Util_Collection::ARG_*. */
const ARGS = {
	id: 'dkc-id',
	category: 'dkc-cat',
	search: 'dkc-s',
	page: 'dkc-page',
};

/**
 * Enhance one rendered collection.
 *
 * Exported because the editor runs it over the same server-rendered markup, so
 * the preview filters, searches and pages exactly like the published page. The
 * editor passes its live attributes in `context`, since the block it is
 * previewing may never have been saved and so cannot be read back off a post.
 *
 * @param {HTMLElement} root    Block root.
 * @param {Object}      context Optional { attributes } for the editor.
 */
export function setUpBlock( root, context = {} ) {
	if ( root.dataset.collectionReady ) {
		return;
	}
	root.dataset.collectionReady = '1';

	const grid = root.querySelector( '[data-collection-grid]' );

	if ( ! grid ) {
		return;
	}

	// Read fresh on every request rather than captured here: in the editor the
	// attributes go on changing after this runs, and a request that sends the
	// ones from mount would preview a block nobody is editing any more.
	const editorAttributes = () => context.attributes || null;
	const inEditor = Boolean( editorAttributes() );

	// Without a way to identify the collection server-side there is nothing to
	// ask for, so the links are left to do their job unaided.
	if ( ! inEditor && ! root.dataset.blockId && ! root.dataset.postId ) {
		return;
	}

	const results = root.querySelector( '[data-collection-results]' );
	const emptyNote = root.querySelector( '[data-collection-empty]' );
	const statusNote = root.querySelector( '[data-collection-status]' );
	const filterBar = root.querySelector( '[data-collection-filters]' );
	const searchForm = root.querySelector( '[data-collection-search]' );
	const moreWrapper = root.querySelector( '[data-collection-more]' );
	const pagerHolder = root.querySelector( '[data-collection-pagination]' );

	const state = {
		category: root.dataset.category || 'all',
		search: root.dataset.search || '',
		page: parseInt( root.dataset.page, 10 ) || 1,
	};

	let controller = null;

	/* ------------------------------------------------------------------ */
	/* Talking to the server                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Fetch a view of the collection and put it on the page.
	 *
	 * @param {Object}  next          The state being moved to.
	 * @param {Object}  options       Behaviour.
	 * @param {boolean} options.append Add the batch rather than replace the grid.
	 * @param {string}  options.href   Where to send the browser if the request fails.
	 * @param {boolean} options.scroll Bring the grid back into view when it lands.
	 * @return {Promise} Resolves once the swap is done.
	 */
	const load = ( next, options = {} ) => {
		// A visitor clicking through chips faster than the network answers must
		// not have an earlier response land on top of a later one.
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();

		setBusy( true );

		const body = new URLSearchParams();
		body.set( 'action', 'darkify_collection_query' );
		body.set( 'nonce', root.dataset.nonce || '' );
		body.set( 'blockId', root.dataset.blockId || '' );
		body.set( 'postId', root.dataset.postId || '0' );
		body.set( 'category', next.category );
		body.set( 'search', next.search );
		body.set( 'page', String( next.page ) );

		if ( options.append ) {
			body.set( 'append', '1' );
		}

		if ( editorAttributes() ) {
			body.set( 'attributes', JSON.stringify( editorAttributes() ) );
		}

		return window
			.fetch( root.dataset.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
				signal: controller.signal,
			} )
			.then( ( response ) => response.json() )
			.then( ( payload ) => {
				if ( ! payload || ! payload.success ) {
					throw new Error( 'request-failed' );
				}

				apply( next, payload, options );
			} )
			.catch( ( error ) => {
				if ( 'AbortError' === error.name ) {
					return;
				}

				setBusy( false );

				// The controls were links all along. An expired nonce on a
				// cached page ends up here, and the visitor gets their results
				// from a page load instead of an error.
				if ( options.href ) {
					window.location.assign( options.href );
				}
			} );
	};

	/**
	 * @param {Object} next    New state.
	 * @param {Object} payload Server response data.
	 * @param {Object} options Behaviour, as passed to load().
	 */
	const apply = ( next, payload, options ) => {
		const data = payload.data;
		const arrived = [];

		if ( options.append ) {
			const marker = grid.children.length;
			grid.insertAdjacentHTML( 'beforeend', data.items );

			for ( let i = marker; i < grid.children.length; i++ ) {
				arrived.push( grid.children[ i ] );
			}
		} else {
			grid.innerHTML = data.items;
			arrived.push( ...Array.from( grid.children ) );
		}

		if ( emptyNote ) {
			emptyNote.hidden = ! data.empty;
		}

		if ( pagerHolder ) {
			pagerHolder.innerHTML = data.pagination;
		}

		if ( moreWrapper ) {
			// The button takes itself away when the collection runs out, and
			// comes back if a wider filter puts more behind it.
			moreWrapper.hidden = ! data.hasMore;

			const button = moreWrapper.querySelector( '[data-collection-more-button]' );

			if ( button ) {
				button.href = urlFor( { ...next, page: next.page + 1 } );
			}
		}

		if ( statusNote ) {
			statusNote.textContent = data.status;
		}

		syncFilters( next.category );

		state.category = next.category;
		state.search = next.search;
		state.page = data.page;

		root.dataset.category = state.category;
		root.dataset.search = state.search;
		root.dataset.page = String( state.page );
		root.dataset.totalPages = String( data.totalPages );

		// The URL keeps up with the grid, so a filtered page can be shared,
		// bookmarked or reloaded and come back the same. Never in the editor,
		// where the address bar belongs to wp-admin.
		if ( ! inEditor && window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', urlFor( state ) );
		}

		setBusy( false );
		reveal( arrived );

		if ( options.append ) {
			focusFirst( arrived );
		} else if ( options.scroll ) {
			bringIntoView();
		}
	};

	/**
	 * @param {boolean} busy Whether a request is in flight.
	 */
	const setBusy = ( busy ) => {
		root.classList.toggle( 'is-loading', busy );

		if ( results ) {
			results.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}
	};

	/**
	 * A URL carrying a view state — what the intercepted links would have gone
	 * to, rebuilt so the address bar and the Load More button stay honest.
	 *
	 * @param {Object} view State.
	 * @return {string} URL.
	 */
	const urlFor = ( view ) => {
		const url = new URL( window.location.href );

		url.searchParams.delete( ARGS.id );
		url.searchParams.delete( ARGS.category );
		url.searchParams.delete( ARGS.search );
		url.searchParams.delete( ARGS.page );

		const blockId = root.dataset.blockId;

		if ( blockId && ( 'all' !== view.category || view.search || view.page > 1 ) ) {
			url.searchParams.set( ARGS.id, blockId );

			if ( 'all' !== view.category ) {
				url.searchParams.set( ARGS.category, view.category );
			}

			if ( view.search ) {
				url.searchParams.set( ARGS.search, view.search );
			}

			if ( view.page > 1 ) {
				url.searchParams.set( ARGS.page, String( view.page ) );
			}
		}

		url.hash = root.id;

		return url.toString();
	};

	/**
	 * @param {string} category Active category slug.
	 */
	const syncFilters = ( category ) => {
		if ( ! filterBar ) {
			return;
		}

		filterBar.querySelectorAll( '[data-filter]' ).forEach( ( chip ) => {
			const isActive = chip.dataset.filter === category;

			chip.classList.toggle( 'is-active', isActive );

			if ( isActive ) {
				chip.setAttribute( 'aria-current', 'true' );
			} else {
				chip.removeAttribute( 'aria-current' );
			}
		} );
	};

	/**
	 * Move keyboard focus to the first card of a batch, so someone who pressed
	 * Load More is not left behind at a button that has moved down the page.
	 *
	 * @param {Array} cards Newly added cards.
	 */
	const focusFirst = ( cards ) => {
		const target = cards[ 0 ];

		if ( ! target ) {
			return;
		}

		const link = target.querySelector( '.darkify-collection__title-link' );
		const focusable = link || target;

		if ( ! link ) {
			focusable.setAttribute( 'tabindex', '-1' );
			focusable.addEventListener( 'blur', () => focusable.removeAttribute( 'tabindex' ), {
				once: true,
			} );
		}

		focusable.focus( { preventScroll: true } );
	};

	/**
	 * After a page change, put the top of the grid back on screen — but only if
	 * it has scrolled off, so filtering from the top of the page does not jump.
	 */
	const bringIntoView = () => {
		const box = root.getBoundingClientRect();

		if ( box.top >= 0 ) {
			return;
		}

		root.scrollIntoView( {
			behavior: REDUCED_MOTION.matches ? 'auto' : 'smooth',
			block: 'start',
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Controls                                                           */
	/* ------------------------------------------------------------------ */

	if ( filterBar ) {
		filterBar.addEventListener( 'click', ( event ) => {
			const chip = event.target.closest( '[data-filter]' );

			if ( ! chip || ! filterBar.contains( chip ) ) {
				return;
			}

			event.preventDefault();

			// Changing the filter goes back to page one: staying on page four of
			// a category that only has two is how you get an empty grid with no
			// explanation.
			load(
				{ category: chip.dataset.filter, search: state.search, page: 1 },
				{ href: chip.href }
			);
		} );
	}

	if ( searchForm ) {
		const input = searchForm.querySelector( 'input[type="search"]' );
		let timer = null;

		const run = () => {
			window.clearTimeout( timer );
			load(
				{ category: state.category, search: input.value.trim(), page: 1 },
				{ href: searchForm.action }
			);
		};

		input.addEventListener( 'input', () => {
			window.clearTimeout( timer );
			// Long enough that a request is not fired per keystroke, short
			// enough that the grid still feels like it is answering as you type.
			timer = window.setTimeout( run, 300 );
		} );

		searchForm.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			run();
		} );
	}

	if ( moreWrapper ) {
		moreWrapper.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-collection-more-button]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			load(
				{ category: state.category, search: state.search, page: state.page + 1 },
				{ append: true, href: button.href }
			);
		} );
	}

	if ( pagerHolder ) {
		// Delegated, because the pagination markup is replaced on every request
		// and listeners bound to the old numbers would go with it.
		pagerHolder.addEventListener( 'click', ( event ) => {
			const link = event.target.closest( '[data-page]' );

			if ( ! link || ! pagerHolder.contains( link ) ) {
				return;
			}

			event.preventDefault();

			load(
				{
					category: state.category,
					search: state.search,
					page: parseInt( link.dataset.page, 10 ) || 1,
				},
				{ href: link.href, scroll: true }
			);
		} );
	}

	setUpReveal( Array.from( grid.children ) );
}

/* ---------------------------------------------------------------------- */
/* Reveal animation                                                        */
/* ---------------------------------------------------------------------- */

/**
 * @param {Array} elements Cards to animate in.
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
			// Capped so a page of forty cards does not take two seconds to
			// finish arriving.
		}, Math.min( index, 8 ) * 45 );
	} );
}

/**
 * Fade and rise each card as it first scrolls into view.
 *
 * @param {Array} cards Cards.
 */
function setUpReveal( cards ) {
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

	cards.forEach( ( card ) => observer.observe( card ) );
}

/* ---------------------------------------------------------------------- */

/**
 * Enhance every collection on the page.
 */
function init() {
	document.querySelectorAll( '[data-darkify-collection]' ).forEach( ( root ) => setUpBlock( root ) );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
