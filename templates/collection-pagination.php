<?php

/**
 * Numbered pagination for the collection.
 *
 * Built by hand rather than with paginate_links() because each control needs a
 * `data-page` for the view module to read *and* a working href for the browser
 * to follow when there is no view module — and because the href has to carry
 * the current category and search with it, which is what keeps the state alive
 * when someone opens page three in a new tab.
 *
 * @var array $data Prepared by Darkify_Util_Collection::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkc_current = (int) $data['currentPage'];
$dkc_pages   = (int) $data['totalPages'];

/*
 * First, last, and a window either side of the current page. Long collections
 * would otherwise print sixty numbers, which is unusable on a phone and reads as
 * a wall on a desktop.
 */
$dkc_window  = array(1, $dkc_pages, $dkc_current, $dkc_current - 1, $dkc_current + 1);
$dkc_numbers = array();

foreach ($dkc_window as $dkc_number) {
    if ($dkc_number >= 1 && $dkc_number <= $dkc_pages) {
        $dkc_numbers[] = $dkc_number;
    }
}

$dkc_numbers = array_values(array_unique($dkc_numbers));
sort($dkc_numbers);
?>
<nav class="darkify-collection__pagination" aria-label="<?php esc_attr_e('Collection pages', 'darkify-util'); ?>">
	<ul class="darkify-collection__pagination-list">

		<?php if ($dkc_current > 1) : ?>
			<li class="darkify-collection__pagination-item">
				<a class="darkify-collection__page darkify-collection__page--prev"
					href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('page' => $dkc_current - 1))); ?>"
					data-page="<?php echo esc_attr($dkc_current - 1); ?>"
					rel="prev">
					<?php esc_html_e('Previous', 'darkify-util'); ?>
				</a>
			</li>
		<?php endif; ?>

		<?php
        $dkc_previous = 0;
        foreach ($dkc_numbers as $dkc_number) :
            if ($dkc_previous && $dkc_number - $dkc_previous > 1) : ?>
				<li class="darkify-collection__pagination-item" aria-hidden="true">
					<span class="darkify-collection__page darkify-collection__page--gap">&hellip;</span>
				</li>
			<?php endif; ?>

			<li class="darkify-collection__pagination-item">
				<?php if ($dkc_number === $dkc_current) : ?>
					<span class="darkify-collection__page is-current" aria-current="page">
						<?php echo esc_html(number_format_i18n($dkc_number)); ?>
					</span>
				<?php else : ?>
					<a class="darkify-collection__page"
						href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('page' => $dkc_number))); ?>"
						data-page="<?php echo esc_attr($dkc_number); ?>">
						<span class="screen-reader-text"><?php esc_html_e('Page', 'darkify-util'); ?></span>
						<?php echo esc_html(number_format_i18n($dkc_number)); ?>
					</a>
				<?php endif; ?>
			</li>

		<?php
            $dkc_previous = $dkc_number;
        endforeach;
        ?>

		<?php if ($dkc_current < $dkc_pages) : ?>
			<li class="darkify-collection__pagination-item">
				<a class="darkify-collection__page darkify-collection__page--next"
					href="<?php echo esc_url(Darkify_Util_Collection::link($data, array('page' => $dkc_current + 1))); ?>"
					data-page="<?php echo esc_attr($dkc_current + 1); ?>"
					rel="next">
					<?php esc_html_e('Next', 'darkify-util'); ?>
				</a>
			</li>
		<?php endif; ?>

	</ul>
</nav>
