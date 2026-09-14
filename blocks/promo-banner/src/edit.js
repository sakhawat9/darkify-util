/**
 * The editor for darkify-util/promo-banner.
 *
 * Unlike the other blocks in this plugin, the canvas is a live React rendering
 * rather than ServerSideRender: the countdown has to tick, and the messages and
 * button label are edited in place on the banner. The markup and class names
 * match templates/promo-banner.php, and both wear style.scss, so the preview is
 * the published banner.
 *
 * The target date is stored as the wall-clock time the author picked, with no
 * offset, and read in the site's timezone (Settings → General) on both sides.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	PanelColorSettings,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	DateTimePicker,
	Dropdown,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { dateI18n, getDate, getSettings } from '@wordpress/date';

import {
	DAY_MS,
	HOUR_MS,
	countdownUnits,
	pad,
	remainingParts,
	resolveDeadline,
	stateFor,
} from './deadline';

/** Which attribute holds each state's message. */
const MESSAGE_ATTRIBUTES = {
	active: 'message',
	urgent: 'urgentMessage',
	expired: 'expiredMessage',
};

/** Colour attribute → the custom property style.scss reads. */
const COLOR_PROPERTIES = {
	bannerBackground: '--darkify-promo-bg',
	bannerColor: '--darkify-promo-color',
	countdownColor: '--darkify-promo-countdown',
	urgentColor: '--darkify-promo-urgent',
	buttonBackground: '--darkify-promo-button-bg',
	buttonBackgroundHover: '--darkify-promo-button-bg-hover',
	buttonColor: '--darkify-promo-button-color',
	buttonColorHover: '--darkify-promo-button-color-hover',
	closeColor: '--darkify-promo-close',
};

const UNIT_LABELS = {
	days: __( 'd', 'darkify-util' ),
	hours: __( 'h', 'darkify-util' ),
	minutes: __( 'm', 'darkify-util' ),
	seconds: __( 's', 'darkify-util' ),
};

const STATE_LABELS = {
	active: __( 'Counting down', 'darkify-util' ),
	urgent: __( 'Urgent', 'darkify-util' ),
	expired: __( 'Ended', 'darkify-util' ),
};

/**
 * The current time, refreshed every second.
 *
 * @return {number} Epoch ms.
 */
function useNow() {
	const [ now, setNow ] = useState( Date.now() );

	useEffect( () => {
		const timer = window.setInterval( () => setNow( Date.now() ), 1000 );

		return () => window.clearInterval( timer );
	}, [] );

	return now;
}

/**
 * The banner's live state, re-rendering the block only when it changes.
 *
 * Kept apart from the ticking countdown so the block — and the RichText the
 * author may be typing in — is not re-rendered every second.
 *
 * @param {number} deadline
 * @param {number} interval
 * @param {number} urgentWithin
 * @return {string} State.
 */
