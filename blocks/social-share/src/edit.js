/**
 * The editor for darkify-util/social-share.
 *
 * There is no editing surface of its own: the block is a row of links, so the
 * canvas shows the server's own rendering of them and every control lives in
 * the inspector. What you see is what publishes, down to the share URLs,
 * because the server builds both.
 */

import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	__experimentalBoxControl as BoxControl,
	PanelBody,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import NetworkList from './components/NetworkList';
import { withKnownNetworks } from './networks';

/**
 * Units offered for padding and margin.
 *
 * px first because that is what these buttons are actually measured in; em and
 * rem follow for a theme that scales with its type.
 */
const SPACING_UNITS = [
	{ value: 'px', label: 'px', default: 0 },
	{ value: 'em', label: 'em', default: 0 },
	{ value: 'rem', label: 'rem', default: 0 },
];

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @return {Element} Block editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		networks,
		targetUrl,
		itemStyle,
		itemShape,
		colorMode,
		showLabels,
		showIcons,
		contentAlign,
		gap,
		iconSize,
		itemRadius,
		itemBackground,
		itemBackgroundHover,
		itemColor,
		itemColorHover,
		itemBorderColor,
		itemBorderColorHover,
		itemBorderWidth,
		itemPadding,
		itemMargin,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'darkify-share-editor',
	} );

	const list = withKnownNetworks( networks );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Networks', 'darkify-util' ) }>
					<NetworkList
						networks={ list }
						showLabels={ showLabels }
						onChange={ ( value ) => setAttributes( { networks: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Button style', 'darkify-util' ) }
						value={ itemStyle }
						options={ [
							{ label: __( 'Brand colour', 'darkify-util' ), value: 'brand' },
							{ label: __( 'Soft', 'darkify-util' ), value: 'soft' },
							{ label: __( 'Outline', 'darkify-util' ), value: 'outline' },
							{ label: __( 'Plain', 'darkify-util' ), value: 'plain' },
						] }
						onChange={ ( value ) => setAttributes( { itemStyle: value } ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Shape', 'darkify-util' ) }
						value={ itemShape }
						options={ [
							{ label: __( 'Rounded', 'darkify-util' ), value: 'rounded' },
							{ label: __( 'Circle / pill', 'darkify-util' ), value: 'round' },
						] }
						onChange={ ( value ) => setAttributes( { itemShape: value } ) }
					/>

					{ /* A pill is already as round as it gets, so the slider
					     would be a control that does nothing. */ }
					{ 'round' !== itemShape && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Corner radius', 'darkify-util' ) }
							value={ itemRadius }
							onChange={ ( value ) => setAttributes( { itemRadius: value ?? 0 } ) }
							min={ 0 }
							max={ 40 }
						/>
					) }

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'darkify-util' ) }
						value={ contentAlign }
						options={ [
							{ label: __( 'Left', 'darkify-util' ), value: 'left' },
							{ label: __( 'Center', 'darkify-util' ), value: 'center' },
							{ label: __( 'Right', 'darkify-util' ), value: 'right' },
						] }
						onChange={ ( value ) => setAttributes( { contentAlign: value } ) }
					/>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Gap between buttons', 'darkify-util' ) }
						value={ gap }
						onChange={ ( value ) => setAttributes( { gap: value ?? 0 } ) }
						min={ 0 }
						max={ 40 }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show icons', 'darkify-util' ) }
						checked={ showIcons }
						onChange={ ( value ) => setAttributes( { showIcons: value } ) }
					/>

					{ showIcons && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Icon size', 'darkify-util' ) }
							value={ iconSize }
							onChange={ ( value ) => setAttributes( { iconSize: value ?? 18 } ) }
							min={ 10 }
							max={ 48 }
						/>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show network names', 'darkify-util' ) }
						help={ __(
							'Off, the buttons are icon only and the name is announced to screen readers.',
							'darkify-util'
						) }
						checked={ showLabels }
						onChange={ ( value ) => setAttributes( { showLabels: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Icon Colours', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Colours', 'darkify-util' ) }
						help={
							'brand' === colorMode
								? __(
										'Each button uses its own network’s colour — Facebook blue, WhatsApp green, and so on.',
										'darkify-util'
								  )
								: __(
										'Every button uses the colours set below. Switch back to brand colours at any time; these are kept.',
										'darkify-util'
								  )
						}
						value={ colorMode }
						options={ [
							{
								label: __( 'Default social media colours', 'darkify-util' ),
								value: 'brand',
							},
							{ label: __( 'Custom colours', 'darkify-util' ), value: 'custom' },
						] }
						onChange={ ( value ) => setAttributes( { colorMode: value } ) }
					/>

					{ /* Structural rather than a colour, so it applies in
					     either mode. */ }
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Border width', 'darkify-util' ) }
						value={ '' === itemBorderWidth ? undefined : parseInt( itemBorderWidth, 10 ) }
						onChange={ ( value ) =>
							setAttributes( {
								itemBorderWidth:
									undefined === value || null === value ? '' : String( value ),
							} )
						}
						min={ 0 }
						max={ 8 }
						allowReset
						resetFallbackValue={ undefined }
					/>
				</PanelBody>

				{ /*
				 * Only shown in custom mode, because in brand mode these have no
				 * effect — PHP does not write them. A panel of controls that
				 * silently do nothing is worse than no panel.
				 *
				 * Each colour is still individually optional: one left unset
				 * falls back to that button's brand colour, so "custom" can mean
				 * one changed colour rather than all six.
				 */ }
				{ 'custom' === colorMode && (
					<PanelColorSettings
						title={ __( 'Custom colours', 'darkify-util' ) }
						initialOpen
						enableAlpha
						colorSettings={ [
							{
								value: itemBackground,
								label: __( 'Background', 'darkify-util' ),
								onChange: ( value ) =>
									setAttributes( { itemBackground: value ?? '' } ),
							},
							{
								value: itemBackgroundHover,
								label: __( 'Background on hover', 'darkify-util' ),
								onChange: ( value ) =>
									setAttributes( { itemBackgroundHover: value ?? '' } ),
							},
							{
								value: itemColor,
								label: __( 'Icon and text', 'darkify-util' ),
								onChange: ( value ) => setAttributes( { itemColor: value ?? '' } ),
							},
							{
								value: itemColorHover,
								label: __( 'Icon and text on hover', 'darkify-util' ),
								onChange: ( value ) =>
									setAttributes( { itemColorHover: value ?? '' } ),
							},
							{
								value: itemBorderColor,
								label: __( 'Border', 'darkify-util' ),
								onChange: ( value ) =>
									setAttributes( { itemBorderColor: value ?? '' } ),
							},
							{
								value: itemBorderColorHover,
								label: __( 'Border on hover', 'darkify-util' ),
								onChange: ( value ) =>
									setAttributes( { itemBorderColorHover: value ?? '' } ),
							},
						] }
					/>
				) }

				<PanelBody title={ __( 'Icon Spacing', 'darkify-util' ) } initialOpen={ false }>
					{ /*
					 * Per-side, and empty means "as designed" — so setting one
					 * side does not quietly zero the other three, and clearing a
					 * side gives the design's value back rather than 0.
					 */ }
					<BoxControl
						__next40pxDefaultSize
						label={ __( 'Padding', 'darkify-util' ) }
						values={ itemPadding }
						units={ SPACING_UNITS }
						onChange={ ( value ) =>
							setAttributes( { itemPadding: emptySides( value ) } )
						}
					/>

					<BoxControl
						__next40pxDefaultSize
						label={ __( 'Margin', 'darkify-util' ) }
						values={ itemMargin }
						units={ SPACING_UNITS }
						onChange={ ( value ) =>
							setAttributes( { itemMargin: emptySides( value ) } )
						}
					/>
				</PanelBody>

				<PanelBody title={ __( 'Share target', 'darkify-util' ) } initialOpen={ false }>
					<TextControl
						__nextHasNoMarginBottom
						type="url"
						label={ __( 'Share a different URL', 'darkify-util' ) }
						help={ __(
							'Leave empty to share the page the block is on.',
							'darkify-util'
						) }
						value={ targetUrl }
						onChange={ ( value ) => setAttributes( { targetUrl: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div className="darkify-share-editor__preview">
				<ServerSideRender
					block="darkify-util/social-share"
					attributes={ attributes }
					EmptyResponsePlaceholder={ () => (
						<p className="darkify-share-editor__hint">
							{ __(
								'No networks switched on — pick at least one in the sidebar.',
								'darkify-util'
							) }
						</p>
					) }
					LoadingResponsePlaceholder={ () => (
						<p className="darkify-share-editor__loading">
							<Spinner />
							{ __( 'Rendering preview…', 'darkify-util' ) }
						</p>
					) }
				/>
			</div>
		</div>
	);
}

/**
 * A box with every unset side stored as '' rather than undefined.
 *
 * BoxControl clears a side by handing back undefined, which would drop the key
 * and leave the saved object a different shape each time. PHP reads four sides,
 * so four sides is what gets saved.
 *
 * @param {Object} box Values from BoxControl.
 * @return {Object} The same box with '' for anything unset.
 */
function emptySides( box ) {
	const sides = [ 'top', 'right', 'bottom', 'left' ];

	return sides.reduce(
		( result, side ) => ( { ...result, [ side ]: box?.[ side ] ?? '' } ),
		{}
	);
}
