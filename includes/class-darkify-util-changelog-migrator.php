<?php

/**
 * One-way migration off the third-party changelog block.
 *
 * The old block keeps two copies of the same changelog: the raw text a human
 * pasted, in its `changelog` attribute, and a parsed JSON structure as the
 * block's inner content. The raw text is the better source — it is what the
 * author actually wrote, and our own parser reads it — so that is what this
 * migrates from, using the JSON only to check the result adds up.
 *
 * Two front ends, one implementation: `wp darkify-util changelog migrate` and
 * Tools → Darkify Changelog Import.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Changelog_Migrator')) {

    class Darkify_Util_Changelog_Migrator
    {
        const OLD_BLOCK = 'block/changeloger';
        const NEW_BLOCK = 'darkify-util/changelog';
        const PAGE_SLUG = 'darkify-util-changelog-import';

        /** @var Darkify_Util_Changelog_Migrator|null */
        private static $instance = null;

        public static function instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            add_action('admin_menu', array($this, 'register_page'));
            add_action('admin_post_darkify_util_changelog_migrate', array($this, 'handle_post'));

            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::add_command('darkify-util changelog', 'Darkify_Util_Changelog_CLI');
            }
        }

        /* --------------------------------------------------------------- */
        /* Finding the old blocks                                          */
        /* --------------------------------------------------------------- */

        /**
         * Posts still holding a third-party changelog block.
         *
         * @return int[]
         */
        public function find_posts()
        {
            global $wpdb;

            // Revisions are excluded deliberately. A page that has been edited
            // often carries the old block in dozens of revisions (this site had
            // ten of them), and rewriting those would edit history to no
            // purpose — the migration saves its own revision of the live post,
            // which is what makes it reversible.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core API finds a block by name across post types.
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_content LIKE %s
                       AND post_type NOT IN ('revision', 'auto-draft')
                       AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                     ORDER BY ID ASC",
                    '%' . $wpdb->esc_like('wp:' . self::OLD_BLOCK) . '%'
                )
            );

            return array_map('absint', (array) $ids);
        }

        /* --------------------------------------------------------------- */
        /* Migration                                                       */
        /* --------------------------------------------------------------- */

        /**
         * Convert every old block on a post.
         *
         * @param int  $post_id
         * @param bool $dry_run
         * @return array|WP_Error Report.
         */
        public function migrate_post($post_id, $dry_run = true)
        {
            $post = get_post($post_id);

            if (!$post) {
                return new WP_Error('darkify_util_no_post', __('That post does not exist.', 'darkify-util'));
            }

            $blocks  = parse_blocks($post->post_content);
            $report  = array(
                'post_id'  => (int) $post_id,
                'title'    => $post->post_title,
                'blocks'   => array(),
                'changed'  => false,
                'dry_run'  => (bool) $dry_run,
            );

            $converted = $this->convert_blocks($blocks, $report);

            if (!$report['blocks']) {
                $report['message'] = __('No third-party changelog blocks found.', 'darkify-util');
                return $report;
            }

            $content = serialize_blocks($converted);

            if ($dry_run) {
                $report['message'] = __('Dry run — nothing was written.', 'darkify-util');
                return $report;
            }

            // The revision is the safety net: the old block, raw JSON and all,
            // stays recoverable from the post's history.
            wp_save_post_revision($post_id);

            $args = array(
                'ID' => $post_id,
                // wp_update_post() unslashes what it is given, so unslashed
                // JSON loses every backslash in it — `\n` becomes `n` and
                // `\u0026` becomes `u0026`, quietly destroying the stored
                // source text while the parsed versions still look fine.
                'post_content' => wp_slash($content),
            );

            if ('page' === $post->post_type) {
                // A page whose saved template no longer exists in the active
                // theme fails wp_update_post()'s validation of a field this
                // migration never touches (the Changelogs page here points at
                // `wp-custom-template-default-page`, which the current block
                // theme does not provide). An empty value skips both that
                // validation and the meta write, leaving the page's own
                // template setting exactly as it was.
                $args['page_template'] = '';
            }

            $updated = wp_update_post($args, true);

            if (is_wp_error($updated)) {
                return $updated;
            }

            $report['changed'] = true;
            $report['message'] = __('Migrated.', 'darkify-util');

            return $report;
        }

        /**
         * Walk a block tree, replacing old changelog blocks as it goes.
         *
         * @param array $blocks
         * @param array $report
         * @return array
         */
        protected function convert_blocks($blocks, &$report)
        {
            foreach ($blocks as $index => $block) {
                if (!empty($block['innerBlocks'])) {
                    $blocks[$index]['innerBlocks'] = $this->convert_blocks($block['innerBlocks'], $report);
                }

                if (empty($block['blockName']) || self::OLD_BLOCK !== $block['blockName']) {
                    continue;
                }

                $result = $this->convert_block($block);

                $report['blocks'][] = $result['report'];
                $blocks[$index]     = $result['block'];
            }

            return $blocks;
        }

        /**
         * Convert one old block into ours.
         *
         * @param array $block
         * @return array `{ block, report }`
         */
        protected function convert_block($block)
        {
            $attrs = isset($block['attrs']) ? $block['attrs'] : array();
            $raw   = isset($attrs['changelog']) ? (string) $attrs['changelog'] : '';

            $parsed = Darkify_Util_Changelog_Parser::parse($raw);

            $versions = $parsed['versions'];
            $entries  = 0;
            foreach ($versions as $version) {
                $entries += count($version['entries']);
            }

            // Cross-check against the copy the old block kept as inner content.
            $inner    = json_decode(trim(isset($block['innerHTML']) ? $block['innerHTML'] : ''), true);
            $expected = array('versions' => null, 'entries' => null);

            if (is_array($inner) && isset($inner['changelog']) && is_array($inner['changelog'])) {
                $expected['versions'] = count($inner['changelog']);
                $expected['entries']  = 0;

                foreach ($inner['changelog'] as $old_version) {
                    $expected['entries'] += isset($old_version['changes']) ? count($old_version['changes']) : 0;

                    // Nested child versions are flattened onto the parent so
                    // nothing is lost; our model has no version tree.
                    if (!empty($old_version['children'])) {
                        $expected['entries'] += $this->count_child_changes($old_version['children']);
                    }
                }
            }

            $versions = $this->apply_custom_links(
                $versions,
                isset($attrs['customLinks']) ? (array) $attrs['customLinks'] : array()
            );

            $versions = $this->apply_version_names(
                $versions,
                isset($attrs['versionName']) ? (array) $attrs['versionName'] : array()
            );

            $new_attrs = array(
                'schemaVersion' => 1,
                'source'        => $raw,
                'versions'      => $versions,
                'categories'    => $parsed['categories'],
            );

            foreach (array(
                'perPage'          => isset($attrs['perPage']) ? absint($attrs['perPage']) : null,
                'versionsPosition' => isset($attrs['versionsPosition']) ? sanitize_key($attrs['versionsPosition']) : null,
            ) as $key => $value) {
                if ($value) {
                    $new_attrs[$key] = $value;
                }
            }

            if (isset($attrs['paginationType'])) {
                $type = sanitize_key($attrs['paginationType']);
                if (in_array($type, array('load-more', 'numbered'), true)) {
                    $new_attrs['paginationType'] = $type;
                }
            }

            if (isset($attrs['enableVersions']) && !$attrs['enableVersions']) {
                $new_attrs['versionsPosition'] = 'none';
            }

            $report = array(
                'versions'  => count($versions),
                'entries'   => $entries,
                'expected'  => $expected,
                'matches'   => (null === $expected['versions'])
                    || ((int) $expected['versions'] === count($versions) && (int) $expected['entries'] === $entries),
                'warnings'  => $parsed['warnings'],
                'links'     => count(isset($attrs['customLinks']) ? (array) $attrs['customLinks'] : array()),
            );

            return array(
                'report' => $report,
                'block'  => array(
                    'blockName'    => self::NEW_BLOCK,
                    'attrs'        => $new_attrs,
                    'innerBlocks'  => array(),
                    'innerHTML'    => '',
                    'innerContent' => array(),
                ),
            );
        }

        /**
         * @param array $children
         * @return int
         */
        protected function count_child_changes($children)
        {
            $count = 0;

            foreach ((array) $children as $child) {
                $count += isset($child['changes']) ? count($child['changes']) : 0;

                if (!empty($child['children'])) {
                    $count += $this->count_child_changes($child['children']);
                }
            }

            return $count;
        }

        /**
         * The old block keyed links by version number and allowed several per
         * version; ours puts one link on an entry. They are handed out in order
         * across the version's entries so none is dropped.
         *
         * @param array $versions
         * @param array $custom_links
         * @return array
         */
        protected function apply_custom_links($versions, $custom_links)
        {
            if (!$custom_links) {
                return $versions;
            }

            foreach ($versions as $index => $version) {
                $number = $version['version'];

                if (empty($custom_links[$number]) || !is_array($custom_links[$number])) {
                    continue;
                }

                foreach (array_values($custom_links[$number]) as $position => $link) {
                    if (!isset($versions[$index]['entries'][$position])) {
                        break;
                    }

                    $url = isset($link['link']) ? esc_url_raw($link['link']) : '';

                    if ('' === $url || '#' === $url) {
                        continue;
                    }

                    $versions[$index]['entries'][$position]['link'] = array(
                        'url'   => $url,
                        'label' => isset($link['name']) ? sanitize_text_field($link['name']) : '',
                    );
                }
            }

            return $versions;
        }

        /**
         * @param array $versions
         * @param array $names
         * @return array
         */
        protected function apply_version_names($versions, $names)
        {
            if (!$names) {
                return $versions;
            }

            foreach ($versions as $index => $version) {
                if (!empty($names[$version['version']])) {
                    $versions[$index]['label'] = sanitize_text_field($names[$version['version']]);
                }
            }

            return $versions;
        }

        /* --------------------------------------------------------------- */
        /* Admin screen                                                    */
        /* --------------------------------------------------------------- */

        public function register_page()
        {
            add_management_page(
                __('Darkify Changelog Import', 'darkify-util'),
                __('Darkify Changelog Import', 'darkify-util'),
                'edit_posts',
                self::PAGE_SLUG,
                array($this, 'render_page')
            );
        }

        public function render_page()
        {
            if (!current_user_can('edit_posts')) {
                wp_die(esc_html__('You are not allowed to do that.', 'darkify-util'));
            }

            $posts  = $this->find_posts();
            $notice = isset($_GET['migrated']) ? absint($_GET['migrated']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Darkify Changelog Import', 'darkify-util'); ?></h1>

                <?php if ($notice) : ?>
                    <div class="notice notice-success"><p>
                        <?php
                        /* translators: %d: post ID. */
                        printf(esc_html__('Post %d migrated. A revision of the previous content was saved.', 'darkify-util'), (int) $notice);
                        ?>
                    </p></div>
                <?php endif; ?>

                <p>
                    <?php esc_html_e('Converts third-party changelog blocks into the first-party Darkify Changelog block. The previous content is kept as a post revision.', 'darkify-util'); ?>
                </p>

                <?php if (!$posts) : ?>
                    <p><strong><?php esc_html_e('Nothing to migrate.', 'darkify-util'); ?></strong></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Post', 'darkify-util'); ?></th>
                                <th><?php esc_html_e('Versions', 'darkify-util'); ?></th>
                                <th><?php esc_html_e('Entries', 'darkify-util'); ?></th>
                                <th><?php esc_html_e('Cross-check', 'darkify-util'); ?></th>
                                <th><?php esc_html_e('Action', 'darkify-util'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post_id) : ?>
                                <?php
                                $preview  = $this->migrate_post($post_id, true);
                                $versions = 0;
                                $entries  = 0;
                                $matches  = true;

                                if (!is_wp_error($preview)) {
                                    foreach ($preview['blocks'] as $block_report) {
                                        $versions += $block_report['versions'];
                                        $entries  += $block_report['entries'];
                                        $matches   = $matches && $block_report['matches'];
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                            <?php echo esc_html(get_the_title($post_id)); ?>
                                        </a>
                                        <code>#<?php echo (int) $post_id; ?></code>
                                    </td>
                                    <td><?php echo (int) $versions; ?></td>
                                    <td><?php echo (int) $entries; ?></td>
                                    <td>
                                        <?php echo $matches
                                            ? esc_html__('Counts match', 'darkify-util')
                                            : esc_html__('Counts differ — review after migrating', 'darkify-util'); ?>
                                    </td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="darkify_util_changelog_migrate">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>">
                                            <?php wp_nonce_field('darkify_util_changelog_migrate_' . $post_id); ?>
                                            <button type="submit" class="button button-primary">
                                                <?php esc_html_e('Migrate', 'darkify-util'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php
        }

        public function handle_post()
        {
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

            check_admin_referer('darkify_util_changelog_migrate_' . $post_id);

            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                wp_die(esc_html__('You are not allowed to edit that post.', 'darkify-util'));
            }

            $result = $this->migrate_post($post_id, false);

            wp_safe_redirect(add_query_arg(
                array(
                    'page'     => self::PAGE_SLUG,
                    'migrated' => is_wp_error($result) ? 0 : $post_id,
                ),
                admin_url('tools.php')
            ));
            exit;
        }
    }
}

if (defined('WP_CLI') && WP_CLI && !class_exists('Darkify_Util_Changelog_CLI')) {

    /**
     * Migrate third-party changelog blocks to darkify-util/changelog.
     */
    class Darkify_Util_Changelog_CLI
    {
        /**
         * Convert every third-party changelog block into the first-party block.
         *
         * ## OPTIONS
         *
         * [--post=<id>]
         * : Only this post. Defaults to every post holding an old block.
         *
         * [--dry-run]
         * : Report what would change and write nothing.
         *
         * [--yes]
         * : Skip the confirmation prompt.
         *
         * ## EXAMPLES
         *
         *     wp darkify-util changelog migrate --dry-run
         *     wp darkify-util changelog migrate --post=26 --yes
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function migrate($args, $assoc_args)
        {
            $migrator = Darkify_Util_Changelog_Migrator::instance();
            $dry_run  = isset($assoc_args['dry-run']);
            $post_id  = isset($assoc_args['post']) ? absint($assoc_args['post']) : 0;
            $posts    = $post_id ? array($post_id) : $migrator->find_posts();

            if (!$posts) {
                WP_CLI::success('Nothing to migrate.');
                return;
            }

            if (!$dry_run) {
                WP_CLI::confirm(
                    sprintf('Migrate %d post(s)? A revision of each will be saved first.', count($posts)),
                    $assoc_args
                );
            }

            $rows = array();

            foreach ($posts as $id) {
                $report = $migrator->migrate_post($id, $dry_run);

                if (is_wp_error($report)) {
                    WP_CLI::warning(sprintf('#%d: %s', $id, $report->get_error_message()));
                    continue;
                }

                if (!$report['blocks']) {
                    WP_CLI::log(sprintf('#%d %s — no old blocks.', $id, $report['title']));
                    continue;
                }

                foreach ($report['blocks'] as $block) {
                    $expected = $block['expected'];

                    $rows[] = array(
                        'post'     => $id,
                        'title'    => $report['title'],
                        'versions' => $block['versions'],
                        'entries'  => $block['entries'],
                        'expected' => null === $expected['versions']
                            ? 'n/a'
                            : $expected['versions'] . ' / ' . $expected['entries'],
                        'check'    => $block['matches'] ? 'ok' : 'MISMATCH',
                        'links'    => $block['links'],
                        'warnings' => count($block['warnings']),
                    );

                    foreach ($block['warnings'] as $warning) {
                        WP_CLI::warning(sprintf(
                            '#%d line %d could not be read: %s',
                            $id,
                            $warning['line'],
                            $warning['text']
                        ));
                    }
                }
            }

            if ($rows) {
                WP_CLI\Utils\format_items(
                    'table',
                    $rows,
                    array('post', 'title', 'versions', 'entries', 'expected', 'check', 'links', 'warnings')
                );
            }

            if ($dry_run) {
                WP_CLI::success('Dry run complete — nothing was written.');
            } else {
                WP_CLI::success(sprintf('Migrated %d post(s).', count($rows)));
            }
        }
    }
}
