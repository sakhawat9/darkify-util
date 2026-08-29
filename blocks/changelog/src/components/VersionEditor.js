/**
 * The editable version list: the block's main editor surface once a changelog
 * has been parsed.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';

import EntryRow from './EntryRow';
import { emptyEntry, emptyVersion, parseDate } from '../parser';

/**
 * @param {Object}   props
 * @param {Array}    props.versions   Versions.
 * @param {Array}    props.categories Available categories.
 * @param {Function} props.onChange   Called with the updated versions array.
 * @return {Element} Version editor.
 */
export default function VersionEditor( { versions, categories, onChange } ) {
	const replaceAt = ( index, version ) => {
		const next = versions.slice();
		next[ index ] = version;
		onChange( next );
	};

	const move = ( index, delta ) => {
		const target = index + delta;

		if ( target < 0 || target >= versions.length ) {
			return;
		}

		const next = versions.slice();
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	const remove = ( index ) => {
		onChange( versions.filter( ( _version, i ) => i !== index ) );
	};

	return (
		<div className="darkify-changelog-editor__versions">
			{ versions.map( ( version, index ) => (
				<section className="darkify-changelog-editor__version" key={ version.id }>
					<header className="darkify-changelog-editor__version-header">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Version', 'darkify-util' ) }
							value={ version.version }
							onChange={ ( value ) =>
								replaceAt( index, { ...version, version: value } )
							}
							className="darkify-changelog-editor__version-number"
						/>

						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Date', 'darkify-util' ) }
							value={ version.date }
							onChange={ ( date ) =>
								replaceAt( index, {
									...version,
									date,
									// Kept in step as you type; an unreadable
									// date simply clears the machine-readable
									// twin rather than blocking the edit.
									dateISO: parseDate( date ),
								} )
							}
							help={
								version.date && ! version.dateISO
									? __( 'Not recognised as a date — it will still display as written.', 'darkify-util' )
									: undefined
							}
						/>

						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Label', 'darkify-util' ) }
							value={ version.label }
							onChange={ ( label ) =>
								replaceAt( index, { ...version, label } )
							}
							placeholder={ __( 'Optional', 'darkify-util' ) }
						/>

						<div className="darkify-changelog-editor__version-tools">
							<Button
								size="small"
								variant="tertiary"
								icon="arrow-up-alt2"
								onClick={ () => move( index, -1 ) }
								disabled={ 0 === index }
								label={ __( 'Move version up', 'darkify-util' ) }
								showTooltip
							/>
							<Button
								size="small"
								variant="tertiary"
								icon="arrow-down-alt2"
								onClick={ () => move( index, 1 ) }
								disabled={ index === versions.length - 1 }
								label={ __( 'Move version down', 'darkify-util' ) }
								showTooltip
							/>
							<Button
								size="small"
								variant="tertiary"
								isDestructive
								icon="trash"
								onClick={ () => remove( index ) }
								label={ __( 'Remove version', 'darkify-util' ) }
								showTooltip
							/>
						</div>
					</header>

					<ul className="darkify-changelog-editor__entries">
						{ version.entries.map( ( entry, entryIndex ) => (
							<EntryRow
								key={ entry.id }
								entry={ entry }
								categories={ categories }
								onChange={ ( updated ) => {
									const entries = version.entries.slice();
									entries[ entryIndex ] = updated;
									replaceAt( index, { ...version, entries } );
								} }
								onRemove={ () =>
									replaceAt( index, {
										...version,
										entries: version.entries.filter(
											( _entry, i ) => i !== entryIndex
										),
									} )
								}
							/>
						) ) }
					</ul>

					<Button
						size="small"
						variant="secondary"
						onClick={ () =>
							replaceAt( index, {
								...version,
								entries: [
									...version.entries,
									emptyEntry( categories[ 0 ] && categories[ 0 ].slug ),
								],
							} )
						}
					>
						{ __( 'Add entry', 'darkify-util' ) }
					</Button>
				</section>
			) ) }

			<div className="darkify-changelog-editor__add-version">
				<Button
					variant="secondary"
					onClick={ () => onChange( [ emptyVersion(), ...versions ] ) }
				>
					{ __( 'Add version', 'darkify-util' ) }
				</Button>

				<span className="darkify-changelog-editor__count">
					{ sprintf(
						/* translators: %d: number of versions. */
						__( '%d versions', 'darkify-util' ),
						versions.length
					) }
				</span>
			</div>
		</div>
	);
}
