<?php

/**
 * [darkify_social_share] and the darkify-util/social-share block.
 *
 * Share buttons for the post being rendered. Every one is an ordinary link to
 * the network's own share endpoint with the post's URL in a query argument —
 * no SDKs, no third-party script, nothing loaded from Facebook or X, and so
 * nothing that can track a reader who never clicks.
 *
 * Instagram is the exception and is worth stating plainly: it has no web share
 * endpoint at all — nothing to link to, by design. Its button copies the post
 * URL to the clipboard and opens Instagram, which is the workflow it actually
 * supports (paste into a story, a caption, or the profile link). That is the
 * only reason this block ships a view script.
 *
 * Dynamic for the same reason the AI Summarize block is: the links name the
 * page they sit on, so a block placed in a template has to resolve them per
 * post at render time rather than freezing one post's URL into every one.
 *
 * Styling is entirely custom properties written onto the wrapper, so an author
 * can restyle every button from the inspector without a line of CSS, and a
 * theme can override any of them with one rule.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Social_Share')) {

    class Darkify_Util_Social_Share
    {
        const SHORTCODE  = 'darkify_social_share';
        const BLOCK_NAME = 'darkify-util/social-share';
        const CATEGORY   = 'darkify';

        /**
         * The wrapper's classes.
         *
         * `darkify_ignore` for the same reason every other block in this plugin
         * carries it: the block ships its own dark palette, and letting the
         * engine repaint the subtree too means two systems colouring the same
         * pixels — brand-coloured buttons flatten to the engine's greys.
         */
        const WRAPPER_CLASS = 'darkify-share darkify_ignore';

        /** Placeholders in a network's share URL. */
        const TOKEN_URL   = '{url}';
        const TOKEN_TITLE = '{title}';
        const TOKEN_SITE  = '{site}';

        /** @var Darkify_Util_Social_Share|null */
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

            if (function_exists('get_block_categories')) {
                add_filter('block_categories_all', array($this, 'register_block_category'), 10, 1);
            } else {
                add_filter('block_categories', array($this, 'register_block_category'), 10, 1);
            }
        }

        /* --------------------------------------------------------------- */
        /* The networks                                                    */
        /* --------------------------------------------------------------- */

        /**
         * Every network this block knows how to share to.
         *
         * `url` is the share endpoint with placeholders; each one is filled in
         * and encoded at render time. An empty `url` means the network has no
         * share endpoint — Instagram — and the button copies instead.
         *
         * `color` is the brand's own, `colorDark` what it becomes on a dark
         * page (X's black would otherwise be a hole in the row). `icon` is the
         * brand mark as a path in a 24×24 box, drawn in currentColor.
         *
         * @return array<string, array> Keyed by slug, in the order they ship.
         */
        public static function networks()
        {
            return array(
                'facebook' => array(
                    'slug'      => 'facebook',
                    'label'     => 'Facebook',
                    'url'       => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                    'color'     => '#1877f2',
                    'colorDark' => '#4a94f6',
                    'icon'      => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
                ),
                'twitter' => array(
                    'slug'      => 'twitter',
                    'label'     => 'Twitter',
                    'url'       => 'https://twitter.com/intent/tweet?url={url}&text={title}',
                    'color'     => '#0f1419',
                    'colorDark' => '#e7e9ea',
                    'icon'      => 'M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z',
                ),
                'instagram' => array(
                    'slug'      => 'instagram',
                    'label'     => 'Instagram',
                    /*
                     * Empty on purpose. Instagram publishes no share endpoint —
                     * there is no address that opens a composer with a link in
                     * it — so this button copies the URL and opens Instagram
                     * rather than pretending to share. See render_network().
                     */
                    'url'       => '',
                    'color'     => '#e4405f',
                    'colorDark' => '#f2708a',
                    'icon'      => 'M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077',
                ),
                'linkedin' => array(
                    'slug'      => 'linkedin',
                    'label'     => 'LinkedIn',
                    'url'       => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
                    'color'     => '#0a66c2',
                    'colorDark' => '#3f92e3',
                    'icon'      => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
                ),
                'whatsapp' => array(
                    'slug'      => 'whatsapp',
                    'label'     => 'WhatsApp',
                    'url'       => 'https://api.whatsapp.com/send?text={title}%20{url}',
                    'color'     => '#25d366',
                    'colorDark' => '#3ddc7f',
                    'icon'      => 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z',
                ),
                'email' => array(
                    'slug'      => 'email',
                    'label'     => 'Email',
                    'url'       => 'mailto:?subject={title}&body={url}',
                    'color'     => '#5b6479',
                    'colorDark' => '#98a1b5',
                    // An envelope rather than a brand: email is not a company.
                    'icon'      => 'M1.5 4.5h21A1.5 1.5 0 0 1 24 6v12a1.5 1.5 0 0 1-1.5 1.5h-21A1.5 1.5 0 0 1 0 18V6a1.5 1.5 0 0 1 1.5-1.5Zm.75 3.19V17.25h19.5V7.69l-9.284 6.19a1.5 1.5 0 0 1-1.664 0L2.25 7.69Zm.98-1.44L12 12.53l8.77-6.28H3.23Z',
                ),
            );
        }

        /**
         * The networks a fresh block starts with, in display order.
         *
         * @return array
         */
        public static function default_networks()
        {
            $list = array();

            foreach (self::networks() as $slug => $network) {
                $list[] = array(
                    'slug'    => $slug,
                    'label'   => $network['label'],
                    'enabled' => true,
                );
            }

            return $list;
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
            $build = DARKIFY_UTIL_PATH . 'blocks/social-share/build';

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

            if ($type && !empty($type->view_script_handles)) {
                foreach ($type->view_script_handles as $handle) {
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
         * Render the block. Called from blocks/social-share/build/render.php.
         *
         * The custom properties go *through* get_block_wrapper_attributes()
         * rather than beside it: both write a `style` attribute, an element only
         * gets one, and a browser keeps the first — so anything added alongside
         * is silently dropped the moment an author touches Spacing.
         *
         * @param array         $attributes
         * @param string        $content
         * @param WP_Block|null $block
         * @return string
         */
        public function render_block($attributes, $content = '', $block = null)
        {
            if (!function_exists('get_block_wrapper_attributes')) {
                return $this->render($attributes);
            }

            $wrapper = get_block_wrapper_attributes(array(
                'class' => self::WRAPPER_CLASS,
                'style' => $this->custom_properties($this->sanitize_attributes($attributes)),
            ));

            return $this->render($attributes, $wrapper);
        }

        /**
         * [darkify_social_share].
         *
         * Attributes are the block's in snake_case, with `networks` a
         * comma-separated list of slugs whose order is the order rendered:
         * [darkify_social_share networks="facebook,whatsapp" show_labels="1"].
         *
         * @param array $atts
         * @return string
         */
        public function render_shortcode($atts)
        {
            $atts = shortcode_atts(array(
                'networks'     => '',
                'style'        => '',
                'shape'        => '',
                'colors'       => '',
                'show_labels'  => '',
                'show_icons'   => '',
                'align'        => '',
                'gap'          => '',
                'icon_size'    => '',
                'radius'       => '',
                'url'          => '',
            ), (array) $atts, self::SHORTCODE);

            $attributes = array();

            foreach (array(
                'style' => 'itemStyle',
                'shape'  => 'itemShape',
                'colors' => 'colorMode',
                'align' => 'contentAlign',
                'url'   => 'targetUrl',
            ) as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = $atts[$att];
                }
            }

            foreach (array(
                'gap'       => 'gap',
                'icon_size' => 'iconSize',
                'radius'    => 'itemRadius',
            ) as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = (int) $atts[$att];
                }
            }

            foreach (array('show_labels' => 'showLabels', 'show_icons' => 'showIcons') as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = in_array(strtolower($atts[$att]), array('1', 'true', 'yes', 'on'), true);
                }
            }

            if ('' !== $atts['networks']) {
                // A written-out list is the whole list: the slugs named are the
                // ones shown, in the order named, and everything else is off.
                $known    = self::networks();
                $networks = array();

                foreach (array_map('trim', explode(',', $atts['networks'])) as $slug) {
                    $slug = sanitize_key($slug);

                    if (isset($known[$slug])) {
                        $networks[] = array(
                            'slug'    => $slug,
                            'label'   => $known[$slug]['label'],
                            'enabled' => true,
                        );
                    }
                }

                if ($networks) {
                    $attributes['networks'] = $networks;
                }
            }

            return $this->render($attributes);
        }

        /**
         * Render from anywhere: block, shortcode, or a template calling in.
         *
         * A caller passing its own `$wrapper` owns the whole attribute string,
         * custom properties included — build them with custom_properties() and
         * put them in that one `style` attribute. The template adds none of its
         * own, because a second one would be ignored.
         *
         * @param array  $attributes Raw (unsanitised) attributes.
         * @param string $wrapper    Pre-built wrapper attributes, or ''.
         * @return string
         */
        public function render($attributes, $wrapper = '')
        {
            $clean = $this->sanitize_attributes($attributes);
            $data  = $this->prepare($clean);

            if (empty($data['items'])) {
                return '';
            }

            if ('' === $wrapper) {
                $properties = $this->custom_properties($clean);

                $wrapper = 'class="' . esc_attr(self::WRAPPER_CLASS) . '"'
                    . ('' !== $properties ? ' style="' . esc_attr($properties) . '"' : '');
            }

            $data['wrapper'] = $wrapper;

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/social-share.php';
            return ob_get_clean();
        }

        /**
         * Every style setting, as the custom properties style.scss reads.
         *
         * Only settings the author actually touched are written. Left out, the
         * var() fallbacks in the stylesheet stand — which is what keeps an
         * untouched block looking like the design instead of like a pile of
         * defaults restated in the page.
         *
         * @param array $attributes Sanitised attributes.
         * @return string Declarations, or '' if there are none.
         */
        public function custom_properties($attributes)
        {
            $properties = array(
                '--darkify-share-gap'       => $attributes['gap'] . 'px',
                '--darkify-share-radius'    => $this->radius($attributes),
                '--darkify-share-icon-size' => $attributes['iconSize'] . 'px',
            );

            $optional = array(
                '--darkify-share-border-width' => '' !== $attributes['itemBorderWidth']
                    ? $attributes['itemBorderWidth'] . 'px'
                    : '',
            );

            /*
             * In brand mode the colours are simply not written, and the
             * stylesheet's var() fallbacks — each button's own brand colour —
             * stand. The author's custom colours stay saved while that is on,
             * so switching back to custom brings them back rather than making
             * someone pick all six again. That is the whole point of the mode
             * being a switch instead of six reset buttons.
             */
            if ('custom' === $attributes['colorMode']) {
                $optional = array_merge($optional, array(
                    '--darkify-share-bg'           => $attributes['itemBackground'],
                    '--darkify-share-bg-hover'     => $attributes['itemBackgroundHover'],
                    '--darkify-share-color'        => $attributes['itemColor'],
                    '--darkify-share-color-hover'  => $attributes['itemColorHover'],
                    '--darkify-share-border'       => $attributes['itemBorderColor'],
                    '--darkify-share-border-hover' => $attributes['itemBorderColorHover'],
                ));
            }

            foreach ($optional as $property => $value) {
                if ('' !== $value) {
                    $properties[$property] = $value;
                }
            }

            foreach (array('itemPadding' => 'padding', 'itemMargin' => 'margin') as $attribute => $name) {
                foreach ($attributes[$attribute] as $side => $value) {
                    if ('' !== $value) {
                        $properties['--darkify-share-' . $name . '-' . $side] = $value;
                    }
                }
            }

            $declarations = array();

            foreach ($properties as $property => $value) {
                $declarations[] = $property . ': ' . $value;
            }

            return implode('; ', $declarations);
        }

        /**
         * The corner radius, which the shape setting can override.
         *
         * A pill and a circle are both "as round as it gets" — the difference is
         * whether the button is square, which is the labels' doing, not the
         * radius's. So one value serves both.
         *
         * @param array $attributes Sanitised attributes.
         * @return string
         */
        protected function radius($attributes)
        {
            return 'round' === $attributes['itemShape'] ? '999px' : (int) $attributes['itemRadius'] . 'px';
        }

        /**
         * Turn the attributes into the finished buttons.
         *
         * @param array $attributes Sanitised attributes.
         * @return array
         */
        protected function prepare($attributes)
        {
            $known  = self::networks();
            $target = $this->target($attributes['targetUrl']);
            $items  = array();

            foreach ($attributes['networks'] as $network) {
                if (empty($network['enabled']) || !isset($known[$network['slug']])) {
                    continue;
                }

                $definition = $known[$network['slug']];
                $label      = '' !== $network['label'] ? $network['label'] : $definition['label'];

                $items[] = array(
                    'slug'      => $definition['slug'],
                    'label'     => $label,
                    'color'     => $definition['color'],
                    'colorDark' => $definition['colorDark'],
                    'icon'      => $definition['icon'],
                    'href'      => $this->share_url($definition, $target),
                    /* translators: %s: name of the social network, e.g. Facebook. */
                    'aria'      => sprintf(__('Share on %s', 'darkify-util'), $label),
                    // Only the endpoint-less networks carry this; the view
                    // script uses its presence to decide what a click means.
                    'copy'      => '' === $definition['url'] ? $target['url'] : '',
                );
            }

            return array_merge($attributes, array(
                'items'  => $items,
                'target' => $target,
            ));
        }

        /**
         * Fill a network's share endpoint in.
         *
         * Each placeholder is encoded on its own as it is substituted, which is
         * the only way a title containing an ampersand does not end up looking
         * like a second query argument.
         *
         * @param array $network
         * @param array $target
         * @return string
         */
        protected function share_url($network, $target)
        {
            if ('' === $network['url']) {
                // No endpoint: the button copies instead, and the link is a
                // sensible destination for a visitor without JavaScript.
                return 'https://www.instagram.com/';
            }

            return str_replace(
                array(self::TOKEN_URL, self::TOKEN_TITLE, self::TOKEN_SITE),
                array(
                    rawurlencode($target['url']),
                    rawurlencode($target['title']),
                    rawurlencode($target['site']),
                ),
                $network['url']
            );
        }

        /**
         * What is being shared: the URL and title of the page.
         *
         * An explicit `targetUrl` wins — it is how the block gets pointed at a
         * landing page it does not live on. Otherwise the post being rendered,
         * and failing that the address of the request itself, so the block still
         * resolves on an archive or the front page.
         *
         * @param string $override
         * @return array {url, title, site}
         */
        protected function target($override)
        {
            $site = $this->plain_text(get_bloginfo('name'));

            if ('' !== $override) {
                return array('url' => $override, 'title' => '', 'site' => $site);
            }

            $post = get_post();

            if ($post instanceof WP_Post) {
                $url = get_permalink($post);

                if ($url) {
                    return array(
                        'url'   => $url,
                        'title' => $this->plain_text(get_the_title($post)),
                        'site'  => $site,
                    );
                }
            }

            global $wp;

            $path = isset($wp->request) ? $wp->request : '';

            return array(
                'url'   => home_url(user_trailingslashit($path)),
                'title' => $this->plain_text(wp_get_document_title()),
                'site'  => $site,
            );
        }

        /**
         * A string as a person would type it: no markup, no entities.
         *
         * Titles come back HTML-encoded because their usual destination is a
         * page; this one is a query string on its way to a share composer, where
         * "Colors &amp; Fixes" is simply wrong.
         *
         * @param string $text
         * @return string
         */
        protected function plain_text($text)
        {
            $text = wp_strip_all_tags((string) $text);

            return html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset'));
        }

        /* --------------------------------------------------------------- */
        /* Sanitising                                                      */
        /* --------------------------------------------------------------- */

        /**
         * Attribute defaults. Kept in PHP as well as block.json so the shortcode
         * path and a partially-specified block both render.
         *
         * Every style setting defaults to empty rather than to the value the
         * stylesheet uses. Restating them here would mean two places to change
         * when the design changes, and the second one would win.
         *
         * @return array
         */
        public static function defaults()
        {
            return array(
                'schemaVersion' => 1,
                'networks'      => self::default_networks(),
                'targetUrl'     => '',

                'itemStyle'  => 'brand',
                'itemShape'  => 'rounded',
                // Where the Icon Colours come from: each network's own, or
                // the six set below. Brand, because an untouched block should
                // look like share buttons without anyone opening a panel.
                'colorMode'  => 'brand',
                'showLabels' => false,
                'showIcons'  => true,
                'contentAlign' => 'left',

                'gap'        => 8,
                'iconSize'   => 18,
                'itemRadius' => 8,

                'itemBackground'       => '',
                'itemBackgroundHover'  => '',
                'itemColor'            => '',
                'itemColorHover'       => '',
                'itemBorderColor'      => '',
                'itemBorderColorHover' => '',
                'itemBorderWidth'      => '',

                'itemPadding' => array('top' => '', 'right' => '', 'bottom' => '', 'left' => ''),
                'itemMargin'  => array('top' => '', 'right' => '', 'bottom' => '', 'left' => ''),
            );
        }

        /**
         * Sanitise every attribute, on save and again on render.
         *
         * Twice on purpose: the editor writes what it believes, and the render
         * path trusts nothing — post_content is editable by anyone who can edit
         * the post, and this method is the only thing between it and the page.
         *
         * The style values matter most here. They are written into a `style`
         * attribute, so each is matched against what it is allowed to be — a
         * length, a colour, a keyword — rather than merely escaped. Anything
         * else becomes empty, and the stylesheet's own value stands.
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

            $clean['targetUrl'] = isset($attributes['targetUrl'])
                ? esc_url_raw(trim((string) $attributes['targetUrl']))
                : '';

            $clean['itemStyle'] = $this->one_of(
                isset($attributes['itemStyle']) ? $attributes['itemStyle'] : '',
                array('brand', 'soft', 'outline', 'plain'),
                $defaults['itemStyle']
            );

            $clean['itemShape'] = $this->one_of(
                isset($attributes['itemShape']) ? $attributes['itemShape'] : '',
                array('rounded', 'round'),
                $defaults['itemShape']
            );

            $clean['colorMode'] = $this->one_of(
                isset($attributes['colorMode']) ? $attributes['colorMode'] : '',
                array('brand', 'custom'),
                $defaults['colorMode']
            );

            /*
             * Not called `align`: that is WordPress's own alignment attribute,
             * declared by the align support above, and a block cannot have two
             * meanings for one name — a value of "left" here came out as an
             * `alignleft` class on the wrapper.
             */
            $clean['contentAlign'] = $this->one_of(
                isset($attributes['contentAlign']) ? $attributes['contentAlign'] : '',
                array('left', 'center', 'right'),
                $defaults['contentAlign']
            );

            foreach (array('gap' => 100, 'iconSize' => 64, 'itemRadius' => 100) as $name => $max) {
                $value        = isset($attributes[$name]) ? absint($attributes[$name]) : $defaults[$name];
                $clean[$name] = max(0, min($max, $value));
            }

            foreach (array('showLabels', 'showIcons') as $flag) {
                $clean[$flag] = isset($attributes[$flag]) ? (bool) $attributes[$flag] : $defaults[$flag];
            }

            foreach (array(
                'itemBackground',
                'itemBackgroundHover',
                'itemColor',
                'itemColorHover',
                'itemBorderColor',
                'itemBorderColorHover',
            ) as $name) {
                $clean[$name] = $this->color(isset($attributes[$name]) ? $attributes[$name] : '');
            }

            $width = isset($attributes['itemBorderWidth']) ? $attributes['itemBorderWidth'] : '';

            $clean['itemBorderWidth'] = '' === $width || null === $width
                ? ''
                : (string) max(0, min(10, absint($width)));

            foreach (array('itemPadding', 'itemMargin') as $name) {
                $clean[$name] = $this->box(isset($attributes[$name]) ? $attributes[$name] : array());
            }

            $clean['networks'] = $this->sanitize_networks(
                isset($attributes['networks']) ? $attributes['networks'] : array()
            );

            return $clean;
        }

        /**
         * @param array $networks
         * @return array
         */
        protected function sanitize_networks($networks)
        {
            $known = self::networks();
            $clean = array();

            foreach ((array) $networks as $network) {
                if (!is_array($network) || empty($network['slug'])) {
                    continue;
                }

                $slug = sanitize_key($network['slug']);

                // An unknown slug is a network this version has no share URL
                // for; rendering it would produce a button that goes nowhere.
                if (!isset($known[$slug]) || isset($clean[$slug])) {
                    continue;
                }

                $clean[$slug] = array(
                    'slug'    => $slug,
                    'label'   => isset($network['label']) && '' !== trim((string) $network['label'])
                        ? sanitize_text_field($network['label'])
                        : $known[$slug]['label'],
                    'enabled' => !isset($network['enabled']) || (bool) $network['enabled'],
                );
            }

            return $clean ? array_values($clean) : self::default_networks();
        }

        /**
         * The four sides of a padding or margin box.
         *
         * @param mixed $box
         * @return array
         */
        protected function box($box)
        {
            $box   = is_array($box) ? $box : array();
            $clean = array();

            foreach (array('top', 'right', 'bottom', 'left') as $side) {
                $clean[$side] = $this->length(isset($box[$side]) ? $box[$side] : '');
            }

            return $clean;
        }

        /**
         * A CSS length, or '' if it is not one.
         *
         * Deliberately narrow: a number and one of the units the editor can
         * produce. It exists to keep anything else — a var(), a calc(), a
         * closing quote — out of the style attribute this ends up in.
         *
         * @param mixed $value
         * @return string
         */
        protected function length($value)
        {
            $value = trim((string) $value);

            return preg_match('/^-?[0-9]+(\.[0-9]+)?(px|rem|em|%|pt|vw|vh)$/', $value) ? $value : '';
        }

        /**
         * A CSS colour, or '' if it is not one.
         *
         * Three forms, and nothing else: a hex colour, an rgb()/rgba() with
         * numeric arguments, and a `var(--wp--…)` reference, which is what the
         * editor's colour palette stores when a theme preset is picked.
         *
         * rgb()/rgba() is converted to hex rather than passed through, and that
         * is not tidiness. These values end up in the wrapper's style attribute,
         * which WordPress runs through safecss_filter_attr(); it drops any
         * declaration still containing brackets after the handful of functions
         * it knows (var(), calc(), clamp() and friends) have been accounted for.
         * rgba() is not on that list, so an alpha colour chosen in the editor
         * would be silently thrown away between here and the page. Hex carries
         * the same colour, alpha included, with no brackets to be dropped over.
         *
         * @param mixed $value
         * @return string
         */
        protected function color($value)
        {
            $value = trim((string) $value);

            if ('' === $value) {
                return '';
            }

            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
                return $value;
            }

            if (preg_match('/^var\(\s*--wp--[a-z0-9-]+\s*\)$/i', $value)) {
                return $value;
            }

            if (preg_match('/^(transparent|currentColor|inherit)$/i', $value)) {
                return $value;
            }

            return $this->rgb_to_hex($value);
        }

        /**
         * An rgb()/rgba() colour as hex, or '' if it is not one.
         *
         * Both the legacy comma form and the modern space form, since the
         * editor's colour picker has produced each at different versions.
         *
         * @param string $value
         * @return string
         */
        protected function rgb_to_hex($value)
        {
            $pattern = '/^rgba?\(\s*([0-9.]+)\s*[, ]\s*([0-9.]+)\s*[, ]\s*([0-9.]+)\s*(?:[,\/]\s*([0-9.]+)(%?)\s*)?\)$/i';

            if (!preg_match($pattern, $value, $parts)) {
                return '';
            }

            $hex = '#';

            foreach (array($parts[1], $parts[2], $parts[3]) as $channel) {
                $hex .= sprintf('%02x', max(0, min(255, (int) round((float) $channel))));
            }

            if (isset($parts[4]) && '' !== $parts[4]) {
                $alpha = (float) $parts[4];

                // "50%" and "0.5" are the same alpha written two ways.
                if ('%' === $parts[5]) {
                    $alpha /= 100;
                }

                $alpha = max(0, min(1, $alpha));

                // A fully opaque colour needs no alpha pair, and leaving it off
                // keeps the common case to the six digits people recognise.
                if ($alpha < 1) {
                    $hex .= sprintf('%02x', (int) round($alpha * 255));
                }
            }

            return $hex;
        }

        /**
         * @param mixed  $value
         * @param array  $allowed
         * @param string $fallback
         * @return string
         */
        protected function one_of($value, $allowed, $fallback)
        {
            $value = is_string($value) ? $value : '';

            return in_array($value, $allowed, true) ? $value : $fallback;
        }
    }
}
