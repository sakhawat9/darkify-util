<?php

/**
 * The hero preview: the browser window and the loop's settings.
 *
 * The chrome and the mode badge belong to the host page — they frame the
 * preview rather than being part of the site inside it, so they keep their own
 * look while the sample site flips. Everything inside <template> is inert markup
 * the front-end script hands to the frame, where Darkify takes over.
 *
 * @var array $data Prepared by Darkify_Util_Hero::render().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div
	class="dkfd dkfd--hero<?php echo $data['chrome'] ? '' : ' dkfd--bare'; ?>"
	id="<?php echo esc_attr($data['instance']); ?>"
	data-dkfd-frame-css="<?php echo esc_url($data['frame_css']); ?>"
	<?php if ($data['autoplay']) : ?>
		data-dkfd-autoplay="1"
		data-dkfd-light-hold="<?php echo esc_attr($data['light_hold']); ?>"
		data-dkfd-dark-hold="<?php echo esc_attr($data['dark_hold']); ?>"
		data-dkfd-fade="<?php echo esc_attr($data['fade']); ?>"
		data-dkfd-start="<?php echo esc_attr($data['start']); ?>"
	<?php endif; ?>
	data-dkfd-label-light="<?php echo esc_attr($data['label_light']); ?>"
	data-dkfd-label-dark="<?php echo esc_attr($data['label_dark']); ?>"
	style="--dkfd-max: <?php echo esc_attr($data['max_width']); ?>px; --dkfd-fade: <?php echo esc_attr($data['fade']); ?>ms;">

	<div class="dkfd__window">
		<?php if ($data['chrome']) : ?>
			<div class="dkfd__chrome">
				<span class="dkfd__dots" aria-hidden="true"><i></i><i></i><i></i></span>
				<span class="dkfd__url"><?php echo esc_html($data['url']); ?></span>
				<?php if ($data['badge']) : ?>
					<?php
					/*
					 * Announced politely rather than silently: the preview is
					 * decorative, but the badge is the one piece of it that
					 * carries meaning, and it changes on its own.
					 */
					?>
					<span class="dkfd__mode" data-dkfd-mode="<?php echo esc_attr($data['start']); ?>" aria-live="polite"><?php
						echo esc_html('dark' === $data['start'] ? $data['label_dark'] : $data['label_light']);
					?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dkfd__viewport">
			<iframe
				class="dkfd__frame"
				title="<?php esc_attr_e('Darkify preview, switching between light and dark mode', 'darkify-util'); ?>"
				src="about:blank"
				scrolling="no"
				tabindex="-1"
				aria-hidden="true"
				loading="lazy"></iframe>
		</div>
	</div>

	<template class="dkfd__source"><?php
		// frame_markup() escapes every dynamic value it prints, and the switcher
		// comes from Darkify's own shortcode, so this is safe to print as-is.
		echo $this->frame_markup($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></template>
</div>
