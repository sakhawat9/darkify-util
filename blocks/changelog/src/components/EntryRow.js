/**
 * One entry inside a version: its category, its text, and an optional link.
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * @param {Object}   props
 * @param {Object}   props.entry      The entry.
 * @param {Array}    props.categories Available categories.
 * @param {Function} props.onChange   Called with the updated entry.
 * @param {Function} props.onRemove   Called to delete the entry.
 * @return {Element} Entry row.
 */
export default function EntryRow( { entry, categories, onChange, onRemove } ) {
	const [ showLink, setShowLink ] = useState( Boolean( entry.link && entry.link.url ) );

	const update = ( changes ) => onChange( { ...entry, ...changes } );

	return (
		<li className="darkify-changelog-editor__entry">
			<div className="darkify-changelog-editor__entry-main">
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Category', 'darkify-util' ) }
					hideLabelFromVision
					value={ entry.category }
					options={ categories.map( ( category ) => ( {
						label: category.label,
						value: category.slug,
					} ) ) }
					onChange={ ( category ) => update( { category } ) }
					className="darkify-changelog-editor__entry-category"
				/>

				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Change', 'darkify-util' ) }
					hideLabelFromVision
					value={ entry.text }
					onChange={ ( text ) => update( { text } ) }
					rows={ 2 }
					className="darkify-changelog-editor__entry-text"
				/>

				<div className="darkify-changelog-editor__entry-tools">
					<Button
						size="small"
						variant="tertiary"
						onClick={ () => setShowLink( ! showLink ) }
						aria-expanded={ showLink }
						label={ __( 'Attach a link', 'darkify-util' ) }
						showTooltip
						icon="admin-links"
					/>
					<Button
						size="small"
						variant="tertiary"
						isDestructive
						onClick={ onRemove }
						label={ __( 'Remove entry', 'darkify-util' ) }
						showTooltip
						icon="trash"
					/>
				</div>
			</div>

			{ showLink && (
				<div className="darkify-changelog-editor__entry-link">
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Link URL', 'darkify-util' ) }
						type="url"
						value={ entry.link ? entry.link.url : '' }
						onChange={ ( url ) =>
							update( { link: { ...entry.link, url } } )
						}
						placeholder="https://"
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Link text', 'darkify-util' ) }
						value={ entry.link ? entry.link.label : '' }
						onChange={ ( label ) =>
							update( { link: { ...entry.link, label } } )
						}
						placeholder={ __( 'Details', 'darkify-util' ) }
					/>
				</div>
			) }
		</li>
	);
}
