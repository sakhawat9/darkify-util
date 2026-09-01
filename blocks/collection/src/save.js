/**
 * The block saves nothing.
 *
 * The collection is dynamic for the same reason the changelog is: attributes
 * live in the block comment, there is no inner content, and a deactivated plugin
 * therefore renders nothing rather than printing the raw item data at visitors.
 *
 * It is also what makes the AJAX path possible — the server can read the items
 * back out of the block comment, so a filter or a page change never has to trust
 * a collection sent up from the browser.
 *
 * @return {null} Nothing.
 */
export default function save() {
	return null;
}
