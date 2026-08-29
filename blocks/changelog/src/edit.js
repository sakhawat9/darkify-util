/**
 * The editor for darkify-util/changelog.
 *
 * Two states: the paste panel, and the editable version list you get after
 * converting. The raw text is kept in the `source` attribute either way, so
 * "Edit source" in the toolbar can always take you back to it.
 */

import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import CategoryColors from './components/CategoryColors';
import PastePanel from './components/PastePanel';
import Preview from './components/Preview';
import VersionEditor from './components/VersionEditor';
import { parseChangelog } from './parser';

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @return {Element} Block editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		source,
		versions,
		categories,
		versionsPosition,
		perPage,
		paginationType,
		loadMoreText,
		showDates,
		showBadges,
		showSearch,
		showFilters,
		collapsible,
	} = attributes;

	const hasContent = versions.length > 0;
	const [ pasting, setPasting ] = useState( ! hasContent );
	const [ editing, setEditing ] = useState( false );
	const [ warnings, setWarnings ] = useState( [] );

	const blockProps = useBlockProps( {
		className: 'darkify-changelog-editor',
	} );

	const convert = ( text ) => {
		const parsed = parseChangelog( text );

		setAttributes( {
			source: text,
			versions: parsed.versions,
			// Colours already chosen for a category are kept; only genuinely
			// new categories bring their generated colour with them.
			categories: parsed.categories.map( ( category ) => {
				const existing = categories.find( ( c ) => c.slug === category.slug );
				return existing ? { ...category, color: existing.color } : category;
			} ),
		} );

		setWarnings( parsed.warnings );
		setPasting( false );
		// Converting lands on the preview, not on a form: the point of the
		// button is to see the changelog.
		setEditing( false );
	};

	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="visibility"
						label={ __( 'Preview', 'darkify-util' ) }
						onClick={ () => {
							setPasting( false );
							setEditing( false );
						} }
						isPressed={ ! pasting && ! editing }
						disabled={ ! hasContent }
					/>
					<ToolbarButton
						icon="list-view"
						label={ __( 'Edit versions', 'darkify-util' ) }
						onClick={ () => {
							setPasting( false );
							setEditing( true );
						} }
						isPressed={ editing }
						disabled={ ! hasContent }
					/>
					<ToolbarButton
						icon="edit"
						label={ __( 'Edit source', 'darkify-util' ) }
						onClick={ () => setPasting( true ) }
						isPressed={ pasting }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'darkify-util' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Version list', 'darkify-util' ) }
						value={ versionsPosition }
						options={ [
							{ label: __( 'Right', 'darkify-util' ), value: 'right' },
							{ label: __( 'Left', 'darkify-util' ), value: 'left' },
							{ label: __( 'Hidden', 'darkify-util' ), value: 'none' },
						] }
						onChange={ ( value ) => setAttributes( { versionsPosition: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show dates', 'darkify-util' ) }
						checked={ showDates }
						onChange={ ( value ) => setAttributes( { showDates: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show category badges', 'darkify-util' ) }
						checked={ showBadges }
						onChange={ ( value ) => setAttributes( { showBadges: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show category filter', 'darkify-util' ) }
						help={ __( 'A row of category chips with counts, above the versions.', 'darkify-util' ) }
						checked={ showFilters }
						onChange={ ( value ) => setAttributes( { showFilters: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show search', 'darkify-util' ) }
						help={ __( 'Filters versions and entries in the browser.', 'darkify-util' ) }
						checked={ showSearch }
						onChange={ ( value ) => setAttributes( { showSearch: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Collapsible versions', 'darkify-util' ) }
						help={ __( 'Versions open by default; visitors can fold them away.', 'darkify-util' ) }
						checked={ collapsible }
						onChange={ ( value ) => setAttributes( { collapsible: value } ) }
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
							label={ __( 'Versions per page', 'darkify-util' ) }
							value={ perPage }
							onChange={ ( value ) => setAttributes( { perPage: value || 1 } ) }
							min={ 1 }
							max={ 50 }
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

				<PanelBody title={ __( 'Category colours', 'darkify-util' ) } initialOpen={ false }>
					<CategoryColors
						categories={ categories }
						onChange={ ( value ) => setAttributes( { categories: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ pasting ? (
				<Placeholder
					icon="backup"
					label={ __( 'Darkify Changelog', 'darkify-util' ) }
					instructions={ __(
						'Paste a changelog and it becomes a versioned release history you can edit.',
						'darkify-util'
					) }
					className="darkify-changelog-editor__placeholder"
				>
					<PastePanel
						source={ source }
						hasContent={ hasContent }
						onConvert={ convert }
						onCancel={ () => hasContent && setPasting( false ) }
					/>
				</Placeholder>
			) : (
				<>
					{ warnings.length > 0 && (
						<Notice
							status="warning"
							onRemove={ () => setWarnings( [] ) }
							className="darkify-changelog-editor__notice"
						>
							{ __( 'Some lines could not be read and were left out:', 'darkify-util' ) }
							<ul>
								{ warnings.slice( 0, 5 ).map( ( warning ) => (
									<li key={ warning.line }>
										{ `${ warning.line }: ${ warning.text }` }
									</li>
								) ) }
							</ul>
						</Notice>
					) }

					{ editing ? (
						<VersionEditor
							versions={ versions }
							categories={ categories }
							onChange={ ( value ) => setAttributes( { versions: value } ) }
						/>
					) : (
						<Preview attributes={ attributes } />
					) }
				</>
			) }
		</div>
	);
}