function useLiveState( deadline, interval, urgentWithin ) {
	const compute = () => {
		const now = Date.now();

		return stateFor( resolveDeadline( deadline, interval, now ), now, urgentWithin );
	};

	const [ state, setState ] = useState( compute );

	useEffect( () => {
		setState( compute() );

		const timer = window.setInterval( () => setState( compute() ), 1000 );

		return () => window.clearInterval( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ deadline, interval, urgentWithin ] );

	return state;
}

/**
 * @param {Object}  props
 * @param {number}  props.deadline
 * @param {number}  props.interval
 * @param {boolean} props.showDays
 * @param {boolean} props.showLabels
 * @return {Element} The ticking countdown.
 */
function Countdown( { deadline, interval, showDays, showLabels } ) {
	const now = useNow();
	const parts = remainingParts( resolveDeadline( deadline, interval, now ), now );

	return (
		<span className="darkify-promo__countdown" aria-hidden="true">
			{ countdownUnits( parts, showDays ).map( ( unit, index ) => (
				<Fragment key={ unit.key }>
					{ index > 0 && <span className="darkify-promo__sep">:</span> }
					<span className="darkify-promo__unit">
						<span className="darkify-promo__value">{ pad( unit.value ) }</span>
						{ showLabels && (
							<span className="darkify-promo__label">
								{ UNIT_LABELS[ unit.key ] }
							</span>
						) }
					</span>
				</Fragment>
			) ) }
		</span>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {string}   props.clientId
 * @return {Element} Block editor UI.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		bannerId,
		showButton,
		buttonText,
		buttonUrl,
		openInNewTab,
		targetDate,
		recurring,
		recurringDays,
		urgentHours,
		expiredAction,
		showCountdown,
		showDays,
		countdownLabels,
		urgentPulse,
		dismissible,
		dismissDays,
		position,
		contentAlign,
	} = attributes;

	const [ preview, setPreview ] = useState( 'live' );
	const settings = getSettings();

	/*
	 * A new banner gets an id for its dismissal key and a deadline three days
	 * out, so it arrives counting down instead of reading 00:00:00 — and so no
	 * date is ever written into block.json to go stale.
	 */
	useEffect( () => {
		const initial = {};

		if ( ! bannerId ) {
			initial.bannerId = clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 12 );
		}

		if ( ! targetDate ) {
			initial.targetDate = dateI18n( 'Y-m-d\\TH:00:00', Date.now() + 3 * DAY_MS );
		}

		if ( Object.keys( initial ).length ) {
			setAttributes( initial );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const deadline = targetDate ? getDate( targetDate ).getTime() : 0;
	const interval = recurring ? recurringDays * DAY_MS : 0;
	const liveState = useLiveState( deadline, interval, urgentHours * HOUR_MS );
	const state = 'live' === preview ? liveState : preview;

	const style = {};

	Object.entries( COLOR_PROPERTIES ).forEach( ( [ attribute, property ] ) => {
		if ( attributes[ attribute ] ) {
			style[ property ] = attributes[ attribute ];
		}
	} );

	const blockProps = useBlockProps( {
		className: [
			'darkify-promo',
			'darkify_ignore',
			'darkify-promo-editor',
			`is-state-${ state }`,
			`is-position-${ position }`,
			`is-align-${ contentAlign }`,
			urgentPulse ? 'has-pulse' : '',
		]
			.filter( Boolean )
			.join( ' ' ),
		style,
	} );

	const messageAttribute = MESSAGE_ATTRIBUTES[ state ];
	const timezone = settings.timezone.string || `UTC${ 0 <= Number( settings.timezone.offset ) ? '+' : '' }${ settings.timezone.offset }`;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Countdown', 'darkify-util' ) }>
					<div className="darkify-promo-editor__field">
						<span className="darkify-promo-editor__field-label">
							{ __( 'Ends at', 'darkify-util' ) }
						</span>
						<Dropdown
							popoverProps={ { placement: 'left-start' } }
							renderToggle={ ( { isOpen, onToggle } ) => (
								<Button
									variant="secondary"
									onClick={ onToggle }
									aria-expanded={ isOpen }
								>
									{ targetDate
										? dateI18n( settings.formats.datetime, getDate( targetDate ) )
										: __( 'Pick a date', 'darkify-util' ) }
								</Button>
							) }
							renderContent={ () => (
								<div className="darkify-promo-editor__picker">
									<DateTimePicker
										currentDate={ targetDate || undefined }
										onChange={ ( value ) =>
											setAttributes( { targetDate: value ?? '' } )
										}
										is12Hour={ /a/i.test( settings.formats.time ) }
									/>
								</div>
							) }
						/>
						<p className="darkify-promo-editor__help">
							{ sprintf(
								/* translators: %s: the site's timezone, e.g. Asia/Dhaka or UTC+6. */
								__(
									'In the site timezone (%s). Visitors see the time converted to their own.',
									'darkify-util'
								),
								timezone
							) }
						</p>
						<p className="darkify-promo-editor__help">
							{ sprintf(
								/* translators: %s: countdown state, e.g. Urgent. */
								__( 'Right now: %s', 'darkify-util' ),
								STATE_LABELS[ liveState ]
							) }
						</p>
					</div>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Repeat automatically', 'darkify-util' ) }
						help={ __(
							'When the deadline passes, the countdown restarts to the same time one cycle later. No need to edit the block again.',
							'darkify-util'
						) }
						checked={ recurring }
						onChange={ ( value ) => setAttributes( { recurring: value } ) }
					/>

					{ recurring && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Repeat every (days)', 'darkify-util' ) }
							value={ recurringDays }
							onChange={ ( value ) => setAttributes( { recurringDays: value ?? 7 } ) }
							min={ 1 }
							max={ 60 }
						/>
					) }

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Urgent when this many hours are left', 'darkify-util' ) }
						help={ __(
							'Swaps in the urgent message and colour. 0 turns it off.',
							'darkify-util'
						) }
						value={ urgentHours }
						onChange={ ( value ) => setAttributes( { urgentHours: value ?? 0 } ) }
						min={ 0 }
						max={ 72 }
					/>

					{ ! recurring && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'When it ends', 'darkify-util' ) }
							value={ expiredAction }
							options={ [
								{ label: __( 'Hide the banner', 'darkify-util' ), value: 'hide' },
								{
									label: __( 'Show the ended message', 'darkify-util' ),
									value: 'message',
								},
							] }
							onChange={ ( value ) => setAttributes( { expiredAction: value } ) }
						/>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show countdown', 'darkify-util' ) }
						checked={ showCountdown }
						onChange={ ( value ) => setAttributes( { showCountdown: value } ) }
					/>

					{ showCountdown && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Show days', 'darkify-util' ) }
								help={ __( 'Off, days are counted as hours.', 'darkify-util' ) }
								checked={ showDays }
								onChange={ ( value ) => setAttributes( { showDays: value } ) }
							/>

							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Unit labels', 'darkify-util' ) }
								value={ countdownLabels }
								options={ [
									{ label: __( 'Short (02d : 14h)', 'darkify-util' ), value: 'short' },
									{ label: __( 'None (02 : 14)', 'darkify-util' ), value: 'none' },
								] }
								onChange={ ( value ) => setAttributes( { countdownLabels: value } ) }
							/>

							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Pulse when urgent', 'darkify-util' ) }
								checked={ urgentPulse }
								onChange={ ( value ) => setAttributes( { urgentPulse: value } ) }
							/>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Messages', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Preview', 'darkify-util' ) }
						help={ __(
							'Switch to Urgent or Ended to edit that message on the banner itself.',
							'darkify-util'
						) }
						value={ preview }
						options={ [
							{ label: __( 'Live', 'darkify-util' ), value: 'live' },
							{ label: __( 'Counting down', 'darkify-util' ), value: 'active' },
							{ label: __( 'Urgent', 'darkify-util' ), value: 'urgent' },
							{ label: __( 'Ended', 'darkify-util' ), value: 'expired' },
						] }
						onChange={ setPreview }
					/>
					<p className="darkify-promo-editor__help">
						{ __(
							'Placeholders: {remaining} (e.g. “5 hours”), {date} and {time} of the deadline — filled in on the page.',
							'darkify-util'
						) }
					</p>
				</PanelBody>

				<PanelBody title={ __( 'Button', 'darkify-util' ) } initialOpen={ false }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show button', 'darkify-util' ) }
						checked={ showButton }
						onChange={ ( value ) => setAttributes( { showButton: value } ) }
					/>

					{ showButton && (
						<>
							<TextControl
								__nextHasNoMarginBottom
								type="url"
								label={ __( 'Link', 'darkify-util' ) }
								value={ buttonUrl }
								onChange={ ( value ) => setAttributes( { buttonUrl: value } ) }
							/>

							{ ! buttonUrl && (
								<Notice status="warning" isDismissible={ false }>
									{ __( 'The button is not shown on the page until it has a link.', 'darkify-util' ) }
								</Notice>
							) }

							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Open in a new tab', 'darkify-util' ) }
								checked={ openInNewTab }
								onChange={ ( value ) => setAttributes( { openInNewTab: value } ) }
							/>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Behaviour', 'darkify-util' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Position', 'darkify-util' ) }
						help={
							'sticky' === position
								? __(
										'Sticks to the top while its container scrolls — place it at the top of the header or the page.',
										'darkify-util'
								  )
								: undefined
						}
						value={ position }
						options={ [
							{ label: __( 'In the flow of the page', 'darkify-util' ), value: 'inline' },
							{ label: __( 'Sticky at the top', 'darkify-util' ), value: 'sticky' },
							{ label: __( 'Fixed to the bottom', 'darkify-util' ), value: 'fixed-bottom' },
						] }
						onChange={ ( value ) => setAttributes( { position: value } ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Content alignment', 'darkify-util' ) }
						value={ contentAlign }
						options={ [
							{ label: __( 'Center', 'darkify-util' ), value: 'center' },
							{ label: __( 'Left', 'darkify-util' ), value: 'left' },
						] }
						onChange={ ( value ) => setAttributes( { contentAlign: value } ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Can be dismissed', 'darkify-util' ) }
						checked={ dismissible }
						onChange={ ( value ) => setAttributes( { dismissible: value } ) }
					/>

					{ dismissible && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Stay dismissed for (days)', 'darkify-util' ) }
							help={ __(
								'0 brings it back on the next page load. A new deadline always shows again.',
								'darkify-util'
							) }
							value={ dismissDays }
							onChange={ ( value ) => setAttributes( { dismissDays: value ?? 0 } ) }
							min={ 0 }
							max={ 60 }
						/>
					) }
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Colours', 'darkify-util' ) }
					initialOpen={ false }
					enableAlpha
					colorSettings={ [
						[ 'bannerBackground', __( 'Background', 'darkify-util' ) ],
						[ 'bannerColor', __( 'Text', 'darkify-util' ) ],
						[ 'countdownColor', __( 'Countdown', 'darkify-util' ) ],
						[ 'urgentColor', __( 'Countdown when urgent', 'darkify-util' ) ],
						[ 'buttonBackground', __( 'Button background', 'darkify-util' ) ],
						[ 'buttonBackgroundHover', __( 'Button background on hover', 'darkify-util' ) ],
						[ 'buttonColor', __( 'Button text', 'darkify-util' ) ],
						[ 'buttonColorHover', __( 'Button text on hover', 'darkify-util' ) ],
						[ 'closeColor', __( 'Close icon', 'darkify-util' ) ],
					].map( ( [ name, label ] ) => ( {
						label,
						value: attributes[ name ],
						onChange: ( value ) => setAttributes( { [ name ]: value ?? '' } ),
					} ) ) }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="darkify-promo__inner">
					<p className="darkify-promo__message">
						<RichText
							key={ messageAttribute }
							tagName="span"
							className="darkify-promo__text"
							value={ attributes[ messageAttribute ] }
							onChange={ ( value ) => setAttributes( { [ messageAttribute ]: value } ) }
							allowedFormats={ [ 'core/bold', 'core/italic', 'core/link', 'core/strikethrough' ] }
							placeholder={
								'urgent' === state
									? __( 'Urgent message — empty uses the main one', 'darkify-util' )
									: __( 'Write the announcement…', 'darkify-util' )
							}
						/>
					</p>

					{ showCountdown && deadline > 0 && 'expired' !== state && (
						<Countdown
							deadline={ deadline }
							interval={ interval }
							showDays={ showDays }
							showLabels={ 'short' === countdownLabels }
						/>
					) }

					{ showButton && 'expired' !== state && (
						<RichText
							tagName="span"
							className="darkify-promo__button"
							value={ buttonText }
							onChange={ ( value ) => setAttributes( { buttonText: value } ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
							placeholder={ __( 'Button text', 'darkify-util' ) }
						/>
					) }
				</div>

				{ dismissible && (
					<span className="darkify-promo__close" aria-hidden="true">
						<svg viewBox="0 0 16 16" focusable="false">
							<path
								d="M3.5 3.5l9 9m0-9l-9 9"
								stroke="currentColor"
								strokeWidth="1.5"
								strokeLinecap="round"
							/>
						</svg>
					</span>
				) }
			</div>
		</>
	);
}
