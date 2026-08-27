<?php

/**
 * The sample site inside the hero preview.
 *
 * A plain light-mode page with no dark-mode styling of its own — the demo is
 * only honest if every dark colour a visitor sees is one Darkify's engine
 * derived, not one written here. It is deliberately busier than the interactive
 * demo's sample: a nav, a headline, body copy, two button styles, a media block
 * and a row of cards, so the flip has something to show on every kind of
 * surface a real page has.
 *
 * `darkify_ignore` is Darkify's own opt-out (the runtime marker behind its
 * Disallowed Elements setting). It is on the brand mark — the engine reads a
 * solid coloured box as a surface and maps it into the palette, which is right
 * for a page's own panels and wrong for a brand colour — and on the content
 * placeholders, which are translucent so that what shows through them is
 * whatever Darkify painted underneath. See darkify-hero-frame.css.
 *
 * @var array $data Prepared by Darkify_Util_Hero::render().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="dkfh">
	<header class="dkfh-bar">
		<span class="dkfh-brand">
			<span class="dkfh-brand__mark darkify_ignore" aria-hidden="true"></span>
			<span class="dkfh-brand__name"><?php echo esc_html($data['brand']); ?></span>
		</span>

		<nav class="dkfh-nav">
			<?php foreach ($data['nav'] as $dkfh_item) : ?>
				<span><?php echo esc_html($dkfh_item['label']); ?></span>
			<?php endforeach; ?>
			<span class="dkfh-nav__cta"><?php echo esc_html($data['cta']); ?></span>
		</nav>
	</header>

	<div class="dkfh-hero">
		<span class="dkfh-eyebrow"><?php esc_html_e('Dark mode', 'darkify-util'); ?></span>
		<h2 class="dkfh-hero__title"><?php echo esc_html($data['heading']); ?></h2>
		<p class="dkfh-hero__text"><?php echo esc_html($data['text']); ?></p>
		<div class="dkfh-actions">
			<span class="dkfh-btn dkfh-btn--primary"><?php echo esc_html($data['cta']); ?></span>
			<span class="dkfh-btn dkfh-btn--ghost"><?php echo esc_html($data['cta_alt']); ?></span>
		</div>
	</div>

	<div class="dkfh-media" aria-hidden="true">
		<span class="dkfh-media__thumb darkify_ignore"></span>
		<span class="dkfh-media__lines darkify_ignore">
			<i style="width: 82%"></i>
			<i style="width: 64%"></i>
			<i style="width: 71%"></i>
		</span>
	</div>

	<div class="dkfh-cards" aria-hidden="true">
		<?php for ($dkfh_i = 0; $dkfh_i < 3; $dkfh_i++) : ?>
			<div class="dkfh-card">
				<span class="dkfh-card__icon darkify_ignore"></span>
				<span class="dkfh-card__title darkify_ignore"></span>
				<span class="dkfh-card__line darkify_ignore"></span>
				<span class="dkfh-card__line dkfh-card__line--short darkify_ignore"></span>
			</div>
		<?php endfor; ?>
	</div>
</div>

<?php if ($data['switcher']) : ?>
	<?php
	/*
	 * Darkify's own switcher, along for the ride: it is the one part of the
	 * preview the engine never repaints (`.darkify_switch` is excluded), so its
	 * sun-to-moon morph plays through every flip while the page behind it
	 * changes. Inert — the loop drives the state, and a stray click would fight
	 * it.
	 */
	?>
	<div class="dkfh-fab darkify_ignore" aria-hidden="true"><?php
		echo $this->switcher_markup($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></div>
<?php endif; ?>
