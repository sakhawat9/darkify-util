<?php

/**
 * The promo banner.
 *
 * All three messages are in the markup, the inactive two `hidden`, so the view
 * script can move between states without a request — and a page cached before
 * the urgent window still has the urgent copy to show when it arrives.
 *
 * The ticking digits are aria-hidden: a live region updating every second would
 * talk over everything. Screen readers get the deadline as a sentence instead.
 *
 * @var array $data Prepared by Darkify_Util_Promo_Banner::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div <?php echo $data['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>
	data-darkify-promo
	data-deadline="<?php echo esc_attr($data['deadline'] * 1000); ?>"
	data-interval="<?php echo esc_attr($data['interval'] * 1000); ?>"
	data-urgent="<?php echo esc_attr($data['urgentWithin'] * 1000); ?>"
	data-expired="<?php echo esc_attr($data['expiredAction']); ?>"
	data-key="<?php echo esc_attr($data['key']); ?>"
	data-dismiss-days="<?php echo esc_attr($data['dismissDays']); ?>"
	data-state="<?php echo esc_attr($data['state']); ?>"
	role="region"
	aria-label="<?php esc_attr_e('Promotion', 'darkify-util'); ?>">

	<?php if ($data['cover']) : ?>
		<?php
		/*
		 * The whole-banner link: a sibling laid over the bar, not a wrapper
		 * around it. A wrapper would put the button's <a> and the close <button>
		 * inside another link, which HTML does not allow and browsers repair
		 * unpredictably. Instead the content sits above this and lets clicks fall
		 * through to it, except on the controls that have their own job.
		 *
		 * Named by the message it covers (aria-labelledby follows whichever state
		 * is showing, since the other two are `hidden`), and first in tab order, so
		 * a keyboard user reaches the offer before its button and close control.
		 */
		?>
		<a class="darkify-promo__cover"
			href="<?php echo esc_url($data['cover']['url']); ?>"
			aria-labelledby="<?php echo esc_attr($data['messageId']); ?>"
			<?php if ($data['cover']['newTab']) : ?>
				target="_blank" rel="noopener"
				aria-describedby="<?php echo esc_attr($data['messageId']); ?>-new-tab"
			<?php endif; ?>></a>
		<?php if ($data['cover']['newTab']) : ?>
			<span class="darkify-promo__sr" id="<?php echo esc_attr($data['messageId']); ?>-new-tab"><?php esc_html_e('Opens in a new tab', 'darkify-util'); ?></span>
		<?php endif; ?>
	<?php endif; ?>

	<div class="darkify-promo__inner">
		<p class="darkify-promo__message" id="<?php echo esc_attr($data['messageId']); ?>"><?php foreach ($data['messages'] as $dkfp_state => $dkfp_html) : ?><span class="darkify-promo__text" data-darkify-promo-message="<?php echo esc_attr($dkfp_state); ?>"<?php echo $dkfp_state === $data['state'] ? '' : ' hidden'; ?>><?php echo $dkfp_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses()ed on sanitising; tokens are escaped. ?></span><?php endforeach; ?></p>

		<?php if ($data['showCountdown']) : ?>
			<span class="darkify-promo__countdown" aria-hidden="true"><?php foreach ($data['units'] as $dkfp_index => $dkfp_unit) : ?><?php if ($dkfp_index > 0) : ?><span class="darkify-promo__sep">:</span><?php endif; ?><span class="darkify-promo__unit"><span class="darkify-promo__value" data-darkify-promo-unit="<?php echo esc_attr($dkfp_unit['key']); ?>"><?php echo esc_html($dkfp_unit['value']); ?></span><?php if ($data['showLabels']) : ?><span class="darkify-promo__label"><?php echo esc_html($dkfp_unit['label']); ?></span><?php endif; ?></span><?php endforeach; ?></span>
			<span class="darkify-promo__sr"><?php
				printf(
					/* translators: 1: date the offer ends, 2: time it ends. */
					esc_html__('Ends %1$s at %2$s.', 'darkify-util'),
					Darkify_Util_Promo_Banner::instance()->token_markup('date', $data['tokens']['date']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
					Darkify_Util_Promo_Banner::instance()->token_markup('time', $data['tokens']['time']) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				);
			?></span>
		<?php endif; ?>

		<?php if ($data['button']) : ?>
			<a class="darkify-promo__button"
				href="<?php echo esc_url($data['button']['url']); ?>"
				<?php if ($data['button']['newTab']) : ?>target="_blank" rel="noopener"<?php endif; ?>><?php echo wp_kses($data['button']['text'], array('strong' => array(), 'em' => array())); ?></a>
		<?php endif; ?>
	</div>

	<?php if ($data['dismissible']) : ?>
		<button type="button" class="darkify-promo__close" data-darkify-promo-dismiss
			aria-label="<?php esc_attr_e('Dismiss this announcement', 'darkify-util'); ?>">
			<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
				<path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
			</svg>
		</button>
	<?php endif; ?>
</div>
<?php
/*
 * Hide a dismissed (or ended) banner before first paint.
 *
 * The view script loads in the footer; waiting for it would flash the bar at
 * someone who closed it yesterday. This is the minimum of that script's logic —
 * roll a recurring deadline forward, check the dismissal key — run inline, right
 * after the element it hides.
 */
?>
<script>(function(e){try{var d=+e.dataset.deadline,i=+e.dataset.interval,n=Date.now();if(i&&d&&n>=d){d+=(Math.floor((n-d)/i)+1)*i}if(+localStorage.getItem(e.dataset.key+':'+d)>n||(d&&!i&&n>=d&&e.dataset.expired==='hide')){e.hidden=true}}catch(x){}})(document.currentScript.previousElementSibling);</script>
