/**
 * One item in the collection editor.
 *
 * Collapsed it is a row: thumbnail, title, categories, tools. Opened it is the
 * whole record. Collections run to twenty or forty items and every field of
 * every one of them on screen at once is unusable, so the list stays a list
 * until you ask it not to be.
 */

import { __, sprintf } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
	Button,
	FormTokenField,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import { emptyMeta, makeCategory } from '../model';

/**
 * @param {Object}   props
 * @param {Object}   props.item             The item.
 * @param {number}   props.index            Its position.
 * @param {Array}    props.categories       Known categories.
 * @param {boolean}  props.isFirst          First in the list.
 * @param {boolean}  props.isLast           Last in the list.
 * @param {Function} props.onChange         Called with the updated item.
 * @param {Function} props.onRemove         Delete this item.
 * @param {Function} props.onDuplicate      Copy this item.
 * @param {Function} props.onMove           Move by a delta.
 * @param {Function} props.onAddCategories  Called with categories typed into the token field.
 * @return {Element} Item row.
 */
export default function ItemRow( {
	item,
	index,
	categories,
	isFirst,
	isLast,
	onChange,
	onRemove,
	onDuplicate,
	onMove,
	onAddCategories,
} ) {
	const [ open, setOpen ] = useState( false );

	const update = ( changes ) => onChange( { ...item, ...changes } );

	const labelFor = ( slug ) => {
		const found = categories.find( ( category ) => category.slug === slug );
		return found ? found.label : slug;
	};

	/**
	 * The token field speaks labels; the item stores slugs. A label that is not
	 * a category yet becomes one, so filing an item under something new is one
	 * step rather than a trip to the category panel and back.
	 *
	 * @param {Array} tokens Labels from the field.
	 */
	const setCategories = ( tokens ) => {
		const created = [];

		const slugs = tokens.map( ( token ) => {
			const known = categories.find(
				( category ) =>
					category.label.toLowerCase() === String( token ).toLowerCase() ||
					category.slug === token
			);

			if ( known ) {
				return known.slug;
			}

			const category = makeCategory( String( token ) );
			created.push( category );

			return category.slug;
		} );

		if ( created.length ) {
			onAddCategories( created );
		}

		update( { categories: slugs.filter( Boolean ) } );
	};

	const setMeta = ( metaIndex, changes ) => {
		const meta = item.meta.slice();
		meta[ metaIndex ] = { ...meta[ metaIndex ], ...changes };
		update( { meta } );
	};

	return (
		<li className={ `darkify-collection-editor__item ${ open ? 'is-open' : '' }` }>
			<div className="darkify-collection-editor__item-head">
				<button
					type="button"
					className="darkify-collection-editor__item-toggle"
					onClick={ () => setOpen( ! open ) }
					aria-expanded={ open }
				>
					<span className="darkify-collection-editor__item-thumb" aria-hidden="true">
						{ item.image && item.image.url ? (
							<img src={ item.image.url } alt="" />
						) : (
							<span className="darkify-collection-editor__item-thumb-empty" />
						) }
					</span>

					<span className="darkify-collection-editor__item-summary">
						<span className="darkify-collection-editor__item-title">
							{ item.title ||
								sprintf(
									/* translators: %d: item number. */
									__( 'Item %d', 'darkify-util' ),
									index + 1
								) }
						</span>
						<span className="darkify-collection-editor__item-terms">
							{ item.categories.length
								? item.categories.map( labelFor ).join( ', ' )
								: __( 'Uncategorised', 'darkify-util' ) }
						</span>
					</span>
				</button>

				<div className="darkify-collection-editor__item-tools">
					<Button
						size="small"
						variant="tertiary"
						icon="arrow-up-alt2"
						onClick={ () => onMove( -1 ) }
						disabled={ isFirst }
						label={ __( 'Move item up', 'darkify-util' ) }
						showTooltip
					/>
					<Button
						size="small"
						variant="tertiary"
						icon="arrow-down-alt2"
						onClick={ () => onMove( 1 ) }
						disabled={ isLast }
						label={ __( 'Move item down', 'darkify-util' ) }
						showTooltip
					/>
					<Button
						size="small"
						variant="tertiary"
						icon="admin-page"
						onClick={ onDuplicate }
						label={ __( 'Duplicate item', 'darkify-util' ) }
						showTooltip
					/>
					<Button
						size="small"
						variant="tertiary"
						isDestructive
						icon="trash"
						onClick={ onRemove }
						label={ __( 'Remove item', 'darkify-util' ) }
						showTooltip
					/>
				</div>
			</div>

			{ open && (
				<div className="darkify-collection-editor__item-body">
					<div className="darkify-collection-editor__media">
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									update( {
										image: {
											id: media.id,
											url: media.url,
											// The library's own alt text is the
											// right default; overriding it here
											// would quietly fork the two.
											alt: media.alt || '',
										},
									} )
								}
								allowedTypes={ [ 'image' ] }
								value={ item.image ? item.image.id : 0 }
								render={ ( { open: openMedia } ) => (
									<Button
										variant="secondary"
										onClick={ openMedia }
										className="darkify-collection-editor__media-button"
									>
										{ item.image && item.image.url
											? __( 'Replace image', 'darkify-util' )
											: __( 'Set image', 'darkify-util' ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>

						{ item.image && item.image.url && (
							<>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => update( { image: { id: 0, url: '', alt: '' } } ) }
								>
									{ __( 'Remove image', 'darkify-util' ) }
								</Button>

								<TextControl
									__nextHasNoMarginBottom
									label={ __( 'Alt text', 'darkify-util' ) }
									value={ item.image.alt }
									onChange={ ( alt ) => update( { image: { ...item.image, alt } } ) }
									help={ __( 'Leave empty if the image is decorative.', 'darkify-util' ) }
								/>
							</>
						) }
					</div>

					<div className="darkify-collection-editor__fields">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Title', 'darkify-util' ) }
							value={ item.title }
							onChange={ ( title ) => update( { title } ) }
						/>

						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Subtitle', 'darkify-util' ) }
							value={ item.subtitle }
							onChange={ ( subtitle ) => update( { subtitle } ) }
							placeholder={ __( 'Optional', 'darkify-util' ) }
						/>

						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Badge', 'darkify-util' ) }
							value={ item.badge }
							onChange={ ( badge ) => update( { badge } ) }
							help={ __( 'A short flag over the image — “80% off”, “New”.', 'darkify-util' ) }
						/>

						<FormTokenField
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Categories', 'darkify-util' ) }
							value={ item.categories.map( labelFor ) }
							suggestions={ categories.map( ( category ) => category.label ) }
							onChange={ setCategories }
							__experimentalExpandOnFocus
							help={ __(
								'An item can sit in several categories. A new name here becomes a new filter.',
								'darkify-util'
							) }
						/>

						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Description', 'darkify-util' ) }
							value={ item.description }
							onChange={ ( description ) => update( { description } ) }
							rows={ 3 }
						/>

						<div className="darkify-collection-editor__pair">
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Link URL', 'darkify-util' ) }
								type="url"
								value={ item.url }
								onChange={ ( url ) => update( { url } ) }
								placeholder="https://"
							/>

							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Link text', 'darkify-util' ) }
								value={ item.linkLabel }
								onChange={ ( linkLabel ) => update( { linkLabel } ) }
								placeholder={ __( 'Uses the block’s button text', 'darkify-util' ) }
							/>
						</div>

						<div className="darkify-collection-editor__meta">
							<p className="darkify-collection-editor__meta-title">
								{ __( 'Details', 'darkify-util' ) }
							</p>
							<p className="darkify-collection-editor__meta-help">
								{ __(
									'Label and value pairs, listed under the title. This is where anything the other fields do not cover belongs — dates, prices, coupons, stack.',
									'darkify-util'
								) }
							</p>

							{ item.meta.map( ( pair, metaIndex ) => (
								<div className="darkify-collection-editor__meta-row" key={ pair.id || metaIndex }>
									<TextControl
										__nextHasNoMarginBottom
										label={ __( 'Label', 'darkify-util' ) }
										hideLabelFromVision
										value={ pair.label }
										onChange={ ( label ) => setMeta( metaIndex, { label } ) }
										placeholder={ __( 'Label', 'darkify-util' ) }
									/>
									<TextControl
										__nextHasNoMarginBottom
										label={ __( 'Value', 'darkify-util' ) }
										hideLabelFromVision
										value={ pair.value }
										onChange={ ( value ) => setMeta( metaIndex, { value } ) }
										placeholder={ __( 'Value', 'darkify-util' ) }
									/>
									<Button
										size="small"
										variant="tertiary"
										isDestructive
										icon="trash"
										onClick={ () =>
											update( {
												meta: item.meta.filter( ( _pair, i ) => i !== metaIndex ),
											} )
										}
										label={ __( 'Remove detail', 'darkify-util' ) }
										showTooltip
									/>
								</div>
							) ) }

							<Button
								size="small"
								variant="secondary"
								onClick={ () => update( { meta: [ ...item.meta, emptyMeta() ] } ) }
							>
								{ __( 'Add detail', 'darkify-util' ) }
							</Button>
						</div>
					</div>
				</div>
			) }
		</li>
	);
}
