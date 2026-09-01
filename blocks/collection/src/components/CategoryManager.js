/**
 * The category table behind the filter bar.
 *
 * Categories are plain slug/label pairs kept on the block, not taxonomy terms:
 * a roundup's categories belong to that roundup, and registering them site-wide
 * would put "Ecommerce" and "Agency" into every other collection's filter bar.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

import { categoryCounts, makeCategory, orphanCategories } from '../model';

/**
 * @param {Object}   props
 * @param {Array}    props.categories Categories.
 * @param {Array}    props.items      Items, for counts and orphan detection.
 * @param {Function} props.onChange   Called with the updated categories.
 * @return {Element} Category manager.
 */
export default function CategoryManager( { categories, items, onChange } ) {
	const [ typed, setTyped ] = useState( '' );

	const counts = categoryCounts( items );
	const orphans = orphanCategories( items, categories );

	const add = () => {
		const label = typed.trim();

		if ( ! label ) {
			return;
		}

		const category = makeCategory( label );

		if ( categories.some( ( existing ) => existing.slug === category.slug ) ) {
			setTyped( '' );
			return;
		}

		onChange( [ ...categories, category ] );
		setTyped( '' );
	};

	return (
		<div className="darkify-collection-editor__categories">
			{ orphans.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Some items are filed under categories that are no longer listed. They still show on the cards, but there is no chip to filter by:',
						'darkify-util'
					) }
					<div className="darkify-collection-editor__orphans">
						{ orphans.map( ( slug ) => (
							<Button
								key={ slug }
								size="small"
								variant="secondary"
								onClick={ () => onChange( [ ...categories, makeCategory( slug ) ] ) }
							>
								{ sprintf(
									/* translators: %s: category slug. */
									__( 'Restore “%s”', 'darkify-util' ),
									slug
								) }
							</Button>
						) ) }
					</div>
				</Notice>
			) }

			<ul className="darkify-collection-editor__category-list">
				{ categories.map( ( category, index ) => (
					<li className="darkify-collection-editor__category" key={ category.slug }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Category name', 'darkify-util' ) }
							hideLabelFromVision
							value={ category.label }
							onChange={ ( label ) => {
								const next = categories.slice();
								// The slug is left alone on a rename. It is what
								// items point at and what sits in shared filter
								// URLs, so renaming a chip must not orphan
								// everything filed under it.
								next[ index ] = { ...category, label };
								onChange( next );
							} }
						/>

						<span className="darkify-collection-editor__category-count">
							{ sprintf(
								/* translators: %d: number of items in the category. */
								__( '%d items', 'darkify-util' ),
								counts[ category.slug ] || 0
							) }
						</span>

						<Button
							size="small"
							variant="tertiary"
							isDestructive
							icon="trash"
							onClick={ () =>
								onChange( categories.filter( ( _category, i ) => i !== index ) )
							}
							label={ __( 'Remove category', 'darkify-util' ) }
							showTooltip
						/>
					</li>
				) ) }
			</ul>

			{ 0 === categories.length && (
				<p className="darkify-collection-editor__hint">
					{ __(
						'No categories yet. Add one here, or just type a name into an item’s Categories field.',
						'darkify-util'
					) }
				</p>
			) }

			<div className="darkify-collection-editor__category-add">
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'New category', 'darkify-util' ) }
					hideLabelFromVision
					value={ typed }
					onChange={ setTyped }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							add();
						}
					} }
					placeholder={ __( 'New category', 'darkify-util' ) }
				/>
				<Button variant="secondary" onClick={ add } disabled={ ! typed.trim() }>
					{ __( 'Add', 'darkify-util' ) }
				</Button>
			</div>
		</div>
	);
}
