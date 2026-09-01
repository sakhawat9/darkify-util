/**
 * The item list: the block's main editor surface.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import ItemRow from './ItemRow';
import { emptyItem, uid } from '../model';

/**
 * @param {Object}   props
 * @param {Array}    props.items            Items.
 * @param {Array}    props.categories       Known categories.
 * @param {Function} props.onChange         Called with the updated items array.
 * @param {Function} props.onAddCategories  Called with categories created inline.
 * @return {Element} Item editor.
 */
export default function ItemsEditor( { items, categories, onChange, onAddCategories } ) {
	const replaceAt = ( index, item ) => {
		const next = items.slice();
		next[ index ] = item;
		onChange( next );
	};

	const move = ( index, delta ) => {
		const target = index + delta;

		if ( target < 0 || target >= items.length ) {
			return;
		}

		const next = items.slice();
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	const duplicate = ( index ) => {
		const next = items.slice();

		// New ids all the way down: the copy's meta rows are React keys too, and
		// sharing them with the original makes both rows edit as one.
		next.splice( index + 1, 0, {
			...items[ index ],
			id: uid( 'i' ),
			meta: items[ index ].meta.map( ( pair ) => ( { ...pair, id: uid( 'm' ) } ) ),
		} );

		onChange( next );
	};

	return (
		<div className="darkify-collection-editor__items">
			<ul className="darkify-collection-editor__list">
				{ items.map( ( item, index ) => (
					<ItemRow
						key={ item.id }
						item={ item }
						index={ index }
						categories={ categories }
						isFirst={ 0 === index }
						isLast={ index === items.length - 1 }
						onChange={ ( updated ) => replaceAt( index, updated ) }
						onRemove={ () => onChange( items.filter( ( _item, i ) => i !== index ) ) }
						onDuplicate={ () => duplicate( index ) }
						onMove={ ( delta ) => move( index, delta ) }
						onAddCategories={ onAddCategories }
					/>
				) ) }
			</ul>

			<div className="darkify-collection-editor__add">
				<Button variant="primary" onClick={ () => onChange( [ ...items, emptyItem() ] ) }>
					{ __( 'Add item', 'darkify-util' ) }
				</Button>

				<span className="darkify-collection-editor__count">
					{ sprintf(
						/* translators: %d: number of items. */
						_n( '%d item', '%d items', items.length, 'darkify-util' ),
						items.length
					) }
				</span>
			</div>
		</div>
	);
}
