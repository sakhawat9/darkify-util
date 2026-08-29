<?php

/**
 * The changelog component.
 *
 * Server-rendered in full: every version, every entry, the search field and the
 * load-more button are all in the HTML before any JavaScript runs. The view
 * module only hides things (the tail of a load-more list, the search field it
 * knows how to drive) — so with JavaScript off the page is a complete,
 * readable changelog rather than an empty shell.
 *
 * @var array $data Prepared by Darkify_Util_Changelog::prepare().
 */

if (!defined('ABSPATH')) {
    exit;
}

$dkfc_has_versions = !empty($data['versions']);
$dkfc_has_nav      = 'none' !== $data['versionsPosition'] && $dkfc_has_versions;
$dkfc_hide_after   = 'load-more' === $data['paginationType'] ? (int) $data['perPage'] : 0;
?>
<div <?php echo $data['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output. ?>
	id="<?php echo esc_attr($data['instance']); ?>"
	data-darkify-changelog
	data-position="<?php echo esc_attr($data['versionsPosition']); ?>"
	data-pagination="<?php echo esc_attr($data['paginationType']); ?>"
	data-per-page="<?php echo esc_attr($data['perPage']); ?>"
	data-collapsible="<?php echo $data['collapsible'] ? '1' : '0'; ?>">

	<?php if (!$dkfc_has_versions) : ?>

		<p class="darkify-changelog__empty">
			<?php esc_html_e('No changelog entries yet.', 'darkify-util'); ?>
		</p>

	<?php else : ?>

		<?php if ($data['showFilters'] && count($data['filters']) > 1) : ?>
			<?php
			/*
			 * Rendered by PHP with every chip present and the counts already
			 * computed, so the bar is readable (if inert) without JavaScript.
			 * The view module turns it into a filter.
			 */
			?>
			<div class="darkify-changelog__filters" data-changelog-filters role="group"
				aria-label="<?php esc_attr_e('Filter changes by category', 'darkify-util'); ?>">
				<button type="button"
					class="darkify-changelog__filter is-active"
					data-filter="all"
					aria-pressed="true">
					<span class="darkify-changelog__filter-label"><?php esc_html_e('All', 'darkify-util'); ?></span>
					<span class="darkify-changelog__filter-count"><?php echo esc_html(number_format_i18n($data['entriesTotal'])); ?></span>
				</button>

				<?php foreach ($data['filters'] as $dkfc_filter) : ?>
					<button type="button"
						class="darkify-changelog__filter"
						data-filter="<?php echo esc_attr($dkfc_filter['slug']); ?>"
						style="--darkify-changelog-filter: <?php echo esc_attr($dkfc_filter['color']); ?>"
						aria-pressed="false">
						<span class="darkify-changelog__filter-label"><?php echo esc_html($dkfc_filter['label']); ?></span>
						<span class="darkify-changelog__filter-count"><?php echo esc_html(number_format_i18n($dkfc_filter['count'])); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="darkify-changelog__layout darkify-changelog__layout--<?php echo esc_attr($data['versionsPosition']); ?>">

			<?php if ($dkfc_has_nav) : ?>
				<nav class="darkify-changelog__nav" aria-label="<?php esc_attr_e('Versions', 'darkify-util'); ?>">
					<h2 class="darkify-changelog__nav-title"><?php esc_html_e('Version', 'darkify-util'); ?></h2>
					<ul class="darkify-changelog__nav-list">
						<?php foreach ($data['versions'] as $dkfc_version) : ?>
							<li class="darkify-changelog__nav-item">
								<a class="darkify-changelog__nav-link"
									href="#<?php echo esc_attr($dkfc_version['slug']); ?>"
									data-version-link="<?php echo esc_attr($dkfc_version['slug']); ?>">
									<span class="darkify-changelog__nav-number">
										<?php
										/* translators: %s: version number. */
										printf(esc_html__('Version %s', 'darkify-util'), esc_html($dkfc_version['version']));
										?>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<div class="darkify-changelog__main">

				<?php if ($data['showSearch']) : ?>
					<?php
					/*
					 * `hidden` until the view module removes it: the filtering is
					 * client-side, so offering the field with no JavaScript would
					 * be offering a control that does nothing.
					 */
					?>
					<div class="darkify-changelog__search" data-changelog-search hidden>
						<label class="screen-reader-text" for="<?php echo esc_attr($data['instance']); ?>-search">
							<?php esc_html_e('Search the changelog', 'darkify-util'); ?>
						</label>
						<input
							type="search"
							class="darkify-changelog__search-input"
							id="<?php echo esc_attr($data['instance']); ?>-search"
							placeholder="<?php esc_attr_e('Search versions and changes…', 'darkify-util'); ?>"
							autocomplete="off">
					</div>
				<?php endif; ?>

				<p class="darkify-changelog__no-results" data-changelog-no-results hidden role="status">
					<?php esc_html_e('No changes match your search.', 'darkify-util'); ?>
				</p>

				<div class="darkify-changelog__versions">
					<?php foreach ($data['versions'] as $dkfc_index => $dkfc_version) : ?>
						<?php
						$dkfc_body_id = $data['instance'] . '-body-' . $dkfc_index;
						$dkfc_beyond  = $dkfc_hide_after > 0 && $dkfc_index >= $dkfc_hide_after;
						?>
						<article
							class="darkify-changelog__version"
							id="<?php echo esc_attr($dkfc_version['slug']); ?>"
							data-version="<?php echo esc_attr($dkfc_version['slug']); ?>"
							data-index="<?php echo esc_attr($dkfc_index); ?>"
							<?php echo $dkfc_beyond ? ' data-beyond-page="1"' : ''; ?>>

							<header class="darkify-changelog__version-header">
								<h2 class="darkify-changelog__version-number">
									<?php if ($data['collapsible']) : ?>
										<button
											type="button"
											class="darkify-changelog__toggle"
											aria-expanded="true"
											aria-controls="<?php echo esc_attr($dkfc_body_id); ?>">
											<span class="darkify-changelog__pill"><?php echo esc_html($dkfc_version['version']); ?></span>
											<span class="darkify-changelog__toggle-icon" aria-hidden="true"></span>
										</button>
									<?php else : ?>
										<span class="darkify-changelog__pill"><?php echo esc_html($dkfc_version['version']); ?></span>
									<?php endif; ?>
								</h2>

								<?php if ($data['showDates'] && '' !== $dkfc_version['date']) : ?>
									<?php if ('' !== $dkfc_version['dateISO']) : ?>
										<time class="darkify-changelog__version-date" datetime="<?php echo esc_attr($dkfc_version['dateISO']); ?>">
											<?php echo esc_html($dkfc_version['date']); ?>
										</time>
									<?php else : ?>
										<span class="darkify-changelog__version-date"><?php echo esc_html($dkfc_version['date']); ?></span>
									<?php endif; ?>
								<?php endif; ?>

								<?php if ($dkfc_version['slug'] === $data['latestSlug']) : ?>
									<span class="darkify-changelog__latest"><?php esc_html_e('Latest', 'darkify-util'); ?></span>
								<?php endif; ?>

								<?php if ('' !== $dkfc_version['label']) : ?>
									<span class="darkify-changelog__version-label"><?php echo esc_html($dkfc_version['label']); ?></span>
								<?php endif; ?>

							</header>

							<ul class="darkify-changelog__entries" id="<?php echo esc_attr($dkfc_body_id); ?>">
								<?php foreach ($dkfc_version['entries'] as $dkfc_entry) : ?>
									<?php
									$dkfc_category = isset($data['categories'][$dkfc_entry['category']])
										? $data['categories'][$dkfc_entry['category']]
										: array('slug' => $dkfc_entry['category'], 'label' => $dkfc_entry['category'], 'color' => '#64748b');
									?>
									<li class="darkify-changelog__entry" data-category="<?php echo esc_attr($dkfc_category['slug']); ?>">
										<?php if ($data['showBadges']) : ?>
											<span class="darkify-changelog__badge"
												style="--darkify-changelog-badge: <?php echo esc_attr($dkfc_category['color']); ?>">
												<?php echo esc_html($dkfc_category['label']); ?>
											</span>
										<?php endif; ?>

										<span class="darkify-changelog__entry-text">
											<?php echo wp_kses_post($dkfc_entry['text']); ?>

											<?php if ('' !== $dkfc_entry['link']['url']) : ?>
												<a class="darkify-changelog__entry-link"
													href="<?php echo esc_url($dkfc_entry['link']['url']); ?>">
													<?php
													echo esc_html(
														'' !== $dkfc_entry['link']['label']
															? $dkfc_entry['link']['label']
															: __('Details', 'darkify-util')
													);
													?>
												</a>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ('load-more' === $data['paginationType'] && $data['total'] > $data['perPage']) : ?>
					<?php
					/*
					 * Also `hidden` until the view module takes over, and for the
					 * same reason as the search field: without JavaScript every
					 * version is already on the page, so a "load more" button
					 * would have nothing left to load.
					 */
					?>
					<div class="darkify-changelog__more" hidden data-changelog-more>
						<button type="button" class="darkify-changelog__more-button">
							<?php echo esc_html($data['loadMoreText']); ?>
						</button>
						<p class="darkify-changelog__more-status" aria-live="polite"></p>
					</div>
				<?php endif; ?>

				<?php if ('numbered' === $data['paginationType'] && $data['totalPages'] > 1) : ?>
					<nav class="darkify-changelog__pagination" aria-label="<?php esc_attr_e('Changelog pages', 'darkify-util'); ?>">
						<?php
						$dkfc_links = paginate_links(array(
							'base'      => add_query_arg('cl-page', '%#%'),
							'format'    => '',
							'current'   => $data['currentPage'],
							'total'     => $data['totalPages'],
							'type'      => 'array',
							'prev_text' => __('Previous', 'darkify-util'),
							'next_text' => __('Next', 'darkify-util'),
						));
						?>
						<?php if ($dkfc_links) : ?>
							<ul class="darkify-changelog__pagination-list">
								<?php foreach ($dkfc_links as $dkfc_link) : ?>
									<li class="darkify-changelog__pagination-item">
										<?php echo wp_kses_post($dkfc_link); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</nav>
				<?php endif; ?>

			</div>
		</div>

	<?php endif; ?>
</div>
