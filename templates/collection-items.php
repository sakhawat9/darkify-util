<?php

/**
 * One page of collection cards — the template renderer.
 *
 * This file is the seam between the data and the design. Everything above it
 * (which items match, which page they are on, what the chips say) has already
 * been decided and is identical whichever template is chosen; everything below
 * it is one partial per template that draws a single card and knows nothing
 * about filtering, search or paging.
 *
 * It is also exactly what AJAX returns: a filter, a search or a page change
 * re-renders this file and nothing else, so the cards that arrive after a click
 * come from the same code that rendered the ones already on screen — in
 * whichever template the block is wearing.
 *
 * @var array $data Prepared by Darkify_Util_Collection::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkc_collection = Darkify_Util_Collection::instance();
$dkc_partial    = Darkify_Util_Collection::card_partial($data['template']);

foreach ($data['items'] as $dkc_index => $dkc_item) :
    // Derived once per item, in PHP, so no partial has to work out whether an
    // item has a link or what its categories are called.
    $dkc_view = $dkc_collection->item_view($dkc_item, $data);

    include $dkc_partial;
endforeach;
