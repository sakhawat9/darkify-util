<?php

/**
 * Template 4 — Showcase.
 *
 * For the pages where the picture is the point: sites built with the plugin,
 * product shots, portfolio pieces. The image fills the tile and the title sits
 * on it, over a gradient rather than a solid bar so the top of the screenshot
 * stays visible; the category rides above the title as an eyebrow, and the
 * whole tile is one link with an arrow that arrives on hover.
 *
 * Details and description are deliberately *below* the image in a quiet strip,
 * not in the overlay. Putting them on the picture is what turns a showcase into
 * an unreadable poster, and most showcase items have none of them anyway — the
 * strip disappears when they are empty.
 *
 * An item with no image gets the same tile with a tinted plate behind it, so a
 * collection that is half screenshots does not fall apart.
 *
 * @var array $data      Prepared by Darkify_Util_Collection::prepare().
 * @var array $dkc_item  The item.
 * @var array $dkc_view  Its derived values.
 * @var int   $dkc_index Its position on the page.
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkc_has_strip = ($data['showMeta'] && !empty($dkc_item['meta']))
    || ($data['showDescription'] && '' !== $dkc_item['description'])
    || ($data['showSubtitle'] && '' !== $dkc_item['subtitle']);
?>
<article class="darkify-collection__item darkify-collection__tile<?php echo $dkc_view['hasImage'] ? '' : ' is-plain'; ?>"
	data-collection-item
	data-categories="<?php echo esc_attr(implode(' ', $dkc_item['categories'])); ?>">

	<div class="darkify-collection__tile-visual">
		<?php if ($dkc_view['hasImage']) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- item_image() escapes what it builds.
			echo $dkc_collection->item_image($dkc_item, $data, 'darkify-collection__image');
			?>
		<?php endif; ?>

		<span class="darkify-collection__tile-veil" aria-hidden="true"></span>

		<?php if ($data['showBadge'] && '' !== $dkc_item['badge']) : ?>
			<span class="darkify-collection__badge darkify-collection__tile-badge">
				<?php echo esc_html($dkc_item['badge']); ?>
			</span>
		<?php endif; ?>

		<?php if ($dkc_view['hasLink']) : ?>
			<span class="darkify-collection__tile-go" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<path d="M7 17 17 7" />
					<path d="M8 7h9v9" />
				</svg>
			</span>
		<?php endif; ?>

		<div class="darkify-collection__tile-caption">
			<?php if (!empty($dkc_view['terms'])) : ?>
				<p class="darkify-collection__tile-terms">
					<?php echo esc_html(implode(' · ', $dkc_view['terms'])); ?>
				</p>
			<?php endif; ?>

			<?php if ('' !== $dkc_item['title']) : ?>
				<h3 class="darkify-collection__title darkify-collection__tile-title">
					<?php if ($dkc_view['hasLink']) : ?>
						<a class="darkify-collection__title-link darkify-collection__stretch"
							href="<?php echo esc_url($dkc_item['url']); ?>" <?php echo $dkc_view['target']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal. ?>>
							<?php echo esc_html($dkc_item['title']); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html($dkc_item['title']); ?>
					<?php endif; ?>
				</h3>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($dkc_has_strip) : ?>
		<div class="darkify-collection__tile-strip">
			<?php if ($data['showSubtitle'] && '' !== $dkc_item['subtitle']) : ?>
				<p class="darkify-collection__tile-subtitle"><?php echo esc_html($dkc_item['subtitle']); ?></p>
			<?php endif; ?>

			<?php if ($data['showDescription'] && '' !== $dkc_item['description']) : ?>
				<p class="darkify-collection__tile-excerpt">
					<?php echo esc_html(wp_strip_all_tags($dkc_item['description'])); ?>
				</p>
			<?php endif; ?>

			<?php if ($data['showMeta'] && !empty($dkc_item['meta'])) : ?>
				<ul class="darkify-collection__tile-meta">
					<?php foreach ($dkc_item['meta'] as $dkc_meta) : ?>
						<li>
							<?php if ('' !== $dkc_meta['label']) : ?>
								<span class="darkify-collection__tile-meta-label"><?php echo esc_html($dkc_meta['label']); ?></span>
							<?php endif; ?>
							<?php if ('' !== $dkc_meta['value']) : ?>
								<span class="darkify-collection__tile-meta-value"><?php echo esc_html($dkc_meta['value']); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>
