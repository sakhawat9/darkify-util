<?php

/**
 * The sample site inside the hero preview.
 *
 * Two views live in this document, not one: the sample front end, and a mock of
 * the WordPress admin running Darkify's own settings screen. The loop walks
 * between them — front end light, front end dark, then over to wp-admin and the
 * same flip again — so the preview shows both halves of what the plugin does.
 *
 * The front end is a plain light-mode page with no dark-mode styling of its
 * own, and so is the admin mock: the demo is only honest if every dark colour a
 * visitor sees is one Darkify's engine derived. It is deliberately busier than
 * the interactive demo's sample — a nav, a headline, body copy, two button
 * styles, a media block and a row of cards on the front end; a admin bar, a
 * menu and a settings card in the admin — so the flip has something to show on
 * every kind of surface a real page has.
 *
 * `darkify_ignore` is Darkify's own opt-out (the runtime marker behind its
 * Disallowed Elements setting). It is on the brand marks — the engine reads a
 * solid coloured box as a surface and maps it into the palette, which is right
 * for a page's own panels and wrong for a brand colour — on the content
 * placeholders, which are translucent so that what shows through them is
 * whatever Darkify painted underneath, and on the admin bar and its controls,
 * which are dark in WordPress to begin with and stay dark in both modes.
 * See darkify-hero-frame.css.
 *
 * @var array $data Prepared by Darkify_Util_Hero::render().
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
/*
 * The veil is what makes the flip seamless.
 *
 * Darkify repaints the sample page in one frame — body background included —
 * and a background cannot be cross-faded from outside: the engine suspends CSS
 * transitions while it reads colours, and the page's background is painted on
 * the canvas *behind* the content, so dimming the content does not dim it. The
 * result was a white-to-black slam at full contrast: the flash.
 *
 * So the swap happens behind cover. The veil takes the page's current colour,
 * fades in, the switch is thrown while it is opaque, and it fades out onto the
 * new colour — one continuous cross-dissolve with nothing to catch the eye. The
 * walk to wp-admin rides the same cover, held a beat longer so it reads as a
 * page load. `darkify_ignore` keeps the engine's hands off the veil itself, and
 * it sits below the switcher and the admin bar so whichever control is being
 * clicked stays visible throughout.
 */
?>
<div class="dkfh-veil darkify_ignore" aria-hidden="true"></div>

