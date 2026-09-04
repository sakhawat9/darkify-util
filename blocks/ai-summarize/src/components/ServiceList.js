/**
 * The assistant list in the inspector.
 *
 * One row per service: a checkbox for whether it is offered, arrows for where
 * it sits, and — for the ones that are on — the text on the button. The order
 * of this list is the order of the buttons, which is why reordering lives here
 * rather than being derived from anything else.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, TextControl } from '@wordpress/components';

import { labelFor, noteFor } from '../services';

/**
 * @param {Object}   props
 * @param {Array}    props.services Complete, repaired service list.
 * @param {Function} props.onChange Receives the new list.
 * @return {Element} The list.
 */
export default function ServiceList( { services, onChange } ) {
	const update = ( index, changes ) => {
		onChange(
			services.map( ( service, position ) =>
				position === index ? { ...service, ...changes } : service
			)
		);
	};

	const move = ( index, offset ) => {
		const target = index + offset;

		if ( target < 0 || target >= services.length ) {
			return;
		}

		const next = [ ...services ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];

		onChange( next );
	};

	const enabled = services.filter( ( service ) => service.enabled ).length;

	return (
		<>
			<ul className="darkify-ai-editor__services">
				{ services.map( ( service, index ) => (
					<li className="darkify-ai-editor__service" key={ service.slug }>
						<div className="darkify-ai-editor__service-row">
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ labelFor( service.slug ) }
								help={ service.enabled ? noteFor( service.slug ) : '' }
								checked={ service.enabled }
								onChange={ ( value ) => update( index, { enabled: value } ) }
							/>

							<div className="darkify-ai-editor__service-tools">
								<Button
									size="small"
									icon="arrow-up-alt2"
									label={ sprintf(
										/* translators: %s: name of the AI assistant. */
										__( 'Move %s up', 'darkify-util' ),
										labelFor( service.slug )
									) }
									disabled={ 0 === index }
									onClick={ () => move( index, -1 ) }
								/>
								<Button
									size="small"
									icon="arrow-down-alt2"
									label={ sprintf(
										/* translators: %s: name of the AI assistant. */
										__( 'Move %s down', 'darkify-util' ),
										labelFor( service.slug )
									) }
									disabled={ index === services.length - 1 }
									onClick={ () => move( index, 1 ) }
								/>
							</div>
						</div>

						{ service.enabled && (
							<TextControl
								__nextHasNoMarginBottom
								className="darkify-ai-editor__service-label"
								label={ __( 'Button text', 'darkify-util' ) }
								hideLabelFromVision
								placeholder={ labelFor( service.slug ) }
								value={ service.label }
								onChange={ ( value ) => update( index, { label: value } ) }
							/>
						) }
					</li>
				) ) }
			</ul>

			{ 0 === enabled && (
				<p className="darkify-ai-editor__hint">
					{ __(
						'Nothing is switched on, so the block renders nothing.',
						'darkify-util'
					) }
				</p>
			) }
		</>
	);
}
