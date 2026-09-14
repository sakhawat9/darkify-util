/**
 * The countdown arithmetic, shared by the editor preview and the front end.
 *
 * Everything here works in epoch milliseconds, which have no timezone. The one
 * place a timezone matters — turning the wall-clock time an author picked into
 * an instant — happens before these are called: in PHP with wp_timezone() for
 * the page, and with @wordpress/date's getDate() in the editor. Both use the
 * site's timezone, so the two agree on the instant.
 *
 * Mirrored in Darkify_Util_Promo_Banner; change one, change the other.
 */

export const MINUTE_MS = 60 * 1000;
export const HOUR_MS = 60 * MINUTE_MS;
export const DAY_MS = 24 * HOUR_MS;

/**
 * The deadline currently in force.
 *
 * A recurring banner rolls its deadline forward by whole intervals until it is
 * in the future again, so a "weekly deal" never needs editing: every cycle lands
 * on the same weekday and time as the date first picked.
 *
 * @param {number} deadline Deadline as picked, or any later cycle of it.
 * @param {number} interval Recurrence in ms, or 0 for a one-off.
 * @param {number} now      Current time.
 * @return {number} The deadline to count down to.
 */
export function resolveDeadline( deadline, interval, now ) {
	if ( ! deadline || ! interval || now < deadline ) {
		return deadline;
	}

	return deadline + ( Math.floor( ( now - deadline ) / interval ) + 1 ) * interval;
}

/**
 * @param {number} deadline
 * @param {number} now
 * @return {{total: number, days: number, hours: number, minutes: number, seconds: number}} Time left.
 */
export function remainingParts( deadline, now ) {
	const total = Math.max( 0, deadline - now );
	const seconds = Math.floor( total / 1000 );

	return {
		total,
		days: Math.floor( seconds / 86400 ),
		hours: Math.floor( ( seconds % 86400 ) / 3600 ),
		minutes: Math.floor( ( seconds % 3600 ) / 60 ),
		seconds: seconds % 60,
	};
}

/**
 * Which of the three messages applies.
 *
 * @param {number} deadline     Resolved deadline, or 0 for none.
 * @param {number} now          Current time.
 * @param {number} urgentWithin How close counts as urgent, in ms; 0 for never.
 * @return {'active'|'urgent'|'expired'} State.
 */
export function stateFor( deadline, now, urgentWithin ) {
	if ( ! deadline ) {
		return 'active';
	}

	const left = deadline - now;

	if ( left <= 0 ) {
		return 'expired';
	}

	return urgentWithin && left <= urgentWithin ? 'urgent' : 'active';
}

/**
 * The segments the countdown shows, in order.
 *
 * With days switched off their hours fold into the hours segment, so the
 * countdown still adds up: 2 days 3 hours reads 51h rather than 03h.
 *
 * @param {Object}  parts    From remainingParts().
 * @param {boolean} showDays
 * @return {Array<{key: string, value: number}>} Segments.
 */
export function countdownUnits( parts, showDays ) {
	const units = [
		{ key: 'hours', value: showDays ? parts.hours : parts.hours + parts.days * 24 },
		{ key: 'minutes', value: parts.minutes },
		{ key: 'seconds', value: parts.seconds },
	];

	return showDays ? [ { key: 'days', value: parts.days }, ...units ] : units;
}

/**
 * @param {number} value
 * @return {string} At least two digits.
 */
export function pad( value ) {
	return String( value ).padStart( 2, '0' );
}

/**
 * Time left as words, for the {remaining} token: "3 days", "5 hours", "40 minutes".
 *
 * Rounded down in days and hours — "ends in 4 hours" at 4h50m is true, "ends in
 * 5 hours" is not — and up in minutes, so the last minute never reads "0".
 *
 * @param {number}             total  Time left in ms.
 * @param {string | undefined} locale BCP 47 tag, or undefined for the browser's.
 * @return {string} Formatted duration.
 */
export function humanRemaining( total, locale ) {
	let unit = 'minute';
	let value = Math.max( 1, Math.ceil( total / MINUTE_MS ) );

	if ( total >= 2 * DAY_MS ) {
		unit = 'day';
		value = Math.floor( total / DAY_MS );
	} else if ( total >= 2 * HOUR_MS ) {
		unit = 'hour';
		value = Math.floor( total / HOUR_MS );
	}

	try {
		return new Intl.NumberFormat( locale, {
			style: 'unit',
			unit,
			unitDisplay: 'long',
		} ).format( value );
	} catch {
		return `${ value } ${ unit }${ 1 === value ? '' : 's' }`;
	}
}
