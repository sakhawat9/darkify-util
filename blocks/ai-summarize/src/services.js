/**
 * The assistants the block knows about, mirroring
 * Darkify_Util_AI_Summarize::services() in PHP.
 *
 * Only the parts the editor needs are here — the slug, the shipped label, and a
 * note about what the button will actually do. The link, the brand colour and
 * the logo stay in PHP, which is the only place that renders them.
 *
 * PHP is the authority: a slug it does not recognise is dropped at render time,
 * so this list exists to name and order the toggles, not to define the service.
 */

export const SERVICES = [
	{
		slug: 'chatgpt',
		label: 'ChatGPT',
		note: 'Opens chatgpt.com with the prompt filled in.',
	},
	{
		slug: 'claude',
		label: 'Claude',
		note: 'Opens claude.ai with the prompt filled in.',
	},
	{
		slug: 'grok',
		label: 'Grok',
		note: 'Opens grok.com with the prompt filled in.',
	},
	{
		slug: 'perplexity',
		label: 'Perplexity',
		note: 'Opens a Perplexity search for the prompt.',
	},
	{
		slug: 'copilot',
		label: 'Copilot',
		note: 'Opens copilot.microsoft.com with the prompt filled in.',
	},
];

/**
 * The saved list, repaired.
 *
 * A block saved by an older version has no entry for a service added since, and
 * a block saved by a newer one may name a service this version has never heard
 * of. Unknown slugs are dropped (PHP would drop them anyway) and missing ones
 * are appended switched off, so the toggle list is always complete and the
 * author's order is left alone.
 *
 * @param {Array} services Saved `services` attribute.
 * @return {Array} Complete list, in the author's order.
 */
export function withKnownServices( services ) {
	const saved = Array.isArray( services ) ? services : [];
	const known = SERVICES.map( ( service ) => service.slug );

	const kept = saved
		.filter(
			( service, index ) =>
				service &&
				known.includes( service.slug ) &&
				// First occurrence wins, so a duplicated slug cannot render twice.
				saved.findIndex( ( other ) => other?.slug === service.slug ) === index
		)
		.map( ( service ) => ( {
			slug: service.slug,
			label: service.label || labelFor( service.slug ),
			enabled: service.enabled !== false,
		} ) );

	const missing = SERVICES.filter(
		( service ) => ! kept.some( ( item ) => item.slug === service.slug )
	).map( ( service ) => ( {
		slug: service.slug,
		label: service.label,
		enabled: false,
	} ) );

	return [ ...kept, ...missing ];
}

/**
 * @param {string} slug Service slug.
 * @return {string} Its shipped label, or the slug if it is unknown.
 */
export function labelFor( slug ) {
	const service = SERVICES.find( ( item ) => item.slug === slug );

	return service ? service.label : slug;
}

/**
 * @param {string} slug Service slug.
 * @return {string} What the button does, for the inspector.
 */
export function noteFor( slug ) {
	const service = SERVICES.find( ( item ) => item.slug === slug );

	return service ? service.note : '';
}
