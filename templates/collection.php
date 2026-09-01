<?php

/**
 * The collection component.
 *
 * Server-rendered in full: the filter bar, the search form, one page of cards
 * and whichever pager is switched on are all in the HTML before any JavaScript
 * runs, and every control is a real link or a real form pointed at query
 * arguments the block class reads back. With JavaScript off the grid filters,
 * searches and pages by navigating; with it, the view module intercepts each
 * control and swaps the cards in place.
 *
 * @var array $data Prepared by Darkify_Util_Collection::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkc_has_items   = !empty($data['items']);
$dkc_has_any     = $data['totalItems'] > 0;
$dkc_filters     = $data['showFilters'] ? $data['categories'] : array();
$dkc_show_bar    = !empty($dkc_filters) || $data['showSearch'];
$dkc_search_id   = $data['instance'] . '-search';

/*
 * Everything the design exposes travels as custom properties on the root, so a
 * second collection further down the page can be laid out and coloured
 * differently without either one's rules leaking into the other.
 */
$dkc_ratios = array(
    '16-9' => '16 / 9',
    '4-3'  => '4 / 3',
    '3-2'  => '3 / 2',
    '1-1'  => '1 / 1',
    '3-4'  => '3 / 4',
);

$dkc_style = array(
    '--darkify-collection-columns: ' . (int) $data['columns'],
    '--darkify-collection-columns-tablet: ' . (int) $data['columnsTablet'],
    '--darkify-collection-columns-mobile: ' . (int) $data['columnsMobile'],
    '--darkify-collection-gap: ' . (int) $data['gap'] . 'px',
    '--darkify-collection-radius: ' . (int) $data['radius'] . 'px',
    '--darkify-collection-fit: ' . $data['imageFit'],
);

if (isset($dkc_ratios[$data['imageRatio']])) {
    $dkc_style[] = '--darkify-collection-ratio: ' . $dkc_ratios[$data['imageRatio']];
}

if ($data['titleSize'] > 0) {
    $dkc_style[] = '--darkify-collection-title-size: ' . (int) $data['titleSize'] . 'px';
}

