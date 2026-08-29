/**
 * The editor's preview.
 *
 * Not a lookalike built out of React: it asks the server to render the block
 * exactly as the front end will, then runs the front end's own view module over
 * the result. So the filter bar, the load-more, the version navigation and the
 * search behave in the editor because they *are* the published behaviour, and
 * there is no second implementation to drift out of step with the first.
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

	useEffect( () => {
		const node = holder.current;

		if ( ! node ) {
			return undefined;
		}

		// ServerSideRender replaces its subtree whenever the attributes change,
		// so the enhancement is re-applied on each new render rather than once.
		const enhance = () => {
			node.querySelectorAll( '[data-darkify-changelog]' ).forEach( ( root ) => {
				setUpBlock( root );
			} );
		};

		enhance();

		const observer = new window.MutationObserver( enhance );
		observer.observe( node, { childList: true, subtree: true } );

		return () => observer.disconnect();
	}, [ attributes ] );

	return (
		<div className="darkify-changelog-editor__preview" ref={ holder }>
			<ServerSideRender
				block="darkify-util/changelog"
				attributes={ attributes }
				// POST because the attributes carry the whole changelog; a
				// 40KB GET query is not a request any server will thank you for.
				httpMethod="POST"
				EmptyResponsePlaceholder={ () => (
					<p className="darkify-changelog__empty">
						{ __( 'No changelog entries yet.', 'darkify-util' ) }
					</p>
				) }
				LoadingResponsePlaceholder={ () => (
					<p className="darkify-changelog-editor__loading">
						<Spinner />
						{ __( 'Rendering preview…', 'darkify-util' ) }
					</p>
				) }
			/>
		</div>
	);
}
