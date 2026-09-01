<?php

/**
 * [darkify_collection] and the darkify-util/collection block.
 *
 * A grid of items the author writes into the block itself — no post type, no
 * taxonomy, no queries. It exists because the collections this site publishes
 * (deal roundups, resource lists, "sites using this plugin" showcases) are not
 * posts and never were: they are hand-curated rows that live and die with the
 * page they are on, and registering a CPT for each one buys nothing but an
 * admin menu and a migration.
 *
 * The block is deliberately not about any one of those pages. An item is an
 * image, a title, a line of supporting text, some label/value metadata, any
 * number of categories and a link; a deal card fills in `meta`, a showcase tile
 * fills in `categories` and leaves `meta` empty, and both are the same record.
 * New fields go into `meta` rather than into this class.
 *
 * Like the changelog block this is dynamic — `save()` returns null, everything
 * lives in the block comment's attributes, and switching the plugin off renders
 * nothing rather than dumping JSON at visitors. Markup lives in
 * templates/collection*.php, so the render path here is only ever: sanitise,
 * filter, slice, hand over.
 *
 * Filtering, search and paging all run in PHP, and every control is a real link
 * or a real form pointed at query arguments this class reads back. The view
 * module intercepts them and asks admin-ajax for the same HTML instead, so with
 * JavaScript the grid never reloads the page and without it every control still
 * works. There is one implementation of "which items match", and it is here.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Collection')) {

    class Darkify_Util_Collection
    {
        const SHORTCODE   = 'darkify_collection';
        const BLOCK_NAME  = 'darkify-util/collection';
        const CATEGORY    = 'darkify';
        const AJAX_ACTION = 'darkify_collection_query';
        const NONCE       = 'darkify-collection';

        /**
         * The wrapper's classes.
         *
         * `darkify_ignore` for the same reason the changelog carries it: this
         * block ships its own dark palette (style.scss redefines its custom
         * properties under Darkify's dark-mode class), and letting the engine
         * repaint the subtree as well means two systems colouring the same
         * pixels — card surfaces and accent buttons flatten to the engine's own
         * greys. Excluding the subtree leaves the block's dark mode in charge;
         * the tokens still switch, because they key off the class Darkify puts
         * on <html>.
         */
        const WRAPPER_CLASS = 'darkify-collection darkify_ignore';

        /** Query arguments. Prefixed and matched against a block id, so two
         *  collections on one page do not read each other's state. */
        const ARG_ID       = 'dkc-id';
        const ARG_CATEGORY = 'dkc-cat';
        const ARG_SEARCH   = 'dkc-s';
        const ARG_PAGE     = 'dkc-page';

        /** @var Darkify_Util_Collection|null */
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

            add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'handle_query'));
            add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'handle_query'));

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
         * The build directory is the registration target (not src): it holds
         * the compiled scripts, the copied block.json and the copied render.php.
         */
        public function register_block()
        {
            $build = DARKIFY_UTIL_PATH . 'blocks/collection/build';

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
         * Add the first-party "Darkify" category to the inserter.
         *
         * Guarded on the slug, so it does not matter whether the changelog
         * block or this one gets there first.
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
        /* Templates                                                       */
        /* --------------------------------------------------------------- */

        /**
         * The card designs this block can wear.
         *
         * A template is a slug and one partial that renders a single card, and
         * that is the whole contract. Nothing above this line knows which one is
         * in use: the items, the category filter, the search and both pagers are
         * decided before a template is ever consulted, so switching design
         * changes the presentation and touches nothing else.
         *
         * Adding a fifth is a filter and a partial — no changes here, and none
         * to the query path:
         *
         *     add_filter('darkify_util_collection_templates', function ($templates) {
         *         $templates['timeline'] = array(
         *             'label' => 'Timeline',
         *             'path'  => __DIR__ . '/cards/timeline.php',
         *         );
         *         return $templates;
         *     });
         *
         * The partial is handed $dkc_item (the item), $dkc_view (its derived
         * values — link, target, category labels), $dkc_index and $data.
         *
         * @return array
         */
        public static function templates()
        {
            $cards = DARKIFY_UTIL_PATH . 'templates/collection-cards/';

            $templates = array(
                'default'   => array(
                    'label' => __('Default', 'darkify-util'),
                    'path'  => $cards . 'default.php',
                ),
                'frame'     => array(
                    'label' => __('Framed', 'darkify-util'),
                    'path'  => $cards . 'frame.php',
                ),
                'showcase'  => array(
                    'label' => __('Showcase', 'darkify-util'),
                    'path'  => $cards . 'showcase.php',
                ),
            );

            return apply_filters('darkify_util_collection_templates', $templates);
        }

        /**
         * The partial that draws one card for a template.
         *
         * A template registered without a readable partial falls back to the
         * default rather than fataling: a design disappearing is a bad day, a
         * white page is a worse one.
         *
         * @param string $template Template slug.
         * @return string Absolute path.
         */
        public static function card_partial($template)
        {
            $templates = self::templates();

            if (isset($templates[$template]['path']) && is_readable($templates[$template]['path'])) {
                return $templates[$template]['path'];
            }

            return DARKIFY_UTIL_PATH . 'templates/collection-cards/default.php';
        }

        /**
         * Everything a card partial needs about an item that is not simply a
         * field on it.
         *
         * Derived once, here, rather than in each of four partials: a template
         * is a layout, and working out whether an item has a link or what its
         * categories are called is not layout.
         *
         * @param array $item Item.
         * @param array $data Prepared data.
         * @return array
         */
        public function item_view($item, $data)
        {
            $terms = array();

            foreach ($item['categories'] as $slug) {
                $terms[] = isset($data['categoryMap'][$slug])
                    ? $data['categoryMap'][$slug]
                    : ucwords(str_replace('-', ' ', $slug));
            }

            return array(
                'hasLink'  => '' !== $item['url'],
                'target'   => $data['openInNewTab'] ? ' target="_blank" rel="noopener noreferrer"' : '',
                'label'    => '' !== $item['linkLabel'] ? $item['linkLabel'] : $data['buttonText'],
                'terms'    => $data['showCategory'] ? $terms : array(),
                'allTerms' => $terms,
                'hasImage' => $data['showImage'] && '' !== $item['image']['url'],
            );
        }

        /**
         * An item's image, as markup.
         *
         * Attachments go through wp_get_attachment_image() so the card gets a
         * srcset — these grids are three across on a desktop and one on a phone,
         * and a full-size upload in a 380px slot is the single biggest thing
         * that would slow the page down. A plain URL (a shortcode, an import)
         * still renders, just without the sizes.
         *
         * @param array  $item  Item.
         * @param array  $data  Prepared data.
         * @param string $class Class for the <img>.
         * @return string
         */
        public function item_image($item, $data, $class = 'darkify-collection__image')
        {
            if ('' === $item['image']['url']) {
                return '';
            }

            if ($item['image']['id']) {
                return (string) wp_get_attachment_image(
                    $item['image']['id'],
                    'large',
                    false,
                    array(
                        'class'   => $class,
                        'alt'     => $item['image']['alt'],
                        'loading' => 'lazy',
                        'sizes'   => '(max-width: 600px) 100vw, (max-width: 960px) 50vw, '
                            . floor(100 / max(1, (int) $data['columns'])) . 'vw',
                    )
                );
            }

            return sprintf(
                '<img class="%1$s" src="%2$s" alt="%3$s" loading="lazy">',
                esc_attr($class),
                esc_url($item['image']['url']),
                esc_attr($item['image']['alt'])
            );
        }

        /* --------------------------------------------------------------- */
        /* Rendering                                                       */
        /* --------------------------------------------------------------- */

        /**
         * Render the block. Called from blocks/collection/build/render.php.
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
         * @param array  $state      Explicit view state, or null to read the URL.
         * @return string
         */
        public function render($attributes, $wrapper = '', $state = null)
        {
            $data = $this->prepare($this->sanitize_attributes($attributes), $state);

            if ('' === $wrapper) {
                $wrapper = 'class="' . esc_attr(self::WRAPPER_CLASS) . '"';
            }

            $data['wrapper'] = $wrapper;

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/collection.php';
            return ob_get_clean();
        }

        /**
         * Just the cards for one page of results — what Load More appends and
         * what numbered pagination swaps in.
         *
         * @param array $data Prepared data.
         * @return string
         */
        public function render_items($data)
        {
            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/collection-items.php';
            return ob_get_clean();
        }

        /**
         * Just the numbered pagination.
         *
         * @param array $data Prepared data.
         * @return string
         */
        public function render_pagination($data)
        {
            if ('numbered' !== $data['paginationType'] || $data['totalPages'] < 2) {
                return '';
            }

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/collection-pagination.php';
            return ob_get_clean();
        }

        /**
         * Everything the templates need that is not simply an attribute: the
         * category table with counts, which items match, and which of those are
         * on this page.
         *
         * @param array $attributes Sanitised attributes.
         * @param array $state      Explicit view state, or null to read the URL.
         * @return array
         */
        public function prepare($attributes, $state = null)
        {
            $items = $attributes['items'];

            // Categories an item points at but the category list has lost still
            // get a chip, built from the slug. Dropping them instead would hide
            // items behind a filter nothing can select.
            $categories = $this->categories_with_counts($attributes['categories'], $items);

            if (null === $state) {
                $state = $this->state_from_request($attributes['blockId']);
            }

            $state = $this->sanitize_state($state, $categories);

            $matches  = $this->filter_items($items, $state['category'], $state['search'], $attributes['categories']);
            $total    = count($matches);
            $per_page = max(1, (int) $attributes['perPage']);
            $paged    = 'none' !== $attributes['paginationType'];
            $pages    = $paged ? max(1, (int) ceil($total / $per_page)) : 1;
            $page     = min(max(1, (int) $state['page']), $pages);

            // Load More is cumulative on a page render: page 3 means "the first
            // three pages", so a visitor who pressed the button twice and then
            // reloaded (or shared the URL) does not lose what they loaded. The
            // one exception is the request the button itself makes, which asks
            // for `append` and gets back only the batch it is about to add.
            $cumulative = 'load-more' === $attributes['paginationType'] && !$state['append'];

            if ($paged) {
                $offset = $cumulative ? 0 : ($page - 1) * $per_page;
                $length = $cumulative ? $page * $per_page : $per_page;
                $slice  = array_slice($matches, $offset, $length);
            } else {
                $slice = $matches;
            }

            return array_merge($attributes, array(
                'items'        => $slice,
                'categories'   => $categories,
                'categoryMap'  => wp_list_pluck($categories, 'label', 'slug'),
                'total'        => $total,
                'totalItems'   => count($items),
                'totalPages'   => $pages,
                'currentPage'  => $page,
                'hasMore'      => $paged && $page < $pages,
                'state'        => $state,
                'baseUrl'      => remove_query_arg(
                    array(self::ARG_ID, self::ARG_CATEGORY, self::ARG_SEARCH, self::ARG_PAGE)
                ),
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce(self::NONCE),
                'postId'       => (int) get_the_ID(),
                'instance'     => 'darkify-collection-' . wp_rand(),
                'emptyMessage' => '' !== $attributes['emptyText']
                    ? $attributes['emptyText']
                    : __('No items match your filters.', 'darkify-util'),
            ));
        }

        /* --------------------------------------------------------------- */
        /* Filtering                                                       */
        /* --------------------------------------------------------------- */

        /**
         * The one definition of "which items match".
         *
         * Category and search are an AND: picking Business and typing "studio"
         * means the Business items whose text mentions studio, not the union of
         * the two. Both are applied to the whole collection, never to the page
         * on screen, so paging cannot hide a match.
         *
         * @param array  $items      Items.
         * @param string $category   Category slug, or 'all'.
         * @param string $search     Search term.
         * @param array  $categories Category table, for matching labels.
         * @return array
         */
        public function filter_items($items, $category, $search, $categories = array())
        {
            $labels = wp_list_pluck($categories, 'label', 'slug');
            $term   = $this->normalise($search);

            $matches = array();

            foreach ($items as $item) {
                if ('all' !== $category && !in_array($category, $item['categories'], true)) {
                    continue;
                }

                if ('' !== $term && false === strpos($this->haystack($item, $labels), $term)) {
                    continue;
                }

                $matches[] = $item;
            }

            return $matches;
        }

        /**
         * Everything about an item a search should be able to find it by.
         *
         * Category *labels* are in here as well as the item's own text, so
         * typing "agency" finds the agency items whether or not any of them says
         * the word — which is what someone typing into a box above a row of
         * category chips expects.
         *
         * @param array $item   Item.
         * @param array $labels Slug => label.
         * @return string
         */
        protected function haystack($item, $labels)
        {
            $parts = array(
                $item['title'],
                $item['subtitle'],
                $item['badge'],
                wp_strip_all_tags($item['description']),
            );

            foreach ($item['meta'] as $meta) {
                $parts[] = $meta['label'];
                $parts[] = $meta['value'];
            }

            foreach ($item['categories'] as $slug) {
                $parts[] = isset($labels[$slug]) ? $labels[$slug] : $slug;
            }

            return $this->normalise(implode(' ', $parts));
        }

        /**
         * Lower-case and collapse whitespace, so a term typed with a stray
         * double space still matches.
         *
         * @param string $value
         * @return string
         */
        protected function normalise($value)
        {
            $value = wp_strip_all_tags((string) $value);
            $value = preg_replace('/\s+/u', ' ', $value);

            return trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
        }

        /**
         * The category table the filter bar renders from: the author's own
         * categories, plus any slug an item still references, each with the
         * number of items filed under it.
         *
         * Counts are of the whole collection rather than the current results.
         * A chip reading "Business 8" has to mean the same thing before and
         * after a search, or the bar becomes unreadable as it changes under you.
         *
         * @param array $categories
         * @param array $items
         * @return array
         */
        protected function categories_with_counts($categories, $items)
        {
            $counts = array();

            foreach ($items as $item) {
                foreach ($item['categories'] as $slug) {
                    $counts[$slug] = isset($counts[$slug]) ? $counts[$slug] + 1 : 1;
                }
            }

            $table = array();

            foreach ($categories as $category) {
                $table[$category['slug']] = array(
                    'slug'  => $category['slug'],
                    'label' => $category['label'],
                    'count' => isset($counts[$category['slug']]) ? $counts[$category['slug']] : 0,
                );
            }

            foreach ($counts as $slug => $count) {
                if (!isset($table[$slug])) {
                    $table[$slug] = array(
                        'slug'  => $slug,
                        'label' => ucwords(str_replace('-', ' ', $slug)),
                        'count' => $count,
                    );
                }
            }

            return array_values($table);
        }

        /* --------------------------------------------------------------- */
        /* View state                                                      */
        /* --------------------------------------------------------------- */

        /**
         * Read category, search and page out of the URL.
         *
         * Only when the request names this block. Without that, two collections
         * on one page would both answer to `dkc-cat` and page in lockstep.
         *
         * @param string $block_id
         * @return array
         */
        protected function state_from_request($block_id)
        {
            $blank = array('category' => 'all', 'search' => '', 'page' => 1);

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state in a GET link.
            if ('' === $block_id || !isset($_GET[self::ARG_ID])) {
                return $blank;
            }

            if (sanitize_key(wp_unslash($_GET[self::ARG_ID])) !== $block_id) {
                return $blank;
            }

            return array(
                'category' => isset($_GET[self::ARG_CATEGORY]) ? sanitize_key(wp_unslash($_GET[self::ARG_CATEGORY])) : 'all',
                'search'   => isset($_GET[self::ARG_SEARCH]) ? sanitize_text_field(wp_unslash($_GET[self::ARG_SEARCH])) : '',
                'page'     => isset($_GET[self::ARG_PAGE]) ? absint(wp_unslash($_GET[self::ARG_PAGE])) : 1,
            );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
        }

        /**
         * @param array $state
         * @param array $categories
         * @return array
         */
        protected function sanitize_state($state, $categories)
        {
            $state = is_array($state) ? $state : array();

            $category = isset($state['category']) ? sanitize_key($state['category']) : 'all';

            // A slug that no longer exists falls back to All rather than showing
            // an empty grid with no chip lit up to explain why.
            if ('all' !== $category && !in_array($category, wp_list_pluck($categories, 'slug'), true)) {
                $category = 'all';
            }

            $search = isset($state['search']) ? sanitize_text_field($state['search']) : '';

            return array(
                'category' => $category,
                // Capped so a pathological query string cannot be turned into a
                // long scan across every field of every item.
                'search'   => function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120),
                'page'     => isset($state['page']) ? max(1, absint($state['page'])) : 1,
                'append'   => !empty($state['append']),
            );
        }

        /**
         * A link that carries this block's view state — the href behind every
         * chip, page number and Load More button.
         *
         * These are what makes the grid work with JavaScript switched off: the
         * view module intercepts the click, and a browser that cannot follows
         * the link to a server-rendered page in the same state.
         *
         * @param array $data      Prepared data.
         * @param array $overrides State to change.
         * @return string
         */
        public static function link($data, $overrides = array())
        {
            $state = array_merge($data['state'], $overrides);
            $args  = array(self::ARG_ID => $data['blockId']);

            if ('all' !== $state['category']) {
                $args[self::ARG_CATEGORY] = $state['category'];
            }

            if ('' !== $state['search']) {
                $args[self::ARG_SEARCH] = $state['search'];
            }

            if ((int) $state['page'] > 1) {
                $args[self::ARG_PAGE] = (int) $state['page'];
            }

            $url = add_query_arg($args, $data['baseUrl']);

            return $url . '#' . $data['instance'];
        }

        /* --------------------------------------------------------------- */
        /* AJAX                                                            */
        /* --------------------------------------------------------------- */

        /**
         * Answer a filter, search or page change with rendered HTML.
         *
         * Two ways in, and the difference is who is trusted with the items:
         *
         * - The front end sends a post id and a block id, and the items are read
         *   back out of that post's content. Nothing about the collection comes
         *   from the request, so no visitor can inject a card.
         * - The editor sends the attributes directly, because the block it is
         *   previewing may never have been saved. That path requires the
         *   capability to edit posts, and the attributes still go through the
         *   same sanitiser before anything is rendered.
         */
        public function handle_query()
        {
            check_ajax_referer(self::NONCE, 'nonce');

            $block_id = isset($_POST['blockId']) ? sanitize_key(wp_unslash($_POST['blockId'])) : '';
            $post_id  = isset($_POST['postId']) ? absint(wp_unslash($_POST['postId'])) : 0;

            $state = array(
                'category' => isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : 'all',
                'search'   => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
                'page'     => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
                // Set by the Load More button, which wants the next batch on its
                // own rather than every page up to it repeated.
                'append'   => isset($_POST['append']) && '1' === $_POST['append'],
            );

            $attributes = null;

            if (isset($_POST['attributes']) && current_user_can('edit_posts')) {
                // wp_unslash before decoding: WordPress has already slashed the
                // JSON, and json_decode() on a slashed string returns null.
                $decoded = json_decode(wp_unslash($_POST['attributes']), true);

                if (is_array($decoded)) {
                    $attributes = $decoded;
                }
            }

            if (null === $attributes) {
                $attributes = $this->attributes_from_post($post_id, $block_id);

                if (null === $attributes) {
                    wp_send_json_error(array('message' => __('That collection could not be found.', 'darkify-util')), 404);
                }
            }

            $data = $this->prepare($this->sanitize_attributes($attributes), $state);

            wp_send_json_success(array(
                'items'      => $this->render_items($data),
                'pagination' => $this->render_pagination($data),
                'page'       => $data['currentPage'],
                'totalPages' => $data['totalPages'],
                'total'      => $data['total'],
                'hasMore'    => $data['hasMore'],
                'empty'      => 0 === $data['total'],
                'status'     => $this->results_status($data),
                'url'        => self::link($data),
            ));
        }

        /**
         * The line read out to screen readers after a filter, search or page.
         *
         * @param array $data Prepared data.
         * @return string
         */
        protected function results_status($data)
        {
            if (0 === $data['total']) {
                return $data['emptyMessage'];
            }

            // Load More reports everything on screen, not the batch that just
            // arrived: "Showing 3 of 40" after pressing the button twice would be
            // read out as though the first two batches had gone away.
            $shown = 'load-more' === $data['paginationType']
                ? min($data['currentPage'] * $data['perPage'], $data['total'])
                : count($data['items']);

            return sprintf(
                /* translators: 1: number of items shown, 2: total number of matching items. */
                _n('Showing %1$s of %2$s item.', 'Showing %1$s of %2$s items.', $data['total'], 'darkify-util'),
                number_format_i18n($shown),
                number_format_i18n($data['total'])
            );
        }

        /**
         * Read a collection block's attributes back out of a post.
         *
         * Matched on the block's own id, so the right one of several
         * collections on a page answers. A post the visitor cannot read is
         * treated as no post at all.
         *
         * @param int    $post_id
         * @param string $block_id
         * @return array|null
         */
        protected function attributes_from_post($post_id, $block_id)
        {
            if (!$post_id) {
                return null;
            }

            $post = get_post($post_id);

            if (!$post || empty($post->post_content)) {
                return null;
            }

            if ('publish' !== $post->post_status && !current_user_can('read_post', $post_id)) {
                return null;
            }

            $found = null;

            $walk = function ($blocks) use (&$walk, &$found, $block_id) {
                foreach ($blocks as $block) {
                    if (null !== $found) {
                        return;
                    }

                    if (isset($block['blockName']) && self::BLOCK_NAME === $block['blockName']) {
                        $attrs = isset($block['attrs']) ? $block['attrs'] : array();
                        $id    = isset($attrs['blockId']) ? $attrs['blockId'] : '';

                        // An empty requested id means "the first one", which is
                        // what the shortcode path and pre-id content need.
                        if ('' === $block_id || $id === $block_id) {
                            $found = $attrs;
                            return;
                        }
                    }

                    if (!empty($block['innerBlocks'])) {
                        $walk($block['innerBlocks']);
                    }
                }
            };

            $walk(parse_blocks($post->post_content));

            return $found;
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
                'schemaVersion'     => 1,
                'blockId'           => '',
                'items'             => array(),
                'categories'        => array(),

                'template'          => 'default',
                'columns'           => 3,
                'columnsTablet'     => 2,
                'columnsMobile'     => 1,
                'gap'               => 24,
                'contentAlign'      => 'left',

                'showFilters'       => true,
                'showFilterCounts'  => true,
                'filterAlign'       => 'center',
                'allLabel'          => __('All', 'darkify-util'),
                'showSearch'        => true,
                'searchPlaceholder' => __('Search…', 'darkify-util'),

                'perPage'           => 9,
                'paginationType'    => 'load-more',
                'loadMoreText'      => __('Load More', 'darkify-util'),

                'showImage'         => true,
                'imageRatio'        => '16:9',
                'imageFit'          => 'cover',
                'showBadge'         => true,
                'showCategory'      => true,
                'showSubtitle'      => true,
                'showMeta'          => true,
                'showDescription'   => true,
                'showButton'        => true,
                'buttonText'        => __('Learn More', 'darkify-util'),
                'openInNewTab'      => false,

                'cardStyle'         => 'boxed',
                'radius'            => 12,
                'hoverEffect'       => 'lift',
                'titleSize'         => 0,
                'cardBackground'    => '',
                'cardBorderColor'   => '',
                'titleColor'        => '',
                'accentColor'       => '',

                'emptyText'         => '',
            );
        }

        /**
         * Sanitise every attribute, on render.
         *
         * The render path trusts nothing: post_content is editable by anyone who
         * can edit the post, the shortcode path takes attributes from a
         * different post entirely, and the editor's AJAX path posts them over
         * the wire. This method is the only thing between all three and the page.
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
            $clean['blockId']       = isset($attributes['blockId']) ? sanitize_key($attributes['blockId']) : '';

            // Checked against the registry rather than a literal list, so a
            // template added by a filter is a valid saved value straight away.
            $template = isset($attributes['template']) ? sanitize_key($attributes['template']) : '';
            $clean['template'] = isset(self::templates()[$template]) ? $template : $defaults['template'];

            $clean['contentAlign'] = $this->one_of(
                isset($attributes['contentAlign']) ? $attributes['contentAlign'] : '',
                array('left', 'center'),
                $defaults['contentAlign']
            );

            $clean['filterAlign'] = $this->one_of(
                isset($attributes['filterAlign']) ? $attributes['filterAlign'] : '',
                array('left', 'center', 'right'),
                $defaults['filterAlign']
            );

            $clean['paginationType'] = $this->one_of(
                isset($attributes['paginationType']) ? $attributes['paginationType'] : '',
                array('none', 'load-more', 'numbered'),
                $defaults['paginationType']
            );

            $clean['imageRatio'] = $this->one_of(
                isset($attributes['imageRatio']) ? str_replace(array(':', '/'), '-', (string) $attributes['imageRatio']) : '',
                array('16-9', '4-3', '3-2', '1-1', '3-4', 'auto'),
                '16-9'
            );

            $clean['imageFit'] = $this->one_of(
                isset($attributes['imageFit']) ? $attributes['imageFit'] : '',
                array('cover', 'contain'),
                $defaults['imageFit']
            );

            $clean['cardStyle'] = $this->one_of(
                isset($attributes['cardStyle']) ? $attributes['cardStyle'] : '',
                array('boxed', 'flat'),
                $defaults['cardStyle']
            );

            $clean['hoverEffect'] = $this->one_of(
                isset($attributes['hoverEffect']) ? $attributes['hoverEffect'] : '',
                array('none', 'lift', 'zoom'),
                $defaults['hoverEffect']
            );

            $clean['columns']       = $this->clamp(isset($attributes['columns']) ? $attributes['columns'] : null, 1, 6, $defaults['columns']);
            $clean['columnsTablet'] = $this->clamp(isset($attributes['columnsTablet']) ? $attributes['columnsTablet'] : null, 1, 4, $defaults['columnsTablet']);
            $clean['columnsMobile'] = $this->clamp(isset($attributes['columnsMobile']) ? $attributes['columnsMobile'] : null, 1, 2, $defaults['columnsMobile']);
            $clean['gap']           = $this->clamp(isset($attributes['gap']) ? $attributes['gap'] : null, 0, 96, $defaults['gap']);
            $clean['perPage']       = $this->clamp(isset($attributes['perPage']) ? $attributes['perPage'] : null, 1, 100, $defaults['perPage']);
            $clean['radius']        = $this->clamp(isset($attributes['radius']) ? $attributes['radius'] : null, 0, 40, $defaults['radius']);
            $clean['titleSize']     = $this->clamp(isset($attributes['titleSize']) ? $attributes['titleSize'] : null, 0, 64, 0);

            foreach (array(
                'showFilters',
                'showFilterCounts',
                'showSearch',
                'showImage',
                'showBadge',
                'showCategory',
                'showSubtitle',
                'showMeta',
                'showDescription',
                'showButton',
                'openInNewTab',
            ) as $flag) {
                $clean[$flag] = isset($attributes[$flag]) ? (bool) $attributes[$flag] : $defaults[$flag];
            }

            foreach (array('allLabel', 'searchPlaceholder', 'loadMoreText', 'buttonText') as $text) {
                $clean[$text] = isset($attributes[$text]) && '' !== trim((string) $attributes[$text])
                    ? sanitize_text_field($attributes[$text])
                    : $defaults[$text];
            }

            $clean['emptyText'] = isset($attributes['emptyText'])
                ? sanitize_text_field((string) $attributes['emptyText'])
                : '';

            foreach (array('cardBackground', 'cardBorderColor', 'titleColor', 'accentColor') as $color) {
                $clean[$color] = isset($attributes[$color]) ? $this->sanitize_color($attributes[$color]) : '';
            }

            $clean['categories'] = $this->sanitize_categories(
                isset($attributes['categories']) ? $attributes['categories'] : array()
            );

            $clean['items'] = $this->sanitize_items(
                isset($attributes['items']) ? $attributes['items'] : array()
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

                $clean[$slug] = array(
                    'slug'  => $slug,
                    'label' => isset($category['label']) && '' !== trim((string) $category['label'])
                        ? sanitize_text_field($category['label'])
                        : ucwords(str_replace('-', ' ', $slug)),
                );
            }

            return array_values($clean);
        }

        /**
         * Sanitise the items.
         *
         * Every key the model defines is normalised to its type here — which is
         * also where the promise that unknown fields are safe to add is kept: a
         * key this version has never heard of is dropped rather than printed,
         * and the extensible surface is `meta`, which is label/value text.
         *
         * @param array $items
         * @return array
         */
        protected function sanitize_items($items)
        {
            $clean = array();

            foreach ((array) $items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $title       = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
                $description = isset($item['description']) ? wp_kses_post((string) $item['description']) : '';
                $image       = isset($item['image']) && is_array($item['image']) ? $item['image'] : array();

                // An item with nothing to show at all is a leftover row in the
                // editor, not a card.
                if ('' === $title && '' === trim(wp_strip_all_tags($description)) && empty($image['url'])) {
                    continue;
                }

                $categories = array();

                foreach ((isset($item['categories']) ? (array) $item['categories'] : array()) as $slug) {
                    $slug = sanitize_key($slug);

                    if ('' !== $slug && !in_array($slug, $categories, true)) {
                        $categories[] = $slug;
                    }
                }

                $meta = array();

                foreach ((isset($item['meta']) ? (array) $item['meta'] : array()) as $pair) {
                    if (!is_array($pair)) {
                        continue;
                    }

                    $label = isset($pair['label']) ? sanitize_text_field((string) $pair['label']) : '';
                    $value = isset($pair['value']) ? sanitize_text_field((string) $pair['value']) : '';

                    if ('' === $label && '' === $value) {
                        continue;
                    }

                    $meta[] = array('label' => $label, 'value' => $value);
                }

                $clean[] = array(
                    'id'          => isset($item['id']) && '' !== sanitize_key($item['id'])
                        ? sanitize_key($item['id'])
                        : 'i' . $index,
                    'title'       => $title,
                    'subtitle'    => isset($item['subtitle']) ? sanitize_text_field((string) $item['subtitle']) : '',
                    'description' => $description,
                    'badge'       => isset($item['badge']) ? sanitize_text_field((string) $item['badge']) : '',
                    'image'       => array(
                        'id'  => isset($image['id']) ? absint($image['id']) : 0,
                        'url' => isset($image['url']) ? esc_url_raw((string) $image['url']) : '',
                        'alt' => isset($image['alt']) ? sanitize_text_field((string) $image['alt']) : '',
                    ),
                    'categories'  => $categories,
                    'url'         => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
                    'linkLabel'   => isset($item['linkLabel']) ? sanitize_text_field((string) $item['linkLabel']) : '',
                    'meta'        => $meta,
                );
            }

            return $clean;
        }

        /**
         * Hex, rgb()/rgba(), or a theme palette slug — the three things the
         * colour controls can hand back. Anything else becomes ''.
         *
         * @param string $value
         * @return string
         */
        protected function sanitize_color($value)
        {
            $value = trim((string) $value);

            if ('' === $value) {
                return '';
            }

            $hex = sanitize_hex_color($value);

            if ($hex) {
                return $hex;
            }

            if (preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/i', $value)) {
                return $value;
            }

            if (preg_match('/^var\(--wp--preset--color--[a-z0-9-]+\)$/i', $value)) {
                return $value;
            }

            return '';
        }

        /**
         * @param mixed $value
         * @param int   $min
         * @param int   $max
         * @param int   $fallback
         * @return int
         */
        protected function clamp($value, $min, $max, $fallback)
        {
            if (null === $value || '' === $value || !is_numeric($value)) {
                return $fallback;
            }

            return (int) min($max, max($min, (int) $value));
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
         * [darkify_collection] — the same component, for pages that are not
         * built out of blocks.
         *
         * With `post`, the collection is read from a block already saved on that
         * post, so a landing page can show the same roundup as the page that
         * owns it without a second copy of the data going out of date.
         *
         * @param array $atts
         * @return string
         */
        public function render_shortcode($atts)
        {
            $atts = shortcode_atts(array(
                'post'        => '',
                'id'          => '',
                'template'    => '',
                'columns'     => '',
                'per_page'    => '',
                'pagination'  => '',
                'show_search' => '',
                'show_filters' => '',
                'category'    => '',
            ), $atts, self::SHORTCODE);

            $attributes = $this->attributes_from_post(absint($atts['post']), sanitize_key($atts['id']));

            if (null === $attributes) {
                return '';
            }

            $attributes = array_merge(self::defaults(), $attributes);

            $map = array(
                'template'   => 'template',
                'columns'    => 'columns',
                'per_page'   => 'perPage',
                'pagination' => 'paginationType',
            );

            foreach ($map as $att => $key) {
                if ('' !== $atts[$att]) {
                    $attributes[$key] = $atts[$att];
                }
            }

            foreach (array('show_search' => 'showSearch', 'show_filters' => 'showFilters') as $att => $key) {
                if ('' !== $atts[$att]) {
                    $attributes[$key] = $this->is_truthy($atts[$att]);
                }
            }

            $this->enqueue_assets();

            // A `category` on the shortcode is a starting filter, not a lock:
            // the visitor can still pick another chip.
            $state = '' !== $atts['category']
                ? array('category' => sanitize_key($atts['category']), 'search' => '', 'page' => 1)
                : null;

            return $this->render($attributes, '', $state);
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
