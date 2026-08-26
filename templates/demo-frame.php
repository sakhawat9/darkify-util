<?php

/**
 * The sample site shown inside the preview frame.
 *
 * Plain, ordinary markup on purpose: Darkify's engine reads whatever a page
 * actually renders, so the demo is only honest if the sample is a normal light
 * page with no dark-mode styling of its own. The switcher is Darkify's own
 * [darkify] shortcode — the same element a site owner would place — so the
 * button, its animation and its colours are the ones configured in Darkify.
 *
 * @var array $data Prepared by Darkify_Util_Demo::render().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="dkfd-site">
	<header class="dkfd-site__bar">
		<span class="dkfd-brand">
			<span class="dkfd-brand__mark" aria-hidden="true"></span>
			<span class="dkfd-brand__name"><?php echo esc_html($data['brand']); ?></span>
		</span>
		<nav class="dkfd-nav" aria-label="<?php esc_attr_e('Sample site navigation', 'darkify-util'); ?>">
			<span><?php esc_html_e('Home', 'darkify-util'); ?></span>
			<span><?php esc_html_e('Shop', 'darkify-util'); ?></span>
			<span><?php esc_html_e('Blog', 'darkify-util'); ?></span>
			<span><?php esc_html_e('Contact', 'darkify-util'); ?></span>
		</nav>
	</header>

	<div class="dkfd-hero">
		<h2 class="dkfd-hero__title"><?php esc_html_e('Your headline looks great in both modes.', 'darkify-util'); ?></h2>
		<p class="dkfd-hero__text"><?php esc_html_e('Darkify recolors backgrounds, text, borders, images, and even scrollbars — automatically, with contrast that stays readable.', 'darkify-util'); ?></p>
	</div>

	<div class="dkfd-cards">
		<?php
		$dkfd_cards = array(
			array(__('Fast', 'darkify-util'), __('No layout shift.', 'darkify-util')),
			array(__('Accessible', 'darkify-util'), __('WCAG contrast.', 'darkify-util')),
			array(__('Yours', 'darkify-util'), __('Fully themed.', 'darkify-util')),
		);
		foreach ($dkfd_cards as $dkfd_card) : ?>
			<div class="dkfd-card">
				<span class="dkfd-card__icon" aria-hidden="true"></span>
				<h3 class="dkfd-card__title"><?php echo esc_html($dkfd_card[0]); ?></h3>
				<p class="dkfd-card__text"><?php echo esc_html($dkfd_card[1]); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="dkfd-fab" data-dkfd-position="<?php echo esc_attr($data['position']); ?>"><?php
	// Darkify's own switcher, rendered by Darkify, with the size the controls
	// start on passed through the shortcode's own attribute — so the first
	// paint already matches the panel. The Switch Size control then updates the
	// very CSS variable this attribute writes.
	$dkfd_shortcode = sprintf(
		'[darkify switch="%s" switch_size="%d"',
		esc_attr($data['variant']),
		(int) $data['switch_size']
	);

	if ($data['radius']) {
		$dkfd_shortcode .= sprintf(' border_radius="%s"', esc_attr($data['radius']));
	}

	// do_shortcode() returns the switcher markup with every value escaped by
	// Darkify itself.
	echo do_shortcode($dkfd_shortcode . ']'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></div>
