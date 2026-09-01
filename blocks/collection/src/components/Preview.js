/**
 * The editor's preview.
 *
 * Not a lookalike built out of React: it asks the server to render the block
 * exactly as the front end will, then runs the front end's own view module over
 * the result. So the filter chips, the search box, Load More and the numbered
 * pages behave in the editor because they *are* the published behaviour.
 *
 * The attributes are handed to the view module as well as to ServerSideRender.
 * The block being previewed may never have been saved, so its AJAX cannot read
 * the items back off the post the way the front end does — it posts them
 * instead, which the server accepts only from someone who can edit posts.
 */

import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

import { setUpBlock } from '../view';

/**
 * @param {Object} props
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Preview.
 */
export default function Preview( { attributes } ) {
	const holder = useRef( null );
	const latest = useRef( attributes );

	latest.current = attributes;

	useEffect( () => {
		const node = holder.current;

		if ( ! node ) {
			return undefined;
		}

		// ServerSideRender replaces its subtree whenever the attributes change,
		// so the enhancement is re-applied on each new render rather than once.
		const enhance = () => {
			node.querySelectorAll( '[data-darkify-collection]' ).forEach( ( root ) => {
				setUpBlock( root, {
					// Read through a ref so a request fired later in the
					// preview's life sends the attributes as they are then, not
					// as they were when this subtree was first enhanced.
					get attributes() {
						return latest.current;
					},
				} );
			} );
		};

		enhance();

		const observer = new window.MutationObserver( enhance );
		observer.observe( node, { childList: true, subtree: true } );

		return () => observer.disconnect();
	}, [] );

	return (
		<div className="darkify-collection-editor__preview" ref={ holder }>
			<ServerSideRender
				block="darkify-util/collection"
				attributes={ attributes }
				// POST because the attributes carry every item, image and
				// description; a collection of forty is not a GET query.
				httpMethod="POST"
				EmptyResponsePlaceholder={ () => (
					<p className="darkify-collection__empty">
						{ __( 'Nothing to show yet.', 'darkify-util' ) }
					</p>
				) }
				LoadingResponsePlaceholder={ () => (
					<p className="darkify-collection-editor__loading">
						<Spinner />
						{ __( 'Rendering preview…', 'darkify-util' ) }
					</p>
				) }
			/>
		</div>
	);
}
