/**
 * Description HTML helpers.
 *
 * The description editor writes HTML, and HTML typed or pasted into a
 * contenteditable picks up whatever the browser or the source page felt like
 * adding: inline styles, spans, Word's and Google Docs' markup, block comments.
 * Everything goes through cleanHtml() on its way into the attribute, so what is
 * stored is the structure — paragraphs, headings, lists, links, tables — and not
 * the source page's styling, which would fight the card's own (and Darkify's
 * dark palette). PHP runs wp_kses_post() over it again on render; this is about
 * fidelity and tidiness, not security.
 */

import { autop } from '@wordpress/autop';

const BLOCK_TAGS = [
	'p',
	'h1',
	'h2',
	'h3',
	'h4',
	'h5',
	'h6',
	'ul',
	'ol',
	'li',
	'blockquote',
	'pre',
	'table',
	'thead',
	'tbody',
	'tfoot',
	'tr',
	'th',
	'td',
	'caption',
	'hr',
];

const INLINE_TAGS = [ 'strong', 'em', 'u', 's', 'del', 'code', 'sub', 'sup', 'mark', 'a', 'br', 'img' ];

const ALLOWED = [ ...BLOCK_TAGS, ...INLINE_TAGS ];

const ATTRIBUTES = {
	a: [ 'href', 'target', 'rel' ],
	img: [ 'src', 'alt', 'width', 'height', 'class' ],
	th: [ 'colspan', 'rowspan', 'scope' ],
	td: [ 'colspan', 'rowspan' ],
};

const RENAME = { b: 'strong', i: 'em', strike: 's', figcaption: 'p' };

const DROP = [ 'script', 'style', 'iframe', 'object', 'embed', 'meta', 'link', 'title', 'noscript', 'template', 'svg', 'head', 'button', 'input', 'select', 'textarea' ];

/** Containers whose whitespace-only text is source formatting, not content. */
const STRUCTURAL_PARENTS = /^(body|ul|ol|table|thead|tbody|tfoot|tr|blockquote)$/i;

/** Markup that shows a plain-text paste is really HTML source. */
const HTML_SOURCE = /<\/?(p|br|ul|ol|li|h[1-6]|strong|b|em|i|u|a|blockquote|div|span|table|tr|td|img|hr|pre|code)\b[^>]*>/i;

/** Marks where the selection was while the editor's HTML is rebuilt. */
export const CARET = 'data-dkc-caret';

/**
 * @param {string} value Text.
 * @return {string} Escaped for HTML.
 */
