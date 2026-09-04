/**
 * The network list in the inspector.
 *
 * One row per network: a checkbox for whether it is offered, arrows for where it
 * sits, and — for the ones that are on — the text on the button. The order of
 * this list is the order of the buttons, which is why reordering lives here
 * rather than being derived from anything else.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, TextControl } from '@wordpress/components';

import { labelFor, noteFor } from '../networks';

/**
 * @param {Object}   props
 * @param {Array}    props.networks   Complete, repaired network list.
 * @param {boolean}  props.showLabels Whether button text is rendered at all.
 * @param {Function} props.onChange   Receives the new list.
 * @return {Element} The list.
 */
export default function NetworkList( { networks, showLabels, onChange } ) {
	const update = ( index, changes ) => {
		onChange(
			networks.map( ( network, position ) =>
				position === index ? { ...network, ...changes } : network
			)
		);
	};

	const move = ( index, offset ) => {
		const target = index + offset;

		if ( target < 0 || target >= networks.length ) {
			return;
		}

		const next = [ ...networks ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];

		onChange( next );
	};

	const enabled = networks.filter( ( network ) => network.enabled ).length;

	return (
		<>
			<ul className="darkify-share-editor__networks">
				{ networks.map( ( network, index ) => (
					<li className="darkify-share-editor__network" key={ network.slug }>
						<div className="darkify-share-editor__network-row">
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ labelFor( network.slug ) }
								help={ network.enabled ? noteFor( network.slug ) : '' }
								checked={ network.enabled }
								onChange={ ( value ) => update( index, { enabled: value } ) }
							/>

							<div className="darkify-share-editor__network-tools">
								<Button
									size="small"
									icon="arrow-up-alt2"
									label={ sprintf(
										/* translators: %s: name of the social network. */
										__( 'Move %s up', 'darkify-util' ),
										labelFor( network.slug )
									) }
									disabled={ 0 === index }
									onClick={ () => move( index, -1 ) }
								/>
								<Button
									size="small"
									icon="arrow-down-alt2"
									label={ sprintf(
										/* translators: %s: name of the social network. */
										__( 'Move %s down', 'darkify-util' ),
										labelFor( network.slug )
									) }
									disabled={ index === networks.length - 1 }
									onClick={ () => move( index, 1 ) }
								/>
							</div>
						</div>

						{ /*
						 * The label field is hidden while the buttons are
						 * icon-only, because nothing would show it. The value is
						 * kept, so turning labels back on restores what was
						 * typed rather than resetting to the network's name.
						 */ }
						{ network.enabled && showLabels && (
							<TextControl
								__nextHasNoMarginBottom
								className="darkify-share-editor__network-label"
								label={ __( 'Button text', 'darkify-util' ) }
								hideLabelFromVision
								placeholder={ labelFor( network.slug ) }
								value={ network.label }
								onChange={ ( value ) => update( index, { label: value } ) }
							/>
						) }
					</li>
				) ) }
			</ul>

			{ 0 === enabled && (
				<p className="darkify-share-editor__hint">
					{ __(
						'Nothing is switched on, so the block renders nothing.',
						'darkify-util'
					) }
				</p>
			) }
		</>
	);
}
