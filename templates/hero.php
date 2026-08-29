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
		<?php if ($data['cursor']) : ?>data-dkfd-cursor="1"<?php endif; ?>
		data-dkfd-light-hold="<?php echo esc_attr($data['light_hold']); ?>"
		data-dkfd-dark-hold="<?php echo esc_attr($data['dark_hold']); ?>"
		data-dkfd-fade="<?php echo esc_attr($data['fade']); ?>"
		data-dkfd-start="<?php echo esc_attr($data['start']); ?>"
		<?php if ($data['admin']) : ?>
			<?php
			/*
			 * The second half of the loop: once the front end has shown the flip,
			 * the preview walks over to wp-admin and shows it again there. The two
			 * addresses are handed over so the script can swap the one in the
			 * chrome as it goes.
			 */
			?>
			data-dkfd-admin="1"
			data-dkfd-admin-hold="<?php echo esc_attr($data['admin_hold']); ?>"
			data-dkfd-admin-dark-hold="<?php echo esc_attr($data['admin_dark_hold']); ?>"
			data-dkfd-admin-url="<?php echo esc_attr($data['admin_url']); ?>"
			<?php if ($data['admin_palette']) : ?>
				<?php
				/*
				 * The preset the loop picks in the admin's palette dropdown,
				 * carrying the CSS variables the server resolved for it — the
				 * same `--darkify_dark_mode_*` values Darkify's own header
				 * template prints, so the recolour is the engine's, not a
				 * second palette written here.
				 */
				?>
				data-dkfd-palette-hold="<?php echo esc_attr($data['palette_hold']); ?>"
				data-dkfd-palette="<?php echo esc_attr(wp_json_encode(array(
					'value' => $data['admin_palette']['value'],
					'label' => $data['admin_palette']['label'],
					'dot'   => $data['admin_palette']['chip'][0],
					'vars'  => $data['admin_palette']['vars'],
				))); ?>"
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
	data-dkfd-url="<?php echo esc_attr($data['url']); ?>"
	data-dkfd-label-light="<?php echo esc_attr($data['label_light']); ?>"
	data-dkfd-label-dark="<?php echo esc_attr($data['label_dark']); ?>"
	style="--dkfd-max: <?php echo esc_attr($data['max_width']); ?>px; --dkfd-fade: <?php echo esc_attr($data['fade']); ?>ms;">

	<div class="dkfd__window">
		<?php if ($data['chrome']) : ?>
			<div class="dkfd__chrome">
				<span class="dkfd__dots" aria-hidden="true"><i></i><i></i><i></i></span>
				<span class="dkfd__url"><?php echo esc_html($data['url']); ?></span>
				<?php
				/*
				 * The walk to wp-admin is a page load, so it gets a page load's
				 * tell: a thin bar that crosses the chrome while the frame is
				 * behind its veil. Purely decorative, and driven by the same
				 * timings as the dissolve.
				 */
				?>
				<span class="dkfd__progress" aria-hidden="true"></span>
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

		<?php if ($data['autoplay'] && $data['cursor']) : ?>
			<?php
			/*
			 * The cursor lives on the host page, above the frame, not inside it
			 * — the preview dips out while the engine repaints, and the hand
			 * doing the clicking has to stay crisp through that. Its target is
			 * read from the switcher's real position inside the frame on every
			 * pass, so it lands on the switch at any width.
			 *
			 * `darkify_ignore` for the same reason the control panel carries it:
			 * this is chrome, not content, and it should keep its own colours
			 * when the host page itself goes dark.
			 */
			?>
			<div class="dkfd__cursor darkify_ignore" aria-hidden="true">
				<span class="dkfd__ripple"></span>
				<svg viewBox="0 0 24 24" width="24" height="24" xmlns="http://www.w3.org/2000/svg">
					<path d="M5.5 2.6 19 12.2c.6.4.4 1.4-.3 1.5l-5.5 1-2.6 5.4c-.3.7-1.3.6-1.5-.2L5.5 2.6Z" fill="#ffffff" stroke="#0f172a" stroke-width="1.4" stroke-linejoin="round"/>
				</svg>
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
