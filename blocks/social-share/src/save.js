/**
 * The block saves nothing.
 *
 * It has to be dynamic: the share links name the page the block is sitting on,
 * and a block placed in a template renders different links on every post.
 * Saving markup would freeze one post's URL into all of them.
 *
 * @return {null} Nothing.
 */
export default function save() {
	return null;
}
