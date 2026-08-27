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
<div class="dkfd" id="<?php echo esc_attr($data['instance']); ?>" data-dkfd-frame-css="<?php echo esc_url($data['frame_css']); ?>" style="--dkfd-max: <?php echo esc_attr($data['max_width']); ?>px;">

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

	<?php if ($data['controls'] && ($data['presets'] || $data['sizes'] || $data['positions'])) : ?>
		<?php
		/*
		 * `darkify_ignore` is Darkify's own opt-out: the engine and its
		 * stylesheet both skip anything carrying it. The panel is already
		 * designed for a dark surface, and its selected states are deliberate,
		 * so it keeps its colours when the host page itself goes dark.
		 */
		?>
		<div class="dkfd__controls darkify_ignore" role="group" aria-label="<?php esc_attr_e('Demo switcher settings', 'darkify-util'); ?>">

			<?php if ($data['presets']) : ?>
				<div class="dkfd-ctrl">
					<span class="dkfd-ctrl__label" id="<?php echo esc_attr($data['instance']); ?>_preset"><?php esc_html_e('Color preset', 'darkify-util'); ?></span>
					<div class="dkfd-ctrl__options" role="group" aria-labelledby="<?php echo esc_attr($data['instance']); ?>_preset">
						<?php foreach ($data['presets'] as $preset) :
							$selected = $preset['value'] === $data['preset']; ?>
							<button
								type="button"
								class="dkfd-swatch<?php echo $selected ? ' is-selected' : ''; ?>"
								style="--dkfd-swatch: <?php echo esc_attr($preset['chip'][0]); ?>; --dkfd-swatch-2: <?php echo esc_attr($preset['chip'][1]); ?>;"
								data-dkfd-control="preset"
								data-dkfd-value="<?php echo esc_attr($preset['value']); ?>"
								data-dkfd-vars="<?php echo esc_attr(wp_json_encode($preset['vars'])); ?>"
								aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>"
								title="<?php echo esc_attr($preset['label']); ?>">
								<span class="screen-reader-text"><?php echo esc_html($preset['label']); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($data['sizes']) : ?>
				<div class="dkfd-ctrl">
					<span class="dkfd-ctrl__label" id="<?php echo esc_attr($data['instance']); ?>_size"><?php esc_html_e('Switch size', 'darkify-util'); ?></span>
					<div class="dkfd-ctrl__options" role="group" aria-labelledby="<?php echo esc_attr($data['instance']); ?>_size">
						<?php foreach ($data['sizes'] as $size) :
							$selected = (int) $size['value'] === (int) $data['switch_size']; ?>
							<button
								type="button"
								class="dkfd-pill<?php echo $selected ? ' is-selected' : ''; ?>"
								data-dkfd-control="size"
								data-dkfd-value="<?php echo esc_attr($size['value']); ?>"
								aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>"><?php echo esc_html($size['label']); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($data['positions']) : ?>
				<div class="dkfd-ctrl">
					<span class="dkfd-ctrl__label" id="<?php echo esc_attr($data['instance']); ?>_position"><?php esc_html_e('Position', 'darkify-util'); ?></span>
					<div class="dkfd-ctrl__options" role="group" aria-labelledby="<?php echo esc_attr($data['instance']); ?>_position">
						<?php foreach ($data['positions'] as $position) :
							$selected = $position['value'] === $data['position']; ?>
							<button
								type="button"
								class="dkfd-pill dkfd-pill--solid<?php echo $selected ? ' is-selected' : ''; ?>"
								data-dkfd-control="position"
								data-dkfd-value="<?php echo esc_attr($position['value']); ?>"
								aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>"><?php echo esc_html($position['label']); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

		</div>
	<?php endif; ?>

	<?php if ($data['note']) : ?>
		<p class="dkfd__note"><?php echo esc_html($data['note']); ?></p>
	<?php endif; ?>

	<template class="dkfd__source"><?php
		// frame_markup() escapes every dynamic value it prints, and the switcher
		// comes from Darkify's own shortcode, so this is safe to print as-is.
		echo $this->frame_markup($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></template>
</div>
