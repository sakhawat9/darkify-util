<?php

/**
 * The AI Summarize component.
 *
 * Plain links, nothing else. No script runs here on the front end, so the
 * buttons work the moment the HTML lands, and a reader with JavaScript off gets
 * the same four working links as everybody else.
 *
 * Each link is marked `nofollow` and `noopener`: these point at third-party
 * chat apps with a query string full of this site's own URL, and neither the
 * link equity nor the opener reference is anything this page wants to hand over.
 *
 * @var array $data Prepared by Darkify_Util_AI_Summarize::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkfa_heading = trim((string) $data['title']);
$dkfa_has_heading = $data['showTitle'] && '' !== $dkfa_heading;
?>
<?php
/*
 * The wrapper carries the custom properties in its own `style` attribute — see
 * Darkify_Util_AI_Summarize::custom_properties(). Nothing here may add a second
 * one: an element gets one style attribute, and a browser ignores every extra.
 */
?>
<div <?php echo $data['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output. ?>
	data-darkify-ai-summarize>

	<?php if ($dkfa_has_heading) : ?>
		<p class="darkify-ai-summarize__title"><?php echo esc_html($dkfa_heading); ?></p>
	<?php endif; ?>

	<div class="darkify-ai-summarize__buttons darkify-ai-summarize__buttons--<?php echo esc_attr($data['buttonStyle']); ?> darkify-ai-summarize__buttons--<?php echo esc_attr($data['align']); ?>">
		<?php foreach ($data['buttons'] as $dkfa_button) : ?>
			<a class="darkify-ai-summarize__button darkify-ai-summarize__button--<?php echo esc_attr($dkfa_button['slug']); ?>"
				href="<?php echo esc_url($dkfa_button['href']); ?>"
				target="_blank"
				rel="noopener nofollow"
				title="<?php echo esc_attr($dkfa_button['aria']); ?>"
				style="--darkify-ai-brand: <?php echo esc_attr($dkfa_button['color']); ?>; --darkify-ai-brand-dark: <?php echo esc_attr($dkfa_button['colorDark']); ?>">

				<?php if ($data['showIcons']) : ?>
					<?php
					/*
					 * The mark is decoration, not information: the label beside
					 * it already says which assistant this is, so it is hidden
					 * from screen readers rather than announced twice.
					 */
					?>
					<svg class="darkify-ai-summarize__icon" viewBox="0 0 24 24" width="18" height="18"
						fill="currentColor" fill-rule="evenodd" aria-hidden="true" focusable="false">
						<path d="<?php echo esc_attr($dkfa_button['icon']); ?>"></path>
					</svg>
				<?php endif; ?>

				<span class="darkify-ai-summarize__label"><?php echo esc_html($dkfa_button['label']); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
