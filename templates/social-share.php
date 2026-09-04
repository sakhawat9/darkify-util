<?php

/**
 * The social share component.
 *
 * Plain links. The share endpoints work without a line of JavaScript, so the
 * row is functional the moment the HTML lands; the view script only upgrades it
 * (a popup instead of a tab, and the clipboard for Instagram, which has no
 * share endpoint to link to).
 *
 * Each link is `nofollow` and `noopener`: these point at third-party composers
 * carrying this site's own URL, and neither the link equity nor the opener
 * reference is anything this page wants to hand over.
 *
 * @var array $data Prepared by Darkify_Util_Social_Share::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * The wrapper carries every custom property in its own `style` attribute — see
 * Darkify_Util_Social_Share::custom_properties(). Nothing here may add a second
 * one: an element gets one style attribute, and a browser ignores every extra.
 */
?>
<div <?php echo $data['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output. ?>
	data-darkify-share>

	<ul class="darkify-share__list darkify-share__list--<?php echo esc_attr($data['itemStyle']); ?> darkify-share__list--<?php echo esc_attr($data['contentAlign']); ?><?php echo $data['showLabels'] ? '' : ' darkify-share__list--icon-only'; ?>">
		<?php foreach ($data['items'] as $dkfs_item) : ?>
			<li class="darkify-share__item">
				<a class="darkify-share__link darkify-share__link--<?php echo esc_attr($dkfs_item['slug']); ?>"
					href="<?php echo esc_url($dkfs_item['href']); ?>"
					target="_blank"
					rel="noopener nofollow"
					aria-label="<?php echo esc_attr($dkfs_item['aria']); ?>"
					<?php if ('' !== $dkfs_item['copy']) : ?>
						data-darkify-share-copy="<?php echo esc_attr($dkfs_item['copy']); ?>"
						data-darkify-share-copied="<?php esc_attr_e('Link copied', 'darkify-util'); ?>"
					<?php endif; ?>
					style="--darkify-share-brand: <?php echo esc_attr($dkfs_item['color']); ?>; --darkify-share-brand-dark: <?php echo esc_attr($dkfs_item['colorDark']); ?>">

					<?php if ($data['showIcons']) : ?>
						<?php
						/*
						 * The mark is decoration: the link already has an
						 * aria-label naming the network, so announcing the icon
						 * as well would say it twice.
						 */
						?>
						<svg class="darkify-share__icon" viewBox="0 0 24 24"
							fill="currentColor" aria-hidden="true" focusable="false">
							<path d="<?php echo esc_attr($dkfs_item['icon']); ?>"></path>
						</svg>
					<?php endif; ?>

					<?php if ($data['showLabels']) : ?>
						<span class="darkify-share__label"><?php echo esc_html($dkfs_item['label']); ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php
	/*
	 * Where "Link copied" is announced. A colour change on the button says
	 * nothing to a screen reader, and the copy is the whole point of that
	 * button — so the confirmation has to be text somewhere. Empty until the
	 * view script fills it, and left in the page rather than created on demand
	 * so the live region is already being watched when it does.
	 */
	?>
	<span class="darkify-share__status" data-darkify-share-status role="status" aria-live="polite"></span>
</div>
