/**
 * Front-end behaviour for darkify-util/promo-banner.
 *
 * The server renders the banner already in the right state, with the countdown
 * reading what it read at render time. This file keeps it true from then on:
 *
 * 1. Ticks the countdown, and swaps to the urgent message, then the ended one
 *    (or hides the banner), the moment each threshold is crossed — including on
 *    a page that was served from a cache hours ago, since everything is worked
 *    out again from the deadline rather than from what the HTML says.
 *
 * 2. Rolls a recurring deadline on to its next cycle when it passes.
 *
 * 3. Rewrites the {date} and {time} tokens in the visitor's own locale and
 *    timezone, with the zone named, so "ends 6:00 PM" is never an hour out for
 *    someone in a different country.
 *
 * 4. Dismissal. Remembered per deadline, so a new campaign — or a recurring
 *    banner's next cycle — shows again to someone who closed the last one.
 */

import {
	DAY_MS,
	countdownUnits,
	humanRemaining,
	pad,
	remainingParts,
	resolveDeadline,
	stateFor,
} from './deadline';

const TICK = 1000;

/**
 * @param {string|undefined} value
 * @return {number} The number, or 0.
 */
function toNumber( value ) {
	const number = Number( value );

	return Number.isFinite( number ) ? number : 0;
}

/**
 * The page's language, so tokens read in it rather than in the browser's.
 *
 * @return {string|undefined} BCP 47 tag.
 */
function pageLocale() {
	return document.documentElement.lang || undefined;
}

/**
 * @param {string} token    'date' or 'time'.
 * @param {number} deadline
 * @return {string|null} Formatted text, or null to leave the server's.
 */
function formatToken( token, deadline ) {
	const date = new Date( deadline );

	try {
		if ( 'date' === token ) {
			const options = { month: 'short', day: 'numeric' };

			if ( date.getFullYear() !== new Date().getFullYear() ) {
				options.year = 'numeric';
			}

			return new Intl.DateTimeFormat( pageLocale(), options ).format( date );
		}

		if ( 'time' === token ) {
			return new Intl.DateTimeFormat( pageLocale(), {
				hour: 'numeric',
				minute: '2-digit',
				timeZoneName: 'short',
			} ).format( date );
		}
	} catch {
		// An unsupported locale tag; the server's rendering stands.
	}

	return null;
}

/**
 * @param {HTMLElement} element
 * @param {string}      text
 */
function setText( element, text ) {
	// Only on change: rewriting identical text every second still costs a
	// layout, and would keep a screen reader's virtual cursor jumping.
	if ( null !== text && element.textContent !== text ) {
		element.textContent = text;
	}
}

/**
 * @param {HTMLElement} root A block wrapper.
 */
export function setUpBlock( root ) {
	if ( root.dataset.darkifyPromoReady ) {
		return;
	}

	root.dataset.darkifyPromoReady = '1';

	const interval = toNumber( root.dataset.interval );
	const urgentWithin = toNumber( root.dataset.urgent );
	const dismissDays = toNumber( root.dataset.dismissDays );
	const hideWhenExpired = 'hide' === root.dataset.expired;
	const fixed = root.classList.contains( 'is-position-fixed-bottom' );

	let deadline = toNumber( root.dataset.deadline );
	let state = root.dataset.state || 'active';
	let formattedFor = 0;
	let timer = 0;

	const values = {};

	root.querySelectorAll( '[data-darkify-promo-unit]' ).forEach( ( element ) => {
		values[ element.dataset.darkifyPromoUnit ] = element;
	} );

	const messages = root.querySelectorAll( '[data-darkify-promo-message]' );
	const tokens = root.querySelectorAll( '[data-darkify-promo-token]' );

	const storageKey = () => `${ root.dataset.key }:${ deadline }`;

	function isDismissed() {
		try {
			return Number( window.localStorage.getItem( storageKey() ) ) > Date.now();
		} catch {
			return false;
		}
	}

	/*
	 * A fixed bar sits over the bottom of the page, footer included. Padding
	 * the body by its height gives that space back, and follows the bar when a
	 * narrow screen wraps it onto more lines.
	 */
	function syncOffset() {
		if ( fixed ) {
			document.body.style.paddingBottom = root.hidden ? '' : `${ root.offsetHeight }px`;
		}
	}

	function hide() {
		root.hidden = true;
		window.clearInterval( timer );
		syncOffset();
	}

	function setState( next ) {
		if ( next === state ) {
			return;
		}

		root.classList.replace( `is-state-${ state }`, `is-state-${ next }` );
		root.dataset.state = next;

		messages.forEach( ( message ) => {
			message.hidden = message.dataset.darkifyPromoMessage !== next;
		} );

		state = next;
	}

	function tick() {
		const now = Date.now();
		const next = resolveDeadline( deadline, interval, now );

		if ( next !== deadline ) {
			deadline = next;

			if ( isDismissed() ) {
				hide();
				return;
			}
		}

		if ( ! deadline ) {
			return;
		}

		const nextState = stateFor( deadline, now, urgentWithin );

		if ( 'expired' === nextState && hideWhenExpired ) {
			hide();
			return;
		}

		setState( nextState );

		const parts = remainingParts( deadline, now );

		countdownUnits( parts, Boolean( values.days ) ).forEach( ( unit ) => {
			if ( values[ unit.key ] ) {
				setText( values[ unit.key ], pad( unit.value ) );
			}
		} );

		tokens.forEach( ( element ) => {
			const token = element.dataset.darkifyPromoToken;

			if ( 'remaining' === token ) {
				setText( element, humanRemaining( parts.total, pageLocale() ) );
			} else if ( formattedFor !== deadline ) {
				setText( element, formatToken( token, deadline ) );
			}
		} );

		formattedFor = deadline;

		if ( 'expired' === nextState ) {
			window.clearInterval( timer );
		}
	}

	if ( isDismissed() ) {
		hide();
		return;
	}

	root.querySelector( '[data-darkify-promo-dismiss]' )?.addEventListener( 'click', () => {
		if ( dismissDays > 0 ) {
			try {
				window.localStorage.setItem(
					storageKey(),
					String( Date.now() + dismissDays * DAY_MS )
				);
			} catch {
				// Private mode or storage full: it still closes, just not for good.
			}
		}

		hide();
	} );

	if ( fixed && 'ResizeObserver' in window ) {
		new window.ResizeObserver( syncOffset ).observe( root );
	}

	tick();

	if ( deadline && ! root.hidden && 'expired' !== state ) {
		timer = window.setInterval( tick, TICK );

		// Background tabs throttle timers to a crawl; catch up on return
		// rather than showing a stale second for up to a minute.
		document.addEventListener( 'visibilitychange', () => {
			if ( ! document.hidden && ! root.hidden ) {
				tick();
			}
		} );
	}

	syncOffset();
}

function setUp() {
	document.querySelectorAll( '[data-darkify-promo]' ).forEach( setUpBlock );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', setUp );
} else {
	setUp();
}
