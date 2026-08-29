/**
 * The block saves nothing.
 *
 * This is the whole reason the block is dynamic. The third-party block this
 * replaces stored its data as inner content, and WordPress prints the inner
 * content of an unregistered block verbatim — which is why deactivating that
 * plugin dumped 40KB of raw JSON onto the Changelogs page. With `null` there is
 * no inner content to print: attributes live in the block comment, and the
 * worst case for a deactivated plugin is that the section renders nothing.
 *
 * @return {null} Nothing.
 */
export default function save() {
	return null;
}