export function escapeHtml( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

/**
 * @param {string} html HTML.
 * @return {HTMLElement} Parsed body, in an inert document.
 */
function parse( html ) {
	return new window.DOMParser().parseFromString( `<body>${ html }</body>`, 'text/html' ).body;
}

/**
 * @param {Element} element Element to replace by its children.
 */
function unwrap( element ) {
	element.replaceWith( ...Array.from( element.childNodes ) );
}

/**
 * The formatting a styled span stands for.
 *
 * Google Docs and Word mark bold and italic with inline styles on spans rather
 * than with tags, so a paste that only kept tags would lose all of it.
 *
 * @param {Element} element Element about to be unwrapped.
 * @return {Array} Tags to wrap its content in.
 */
function styleTags( element ) {
	const style = element.style;

	if ( ! style ) {
		return [];
	}

	const tags = [];
	const weight = style.fontWeight;
	const decoration = `${ style.textDecorationLine || '' } ${ style.textDecoration || '' }`;

	if ( 'bold' === weight || 'bolder' === weight || parseInt( weight, 10 ) >= 600 ) {
		tags.push( 'strong' );
	}
	if ( 'italic' === style.fontStyle ) {
		tags.push( 'em' );
	}
	if ( decoration.includes( 'underline' ) ) {
		tags.push( 'u' );
	}
	if ( decoration.includes( 'line-through' ) ) {
		tags.push( 's' );
	}
	if ( 'sub' === style.verticalAlign ) {
		tags.push( 'sub' );
	} else if ( 'super' === style.verticalAlign ) {
		tags.push( 'sup' );
	}

	return tags;
}

/**
 * @param {Element} element Block element.
 * @return {string} Alignment worth keeping, or ''.
 */
function alignmentOf( element ) {
	const fromStyle = element.style ? element.style.textAlign : '';
	const fromClass = ( element.getAttribute( 'class' ) || '' ).match( /has-text-align-(center|right|justify)/ );
	const align = fromStyle || ( fromClass ? fromClass[ 1 ] : '' );

	return [ 'center', 'right', 'justify' ].includes( align ) ? align : '';
}

/**
 * @param {Node}    parent  Node whose children are cleaned, deepest first.
 * @param {Object}  options cleanHtml() options.
 * @param {boolean} inPre   Inside preformatted text, where newlines are content.
 */
function cleanChildren( parent, options, inPre = false ) {
	Array.from( parent.childNodes ).forEach( ( child ) => {
		if ( 3 === child.nodeType ) {
			if ( inPre ) {
				return;
			}

			// A newline in HTML source is a space on screen. Left alone, it
			// would become a <br> the moment wpautop() saw it.
			if ( ! child.nodeValue.trim() && STRUCTURAL_PARENTS.test( parent.nodeName ) ) {
				child.remove();
			} else {
				child.nodeValue = child.nodeValue.replace( /[\r\n]+/g, ' ' );
			}
			return;
		}

		if ( 1 !== child.nodeType ) {
			child.remove();
			return;
		}

		let tag = child.tagName.toLowerCase();

		if ( 'span' === tag && child.hasAttribute( CARET ) ) {
			return;
		}

		if ( DROP.includes( tag ) ) {
			child.remove();
			return;
		}

		// Google Docs wraps a whole paste in <b style="font-weight:normal">.
		if ( 'b' === tag && /^(normal|[1-5]00)$/.test( child.style.fontWeight ) ) {
			tag = 'span';
		}

		const align = alignmentOf( child );
		const wrappers =
			options.paste && ! ALLOWED.includes( RENAME[ tag ] || tag ) && ! [ 'div', 'figure' ].includes( tag )
				? styleTags( child )
				: [];

		// Lazy loaders park the real address in a data attribute.
		if ( 'img' === tag ) {
			const src = child.getAttribute( 'src' ) || '';
			const lazy = child.getAttribute( 'data-src' ) || child.getAttribute( 'data-lazy-src' ) || '';

			if ( lazy && ( ! src || /^data:/i.test( src ) ) ) {
				child.setAttribute( 'src', lazy );
			}
		}

		cleanChildren( child, options, inPre || 'pre' === tag );

		if ( 'figure' === tag ) {
			unwrap( child );
			return;
		}

		// A div holding blocks is only a wrapper; one holding text is a line.
		if ( 'div' === tag ) {
			if ( child.querySelector( BLOCK_TAGS.join( ',' ) ) ) {
				unwrap( child );
				return;
			}
			tag = 'p';
		}

		if ( RENAME[ tag ] || tag !== child.tagName.toLowerCase() ) {
			const renamed = child.ownerDocument.createElement( RENAME[ tag ] || tag );
			while ( child.firstChild ) {
				renamed.appendChild( child.firstChild );
			}
			child.replaceWith( renamed );
			child = renamed;
			tag = renamed.tagName.toLowerCase();
		}

		if ( ! ALLOWED.includes( tag ) ) {
			if ( wrappers.length && child.textContent.trim() ) {
				let wrapped = child.ownerDocument.createDocumentFragment();
				while ( child.firstChild ) {
					wrapped.appendChild( child.firstChild );
				}
				wrappers.forEach( ( name ) => {
					const element = child.ownerDocument.createElement( name );
					element.appendChild( wrapped );
					wrapped = element;
				} );
				child.replaceWith( wrapped );
			} else {
				unwrap( child );
			}
			return;
		}

		Array.from( child.attributes ).forEach( ( attribute ) => {
			if ( ! ( ATTRIBUTES[ tag ] || [] ).includes( attribute.name ) ) {
				child.removeAttribute( attribute.name );
			}
		} );

		[ 'href', 'src' ].forEach( ( name ) => {
			const url = child.getAttribute( name );
			if ( url && /^\s*(javascript|vbscript|data):/i.test( url ) ) {
				child.removeAttribute( name );
			}
		} );

		if ( 'img' === tag && ! child.getAttribute( 'src' ) ) {
			child.remove();
			return;
		}

		if ( align && /^(p|h[1-6])$/.test( tag ) ) {
			child.setAttribute( 'style', `text-align:${ align }` );
		}

		// A paragraph cannot hold blocks; parsing a paste can leave one that does.
		if ( /^(p|h[1-6])$/.test( tag ) && child.querySelector( ':scope > :is(p,h1,h2,h3,h4,h5,h6,ul,ol,table,blockquote,pre,hr)' ) ) {
			unwrap( child );
			return;
		}

		// Google Docs puts a paragraph inside every list item.
		if ( 'li' === tag && 1 === child.children.length && 'P' === child.firstElementChild.tagName ) {
			unwrap( child.firstElementChild );
		}

		// Enter leaves empty paragraphs and list items behind; they render as
		// nothing but a gap. One holding the caret is the line being typed on.
		if (
			/^(p|h[1-6]|li|ul|ol|blockquote|pre|table)$/.test( tag ) &&
			! child.textContent.trim() &&
			! child.querySelector( `img,hr,[${ CARET }]` )
		) {
			child.remove();
		}
	} );
}

/**
 * Put text and inline elements left loose between blocks into paragraphs, so
 * the stored HTML is all blocks and nothing depends on wpautop() guessing.
 *
 * @param {HTMLElement} body Parsed body.
 */
function wrapLoose( body ) {
	let run = null;

	Array.from( body.childNodes ).forEach( ( node ) => {
		if ( 1 === node.nodeType && BLOCK_TAGS.includes( node.tagName.toLowerCase() ) ) {
			run = null;
			return;
		}

		if ( 3 === node.nodeType && ! node.nodeValue.trim() && ! run ) {
			node.remove();
			return;
		}

		if ( ! run ) {
			run = body.ownerDocument.createElement( 'p' );
			node.before( run );
		}

		run.appendChild( node );
	} );

	Array.from( body.children ).forEach( ( element ) => {
		if (
			'P' === element.tagName &&
			! element.textContent.trim() &&
			! element.querySelector( `img,[${ CARET }]` )
		) {
			element.remove();
		}
	} );
}

/**
 * Reduce HTML to the structure the description supports.
 *
 * Parsing also repairs nesting the browser's editing commands can leave behind,
 * such as a list inside a paragraph.
 *
 * @param {string}  html              Raw HTML.
 * @param {Object}  options
 * @param {boolean} options.keepCaret Keep selection markers, even in empty content.
 * @param {boolean} options.paste     Read styled spans as the formatting they show.
 * @return {string} Clean HTML, or '' when there is nothing in it.
 */
export function cleanHtml( html, { keepCaret = false, paste = false } = {} ) {
	const body = parse( html );

	if ( ! keepCaret ) {
		body.querySelectorAll( `[${ CARET }]` ).forEach( ( marker ) => marker.remove() );
	}

	cleanChildren( body, { paste } );
	wrapLoose( body );

	if ( ! keepCaret && ! body.textContent.trim() && ! body.querySelector( 'img,hr' ) ) {
		return '';
	}

	return body.innerHTML.trim();
}

/**
 * Turn clipboard contents into HTML ready to insert.
 *
 * - HTML (a web page, the block editor, Google Docs, Word) keeps its structure.
 * - Plain text that is HTML source — copied from a code editor or from the old
 *   description textarea — is read as the HTML it is, with its newlines meaning
 *   what they meant there: a line break, or a paragraph when doubled.
 * - Other plain text keeps its line breaks and paragraphs.
 *
 * A paste that comes to a single paragraph is inserted as inline content, so a
 * sentence pasted into the middle of another does not split it in two.
 *
 * @param {string} html Clipboard text/html.
 * @param {string} text Clipboard text/plain.
 * @return {string} HTML.
 */
export function clipboardToHtml( html, text ) {
	let clean = '';

	if ( html && ! ( text && HTML_SOURCE.test( text ) && HTML_SOURCE.test( parse( html ).textContent ) ) ) {
		clean = cleanHtml( html, { paste: true } );
	} else if ( text && text.trim() ) {
		const source = HTML_SOURCE.test( text ) ? text : escapeHtml( text );
		clean = cleanHtml( autop( source.replace( /\r\n?/g, '\n' ) ), { paste: true } );
	}

	const body = parse( clean );

	if ( 1 === body.children.length && 'P' === body.firstElementChild.tagName && ! body.firstElementChild.getAttribute( 'style' ) ) {
		return body.firstElementChild.innerHTML;
	}

	return clean;
}

/**
 * A stored description as HTML the editor can load.
 *
 * Plain-text descriptions, and HTML typed into the old textarea with newlines for
 * line breaks, are passed through autop — the same rules wpautop() applies on the
 * page — so the editor shows them exactly as the front end does.
 *
 * @param {string} value Stored description.
 * @return {string} HTML.
 */
export function descriptionToHtml( value ) {
	const text = String( value || '' ).replace( /\r\n?/g, '\n' );

	if ( ! text.trim() ) {
		return '';
	}

	if ( ! /<[a-z][^>]*>/i.test( text ) ) {
		return autop( escapeHtml( text ) );
	}

	return /\n/.test( text.replace( /<pre[\s\S]*?<\/pre>/gi, '' ) ) ? autop( text ) : text;
}
