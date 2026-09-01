/**
 * The shape of a collection item, and the small helpers that make one.
 *
 * Kept in its own module rather than inside the editor components because the
 * shape is the contract: PHP sanitises exactly these keys (see
 * Darkify_Util_Collection::sanitize_items()), and both ends have to agree.
 *
 * The design goal is that an item is *not* about any one use case. A roundup
 * card, a website showcase tile and a plugin deal card are the same record with
 * different fields filled in: an image, a title, a line of supporting text, some
 * label/value metadata, one or more categories and a link. Anything a future
 * collection needs that is not here goes into `meta` — an open list of
 * label/value pairs — so adding a field never means a schema migration.
 */

/**
 * A short, collision-resistant id.
 *
 * Item ids are React keys and are what the editor reorders around, so they only
 * have to be unique inside one block.
 *
 * @param {string} prefix Prefix for the id.
 * @return {string} Identifier.
 */
export function uid( prefix = 'i' ) {
	return `${ prefix }${ Math.random().toString( 36 ).slice( 2, 9 ) }`;
}

/**
 * Turn a label into a category slug.
 *
 * Mirrors sanitize_key() closely enough that a slug made here survives the trip
 * through PHP unchanged — anything that would be stripped server-side is
 * stripped here, so the editor never shows a slug the front end will not honour.
 *
 * @param {string} label Human label.
 * @return {string} Slug.
 */
export function slugify( label ) {
	return String( label )
		.toLowerCase()
		.replace( /[^a-z0-9_\- ]+/g, '' )
		.trim()
		.replace( /\s+/g, '-' )
		.replace( /-+/g, '-' );
}

/**
 * A blank item.
 *
 * @param {Object} overrides Fields to preset.
 * @return {Object} Item.
 */
export function emptyItem( overrides = {} ) {
	return {
		id: uid( 'i' ),
		title: '',
		subtitle: '',
		description: '',
		badge: '',
		image: { id: 0, url: '', alt: '' },
		categories: [],
		url: '',
		linkLabel: '',
		meta: [],
		...overrides,
	};
}

/**
 * A blank metadata row.
 *
 * @return {Object} Meta pair.
 */
export function emptyMeta() {
	return { id: uid( 'm' ), label: '', value: '' };
}

/**
 * A category, from a typed label.
 *
 * @param {string} label Human label.
 * @return {Object} Category.
 */
export function makeCategory( label ) {
	const slug = slugify( label );

	return { slug: slug || uid( 'c' ), label: label.trim() || slug };
}

/**
 * Categories that items point at but the category list has lost.
 *
 * Deleting a category should not silently orphan the items filed under it, so
 * the editor offers to restore whatever is still referenced.
 *
 * @param {Array} items      Items.
 * @param {Array} categories Known categories.
 * @return {Array} Missing category slugs.
 */
export function orphanCategories( items, categories ) {
	const known = categories.map( ( category ) => category.slug );
	const missing = new Set();

	items.forEach( ( item ) => {
		( item.categories || [] ).forEach( ( slug ) => {
			if ( ! known.includes( slug ) ) {
				missing.add( slug );
			}
		} );
	} );

	return Array.from( missing );
}

/**
 * How many items each category holds.
 *
 * @param {Array} items Items.
 * @return {Object} Slug-keyed counts.
 */
export function categoryCounts( items ) {
	const counts = {};

	items.forEach( ( item ) => {
		( item.categories || [] ).forEach( ( slug ) => {
			counts[ slug ] = ( counts[ slug ] || 0 ) + 1;
		} );
	} );

	return counts;
}
