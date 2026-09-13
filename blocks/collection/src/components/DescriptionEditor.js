/**
 * The item description editor.
 *
 * A small WYSIWYG in the shape of WordPress's classic editor — media, block
 * format, bold, italic, underline, lists, quote, link, clear formatting, and a
 * Code tab for the HTML underneath. Built on contenteditable rather than
 * TinyMCE because it lives inside the block, in the editor's iframe, where
 * TinyMCE does not; and rather than RichText because RichText cannot hold
 * lists, headings or more than one paragraph.
 *
 * The DOM is the source of truth while typing. The attribute is only written
 * back into it when it changes from outside — switching back from the Code tab,
 * undo — so the caret never jumps.
 */

import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button, CheckboxControl, Popover, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

import { CARET, cleanHtml, clipboardToHtml, descriptionToHtml, escapeHtml } from '../description';

const FORMATS = [
	{ value: 'p', label: __( 'Paragraph', 'darkify-util' ) },
	{ value: 'h2', label: __( 'Heading 2', 'darkify-util' ) },
	{ value: 'h3', label: __( 'Heading 3', 'darkify-util' ) },
	{ value: 'h4', label: __( 'Heading 4', 'darkify-util' ) },
	{ value: 'h5', label: __( 'Heading 5', 'darkify-util' ) },
	{ value: 'h6', label: __( 'Heading 6', 'darkify-util' ) },
	{ value: 'pre', label: __( 'Preformatted', 'darkify-util' ) },
];

/**
 * @param {Object}  props
 * @param {Element} props.children SVG content.
 * @return {Element} Icon.
 */
const Icon = ( { children } ) => (
	<svg
		viewBox="0 0 24 24"
		width="18"
		height="18"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
	>
		{ children }
	</svg>
);

/** Commands whose result is re-cleaned into the DOM straight away. */
const STRUCTURAL = [ 'formatBlock', 'insertUnorderedList', 'insertOrderedList', 'insertHTML' ];

/** Blocks a paste can end in, where the caret should land at the end of. */
const BLOCK_END = /^(P|H[1-6]|UL|OL|LI|BLOCKQUOTE|PRE|TABLE|TBODY|THEAD|TFOOT|TR|TD|TH)$/;

/** Keeps the editor's selection when a toolbar control is pressed. */
const keepSelection = ( event ) => event.preventDefault();

/**
 * @param {Object}   props
 * @param {string}   props.label    Accessible name.
 * @param {string}   props.value    Stored description.
 * @param {Function} props.onChange Called with the new HTML.
 * @return {Element} Editor.
 */
