/**
 * The editor for darkify-util/collection.
 *
 * Three surfaces, switched from the toolbar: the preview (the server's own
 * render, behaving exactly as it will on the page), the item list, and the
 * category table. Everything that dresses the grid rather than fills it lives in
 * the inspector.
 */

import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

import CategoryManager from './components/CategoryManager';
import ItemsEditor from './components/ItemsEditor';
import Preview from './components/Preview';
import { emptyItem } from './model';

/**
 * Which client id owns which block id.
 *
 * Duplicating a block copies its attributes, block id included, and two
 * collections answering to the same id would page and filter as one. The map
 * spots the copy — same id, different client — and sends it away with a new one.
 *
 * @type {Map<string, string>}
 */
const claimed = new Map();

/**
 * The four designs, and what each one is for.
 *
 * Presentation only: every template renders the same items, through the same
 * filter, search and pagination. Switching one changes how the collection
 * looks and nothing about what is in it.
 *
 * The server keeps the matching list (see Darkify_Util_Collection::templates(),
 * which is filterable); this is the picker's copy of it. A template added by a
 * filter renders on the front end without touching this file — it simply will
 * not appear in the dropdown until it is listed here too.
 */
const TEMPLATES = [
	{
		value: 'default',
		label: __( 'Default — universal cards', 'darkify-util' ),
		help: __(
			'Image, title, details and a button. The all-rounder: roundups, resources, products.',
			'darkify-util'
		),
	},
	{
		value: 'frame',
		label: __( 'Framed — panel and bar', 'darkify-util' ),
		help: __(
			'A dashed image panel over a bar with the title, subtitle and link — nothing else. The picture carries the card.',
			'darkify-util'
		),
	},
	{
		value: 'showcase',
		label: __( 'Showcase — image tiles', 'darkify-util' ),
		help: __(
			'Full-bleed imagery with the title over it. For sites, plugins and portfolios.',
			'darkify-util'
		),
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {string}   props.clientId
 * @return {Element} Block editor UI.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		blockId,
		items,
		categories,
		template,
		columns,
		columnsTablet,
		columnsMobile,
		gap,
		contentAlign,
		showFilters,
		showFilterCounts,
		filterAlign,
		allLabel,
		showSearch,
		searchPlaceholder,
		perPage,
		paginationType,
		loadMoreText,
		showImage,
		imageRatio,
		imageFit,
		showBadge,
		showCategory,
		showSubtitle,
		showMeta,
		showDescription,
		showButton,
		buttonText,
		openInNewTab,
		cardStyle,
		radius,
		hoverEffect,
		titleSize,
		cardBackground,
		cardBorderColor,
		titleColor,
		accentColor,
		emptyText,
	} = attributes;

	const hasItems = items.length > 0;
	const [ view, setView ] = useState( hasItems ? 'preview' : 'items' );

	useEffect( () => {
		const owner = blockId ? claimed.get( blockId ) : undefined;

		if ( ! blockId || ( owner && owner !== clientId ) ) {
			const next = clientId.replace( /-/g, '' ).slice( 0, 12 );
			claimed.set( next, clientId );
			setAttributes( { blockId: next } );
			return;
		}

		claimed.set( blockId, clientId );
	}, [ blockId, clientId, setAttributes ] );

	const blockProps = useBlockProps( { className: 'darkify-collection-editor' } );

	const addCategories = ( created ) => {
		const known = categories.map( ( category ) => category.slug );

		setAttributes( {
			categories: [
				...categories,
				...created.filter( ( category ) => ! known.includes( category.slug ) ),
			],
		} );
	};

	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="visibility"
						label={ __( 'Preview', 'darkify-util' ) }
						onClick={ () => setView( 'preview' ) }
						isPressed={ 'preview' === view }
						disabled={ ! hasItems }
					/>
					<ToolbarButton
						icon="list-view"
						label={ __( 'Edit items', 'darkify-util' ) }
						onClick={ () => setView( 'items' ) }
						isPressed={ 'items' === view }
					/>
					<ToolbarButton
						icon="category"
						label={ __( 'Categories', 'darkify-util' ) }
						onClick={ () => setView( 'categories' ) }
						isPressed={ 'categories' === view }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Template', 'darkify-util' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Design', 'darkify-util' ) }
						value={ template }
						options={ TEMPLATES.map( ( { value, label } ) => ( { value, label } ) ) }
						onChange={ ( value ) => setAttributes( { template: value } ) }
						help={
							( TEMPLATES.find( ( item ) => item.value === template ) || TEMPLATES[ 0 ] )
								.help
						}
					/>

					<p className="darkify-collection-editor__hint">
						{ __(
							'Changes the presentation only. Items, categories, search and pagination stay exactly as they are.',
							'darkify-util'
						) }
					</p>
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'darkify-util' ) }>
					{ /* Every template is a grid; the columns are what shape it. */ }
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'darkify-util' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value || 1 } ) }
						min={ 1 }
						max={ 6 }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Columns on tablet', 'darkify-util' ) }
						value={ columnsTablet }
						onChange={ ( value ) => setAttributes( { columnsTablet: value || 1 } ) }
						min={ 1 }
						max={ 4 }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Columns on mobile', 'darkify-util' ) }
						value={ columnsMobile }
						onChange={ ( value ) => setAttributes( { columnsMobile: value || 1 } ) }
						min={ 1 }
						max={ 2 }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Gap', 'darkify-util' ) }
						value={ gap }
						onChange={ ( value ) => setAttributes( { gap: value } ) }
						min={ 0 }
						max={ 64 }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Text alignment', 'darkify-util' ) }
						value={ contentAlign }
						options={ [
							{ label: __( 'Left', 'darkify-util' ), value: 'left' },
							{ label: __( 'Centre', 'darkify-util' ), value: 'center' },
						] }
						onChange={ ( value ) => setAttributes( { contentAlign: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Filter and search', 'darkify-util' ) } initialOpen={ false }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Category filter', 'darkify-util' ) }
						help={ __( 'A chip per category that has items in it, plus All.', 'darkify-util' ) }
						checked={ showFilters }
						onChange={ ( value ) => setAttributes( { showFilters: value } ) }
					/>

					{ showFilters && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Show counts', 'darkify-util' ) }
								checked={ showFilterCounts }
								onChange={ ( value ) => setAttributes( { showFilterCounts: value } ) }
							/>

							<TextControl
								__nextHasNoMarginBottom
								label={ __( '“All” label', 'darkify-util' ) }
								value={ allLabel }
								onChange={ ( value ) => setAttributes( { allLabel: value } ) }
							/>

							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Bar alignment', 'darkify-util' ) }
								value={ filterAlign }
								options={ [
									{ label: __( 'Left', 'darkify-util' ), value: 'left' },
									{ label: __( 'Centre', 'darkify-util' ), value: 'center' },
									{ label: __( 'Right', 'darkify-util' ), value: 'right' },
								] }
								onChange={ ( value ) => setAttributes( { filterAlign: value } ) }
							/>
						</>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Search box', 'darkify-util' ) }
						help={ __( 'Searches titles, details, descriptions and category names.', 'darkify-util' ) }
						checked={ showSearch }
						onChange={ ( value ) => setAttributes( { showSearch: value } ) }
					/>

					{ showSearch && (
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Search placeholder', 'darkify-util' ) }
							value={ searchPlaceholder }
							onChange={ ( value ) => setAttributes( { searchPlaceholder: value } ) }
						/>
					) }

					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'No results message', 'darkify-util' ) }
						value={ emptyText }
						onChange={ ( value ) => setAttributes( { emptyText: value } ) }
						placeholder={ __( 'No items match your filters.', 'darkify-util' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Pagination', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Type', 'darkify-util' ) }
						value={ paginationType }
						options={ [
							{ label: __( 'Load more', 'darkify-util' ), value: 'load-more' },
							{ label: __( 'Numbered', 'darkify-util' ), value: 'numbered' },
							{ label: __( 'Show all', 'darkify-util' ), value: 'none' },
						] }
						onChange={ ( value ) => setAttributes( { paginationType: value } ) }
					/>

					{ 'none' !== paginationType && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Items per page', 'darkify-util' ) }
							value={ perPage }
							onChange={ ( value ) => setAttributes( { perPage: value || 1 } ) }
							min={ 1 }
							max={ 48 }
						/>
					) }

					{ 'load-more' === paginationType && (
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Button text', 'darkify-util' ) }
							value={ loadMoreText }
							onChange={ ( value ) => setAttributes( { loadMoreText: value } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Card', 'darkify-util' ) } initialOpen={ false }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Image', 'darkify-util' ) }
						checked={ showImage }
						onChange={ ( value ) => setAttributes( { showImage: value } ) }
					/>

					{ showImage && (
						<>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Image shape', 'darkify-util' ) }
								value={ imageRatio }
								options={ [
									{ label: __( 'Landscape 16:9', 'darkify-util' ), value: '16-9' },
									{ label: __( 'Landscape 4:3', 'darkify-util' ), value: '4-3' },
									{ label: __( 'Landscape 3:2', 'darkify-util' ), value: '3-2' },
									{ label: __( 'Square', 'darkify-util' ), value: '1-1' },
									{ label: __( 'Portrait 3:4', 'darkify-util' ), value: '3-4' },
									{ label: __( 'Original', 'darkify-util' ), value: 'auto' },
								] }
								onChange={ ( value ) => setAttributes( { imageRatio: value } ) }
							/>

							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Image fit', 'darkify-util' ) }
								value={ imageFit }
								options={ [
									{ label: __( 'Fill the frame', 'darkify-util' ), value: 'cover' },
									{ label: __( 'Fit inside it', 'darkify-util' ), value: 'contain' },
								] }
								help={
									'contain' === imageFit
										? __( 'Better for logos, which crop badly.', 'darkify-util' )
										: undefined
								}
								onChange={ ( value ) => setAttributes( { imageFit: value } ) }
							/>

							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Badge', 'darkify-util' ) }
								checked={ showBadge }
								onChange={ ( value ) => setAttributes( { showBadge: value } ) }
							/>
						</>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Category labels', 'darkify-util' ) }
						checked={ showCategory }
						onChange={ ( value ) => setAttributes( { showCategory: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Subtitle', 'darkify-util' ) }
						checked={ showSubtitle }
						onChange={ ( value ) => setAttributes( { showSubtitle: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Details', 'darkify-util' ) }
						checked={ showMeta }
						onChange={ ( value ) => setAttributes( { showMeta: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Description', 'darkify-util' ) }
						checked={ showDescription }
						onChange={ ( value ) => setAttributes( { showDescription: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Button', 'darkify-util' ) }
						checked={ showButton }
						onChange={ ( value ) => setAttributes( { showButton: value } ) }
					/>

					{ showButton && (
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Default button text', 'darkify-util' ) }
							value={ buttonText }
							onChange={ ( value ) => setAttributes( { buttonText: value } ) }
							help={ __( 'An item with its own link text uses that instead.', 'darkify-util' ) }
						/>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Open links in a new tab', 'darkify-util' ) }
						checked={ openInNewTab }
						onChange={ ( value ) => setAttributes( { openInNewTab: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Style', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Card style', 'darkify-util' ) }
						value={ cardStyle }
						options={ [
							{ label: __( 'Boxed', 'darkify-util' ), value: 'boxed' },
							{ label: __( 'Flat', 'darkify-util' ), value: 'flat' },
						] }
						onChange={ ( value ) => setAttributes( { cardStyle: value } ) }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Corner radius', 'darkify-util' ) }
						value={ radius }
						onChange={ ( value ) => setAttributes( { radius: value } ) }
						min={ 0 }
						max={ 32 }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Hover effect', 'darkify-util' ) }
						value={ hoverEffect }
						options={ [
							{ label: __( 'Lift', 'darkify-util' ), value: 'lift' },
							{ label: __( 'Zoom the image', 'darkify-util' ), value: 'zoom' },
							{ label: __( 'None', 'darkify-util' ), value: 'none' },
						] }
						onChange={ ( value ) => setAttributes( { hoverEffect: value } ) }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Title size', 'darkify-util' ) }
						value={ titleSize }
						onChange={ ( value ) => setAttributes( { titleSize: value || 0 } ) }
						min={ 0 }
						max={ 40 }
						help={ __( '0 keeps the theme’s own heading size.', 'darkify-util' ) }
					/>
				</PanelBody>

				<PanelColorSettings
					__experimentalIsRenderedInSidebar
					title={ __( 'Colours', 'darkify-util' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: cardBackground,
							onChange: ( value ) => setAttributes( { cardBackground: value || '' } ),
							label: __( 'Card background', 'darkify-util' ),
						},
						{
							value: cardBorderColor,
							onChange: ( value ) => setAttributes( { cardBorderColor: value || '' } ),
							label: __( 'Card border', 'darkify-util' ),
						},
						{
							value: titleColor,
							onChange: ( value ) => setAttributes( { titleColor: value || '' } ),
							label: __( 'Title', 'darkify-util' ),
						},
						{
							value: accentColor,
							onChange: ( value ) => setAttributes( { accentColor: value || '' } ),
							label: __( 'Accent', 'darkify-util' ),
						},
					] }
				>
					<p className="darkify-collection-editor__hint">
						{ __(
							'Left unset, the card follows the page — including the palette Darkify switches to in dark mode.',
							'darkify-util'
						) }
					</p>
				</PanelColorSettings>
			</InspectorControls>

			{ ! hasItems && 'items' === view ? (
				<Placeholder
					icon="grid-view"
					label={ __( 'Darkify Collection', 'darkify-util' ) }
					instructions={ __(
						'A filterable grid of items you write here — a roundup, a showcase, a directory. Nothing is queried from posts.',
						'darkify-util'
					) }
					className="darkify-collection-editor__placeholder"
				>
					<Button
						variant="primary"
						onClick={ () => setAttributes( { items: [ emptyItem() ] } ) }
					>
						{ __( 'Add the first item', 'darkify-util' ) }
					</Button>
				</Placeholder>
			) : (
				<>
					{ 'preview' === view && <Preview attributes={ attributes } /> }

					{ 'items' === view && (
						<ItemsEditor
							items={ items }
							categories={ categories }
							onChange={ ( value ) => setAttributes( { items: value } ) }
							onAddCategories={ addCategories }
						/>
					) }

					{ 'categories' === view && (
						<CategoryManager
							categories={ categories }
							items={ items }
							onChange={ ( value ) => setAttributes( { categories: value } ) }
						/>
					) }
				</>
			) }
		</div>
	);
}
