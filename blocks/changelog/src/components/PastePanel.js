/**
 * The paste step.
 *
 * Mirrors the flow of the block this replaces — paste a changelog, press the
 * button, get a structured list — but keeps the raw text afterwards so the
 * "Edit source" toolbar button can bring it back.
 */

import { __ } from '@wordpress/i18n';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * @param {Object}   props
 * @param {string}   props.source     Current raw changelog text.
 * @param {boolean}  props.hasContent Whether parsed versions already exist.
 * @param {Function} props.onConvert  Called with the text to parse.
 * @param {Function} props.onCancel   Called when the visitor backs out.
 * @return {Element} Paste panel.
 */
export default function PastePanel( { source, hasContent, onConvert, onCancel } ) {
	const [ text, setText ] = useState( source || '' );
	const [ confirming, setConfirming ] = useState( false );

	const convert = () => {
		// Re-parsing throws away anything edited by hand since the last parse,
		// so it asks first — but only when there is something to lose.
		if ( hasContent && ! confirming ) {
			setConfirming( true );
			return;
		}

		onConvert( text );
	};

	return (
		<div className="darkify-changelog-editor__paste">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Paste your changelog here', 'darkify-util' ) }
				help={ __(
					'Version headings such as "= 2.1.0 – 25 August 2026 =" and entries such as "* Fixed: …". Bullets and category prefixes are both optional.',
					'darkify-util'
				) }
				value={ text }
				onChange={ setText }
				rows={ 14 }
				className="darkify-changelog-editor__textarea"
			/>

			{ confirming && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="darkify-changelog-editor__notice"
				>
					{ __(
						'Converting again replaces the versions below, including any edits made by hand. Convert anyway?',
						'darkify-util'
					) }
				</Notice>
			) }

			<div className="darkify-changelog-editor__actions">
				<Button variant="primary" onClick={ convert } disabled={ ! text.trim() }>
					{ confirming
						? __( 'Yes, replace the changelog', 'darkify-util' )
						: __( 'Convert to changelog', 'darkify-util' ) }
				</Button>

				<Button
					variant="tertiary"
					onClick={ () => {
						setConfirming( false );
						onCancel();
					} }
				>
					{ __( 'Cancel', 'darkify-util' ) }
				</Button>
			</div>
		</div>
	);
}