<div class="dkfh-stage">
	<div class="dkfh-view dkfh-view--site">
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
	</div>

	<?php if ($data['admin']) : ?>
		<?php
		/*
		 * The admin half: Darkify's settings screen inside a mock of wp-admin.
		 *
		 * Laid over the front end rather than beside it — absolutely positioned,
		 * opaque, and only faded in while the veil is closed — so the two views
		 * are exactly the same size and the window never resizes between them.
		 *
		 * Nothing here is a screenshot. It is markup in the same document the
		 * engine is already running against, so the dark admin a visitor sees is
		 * Darkify deriving these colours live, the same way it would on their own
		 * dashboard.
		 */
		$dkfa_menu = array(
			array(
				'label' => __('Controls', 'darkify-util'),
				'icon'  => '<path d="M3 6h2m4 0h9M3 12h9m4 0h5M3 18h5m4 0h9"/><circle cx="7" cy="6" r="2"/><circle cx="14" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>',
				'open'  => true,
				'kids'  => array(
					array(
                        'label'  => __('Frontend', 'darkify-util'),
                        'icon'   => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M9 20h6"/>',
                        'active' => false,
                    ),
					array(
                        'label'  => __('Admin', 'darkify-util'),
                        'icon'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
                        'active' => true,
                    ),
				),
			),
			array(
				'label' => __('Switches', 'darkify-util'),
				'icon'  => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/>',
				'more'  => true,
			),
			array(
				'label' => __('Colors', 'darkify-util'),
				'icon'  => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.6-.8 1.6-1.7 0-1.6 1.2-2.3 2.4-2.3H18a4 4 0 0 0 4-4c0-5.5-4.5-10-10-10Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="10" r="1"/>',
				'more'  => true,
			),
			array(
				'label' => __('Media', 'darkify-util'),
				'icon'  => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>',
				'more'  => true,
			),
			array(
				'label' => __('Advanced', 'darkify-util'),
				'icon'  => '<path d="M4 17c4 0 4-10 8-10s4 10 8 10"/>',
				'more'  => true,
			),
			array(
				'label' => __('Tools', 'darkify-util'),
				'icon'  => '<path d="M14.5 5.5a4 4 0 0 0 5 5l-9 9a2.8 2.8 0 0 1-4-4l9-9Z"/>',
			),
			array(
				'label' => __('License', 'darkify-util'),
				'icon'  => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9m-3 0v3m-2-3v2.5"/>',
			),
		);

		$dkfa_rows = array(
			array(
				'title'   => __('Admin Panel Dark Mode', 'darkify-util'),
				'text'    => __('Enable dark mode in the WordPress admin dashboard.', 'darkify-util'),
				'control' => 'toggle',
			),
			array(
				'title'   => __('Admin Panel Palette', 'darkify-util'),
				'text'    => __('Color preset for the WordPress admin. Auto lets Darkify derive each color from the original.', 'darkify-util'),
				'control' => 'select',
			),
			array(
				'title'   => __('Block Editor Dark Mode', 'darkify-util'),
				'text'    => __('Enable the dark mode switch in the block editor to toggle between dark and light mode.', 'darkify-util'),
				'control' => 'toggle',
			),
			array(
				'title'   => __('Block Editor Palette', 'darkify-util'),
				'text'    => __('Color preset for the block editor, independent of the rest of the admin.', 'darkify-util'),
				'control' => 'select',
			),
			array(
				'title'   => __('Classic Editor Dark Mode', 'darkify-util'),
				'text'    => __('Enable the dark mode switch in the classic editor to toggle between dark and light modes.', 'darkify-util'),
				'control' => 'toggle',
			),
		);
		?>
		<div class="dkfh-view dkfh-view--admin" aria-hidden="true">
			<div class="dkfa">
				<?php
				/*
				 * The admin bar keeps its own colours in both modes — that is what
				 * WordPress does, and what the plugin leaves alone — so it carries
				 * `darkify_ignore`. It is also the one strip that stays above the
				 * veil, because the moon the pointer is about to click lives here
				 * and has to be visible while the page dissolves behind it.
				 */
				?>
				<div class="dkfa-bar darkify_ignore">
					<span class="dkfa-bar__group">
						<span class="dkfa-bar__item dkfa-bar__item--home">
							<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3.2 10.5h17.6M3.2 13.5h17.6M12 3c2.5 3 2.5 15 0 18M12 3c-2.5 3-2.5 15 0 18"/></svg>
						</span>
						<span class="dkfa-bar__item"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v5h-5"/></svg>12</span>
						<span class="dkfa-bar__item"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5"/></svg><b>&#8984;K</b></span>
						<span class="dkfa-bar__item"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4v12h4v4l5-4h7V4Z"/></svg>0</span>
						<span class="dkfa-bar__item"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><?php esc_html_e('New', 'darkify-util'); ?></span>
					</span>

					<span class="dkfa-bar__group dkfa-bar__group--end">
						<span class="dkfa-bar__item"><?php
							/* translators: %s: sample user name. */
							echo esc_html(sprintf(__('Howdy, %s', 'darkify-util'), $data['admin_user']));
						?><span class="dkfa-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg></span></span>

						<?php
						/*
						 * Darkify's admin switch. Inert markup: the loop throws the
						 * real switcher, and this is what the pointer appears to
						 * press — the icon follows the engine's own state class, so
						 * it can never disagree with the page behind it.
						 */
						?>
						<span class="dkfa-toggle darkify_ignore" aria-hidden="true">
							<svg class="dkfa-toggle__moon" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/></svg>
							<svg class="dkfa-toggle__sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.2 12H2m20 0h-2.2M6.3 6.3 4.8 4.8m14.4 14.4-1.5-1.5M6.3 17.7l-1.5 1.5M19.2 4.8l-1.5 1.5"/></svg>
						</span>
					</span>
				</div>

				<div class="dkfa-body">
					<div class="dkfa-side">
						<div class="dkfa-side__head">
							<span class="dkfa-logo darkify_ignore" aria-hidden="true">
								<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2.6v3M12 18.4v3M2.6 12h3m12.8 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"/></svg>
							</span>
							<span class="dkfa-side__title">
								<b><?php echo esc_html($data['admin_brand']); ?></b>
								<em><?php echo esc_html($data['admin_version']); ?></em>
							</span>
							<span class="dkfa-side__collapse" aria-hidden="true">
								<svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M9.5 4.5v15M17 9.5 14 12l3 2.5"/></svg>
							</span>
						</div>

						<div class="dkfa-menu">
							<?php foreach ($dkfa_menu as $dkfa_item) : ?>
								<span class="dkfa-menu__item<?php echo empty($dkfa_item['open']) ? '' : ' is-open'; ?>">
									<svg viewBox="0 0 24 24" aria-hidden="true"><?php
										echo $dkfa_item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup above.
									?></svg>
									<em><?php echo esc_html($dkfa_item['label']); ?></em>
									<?php if (!empty($dkfa_item['open']) || !empty($dkfa_item['more'])) : ?>
										<svg class="dkfa-menu__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
									<?php endif; ?>
								</span>

								<?php if (!empty($dkfa_item['kids'])) : ?>
									<span class="dkfa-menu__sub">
										<?php foreach ($dkfa_item['kids'] as $dkfa_kid) : ?>
											<span class="dkfa-menu__item dkfa-menu__item--sub<?php echo empty($dkfa_kid['active']) ? '' : ' is-active'; ?>">
												<svg viewBox="0 0 24 24" aria-hidden="true"><?php
													echo $dkfa_kid['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup above.
												?></svg>
												<em><?php echo esc_html($dkfa_kid['label']); ?></em>
											</span>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="dkfa-main">
						<div class="dkfa-head">
							<span class="dkfa-head__title">
								<em><?php esc_html_e('Controls', 'darkify-util'); ?></em>
								<b><?php esc_html_e('Admin', 'darkify-util'); ?></b>
							</span>
							<span class="dkfa-head__tools">
								<span class="dkfa-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5"/></svg></span>
								<span class="dkfa-save"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 4.5h11l4 4v11h-15Z"/><path d="M8 4.5v5h7M8 19.5v-6h8v6"/></svg><?php esc_html_e('Save', 'darkify-util'); ?></span>
								<span class="dkfa-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/></svg></span>
							</span>
						</div>

						<div class="dkfa-content">
							<p class="dkfa-intro"><?php esc_html_e('Bring dark mode to the WordPress admin dashboard and editors.', 'darkify-util'); ?> <a class="dkfa-link"><?php esc_html_e('View documentation', 'darkify-util'); ?></a></p>

							<span class="dkfa-section"><?php esc_html_e('Admin appearance', 'darkify-util'); ?></span>

							<div class="dkfa-card">
								<?php foreach ($dkfa_rows as $dkfa_row) : ?>
									<div class="dkfa-row">
										<span class="dkfa-row__text">
											<b><?php echo esc_html($dkfa_row['title']); ?></b>
											<em><?php echo esc_html($dkfa_row['text']); ?></em>
											<?php if ('select' === $dkfa_row['control']) : ?>
												<span class="dkfa-select">
													<span class="dkfa-select__dot darkify_ignore" aria-hidden="true"></span>
													<?php esc_html_e('Auto (recommended)', 'darkify-util'); ?>
													<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
												</span>
											<?php endif; ?>
										</span>

										<?php if ('toggle' === $dkfa_row['control']) : ?>
											<span class="dkfa-onoff">
												<em><?php esc_html_e('On', 'darkify-util'); ?></em>
												<span class="dkfa-switch darkify_ignore" aria-hidden="true"><i></i></span>
											</span>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php if ($data['switcher']) : ?>
	<?php
	/*
	 * Darkify's own switcher, along for the ride: it is the one part of the
	 * preview the engine never repaints (`.darkify_switch` is excluded), so its
	 * sun-to-moon morph plays through every flip while the page behind it
	 * changes. Inert — the loop drives the state, and a stray click would fight
	 * it. It belongs to the front end, so it steps aside while the admin view is
	 * on screen, where the admin bar's own moon takes over.
	 */
	?>
	<div class="dkfh-fab darkify_ignore" aria-hidden="true"><?php
		echo $this->switcher_markup($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></div>
<?php endif; ?>
