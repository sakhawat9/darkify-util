/**
 * The block saves nothing.
 *
 * It has to be dynamic: which message shows, and what the countdown reads, both
 * depend on the moment the page is served. Saved markup would freeze the banner
 * at the moment the post was last updated — a countdown that starts wrong and an
 * "ends in 5 hours" that is still there next week.
 *
 * @return {null} Nothing.
 */
export default function save() {
	return null;
}
