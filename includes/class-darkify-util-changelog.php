<?php

/**
 * [darkify_changelog] and the darkify-util/changelog block.
 *
 * A first-party replacement for the third-party changelog block the Changelogs
 * page used to depend on, written to make that block's failure mode impossible.
 *
 * That block stored its data as the block's *inner content*. WordPress prints
 * the inner content of an unregistered block verbatim, so the day the plugin
 * was deactivated the page started rendering 40KB of raw JSON at visitors. This
 * one is dynamic: everything lives in attributes inside the block comment,
 * `save()` returns null, and there is therefore no inner content to leak. With
 * this plugin switched off the block renders nothing at all.
 *
 * Markup lives in templates/changelog.php, like the previews' markup, so the
 * render path here is only ever: sanitise attributes, derive what the template
 * needs, hand it over.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Changelog')) {

    class Darkify_Util_Changelog
    {
        const SHORTCODE  = 'darkify_changelog';
        const BLOCK_NAME = 'darkify-util/changelog';
        const CATEGORY   = 'darkify';

        /**
         * The wrapper's classes.
         *
         * `darkify_ignore` is Darkify's own opt-out, and it is load-bearing
         * here. This block ships its own dark palette (see style.scss, which
         * redefines its custom properties under Darkify's dark-mode class), so
         * letting the engine repaint it as well means two systems colouring the
         * same pixels: measured, the engine flattened every category badge to
         * its own grey, losing the colours the inspector had set. Excluding the
         * subtree leaves the block's own dark mode in charge — the tokens still
         * switch, because they key off the class Darkify puts on <html>.
         */
        const WRAPPER_CLASS = 'darkify-changelog darkify_ignore';

        /** @var Darkify_Util_Changelog|null */
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
            add_action('init', array($this, 'register_block'));
            add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

            // `block_categories_all` is the 5.8+ hook; the older name is kept as
            // a fallback so the category still appears if a site pins an older
            // WordPress. Registration failing is not fatal — block.json declares
            // `widgets` as the category, and this filter promotes it.
            if (function_exists('get_block_categories')) {
                add_filter('block_categories_all', array($this, 'register_block_category'), 10, 1);
            } else {
                add_filter('block_categories', array($this, 'register_block_category'), 10, 1);
            }
        }

        /* --------------------------------------------------------------- */
        /* Registration                                                    */
        /* --------------------------------------------------------------- */

        /**
         * Register the block from its built block.json.
         *
         * The build directory is the registration target (not src): it holds the
         * compiled scripts, the copied block.json and the copied render.php.
         */
        public function register_block()
        {
            $build = DARKIFY_UTIL_PATH . 'blocks/changelog/build';

            if (!file_exists($build . '/block.json')) {
                // Built assets missing (a fresh checkout before `npm run build`).
                // The shortcode still works; the block simply is not offered.
                return;
            }

            $type = register_block_type($build);

            if ($type && !empty($type->editor_script_handles)) {
                foreach ($type->editor_script_handles as $handle) {
                    wp_set_script_translations($handle, 'darkify-util', DARKIFY_UTIL_PATH . 'languages');
                }
            }
        }

        /**
         * Add a first-party "Darkify" category to the inserter.
         *
         * @param array $categories
         * @return array
         */
        public function register_block_category($categories)
        {
            foreach ((array) $categories as $category) {
                if (isset($category['slug']) && self::CATEGORY === $category['slug']) {
                    return $categories;
                }
            }

            array_unshift($categories, array(
                'slug'  => self::CATEGORY,
                'title' => __('Darkify', 'darkify-util'),
                'icon'  => null,
            ));

            return $categories;
        }

        /* --------------------------------------------------------------- */
        /* Rendering                                                       */
        /* --------------------------------------------------------------- */

        /**
         * Render the block. Called from blocks/changelog/build/render.php.
         *
         * @param array         $attributes
         * @param string        $content
         * @param WP_Block|null $block
         * @return string
         */
        public function render_block($attributes, $content = '', $block = null)
        {
            $wrapper = function_exists('get_block_wrapper_attributes')
                ? get_block_wrapper_attributes(array('class' => self::WRAPPER_CLASS))
                : 'class="' . esc_attr(self::WRAPPER_CLASS) . '"';

            return $this->render($attributes, $wrapper);
        }

        /**
         * Render from anywhere: block, shortcode, or a template calling in.
         *
         * @param array  $attributes Raw (unsanitised) attributes.
         * @param string $wrapper    Pre-built wrapper attributes, or ''.
         * @return string
         */
        public function render($attributes, $wrapper = '')
        {
            $data = $this->prepare($this->sanitize_attributes($attributes));

            if ('' === $wrapper) {
                $wrapper = 'class="' . esc_attr(self::WRAPPER_CLASS) . '"';
            }

            $data['wrapper'] = $wrapper;

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/changelog.php';
            return ob_get_clean();
        }

        /**
         * Everything the template needs that is not simply an attribute:
         * anchors, the category lookup, and which versions this page shows.
         *
         * @param array $attributes Sanitised attributes.
         * @return array
         */
        protected function prepare($attributes)
        {
            $versions = $attributes['versions'];
            $total    = count($versions);
            $per_page = max(1, (int) $attributes['perPage']);
            $pages    = (int) ceil($total / $per_page);
            $current  = 1;

            // Numbered pagination is the only mode that pages server-side, so it
            // keeps working with JavaScript switched off. Load-more renders the
            // whole list and lets the view module hide the tail — which means no
            // JavaScript simply shows everything, rather than showing ten.
            if ('numbered' === $attributes['paginationType'] && $pages > 1) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
                $requested = isset($_GET['cl-page']) ? absint($_GET['cl-page']) : 1;
                $current   = min(max(1, $requested), $pages);
                $versions  = array_slice($versions, ($current - 1) * $per_page, $per_page);
            }

            $categories = array();
            foreach ($attributes['categories'] as $category) {
                $categories[$category['slug']] = $category;
            }

            // Filter counts are of the entire changelog, not the page on screen:
            // a chip reading "Fixed 46" has to mean the same thing before and
            // after Load More.
            $counts = array();
            $entries_total = 0;

            foreach ($attributes['versions'] as $version) {
                foreach ($version['entries'] as $entry) {
                    $slug = $entry['category'];
                    $counts[$slug] = isset($counts[$slug]) ? $counts[$slug] + 1 : 1;
                    $entries_total++;
                }
            }

            // Only categories actually used get a chip — an empty "Removed 0"
            // is a dead control.
            $filters = array();
            foreach ($attributes['categories'] as $category) {
                if (!empty($counts[$category['slug']])) {
                    $filters[] = array_merge($category, array('count' => $counts[$category['slug']]));
                }
            }

            foreach ($versions as $index => $version) {
                $versions[$index]['slug'] = Darkify_Util_Changelog_Parser::version_slug($version['version']);
            }

            return array_merge($attributes, array(
                'versions'     => $versions,
                'categories'   => $categories,
                'filters'      => $filters,
                'entriesTotal' => $entries_total,
                'total'        => $total,
                'totalPages'   => $pages,
                'currentPage'  => $current,
                // The newest version carries a "latest" tag. First in the list
                // rather than by date: the order the author put them in is the
                // order that gets published.
                'latestSlug'   => isset($versions[0]['slug']) && 1 === $current ? $versions[0]['slug'] : '',
                'instance'     => 'darkify-changelog-' . wp_rand(),
            ));
        }

        /* --------------------------------------------------------------- */
        /* Sanitising                                                      */
        /* --------------------------------------------------------------- */

        /**
         * Attribute defaults. Kept in PHP as well as block.json so the
         * shortcode path and a partially-specified block both render.
         *
         * @return array
         */
        public static function defaults()
        {
            return array(
                'schemaVersion'    => 1,
                'source'           => '',
                'versions'         => array(),
                'categories'       => Darkify_Util_Changelog_Parser::default_categories(),
                'versionsPosition' => 'right',
                'perPage'          => 10,
                'paginationType'   => 'load-more',
                'loadMoreText'     => __('Load More', 'darkify-util'),
                'showDates'        => true,
                'showBadges'       => true,
                'showSearch'       => false,
                'showFilters'      => true,
                'collapsible'      => false,
            );
        }

        /**
         * Sanitise every attribute, on save and again on render.
         *
         * Twice on purpose: the editor writes what it believes, and the render
         * path trusts nothing — post_content is editable by anyone who can edit
         * the post, and this method is the only thing between it and the page.
         *
         * @param array $attributes
         * @return array
         */
        public function sanitize_attributes($attributes)
        {
            $attributes = is_array($attributes) ? $attributes : array();
            $defaults   = self::defaults();
            $clean      = array();

            $clean['schemaVersion'] = isset($attributes['schemaVersion']) ? absint($attributes['schemaVersion']) : 1;
            $clean['source']        = isset($attributes['source']) ? wp_kses_post((string) $attributes['source']) : '';

            $clean['versionsPosition'] = $this->one_of(
                isset($attributes['versionsPosition']) ? $attributes['versionsPosition'] : '',
                array('left', 'right', 'none'),
                $defaults['versionsPosition']
            );

            $clean['paginationType'] = $this->one_of(
                isset($attributes['paginationType']) ? $attributes['paginationType'] : '',
                array('load-more', 'numbered', 'none'),
                $defaults['paginationType']
            );

            $per_page         = isset($attributes['perPage']) ? absint($attributes['perPage']) : $defaults['perPage'];
            $clean['perPage'] = max(1, min(200, $per_page));

            $clean['loadMoreText'] = isset($attributes['loadMoreText']) && '' !== trim((string) $attributes['loadMoreText'])
                ? sanitize_text_field($attributes['loadMoreText'])
                : $defaults['loadMoreText'];

            foreach (array('showDates', 'showBadges', 'showSearch', 'showFilters', 'collapsible') as $flag) {
                $clean[$flag] = isset($attributes[$flag]) ? (bool) $attributes[$flag] : $defaults[$flag];
            }

            $clean['categories'] = $this->sanitize_categories(
                isset($attributes['categories']) ? $attributes['categories'] : array()
            );

            $clean['versions'] = $this->sanitize_versions(
                isset($attributes['versions']) ? $attributes['versions'] : array(),
                $clean['categories']
            );

            return $clean;
        }

        /**
         * @param array $categories
         * @return array
         */
        protected function sanitize_categories($categories)
        {
            $clean = array();

            foreach ((array) $categories as $category) {
                if (!is_array($category) || empty($category['slug'])) {
                    continue;
                }

                $slug = sanitize_key($category['slug']);

                if ('' === $slug || isset($clean[$slug])) {
                    continue;
                }

                $color = isset($category['color']) ? sanitize_hex_color($category['color']) : '';

                $clean[$slug] = array(
                    'slug'  => $slug,
                    'label' => isset($category['label']) && '' !== trim((string) $category['label'])
                        ? sanitize_text_field($category['label'])
                        : ucfirst($slug),
                    'color' => $color ? $color : Darkify_Util_Changelog_Parser::generated_color($slug),
                );
            }

            return $clean ? array_values($clean) : Darkify_Util_Changelog_Parser::default_categories();
        }

        /**
         * @param array $versions
         * @param array $categories
         * @return array
         */
        protected function sanitize_versions($versions, $categories)
        {
            $known = wp_list_pluck($categories, 'slug');
            $clean = array();

            foreach ((array) $versions as $version) {
                if (!is_array($version)) {
                    continue;
                }

                $entries = array();

                foreach ((isset($version['entries']) ? (array) $version['entries'] : array()) as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $text = isset($entry['text']) ? wp_kses_post((string) $entry['text']) : '';

                    if ('' === trim(wp_strip_all_tags($text))) {
                        continue;
                    }

                    $category = isset($entry['category']) ? sanitize_key($entry['category']) : '';

                    // An entry pointing at a category that no longer exists
                    // would render an uncoloured badge; fall back rather than
                    // drop the entry.
                    if (!in_array($category, $known, true)) {
                        $category = isset($known[0]) ? $known[0] : 'uncategorised';
                    }

                    $url   = isset($entry['link']['url']) ? esc_url_raw((string) $entry['link']['url']) : '';
                    $label = isset($entry['link']['label']) ? sanitize_text_field((string) $entry['link']['label']) : '';

                    $entries[] = array(
                        'id'          => $this->sanitize_id(isset($entry['id']) ? $entry['id'] : '', 'e'),
                        'category'    => $category,
                        'sourceLabel' => isset($entry['sourceLabel']) ? sanitize_text_field((string) $entry['sourceLabel']) : '',
                        'text'        => $text,
                        'link'        => array('url' => $url, 'label' => $label),
                    );
                }

                $number = isset($version['version']) ? sanitize_text_field((string) $version['version']) : '';

                if ('' === $number && !$entries) {
                    continue;
                }

                $iso = isset($version['dateISO']) ? trim((string) $version['dateISO']) : '';

                $clean[] = array(
                    'id'      => $this->sanitize_id(isset($version['id']) ? $version['id'] : '', 'v'),
                    'version' => $number,
                    'date'    => isset($version['date']) ? sanitize_text_field((string) $version['date']) : '',
                    'dateISO' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) ? $iso : '',
                    'label'   => isset($version['label']) ? sanitize_text_field((string) $version['label']) : '',
                    'entries' => $entries,
                );
            }

            return $clean;
        }

        /**
         * @param string $id
         * @param string $prefix
         * @return string
         */
        protected function sanitize_id($id, $prefix)
        {
            $id = sanitize_key((string) $id);

            return '' !== $id ? $id : Darkify_Util_Changelog_Parser::id($prefix);
        }

        /**
         * @param string $value
         * @param array  $allowed
         * @param string $fallback
         * @return string
         */
        protected function one_of($value, $allowed, $fallback)
        {
            $value = sanitize_key((string) $value);

            return in_array($value, $allowed, true) ? $value : $fallback;
        }

        /* --------------------------------------------------------------- */
        /* Shortcode                                                       */
        /* --------------------------------------------------------------- */

        /**
         * [darkify_changelog] — the same component, for pages that are not
         * built out of blocks (the demo and showcase pages, mostly).
         *
         * With `post`, the changelog is read from a block already saved on that
         * post, so a showcase page can display the real changelog without a
         * second copy of the data going out of date.
         *
         * @param array $atts
         * @return string
         */
        public function render_shortcode($atts)
        {
            $atts = shortcode_atts(array(
                'post'              => '',
                'versions_position' => '',
                'per_page'          => '',
                'pagination'        => '',
                'show_dates'        => '',
                'show_badges'       => '',
                'show_search'       => '',
                'collapsible'       => '',
                'load_more_text'    => '',
            ), $atts, self::SHORTCODE);

            $attributes = $this->attributes_from_post($atts['post']);

            $map = array(
                'versions_position' => 'versionsPosition',
                'per_page'          => 'perPage',
                'pagination'        => 'paginationType',
                'load_more_text'    => 'loadMoreText',
            );

            foreach ($map as $att => $key) {
                if ('' !== $atts[$att]) {
                    $attributes[$key] = $atts[$att];
                }
            }

            foreach (array('show_dates' => 'showDates', 'show_badges' => 'showBadges', 'show_search' => 'showSearch', 'collapsible' => 'collapsible') as $att => $key) {
                if ('' !== $atts[$att]) {
                    $attributes[$key] = $this->is_truthy($atts[$att]);
                }
            }

            $this->enqueue_assets();

            return $this->render($attributes);
        }

        /**
         * Pull the changelog attributes out of the first darkify-util/changelog
         * block on a post.
         *
         * @param string|int $post_id
         * @return array
         */
        protected function attributes_from_post($post_id)
        {
            $post_id = absint($post_id);

            if (!$post_id) {
                return self::defaults();
            }

            $post = get_post($post_id);

            if (!$post || empty($post->post_content)) {
                return self::defaults();
            }

            $found = array();

            $walk = function ($blocks) use (&$walk, &$found) {
                foreach ($blocks as $block) {
                    if ($found) {
                        return;
                    }
                    if (isset($block['blockName']) && self::BLOCK_NAME === $block['blockName']) {
                        $found = isset($block['attrs']) ? $block['attrs'] : array();
                        return;
                    }
                    if (!empty($block['innerBlocks'])) {
                        $walk($block['innerBlocks']);
                    }
                }
            };

            $walk(parse_blocks($post->post_content));

            return $found ? array_merge(self::defaults(), $found) : self::defaults();
        }

        /**
         * The block's own stylesheet and view module, for the shortcode path.
         *
         * Handles are read back from the registry rather than guessed, so they
         * stay correct whatever WordPress generates them as.
         */
        protected function enqueue_assets()
        {
            $registry = WP_Block_Type_Registry::get_instance();
            $type     = $registry->get_registered(self::BLOCK_NAME);

            if (!$type) {
                return;
            }

            foreach ((array) $type->style_handles as $handle) {
                wp_enqueue_style($handle);
            }

            if (!empty($type->view_script_module_ids) && function_exists('wp_enqueue_script_module')) {
                foreach ((array) $type->view_script_module_ids as $id) {
                    wp_enqueue_script_module($id);
                }
            }

            foreach ((array) $type->view_script_handles as $handle) {
                wp_enqueue_script($handle);
            }
        }

        /**
         * @param string $value
         * @return bool
         */
        protected function is_truthy($value)
        {
            return !in_array(strtolower(trim((string) $value)), array('no', 'false', '0', 'off', ''), true);
        }
    }
}