foreach (array(
    'cardBackground'  => '--darkify-collection-surface',
    'cardBorderColor' => '--darkify-collection-border',
    'titleColor'      => '--darkify-collection-heading',
    'accentColor'     => '--darkify-collection-accent',
) as $dkc_attribute => $dkc_property) {
    if ('' !== $data[$dkc_attribute]) {
        $dkc_style[] = $dkc_property . ': ' . $data[$dkc_attribute];
    }
}
?>
<div <?php echo $data['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output. ?>
	id="<?php echo esc_attr($data['instance']); ?>"
	data-darkify-collection
	data-template="<?php echo esc_attr($data['template']); ?>"
	data-block-id="<?php echo esc_attr($data['blockId']); ?>"
	data-post-id="<?php echo esc_attr($data['postId']); ?>"
	data-ajax-url="<?php echo esc_url($data['ajaxUrl']); ?>"
	data-nonce="<?php echo esc_attr($data['nonce']); ?>"
	data-pagination="<?php echo esc_attr($data['paginationType']); ?>"
	data-per-page="<?php echo esc_attr($data['perPage']); ?>"
	data-category="<?php echo esc_attr($data['state']['category']); ?>"
	data-search="<?php echo esc_attr($data['state']['search']); ?>"
	data-page="<?php echo esc_attr($data['currentPage']); ?>"
	data-total-pages="<?php echo esc_attr($data['totalPages']); ?>"
	style="<?php echo esc_attr(implode('; ', $dkc_style)); ?>">

	<?php if (!$dkc_has_any) : ?>

		<p class="darkify-collection__empty">
			<?php esc_html_e('No items yet.', 'darkify-util'); ?>
		</p>

	<?php else : ?>

		<?php if ($dkc_show_bar) : ?>
			<div class="darkify-collection__bar darkify-collection__bar--<?php echo esc_attr($data['filterAlign']); ?>">

				<?php if (!empty($dkc_filters)) : ?>
					<div class="darkify-collection__filters" data-collection-filters role="group"
						aria-label="<?php esc_attr_e('Filter items by category', 'darkify-util'); ?>">

						<a class="darkify-collection__filter<?php echo 'all' === $data['state']['category'] ? ' is-active' : ''; ?>"
							href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('category' => 'all', 'page' => 1))); ?>"
							data-filter="all"
							<?php echo 'all' === $data['state']['category'] ? ' aria-current="true"' : ''; ?>>
							<span class="darkify-collection__filter-label"><?php echo esc_html($data['allLabel']); ?></span>
							<?php if ($data['showFilterCounts']) : ?>
								<span class="darkify-collection__filter-count"><?php echo esc_html(number_format_i18n($data['totalItems'])); ?></span>
							<?php endif; ?>
						</a>

						<?php foreach ($dkc_filters as $dkc_category) : ?>
							<?php if (0 === $dkc_category['count']) : ?>
								<?php
								// An empty category is a dead control; the chip is
								// only worth the space when something is behind it.
								continue;
								?>
							<?php endif; ?>
							<a class="darkify-collection__filter<?php echo $dkc_category['slug'] === $data['state']['category'] ? ' is-active' : ''; ?>"
								href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('category' => $dkc_category['slug'], 'page' => 1))); ?>"
								data-filter="<?php echo esc_attr($dkc_category['slug']); ?>"
								<?php echo $dkc_category['slug'] === $data['state']['category'] ? ' aria-current="true"' : ''; ?>>
								<span class="darkify-collection__filter-label"><?php echo esc_html($dkc_category['label']); ?></span>
								<?php if ($data['showFilterCounts']) : ?>
									<span class="darkify-collection__filter-count"><?php echo esc_html(number_format_i18n($dkc_category['count'])); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ($data['showSearch']) : ?>
					<?php
					/*
					 * A real GET form, so pressing Enter with JavaScript off
					 * reloads the page filtered. The hidden fields carry the rest
					 * of the view state across that reload — losing the chosen
					 * category on submit is the bug this prevents.
					 */
					?>
					<form class="darkify-collection__search" data-collection-search role="search" method="get"
						action="<?php echo esc_url($data['baseUrl']); ?>">
						<label class="screen-reader-text" for="<?php echo esc_attr($dkc_search_id); ?>">
							<?php esc_html_e('Search this collection', 'darkify-util'); ?>
						</label>
						<input type="hidden" name="<?php echo esc_attr(Darkify_Util_Collection::ARG_ID); ?>"
							value="<?php echo esc_attr($data['blockId']); ?>">
						<?php if ('all' !== $data['state']['category']) : ?>
							<input type="hidden" name="<?php echo esc_attr(Darkify_Util_Collection::ARG_CATEGORY); ?>"
								value="<?php echo esc_attr($data['state']['category']); ?>">
						<?php endif; ?>
						<input
							type="search"
							class="darkify-collection__search-input"
							id="<?php echo esc_attr($dkc_search_id); ?>"
							name="<?php echo esc_attr(Darkify_Util_Collection::ARG_SEARCH); ?>"
							value="<?php echo esc_attr($data['state']['search']); ?>"
							placeholder="<?php echo esc_attr($data['searchPlaceholder']); ?>"
							autocomplete="off">
						<button type="submit" class="darkify-collection__search-submit">
							<span class="screen-reader-text"><?php esc_html_e('Search', 'darkify-util'); ?></span>
							<span class="darkify-collection__search-icon" aria-hidden="true"></span>
						</button>
					</form>
				<?php endif; ?>

			</div>
		<?php endif; ?>

		<div class="darkify-collection__results" data-collection-results aria-busy="false">

			<?php
			/*
			 * The card's own variations sit on the grid rather than on the block
			 * root, because AJAX only ever replaces what is inside this element —
			 * so alignment, card style and hover survive a filter or a page
			 * change without being re-sent with every batch of cards.
			 */
			$dkc_grid_class = sprintf(
				'darkify-collection__grid is-template-%1$s is-align-%2$s is-%3$s is-hover-%4$s',
				$data['template'],
				$data['contentAlign'],
				$data['cardStyle'],
				$data['hoverEffect']
			);
			?>
			<div class="<?php echo esc_attr($dkc_grid_class); ?>" data-collection-grid>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes every value it prints.
				echo Darkify_Util_Collection::instance()->render_items($data);
				?>
			</div>

			<p class="darkify-collection__empty" data-collection-empty <?php echo $dkc_has_items ? 'hidden' : ''; ?>>
				<?php echo esc_html($data['emptyMessage']); ?>
			</p>

			<div class="darkify-collection__spinner" data-collection-spinner aria-hidden="true"></div>
		</div>

		<?php if ('load-more' === $data['paginationType']) : ?>
			<?php
			/*
			 * The button is an anchor to the next page. JavaScript turns it into
			 * an append; without JavaScript it is a link to page two, which is
			 * why it says what it says either way.
			 */
			?>
			<div class="darkify-collection__more" data-collection-more <?php echo $data['hasMore'] ? '' : 'hidden'; ?>>
				<a class="darkify-collection__more-button"
					href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('page' => $data['currentPage'] + 1))); ?>"
					data-collection-more-button>
					<span class="darkify-collection__more-label"><?php echo esc_html($data['loadMoreText']); ?></span>
				</a>
			</div>
		<?php endif; ?>

		<div data-collection-pagination>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes every value it prints.
			echo Darkify_Util_Collection::instance()->render_pagination($data);
			?>
		</div>

		<p class="screen-reader-text" data-collection-status role="status" aria-live="polite"></p>

	<?php endif; ?>
</div>