export default function DescriptionEditor( { label, value, onChange } ) {
	const editable = useRef( null );
	const linkButton = useRef( null );
	const range = useRef( null );
	const lastHtml = useRef( null );

	const [ mode, setMode ] = useState( 'visual' );
	const [ code, setCode ] = useState( '' );
	const [ active, setActive ] = useState( {} );
	const [ linkOpen, setLinkOpen ] = useState( false );
	const [ linkUrl, setLinkUrl ] = useState( '' );
	const [ linkNewTab, setLinkNewTab ] = useState( false );

	const html = descriptionToHtml( value );

	useEffect( () => {
		const node = editable.current;

		if ( node && html !== lastHtml.current ) {
			node.innerHTML = html;
			lastHtml.current = html;
		}
	}, [ html ] );

	/* ------------------------------------------------------------------ */
	/* Selection                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * The live selection while it is in the editor, else the last one saved —
	 * the dropdown, the link popover and the media modal all take focus away.
	 *
	 * @return {Range|null} Range.
	 */
	const currentRange = () => {
		const node = editable.current;

		if ( ! node ) {
			return null;
		}

		const selection = node.ownerDocument.getSelection();

		if ( selection && selection.rangeCount && node.contains( selection.getRangeAt( 0 ).commonAncestorContainer ) ) {
			return selection.getRangeAt( 0 );
		}

		return range.current;
	};

	const closest = ( selector ) => {
		const node = editable.current;
		const current = currentRange();
		let element = current ? current.startContainer : null;

		if ( element && 3 === element.nodeType ) {
			element = element.parentNode;
		}

		const found = element && element.closest ? element.closest( selector ) : null;

		return found && node && node.contains( found ) && found !== node ? found : null;
	};

	const refreshActive = () => {
		const node = editable.current;

		if ( ! node ) {
			return;
		}

		const doc = node.ownerDocument;
		const block = closest( 'p,h1,h2,h3,h4,h5,h6,pre' );

		// At a caret the command state is the truth — it knows about a toggle
		// pressed but not yet typed — except in a heading, which is bold by its
		// own style and should not light Bold unless the markup says so. Over a
		// selection, the markup decides.
		const inHeading = Boolean( block && block.matches( 'h1,h2,h3,h4,h5,h6' ) );
		const state = ( selector, command ) => {
			if ( doc.getSelection().isCollapsed ) {
				return doc.queryCommandState( command ) && ( ! inHeading || Boolean( closest( selector ) ) );
			}

			return Boolean( closest( selector ) );
		};

		setActive( {
			bold: state( 'strong,b', 'bold' ),
			italic: state( 'em,i', 'italic' ),
			underline: state( 'u', 'underline' ),
			ul: Boolean( closest( 'ul' ) ),
			ol: Boolean( closest( 'ol' ) ),
			quote: Boolean( closest( 'blockquote' ) ),
			link: Boolean( closest( 'a' ) ),
			block: block ? block.tagName.toLowerCase() : 'p',
		} );
	};

	useEffect( () => {
		const node = editable.current;

		if ( ! node ) {
			return undefined;
		}

		const doc = node.ownerDocument;

		const onSelection = () => {
			const selection = doc.getSelection();

			if ( ! selection || ! selection.rangeCount ) {
				return;
			}

			const current = selection.getRangeAt( 0 );

			if ( node.contains( current.commonAncestorContainer ) ) {
				range.current = current.cloneRange();
				refreshActive();
			}
		};

		doc.addEventListener( 'selectionchange', onSelection );

		return () => doc.removeEventListener( 'selectionchange', onSelection );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	/** Put focus and the last selection back into the editor. */
	const restore = () => {
		const node = editable.current;
		const doc = node.ownerDocument;
		const selection = doc.getSelection();

		// Still focused (toolbar buttons never take focus): the live selection
		// is the truth, and the saved copy may be a keystroke behind it.
		if (
			doc.activeElement === node &&
			selection.rangeCount &&
			node.contains( selection.getRangeAt( 0 ).commonAncestorContainer )
		) {
			return;
		}

		node.focus( { preventScroll: true } );
		selection.removeAllRanges();

		if ( range.current && node.contains( range.current.commonAncestorContainer ) ) {
			selection.addRange( range.current );
		} else {
			const end = doc.createRange();
			end.selectNodeContents( node );
			end.collapse( false );
			selection.addRange( end );
		}
	};

	/* ------------------------------------------------------------------ */
	/* Writing                                                            */
	/* ------------------------------------------------------------------ */

	const emit = () => {
		const clean = descriptionToHtml( cleanHtml( editable.current.innerHTML ) );

		lastHtml.current = clean;
		onChange( clean );
	};

	/**
	 * Rebuild the editor's DOM from its cleaned HTML, keeping the selection.
	 *
	 * execCommand happily nests a list inside a paragraph or leaves styled spans
	 * about. The stored HTML is cleaned either way, but if the DOM being edited
	 * kept that shape the next command would build on it — so after each toolbar
	 * action the DOM is replaced with what will actually be saved, with markers
	 * standing in for the selection across the swap.
	 */
	const normalize = () => {
		const node = editable.current;
		const doc = node.ownerDocument;
		const selection = doc.getSelection();

		const marker = ( which ) => {
			const span = doc.createElement( 'span' );
			span.setAttribute( CARET, which );
			return span;
		};

		if ( selection.rangeCount && node.contains( selection.getRangeAt( 0 ).commonAncestorContainer ) ) {
			const current = selection.getRangeAt( 0 );
			const collapsed = current.collapsed;
			const end = current.cloneRange();

			end.collapse( false );
			end.insertNode( marker( 'end' ) );

			if ( ! collapsed ) {
				const start = current.cloneRange();
				start.collapse( true );
				start.insertNode( marker( 'start' ) );
			}
		}

		node.innerHTML = cleanHtml( node.innerHTML, { keepCaret: true } );

		const place = doc.createRange();
		const endMarker = node.querySelector( `[${ CARET }="end"]` );
		const startMarker = node.querySelector( `[${ CARET }="start"]` );

		if ( ! node.textContent.trim() && ! node.querySelector( 'img' ) ) {
			node.innerHTML = '<p><br></p>';
			place.setStart( node.firstChild, 0 );
			place.collapse( true );
		} else if ( endMarker ) {
			place.setStartBefore( startMarker || endMarker );
			place.setEndBefore( endMarker );

			const block = endMarker.parentNode;

			if ( startMarker ) {
				startMarker.remove();
			}
			endMarker.remove();

			// A caret left in an empty line needs something to sit on.
			if ( block !== node && ! block.textContent.trim() && ! block.querySelector( 'img,br' ) ) {
				block.appendChild( doc.createElement( 'br' ) );
				place.setStart( block, 0 );
				place.collapse( true );
			}
		} else {
			place.selectNodeContents( node );
			place.collapse( false );
		}

		selection.removeAllRanges();
		selection.addRange( place );
		range.current = place.cloneRange();
	};

	const exec = ( command, argument = null ) => {
		restore();

		editable.current.ownerDocument.execCommand( command, false, argument );

		// Only commands that move blocks around can nest them badly. Rebuilding
		// after bold or italic would also drop the style the browser holds for
		// the next character typed at a collapsed caret.
		if ( STRUCTURAL.includes( command ) ) {
			normalize();
		}

		emit();
		refreshActive();
	};

	const toggleQuote = () => {
		const quote = closest( 'blockquote' );

		if ( ! quote ) {
			exec( 'formatBlock', '<blockquote>' );
			return;
		}

		const doc = quote.ownerDocument;

		if ( ! quote.querySelector( 'p,h1,h2,h3,h4,h5,h6,ul,ol,pre' ) ) {
			const paragraph = doc.createElement( 'p' );
			while ( quote.firstChild ) {
				paragraph.appendChild( quote.firstChild );
			}
			quote.appendChild( paragraph );
		}

		quote.replaceWith( ...Array.from( quote.childNodes ) );
		normalize();
		emit();
		refreshActive();
	};

	const onFocus = () => {
		const node = editable.current;
		const doc = node.ownerDocument;

		// Set here, once, not before each command: any execCommand clears the
		// style Chrome holds for the next character, so Bold pressed at a caret
		// would never switch off again.
		doc.execCommand( 'defaultParagraphSeparator', false, 'p' );
		doc.execCommand( 'styleWithCSS', false, false );

		// Start inside a paragraph, so the first line typed is one.
		if ( ! node.innerHTML.trim() ) {
			node.innerHTML = '<p><br></p>';

			const start = doc.createRange();
			start.setStart( node.firstChild, 0 );
			start.collapse( true );

			const selection = doc.getSelection();
			selection.removeAllRanges();
			selection.addRange( start );
		}
	};

	const onBlur = () => {
		if ( '' === lastHtml.current ) {
			editable.current.innerHTML = '';
		}
	};

	/**
	 * Insert HTML at the selection.
	 *
	 * Through the Range API rather than execCommand('insertHTML'): Chrome's
	 * version re-styles what it inserts to match its surroundings, turning
	 * <strong> into styled spans and lists into something else, which is the
	 * opposite of a faithful paste.
	 *
	 * @param {string} markup Clean HTML.
	 */
	const insertHtml = ( markup ) => {
		restore();

		const node = editable.current;
		const doc = node.ownerDocument;
		const selection = doc.getSelection();
		const target = selection.getRangeAt( 0 );
		const template = doc.createElement( 'template' );

		template.innerHTML = markup;

		let last = template.content.lastChild;

		target.deleteContents();
		target.insertNode( template.content );

		if ( last ) {
			const after = doc.createRange();

			if ( 1 === last.nodeType && BLOCK_END.test( last.tagName ) ) {
				// Finish inside the last block — the end of the last list item,
				// say — so typing carries on from where the paste stopped.
				while ( last.lastElementChild && BLOCK_END.test( last.lastElementChild.tagName ) ) {
					last = last.lastElementChild;
				}
				after.selectNodeContents( last );
				after.collapse( false );
			} else {
				after.setStartAfter( last );
				after.collapse( true );
			}

			selection.removeAllRanges();
			selection.addRange( after );
		}

		normalize();
		emit();
	};

	const onPaste = ( event ) => {
		const data = event.clipboardData;

		if ( ! data ) {
			return;
		}

		// Always ours: left to bubble, the block editor would read the same
		// clipboard as blocks and insert them next to this one.
		event.preventDefault();

		const insert = clipboardToHtml( data.getData( 'text/html' ), data.getData( 'text/plain' ) );

		if ( insert ) {
			insertHtml( insert );
		}
	};

	const onKeyDown = ( event ) => {
		if ( ( event.metaKey || event.ctrlKey ) && 'k' === event.key.toLowerCase() ) {
			event.preventDefault();
			event.stopPropagation();
			openLink();
		}
	};

	const insertMedia = ( media ) => {
		restore();

		const isImage = 'image' === media.type;
		const src =
			isImage && media.sizes && media.sizes.large ? media.sizes.large.url : media.url;

		const markup = isImage
			? `<img src="${ escapeHtml( src ) }" alt="${ escapeHtml( media.alt || '' ) }" class="wp-image-${ parseInt( media.id, 10 ) || 0 }">`
			: `<a href="${ escapeHtml( media.url ) }">${ escapeHtml( media.title || media.filename || media.url ) }</a>`;

		insertHtml( markup );
	};

	/* ------------------------------------------------------------------ */
	/* Links                                                              */
	/* ------------------------------------------------------------------ */

	const openLink = () => {
		const anchor = closest( 'a' );

		setLinkUrl( anchor ? anchor.getAttribute( 'href' ) || '' : '' );
		setLinkNewTab( anchor ? '_blank' === anchor.getAttribute( 'target' ) : false );
		setLinkOpen( true );
	};

	const setTarget = ( anchors ) => {
		anchors.forEach( ( anchor ) => {
			if ( linkNewTab ) {
				anchor.setAttribute( 'target', '_blank' );
				anchor.setAttribute( 'rel', 'noopener' );
			} else {
				anchor.removeAttribute( 'target' );
				anchor.removeAttribute( 'rel' );
			}
		} );
	};

	const removeLink = () => {
		setLinkOpen( false );

		const anchor = closest( 'a' );

		restore();

		if ( anchor ) {
			const doc = anchor.ownerDocument;
			const whole = doc.createRange();
			whole.selectNodeContents( anchor );

			const selection = doc.getSelection();
			selection.removeAllRanges();
			selection.addRange( whole );
		}

		exec( 'unlink' );
	};

	const applyLink = ( event ) => {
		event.preventDefault();

		const url = linkUrl.trim();

		if ( ! url ) {
			removeLink();
			return;
		}

		setLinkOpen( false );

		const node = editable.current;
		const doc = node.ownerDocument;
		const existing = closest( 'a' );

		restore();

		if ( existing ) {
			existing.setAttribute( 'href', url );
			setTarget( [ existing ] );
		} else if ( doc.getSelection().isCollapsed ) {
			doc.execCommand(
				'insertHTML',
				false,
				`<a href="${ escapeHtml( url ) }" data-dkc-new="1">${ escapeHtml( url ) }</a>`
			);

			const created = Array.from( node.querySelectorAll( '[data-dkc-new]' ) );
			created.forEach( ( anchor ) => anchor.removeAttribute( 'data-dkc-new' ) );
			setTarget( created );
		} else {
			doc.execCommand( 'createLink', false, url );

			const selection = doc.getSelection();
			setTarget(
				Array.from( node.querySelectorAll( 'a' ) ).filter(
					( anchor ) =>
						anchor.getAttribute( 'href' ) === url && selection.containsNode( anchor, true )
				)
			);
		}

		emit();
		refreshActive();
	};

	/* ------------------------------------------------------------------ */
	/* Modes                                                              */
	/* ------------------------------------------------------------------ */

	const showCode = () => {
		setCode( html.replace( /(<\/(?:p|h[1-6]|ul|ol|li|blockquote|pre)>)(?!\n)/gi, '$1\n' ).trim() );
		setLinkOpen( false );
		setMode( 'code' );
	};

	const button = ( key, text, icon, onClick, extra = {} ) => (
		<button
			type="button"
			className={ `darkify-collection-editor__wysiwyg-button${ active[ key ] ? ' is-active' : '' }` }
			aria-label={ text }
			aria-pressed={ Boolean( active[ key ] ) }
			title={ text }
			onMouseDown={ keepSelection }
			onClick={ onClick }
			disabled={ 'visual' !== mode }
			{ ...extra }
		>
			<Icon>{ icon }</Icon>
		</button>
	);

	return (
		<div className="darkify-collection-editor__wysiwyg">
			<div className="darkify-collection-editor__wysiwyg-toolbar">
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ insertMedia }
						render={ ( { open } ) => (
							<button
								type="button"
								className="darkify-collection-editor__wysiwyg-media"
								onMouseDown={ keepSelection }
								onClick={ open }
								disabled={ 'visual' !== mode }
							>
								<Icon>
									<path d="M16 5h6M19 2v6" />
									<path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5" />
									<path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21" />
									<circle cx="9" cy="9" r="2" />
								</Icon>
								{ __( 'Add Media', 'darkify-util' ) }
							</button>
						) }
					/>
				</MediaUploadCheck>

				<select
					className="darkify-collection-editor__wysiwyg-format"
					aria-label={ __( 'Text format', 'darkify-util' ) }
					value={ active.block || 'p' }
					onChange={ ( event ) => exec( 'formatBlock', `<${ event.target.value }>` ) }
					disabled={ 'visual' !== mode }
				>
					{ FORMATS.map( ( format ) => (
						<option key={ format.value } value={ format.value }>
							{ format.label }
						</option>
					) ) }
				</select>

				<span className="darkify-collection-editor__wysiwyg-group">
					{ button(
						'bold',
						__( 'Bold', 'darkify-util' ),
						<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8" />,
						() => exec( 'bold' )
					) }
					{ button(
						'italic',
						__( 'Italic', 'darkify-util' ),
						<path d="M19 4h-9M14 20H5M15 4 9 20" />,
						() => exec( 'italic' )
					) }
					{ button(
						'underline',
						__( 'Underline', 'darkify-util' ),
						<path d="M6 4v6a6 6 0 0 0 12 0V4M4 20h16" />,
						() => exec( 'underline' )
					) }
				</span>

				<span className="darkify-collection-editor__wysiwyg-group">
					{ button(
						'ul',
						__( 'Bulleted list', 'darkify-util' ),
						<path d="M3 6h.01M3 12h.01M3 18h.01M8 6h13M8 12h13M8 18h13" />,
						() => exec( 'insertUnorderedList' )
					) }
					{ button(
						'ol',
						__( 'Numbered list', 'darkify-util' ),
						<path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" />,
						() => exec( 'insertOrderedList' )
					) }
					{ button(
						'quote',
						__( 'Quote', 'darkify-util' ),
						<>
							<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z" />
							<path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z" />
						</>,
						toggleQuote
					) }
					{ button(
						'link',
						__( 'Link', 'darkify-util' ),
						<>
							<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
							<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
						</>,
						openLink,
						{ ref: linkButton }
					) }
					{ button(
						'clear',
						__( 'Clear formatting', 'darkify-util' ),
						<path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21M22 21H7M5 11l9 9" />,
						() => {
							exec( 'removeFormat' );
							exec( 'unlink' );
						}
					) }
				</span>

				<span className="darkify-collection-editor__wysiwyg-modes" role="group" aria-label={ __( 'Editor mode', 'darkify-util' ) }>
					<button
						type="button"
						className={ 'visual' === mode ? 'is-active' : '' }
						aria-pressed={ 'visual' === mode }
						onClick={ () => setMode( 'visual' ) }
					>
						{ __( 'Visual', 'darkify-util' ) }
					</button>
					<button
						type="button"
						className={ 'code' === mode ? 'is-active' : '' }
						aria-pressed={ 'code' === mode }
						onClick={ showCode }
					>
						{ __( 'Code', 'darkify-util' ) }
					</button>
				</span>
			</div>

			<div
				ref={ editable }
				className={ `darkify-collection-editor__wysiwyg-content${ html ? '' : ' is-empty' }` }
				contentEditable
				suppressContentEditableWarning
				role="textbox"
				aria-multiline="true"
				aria-label={ label }
				data-placeholder={ __( 'Write a description…', 'darkify-util' ) }
				hidden={ 'visual' !== mode }
				onInput={ emit }
				onFocus={ onFocus }
				onBlur={ onBlur }
				onPaste={ onPaste }
				onKeyDown={ onKeyDown }
			/>

			{ 'code' === mode && (
				<textarea
					className="darkify-collection-editor__wysiwyg-code"
					aria-label={ label }
					spellCheck={ false }
					value={ code }
					onChange={ ( event ) => {
						setCode( event.target.value );
						onChange( event.target.value );
					} }
				/>
			) }

			{ linkOpen && (
				<Popover
					anchor={ linkButton.current }
					placement="bottom-start"
					onClose={ () => setLinkOpen( false ) }
					focusOnMount="firstElement"
				>
					<form className="darkify-collection-editor__wysiwyg-link" onSubmit={ applyLink }>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'URL', 'darkify-util' ) }
							type="url"
							value={ linkUrl }
							onChange={ setLinkUrl }
							placeholder="https://"
						/>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Open in new tab', 'darkify-util' ) }
							checked={ linkNewTab }
							onChange={ setLinkNewTab }
						/>
						<div className="darkify-collection-editor__wysiwyg-link-actions">
							{ active.link && (
								<Button variant="tertiary" isDestructive onClick={ removeLink }>
									{ __( 'Remove link', 'darkify-util' ) }
								</Button>
							) }
							<Button variant="primary" type="submit">
								{ __( 'Apply', 'darkify-util' ) }
							</Button>
						</div>
					</form>
				</Popover>
			) }
		</div>
	);
}
