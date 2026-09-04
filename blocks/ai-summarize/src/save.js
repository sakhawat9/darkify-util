/**
 * The block saves nothing.
 *
 * It has to be dynamic: the prompt names the page the block is sitting on, and
 * a block placed in a template renders a different link on every post. Saving
 * markup would freeze one post's URL into every one of them.
 *
 * @return {null} Nothing.
 */
export default function save() {
	return null;
}
