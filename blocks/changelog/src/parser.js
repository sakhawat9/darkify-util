/**
 * The editor's changelog parser.
 *
 * A direct mirror of Darkify_Util_Changelog_Parser (PHP), sharing its category
 * table by importing the very same JSON file — so "Fix:" cannot normalise one
 * way in the editor and another way during a migration.
 *
 * The two are mirrors by hand, so a change to the parsing rules on either side
 * belongs on both. The heading and entry shapes they must handle are documented
 * in the PHP file's header.
 *
 * @see ../../../includes/class-darkify-util-changelog-parser.php
 * @see ../../../includes/changelog-categories.json
 */

// The standard import attribute for JSON modules; webpack bundles the file and
// the parser can also be read (or run) outside the bundle without ceremony.
import table from '../../../includes/changelog-categories.json' with { type: 'json' };

const HEADING = /^={1,3}\s*(.+?)\s*={1,3}$/;
const PARENTHESISED = /^(\S+)\s*\(([^)]+)\)\s*(.*)$/;
const DASHED = /^(\S+)\s*[–—-]\s*(.+)$/;
// The remainder splits on its *first* separator, not on a whitespace boundary:
// `1 September 2026 - Big release` is a date and a label, and `\S+` would stop
// at "1". Mirrors the second split in the PHP parser.
const DASHED_TAIL = /^(.+?)\s*[–—-]\s*(.+)$/;
const BULLET = /^\s*[*\-•·–]\s+/;
const CATEGORY = /^([A-Za-z][A-Za-z ]{1,20}):\s*(.+)$/;
const HAS_DIGIT = /\d/;
const ORDINAL = /(\d+)(st|nd|rd|th)/gi;

let counter = 0;

/**
 * Unique enough within one block's attributes.
 *
 * @param {string} prefix Short prefix, `v` or `e`.
 * @return {string} Identifier.
 */
function makeId( prefix ) {
	counter += 1;
	return `${ prefix }-${ Date.now().toString( 36 ) }${ counter.toString( 36 ) }`;
}

/**
 * Resolve a label as written into a canonical slug.
 *
 * @param {string} label Label as written.
 * @return {string} Canonical slug.
 */
function normalizeCategory( label ) {
	const key = String( label || '' )
		.toLowerCase()
		.trim()
		.replace( /^[:.\-\s]+|[:.\-\s]+$/g, '' );

	if ( ! key ) {
		return table.fallback.slug;
	}

	if ( table.aliases[ key ] ) {
		return table.aliases[ key ];
	}

	const slug = key.replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );

	return slug || table.fallback.slug;
}

/**
 * A stable colour for a category the table has never seen. Mirrors the PHP
 * side: same slug, same colour, whichever parser ran.
 *
 * @param {string} slug Category slug.
 * @return {string} Hex colour.
 */
function generatedColor( slug ) {
	const colors = table.generatedColors;
	let hash = 0;

	for ( let i = 0; i < slug.length; i++ ) {
		hash = ( hash * 31 + slug.charCodeAt( i ) ) % 100000;
	}

	return colors[ hash % colors.length ];
}

/**
 * Best-effort ISO date. An unreadable date is not an error — the version keeps
 * its text and simply has no machine-readable twin.
 *
 * @param {string} date Date as written.
 * @return {string} `YYYY-MM-DD`, or ''.
 */
