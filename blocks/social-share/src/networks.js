/**
 * The networks the block knows about, mirroring
 * Darkify_Util_Social_Share::networks() in PHP.
 *
 * Only what the editor needs: the slug, the shipped label, and a note for the
 * one network that does not behave like the others. The share endpoint, the
 * brand colour and the logo stay in PHP, which is the only place that renders
 * them.
 *
 * PHP is the authority: a slug it does not recognise is dropped at render time,
 * so this list exists to name and order the toggles, not to define the network.
 */

import { __ } from '@wordpress/i18n';

export const NETWORKS = [
	{ slug: 'facebook', label: 'Facebook' },
	{ slug: 'twitter', label: 'Twitter' },
	{
		slug: 'instagram',
		label: 'Instagram',
		note: __(
			'Instagram has no share link. This button copies the post URL and opens Instagram to paste it into.',
			'darkify-util'
		),
	},
	{ slug: 'linkedin', label: 'LinkedIn' },
	{ slug: 'whatsapp', label: 'WhatsApp' },
	{ slug: 'email', label: 'Email' },
];

/**
 * The saved list, repaired.
 *
 * A block saved by an older version has no entry for a network added since, and
 * one saved by a newer version may name a network this one has never heard of.
 * Unknown slugs are dropped (PHP drops them anyway) and missing ones appended
 * switched off, so the list is always complete and the author's order is kept.
 *
 * @param {Array} networks Saved `networks` attribute.
 * @return {Array} Complete list, in the author's order.
 */
export function withKnownNetworks( networks ) {
	const saved = Array.isArray( networks ) ? networks : [];
	const known = NETWORKS.map( ( network ) => network.slug );

	const kept = saved
		.filter(
			( network, index ) =>
				network &&
				known.includes( network.slug ) &&
				// First occurrence wins, so a duplicated slug cannot render twice.
				saved.findIndex( ( other ) => other?.slug === network.slug ) === index
		)
		.map( ( network ) => ( {
			slug: network.slug,
			label: network.label || labelFor( network.slug ),
			enabled: network.enabled !== false,
		} ) );

	const missing = NETWORKS.filter(
		( network ) => ! kept.some( ( item ) => item.slug === network.slug )
	).map( ( network ) => ( {
		slug: network.slug,
		label: network.label,
		enabled: false,
	} ) );

	return [ ...kept, ...missing ];
}

/**
 * @param {string} slug Network slug.
 * @return {string} Its shipped label, or the slug if it is unknown.
 */
export function labelFor( slug ) {
	const network = NETWORKS.find( ( item ) => item.slug === slug );

	return network ? network.label : slug;
}

/**
 * @param {string} slug Network slug.
 * @return {string} Anything the author needs to know about it, or ''.
 */
export function noteFor( slug ) {
	const network = NETWORKS.find( ( item ) => item.slug === slug );

	return network?.note || '';
}
