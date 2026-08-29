/**
 * Per-category colour controls for the inspector.
 *
 * The list is whatever the parsed changelog actually used, so a project with a
 * "Security" or "Performance" category gets a colour control for it without
 * anyone editing this file.
 */

import { __ } from '@wordpress/i18n';
import { ColorIndicator, ColorPalette, Dropdown, Button } from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {Array}    props.categories Categories.
 * @param {Function} props.onChange   Called with the updated categories array.
 * @return {Element} Colour controls.
 */
export default function CategoryColors( { categories, onChange } ) {
	const update = ( index, color ) => {
		const next = categories.slice();
		next[ index ] = { ...next[ index ], color: color || next[ index ].color };
		onChange( next );
	};

	return (
		<ul className="darkify-changelog-editor__colors">
			{ categories.map( ( category, index ) => (
				<li className="darkify-changelog-editor__color" key={ category.slug }>
					<Dropdown
						popoverProps={ { placement: 'left-start' } }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								variant="tertiary"
								onClick={ onToggle }
								aria-expanded={ isOpen }
								className="darkify-changelog-editor__color-toggle"
							>
								<ColorIndicator colorValue={ category.color } />
								<span>{ category.label }</span>
							</Button>
						) }
						renderContent={ () => (
							<div className="darkify-changelog-editor__color-picker">
								<ColorPalette
									value={ category.color }
									onChange={ ( color ) => update( index, color ) }
									clearable={ false }
									enableAlpha={ false }
								/>
							</div>
						) }
					/>
				</li>
			) ) }

			{ ! categories.length && (
				<li className="darkify-changelog-editor__color-empty">
					{ __( 'Categories appear here once a changelog is converted.', 'darkify-util' ) }
				</li>
			) }
		</ul>
	);
}
