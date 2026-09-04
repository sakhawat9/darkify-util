/**
 * Front-end behaviour for darkify-util/social-share.
 *
 * Everything here is an upgrade to markup that already works. The share links
 * are real links to real endpoints, so with this file blocked, missing or still
 * loading, every button still shares — it opens a tab instead of a popup, and
 * Instagram lands on instagram.com without the URL on the clipboard.
 *
 * Two jobs:
 *
 * 1. Open share endpoints in a popup rather than a tab. It is what every
 *    composer expects: a small window that closes itself on publish and leaves
 *    the reader on the article. `mailto:` is left alone — a popup for a link
 *    that hands off to a mail client is an empty window nobody closes.
 *
 * 2. Instagram, which has no share endpoint at all. Its button copies the post
 *    URL and says so, because pasting is the only way to share a link there.
 */

const POPUP_WIDTH = 600;
const POPUP_HEIGHT = 560;

/**
 * How long the "Copied" state stays up. Long enough to read, short enough that
 * the button is back to itself before anyone tries it again.
 */
const COPIED_FOR = 2000;

/**
 * Put text on the clipboard.
 *
 * The async clipboard API needs a secure context, which a site on plain HTTP is
 * not, so there is a fallback: a hidden textarea and execCommand, deprecated but
 * still the only thing that works there.
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether it worked.
 */
async function copyText( text ) {
	if ( window.navigator?.clipboard?.writeText ) {
		try {
			await window.navigator.clipboard.writeText( text );
			return true;
		} catch {
			// Denied or unavailable; fall through to the older path.
		}
	}

	const field = document.createElement( 'textarea' );

	field.value = text;
	field.setAttribute( 'readonly', '' );
	// Off-screen rather than hidden: a display:none field cannot be selected.
	field.style.cssText = 'position:fixed;top:-9999px;opacity:0';

	document.body.appendChild( field );
	field.select();

	let copied = false;

	try {
		copied = document.execCommand( 'copy' );
	} catch {
		copied = false;
	}

	field.remove();

	return copied;
}

/**
 * Flash the "Copied" state on a button.
 *
 * The class drives the visual; the live region is what a screen reader hears,
 * since a colour change announces nothing.
 *
 * @param {HTMLElement} link The share link.
 */
function confirmCopy( link ) {
	if ( link.timer ) {
		window.clearTimeout( link.timer );
	}

	link.classList.add( 'is-copied' );

	const status = link.closest( '[data-darkify-share]' )?.querySelector(
		'[data-darkify-share-status]'
	);

	if ( status ) {
		status.textContent = link.dataset.darkifyShareCopied || 'Link copied';
	}

	link.timer = window.setTimeout( () => {
		link.classList.remove( 'is-copied' );

		if ( status ) {
			status.textContent = '';
		}
	}, COPIED_FOR );
}

/**
 * @param {MouseEvent} event Click on a share link.
 */
async function onClick( event ) {
	const link = event.target.closest( '.darkify-share__link' );

	if ( ! link || event.metaKey || event.ctrlKey || event.shiftKey || 1 === event.button ) {
		// A modified click is the reader asking for a tab or a window of their
		// own; that is theirs to decide, not this file's.
		return;
	}

	const copy = link.dataset.darkifyShareCopy;

	if ( copy ) {
		event.preventDefault();

		const copied = await copyText( copy );

		if ( copied ) {
			confirmCopy( link );
		}

		// Instagram opens either way: with the URL copied it is a paste away,
		// and if the clipboard was refused the reader is at least where they
		// were trying to go.
		window.open( link.href, '_blank', 'noopener' );

		return;
	}

	if ( link.href.startsWith( 'mailto:' ) ) {
		return;
	}

	const left = Math.round( window.screenX + ( window.outerWidth - POPUP_WIDTH ) / 2 );
	const top = Math.round( window.screenY + ( window.outerHeight - POPUP_HEIGHT ) / 2.5 );

	const popup = window.open(
		link.href,
		'darkify-share',
		`popup=1,width=${ POPUP_WIDTH },height=${ POPUP_HEIGHT },left=${ left },top=${ top },noopener`
	);

	// A blocked popup means the ordinary link behaviour has to stand, so the
	// default is only prevented once there is a window to show for it.
	if ( popup ) {
		event.preventDefault();
		popup.focus();
	}
}

/**
 * @param {HTMLElement} root A block wrapper.
 */
export function setUpBlock( root ) {
	if ( root.dataset.darkifyShareReady ) {
		return;
	}

	root.dataset.darkifyShareReady = '1';
	root.addEventListener( 'click', onClick );
}

function setUp() {
	document.querySelectorAll( '[data-darkify-share]' ).forEach( setUpBlock );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', setUp );
} else {
	setUp();
}
