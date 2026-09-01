<?php

/**
 * Template 2 — Framed.
 *
 * The design from the supplied screenshot: a dashed panel holding a large,
 * softly-tinted image area, and beneath it a solid footer bar carrying the
 * title on the left and a round outlined arrow button on the right. Hover (and
 * keyboard focus) lights the whole card's border in the accent colour, which is
 * the highlighted state the screenshot shows on its first card.
 *
 * The bar carries a title, a subtitle and the link, and nothing else — no
 * category, no details, no description. That is the design rather than an
 * omission: this is the one template that is a *bar* rather than a body, it is
 * only ever as tall as the picture plus two lines, and the picture is meant to
 * carry the card. Every one of those fields is still on the item, the filter
 * chips still work from the categories, and the other templates show them all;
 * this design simply stays out of their way.
 *
 * Which means the Details, Description and Category toggles have nothing to act
 * on here. They are left switched on for the collection rather than forced off,
 * so switching to another template brings the fields straight back.
 *
 * The screenshot's panels are empty because it is a mock-up of the upload
 * state; an item with no image renders the same panel with a picture glyph, so
 * a half-filled collection still lines up rather than collapsing.
 *
 * @var array $data      Prepared by Darkify_Util_Collection::prepare().
 * @var array $dkc_item  The item.
 * @var array $dkc_view  Its derived values.
 * @var int   $dkc_index Its position on the page.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="darkify-collection__item darkify-collection__frame"
	data-collection-item
	data-categories="<?php echo esc_attr(implode(' ', $dkc_item['categories'])); ?>">

	<div class="darkify-collection__frame-panel">
		<?php if ($dkc_view['hasImage']) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- item_image() escapes what it builds.
			echo $dkc_collection->item_image($dkc_item, $data, 'darkify-collection__image');
			?>
		<?php else : ?>
			<span class="darkify-collection__frame-placeholder" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
					stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<rect x="3" y="3" width="18" height="18" rx="2" />
					<circle cx="8.5" cy="8.5" r="1.5" />
					<path d="m21 15-5-5L5 21" />
				</svg>
			</span>
		<?php endif; ?>

		<?php if ($data['showBadge'] && '' !== $dkc_item['badge']) : ?>
			<span class="darkify-collection__badge"><?php echo esc_html($dkc_item['badge']); ?></span>
		<?php endif; ?>
	</div>

	<div class="darkify-collection__frame-bar">
		<div class="darkify-collection__frame-text">
			<?php if ('' !== $dkc_item['title']) : ?>
				<h3 class="darkify-collection__title">
					<?php if ($dkc_view['hasLink']) : ?>
						<?php
						/*
						 * The stretched link: the anchor is on the title for
						 * screen readers and the keyboard, and a pseudo-element
						 * spreads its hit area over the whole card. One link per
						 * card, and all of the card is clickable.
						 */
						?>
						<a class="darkify-collection__title-link darkify-collection__stretch"
							href="<?php echo esc_url($dkc_item['url']); ?>" <?php echo $dkc_view['target']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal. ?>>
							<?php echo esc_html($dkc_item['title']); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html($dkc_item['title']); ?>
					<?php endif; ?>
				</h3>
			<?php endif; ?>

			<?php if ($data['showSubtitle'] && '' !== $dkc_item['subtitle']) : ?>
				<p class="darkify-collection__frame-subtitle"><?php echo esc_html($dkc_item['subtitle']); ?></p>
			<?php endif; ?>
		</div>

		<?php if ($dkc_view['hasLink']) : ?>
			<span class="darkify-collection__frame-go" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<path d="M7 17 17 7" />
					<path d="M8 7h9v9" />
				</svg>
			</span>
		<?php endif; ?>
	</div>
</article>
