<?php

/**
 * Template 1 — Default.
 *
 * The universal card: image on top, then whatever the item fills in. It shows
 * only the fields an item actually has, which is what lets the same card be a
 * deal roundup (image, badge, dated details, a button), a resource list (logo,
 * title, a paragraph) and a plain directory entry without any of them carrying
 * the others' empty markup.
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
<article class="darkify-collection__item"
	data-collection-item
	data-categories="<?php echo esc_attr(implode(' ', $dkc_item['categories'])); ?>">

	<?php if ($dkc_view['hasImage']) : ?>
		<div class="darkify-collection__media">
			<?php if ($dkc_view['hasLink']) : ?>
				<a class="darkify-collection__media-link" href="<?php echo esc_url($dkc_item['url']); ?>" <?php echo $dkc_view['target']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal. ?> tabindex="-1" aria-hidden="true">
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- item_image() escapes what it builds.
			echo $dkc_collection->item_image($dkc_item, $data);
			?>

			<?php if ($dkc_view['hasLink']) : ?>
				</a>
			<?php endif; ?>

			<?php if ($data['showBadge'] && '' !== $dkc_item['badge']) : ?>
				<span class="darkify-collection__badge"><?php echo esc_html($dkc_item['badge']); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="darkify-collection__body">

		<?php if (!empty($dkc_view['terms'])) : ?>
			<p class="darkify-collection__terms">
				<?php foreach ($dkc_view['terms'] as $dkc_term) : ?>
					<span class="darkify-collection__term"><?php echo esc_html($dkc_term); ?></span>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ('' !== $dkc_item['title']) : ?>
			<h3 class="darkify-collection__title">
				<?php if ($dkc_view['hasLink']) : ?>
					<a class="darkify-collection__title-link" href="<?php echo esc_url($dkc_item['url']); ?>" <?php echo $dkc_view['target']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal. ?>>
						<?php echo esc_html($dkc_item['title']); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html($dkc_item['title']); ?>
				<?php endif; ?>
			</h3>
		<?php endif; ?>

		<?php if ($data['showSubtitle'] && '' !== $dkc_item['subtitle']) : ?>
			<p class="darkify-collection__subtitle"><?php echo esc_html($dkc_item['subtitle']); ?></p>
		<?php endif; ?>

		<?php if ($data['showMeta'] && !empty($dkc_item['meta'])) : ?>
			<?php
			/*
			 * The open end of the schema. "Start Date / End Date / Deal /
			 * Coupon" on a deals page and "Founded / Stack" on a showcase are
			 * the same markup, which is why adding a field to a collection
			 * never means changing this template.
			 */
			?>
			<ul class="darkify-collection__meta">
				<?php foreach ($dkc_item['meta'] as $dkc_meta) : ?>
					<li class="darkify-collection__meta-row">
						<?php if ('' !== $dkc_meta['label']) : ?>
							<span class="darkify-collection__meta-label"><?php echo esc_html($dkc_meta['label']); ?></span>
						<?php endif; ?>
						<?php if ('' !== $dkc_meta['value']) : ?>
							<span class="darkify-collection__meta-value"><?php echo esc_html($dkc_meta['value']); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ($data['showDescription'] && '' !== $dkc_item['description']) : ?>
			<div class="darkify-collection__description">
				<?php echo wp_kses_post(wpautop($dkc_item['description'])); ?>
			</div>
		<?php endif; ?>

		<?php if ($data['showButton'] && $dkc_view['hasLink']) : ?>
			<div class="darkify-collection__footer">
				<a class="darkify-collection__button" href="<?php echo esc_url($dkc_item['url']); ?>" <?php echo $dkc_view['target']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal. ?>>
					<span class="darkify-collection__button-label"><?php echo esc_html($dkc_view['label']); ?></span>
					<span class="darkify-collection__button-arrow" aria-hidden="true">&rarr;</span>
					<?php if ('' !== $dkc_item['title']) : ?>
						<span class="screen-reader-text">
							<?php
							/* translators: %s: item title. */
							printf(esc_html__('about %s', 'darkify-util'), esc_html($dkc_item['title']));
							?>
						</span>
					<?php endif; ?>
				</a>
			</div>
		<?php endif; ?>

	</div>
</article>