export function parseDate( date ) {
	const text = String( date || '' ).trim();

	if ( ! text ) {
		return '';
	}

	const parsed = new Date( text.replace( ORDINAL, '$1' ) );

	if ( isNaN( parsed.getTime() ) ) {
		return '';
	}

	const month = String( parsed.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( parsed.getDate() ).padStart( 2, '0' );

	return `${ parsed.getFullYear() }-${ month }-${ day }`;
}

/**
 * Backticks become <code>; everything else is left for the server to sanitise
 * with wp_kses_post on save and again on render.
 *
 * @param {string} text Entry text.
 * @return {string} Entry text with code spans.
 */
function formatText( text ) {
	return String( text || '' )
		.replace( /`([^`]+)`/g, ( _match, code ) => {
			const escaped = code
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' );
			return `<code>${ escaped }</code>`;
		} )
		.trim();
}

/**
 * Parse a heading line.
 *
 * @param {string} line Trimmed line.
 * @return {?Object} `{ version, date, label }` or null.
 */
function parseHeading( line ) {
	const matched = line.match( HEADING );

	if ( ! matched ) {
		return null;
	}

	const inside = matched[ 1 ].trim();

	if ( ! inside ) {
		return null;
	}

	let version = inside;
	let date = '';
	let label = '';

	const parenthesised = inside.match( PARENTHESISED );
	const dashed = inside.match( DASHED );

	if ( parenthesised ) {
		version = parenthesised[ 1 ];
		date = parenthesised[ 2 ].trim();
		label = parenthesised[ 3 ].trim();
	} else if ( dashed ) {
		version = dashed[ 1 ];
		const remainder = dashed[ 2 ].trim();
		const tail = remainder.match( DASHED_TAIL );

		if ( tail ) {
			date = tail[ 1 ].trim();
			label = tail[ 2 ].trim();
		} else {
			date = remainder;
		}
	}

	// `1.4.15 16 December 2025` — no separator at all. Only split when the
	// remainder actually reads as a date, so a genuine label is left alone.
	if ( ! date ) {
		const spaced = version.match( /^(\S+)\s+(.+)$/ );

		if ( spaced && parseDate( spaced[ 2 ] ) ) {
			version = spaced[ 1 ];
			date = spaced[ 2 ].trim();
		}
	}

	// `== Changelog ==` is a readme section, not a version.
	if ( ! HAS_DIGIT.test( version ) ) {
		return null;
	}

	return {
		version: version.replace( /^["']+|["']+$/g, '' ).trim(),
		date,
		label,
	};
}

/**
 * Parse an entry line, with or without a bullet and category prefix.
 *
 * @param {string} line Trimmed line.
 * @return {?Object} Entry, or null when there is nothing left after trimming.
 */
function parseEntry( line ) {
	let text = line.replace( BULLET, '' ).trim();

	if ( ! text ) {
		return null;
	}

	let category = table.fallback.slug;
	let sourceLabel = '';

	const matched = text.match( CATEGORY );

	if ( matched ) {
		sourceLabel = matched[ 1 ].trim();
		category = normalizeCategory( sourceLabel );
		text = matched[ 2 ].trim();
	}

	if ( ! text ) {
		return null;
	}

	return {
		id: makeId( 'e' ),
		category,
		sourceLabel,
		text: formatText( text ),
		link: { url: '', label: '' },
	};
}

/**
 * Build the category list for a parsed document: canonical entries first, in
 * their declared order, then anything the text introduced.
 *
 * @param {Object} used slug => label as written.
 * @return {Array} Categories.
 */
function categoriesFor( used ) {
	const slugs = Object.keys( used );
	const categories = [];
	const seen = {};

	table.canonical.forEach( ( category ) => {
		if ( slugs.includes( category.slug ) ) {
			categories.push( { ...category } );
			seen[ category.slug ] = true;
		}
	} );

	slugs.forEach( ( slug ) => {
		if ( seen[ slug ] ) {
			return;
		}

		if ( slug === table.fallback.slug ) {
			categories.push( { ...table.fallback } );
		} else {
			const label =
				used[ slug ] ||
				slug.replace( /-/g, ' ' ).replace( /\b\w/g, ( c ) => c.toUpperCase() );
			categories.push( { slug, label, color: generatedColor( slug ) } );
		}

		seen[ slug ] = true;
	} );

	return categories.length ? categories : table.canonical.map( ( c ) => ( { ...c } ) );
}

/**
 * Parse raw changelog text into the block's structure.
 *
 * @param {string} text Raw changelog.
 * @return {Object} `{ versions, categories, warnings }`.
 */
export function parseChangelog( text ) {
	const lines = String( text || '' ).split( /\r\n|\r|\n/ );
	const versions = [];
	const warnings = [];
	const used = {};
	let current = null;

	lines.forEach( ( raw, index ) => {
		const line = raw.trim();

		if ( ! line ) {
			return;
		}

		const heading = parseHeading( line );

		if ( heading ) {
			if ( current ) {
				versions.push( current );
			}

			current = {
				id: makeId( 'v' ),
				version: heading.version,
				date: heading.date,
				dateISO: parseDate( heading.date ),
				label: heading.label,
				entries: [],
			};

			return;
		}

		const entry = parseEntry( line );

		if ( ! entry ) {
			warnings.push( { line: index + 1, text: line } );
			return;
		}

		if ( ! current ) {
			current = {
				id: makeId( 'v' ),
				version: '',
				date: '',
				dateISO: '',
				label: '',
				entries: [],
			};
		}

		used[ entry.category ] = entry.sourceLabel || used[ entry.category ] || '';
		current.entries.push( entry );
	} );

	if ( current ) {
		versions.push( current );
	}

	return { versions, categories: categoriesFor( used ), warnings };
}

/**
 * A blank version, for the "Add version" button.
 *
 * @return {Object} Empty version.
 */
export function emptyVersion() {
	return {
		id: makeId( 'v' ),
		version: '',
		date: '',
		dateISO: '',
		label: '',
		entries: [],
	};
}

/**
 * A blank entry, for the "Add entry" button.
 *
 * @param {string} category Category slug to start on.
 * @return {Object} Empty entry.
 */
export function emptyEntry( category ) {
	return {
		id: makeId( 'e' ),
		category: category || table.canonical[ 0 ].slug,
		sourceLabel: '',
		text: '',
		link: { url: '', label: '' },
	};
}
