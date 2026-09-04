/**
 * The editor for darkify-util/ai-summarize.
 *
 * There is no editing surface of its own: the block is a row of links, so the
 * canvas shows the server's own rendering of them and every control lives in
 * the inspector. What you see is what publishes, including the prompt, because
 * the server builds both.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	FontSizePicker,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

import ServiceList from './components/ServiceList';
import { withKnownServices } from './services';

/**
 * Sizes offered for the heading.
 *
 * The block's own list rather than the theme's presets: this is a small label
 * over a row of buttons, and a theme's "Large" is sized for headlines. Any other
 * size can still be typed into the custom field the picker provides.
 */
const HEADING_SIZES = [
	{ name: __( 'Small', 'darkify-util' ), slug: 'small', size: '0.75rem' },
	{ name: __( 'Normal', 'darkify-util' ), slug: 'normal', size: '0.8125rem' },
	{ name: __( 'Medium', 'darkify-util' ), slug: 'medium', size: '1rem' },
	{ name: __( 'Large', 'darkify-util' ), slug: 'large', size: '1.25rem' },
];

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @return {Element} Block editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		title,
		showTitle,
		services,
		prompt,
		targetUrl,
		buttonStyle,
		showIcons,
		align,
		radius,
		titleFontSize,
		titleFontWeight,
		titleLineHeight,
		titleLetterSpacing,
		titleTextTransform,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'darkify-ai-editor',
	} );

	const list = withKnownServices( services );

	/*
	 * An assistant can only summarise a page it can fetch. On a draft or a
	 * private post the buttons are still correct — they just point at a URL
	 * that answers 404 to anyone who is not logged in — and that is worth
	 * saying once here rather than being discovered after publishing.
	 */
	const isPublic = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		if ( ! editor || ! editor.getEditedPostAttribute ) {
			return true;
		}

		const status = editor.getEditedPostAttribute( 'status' );

		return ! status || 'publish' === status;
	}, [] );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Heading', 'darkify-util' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show heading', 'darkify-util' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>

					{ showTitle && (
						<>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Heading text', 'darkify-util' ) }
								value={ title }
								onChange={ ( value ) => setAttributes( { title: value } ) }
							/>

							{ /*
							 * The block's own Typography panel sets the buttons;
							 * these five set the line above them, which is a
							 * separate size and weight by design. Every one of
							 * them resets to empty, which is what puts the
							 * stylesheet's own value back.
							 */ }
							<FontSizePicker
								__nextHasNoMarginBottom
								fontSizes={ HEADING_SIZES }
								value={ titleFontSize }
								withSlider={ false }
								withReset
								onChange={ ( value ) =>
									setAttributes( { titleFontSize: value ?? '' } )
								}
							/>

							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Heading weight', 'darkify-util' ) }
								value={ titleFontWeight }
								options={ [
									{ label: __( 'Default', 'darkify-util' ), value: '' },
									{ label: __( 'Light', 'darkify-util' ), value: '300' },
									{ label: __( 'Regular', 'darkify-util' ), value: '400' },
									{ label: __( 'Medium', 'darkify-util' ), value: '500' },
									{ label: __( 'Semi bold', 'darkify-util' ), value: '600' },
									{ label: __( 'Bold', 'darkify-util' ), value: '700' },
									{ label: __( 'Extra bold', 'darkify-util' ), value: '800' },
								] }
								onChange={ ( value ) => setAttributes( { titleFontWeight: value } ) }
							/>

							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Heading letter case', 'darkify-util' ) }
								value={ titleTextTransform }
								options={ [
									{ label: __( 'Default', 'darkify-util' ), value: '' },
									{ label: __( 'As typed', 'darkify-util' ), value: 'none' },
									{ label: __( 'UPPERCASE', 'darkify-util' ), value: 'uppercase' },
									{ label: __( 'lowercase', 'darkify-util' ), value: 'lowercase' },
									{ label: __( 'Capitalize', 'darkify-util' ), value: 'capitalize' },
								] }
								onChange={ ( value ) =>
									setAttributes( { titleTextTransform: value } )
								}
							/>

							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Heading line height', 'darkify-util' ) }
								value={ titleLineHeight ? parseFloat( titleLineHeight ) : undefined }
								onChange={ ( value ) =>
									setAttributes( {
										titleLineHeight:
											undefined === value || null === value
												? ''
												: String( value ),
									} )
								}
								min={ 0.8 }
								max={ 3 }
								step={ 0.1 }
								allowReset
								resetFallbackValue={ undefined }
							/>

							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Heading letter spacing', 'darkify-util' ) }
								help={ __( 'In em, so it follows the heading size.', 'darkify-util' ) }
								value={
									titleLetterSpacing
										? parseFloat( titleLetterSpacing )
										: undefined
								}
								onChange={ ( value ) =>
									setAttributes( {
										titleLetterSpacing:
											undefined === value || null === value
												? ''
												: `${ value }em`,
									} )
								}
								min={ -0.05 }
								max={ 0.4 }
								step={ 0.01 }
								allowReset
								resetFallbackValue={ undefined }
							/>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Assistants', 'darkify-util' ) }>
					<ServiceList
						services={ list }
						onChange={ ( value ) => setAttributes( { services: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Prompt', 'darkify-util' ) } initialOpen={ false }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Prompt sent to the assistant', 'darkify-util' ) }
						help={ __(
							'{url} becomes this post’s address, {title} its title and {site} the site name.',
							'darkify-util'
						) }
						rows={ 4 }
						value={ prompt }
						onChange={ ( value ) => setAttributes( { prompt: value } ) }
					/>

					<TextControl
						__nextHasNoMarginBottom
						type="url"
						label={ __( 'Summarize a different URL', 'darkify-util' ) }
						help={ __(
							'Leave empty to use the page the block is on.',
							'darkify-util'
						) }
						value={ targetUrl }
						onChange={ ( value ) => setAttributes( { targetUrl: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Button style', 'darkify-util' ) }
						value={ buttonStyle }
						options={ [
							{ label: __( 'Outline', 'darkify-util' ), value: 'outline' },
							{ label: __( 'Soft', 'darkify-util' ), value: 'soft' },
							{ label: __( 'Solid', 'darkify-util' ), value: 'solid' },
							{ label: __( 'Plain', 'darkify-util' ), value: 'plain' },
						] }
						onChange={ ( value ) => setAttributes( { buttonStyle: value } ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'darkify-util' ) }
						value={ align }
						options={ [
							{ label: __( 'Left', 'darkify-util' ), value: 'left' },
							{ label: __( 'Center', 'darkify-util' ), value: 'center' },
							{ label: __( 'Right', 'darkify-util' ), value: 'right' },
						] }
						onChange={ ( value ) => setAttributes( { align: value } ) }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Corner radius', 'darkify-util' ) }
						value={ radius }
						onChange={ ( value ) => setAttributes( { radius: value ?? 0 } ) }
						min={ 0 }
						max={ 40 }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show logos', 'darkify-util' ) }
						checked={ showIcons }
						onChange={ ( value ) => setAttributes( { showIcons: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ ! isPublic && (
				<Notice status="warning" isDismissible={ false } className="darkify-ai-editor__notice">
					{ __(
						'This post is not published yet, so an assistant opening its link will not be able to read it.',
						'darkify-util'
					) }
				</Notice>
			) }

			<div className="darkify-ai-editor__preview">
				<ServerSideRender
					block="darkify-util/ai-summarize"
					attributes={ attributes }
					EmptyResponsePlaceholder={ () => (
						<p className="darkify-ai-editor__hint">
							{ __(
								'No assistants switched on — pick at least one in the sidebar.',
								'darkify-util'
							) }
						</p>
					) }
					LoadingResponsePlaceholder={ () => (
						<p className="darkify-ai-editor__loading">
							<Spinner />
							{ __( 'Rendering preview…', 'darkify-util' ) }
						</p>
					) }
				/>
			</div>
		</div>
	);
}
