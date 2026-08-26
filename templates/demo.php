<?php

/**
 * The demo wrapper: the browser window that frames the sample site.
 *
 * The chrome (title bar, traffic lights, address) belongs to the host page —
 * it is the browser, not the site being previewed, so it keeps its own look in
 * both modes. Everything inside <template> is inert markup that the front-end
 * script hands to the preview frame, where Darkify takes over.
 *
 * @var array $data Prepared by Darkify_Util_Demo::render().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="dkfd" id="<?php echo esc_attr($data['instance']); ?>" style="--dkfd-max: <?php echo esc_attr($data['max_width']); ?>px;">

	<?php if ($data['heading']) : ?>
		<h2 class="dkfd__heading"><?php echo esc_html($data['heading']); ?></h2>
	<?php endif; ?>

	<?php if ($data['subtitle']) : ?>
		<p class="dkfd__subtitle"><?php echo esc_html($data['subtitle']); ?></p>
	<?php endif; ?>

	<div class="dkfd__window">
		<div class="dkfd__chrome">
			<span class="dkfd__dots" aria-hidden="true"><i></i><i></i><i></i></span>
			<span class="dkfd__url"><?php echo esc_html($data['url']); ?></span>
		</div>
		<div class="dkfd__viewport">
			<iframe
				class="dkfd__frame"
				title="<?php esc_attr_e('Interactive Darkify preview', 'darkify-util'); ?>"
				src="about:blank"
				scrolling="no"
				tabindex="0"
				loading="lazy"></iframe>
		</div>
	</div>

	<?php if ($data['note']) : ?>
		<p class="dkfd__note"><?php echo esc_html($data['note']); ?></p>
	<?php endif; ?>

	<template class="dkfd__source"><?php
		// frame_markup() escapes every dynamic value it prints, and the switcher
		// comes from Darkify's own shortcode, so this is safe to print as-is.
		echo $this->frame_markup($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></template>
</div>
